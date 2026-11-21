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

use local_moodlexhub\student\planner;

require_login(null, false);
$service = new planner();
$service::require_access();
$context = context_user::instance($USER->id);
$courseid = optional_param('courseid', 0, PARAM_INT);
$days = optional_param('days', 14, PARAM_INT);
$status = optional_param('status', 'all', PARAM_ALPHA);
if (!in_array($days, [7, 14, 30, 60], true) || !in_array($status, ['all', 'pending', 'completed', 'favourites'], true)) {
    throw new invalid_parameter_exception();
}
$activitypage = max(0, min(intdiv(PHP_INT_MAX, 25), optional_param('page', 0, PARAM_INT)));
$notepage = max(0, min(intdiv(PHP_INT_MAX, 10), optional_param('notepage', 0, PARAM_INT)));
$path = '/local/moodlexhub/student.php';
$url = new moodle_url($path, ['courseid' => $courseid, 'days' => $days, 'status' => $status]);
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('studentplanner'));
$PAGE->set_heading($label('studentplanner'));

if (data_submitted()) {
    require_sesskey();
    if ($activity = optional_param('activity', 0, PARAM_INT)) {
        $service->set_favourite($activity, (bool) optional_param('favourite', 0, PARAM_BOOL));
        redirect(new moodle_url($url, ['page' => $activitypage]));
    }
    if ($note = optional_param('deletenote', 0, PARAM_INT)) {
        $DB->delete_records(planner::NOTES, ['id' => $note, 'userid' => $USER->id]);
        redirect($url);
    }
}

$courses = [['id' => 0, 'name' => $label('studentchoosecourse'), 'selected' => !$courseid]];
foreach (enrol_get_my_courses() as $course) {
    if (!can_access_course($course, $USER, '', true)) {
        continue;
    }
    $coursecontext = context_course::instance($course->id);
    $courses[] = [
        'id' => $course->id,
        'name' => html_to_text(format_string($course->fullname, true, ['context' => $coursecontext]), 0, false),
        'selected' => $course->id == $courseid,
    ];
}
$activities = $courseid ? $service->activities($courseid) : [];
$tracked = count(array_filter($activities, static fn(array $row): bool => $row['tracked']));
$completed = count(array_filter($activities, static fn(array $row): bool => $row['completed']));
$activities = array_values(array_filter($activities, static function (array $row) use ($status): bool {
    return match ($status) {
        'favourites' => $row['favourite'],
        'pending' => $row['tracked'] && !$row['completed'],
        'completed' => $row['completed'],
        default => true,
    };
}));
$notes = [];
$conditions = ['userid' => $USER->id];
foreach ($DB->get_records(planner::NOTES, $conditions, 'timemodified DESC, id DESC', '*', $notepage * 10, 10) as $note) {
    $notes[] = [
        'id' => $note->id,
        'title' => $note->title,
        'body' => $note->body,
        'url' => (new moodle_url('/local/moodlexhub/student_note.php', ['noteid' => $note->id]))->out(false),
    ];
}
$eventrows = $service->upcoming($days);
$sesskey = sesskey();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/student', [
    'dashboardurl' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
    'calendarurl' => (new moodle_url('/calendar/view.php', ['view' => 'month']))->out(false),
    'reminderurl' => has_capability('moodle/calendar:manageownentries', $context)
        ? (new moodle_url('/local/moodlexhub/student_reminder.php'))->out(false) : null,
    'newnoteurl' => (new moodle_url('/local/moodlexhub/student_note.php'))->out(false),
    'action' => (new moodle_url($path))->out(false),
    'actionwithfilters' => (new moodle_url($url, ['page' => $activitypage]))->out(false),
    'sesskey' => $sesskey,
    'showactivities' => (bool) $courseid,
    'courses' => $courses,
    'periods' => array_map(static fn(int $value): array => ['days' => $value, 'selected' => $value === $days], [7, 14, 30, 60]),
    'filters' => array_map(static fn(string $value): array => [
        'key' => $value, 'label' => $label('studentfilter_' . $value), 'selected' => $value === $status,
    ], ['all', 'pending', 'completed', 'favourites']),
    'events' => $eventrows,
    'hasevents' => (bool) $eventrows,
    'hasactivities' => (bool) $activities,
    'tracked' => $tracked,
    'completed' => $completed,
    'activities' => array_slice($activities, $activitypage * 25, 25),
    'activitypager' => $OUTPUT->paging_bar(count($activities), $activitypage, 25, $url),
    'notepager' => $OUTPUT->paging_bar($DB->count_records(planner::NOTES, $conditions), $notepage, 10, $url, 'notepage'),
    'notes' => $notes,
    'hasnotes' => (bool) $notes,
]);
echo $OUTPUT->footer();
