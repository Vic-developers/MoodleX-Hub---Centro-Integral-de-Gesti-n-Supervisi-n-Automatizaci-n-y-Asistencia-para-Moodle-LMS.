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
require_once($CFG->dirroot . '/calendar/lib.php');

use local_moodlexhub\form\student_reminder;
use local_moodlexhub\student\planner;

require_login(null, false);
planner::require_access();
$context = context_user::instance($USER->id);
require_capability('moodle/calendar:manageownentries', $context);
$url = new moodle_url('/local/moodlexhub/student_reminder.php');
$returnurl = new moodle_url('/local/moodlexhub/student.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$title = get_string('studentreminderadd', 'local_moodlexhub');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$form = new student_reminder($url);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    $event = calendar_event::create([
        'name' => $data->title,
        'description' => '',
        'format' => FORMAT_PLAIN,
        'eventtype' => 'user',
        'userid' => $USER->id,
        'courseid' => 0,
        'groupid' => 0,
        'timestart' => $data->timestart,
        'timeduration' => 0,
        'visible' => 1,
    ]);
    if (!$event) {
        throw new moodle_exception('studentreminderfailed', 'local_moodlexhub');
    }
    redirect(new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $data->timestart]));
}
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
