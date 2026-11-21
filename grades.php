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

use local_moodlexhub\grade\diagnostics;
use local_moodlexhub\report\snapshots;

require_login(null, false);
$course = get_course(required_param('id', PARAM_INT));
$service = new diagnostics();
$context = $service->require_access($course);
$search = trim(optional_param('search', '', PARAM_TEXT));
$onlyissues = optional_param('issues', 0, PARAM_BOOL);
$page = max(0, min(intdiv(PHP_INT_MAX, 50), optional_param('page', 0, PARAM_INT)));
$path = '/local/moodlexhub/grades.php';
$url = new moodle_url($path, ['id' => $course->id, 'search' => $search, 'issues' => $onlyissues]);
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('gradesdiagnostics'));
$PAGE->set_heading($label('gradesdiagnostics'));

$result = $service->inspect($course);
if (data_submitted() && optional_param('record', 0, PARAM_BOOL)) {
    require_sesskey();
    $summary = [];
    foreach ($result as $section => $records) {
        $summary[$section] = [
            'total' => count($records),
            'findings' => count(array_filter($records, static fn(array $row): bool => $row['finding'] !== '')),
        ];
    }
    snapshots::create($course->id, 'grades', $summary);
    redirect($url);
}
$needle = core_text::strtolower($search);
$items = array_filter($result['items'], static function (array $row) use ($needle, $onlyissues): bool {
    return (!$onlyissues || $row['finding'] !== '')
        && ($needle === '' || str_contains(core_text::strtolower($row['name'] . ' ' . $row['type']), $needle));
});
$categories = array_filter($result['categories'], static fn(array $row): bool => !$onlyissues || $row['finding'] !== '');
$itemheadings = [
    get_string('name'),
    $label('gradeitemtype'),
    get_string('category'),
    $label('visibility'),
    $label('graderange'),
    $label('gradefinding'),
];
$categoryheadings = [get_string('name'), $label('gradeaggregation'), $label('gradefinding')];
$maketable = static fn(string $title, array $headings, array $rows): array => [
    'title' => $title,
    'headings' => $headings,
    'rows' => array_values(array_map(static fn(array $row): array => ['cells' => array_values($row)], $rows)),
    'hasrows' => (bool) $rows,
];

echo $OUTPUT->header();
$history = [];
foreach (snapshots::recent($course->id, 'grades') as $entry) {
    $summary = json_decode($entry->summary, true, flags: JSON_THROW_ON_ERROR);
    $history[] = [
        'date' => userdate($entry->timecreated),
        'categories' => $summary['categories']['findings'] . ' / ' . $summary['categories']['total'],
        'items' => $summary['items']['findings'] . ' / ' . $summary['items']['total'],
    ];
}
$courseroute = has_capability('local/moodlexhub:viewcourses', $context) ? '/local/moodlexhub/course.php' : '/course/view.php';
echo $OUTPUT->render_from_template('local_moodlexhub/grades', [
    'courseurl' => (new moodle_url($courseroute, ['id' => $course->id]))->out(false),
    'action' => (new moodle_url($path))->out(false),
    'courseid' => $course->id,
    'search' => $search,
    'onlyissues' => $onlyissues,
    'history' => $history,
    'hashistory' => (bool) $history,
    'recordurl' => $url->out(false),
    'sesskey' => sesskey(),
    'tables' => [
        $maketable($label('gradecategories'), $categoryheadings, $categories),
        $maketable($label('gradeitems'), $itemheadings, array_slice($items, $page * 50, 50)),
    ],
]);
echo $OUTPUT->paging_bar(count($items), $page, 50, $url);
echo $OUTPUT->footer();
