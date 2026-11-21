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
use cm_info;
use completion_info;
use context_course;
use mod_quiz\quiz_settings;
use stdClass;

/**
 * Individual activity evidence from permissions and data owned by Moodle modules.
 */
final class activity_workload {
    /**
     * List activities where the actor can inspect individual work for the selected group.
     *
     * @param stdClass $course Current course.
     * @param int $group Resolved course group.
     * @return cm_info[]
     */
    public function activities(stdClass $course, int $group): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->libdir . '/completionlib.php');

        $completion = new completion_info($course);
        $activities = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->deletioninprogress || !$cm->url) {
                continue;
            }
            $capability = match ($cm->modname) {
                'assign' => 'mod/assign:grade',
                'quiz' => 'mod/quiz:viewreports',
                'forum' => 'mod/forum:viewdiscussion',
                default => null,
            };
            if (!$capability || !has_capability($capability, $cm->context)) {
                continue;
            }
            $restricted = (int) groups_get_activity_groupmode($cm) === SEPARATEGROUPS
                && !has_capability('moodle/site:accessallgroups', $cm->context);
            if (
                ($restricted && !$group)
                || ($group && (int) groups_get_activity_groupmode($cm) !== NOGROUPS
                    && !isset(groups_get_activity_allowed_groups($cm, $USER->id)[$group]))
            ) {
                continue;
            }
            if ($cm->modname === 'assign') {
                $assignment = new assign($cm->context, $cm, $course);
                if (
                    $assignment->is_blind_marking() || $assignment->get_instance()->teamsubmission
                    || !$assignment->is_any_submission_plugin_enabled()
                ) {
                    continue;
                }
            }
            if (
                $cm->modname === 'forum' && (!$completion->is_enabled($cm) || !$cm->completionexpected
                    || !has_capability('report/progress:view', context_course::instance($course->id)))
            ) {
                continue;
            }
            $activities[$cm->id] = $cm;
        }
        return $activities;
    }

    /**
     * Determine the state of one learner in an activity already authorized for the actor.
     *
     * @param stdClass $course Current course.
     * @param cm_info $cm Authorized activity.
     * @param int $userid Enrolled learner within the resolved group.
     * @return array{state:string,due:int}
     */
    public function inspect(stdClass $course, cm_info $cm, int $userid): array {
        if (!get_fast_modinfo($course, $userid)->get_cm($cm->id)->uservisible) {
            return ['state' => 'unavailable', 'due' => 0];
        }

        return match ($cm->modname) {
            'assign' => $this->assignment($course, $cm, $userid),
            'quiz' => $this->quiz($cm, $userid),
            'forum' => $this->forum($course, $cm, $userid),
        };
    }

    /**
     * Link to the native activity screen where the teacher can act.
     *
     * @param cm_info $cm Authorized module.
     * @param int $group Resolved group filter.
     * @return string
     */
    public function review_url(cm_info $cm, int $group): string {
        $params = ['id' => $cm->id] + ($group > 0 ? ['group' => $group] : []);
        $url = match ($cm->modname) {
            'assign' => new \moodle_url('/mod/assign/view.php', $params + ['action' => 'grading']),
            'quiz' => new \moodle_url('/mod/quiz/report.php', $params + [
                'mode' => has_capability('mod/quiz:grade', $cm->context) ? 'grading' : 'overview',
            ]),
            default => $cm->url,
        };
        return $url->out(false);
    }

    /**
     * Inspect the current submission and grade using the learner's effective deadline.
     *
     * @param stdClass $course Current course.
     * @param cm_info $cm Assignment.
     * @param int $userid Learner.
     * @return array
     */
    private function assignment(stdClass $course, cm_info $cm, int $userid): array {
        if (!has_capability('mod/assign:submit', $cm->context, $userid)) {
            return ['state' => 'unavailable', 'due' => 0];
        }
        $assignment = new assign($cm->context, $cm, $course);
        $assignment->update_effective_access($userid);
        $instance = $assignment->get_instance($userid);
        $flags = $assignment->get_user_flags($userid, false);
        $due = max((int) $instance->duedate, (int) ($flags->extensionduedate ?? 0));
        $submission = $assignment->get_user_submission($userid, false);
        if (!$submission || $submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            return ['state' => $due && $due < time() ? 'late' : 'pending', 'due' => $due];
        }
        $grade = $assignment->get_user_grade($userid, false, $submission->attemptnumber);
        $needsgrade = (int) $instance->grade !== 0
            && (!$grade || $grade->grade === null || $grade->grade < 0 || $grade->timemodified < $submission->timemodified);
        return ['state' => $needsgrade ? 'grading' : 'done', 'due' => $due];
    }

    /**
     * Inspect submitted quiz attempts and the learner's effective closing time.
     *
     * @param cm_info $cm Quiz.
     * @param int $userid Learner.
     * @return array
     */
    private function quiz(cm_info $cm, int $userid): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        if (!has_capability('mod/quiz:attempt', $cm->context, $userid)) {
            return ['state' => 'unavailable', 'due' => 0];
        }
        $settings = quiz_settings::create_for_cmid($cm->id, $userid);
        if (!$settings->has_questions()) {
            return ['state' => 'unavailable', 'due' => 0];
        }
        $due = (int) $settings->get_quiz()->timeclose;
        $attempts = quiz_get_user_attempts($cm->instance, $userid, 'all');
        $submitted = false;
        $active = false;
        foreach ($attempts as $attempt) {
            if ($attempt->state !== 'finished') {
                $active = $active || in_array($attempt->state, ['inprogress', 'overdue'], true);
                continue;
            }
            $submitted = true;
            if (has_all_capabilities(['mod/quiz:grade', 'quiz/grading:viewstudentnames'], $cm->context)) {
                $usage = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
                foreach ($usage->get_slots() as $slot) {
                    if ($usage->get_question_state($slot) === \question_state::$needsgrading) {
                        return ['state' => 'grading', 'due' => $due];
                    }
                }
            }
        }
        $state = $submitted ? 'done' : (!$active && $due && $due < time() ? 'late' : 'pending');
        return ['state' => $state, 'due' => $due];
    }

    /**
     * Read Moodle's own forum completion state and expected completion date.
     *
     * @param stdClass $course Current course.
     * @param cm_info $cm Forum.
     * @param int $userid Learner.
     * @return array
     */
    private function forum(stdClass $course, cm_info $cm, int $userid): array {
        if (!has_capability('mod/forum:viewdiscussion', $cm->context, $userid)) {
            return ['state' => 'unavailable', 'due' => 0];
        }
        $due = (int) $cm->completionexpected;
        $complete = (new completion_info($course))->get_data($cm, false, $userid)->completionstate != COMPLETION_INCOMPLETE;
        return ['state' => $complete ? 'done' : ($due < time() ? 'late' : 'pending'), 'due' => $due];
    }
}
