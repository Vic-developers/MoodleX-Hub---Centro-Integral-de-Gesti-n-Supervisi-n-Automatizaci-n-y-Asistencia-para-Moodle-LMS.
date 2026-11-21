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

require_once(__DIR__ . '/../../config.php');

use local_moodlexhub\report\builder;

require_login(null, false);
\local_moodlexhub\module_config::require_enabled('reports');
$context = context_system::instance();
require_capability(builder::CAPABILITY, $context);
$reportid = optional_param('reportid', 0, PARAM_INT);
$runid = optional_param('runid', 0, PARAM_INT);
$export = optional_param('export', '', PARAM_ALPHA);
$page = max(0, min(intdiv(PHP_INT_MAX, 25), optional_param('page', 0, PARAM_INT)));
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$service = new builder();
$url = new moodle_url('/local/moodlexhub/reports.php');
$href = $reportid ? new moodle_url($url, ['reportid' => $reportid]) : $url;
$report = $reportid ? $DB->get_record(builder::REPORTS, ['id' => $reportid, 'userid' => $USER->id], '*', MUST_EXIST) : null;
$run = $runid && $report ? $DB->get_record(builder::RUNS, ['id' => $runid, 'reportid' => $reportid], '*', MUST_EXIST) : null;
$payload = $run && $run->payload ? json_decode($run->payload, true, flags: JSON_THROW_ON_ERROR) : null;
if ($payload) {
    $service->require_source($payload['source'], $payload['courseid']);
}
if ($report && data_submitted() && optional_param('run', 0, PARAM_BOOL)) {
    require_sesskey();
    $service->require_source($report->source, $report->courseid);
    $id = $service->enqueue($reportid, -time());
    redirect(new moodle_url($href, ['runid' => $id]));
}

$formats = array_intersect_key(
    ['csv' => 'CSV', 'excel' => 'Excel', 'pdf' => 'PDF'],
    \core\plugininfo\dataformat::get_enabled_plugins(),
);
$method = $payload ? $label('reportmethod_' . $payload['source']) : '';
$filter = $payload ? (string) $payload['filtervalue'] : '';
if ($export !== '') {
    if (!$payload || !isset($formats[$export])) {
        throw new invalid_parameter_exception();
    }
    $columns = [
        'metric' => $label('reportmetric'), 'value' => $label('reportvalue'), 'date' => get_string('date'),
        'course' => get_string('course'), 'filter' => $label('reportfiltervalue'), 'method' => $label('reportmethod'),
    ];
    $rows = $payload['rows'] ?: [['metric' => $label('reportnodata'), 'value' => '']];
    foreach ($rows as &$row) {
        $row += ['date' => gmdate(DATE_ATOM, $payload['generated']), 'course' => $payload['courseid'] ?: '',
            'filter' => $filter, 'method' => $method];
    }
    unset($row);
    \core\dataformat::download_data('moodlexhub-report-' . $runid, $export, $columns, $rows);
    exit;
}

$conditions = $report ? ['reportid' => $reportid] : ['userid' => $USER->id];
$table = $report ? builder::RUNS : builder::REPORTS;
$total = $DB->count_records($table, $conditions);
$entries = [];
foreach ($DB->get_records($table, $conditions, 'timemodified DESC, id DESC', '*', $page * 25, 25) as $entry) {
    $entries[] = [
        'label' => $report ? $label('import_' . $entry->status) . ' · ' . userdate($entry->timecreated) : $entry->title,
        'url' => (new moodle_url($href, [($report ? 'runid' : 'reportid') => $entry->id]))->out(false),
    ];
}
$downloads = [];
if ($payload) {
    foreach ($formats as $extension => $name) {
        $downloads[] = ['name' => $name, 'url' => (new moodle_url($href, ['runid' => $runid, 'export' => $extension]))->out(false)];
    }
}
$PAGE->set_context($context);
$PAGE->set_url($href);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('reports'));
$PAGE->set_heading($label('reports'));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/reports', [
    'dashboardurl' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
    'listurl' => $url->out(false),
    'editurl' => (new moodle_url('/local/moodlexhub/report_edit.php', ['reportid' => (int) $reportid]))->out(false),
    'title' => $report->title ?? null,
    'nextdue' => $report && $report->nextdue ? userdate($report->nextdue) : null,
    'viewreport' => (bool) $report,
    'joburl' => $href->out(false),
    'sesskey' => sesskey(),
    'entries' => $entries,
    'hasentries' => (bool) $entries,
    'downloads' => $downloads,
    'result' => $payload ? [
        'generated' => userdate($payload['generated']),
        'filter' => $filter,
        'method' => $method,
        'table' => [
            'title' => $payload['title'],
            'headings' => [$label('reportmetric'), $label('reportvalue')],
            'hasrows' => (bool) $payload['rows'],
            'rows' => array_map(static fn(array $row): array => ['cells' => array_values($row)], $payload['rows']),
        ],
    ] : null,
    'runstatus' => $run ? $label('import_' . $run->status) : null,
]);
echo $OUTPUT->paging_bar($total, $page, 25, $href);
echo $OUTPUT->footer();
