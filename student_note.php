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

use local_moodlexhub\form\student_note;
use local_moodlexhub\student\planner;

require_login(null, false);
planner::require_access();
$id = optional_param('noteid', 0, PARAM_INT);
$note = $id ? $DB->get_record(planner::NOTES, ['id' => $id, 'userid' => $USER->id], '*', MUST_EXIST) : null;
$url = new moodle_url('/local/moodlexhub/student_note.php', ['noteid' => $id]);
$returnurl = new moodle_url('/local/moodlexhub/student.php');
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$title = get_string('studentnote', 'local_moodlexhub');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$form = new student_note($url);
$form->set_data(['noteid' => $id, 'title' => $note->title ?? '', 'body' => $note->body ?? '']);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    $record = (object) ['title' => $data->title, 'body' => $data->body, 'timemodified' => time()];
    if ($note) {
        $record->id = $note->id;
        $DB->update_record(planner::NOTES, $record);
    } else {
        $record->userid = $USER->id;
        $record->timecreated = $record->timemodified;
        $DB->insert_record(planner::NOTES, $record);
    }
    redirect($returnurl);
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
