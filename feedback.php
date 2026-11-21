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

use local_moodlexhub\feedback\library;

require_login(null, false);
$course = get_course(required_param('id', PARAM_INT));
$service = new library();
$context = $service->require_access($course);
$id = optional_param('templateid', 0, PARAM_INT);
$filter = optional_param('filter', '', PARAM_ALPHA);
$search = trim(optional_param('search', '', PARAM_TEXT));
if ($filter && !in_array($filter, [...library::TYPES, 'favourites'], true)) {
    throw new invalid_parameter_exception();
}
$page = max(0, min(intdiv(PHP_INT_MAX, 25), optional_param('page', 0, PARAM_INT)));
$path = '/local/moodlexhub/feedback.php';
$url = new moodle_url($path, ['id' => $course->id, 'filter' => $filter, 'search' => $search]);
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$template = $id ? $service->require_template($id, $course) : null;
$editurl = new moodle_url('/local/moodlexhub/feedback_edit.php', ['courseid' => $course->id]);
if ($template && data_submitted()) {
    require_sesskey();
    $service->favourite($template, $context, (bool) required_param('favourite', PARAM_BOOL));
    redirect(new moodle_url($url, ['templateid' => $id]));
}

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('feedback'));
$PAGE->set_heading($label('feedback'));
$rows = [];
$total = 0;
$favourites = $service->get_favourites();
if (!$template) {
    $conditions = ['courseid = :courseid', '(userid = :userid OR shared = 1)'];
    $params = ['courseid' => $course->id, 'userid' => $USER->id];
    if ($search !== '') {
        $conditions[] = '(' . $DB->sql_like('title', ':title', false) . ' OR ' . $DB->sql_like('category', ':category', false) . ')';
        $params['title'] = $params['category'] = '%' . $DB->sql_like_escape($search) . '%';
    }
    if ($filter === 'favourites') {
        [$insql, $favouriteparams] = $DB->get_in_or_equal(array_keys($favourites), SQL_PARAMS_NAMED, 'favourite', true, null);
        $conditions[] = 'id ' . $insql;
        $params += $favouriteparams;
    } else if ($filter !== '') {
        $conditions[] = 'type = :type';
        $params['type'] = $filter;
    }
    $where = implode(' AND ', $conditions);
    $total = $DB->count_records_select(library::TABLE, $where, $params);
    foreach (
        $DB->get_records_select(
            library::TABLE,
            $where,
            $params,
            'timemodified DESC, id DESC',
            'id,title,category,shared,type,timemodified',
            $page * 25,
            25
        ) as $entry
    ) {
        $rows[] = [
            'title' => $entry->title, 'category' => $entry->category, 'type' => $label('feedbacktype_' . $entry->type),
            'date' => userdate($entry->timemodified),
            'url' => (new moodle_url($url, ['templateid' => $entry->id]))->out(false),
        ];
    }
}
$assignurl = null;
if ($template && $template->cmid) {
    $cm = get_fast_modinfo($course)->get_cms()[$template->cmid] ?? null;
    if ($cm && $cm->uservisible && !$cm->deletioninprogress && has_capability('mod/assign:grade', $cm->context)) {
        $assignurl = (new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grading']))->out(false);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/feedback', [
    'academicurl' => (new moodle_url('/local/moodlexhub/academic.php', ['view' => 'teacher', 'courseid' => $course->id]))->out(false),
    'listurl' => $url->out(false),
    'newurl' => $editurl->out(false),
    'action' => (new moodle_url($path))->out(false),
    'courseid' => $course->id,
    'search' => $search,
    'filters' => array_map(fn($key) => [
        'key' => $key, 'name' => $label('feedbacktype_' . ($key ?: 'all')), 'selected' => $filter === $key,
    ], ['', ...library::TYPES, 'favourites']),
    'rows' => $rows,
    'hasrows' => (bool) $rows,
    'template' => $template ? [
        'title' => $template->title, 'category' => $template->category, 'body' => $template->body,
        'type' => $label('feedbacktype_' . $template->type), 'favourite' => isset($favourites[$id]),
        'posturl' => (new moodle_url($url, ['templateid' => $id]))->out(false), 'sesskey' => sesskey(),
        'editurl' => $template->userid == $USER->id ? (new moodle_url($editurl, ['templateid' => $id]))->out(false) : null,
        'drafturl' => (new moodle_url($editurl, ['source' => $id]))->out(false), 'assignurl' => $assignurl,
    ] : null,
]);
if (!$template) {
    echo $OUTPUT->paging_bar($total, $page, 25, $url);
}
echo $OUTPUT->footer();
