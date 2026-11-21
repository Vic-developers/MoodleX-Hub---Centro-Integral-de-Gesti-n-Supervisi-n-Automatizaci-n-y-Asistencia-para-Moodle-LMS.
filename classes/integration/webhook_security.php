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

use core\files\curl_security_helper;

/**
 * Restrict webhook egress to configured public HTTPS destinations with DNS pinning.
 */
final class webhook_security extends curl_security_helper {
    /** @var string[] Pins for the URL most recently validated. */
    private array $pins = [];

    /**
     * Validate URL structure and the administrator's exact host allowlist.
     *
     * @param string $url Webhook destination.
     * @return string|null Safe host name, or null.
     */
    public static function allowed_host(string $url): ?string {
        global $CFG;
        if (!filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (
            ($parts['scheme'] ?? '') !== 'https' || ($parts['port'] ?? 443) !== 443
            || isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])
            || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host)
        ) {
            return null;
        }
        $hosts = array_map('strtolower', (array) ($CFG->local_moodlexhub_webhook_hosts ?? []));
        return in_array($host, $hosts, true) ? $host : null;
    }

    #[\Override]
    public function url_is_blocked($urlstring, $notused = null): bool {
        $this->pins = [];
        $host = self::allowed_host($urlstring);
        if (!$host || parent::url_is_blocked($urlstring)) {
            return true;
        }
        $ips = [];
        $corepins = parent::get_resolve_info();
        foreach ($corepins as $pin) {
            $parts = explode(':', $pin, 3);
            foreach (explode(',', $parts[2] ?? '') as $ip) {
                $ips[] = trim($ip, '[]');
            }
        }
        if (!$ips) {
            $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                } else if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }
        if (!$ips) {
            return true;
        }
        $resolved = [];
        foreach (array_unique($ips) as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
                return true;
            }
            $resolved[] = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
        }
        $this->pins = [$host . ':443:' . implode(',', $resolved)];
        return false;
    }

    #[\Override]
    public function get_resolve_info(): array {
        return $this->pins;
    }
}
