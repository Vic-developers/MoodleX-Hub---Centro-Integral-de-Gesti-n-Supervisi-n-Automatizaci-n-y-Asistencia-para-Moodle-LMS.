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

use local_moodlexhub\academic\activity_workload;
use local_moodlexhub\academic\course_metrics;
use local_moodlexhub\academic\followup;

$course = get_course(required_param('id', PARAM_INT));
require_login($course, false);
$requestedgroup = max(0, optional_param('group', 0, PARAM_INT));
$cmid = max(0, optional_param('cmid', 0, PARAM_INT));
$view = optional_param('view', 'evidence', PARAM_ALPHA);
if (!in_array($view, ['evidence', 'open', 'closed'], true)) {
    throw new invalid_parameter_exception();
}
$caseid = max(0, optional_param('caseid', 0, PARAM_INT));
$page = max(0, min(intdiv(PHP_INT_MAX, 15), optional_param('page', 0, PARAM_INT)));
$historypage = max(0, min(intdiv(PHP_INT_MAX, 50), optional_param('historypage', 0, PARAM_INT)));

$service = new followup();
[$group, $groups] = $service->scope($course, $requestedgroup);
$context = context_course::instance($course->id);
$path = '/local/moodlexhub/followup.php';
$url = new moodle_url($path, ['id' => $course->id, 'group' => max(0, $group), 'cmid' => $cmid, 'view' => $view]);
$workload = new activity_workload();
$activities = $group < 0 ? [] : $workload->activities($course, $group);
if ($cmid && !isset($activities[$cmid])) {
    throw new moodle_exception('followupunavailable', 'local_moodlexhub');
}

if (data_submitted()) {
    require_sesskey();
    $action = required_param('operation', PARAM_ALPHA);
    if ($action === 'open') {
        $caseid = $service->open($course, $requestedgroup, required_param('learner', PARAM_INT), $cmid);
    } else {
        $service->update($course, $requestedgroup, $caseid, $action, optional_param('note', '', PARAM_RAW_TRIMMED));
    }
    redirect(new moodle_url($url, ['caseid' => $caseid]));
}

$label = static fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url($url, $caseid ? ['caseid' => $caseid] : []));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('followup'));
$PAGE->set_heading($label('followup'));
$renderer = $PAGE->get_renderer('core');

