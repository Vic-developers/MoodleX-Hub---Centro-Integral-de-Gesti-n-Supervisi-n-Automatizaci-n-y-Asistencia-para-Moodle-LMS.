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

use local_moodlexhub\academic\assignment_queue;
use local_moodlexhub\academic\course_metrics;

require_login(null, false);
$view = optional_param('view', 'teacher', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$requestedgroup = optional_param('group', 0, PARAM_INT);
$days = optional_param('days', 14, PARAM_INT);
$inactive = optional_param('inactive', 0, PARAM_BOOL);
$learner = max(0, optional_param('learner', 0, PARAM_INT));
$enddate = optional_param('enddate', '', PARAM_TEXT);
$through = null;
if ($enddate !== '') {
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $enddate, \core_date::get_user_timezone_object());
    if (!$day || $day->format('Y-m-d') !== $enddate) {
        throw new invalid_parameter_exception();
    }
    $through = $day->modify('+1 day')->getTimestamp();
}
if (!in_array($view, ['teacher', 'analytics'], true) || !in_array($days, [7, 14, 30, 90], true)) {
    throw new invalid_parameter_exception();
}
\local_moodlexhub\module_config::require_enabled($view);
$page = max(0, min(intdiv(PHP_INT_MAX, 30), optional_param('page', 0, PARAM_INT)));
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$capability = $view === 'teacher' ? course_metrics::TEACHER : course_metrics::ANALYTICS;
$service = new course_metrics();
$course = $courseid ? get_course($courseid) : null;
$context = $course ? $service->require_access($course, $capability) : context_system::instance();

$courses = [['id' => 0, 'name' => $label('studentchoosecourse'), 'selected' => !$courseid]];
foreach (get_user_capability_course($capability, null, true, 'fullname,visible,category', 'sortorder ASC') ?: [] as $candidate) {
    if ($candidate->id == SITEID || !core_course_category::can_view_course_info($candidate)) {
        continue;
    }
    $candidatecontext = context_course::instance($candidate->id);
    if (has_capability('moodle/course:viewparticipants', $candidatecontext)) {
        $courses[] = [
            'id' => $candidate->id,
            'name' => html_to_text(format_string($candidate->fullname, true, ['context' => $candidatecontext]), 0, false),
            'selected' => $courseid == $candidate->id,
        ];
    }
}
if (!$course && count($courses) === 1) {
    require_capability($capability, $context);
}
[$group, $groups] = $course ? $service->group_scope($course, $context, $requestedgroup) : [0, []];
$path = '/local/moodlexhub/academic.php';
$url = new moodle_url($path, ['view' => $view, 'courseid' => $courseid, 'days' => $days, 'inactive' => $inactive]);
$url->params(['learner' => $learner, 'enddate' => $enddate]);
if ($requestedgroup) {
    $url->param('group', $requestedgroup);
}
if ($course) {
    $PAGE->set_course($course);
}
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$title = $label($view === 'teacher' ? 'teacherpanel' : 'courseanalytics');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$participants = ['total' => 0, 'rows' => [], 'tracked' => 0];
$assignments = [];
$upcoming = [];
$periods = null;
if ($course) {
    $cutoff = $inactive ? time() - DAYSECS * $days : 0;
    $participants = $service->learners($course, $context, $group, $page * 30, 30, $cutoff, $learner);
    if ($view === 'analytics' && (!$learner || ($participants['rows'][0]['userid'] ?? null) === $learner)) {
        $periods = $service->trends($context, $group, $days, $through, $learner);
    }
    if ($view === 'teacher' && $group >= 0) {
        $upcoming = $service->upcoming($course, $days);
        foreach ((new assignment_queue())->get_rows($course, $days, $group) as $assignment) {
            foreach (['missing', 'late', 'grading'] as $key) {
                $assignment[$key] ??= $label('notapplicable');
            }
            $assignments[] = $assignment;
        }
    }
}

echo $OUTPUT->header();
$chart = '';
if ($periods !== null) {
    $bar = new \core\chart_bar();
    $bar->set_labels([$label('academicprevperiod'), $label('academicperiod')]);
    $bar->add_series(new \core\chart_series($label('academicread'), array_column($periods, 'read')));
    $bar->add_series(new \core\chart_series($label('academicparticipation'), array_column($periods, 'participation')));
    $chart = $OUTPUT->render($bar);
}
echo $OUTPUT->render_from_template('local_moodlexhub/academic', [
    'dashboardurl' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
    'chooseurl' => (new moodle_url($path))->out(false),
    'groupurl' => (new moodle_url($path))->out(false),
    'view' => $view,
    'courseid' => $courseid,
    'courses' => $courses,
    'groups' => $groups,
    'days' => $days,
    'intervals' => array_map(fn(int $value): array => ['value' => $value, 'selected' => $days === $value], [7, 14, 30, 90]),
    'inactive' => $inactive,
    'learner' => $learner ?: '',
    'enddate' => $enddate,
    'categoryurl' => $view === 'analytics'
        && has_all_capabilities([course_metrics::ANALYTICS, 'local/moodlexhub:viewcourses'], context_system::instance())
        ? (new moodle_url('/local/moodlexhub/category.php', ['view' => 'analytics']))->out(false) : null,
    'scoped' => (bool) $course,
    'chart' => $chart,
    'teacher' => $view === 'teacher',
    'followupurl' => $view === 'teacher' && $course && has_capability(\local_moodlexhub\academic\followup::CAPABILITY, $context)
        ? (new moodle_url('/local/moodlexhub/followup.php', ['id' => $course->id, 'group' => max(0, $group)]))->out(false) : null,
    'feedbackurl' => $course && \local_moodlexhub\module_config::enabled('feedback')
        && has_all_capabilities(['local/moodlexhub:managefeedback', 'mod/assign:grade'], $context)
        ? (new moodle_url('/local/moodlexhub/feedback.php', ['id' => $course->id]))->out(false) : null,
    'assignmentrows' => $assignments,
    'hasassignments' => (bool) $assignments,
    'upcoming' => $upcoming,
    'hasupcoming' => (bool) $upcoming,
    'participants' => [
        'title' => $label('academicparticipants'),
        'headings' => [get_string('fullname'), $label('academiclastaccess'), $label('studentprogress')],
        'hasrows' => (bool) $participants['rows'],
        'rows' => $participants['rows'],
    ],
    'analytics' => $view === 'analytics' && $course,
    'logavailable' => $periods !== null,
    'pager' => $OUTPUT->paging_bar($participants['total'], $page, 30, $url),
]);
echo $OUTPUT->footer();
