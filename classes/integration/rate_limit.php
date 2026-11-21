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

use cache;
use core\lock\lock_config;

/**
 * Bound API requests per Moodle user and minute across worker processes.
 */
final class rate_limit {
    /**
     * Check and increment the current request window.
     */
    public static function check(): void {
        \local_moodlexhub\module_config::require_enabled('integrations');
        global $USER;
        $key = 'u' . $USER->id;
        $lock = lock_config::get_lock_factory('local_moodlexhub_api')->get_lock($key, 2);
        if (!$lock) {
            throw new \moodle_exception('apiratelimited', 'local_moodlexhub');
        }
        try {
            $cache = cache::make('local_moodlexhub', 'apirate');
            $current = intdiv(time(), 60);
            $entry = $cache->get($key);
            $used = $entry && $entry['minute'] === $current ? $entry['used'] : 0;
            if ($used >= 60) {
                throw new \moodle_exception('apiratelimited', 'local_moodlexhub');
            }
            $cache->set($key, ['minute' => $current, 'used' => $used + 1]);
        } finally {
            $lock->release();
        }
    }
}
