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

namespace local_moodlexhub\report;

use context_course;
use context_system;
use core\lock\lock_config;
use core\task\manager;
use local_moodlexhub\academic\course_metrics;
use local_moodlexhub\grade\diagnostics;
use local_moodlexhub\quality\auditor;
use local_moodlexhub\storage\scan;
use local_moodlexhub\support\tickets;
use local_moodlexhub\task\build_report;
use stdClass;

/**
 * Saved reports whose data sources apply their original context permissions.
 */
final class builder {
    /** @var string Moodle capability required for this operation. */
    public const CAPABILITY = 'local/moodlexhub:exportreports';
    /** @var string Table of saved report definitions. */
    public const REPORTS = 'local_moodlexhub_report';
    /** @var string Table of persisted operation results. */
    public const RUNS = 'local_moodlexhub_rpt_run';
    /** @var string[] Allowed report data sources. */
    public const SOURCES = ['quality', 'storage', 'grades', 'learners', 'tickets'];
    /** @var int[] Supported schedule intervals in seconds. */
    public const INTERVALS = [0, DAYSECS, WEEKSECS];

    /**
     * Require access to the selected report source.
     *
     * @param string $source Approved data source.
     * @param int $courseid Course identifier when applicable.
     * @return stdClass|null Course, or null for site tickets.
     */
    public function require_source(string $source, int $courseid): ?stdClass {
        \local_moodlexhub\module_config::require_enabled('reports');
        require_capability(self::CAPABILITY, context_system::instance());
        if (!in_array($source, self::SOURCES, true)) {
            throw new \invalid_parameter_exception();
        }
        if ($source === 'tickets') {
            \local_moodlexhub\module_config::require_enabled('support');
            require_capability(tickets::MANAGE, context_system::instance());
            return null;
        }
        $course = get_course($courseid);
        $context = match ($source) {
            'quality' => (new auditor())->require_access($course),
            'storage' => (new scan())->require_access($course),
            'grades' => (new diagnostics())->require_access($course),
            'learners' => (new course_metrics())->require_access($course, course_metrics::ANALYTICS),
        };
        if ($source === 'learners' && groups_get_course_groupmode($course) === SEPARATEGROUPS) {
            require_capability('moodle/site:accessallgroups', $context);
        }
        return $course;
    }

    /**
     * Generate a filtered aggregate dataset using the original source services.
     *
     * @param stdClass $report Saved report.
     * @return array
     */
    public function generate(stdClass $report): array {
        $source = $report->source;
        $course = $this->require_source($source, $report->courseid);
        $filter = (int) $report->filtervalue;
        $rows = [];
        $append = static function (string $metric, $value) use (&$rows): void {
            $rows[] = ['metric' => $metric, 'value' => $value];
        };
        $label = static fn(string $key): string => get_string($key, 'local_moodlexhub');
        switch ($source) {
            case 'quality':
                $entry = (new auditor())->latest($course);
                if ($entry) {
                    $append($label('reportqualityscore'), $entry->score ?? $label('notapplicable'));
                    foreach (json_decode($entry->findings, true, flags: JSON_THROW_ON_ERROR) as $finding) {
                        if ($finding['weight'] >= max(1, $filter) && isset(auditor::WEIGHTS[$finding['rule']])) {
                            $append($label('rule_' . $finding['rule']), $finding['count'] ?? $label('notapplicable'));
                        }
                    }
                }
                break;
            case 'storage':
                foreach (snapshots::recent($course->id, scan::SOURCE, 20) as $entry) {
                    if ($entry->status !== 'finished') {
                        continue;
                    }
                    $data = json_decode($entry->summary, true, flags: JSON_THROW_ON_ERROR);
                    $append($label('storagelogical'), $data['bytes']);
                    $append($label('storagephysical'), $data['physicalbytes']);
                    $append($label('storagefiles'), $data['filecount']);
                    $append($label('storageduplicates'), $data['duplicates']);
                    break;
                }
                break;
            case 'grades':
                $data = (new diagnostics())->inspect($course);
                foreach (['categories', 'items'] as $type) {
                    $key = $type === 'items' ? 'gradeitems' : 'gradecategories';
                    $append($label($key), count($data[$type]));
                    $append(
                        $label($key) . ' · ' . $label('gradefinding'),
                        count(array_filter($data[$type], static fn(array $entry): bool => $entry['finding'] !== ''))
                    );
                }
                break;
            case 'learners':
                $metrics = new course_metrics();
                $context = context_course::instance($course->id);
                $total = $metrics->learners($course, $context, 0, 0, 1, 0);
                $inactive = $metrics->learners($course, $context, 0, 0, 1, time() - max(1, $filter) * DAYSECS);
                $append($label('academicparticipants'), $total['total']);
                $append($label('reportinactive'), $inactive['total']);
                $append($label('reportcompletionactivities'), $total['tracked']);
                break;
            case 'tickets':
                foreach ((new tickets())->status_counts() as $status => $count) {
                    $append($label('ticketstatus_' . $status), $count);
                }
                break;
        }

        return [
            'source' => $source,
            'courseid' => (int) ($course->id ?? 0),
            'title' => $report->title,
            'generated' => time(),
            'filtervalue' => $filter,
            'rows' => $rows,
        ];
    }

