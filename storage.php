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

use local_moodlexhub\report\snapshots;
use local_moodlexhub\storage\scan;

require_login(null, false);
$course = get_course(required_param('id', PARAM_INT));
$service = new scan();
$context = $service->require_access($course);
$url = new moodle_url('/local/moodlexhub/storage.php', ['id' => $course->id]);
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('storage'));
$PAGE->set_heading($label('storage'));

if (data_submitted() && optional_param('scan', 0, PARAM_BOOL)) {
    require_sesskey();
    $id = $service->schedule($course);
    redirect(new moodle_url($url, ['snapshotid' => $id]));
}

$history = snapshots::recent($course->id, scan::SOURCE, 20);
$id = optional_param('snapshotid', 0, PARAM_INT);
$entry = $id ? snapshots::get($id, $course->id, scan::SOURCE) : reset($history);
$summary = $entry && $entry->summary ? json_decode($entry->summary, true, flags: JSON_THROW_ON_ERROR) : null;
$maketable = static fn(string $title, array $headings, array $rows): array => [
    'title' => $title,
    'headings' => $headings,
    'rows' => $rows,
    'hasrows' => (bool) $rows,
];
$tables = [];
$metrics = [];

if ($summary) {
    $metrics = [
        ['name' => $label('storagelogical'), 'value' => display_size($summary['bytes'])],
        ['name' => $label('storagephysical'), 'value' => display_size($summary['physicalbytes'])],
        ['name' => $label('storagefiles'), 'value' => $summary['filecount']],
        ['name' => $label('storageduplicates'), 'value' => $summary['duplicates']],
        ['name' => $label('storageexternal'), 'value' => $summary['external']],
        ['name' => $label('storageorphans'), 'value' => $summary['orphans'] ?? $label('notapplicable')],
    ];
    $extensions = [];
    foreach ($summary['extensions'] as $extension => $counts) {
        $extensions[] = ['cells' => [$extension ?: $label('storageunknownext'), $counts['files'], display_size($counts['bytes'])]];
    }
    $tables[] = $maketable($label('storageextensions'), [$label('storageextension'), $label('storagefiles'), $label('storagelogical')], $extensions);
    $largest = [];
    foreach ($summary['largest'] as $file) {
        $largest[] = ['cells' => [$file['area'], $file['ext'] ?: $label('storageunknownext'), display_size($file['bytes'])]];
    }
    $tables[] = $maketable($label('storagelargest'), [$label('storagearea'), $label('storageextension'), $label('storagelogical')], $largest);
}

$historyrows = [];
foreach ($history as $item) {
    $historyrows[] = [
        'date' => userdate($item->timecreated),
        'state' => $label('import_' . $item->status),
        'url' => (new moodle_url($url, ['snapshotid' => $item->id]))->out(false),
    ];
}
$courseroute = has_capability('local/moodlexhub:viewcourses', $context) ? '/local/moodlexhub/course.php' : '/course/view.php';

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/storage', [
    'courseurl' => (new moodle_url($courseroute, ['id' => $course->id]))->out(false),
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'state' => $entry ? $label('import_' . $entry->status) : null,
    'metrics' => $metrics,
    'hasmetrics' => (bool) $metrics,
    'tables' => $tables,
    'history' => $historyrows,
    'hashistory' => (bool) $historyrows,
]);
echo $OUTPUT->footer();
