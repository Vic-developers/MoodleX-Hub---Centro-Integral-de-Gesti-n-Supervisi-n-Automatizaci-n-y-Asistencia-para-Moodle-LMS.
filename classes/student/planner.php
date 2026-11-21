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

namespace local_moodlexhub\student;

use completion_info;
use context_course;
use context_module;
use context_system;
use context_user;
use core_calendar\local\api;
use core_favourites\service_factory;
use moodle_url;

/**
 * Personal view of active courses, calendar deadlines and Moodle completion.
 */
final class planner {
    /** @var string Moodle capability required for this operation. */
    public const CAPABILITY = 'local/moodlexhub:viewstudent';
    /** @var string Table of private planner notes. */
    public const NOTES = 'local_moodlexhub_note';
    /** @var string Item type stored by the Moodle Favourites API. */
    public const FAVOURITE = 'student_activity';

    /**
     * Require planner access for the current authenticated user.
     */
    public static function require_access(): void {
        \local_moodlexhub\module_config::require_enabled('student');
        require_capability(self::CAPABILITY, context_system::instance());
    }

    /**
     * Get upcoming action events as Moodle supplies them for the current user.
     *
     * @param int $days Number of days to display.
     * @return array
     */
    public function upcoming(int $days): array {
        global $CFG;
        self::require_access();
        require_once($CFG->dirroot . '/calendar/lib.php');

        $now = time();
        $rows = [];
        foreach (api::get_action_events_by_timesort($now, $now + $days * DAYSECS, null, 50, true) as $event) {
            $course = $event->get_course();
            $rows[] = [
                'name' => html_to_text(format_string($event->get_name()), 0, false),
                'date' => userdate($event->get_times()->get_sort_time()->getTimestamp()),
                'url' => (new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $event->get_times()
                    ->get_start_time()->getTimestamp()]))->out(false),
                'course' => $course ? html_to_text(format_string($course->get('fullname')), 0, false) : '',
            ];
        }
        return $rows;
    }

    /**
     * List visible course activities with Moodle completion states.
     *
     * @param int $courseid Active course the user selected.
     * @return array
     */
    public function activities(int $courseid): array {
        global $CFG, $USER;
        self::require_access();
        require_once($CFG->libdir . '/completionlib.php');

        $course = get_course($courseid);
        $context = context_course::instance($courseid);
        if (!is_enrolled($context, $USER, '', true) || !can_access_course($course, $USER, '', true)) {
            throw new \moodle_exception('courseunavailable', 'local_moodlexhub');
        }

        $modinfo = get_fast_modinfo($course);
        $completion = new completion_info($course);
        $favourites = service_factory::get_service_for_user_context(context_user::instance($USER->id));
        $saved = array_column($favourites->find_favourites_by_type('local_moodlexhub', self::FAVOURITE), 'itemid');
        $saved = array_fill_keys($saved, true);
        $rows = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || !$cm->url || $cm->deletioninprogress) {
                continue;
            }
            $tracked = (bool) $completion->is_enabled($cm);
            $state = $tracked ? (int) $completion->get_data($cm, true, $USER->id)->completionstate : null;
            $status = match ($state) {
                COMPLETION_COMPLETE => 'studentdone',
                COMPLETION_COMPLETE_PASS => 'studentpassed',
                COMPLETION_COMPLETE_FAIL, COMPLETION_COMPLETE_FAIL_HIDDEN => 'studentfailed',
                COMPLETION_INCOMPLETE => 'studentpending',
                default => 'studentuntracked',
            };
            $rows[] = [
                'id' => $cm->id,
                'name' => html_to_text($cm->get_formatted_name(), 0, false),
                'url' => $cm->url->out(false),
                'status' => get_string($status, 'local_moodlexhub'),
                'tracked' => $tracked,
                'completed' => in_array($state, [
                    COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS, COMPLETION_COMPLETE_FAIL, COMPLETION_COMPLETE_FAIL_HIDDEN,
                ], true),
                'favourite' => isset($saved[$cm->id]),
            ];
        }
        return $rows;
    }

    /**
     * Change a favourite for an activity the current user can access.
     *
     * @param int $cmid Course module identifier.
     * @param bool $favourite Desired state.
     */
    public function set_favourite(int $cmid, bool $favourite): void {
        global $USER;
        self::require_access();
        $record = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $context = context_course::instance($record->course);
        $course = get_course($record->course);
        $cm = get_fast_modinfo($course)->get_cm($cmid);
        if (
            !is_enrolled($context, $USER, '', true) || !can_access_course($course, $USER, '', true) || !$cm->uservisible
            || $cm->deletioninprogress
        ) {
            throw new \moodle_exception('courseunavailable', 'local_moodlexhub');
        }
        $service = service_factory::get_service_for_user_context(context_user::instance($USER->id));
        $modulecontext = context_module::instance($cmid);
        $saved = $service->favourite_exists('local_moodlexhub', self::FAVOURITE, $cmid, $modulecontext);
        if ($favourite && !$saved) {
            $service->create_favourite('local_moodlexhub', self::FAVOURITE, $cmid, $modulecontext);
        } else if (!$favourite && $saved) {
            $service->delete_favourite('local_moodlexhub', self::FAVOURITE, $cmid, $modulecontext);
        }
    }
}
