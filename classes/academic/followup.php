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

use context_course;
use core\lock\lock_config;
use moodle_exception;
use stdClass;

/**
 * Course- and group-scoped learner cases with a chronological intervention log.
 */
final class followup {
    /** @var string Case table. */
    public const CASES = 'local_moodlexhub_followup';
    /** @var string Intervention table. */
    public const HISTORY = 'local_moodlexhub_followlog';
    /** @var string Capability to view and manage the course queue. */
    public const CAPABILITY = 'local/moodlexhub:managefollowup';
    /** @var int Days after which the last course access warrants review. */
    public const INACTIVITY_DAYS = 14;

    /**
     * Require teacher, participant and follow-up access, then resolve group restrictions.
     *
     * @param stdClass $course Current course.
     * @param int $group Requested group.
     * @return array
     */
    public function scope(stdClass $course, int $group): array {
        $metrics = new course_metrics();
        $context = $metrics->require_access($course, course_metrics::TEACHER);
        require_capability(self::CAPABILITY, $context);
        return $metrics->group_scope($course, $context, $group);
    }

    /**
     * Check for no recorded course access in the review period.
     *
     * @param int $courseid Course identifier.
     * @param int $userid Learner identifier.
     * @return bool
     */
    public function inactive(int $courseid, int $userid): bool {
        global $DB;
        $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', ['courseid' => $courseid, 'userid' => $userid]);
        return (int) $lastaccess < time() - self::INACTIVITY_DAYS * DAYSECS;
    }

    /**
     * Open a case from current Moodle evidence; repeat requests return the existing case.
     *
     * @param stdClass $course Current course.
     * @param int $requestedgroup Group requested by the current actor.
     * @param int $userid Learner.
     * @param int $cmid Course module, or zero for course access.
     * @return int
     */
    public function open(stdClass $course, int $requestedgroup, int $userid, int $cmid): int {
        global $DB;
        [$group] = $this->scope($course, $requestedgroup);
        $this->require_learner($course, $group, $userid);
        $workload = new activity_workload();
        if ($cmid) {
            $activity = $workload->activities($course, $group)[$cmid] ?? null;
            $reason = $activity ? $workload->inspect($course, $activity, $userid)['state'] : '';
            if (!in_array($reason, ['late', 'grading'], true)) {
                throw new moodle_exception('followupunavailable', 'local_moodlexhub');
            }
        } else {
            if (!$this->inactive($course->id, $userid)) {
                throw new moodle_exception('followupunavailable', 'local_moodlexhub');
            }
            $reason = 'inactive';
        }
        $key = ['courseid' => $course->id, 'userid' => $userid, 'cmid' => $cmid, 'reason' => $reason];
        $lock = lock_config::get_lock_factory('local_moodlexhub_followup')->get_lock(implode(':', $key), 5);
        if (!$lock) {
            throw new moodle_exception('followupbusy', 'local_moodlexhub');
        }
        try {
            $existing = $DB->get_field(self::CASES, 'id', $key);
            if ($existing) {
                return (int) $existing;
            }
            $transaction = $DB->start_delegated_transaction();
            $id = $DB->insert_record(self::CASES, $key + ['timecreated' => time(), 'timemodified' => time()]);
            $this->log($id, 'opened', '');
            $transaction->allow_commit();
            return $id;
        } finally {
            $lock->release();
        }
    }

    /**
     * Read a case after rechecking course, group, enrolment and module permissions.
     *
     * @param stdClass $course Current course.
     * @param int $requestedgroup Requested group.
     * @param int $id Case identifier.
     * @return stdClass
     */
    public function get(stdClass $course, int $requestedgroup, int $id): stdClass {
        global $DB;
        [$group] = $this->scope($course, $requestedgroup);
        $case = $DB->get_record(self::CASES, ['id' => $id, 'courseid' => $course->id]);
        if (!$case) {
            throw new moodle_exception('followupunavailable', 'local_moodlexhub');
        }
        $this->require_learner($course, $group, $case->userid);
        if ($case->cmid && !isset((new activity_workload())->activities($course, $group)[$case->cmid])) {
            throw new moodle_exception('followupunavailable', 'local_moodlexhub');
        }
        return $case;
    }

