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

use local_moodlexhub\course\catalog;

require_login(null, false);
\local_moodlexhub\module_config::require_enabled('courses');
$system = context_system::instance();
require_capability(catalog::VIEW, $system);

$search = optional_param('search', '', PARAM_TEXT);
$category = max(0, optional_param('category', 0, PARAM_INT));
$visibility = optional_param('visibility', 0, PARAM_INT);
$health = optional_param('health', '', PARAM_ALPHA);
if (!in_array($visibility, [0, 1, 2], true) || ($health !== '' && !in_array($health, catalog::HEALTH, true))) {
    throw new invalid_parameter_exception();
}
$download = optional_param('download', '', PARAM_ALPHA);
$perpage = 25;
$page = max(0, min(intdiv(PHP_INT_MAX, $perpage), optional_param('page', 0, PARAM_INT)));
$url = new moodle_url('/local/moodlexhub/courses.php', [
    'search' => $search,
    'category' => $category,
    'visibility' => $visibility,
    'health' => $health,
]);
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_context($system);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('courses'));
$PAGE->set_heading($label('courses'));
$PAGE->navbar->add($label('pluginname'), new moodle_url('/local/moodlexhub/index.php'));

$matches = (new catalog())->matching($search, $category, $visibility, $health);
$canexport = has_capability('local/moodlexhub:exportreports', $system);
$visibilitytext = fn($visible): string => $label($visible ? 'coursevisible' : 'coursehidden');
if ($download !== '') {
    require_capability('local/moodlexhub:exportreports', $system);
    if ($download !== 'csv') {
        throw new invalid_parameter_exception();
    }
    \local_moodlexhub\event\course_catalogue_exported::create(['context' => $system])->trigger();
    $columns = [
        'fullname' => get_string('fullnamecourse'),
        'shortname' => get_string('shortnamecourse'),
        'category' => get_string('category'),
        'visible' => $label('visibility'),
    ];
    $categories = [];
    $rows = (function () use ($matches, $visibilitytext, &$categories): Generator {
        foreach ($matches as $course) {
            $categories[$course->category] ??= core_course_category::get($course->category, MUST_EXIST, true)
                ->get_formatted_name();
            yield [
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
                'category' => html_to_text($categories[$course->category], 0, false),
                'visible' => $visibilitytext($course->visible),
            ];
        }
    })();
    \core\dataformat::download_data('moodlexhub-courses-' . gmdate('Ymd-His'), 'csv', $columns, $rows);
    exit;
}

$total = 0;
$rows = [];
$start = $page * $perpage;
foreach ($matches as $course) {
    if ($total >= $start && count($rows) < $perpage) {
        $context = context_course::instance($course->id);
        $rows[] = [
            'name' => html_to_text(format_string($course->fullname, true, ['context' => $context]), 0, false),
            'shortname' => $course->shortname,
            'visible' => $visibilitytext($course->visible),
            'url' => (new moodle_url('/local/moodlexhub/course.php', ['id' => $course->id]))->out(false),
        ];
    }
    $total++;
}
if ($total && $start >= $total) {
    redirect($url);
}
$categories = [['id' => 0, 'name' => $label('allcategories'), 'selected' => !$category]];
foreach (core_course_category::make_categories_list() as $id => $name) {
    $categories[] = ['id' => $id, 'name' => html_to_text($name, 0, false), 'selected' => $id == $category];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/courses', [
    'dashboardurl' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
    'action' => (new moodle_url('/local/moodlexhub/courses.php'))->out(false),
    'search' => $search,
    'categories' => $categories,
    'visibilities' => [
        ['value' => 0, 'name' => $label('allvisibilities'), 'selected' => $visibility === 0],
        ['value' => 1, 'name' => $label('coursevisible'), 'selected' => $visibility === 1],
        ['value' => 2, 'name' => $label('coursehidden'), 'selected' => $visibility === 2],
    ],
    'healthoptions' => array_map(fn($key) => [
        'value' => $key, 'name' => $label('coursehealth_' . ($key ?: 'all')), 'selected' => $key === $health,
    ], ['', ...catalog::HEALTH]),
    'exporturl' => $canexport ? (new moodle_url($url, ['download' => 'csv']))->out(false) : null,
    'bulkurl' => \local_moodlexhub\module_config::enabled('quality')
        && has_capability(\local_moodlexhub\quality\auditor::CAPABILITY, $system)
        ? (new moodle_url('/local/moodlexhub/quality_bulk.php'))->out(false) : null,
    'storagelisturl' => \local_moodlexhub\module_config::enabled('storage')
        && has_capability('local/moodlexhub:viewstorage', $system)
        ? (new moodle_url('/local/moodlexhub/category.php', ['view' => 'storage', 'category' => $category]))->out(false) : null,
    'courses' => $rows,
    'hascourses' => !empty($rows),
]);
echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
echo $OUTPUT->footer();