    /**
     * Load a definition belonging to the current user and authorize its data source.
     *
     * @param int $id Definition identifier.
     * @return stdClass Report definition.
     */
    public function require_owned(int $id): stdClass {
        global $DB, $USER;
        $report = $DB->get_record(self::REPORTS, ['id' => $id, 'userid' => $USER->id], '*', MUST_EXIST);
        $this->require_source($report->source, $report->courseid);
        return $report;
    }

    /**
     * Enqueue a single persisted report run.
     *
     * @param int $reportid Saved report identifier.
     * @param int $slot Scheduled timestamp or negative manual timestamp.
     * @return int Run ID, or zero when the report was removed.
     */
    public function enqueue(int $reportid, int $slot): int {
        global $DB;

        $lock = lock_config::get_lock_factory('local_moodlexhub_report')->get_lock('report_' . $reportid, 3);
        if (!$lock) {
            throw new \moodle_exception('reportbusy', 'local_moodlexhub');
        }
        try {
            $report = $DB->get_record(self::REPORTS, ['id' => $reportid]);
            if (!$report || !$report->userid) {
                return 0;
            }
            if ($slot > 0 && ((int) $report->nextdue !== $slot || !$report->intervalvalue)) {
                return 0;
            }
            $previous = $DB->get_record(self::RUNS, ['reportid' => $reportid, 'timeslot' => $slot]);
            if ($previous) {
                return (int) $previous->id;
            }
            $transaction = $DB->start_delegated_transaction();
            $runid = $DB->insert_record(self::RUNS, (object) [
                'reportid' => $reportid, 'timeslot' => $slot,
                'status' => 'queued', 'timecreated' => time(), 'timemodified' => time(),
            ]);
            $task = new build_report();
            $task->set_userid($report->userid);
            $task->set_attempts_available(3);
            $task->set_custom_data(['runid' => $runid]);
            manager::queue_adhoc_task($task, true);
            if ($slot > 0) {
                $DB->set_field(self::REPORTS, 'nextdue', time() + $report->intervalvalue, ['id' => $reportid]);
            }
            $transaction->allow_commit();
            return $runid;
        } finally {
            $lock->release();
        }
    }

    /**
     * Run the task under its current owner's authority.
     *
     * @param int $runid Persisted run ID.
     * @param int $attempts Remaining Moodle task attempts.
     */
    public function run(int $runid, int $attempts): void {
        global $DB, $USER;
        $run = $DB->get_record(self::RUNS, ['id' => $runid]);
        if (!$run || $run->status !== 'queued') {
            return;
        }
        $report = $DB->get_record(self::REPORTS, ['id' => $run->reportid, 'userid' => $USER->id]);
        if (!$report) {
            return;
        }
        try {
            $this->require_source($report->source, $report->courseid);
        } catch (\moodle_exception $exception) {
            $this->complete($runid, 'blocked');
            return;
        }
        try {
            $this->complete($runid, 'finished', $this->generate($report));
        } catch (\Throwable $exception) {
            if ($attempts <= 1) {
                $this->complete($runid, 'failed');
            }
            throw $exception;
        }
    }

    /**
     * Persist the result of a report task.
     *
     * @param int $id Persisted run ID.
     * @param string $status Result status.
     * @param array|null $data Report payload.
     */
    private function complete(int $id, string $status, ?array $data = null): void {
        global $DB;
        $DB->update_record(self::RUNS, (object) [
            'id' => $id, 'status' => $status, 'timemodified' => time(),
            'payload' => $data === null ? null : json_encode($data, JSON_THROW_ON_ERROR),
        ]);
    }
}
