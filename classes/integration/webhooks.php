<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * MoodleX Hub.
 *
 * @package    local_moodlexhub
 * @copyright  2026 MoodleX
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlexhub\integration;

use core\task\manager;
use local_moodlexhub\task\deliver_webhook;

/**
 * Encrypted webhook configuration and idempotent outgoing deliveries.
 */
final class webhooks {
    /** @var string Table of configured webhook destinations. */
    public const ENDPOINTS = 'local_moodlexhub_endpoint';
    /** @var string Table of outbound delivery outcomes. */
    public const LOG = 'local_moodlexhub_api_log';
    /** @var string Capability to administer this module. */
    public const MANAGE = 'local/moodlexhub:manageintegrations';
    /** @var string[] Event topics that can be delivered. */
    public const TOPICS = ['quality', 'storage'];

    /**
     * Authorize access to endpoint and credential administration.
     */
    public static function require_management(): void {
        \local_moodlexhub\module_config::require_enabled('integrations');
        require_all_capabilities([self::MANAGE, 'moodle/site:config'], \context_system::instance());
    }

    /**
     * Encrypt a purpose-specific HMAC secret with a server-side master key.
     *
     * @param string $secret Sender/receiver shared secret, 32–256 bytes.
     * @return string Encrypted configuration value.
     */
    public static function encrypt(string $secret): string {
        if (strlen($secret) < 32 || strlen($secret) > 256) {
            throw new \moodle_exception('webhooksecretlength', 'local_moodlexhub');
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($secret, $nonce, self::key()));
    }

    /**
     * Return the master key configured outside the database.
     *
     * @return string Binary key.
     */
    private static function key(): string {
        global $CFG;
        $key = base64_decode((string) ($CFG->local_moodlexhub_webhook_key ?? ''), true);
        if (!$key || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \moodle_exception('webhookkeymissing', 'local_moodlexhub');
        }
        return $key;
    }

    /**
     * Usability check for configuration without exposing the key.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        global $CFG;
        try {
            self::key();
            return !empty($CFG->local_moodlexhub_webhook_hosts);
        } catch (\moodle_exception $exception) {
            return false;
        }
    }

    /**
     * Decrypt a saved receiver secret for signing a request.
     *
     * @param string $cipher Encoded encrypted value.
     * @return string
     */
    public static function decrypt(string $cipher): string {
        $raw = base64_decode($cipher, true);
        $size = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (!$raw || strlen($raw) < $size + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new \moodle_exception('webhookkeymissing', 'local_moodlexhub');
        }
        $value = sodium_crypto_secretbox_open(substr($raw, $size), substr($raw, 0, $size), self::key());
        if ($value === false) {
            throw new \moodle_exception('webhookkeymissing', 'local_moodlexhub');
        }
        return $value;
    }

    /**
     * Publish metadata for a successful internal operation.
     *
     * @param string $topic Allowlisted event type.
     * @param int $reference Source operation ID used only for deduplication.
     */
    public static function publish(string $topic, int $reference): void {
        global $DB;
        if (
            !\local_moodlexhub\module_config::enabled('integrations') || !in_array($topic, self::TOPICS, true) || !self::is_configured()
            || !get_config('local_moodlexhub', 'webhooksenabled')
        ) {
            return;
        }
        $endpoints = $DB->get_records(self::ENDPOINTS, ['enabled' => 1, 'topic' => $topic], 'id ASC', 'id');
        foreach ($endpoints as $endpoint) {
            $filter = ['endpointid' => $endpoint->id, 'topic' => $topic, 'reference' => $reference];
            if ($DB->record_exists(self::LOG, $filter)) {
                continue;
            }
            $transaction = $DB->start_delegated_transaction();
            $id = $DB->insert_record(self::LOG, (object) ($filter + [
                'status' => 'queued', 'attempts' => 0, 'timecreated' => time(), 'timemodified' => time(),
            ]));
            $task = new deliver_webhook();
            $task->set_custom_data(['deliveryid' => $id]);
            $task->set_attempts_available(3);
            manager::queue_adhoc_task($task, true);
            $transaction->allow_commit();
        }
    }

    /**
     * Deliver a stable event ID and signed JSON envelope.
     *
     * @param int $deliveryid Outbox entry.
     * @param int $remaining Remaining attempts reported by Moodle.
     */
    public static function send(int $deliveryid, int $remaining): void {
        global $CFG, $DB;
        $entry = $DB->get_record(self::LOG, ['id' => $deliveryid]);
        if (!$entry || !in_array($entry->status, ['queued', 'retry'], true)) {
            return;
        }
        $endpoint = $DB->get_record(self::ENDPOINTS, ['id' => $entry->endpointid, 'enabled' => 1, 'topic' => $entry->topic]);
        if (
            !$endpoint || !\local_moodlexhub\module_config::enabled('integrations')
            || !get_config('local_moodlexhub', 'webhooksenabled') || !webhook_security::allowed_host($endpoint->url)
        ) {
            self::update($deliveryid, 'blocked', (int) $entry->attempts);
            return;
        }
        try {
            $secret = self::decrypt($endpoint->cipher);
        } catch (\moodle_exception $exception) {
            self::update($deliveryid, 'blocked', (int) $entry->attempts);
            return;
        }

        $sent = time();
        $id = 'mlx-' . $deliveryid;
        $body = json_encode([
            'id' => $id,
            'event' => 'moodlexhub.' . $entry->topic . '.completed',
            'occurred_at' => (int) $entry->timecreated,
        ], JSON_THROW_ON_ERROR);
        $attempt = (int) $entry->attempts + 1;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl(['proxy' => false, 'securityhelper' => new webhook_security()]);
        $curl->setHeader([
            'Content-Type: application/json',
            'X-MoodleX-Id: ' . $id,
            'X-MoodleX-Timestamp: ' . $sent,
            'X-MoodleX-Signature: sha256=' . hash_hmac('sha256', $sent . '.' . $body, $secret),
        ]);
        sodium_memzero($secret);
        $curl->post($endpoint->url, $body, [
            'CURLOPT_FOLLOWLOCATION' => 0,
            'CURLOPT_PROXY' => '',
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_TIMEOUT' => 12,
            'CURLOPT_WRITEFUNCTION' => static fn($handle, string $data): int => strlen($data),
        ]);
        $http = (int) ($curl->get_info()['http_code'] ?? 0);
        if (!$curl->get_errno() && $http >= 200 && $http < 300) {
            self::update($deliveryid, 'finished', $attempt, $http);
            return;
        }
        $retry = $remaining > 1 && (!$http || $http >= 500 || $http === 408 || $http === 429);
        self::update($deliveryid, $retry ? 'retry' : 'failed', $attempt, $http);
        if ($retry) {
            throw new \moodle_exception('webhookdeliveryfailed', 'local_moodlexhub');
        }
    }

    /**
     * Record only the outcome, attempt count, timestamp and response status code.
     *
     * @param int $id Outbox row.
     * @param string $status Outcome.
     * @param int $attempts Total requests attempted.
     * @param int|null $http HTTP status code.
     */
    private static function update(int $id, string $status, int $attempts, ?int $http = null): void {
        global $DB;
        $DB->update_record(self::LOG, (object) [
            'id' => $id, 'status' => $status, 'attempts' => $attempts, 'httpcode' => $http, 'timemodified' => time(),
        ]);
    }
}
