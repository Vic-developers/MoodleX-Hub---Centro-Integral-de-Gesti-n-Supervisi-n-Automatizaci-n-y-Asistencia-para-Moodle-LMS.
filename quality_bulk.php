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

use local_moodlexhub\quality\auditor;
use local_moodlexhub\quality\bulk;

require_login(null, false);
bulk::require_access();
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$path = '/local/moodlexhub/quality_bulk.php';
$id = optional_param('jobid', 0, PARAM_INT);
$url = new moodle_url($path, $id ? ['jobid' => $id] : []);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('qualitybulk'));
$PAGE->set_heading($label('qualitybulk'));
if (data_submitted() && optional_param('run', 0, PARAM_BOOL)) {
    require_sesskey();
    redirect(new moodle_url($path, ['jobid' => bulk::start(required_param('categoryid', PARAM_INT))]));
}

$page = max(0, min(intdiv(PHP_INT_MAX, 25), optional_param('page', 0, PARAM_INT)));
$jobs = [];
$rows = [];
$job = $id ? $DB->get_record(bulk::JOBS, ['id' => $id, 'userid' => $USER->id], '*', MUST_EXIST) : null;
$conditions = $job ? ['jobid' => $id] : ['userid' => $USER->id];
$table = $job ? bulk::ROWS : bulk::JOBS;
foreach ($DB->get_records($table, $conditions, 'id DESC', '*', $page * 25, 25) as $entry) {
    if (!$job) {
        $jobs[] = [
            'date' => userdate($entry->timecreated), 'state' => $label('import_' . $entry->status),
            'url' => (new moodle_url($path, ['jobid' => $entry->id]))->out(false),
        ];
        continue;
    }
    $course = $DB->get_record('course', ['id' => $entry->courseid]);
    if (!$course) {
        continue;
    }
    try {
        $context = (new auditor())->require_access($course);
    } catch (moodle_exception $exception) {
        continue;
    }
    $rows[] = [
        'name' => html_to_text(format_string($course->fullname, true, ['context' => $context]), 0, false),
        'state' => $label('import_' . $entry->status),
        'link' => $entry->auditid ? (new moodle_url(
            '/local/moodlexhub/quality.php',
            ['id' => $course->id, 'audit' => $entry->auditid]
        ))->out(false) : null,
    ];
}
$categories = [['id' => 0, 'name' => $label('allcategories')]];
foreach (core_course_category::make_categories_list() as $catid => $name) {
    $categories[] = ['id' => $catid, 'name' => html_to_text($name, 0, false)];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/quality_bulk', [
    'coursesurl' => (new moodle_url('/local/moodlexhub/courses.php'))->out(false),
    'baseurl' => (new moodle_url($path))->out(false),
    'sesskey' => sesskey(),
    'categories' => $categories,
    'hasjob' => (bool) $job,
    'state' => $job ? $label('import_' . $job->status) : null,
    'jobs' => $jobs,
    'hasjobs' => (bool) $jobs,
    'rows' => $rows,
    'hasrows' => (bool) $rows,
]);
echo $OUTPUT->paging_bar($DB->count_records($table, $conditions), $page, 25, $url);
echo $OUTPUT->footer();
