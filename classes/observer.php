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

use core\event\course_category_deleted;
use core\event\course_deleted;
use core\event\course_module_deleted;
use local_moodlexhub\enrol\imports;
use local_moodlexhub\privacy\followup_records;
use local_moodlexhub\privacy\owned_records;
use local_moodlexhub\quality\auditor;
use local_moodlexhub\report\snapshots;

/**
 * Remove plugin data that belongs to a deleted course.
 */
final class observer {
    /**
     * Remove a deleted category's quality template.
     *
     * @param course_category_deleted $event Category deletion event.
     */
    public static function course_category_deleted(course_category_deleted $event): void {
        unset_config('qualitytemplate_' . $event->objectid, 'local_moodlexhub');
    }

    /**
     * Drop audit records after Moodle deletes their course.
     *
     * @param course_deleted $event Course deletion event.
     */
    public static function course_deleted(course_deleted $event): void {
        global $DB;
        $DB->delete_records(auditor::TABLE, ['courseid' => $event->objectid]);
        imports::delete_course_data($event->objectid);
        owned_records::delete_course($event->objectid);
        followup_records::delete('courseid = :courseid', ['courseid' => $event->objectid]);
        $DB->delete_records(snapshots::TABLE, ['courseid' => $event->objectid]);
    }

    /**
     * Remove follow-up cases for a deleted activity.
     *
     * @param course_module_deleted $event Module deletion event.
     */
    public static function course_module_deleted(course_module_deleted $event): void {
        followup_records::delete('cmid = :cmid', ['cmid' => $event->objectid]);
    }
}
