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
require_once($CFG->libdir . '/formslib.php');

use local_moodlexhub\feedback\library;
use local_moodlexhub\form\feedback_template;

require_login(null, false);
$course = get_course(required_param('courseid', PARAM_INT));
$service = new library();
$context = $service->require_access($course);
$id = optional_param('templateid', 0, PARAM_INT);
$source = optional_param('source', 0, PARAM_INT);
$template = $id ? $service->require_template($id, $course) : null;
if ($template && $template->userid != $USER->id) {
    throw new moodle_exception('feedbackunavailable', 'local_moodlexhub');
}
$baseurl = new moodle_url('/local/moodlexhub/feedback.php', ['id' => $course->id]);
$url = new moodle_url('/local/moodlexhub/feedback_edit.php', ['courseid' => $course->id, 'templateid' => $id, 'source' => $source]);
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$title = get_string($id ? 'knowledgeedit' : 'feedbackcreate', 'local_moodlexhub');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$assignments = [0 => get_string('none')];
foreach (get_fast_modinfo($course)->get_instances_of('assign') as $cm) {
    if ($cm->uservisible && !$cm->deletioninprogress && has_capability('mod/assign:grade', $cm->context)) {
        $assignments[$cm->id] = $cm->get_formatted_name();
    }
}
$share = has_capability('moodle/course:update', $context);
$defaults = $template ? (array) $template : ['type' => 'comment', 'shared' => 0, 'cmid' => 0];
if (!$template && $source) {
    $initial = $service->require_template($source, $course);
    $defaults = ['type' => 'draft', 'title' => $initial->title, 'category' => $initial->category, 'body' => $initial->body];
}
$form = new feedback_template($url, ['assignments' => $assignments, 'share' => $share]);
$form->set_data(['courseid' => $course->id, 'templateid' => $id, 'source' => $source] + $defaults);
if ($form->is_cancelled()) {
    redirect($baseurl);
}
if ($data = $form->get_data()) {
    if (!in_array($data->type, library::TYPES, true)) {
        throw new invalid_parameter_exception();
    }
    if ($data->cmid) {
        $service->require_assignment($data->cmid, $course);
    }
    $record = (object) [
        'title' => $data->title, 'category' => trim($data->category ?? ''), 'body' => $data->body,
        'type' => $data->type, 'cmid' => $data->cmid ?: null,
        'shared' => (int) ($share && !empty($data->shared) && $data->type !== 'draft'),
        'timemodified' => time(),
    ];
    if ($template) {
        $record->id = $id;
        $DB->update_record(library::TABLE, $record);
    } else {
        $record->courseid = $course->id;
        $record->userid = $USER->id;
        $record->timecreated = $record->timemodified;
        $id = $DB->insert_record(library::TABLE, $record);
    }
    redirect(new moodle_url($baseurl, ['templateid' => $id]));
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
