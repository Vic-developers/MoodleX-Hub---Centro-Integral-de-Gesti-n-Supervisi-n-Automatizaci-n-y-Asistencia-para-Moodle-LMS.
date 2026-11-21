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

namespace local_moodlexhub\output;

use core\task\manager;
use moodle_url;
use renderer_base;
use templatable;

/**
 * Read Moodle runtime and task health without exposing raw logs.
 */
final class monitoring implements templatable {
    /** @var string Moodle capability required for this operation. */
    public const CAPABILITY = 'local/moodlexhub:viewmonitoring';

    /** @var int Number of history records in the current scope. */
    private int $total = 0;

    /** @var string Requested scope of task data. */
    private readonly string $scope;

    /** @var int Zero-based history page. */
    private readonly int $page;

    /**
     * Prepare the monitoring view for a requested scope and page.
     *
     * @param string $scope Requested screen scope.
     * @param int $page History page.
     */
    public function __construct(string $scope, int $page) {
        $this->scope = $scope;
        $this->page = $page;
    }

    /**
     * Number of matching task-log records.
     *
     * @return int
     */
    public function get_total(): int {
        return $this->total;
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        global $CFG, $DB;

        require_capability(self::CAPABILITY, \context_system::instance());
        $label = fn(string $key): string => get_string($key, 'local_moodlexhub');
        $scope = $this->scope;
        $site = $scope === 'site';
        if ($site) {
            require_capability('moodle/site:config', \context_system::instance());
        }
        $server = $DB->get_server_info();
        $rows = [];
        $scheduled = manager::get_all_scheduled_tasks();
        $scheduledcount = 0;
        $delayed = 0;
        $disabled = 0;
        foreach ($scheduled as $task) {
            if (!$site && $task->get_component() !== 'local_moodlexhub') {
                continue;
            }
            $scheduledcount++;
            $disabled += (int) $task->get_disabled();
            $delayed += (int) ($task->get_fail_delay() > 0);
            if ($task->get_component() === 'local_moodlexhub') {
                $rows[] = [
                    'name' => $task->get_name(),
                    'status' => $label($task->get_disabled() ? 'disabled' : ($task->get_fail_delay() ? 'retrying' : 'enabled')),
                    'next' => $task->get_next_run_time() ? userdate($task->get_next_run_time()) : get_string('never'),
                    'last' => $task->get_last_run_time() ? userdate($task->get_last_run_time()) : get_string('never'),
                ];
            }
        }

        $conditions = $site ? [] : ['component' => 'local_moodlexhub'];
        $this->total = $DB->count_records('task_log', $conditions);
        $history = $DB->get_records(
            'task_log',
            $conditions,
            'timestart DESC, id DESC',
            'id,classname,timestart,timeend,result',
            $this->page * 20,
            20,
        );
        $entries = [];
        foreach ($history as $log) {
            $entries[] = [
                'name' => $log->classname,
                'date' => userdate((int) $log->timestart),
                'duration' => format_float(max(0, $log->timeend - $log->timestart), 2),
                'status' => $label($log->result ? 'taskfailure' : 'tasksuccess'),
            ];
        }

        $pending = 0;
        $failed = 0;
        $running = 0;
        $summary = manager::get_adhoc_tasks_summary();
        foreach ($summary as $component => $classes) {
            if (!$site && $component !== 'local_moodlexhub') {
                continue;
            }
            foreach ($classes as $stats) {
                $pending += $stats['count'];
                $failed += $stats['failed'];
                $running += $stats['running'];
            }
        }

        $url = new moodle_url('/local/moodlexhub/monitoring.php');
        return [
            'dashboardurl' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
            'switchurl' => has_capability('moodle/site:config', \context_system::instance())
                ? (new moodle_url($url, ['scope' => $site ? 'hub' : 'site']))->out(false) : null,
            'switchlabel' => $label($site ? 'monitorplugin' : 'monitorsite'),
            'metrics' => [
                ['name' => $label('monitorrelease'), 'value' => $CFG->release],
                ['name' => $label('monitorphp'), 'value' => PHP_VERSION],
                ['name' => $label('monitordatabase'), 'value' => $CFG->dbtype . ' ' . ($server['version'] ?? '')],
                ['name' => $label('scheduledcount'), 'value' => $scheduledcount],
                ['name' => $label('disabledcount'), 'value' => $disabled],
                ['name' => $label('retryingcount'), 'value' => $delayed],
                ['name' => $label('queuedcount'), 'value' => $pending],
                ['name' => $label('runningcount'), 'value' => $running],
                ['name' => $label('failedcount'), 'value' => $failed],
            ],
            'tasks' => $rows,
            'hastasks' => !empty($rows),
            'history' => $entries,
            'hashistory' => !empty($entries),
        ];
    }
}
