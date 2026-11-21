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
    /** @var string Capability to assign cases to other staff. */
    public const COORDINATE = 'local/moodlexhub:coordinatefollowup';
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
        return ($this->last_access($courseid, $userid) ?: 0) < time() - self::INACTIVITY_DAYS * DAYSECS;
    }

    /**
     * Read the timestamp of the last recorded access.
     *
     * @param int $courseid Course identifier.
     * @param int $userid Learner identifier.
     * @return int
     */
    public function last_access(int $courseid, int $userid): int {
        global $DB;
        return (int) $DB->get_field('user_lastaccess', 'timeaccess', ['courseid' => $courseid, 'userid' => $userid]);
    }

    /**
     * Determine whether new evidence can open a new case after a prior closure.
     *
     * @param stdClass|null $previous Latest prior case.
     * @param int $since Date of the underlying access, deadline or submission evidence.
     * @return bool
     */
    public function is_new_episode(?stdClass $previous, int $since): bool {
        return !$previous || ($previous->status === 'closed' && $since > (int) $previous->timemodified);
    }

    /**
     * Determine whether a closed case is still the most recent episode.
     *
     * @param stdClass $case Authorized case.
     * @return bool
     */
    public function can_reopen(stdClass $case): bool {
        global $DB;
        return $case->status === 'closed' && !$DB->record_exists_select(
            self::CASES,
            'courseid = :courseid AND userid = :userid AND cmid = :cmid AND reason = :reason AND id > :caseid',
            [
                'courseid' => $case->courseid, 'userid' => $case->userid, 'cmid' => $case->cmid,
                'reason' => $case->reason, 'caseid' => $case->id,
            ],
        );
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
            $evidence = $activity ? $workload->inspect($course, $activity, $userid) : [];
            $reason = $evidence['state'] ?? '';
            if (!in_array($reason, ['late', 'grading'], true)) {
                throw new moodle_exception('followupunavailable', 'local_moodlexhub');
            }
            $since = $reason === 'grading' ? ($evidence['since'] ?? 0) : $evidence['due'];
        } else {
            if (!$this->inactive($course->id, $userid)) {
                throw new moodle_exception('followupunavailable', 'local_moodlexhub');
            }
            $reason = 'inactive';
            $since = $this->last_access($course->id, $userid);
        }
        $key = ['courseid' => $course->id, 'userid' => $userid, 'cmid' => $cmid, 'reason' => $reason];
        $lock = lock_config::get_lock_factory('local_moodlexhub_followup')->get_lock(implode(':', $key), 5);
        if (!$lock) {
            throw new moodle_exception('followupbusy', 'local_moodlexhub');
        }
        try {
            $existing = $DB->get_records(self::CASES, $key, 'id DESC', 'id,status,timemodified', 0, 1);
            $previous = $existing ? reset($existing) : null;
            if (!$this->is_new_episode($previous, $since)) {
                return (int) $previous->id;
            }
            $transaction = $DB->start_delegated_transaction();
            $id = $DB->insert_record(self::CASES, $key + ['occurrence' => $since, 'timecreated' => time(), 'timemodified' => time()]);
            $this->log($id, $previous ? 'recurrent' : 'opened', '');
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
     * @param string $status Case state or work queue.
     * @param int $cmid Filter by this activity when provided.
     * @param int $offset First row.
     * @param int $limit Page size.
     * @return array
     */
    public function queue(stdClass $course, int $requestedgroup, string $status, int $cmid, int $offset, int $limit): array {
        global $DB, $USER;
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
        $params = $enrolled + $modules + ['followcourse' => $course->id, 'followstatus' => $status === 'closed' ? 'closed' : 'open'];
        $from = " FROM {" . self::CASES . "} c JOIN ($enrolledsql) e ON e.id = c.userid
                    JOIN {user} u ON u.id = c.userid
                   WHERE c.courseid = :followcourse AND c.status = :followstatus AND c.cmid $insql
                     AND u.deleted = 0 AND u.suspended = 0";
        if ($status === 'mine') {
            $from .= ' AND c.assignee = :followstaff';
            $params['followstaff'] = $USER->id;
        } else if ($status === 'unassigned') {
            $from .= ' AND c.assignee IS NULL';
        } else if ($status === 'overdue') {
            $from .= ' AND c.reviewdue > 0 AND c.reviewdue <= :follownow';
            $params['follownow'] = time();
        } else if ($status === 'stale') {
            $from .= ' AND c.timecreated < :casecutoff AND NOT EXISTS (
                SELECT 1 FROM {' . self::HISTORY . '} h WHERE h.caseid = c.id AND h.timecreated >= :activitycutoff
                AND (h.action = :intervention OR h.action = :communication)
            )';
            $cutoff = time() - WEEKSECS;
            $params += ['casecutoff' => $cutoff, 'activitycutoff' => $cutoff, 'intervention' => 'note', 'communication' => 'message'];
        }
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $records = $DB->get_records_sql(
            "SELECT c.id, c.cmid, c.reason, c.status, c.assignee, c.reviewdue, c.timemodified, $names $from
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
     * @param int $assignee Authorized staff identifier, or zero to clear the assignment.
     * @param int $reviewdue Review date timestamp, or zero to clear it.
     */
    public function update(
        stdClass $course,
        int $requestedgroup,
        int $id,
        string $action,
        string $note,
        int $assignee = 0,
        int $reviewdue = 0,
    ): void {
        global $DB, $USER;
        $note = trim($note);
        if (\core_text::strlen($note) > 2000) {
            throw new moodle_exception('followupnotelength', 'local_moodlexhub');
        }
        $lock = lock_config::get_lock_factory('local_moodlexhub_followup')->get_lock('case:' . $id, 5);
        if (!$lock) {
            throw new moodle_exception('followupbusy', 'local_moodlexhub');
        }
        $episodelock = null;
        try {
            $case = $this->get($course, $requestedgroup, $id);
            $context = context_course::instance($course->id);
            $coordinator = has_capability(self::COORDINATE, $context);
            $group = $action === 'assign' && $coordinator ? $this->scope($course, $requestedgroup)[0] : 0;
            $allowed = $case->status === 'open' && match ($action) {
                'take' => !$case->assignee,
                'release' => (int) $case->assignee === (int) $USER->id,
                'note', 'close' => $note !== '',
                'assign' => $coordinator && (!$assignee || $this->can_handle($course, $case, $assignee, $group)),
                'review' => $coordinator || (int) $case->assignee === (int) $USER->id,
                'message' => $note !== '' && \core_message\api::can_send_message($case->userid, $USER->id),
                default => false,
            };
            if ($action === 'reopen') {
                $key = ['courseid' => $case->courseid, 'userid' => $case->userid, 'cmid' => $case->cmid, 'reason' => $case->reason];
                $episodelock = lock_config::get_lock_factory('local_moodlexhub_followup')->get_lock(implode(':', $key), 5);
                if (!$episodelock) {
                    throw new moodle_exception('followupbusy', 'local_moodlexhub');
                }
                $allowed = $note !== '' && $this->can_reopen($case);
            }
            if (!$allowed) {
                throw new moodle_exception('followupunavailable', 'local_moodlexhub');
            }
            $change = (object) ['id' => $id, 'timemodified' => time()];
            if (in_array($action, ['take', 'release', 'assign'], true)) {
                $change->assignee = $action === 'take' ? $USER->id : ($action === 'assign' && $assignee ? $assignee : null);
                if ((int) $change->assignee === (int) $case->assignee) {
                    return;
                }
                $change->reminded = 0;
            }
            if ($action === 'review') {
                $change->reviewdue = max(0, $reviewdue);
                if ($change->reviewdue === (int) $case->reviewdue) {
                    return;
                }
                $change->reminded = 0;
                $note = $reviewdue ? userdate($reviewdue) : '';
            }
            if (in_array($action, ['close', 'reopen'], true)) {
                $change->status = $action === 'close' ? 'closed' : 'open';
                if ($action === 'close') {
                    $change->assignee = null;
                    $change->reviewdue = 0;
                    $change->reminded = 0;
                }
            }
            $transaction = $DB->start_delegated_transaction();
            if ($action === 'message') {
                $note = $this->send_message($course, $case, $note);
            }
            $DB->update_record(self::CASES, $change);
            $this->log($id, $action, $note);
            $transaction->allow_commit();
        } finally {
            if ($episodelock) {
                $episodelock->release();
            }
            $lock->release();
        }
    }

    /**
     * Check an active staff member's access to the case under their own Moodle permissions.
     *
     * @param stdClass $course Current course.
     * @param stdClass $case Case being assigned.
     * @param int $userid Staff member.
     * @param int $group Requested group, or zero to find a shared group.
     * @return bool
     */
    public function can_handle(stdClass $course, stdClass $case, int $userid, int $group = 0): bool {
        global $USER;
        $context = context_course::instance($course->id);
        $staff = \core_user::get_user($userid);
        if (
            !$staff || $staff->deleted || $staff->suspended || !is_enrolled($context, $staff, '', true)
            || !has_all_capabilities([self::CAPABILITY, course_metrics::TEACHER, 'moodle/course:viewparticipants'], $context, $userid)
        ) {
            return false;
        }
        $current = $USER;
        try {
            \core\session\manager::set_user($staff);
            $groups = $group ? [$group] : array_merge([0], array_keys(groups_get_all_groups($course->id, $case->userid)));
            foreach ($groups as $candidate) {
                try {
                    $this->get($course, $candidate, $case->id);
                    return true;
                } catch (moodle_exception $exception) {
                    // Try the next candidate group without relaxing Moodle permissions.
                    continue;
                }
            }
            return false;
        } finally {
            \core\session\manager::set_user($current);
        }
    }

    /**
     * Send only the explicitly approved message and a learner-visible Moodle link.
     *
     * @param stdClass $course Course record.
     * @param stdClass $case Accessible case.
     * @param string $body Teacher's reviewed message.
     * @return string Text sent for the communication history.
     */
    private function send_message(stdClass $course, stdClass $case, string $body): string {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/message/lib.php');
        $recipient = \core_user::get_user($case->userid, '*', MUST_EXIST);
        $url = new \moodle_url('/course/view.php', ['id' => $course->id]);
        if ($case->cmid) {
            $module = get_fast_modinfo($course, $recipient->id)->get_cm($case->cmid);
            if ($module->uservisible && $module->url) {
                $url = $module->url;
            }
        }
        $text = $body . "\n\n" . $url->out(false);
        $previous = $DB->get_records(self::HISTORY, [
            'caseid' => $case->id, 'actorid' => $USER->id, 'action' => 'message',
        ], 'id DESC', 'id,note,timecreated', 0, 1);
        $latest = $previous ? reset($previous) : null;
        if ($latest && $latest->note === $text && $latest->timecreated > time() - MINSECS) {
            throw new moodle_exception('followupmessageduplicate', 'local_moodlexhub');
        }
        if (!message_post_message($USER, $recipient, $text, FORMAT_PLAIN)) {
            throw new moodle_exception('followupmessagefailed', 'local_moodlexhub');
        }
        return $text;
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
