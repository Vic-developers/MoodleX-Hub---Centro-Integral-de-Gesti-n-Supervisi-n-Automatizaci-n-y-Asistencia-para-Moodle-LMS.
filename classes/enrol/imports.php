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

namespace local_moodlexhub\enrol;

use context_course;
use core\lock\lock_config;
use core\task\manager;
use local_moodlexhub\task\enrol_batch;
use moodle_exception;
use stdClass;

/**
 * Preview and run course enrolments under the requesting user's authority.
 */
final class imports {
    /** @var string Moodle capability required for this operation. */
    public const CAPABILITY = 'local/moodlexhub:bulkenrolusers';
    /** @var string Table of queued and historical batches. */
    public const JOBS = 'local_moodlexhub_import';
    /** @var string Table of individual records within a batch. */
    public const ROWS = 'local_moodlexhub_imp_row';

    /**
     * Remove records owned by a deleted course.
     *
     * @param int $courseid Course identifier.
     */
    public static function delete_course_data(int $courseid): void {
        self::delete_jobs('courseid = :courseid', ['courseid' => $courseid]);
    }

    /**
     * Purge abandoned previews and historical imports.
     */
    public static function purge_expired(): void {
        self::delete_jobs(
            '(status = :preview AND timemodified < :previewdate)
                OR ((status = :finished OR status = :blocked) AND timemodified < :historydate)',
            [
                'preview' => 'preview',
                'previewdate' => time() - 7 * DAYSECS,
                'finished' => 'finished',
                'blocked' => 'blocked',
                'historydate' => time() - 90 * DAYSECS,
            ],
        );
    }