$optionrows = [['id' => 0, 'name' => $label('followupaccess'), 'selected' => !$cmid]];
foreach ($activities as $activity) {
    $optionrows[] = [
        'id' => $activity->id,
        'name' => html_to_text($activity->get_formatted_name(), 0, false),
        'selected' => $activity->id == $cmid,
    ];
}
$case = $caseid ? $service->get($course, $requestedgroup, $caseid) : null;
$rows = [];
$queued = [];
$detail = [];
$pager = '';
if ($case) {
    $learner = core_user::get_user($case->userid, '*', MUST_EXIST);
    $details = $case->cmid ? $activities[$case->cmid] : null;
    $history = [];
    $entrylist = $DB->get_records(followup::HISTORY, ['caseid' => $case->id], 'id DESC', '*', $historypage * 50, 50);
    foreach ($entrylist as $entry) {
        $actor = $entry->actorid ? core_user::get_user($entry->actorid) : null;
        $history[] = [
            'action' => $label('followupaction_' . $entry->action),
            'note' => $entry->note,
            'time' => userdate($entry->timecreated),
            'actor' => $actor ? fullname($actor) : $label('followupactorunknown'),
        ];
    }
    $assignee = $case->assignee ? core_user::get_user($case->assignee) : null;
    $open = $case->status === 'open';
    $detail = [
        'id' => $caseid,
        'learner' => fullname($learner),
        'reason' => $label('followupstate_' . $case->reason),
        'status' => $label('followupstatus_' . $case->status),
        'assignee' => $assignee ? fullname($assignee) : $label('followupunassigned'),
        'activity' => $details ? html_to_text($details->get_formatted_name(), 0, false) : '',
        'activityurl' => $details ? $workload->review_url($details, $group) : '',
        'open' => $open,
        'cantake' => $open && !$case->assignee,
        'canrelease' => $open && (int) $case->assignee === (int) $USER->id,
        'history' => $history,
    ];
    $pager = $renderer->paging_bar(
        $DB->count_records(followup::HISTORY, ['caseid' => $caseid]),
        $historypage,
        50,
        new moodle_url($url, ['caseid' => $caseid]),
        'historypage'
    );
} else if ($view !== 'evidence') {
    $result = $service->queue($course, $requestedgroup, $view, $cmid, $page * 15, 15);
    foreach ($result['rows'] as $entry) {
        $activity = $activities[$entry->cmid] ?? null;
        $queued[] = [
            'name' => fullname($entry, has_capability('moodle/site:viewfullnames', $context)),
            'reason' => $label('followupstate_' . $entry->reason),
            'activity' => $activity ? html_to_text($activity->get_formatted_name(), 0, false) : $label('followupaccess'),
            'time' => userdate($entry->timemodified),
            'url' => (new moodle_url($url, ['caseid' => $entry->id]))->out(false),
        ];
    }
    $pager = $renderer->paging_bar($result['total'], $page, 15, $url);
} else {
    $roster = (new course_metrics())->learners($course, $context, $group, $page * 15, 15, 0);
    $byuser = [];
    $existing = [];
    if ($roster['rows']) {
        [$insql, $params] = $DB->get_in_or_equal(array_column($roster['rows'], 'userid'), SQL_PARAMS_NAMED);
        $cases = $DB->get_records_select(
            followup::CASES,
            'courseid = :courseid AND userid ' . $insql,
            ['courseid' => $course->id] + $params,
            'id DESC',
        );
        foreach ($cases as $entry) {
            if (!$entry->cmid || isset($activities[$entry->cmid])) {
                $existing[$entry->userid][$entry->cmid][$entry->reason] = true;
                $byuser[$entry->userid][] = [
                    'name' => $label('followupstate_' . $entry->reason) . ' · ' . $label('followupstatus_' . $entry->status),
                    'url' => (new moodle_url($url, ['caseid' => $entry->id]))->out(false),
                ];
            }
        }
    }
    foreach ($roster['rows'] as $participant) {
        $userid = $participant['userid'];
        $result = $cmid ? $workload->inspect($course, $activities[$cmid], $userid) : [
            'state' => $service->inactive($course->id, $userid) ? 'inactive' : 'active',
            'due' => 0,
        ];
        $rows[] = [
            'userid' => $userid,
            'name' => $participant['cells'][0],
            'lastaccess' => $participant['cells'][1],
            'state' => $label('followupstate_' . $result['state']),
            'due' => $result['due'] ? userdate($result['due']) : '',
            'canopen' => in_array($result['state'], ['inactive', 'late', 'grading'], true)
                && empty($existing[$userid][$cmid][$result['state']]),
            'cases' => $byuser[$userid] ?? [],
        ];
    }
    $pager = $renderer->paging_bar($roster['total'], $page, 15, $url);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/followup', [
    'backurl' => $url->out(false),
    'teacherurl' => (new moodle_url('/local/moodlexhub/academic.php', ['courseid' => $course->id]))->out(false),
    'action' => (new moodle_url($path))->out(false),
    'posturl' => $url->out(false),
    'courseid' => $course->id,
    'sesskey' => sesskey(),
    'groups' => $groups,
    'hasgroups' => (bool) $groups,
    'options' => $optionrows,
    'selectedactivityurl' => $cmid ? $workload->review_url($activities[$cmid], $group) : '',
    'rows' => $rows,
    'evidenceview' => $view === 'evidence',
    'queueview' => $view !== 'evidence',
    'hasqueue' => (bool) $queued,
    'queued' => $queued,
    'views' => array_map(
        fn(string $item): array => ['id' => $item, 'name' => $label('followupview_' . $item), 'selected' => $item === $view],
        ['evidence', 'open', 'closed'],
    ),
    'hasrows' => (bool) $rows,
    'overview' => !$caseid,
    'detail' => $detail,
    'pager' => $pager,
]);
echo $OUTPUT->footer();
