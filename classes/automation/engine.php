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

namespace local_moodlexhub\automation;

use context_course;
use context_system;
use core\lock\lock_config;
use core\message\message;
use core\task\manager;
use local_moodlexhub\academic\course_metrics;
use local_moodlexhub\course\catalog;
use local_moodlexhub\quality\auditor;
use local_moodlexhub\report\builder;
use local_moodlexhub\report\snapshots;
use local_moodlexhub\storage\scan;
use local_moodlexhub\support\tickets;
use local_moodlexhub\task\run_automation;
use stdClass;

/**
 * Evaluate allowlisted conditions and dispatch operations as the rule owner.
 */
final class engine {
    /** @var string Moodle capability required for this operation. */
    public const CAPABILITY = 'local/moodlexhub:manageautomation';
    /** @var string Table of saved automation rules. */
    public const RULES = 'local_moodlexhub_auto';
    /** @var string Table of persisted operation results. */
    public const RUNS = 'local_moodlexhub_auto_run';
    /** @var string[] Supported rule conditions. */
    public const CONDITIONS = ['always', 'noteachers', 'inactive', 'storage', 'duedates', 'tickets'];
    /** @var string[] Supported rule actions. */
    public const ACTIONS = ['audit', 'scan', 'report', 'notify'];

    /**
     * Find the course and validate the authority of the current rule owner.
     *
     * @param stdClass $rule Rule configuration.
     * @return stdClass|null Course when the rule is course scoped.
     */
    public function require_scope(stdClass $rule): ?stdClass {
        \local_moodlexhub\module_config::require_enabled('automation');
        require_capability(self::CAPABILITY, context_system::instance());
        if (!in_array($rule->conditiontype, self::CONDITIONS, true) || !in_array($rule->actiontype, self::ACTIONS, true)) {
            throw new \invalid_parameter_exception();
        }
        $needs = in_array($rule->conditiontype, ['noteachers', 'inactive', 'storage', 'duedates'], true)
            || in_array($rule->actiontype, ['audit', 'scan'], true);
        if ($needs && !$rule->courseid) {
            throw new \moodle_exception('automationneedscope', 'local_moodlexhub');
        }
        $course = $rule->courseid ? get_course($rule->courseid) : null;
        if ($course) {
            (new catalog())->require_course($course);
        }
        if ($rule->conditiontype === 'tickets') {
            \local_moodlexhub\module_config::require_enabled('support');
            require_capability(tickets::MANAGE, context_system::instance());
        }
        if ($rule->conditiontype === 'storage' || $rule->actiontype === 'scan') {
            (new scan())->require_access($course);
        }
        if ($rule->actiontype === 'audit') {
            (new auditor())->require_access($course);
        }
        if ($rule->conditiontype === 'inactive') {
            $context = (new course_metrics())->require_access($course, course_metrics::ANALYTICS);
            if (groups_get_course_groupmode($course) === SEPARATEGROUPS) {
                require_capability('moodle/site:accessallgroups', $context);
            }
        }
        if ($rule->actiontype === 'report') {
            (new builder())->require_owned($rule->reportid);
        }
        return $course;
    }

