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

use local_moodlexhub\academic\course_metrics;
use local_moodlexhub\course\catalog;
use local_moodlexhub\module_config;
use local_moodlexhub\report\snapshots;
use local_moodlexhub\storage\scan;

require_login(null, false);
$system = context_system::instance();
$view = optional_param('view', 'storage', PARAM_ALPHA);
if (!in_array($view, ['storage', 'analytics'], true)) {
    throw new invalid_parameter_exception();
}
module_config::require_enabled($view);
$storage = $view === 'storage';
require_all_capabilities([catalog::VIEW, $storage ? scan::CAPABILITY : course_metrics::ANALYTICS], $system);
$category = optional_param('category', 0, PARAM_INT);
$teacher = optional_param('teacher', 0, PARAM_INT);
$days = optional_param('days', 30, PARAM_INT);
if (!in_array($days, [7, 14, 30, 90], true)) {
    throw new invalid_parameter_exception();
}
$page = max(0, min(intdiv(PHP_INT_MAX, 20), optional_param('page', 0, PARAM_INT)));
$path = '/local/moodlexhub/category.php';
$url = new moodle_url($path, ['view' => $view, 'category' => $category, 'days' => $days, 'teacher' => $teacher]);
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$title = $label($storage ? 'categorystorage' : 'categoryanalytics');
$PAGE->set_context($system);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$categories = [['id' => 0, 'name' => $label('allcategories'), 'selected' => !$category]];
foreach (core_course_category::make_categories_list() as $id => $name) {
    $categories[] = ['id' => $id, 'name' => html_to_text($name, 0, false), 'selected' => $id == $category];
}

$eligible = [];
if ($category) {
    foreach ((new catalog())->matching('', $category, 0) as $course) {
        $context = context_course::instance($course->id);
        if ($storage && !has_all_capabilities([scan::CAPABILITY, 'moodle/course:update'], $context)) {
            continue;
        }
        if (!$storage && !has_all_capabilities([course_metrics::ANALYTICS, 'moodle/course:viewparticipants'], $context)) {
            continue;
        }
        if ($teacher && !isset((new core_course_list_element($course))->get_course_contacts()[$teacher])) {
            continue;
        }
        $eligible[$course->id] = $course;
    }
}
$list = array_slice($eligible, $page * 20, 20, true);
$rows = [];
$summary = [];
$none = $label('notapplicable');
$headings = $storage
    ? [get_string('course'), $label('storagelogical'), $label('storagephysical'), $label('storagefiles'), get_string('date')]
    : [get_string('course'), $label('academicparticipants'), $label('reportinactive'), $label('reportcompletionactivities')];
if ($storage && $eligible) {
    $logical = $estimated = $scanned = 0;
    $data = [];
    foreach (array_chunk(array_keys($eligible), 50) as $ids) {
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'categorycourse');
        $params['source'] = scan::SOURCE;
        $params['status'] = 'finished';
        $records = $DB->get_records_sql(
            'SELECT s.id, s.courseid, s.summary, s.timemodified
               FROM {' . snapshots::TABLE . '} s
               JOIN (SELECT MAX(id) AS id FROM {' . snapshots::TABLE . '}
                      WHERE source = :source AND status = :status AND courseid ' . $insql . '
                   GROUP BY courseid) latest ON latest.id = s.id',
            $params,
        );
        foreach ($records as $entry) {
            $result = json_decode($entry->summary, true, flags: JSON_THROW_ON_ERROR);
            $logical += $result['bytes'];
            $estimated += $result['physicalbytes'];
            $scanned++;
            if (isset($list[$entry->courseid])) {
                $data[$entry->courseid] = [
                    display_size($result['bytes']), display_size($result['physicalbytes']),
                    $result['filecount'], userdate($entry->timemodified),
                ];
            }
        }
    }
    $summary = [
        ['name' => $label('storagelogical'), 'value' => display_size($logical)],
        ['name' => $label('storagephysical'), 'value' => display_size($estimated)],
        ['name' => $label('categoryscanned'), 'value' => $scanned . ' / ' . count($eligible)],
    ];
    foreach ($list as $course) {
        $rows[] = ['cells' => [
            html_to_text(format_string($course->fullname, true, ['context' => context_course::instance($course->id)]), 0, false),
            ...($data[$course->id] ?? [$none, $none, $none, $none]),
        ]];
    }
} else if ($list) {
    $metrics = new course_metrics();
    foreach ($DB->get_records_list('course', 'id', array_keys($list), 'sortorder ASC, id ASC') as $course) {
        $context = context_course::instance($course->id);
        $name = html_to_text(format_string($course->fullname, true, ['context' => $context]), 0, false);
        if (groups_get_course_groupmode($course) === SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
            $rows[] = ['cells' => [$name, $none, $none, $none]];
            continue;
        }
        $all = $metrics->learners($course, $context, 0, 0, 1, 0);
        $inactive = $metrics->learners($course, $context, 0, 0, 1, time() - $days * DAYSECS);
        $rows[] = ['cells' => [$name, $all['total'], $inactive['total'], $all['tracked']]];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/category', [
    'heading' => $title,
    'action' => (new moodle_url($path))->out(false),
    'backurl' => (new moodle_url(
        $storage ? '/local/moodlexhub/courses.php' : '/local/moodlexhub/academic.php',
        $storage ? [] : ['view' => 'analytics']
    ))->out(false),
    'view' => $view,
    'storage' => $storage,
    'analytics' => !$storage,
    'hascategory' => (bool) $category,
    'categories' => $categories,
    'teacher' => $teacher ?: '',
    'intervals' => array_map(fn(int $key) => ['key' => $key, 'selected' => $key === $days], [7, 14, 30, 90]),
    'metrics' => $summary,
    'hasmetrics' => (bool) $summary,
    'report' => ['title' => $title, 'headings' => $headings, 'rows' => $rows, 'hasrows' => (bool) $rows],
]);
echo $OUTPUT->paging_bar(count($eligible), $page, 20, $url);
echo $OUTPUT->footer();