    /**
     * Page the case queue for active learners in the selected group and accessible activities.
     *
     * @param stdClass $course Current course.
     * @param int $requestedgroup Requested group.
     * @param string $status Open or closed.
     * @param int $cmid Filter by this activity when provided.
     * @param int $offset First row.
     * @param int $limit Page size.
     * @return array
     */
    public function queue(stdClass $course, int $requestedgroup, string $status, int $cmid, int $offset, int $limit): array {
        global $DB;
        [$group] = $this->scope($course, $requestedgroup);
        if ($group < 0) {
            return ['total' => 0, 'rows' => []];
        }
        $context = context_course::instance($course->id);
        $activities = array_keys((new activity_workload())->activities($course, $group));
        $allowed = $cmid ? array_intersect([$cmid], $activities) : [0, ...$activities];
        if (!$allowed) {
            return ['total' => 0, 'rows' => []];
        }
        [$enrolledsql, $enrolled] = get_enrolled_sql($context, 'moodle/course:isincompletionreports', $group, true);
        [$insql, $modules] = $DB->get_in_or_equal($allowed, SQL_PARAMS_NAMED, 'followcm');
        $params = $enrolled + $modules + ['followcourse' => $course->id, 'followstatus' => $status];
        $from = " FROM {" . self::CASES . "} c JOIN ($enrolledsql) e ON e.id = c.userid
                    JOIN {user} u ON u.id = c.userid
                   WHERE c.courseid = :followcourse AND c.status = :followstatus AND c.cmid $insql
                     AND u.deleted = 0 AND u.suspended = 0";
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $records = $DB->get_records_sql(
            "SELECT c.id, c.cmid, c.reason, c.status, c.assignee, c.timemodified, $names $from
               ORDER BY c.timemodified DESC, c.id DESC",
            $params,
            $offset,
            $limit,
        );
        return [
            'total' => $DB->count_records_sql("SELECT COUNT(c.id) $from", $params),
            'rows' => array_values($records),
        ];
    }

    /**
     * Update one accessible case with an atomic, logged transition.
     *
     * @param stdClass $course Current course.
     * @param int $requestedgroup Requested group.
     * @param int $id Case identifier.
     * @param string $action Requested action.
     * @param string $note Plain-text intervention.
     */
    public function update(stdClass $course, int $requestedgroup, int $id, string $action, string $note): void {
        global $DB, $USER;
        $note = trim($note);
        if (\core_text::strlen($note) > 2000) {
            throw new moodle_exception('followupnotelength', 'local_moodlexhub');
        }
        $lock = lock_config::get_lock_factory('local_moodlexhub_followup')->get_lock('case:' . $id, 5);
        if (!$lock) {
            throw new moodle_exception('followupbusy', 'local_moodlexhub');
        }
        try {
            $case = $this->get($course, $requestedgroup, $id);
            $allowed = $case->status === 'open' && match ($action) {
                'take' => !$case->assignee,
                'release' => (int) $case->assignee === (int) $USER->id,
                'note', 'close' => $note !== '',
                default => false,
            };
            if ($action === 'reopen') {
                $allowed = $case->status === 'closed' && $note !== '';
            }
            if (!$allowed) {
                throw new moodle_exception('followupunavailable', 'local_moodlexhub');
            }
            $transaction = $DB->start_delegated_transaction();
            $change = (object) ['id' => $id, 'timemodified' => time()];
            if (in_array($action, ['take', 'release'], true)) {
                $change->assignee = $action === 'take' ? $USER->id : null;
            }
            if (in_array($action, ['close', 'reopen'], true)) {
                $change->status = $action === 'close' ? 'closed' : 'open';
                if ($action === 'close') {
                    $change->assignee = null;
                }
            }
            $DB->update_record(self::CASES, $change);
            $this->log($id, $action, $note);
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /**
     * Require an active learner within the actor's selected group.
     *
     * @param stdClass $course Current course.
     * @param int $group Resolved group.
     * @param int $userid Learner.
     */
    private function require_learner(stdClass $course, int $group, int $userid): void {
        $context = context_course::instance($course->id);
        $result = (new course_metrics())->learners($course, $context, $group, 0, 1, 0, $userid);
        if ($userid < 1 || !$result['total']) {
            throw new moodle_exception('followupunavailable', 'local_moodlexhub');
        }
    }

    /**
     * Append an intervention owned by the current actor.
     *
     * @param int $caseid Case identifier.
     * @param string $action Operation.
     * @param string $note Plain-text note.
     */
    private function log(int $caseid, string $action, string $note): void {
        global $DB, $USER;
        $DB->insert_record(self::HISTORY, [
            'caseid' => $caseid, 'actorid' => $USER->id, 'action' => $action, 'note' => $note, 'timecreated' => time(),
        ]);
    }
}
