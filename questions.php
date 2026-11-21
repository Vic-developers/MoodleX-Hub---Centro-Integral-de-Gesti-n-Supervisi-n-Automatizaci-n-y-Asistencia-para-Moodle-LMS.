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

use local_moodlexhub\question\catalogue;

require_login(null, false);
$course = get_course(required_param('id', PARAM_INT));
$service = new catalogue();
$scope = $service->scope($course);
$context = context_course::instance($course->id);
$filters = [
    'search' => trim(optional_param('search', '', PARAM_TEXT)),
    'bank' => optional_param('bank', 0, PARAM_INT),
    'category' => optional_param('category', 0, PARAM_INT),
    'author' => optional_param('author', 0, PARAM_INT),
    'type' => optional_param('type', '', PARAM_ALPHANUMEXT),
    'status' => optional_param('status', '', PARAM_ALPHA),
];
if ($filters['status'] !== '' && !in_array($filters['status'], catalogue::STATUSES, true)) {
    throw new invalid_parameter_exception();
}
$page = max(0, min(intdiv(PHP_INT_MAX, 30), optional_param('page', 0, PARAM_INT)));
$path = '/local/moodlexhub/questions.php';
$url = new moodle_url($path, ['id' => $course->id] + $filters);
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('questioncatalogue'));
$PAGE->set_heading($label('questioncatalogue'));

$compareid = max(0, optional_param('compare', 0, PARAM_INT));
$batch = max(0, min(intdiv(PHP_INT_MAX, 200), optional_param('batch', 0, PARAM_INT)));
$result = $service->search($scope, $filters, $page * 30, 30, $compareid, $batch);
$comparison = $result['comparison'];
if ($comparison) {
    $comparison['previous'] = $batch ? (new moodle_url($url, ['compare' => $compareid, 'batch' => $batch - 1]))->out(false) : '';
    $comparison['next'] = $comparison['more']
        ? (new moodle_url($url, ['compare' => $compareid, 'batch' => $batch + 1]))->out(false) : '';
}
$comparelinks = [];
foreach ($result['rows'] as $item) {
    $comparelinks[] = [
        'name' => $item['cells'][0],
        'url' => (new moodle_url($url, ['compare' => $item['entryid'], 'page' => $page]))->out(false),
    ];
}
$typeoptions = [];
foreach ($result['types'] as $type) {
    $typeoptions[$type] = $service->type_name($type);
}
$authoroptions = [];
foreach ($result['authors'] as $author) {
    $authoroptions[$author->id] = trim(($author->firstname ?? '') . ' ' . ($author->lastname ?? ''));
}
$statusoptions = [];
foreach (catalogue::STATUSES as $status) {
    $statusoptions[$status] = $label('questionstate_' . $status);
}
$selectors = [];
foreach (
    [
    'bank' => $scope['labels'],
    'category' => $result['categories'],
    'author' => $authoroptions,
    'type' => $typeoptions,
    'status' => $statusoptions,
    ] as $filter => $values
) {
    $options = [['value' => '', 'name' => $label('questionany'), 'selected' => !$filters[$filter]]];
    foreach ($values as $value => $name) {
        $options[] = ['value' => $value, 'name' => $name, 'selected' => $value == $filters[$filter]];
    }
    $selectors[] = ['key' => $filter, 'label' => $label('questionfield_' . $filter), 'options' => $options];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/questions', [
    'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    'action' => (new moodle_url($path))->out(false),
    'courseid' => $course->id,
    'search' => $filters['search'],
    'selectors' => $selectors,
    'comparison' => $comparison,
    'comparelinks' => $comparelinks,
    'hascomparelinks' => (bool) $comparelinks,
    'report' => [
        'title' => $label('questioncatalogue'),
        'headings' => [
            get_string('name'), $label('questionfield_type'), $label('questionfield_category'), $label('questionfield_bank'),
            $label('questionfield_author'), $label('questionversion'), $label('questionuses'), $label('questionduplicates'),
        ],
        'rows' => $result['rows'],
        'hasrows' => (bool) $result['rows'],
    ],
]);
echo $OUTPUT->paging_bar($result['total'], $page, 30, $url);
echo $OUTPUT->footer();
