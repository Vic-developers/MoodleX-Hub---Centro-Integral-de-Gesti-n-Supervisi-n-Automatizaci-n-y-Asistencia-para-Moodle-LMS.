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

namespace local_moodlexhub\quality;

use context_system;
use core\lock\lock_config;
use core\task\manager;
use local_moodlexhub\course\catalog;
use local_moodlexhub\module_config;
use local_moodlexhub\task\audit_batch;
use stdClass;

/**
 * Process category-level audits in persisted, bounded task steps.
 */
final class bulk {
    /** @var string Table of queued and historical batches. */
    public const JOBS = 'local_moodlexhub_aud_batch';
    /** @var string Table of individual records within a batch. */
    public const ROWS = 'local_moodlexhub_aud_row';

    /**
     * Authorize a site-level bulk operation.
     */
    public static function require_access(): void {
        module_config::require_enabled('quality');
        require_all_capabilities([auditor::CAPABILITY, catalog::VIEW], context_system::instance());
    }

    /**
     * Queue a bulk audit from the current actor.
     *
     * @param int $categoryid Moodle course category ID, or zero for all.
     * @return int New job identifier.
     */
    public static function start(int $categoryid): int {
        global $DB, $USER;
        self::require_access();
        if ($categoryid) {
            \core_course_category::get($categoryid, MUST_EXIST);
        }
        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $id = $DB->insert_record(self::JOBS, (object) [
            'userid' => $USER->id, 'categoryid' => $categoryid,
            'status' => 'queued', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        self::enqueue($id, $USER->id, 0);
        $transaction->allow_commit();
        return $id;
    }

    /**
     * Run a persisted block and revalidate scope before auditing each course.
     *
     * @param int $jobid Bulk audit identifier.
     * @param int $step Current queue step.
     */
    public static function process(int $jobid, int $step): void {
        global $DB, $USER;
        $lock = lock_config::get_lock_factory('local_moodlexhub_bulk')->get_lock('job_' . $jobid, 5);
        if (!$lock) {
            throw new \moodle_exception('importbusy', 'local_moodlexhub');
        }
        try {
            $job = $DB->get_record(self::JOBS, ['id' => $jobid, 'userid' => $USER->id]);
            if (!$job || !in_array($job->status, ['queued', 'running'], true)) {
                return;
            }
            try {
                self::require_access();
            } catch (\moodle_exception $exception) {
                self::status($jobid, 'blocked');
                return;
            }
            if ($job->status === 'queued') {
                self::discover($job);
            }
            $configured = get_config('local_moodlexhub', 'importbatchsize');
            $size = $configured === false ? 100 : max(1, min(500, (int) $configured));
            $conditions = ['jobid' => $jobid, 'status' => 'ready'];
            foreach ($DB->get_records(self::ROWS, $conditions, 'attempts ASC, id ASC', '*', 0, $size) as $row) {
                self::audit_row($row);
            }
            if ($DB->record_exists(self::ROWS, $conditions)) {
                self::enqueue($jobid, $USER->id, $step + 1);
            } else {
                self::status($jobid, 'finished');
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Record the initial target set before executing any audits.
     *
     * @param stdClass $job Persisted request.
     */
    private static function discover(stdClass $job): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $rows = [];
        foreach ((new catalog())->matching('', $job->categoryid, 0) as $course) {
            $context = \context_course::instance($course->id);
            if (!has_all_capabilities([auditor::CAPABILITY, 'moodle/course:update'], $context)) {
                continue;
            }
            $rows[] = (object) ['jobid' => $job->id, 'courseid' => $course->id, 'status' => 'ready', 'attempts' => 0];
            if (count($rows) === 250) {
                $DB->insert_records(self::ROWS, $rows);
                $rows = [];
            }
        }
        if ($rows) {
            $DB->insert_records(self::ROWS, $rows);
        }
        self::status($job->id, 'running');
        $transaction->allow_commit();
    }

    /**
     * Persist one audit and its row state atomically.
     *
     * @param stdClass $row Selected course in the batch.
     */
    private static function audit_row(stdClass $row): void {
        global $DB;
        $auditor = new auditor();
        try {
            $course = get_course($row->courseid);
            $auditor->require_access($course);
        } catch (\moodle_exception $exception) {
            $DB->set_field(self::ROWS, 'status', 'skipped', ['id' => $row->id]);
            return;
        }

        $attempts = $row->attempts + 1;
        $transaction = $DB->start_delegated_transaction();
        try {
            $audit = $auditor->run($course);
            $DB->update_record(self::ROWS, (object) [
                'id' => $row->id, 'auditid' => $audit, 'attempts' => $attempts, 'status' => 'complete',
            ]);
            $transaction->allow_commit();
        } catch (\Throwable $exception) {
            try {
                $transaction->rollback($exception);
            } catch (\Throwable $failure) {
                $DB->update_record(self::ROWS, (object) [
                    'id' => $row->id, 'attempts' => $attempts, 'status' => $attempts >= 3 ? 'failed' : 'ready',
                ]);
                mtrace('MoodleX Hub audit failed for batch row ' . (int) $row->id . '.');
            }
        }
    }

    /**
     * Queue one idempotent task step for an actor.
     *
     * @param int $jobid Bulk audit identifier.
     * @param int $userid Authorized actor.
     * @param int $step Unique step counter.
     */
    private static function enqueue(int $jobid, int $userid, int $step): void {
        $task = new audit_batch();
        $task->set_userid($userid);
        $task->set_attempts_available(3);
        $task->set_custom_data(['jobid' => $jobid, 'step' => $step]);
        manager::queue_adhoc_task($task, true);
    }

    /**
     * Set state and modification time.
     *
     * @param int $jobid Bulk audit identifier.
     * @param string $status New job status.
     */
    private static function status(int $jobid, string $status): void {
        global $DB;
        $DB->update_record(self::JOBS, (object) ['id' => $jobid, 'status' => $status, 'timemodified' => time()]);
    }
}
