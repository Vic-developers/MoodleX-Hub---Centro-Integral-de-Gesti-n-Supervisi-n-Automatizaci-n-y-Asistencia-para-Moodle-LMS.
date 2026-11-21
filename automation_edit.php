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

use local_moodlexhub\automation\engine;
use local_moodlexhub\course\catalog;
use local_moodlexhub\form\automation_rule;
use local_moodlexhub\report\builder;

require_login(null, false);
\local_moodlexhub\module_config::require_enabled('automation');
$context = context_system::instance();
require_capability(engine::CAPABILITY, $context);
$id = optional_param('ruleid', 0, PARAM_INT);
$entry = $id ? $DB->get_record(engine::RULES, ['id' => $id, 'userid' => $USER->id], '*', MUST_EXIST) : null;
$courses = [0 => get_string('none')];
foreach ((new catalog())->matching('', 0, 0) as $course) {
    $courses[$course->id] = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
}
$reports = [0 => get_string('none')];
if (has_capability(builder::CAPABILITY, $context)) {
    $reports += $DB->get_records_menu(builder::REPORTS, ['userid' => $USER->id], 'title ASC', 'id,title');
}
$base = new moodle_url('/local/moodlexhub/automation.php');
$url = new moodle_url('/local/moodlexhub/automation_edit.php', ['ruleid' => $id]);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$title = get_string($entry ? 'automationedit' : 'automationnew', 'local_moodlexhub');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$form = new automation_rule($url, ['courses' => $courses, 'reports' => $reports]);
if ($entry) {
    $form->set_data(['ruleid' => $id] + (array) $entry);
}
if ($form->is_cancelled()) {
    redirect($base);
}
if ($data = $form->get_data()) {
    $interval = (int) $data->intervalvalue;
    if (!in_array($interval, [HOURSECS, DAYSECS, WEEKSECS], true)) {
        throw new invalid_parameter_exception();
    }
    $record = (object) [
        'title' => $data->title,
        'description' => $data->description ?? '',
        'courseid' => $data->courseid,
        'recipient' => ($data->recipient ?? 0) ?: null,
        'reportid' => ($data->reportid ?? 0) ?: null,
        'conditiontype' => $data->conditiontype,
        'actiontype' => $data->actiontype,
        'threshold' => max(1, min(3650, (int) $data->threshold)),
        'intervalvalue' => $interval,
        'enabled' => (int) !empty($data->enabled),
        'nextdue' => empty($data->enabled) ? 0 : time() + $interval,
        'timemodified' => time(),
    ];
    (new engine())->require_scope($record);
    if ($entry) {
        $record->id = $id;
        $DB->update_record(engine::RULES, $record);
    } else {
        $record->userid = $USER->id;
        $id = $DB->insert_record(engine::RULES, $record);
    }
    redirect(new moodle_url($base, ['ruleid' => $id]));
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
