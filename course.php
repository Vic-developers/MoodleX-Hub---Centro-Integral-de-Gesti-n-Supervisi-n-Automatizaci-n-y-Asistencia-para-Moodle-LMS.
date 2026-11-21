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
$id = required_param('id', PARAM_INT);
if ($id <= SITEID) {
    throw new invalid_parameter_exception();
}
$course = get_course($id);
$context = (new catalog())->require_course($course);
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moodlexhub/course.php', ['id' => $id]));
$PAGE->set_pagelayout('standard');
$title = format_string($course->fullname, true, ['context' => $context]);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$catalogurl = new moodle_url('/local/moodlexhub/courses.php');
$canlist = has_capability(catalog::VIEW, context_system::instance());
if ($canlist) {
    $PAGE->navbar->add($label('courses'), $catalogurl);
}
$category = core_course_category::get($course->category, MUST_EXIST, true);
$fields = [
    ['label' => get_string('shortnamecourse'), 'value' => $course->shortname],
    ['label' => get_string('category'), 'value' => html_to_text($category->get_formatted_name(), 0, false)],
    ['label' => $label('visibility'), 'value' => $label($course->visible ? 'coursevisible' : 'coursehidden')],
    ['label' => get_string('startdate'), 'value' => $course->startdate ? userdate($course->startdate) : get_string('none')],
    ['label' => get_string('enddate'), 'value' => $course->enddate ? userdate($course->enddate) : get_string('none')],
];
$contacts = (new core_course_list_element($course))->get_course_contacts();
if ($contacts) {
    $fields[] = [
        'label' => $label('rule_coursecontacts'),
        'value' => implode(', ', array_map(static fn(array $contact): string => html_to_text($contact['username'], 0, false), $contacts)),
    ];
}
if (has_capability('moodle/course:viewparticipants', $context)) {
    $group = groups_get_course_group($course, true);
    if (
        $group || (int) groups_get_course_groupmode($course) !== SEPARATEGROUPS
        || has_capability('moodle/site:accessallgroups', $context)
    ) {
        $fields[] = ['label' => $label('activeenrolments'), 'value' => count_enrolled_users($context, '', $group, true)];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/course', [
    'catalogurl' => $canlist ? $catalogurl->out(false) : null,
    'compareurl' => has_capability('moodle/course:update', $context)
        ? (new moodle_url('/local/moodlexhub/coursecompare.php', ['id' => $course->id]))->out(false) : null,
    'logsurl' => has_capability('report/log:view', $context)
        ? (new moodle_url('/report/log/index.php', ['id' => $course->id]))->out(false) : null,
    'participantsurl' => has_capability('moodle/course:viewparticipants', $context)
        ? (new moodle_url('/user/index.php', ['id' => $course->id]))->out(false) : null,
    'courseurl' => can_access_course($course) ? (new moodle_url('/course/view.php', ['id' => $id]))->out(false) : null,
    'qualityurl' => \local_moodlexhub\module_config::enabled('quality')
        && has_all_capabilities(['local/moodlexhub:auditcourses', 'moodle/course:update'], $context)
        ? (new moodle_url('/local/moodlexhub/quality.php', ['id' => $course->id]))->out(false)
        : null,
    'enrolurl' => \local_moodlexhub\module_config::enabled('users')
        && has_capability(\local_moodlexhub\enrol\imports::CAPABILITY, $context)
        ? (new moodle_url('/local/moodlexhub/enrol.php', ['id' => $course->id]))->out(false)
        : null,
    'gradesurl' => \local_moodlexhub\module_config::enabled('grades') && has_all_capabilities([
        \local_moodlexhub\grade\diagnostics::CAPABILITY,
        ...\local_moodlexhub\grade\diagnostics::CORE_CAPABILITIES,
    ], $context) ? (new moodle_url('/local/moodlexhub/grades.php', ['id' => $course->id]))->out(false) : null,
    'storageurl' => \local_moodlexhub\module_config::enabled('storage')
        && has_all_capabilities(['local/moodlexhub:viewstorage', 'moodle/course:update'], $context)
        ? (new moodle_url('/local/moodlexhub/storage.php', ['id' => $course->id]))->out(false) : null,
    'questionsurl' => \local_moodlexhub\module_config::enabled('questions') && has_capability('local/moodlexhub:viewquestions', $context)
        ? (new moodle_url('/local/moodlexhub/questions.php', ['id' => $course->id]))->out(false) : null,
    'feedbackurl' => \local_moodlexhub\module_config::enabled('feedback')
        && has_all_capabilities(['local/moodlexhub:managefeedback', 'mod/assign:grade'], $context)
        ? (new moodle_url('/local/moodlexhub/feedback.php', ['id' => $course->id]))->out(false) : null,
    'copyurl' => has_all_capabilities([
        'local/moodlexhub:managecourses', ...\core_course\management\helper::get_course_copy_capabilities(),
    ], $context) ? (new moodle_url('/local/moodlexhub/copy.php', ['id' => $course->id]))->out(false) : null,
    'fields' => $fields,
]);
echo $OUTPUT->footer();
