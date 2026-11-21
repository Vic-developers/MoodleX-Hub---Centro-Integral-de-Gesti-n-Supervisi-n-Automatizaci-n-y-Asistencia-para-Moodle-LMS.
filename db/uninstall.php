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

/**
 * Remove MoodleX Hub's non-table data through Moodle APIs.
 *
 * @return bool
 */
function xmldb_local_moodlexhub_uninstall(): bool {
    global $DB;

    $component = 'local_moodlexhub';
    $fs = get_file_storage();
    $fs->delete_area_files(context_system::instance()->id, $component, \local_moodlexhub\support\tickets::FILEAREA);

    $preferences = \local_moodlexhub\output\dashboard::PREFERENCES;
    foreach ($preferences as $name) {
        $key = $component . '_' . $name;
        do {
            $users = $DB->get_records('user_preferences', ['name' => $key], 'id ASC', 'id,userid', 0, 100);
            foreach ($users as $record) {
                unset_user_preference($key, $record->userid);
            }
        } while (count($users) === 100);
    }

    $favourites = \core_favourites\service_factory::get_service_for_component($component);
    foreach ([\local_moodlexhub\student\planner::FAVOURITE, \local_moodlexhub\feedback\library::FAVOURITE] as $type) {
        $ids = $DB->get_fieldset_sql(
            'SELECT DISTINCT itemid FROM {favourite} WHERE component = :component AND itemtype = :type',
            ['component' => $component, 'type' => $type],
        );
        foreach ($ids as $id) {
            $favourites->delete_favourites_by_type_and_item($type, $id);
        }
    }

    $tasks = [
        \local_moodlexhub\task\audit_batch::class,
        \local_moodlexhub\task\build_report::class,
        \local_moodlexhub\task\deliver_webhook::class,
        \local_moodlexhub\task\enrol_batch::class,
        \local_moodlexhub\task\run_automation::class,
        \local_moodlexhub\task\scan_storage::class,
        \local_moodlexhub\task\ticket_notice::class,
    ];
    foreach ($tasks as $classname) {
        foreach (\core\task\manager::get_adhoc_tasks($classname) as $task) {
            \core\task\manager::delete_adhoc_task($task->get_id());
        }
    }
    return true;
}
