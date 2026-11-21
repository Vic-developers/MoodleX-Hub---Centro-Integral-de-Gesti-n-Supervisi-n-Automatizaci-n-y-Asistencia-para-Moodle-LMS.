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

/**
 * Site-level feature switches. Privacy and retention run independently.
 */
final class module_config {
    /** @var string[] Language keys indexed by module key. */
    public const LABELS = [
        'courses' => 'courses', 'users' => 'userdirectory', 'quality' => 'quality', 'analytics' => 'courseanalytics',
        'grades' => 'gradesdiagnostics', 'storage' => 'storage', 'teacher' => 'teacherpanel', 'student' => 'studentplanner',
        'support' => 'support', 'questions' => 'questioncatalogue', 'monitoring' => 'monitoring', 'automation' => 'automation',
        'assistant' => 'assistant', 'feedback' => 'feedback', 'integrations' => 'integrations', 'reports' => 'reports',
    ];

    /**
     * Check the site switch for a module.
     *
     * @param string $module Module key.
     * @return bool
     */
    public static function enabled(string $module): bool {
        if (!isset(self::LABELS[$module])) {
            throw new \coding_exception('Unknown MoodleX Hub module.');
        }
        if ($module === 'assistant') {
            return (bool) get_config('local_moodlexhub', 'assistantenabled');
        }
        $status = get_config('local_moodlexhub', 'enable_' . $module);
        return $status === false || (bool) $status;
    }

    /**
     * Stop requests or jobs for a disabled module.
     *
     * @param string $module Module key.
     */
    public static function require_enabled(string $module): void {
        if (!self::enabled($module)) {
            throw new \moodle_exception(
                'moduledisabled',
                'local_moodlexhub',
                '',
                get_string(self::LABELS[$module], 'local_moodlexhub')
            );
        }
    }
}