    /**
     * Queue a single run for the requested slot.
     *
     * @param int $ruleid Saved rule identifier.
     * @param int $slot Due timestamp, or negative manual timestamp.
     * @return int Queued run identifier.
     */
    public function enqueue(int $ruleid, int $slot): int {
        global $DB;
        $lock = lock_config::get_lock_factory('local_moodlexhub_auto')->get_lock('rule_' . $ruleid, 5);
        if (!$lock) {
            throw new \moodle_exception('automationbusy', 'local_moodlexhub');
        }
        try {
            $rule = $DB->get_record(self::RULES, ['id' => $ruleid]);
            if (!$rule || ($slot > 0 && (!$rule->enabled || (int) $rule->nextdue !== $slot))) {
                return 0;
            }
            $previous = $DB->get_record(self::RUNS, ['ruleid' => $ruleid, 'timeslot' => $slot]);
            if ($previous) {
                return (int) $previous->id;
            }
            $transaction = $DB->start_delegated_transaction();
            $runid = $DB->insert_record(self::RUNS, (object) [
                'ruleid' => $ruleid,
                'timeslot' => $slot,
                'status' => 'queued',
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
            $task = new run_automation();
            $task->set_userid($rule->userid);
            $task->set_attempts_available(3);
            $task->set_custom_data(['runid' => $runid]);
            manager::queue_adhoc_task($task, true);
            if ($slot > 0) {
                $DB->set_field(self::RULES, 'nextdue', time() + $rule->intervalvalue, ['id' => $ruleid]);
            }
            $transaction->allow_commit();
            return $runid;
        } finally {
            $lock->release();
        }
    }

    /**
     * Execute the authorized action once when the condition matches.
     *
     * @param int $runid Persisted rule run.
     * @param int $attempts Remaining Moodle task attempts.
     */
    public function process(int $runid, int $attempts): void {
        global $DB, $USER;
        $lock = lock_config::get_lock_factory('local_moodlexhub_auto')->get_lock('run_' . $runid, 10);
        if (!$lock) {
            throw new \moodle_exception('automationbusy', 'local_moodlexhub');
        }
        try {
            $run = $DB->get_record(self::RUNS, ['id' => $runid, 'status' => 'queued']);
            $rule = $run ? $DB->get_record(self::RULES, ['id' => $run->ruleid, 'userid' => $USER->id]) : null;
            if (!$rule) {
                return;
            }
            if ($run->timeslot > 0 && !$rule->enabled) {
                $this->finish($runid, 'blocked');
                return;
            }
            try {
                $course = $this->require_scope($rule);
            } catch (\moodle_exception $exception) {
                $this->finish($runid, 'blocked');
                return;
            }
            if (!$this->matches($rule, $course)) {
                $this->finish($runid, 'skipped');
                return;
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                $result = match ($rule->actiontype) {
                    'audit' => (new auditor())->run($course),
                    'scan' => (new scan())->schedule($course),
                    'report' => (new builder())->enqueue($rule->reportid, -$runid),
                    'notify' => $this->send_notice($rule, $course),
                };
                $this->finish($runid, 'finished', $result);
                $transaction->allow_commit();
            } catch (\Throwable $exception) {
                try {
                    $transaction->rollback($exception);
                } catch (\Throwable $failure) {
                    if ($attempts <= 1) {
                        $this->finish($runid, 'failed');
                    }
                    throw $failure;
                }
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Check the selected condition with data from native Moodle or another module.
     *
     * @param stdClass $rule Saved rule.
     * @param stdClass|null $course Rule's course if any.
     * @return bool
     */
    private function matches(stdClass $rule, ?stdClass $course): bool {
        global $CFG, $USER;
        $threshold = max(1, min(3650, (int) $rule->threshold));
        switch ($rule->conditiontype) {
            case 'always':
                return true;
            case 'noteachers':
                return !empty($CFG->coursecontact) && !(new \core_course_list_element($course))->has_course_contacts();
            case 'inactive':
                $context = context_course::instance($course->id);
                return (new course_metrics())->learners($course, $context, 0, 0, 1, time() - DAYSECS * $threshold)['total'] > 0;
            case 'storage':
                foreach (snapshots::recent($course->id, scan::SOURCE, 20) as $entry) {
                    if ($entry->status === 'finished') {
                        $data = json_decode($entry->summary, true, flags: JSON_THROW_ON_ERROR);
                        return $data['bytes'] >= $threshold * 1024 * 1024;
                    }
                }
                return false;
            case 'duedates':
                $now = time();
                foreach (get_fast_modinfo($course)->get_cms() as $cm) {
                    if (!$cm->uservisible || $cm->deletioninprogress) {
                        continue;
                    }
                    foreach (\core\activity_dates::get_dates_for_module($cm, $USER->id) as $date) {
                        if ($date['timestamp'] > $now && $date['timestamp'] <= $now + $threshold * DAYSECS) {
                            return true;
                        }
                    }
                }
                return false;
            case 'tickets':
                return (new tickets())->has_pending_before(time() - $threshold * DAYSECS);
        }
        return false;
    }

    /**
     * Inform the selected Moodle account without disclosing the rule data.
     *
     * @param stdClass $rule Saved rule.
     * @param stdClass|null $course Rule's course if any.
     * @return int|null Moodle notification ID.
     */
    private function send_notice(stdClass $rule, ?stdClass $course): ?int {
        $userid = $rule->recipient ?: $rule->userid;
        $user = \core_user::get_user($userid, '*', IGNORE_MISSING);
        $system = context_system::instance();
        if (
            !$user || $user->deleted || $user->suspended || !$user->confirmed
            || !has_capability('local/moodlexhub:viewdashboard', $system, $user->id)
            || ($course && !can_access_course($course, $user, '', true))
        ) {
            throw new \moodle_exception('automationrecipient', 'local_moodlexhub');
        }
        $message = new message();
        $message->component = 'local_moodlexhub';
        $message->name = 'automationnotice';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string_manager()->get_string('automationnotification', 'local_moodlexhub', null, $user->lang);
        $message->fullmessage = $message->subject;
        $message->smallmessage = $message->subject;
        $message->fullmessagehtml = '';
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/local/moodlexhub/index.php'))->out(false);
        $message->contexturlname = 'MoodleX Hub';
        $result = message_send($message);
        if (!$result) {
            throw new \moodle_exception('automationrecipient', 'local_moodlexhub');
        }
        return (int) $result;
    }

    /**
     * Persist the final outcome of a rule's scheduled slot.
     *
     * @param int $id Run identifier.
     * @param string $status Final state.
     * @param int|null $result Related operation result.
     */
    private function finish(int $id, string $status, ?int $result = null): void {
        global $DB;
        $DB->update_record(self::RUNS, (object) [
            'id' => $id, 'status' => $status, 'resultid' => $result, 'timemodified' => time(),
        ]);
    }
}