    /**
     * Remove imports and their rows using a fixed internal condition.
     *
     * @param string $select Job filter with bound parameters.
     * @param array $params Filter parameters.
     */
    private static function delete_jobs(string $select, array $params): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records_select(self::ROWS, 'importid IN (SELECT id FROM {' . self::JOBS . '} WHERE ' . $select . ')', $params);
        $DB->delete_records_select(self::JOBS, $select, $params);
        $transaction->allow_commit();
    }

    /**
     * Authorize the current user for the requested course.
     *
     * @param stdClass $course Course to enrol users into.
     * @return context_course
     */
    public function require_access(stdClass $course): context_course {
        \local_moodlexhub\module_config::require_enabled('users');
        if ($course->id == SITEID || !\core_course_category::can_view_course_info($course)) {
            throw new moodle_exception('courseunavailable', 'local_moodlexhub');
        }
        $context = context_course::instance($course->id);
        require_capability(self::CAPABILITY, $context);
        return $context;
    }

    /**
     * Identify active Moodle enrolment methods the actor is allowed to use.
     *
     * @param context_course $context Course context.
     * @return array<int, stdClass>
     */
    public function available_methods(context_course $context): array {
        $instances = [];
        foreach (enrol_get_instances($context->instanceid, true) as $instance) {
            $plugin = enrol_get_plugin($instance->enrol);
            if (
                $plugin && enrol_is_enabled($instance->enrol) && $plugin->allow_enrol($instance)
                && has_capability('enrol/' . $instance->enrol . ':enrol', $context)
            ) {
                $instances[$instance->id] = $instance;
            }
        }
        return $instances;
    }

    /**
     * Validate the selected Moodle enrolment instance and role.
     *
     * @param context_course $context Course context.
     * @param int $instanceid Moodle enrolment instance.
     * @param int $roleid Requested role.
     * @return stdClass
     */
    public function require_destination(context_course $context, int $instanceid, int $roleid): stdClass {
        $methods = $this->available_methods($context);
        $roles = get_assignable_roles($context);
        if (!isset($methods[$instanceid], $roles[$roleid])) {
            throw new moodle_exception('importinvaliddestination', 'local_moodlexhub');
        }
        return $methods[$instanceid];
    }

    /**
     * Write a preview using only the identity of resolved accounts and row statuses.
     *
     * @param stdClass $course Course selected by the requester.
     * @param int $instanceid Moodle enrolment instance.
     * @param int $roleid Moodle role to assign.
     * @param resource $stream Uploaded CSV content.
     * @return int Import identifier.
     */
    public function preview(stdClass $course, int $instanceid, int $roleid, $stream): int {
        global $DB, $USER;

        $context = $this->require_access($course);
        $this->require_destination($context, $instanceid, $roleid);
        $header = fgetcsv($stream, 0, ',', '"', '');
        if (!$header || count($header) !== 1 || strtolower(ltrim(trim($header[0] ?? ''), "\xEF\xBB\xBF")) !== 'username') {
            throw new moodle_exception('importbadheader', 'local_moodlexhub');
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $jobid = $DB->insert_record(self::JOBS, (object) [
            'courseid' => $course->id,
            'userid' => $USER->id,
            'instanceid' => $instanceid,
            'roleid' => $roleid,
            'status' => 'preview',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $seen = [];
        $position = 1;
        $batch = [];
        while (($line = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $position++;
            if ($line === [null]) {
                continue;
            }
            $username = trim($line[0] ?? '');
            $normalised = clean_param($username, PARAM_USERNAME);
            $valid = count($line) === 1 && $username !== '' && \core_text::strlen($username) <= 100
                && $normalised === \core_text::strtolower($username);
            $duplicate = $valid && isset($seen[$normalised]);
            if ($valid) {
                $seen[$normalised] = true;
            }
            $batch[] = [$position, $valid ? $normalised : null, $duplicate];
            if (count($batch) === 250) {
                $this->save_preview_rows($jobid, $batch);
                $batch = [];
            }
        }
        if ($batch) {
            $this->save_preview_rows($jobid, $batch);
        }
        if (!$DB->record_exists(self::ROWS, ['importid' => $jobid])) {
            $transaction->rollback(new moodle_exception('importnorows', 'local_moodlexhub'));
        }
        $transaction->allow_commit();
        return $jobid;
    }

    /**
     * Resolve accounts for a block of CSV records.
     *
     * @param int $jobid Import identifier.
     * @param array $rows Preview records, including shape and duplicate information.
     */
    private function save_preview_rows(int $jobid, array $rows): void {
        global $CFG, $DB;

        $names = array_values(array_unique(array_filter(array_column($rows, 1), static fn($name): bool => $name !== null)));
        $accounts = [];
        if ($names) {
            [$insql, $params] = $DB->get_in_or_equal($names, SQL_PARAMS_NAMED);
            $accounts = $DB->get_records_select(
                'user',
                'mnethostid = :localid AND deleted = 0 AND username ' . $insql,
                $params + ['localid' => $CFG->mnet_localhost_id],
                '',
                'username,id,suspended,confirmed',
            );
        }
        $records = [];
        foreach ($rows as [$position, $username, $duplicate]) {
            $account = $accounts[$username] ?? null;
            $reason = match (true) {
                $username === null => 'malformed',
                $duplicate => 'duplicate',
                !$account => 'unknown',
                $account->suspended || !$account->confirmed || isguestuser($account->id) => 'unavailable',
                default => '',
            };
            $records[] = (object) [
                'importid' => $jobid,
                'position' => $position,
                'userid' => $account->id ?? null,
                'status' => $reason === '' ? 'ready' : 'invalid',
                'reason' => $reason,
                'attempts' => 0,
            ];
        }
        $DB->insert_records(self::ROWS, $records);
    }

    /**
     * Load a course import that belongs to the current requester.
     *
     * @param int $id Import identifier.
     * @param stdClass $course Course selected in the UI.
     * @return stdClass
     */
    public function require_job(int $id, stdClass $course): stdClass {
        global $DB, $USER;

        $this->require_access($course);
        return $DB->get_record(self::JOBS, ['id' => $id, 'courseid' => $course->id, 'userid' => $USER->id], '*', MUST_EXIST);
    }

    /**
     * Confirm the preview and enqueue its first worker run.
     *
     * @param stdClass $job Import record, already scoped to the current user.
     * @param stdClass $course Import destination.
     * @param bool $retry Requeue failed rows or resume after a blocked destination.
     */
    public function schedule(stdClass $job, stdClass $course, bool $retry = false): void {
        global $DB;

        $context = $this->require_access($course);
        $this->require_destination($context, $job->instanceid, $job->roleid);
        $lock = lock_config::get_lock_factory('local_moodlexhub_enrol')->get_lock('import_' . $job->id, 5);
        if (!$lock) {
            throw new moodle_exception('importbusy', 'local_moodlexhub');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            $job = $this->require_job($job->id, $course);
            $retryallowed = $retry && in_array($job->status, ['finished', 'blocked'], true);
            if ($retryallowed) {
                $failed = ['importid' => $job->id, 'status' => 'failed'];
                $DB->set_field(self::ROWS, 'attempts', 0, $failed);
                $DB->set_field(self::ROWS, 'reason', null, $failed);
                $DB->set_field(self::ROWS, 'status', 'ready', $failed);
            }
            if (
                ($job->status === 'preview' || $retryallowed) && $DB->record_exists_select(
                    self::ROWS,
                    'importid = :id AND (status = :ready OR status = :retry)',
                    ['id' => $job->id, 'ready' => 'ready', 'retry' => 'retry']
                )
            ) {
                $job->status = 'queued';
                $job->timemodified = time();
                $DB->update_record(self::JOBS, $job);
                $this->queue($job, $retryallowed ? time() : 0);
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /**
     * Execute a bounded block and schedule the next block if needed.
     *
     * @param int $jobid Import identifier.
     * @param int $step Processing step for queue deduplication.
     */
    public function process(int $jobid, int $step): void {
        global $DB, $USER;

        $job = $DB->get_record(self::JOBS, ['id' => $jobid]);
        if (!$job || $job->userid != $USER->id || !in_array($job->status, ['queued', 'running'], true)) {
            return;
        }
        $lock = lock_config::get_lock_factory('local_moodlexhub_enrol')->get_lock('import_' . $jobid, 10);
        if (!$lock) {
            throw new moodle_exception('importbusy', 'local_moodlexhub');
        }
        try {
            $job = $DB->get_record(self::JOBS, ['id' => $jobid, 'userid' => $USER->id]);
            if (!$job || !in_array($job->status, ['queued', 'running'], true)) {
                return;
            }
            $course = get_course($job->courseid);
            try {
                $context = $this->require_access($course);
                $method = $this->require_destination($context, $job->instanceid, $job->roleid);
            } catch (moodle_exception $exception) {
                $this->update_status($job, 'blocked');
                mtrace('MoodleX Hub enrolment import blocked by destination permissions or configuration.');
                return;
            }

            $this->update_status($job, 'running');
            $configured = get_config('local_moodlexhub', 'importbatchsize');
            $limit = $configured === false ? 100 : max(1, min(500, (int) $configured));
            $select = 'importid = :importid AND ' . '(status = :ready OR status = :retry)';
            $params = ['importid' => $jobid, 'ready' => 'ready', 'retry' => 'retry'];
            $rows = $DB->get_records_select(self::ROWS, $select, $params, 'attempts ASC, position ASC', '*', 0, $limit);
            foreach ($rows as $row) {
                $this->process_row($row, $method, (int) $job->roleid, $context);
            }
            if ($DB->record_exists_select(self::ROWS, $select, $params)) {
                $this->queue($job, $step + 1);
            } else {
                $this->update_status($job, 'finished');
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Attempt an idempotent enrolment through the selected Moodle plugin.
     *
     * @param stdClass $row Import row.
     * @param stdClass $method Moodle enrolment instance.
     * @param int $roleid Requested role.
     * @param context_course $context Course context.
     */
    private function process_row(stdClass $row, stdClass $method, int $roleid, context_course $context): void {
        global $DB;

        $row->attempts++;
        $DB->update_record(self::ROWS, $row);
        $transaction = $DB->start_delegated_transaction();
        try {
            $account = $row->userid ? \core_user::get_user($row->userid, 'id,deleted,suspended,confirmed') : null;
            if (!$account || $account->deleted || $account->suspended || !$account->confirmed || isguestuser($account->id)) {
                $row->status = 'invalid';
                $row->reason = 'unavailable';
            } else if ($DB->record_exists('user_enrolments', ['enrolid' => $method->id, 'userid' => $account->id])) {
                $row->status = 'skipped';
                $row->reason = 'existing';
            } else {
                require_capability('enrol/' . $method->enrol . ':enrol', $context);
                enrol_get_plugin($method->enrol)->enrol_user($method, $account->id, $roleid);
                $row->status = 'complete';
                $row->reason = '';
            }
            $DB->update_record(self::ROWS, $row);
            $transaction->allow_commit();
        } catch (\Throwable $exception) {
            try {
                $transaction->rollback($exception);
            } catch (\Throwable $failure) {
                $row->status = $row->attempts < 3 ? 'retry' : 'failed';
                $row->reason = 'enrolerror';
                $DB->update_record(self::ROWS, $row);
                mtrace('MoodleX Hub could not enrol import row ' . (int) $row->position . '. Check the chosen enrolment method.');
            }
        }
    }

    /**
     * Schedule one worker step using Moodle's queue deduplication.
     *
     * @param stdClass $job Import record.
     * @param int $step Processing step.
     */
    private function queue(stdClass $job, int $step): void {
        $task = new enrol_batch();
        $task->set_custom_data(['id' => (int) $job->id, 'step' => $step]);
        $task->set_userid($job->userid);
        $task->set_attempts_available(3);
        manager::queue_adhoc_task($task, true);
    }

    /**
     * Set a job status and timestamp.
     *
     * @param stdClass $job Import record.
     * @param string $status New status.
     */
    private function update_status(stdClass $job, string $status): void {
        global $DB;
        $DB->update_record(self::JOBS, (object) ['id' => $job->id, 'status' => $status, 'timemodified' => time()]);
    }
}
