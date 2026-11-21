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

namespace local_moodlexhub\academic;

use assign;
use context_course;
use stdClass;

/**
 * Assignment due dates and the grading workload of the current teacher.
 */
final class assignment_queue {
    /**
     * Build assignment rows scoped to groups the teacher is allowed to grade.
     *
     * @param stdClass $course Moodle course record.
     * @param int $days Include future deadlines up to this number of days ahead.
     * @param int $coursegroup Group selected on the academic page, or zero.
     * @return array
     */
    public function get_rows(stdClass $course, int $days, int $coursegroup): array {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $rows = [];
        $now = time();
        foreach (get_fast_modinfo($course)->get_instances_of('assign') as $cm) {
            if ($cm->deletioninprogress || !$cm->uservisible || !has_capability('mod/assign:grade', $cm->context)) {
                continue;
            }
            $restricted = groups_get_activity_groupmode($cm) === SEPARATEGROUPS
                && !has_capability('moodle/site:accessallgroups', $cm->context);
            $group = $coursegroup > 0 ? $coursegroup : 0;
            $allowedgroups = groups_get_activity_allowed_groups($cm, $USER->id);
            if (($restricted && !$group) || ($group && !isset($allowedgroups[$group]))) {
                continue;
            }
            $assignment = new assign($cm->context, $cm, $course);
            $instance = $assignment->get_instance();
            if ($instance->duedate > $now + DAYSECS * $days) {
                continue;
            }
            $team = (bool) $instance->teamsubmission;
            $expected = $team ? null : $assignment->count_participants($group);
            $submitted = $assignment->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED, $group);
            $grading = $team ? null : $assignment->count_submissions_need_grading($group);
            $missing = $expected === null ? null : max(0, $expected - $submitted);
            $rows[] = [
                'sort' => $instance->duedate ?: PHP_INT_MAX,
                'name' => html_to_text($cm->get_formatted_name(), 0, false),
                'due' => $instance->duedate ? userdate($instance->duedate) : get_string('never'),
                'submitted' => $submitted,
                'expected' => $expected,
                'missing' => $missing,
                'late' => $instance->duedate && $instance->duedate < $now ? $missing : null,
                'grading' => $grading,
                'url' => (new \moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grading']))->out(false),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);
        return $rows;
    }
}
