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

namespace local_moodlexhub;

use advanced_testcase;
use local_moodlexhub\integration\webhook_security;
use local_moodlexhub\integration\webhooks;

/**
 * Check webhook host control and at-rest protection without external traffic.
 */
final class webhook_security_test extends advanced_testcase {
    /**
     * Only an exact allowlisted host over HTTPS may be used.
     */
    public function test_webhook_url_constraints(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->local_moodlexhub_webhook_hosts = ['receiver.example.test'];

        $this->assertSame('receiver.example.test', webhook_security::allowed_host('https://receiver.example.test/events'));
        $this->assertNull(webhook_security::allowed_host('http://receiver.example.test/events'));
        $this->assertNull(webhook_security::allowed_host('https://receiver.example.test:8443/events'));
        $this->assertNull(webhook_security::allowed_host('https://receiver.example.test/events?sample=value'));
        $this->assertNull(webhook_security::allowed_host('https://other.example.test/events'));
    }

    /**
     * The configured master key protects the receiver's shared secret.
     */
    public function test_webhook_secret_is_encrypted(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->local_moodlexhub_webhook_key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $plain = random_bytes(35);
        $first = webhooks::encrypt($plain);
        $second = webhooks::encrypt($plain);
        $this->assertNotSame($first, $second);
        $this->assertSame($plain, webhooks::decrypt($first));
    }
}
