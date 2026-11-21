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

use local_moodlexhub\course\catalog;
use local_moodlexhub\form\report_builder;
use local_moodlexhub\report\builder;

require_login(null, false);
\local_moodlexhub\module_config::require_enabled('reports');
$context = context_system::instance();
require_capability(builder::CAPABILITY, $context);
$id = optional_param('reportid', 0, PARAM_INT);
$record = $id ? $DB->get_record(builder::REPORTS, ['id' => $id, 'userid' => $USER->id], '*', MUST_EXIST) : null;
$courses = [0 => get_string('none')];
foreach ((new catalog())->matching('', 0, 0) as $course) {
    $courses[$course->id] = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
}
$url = new moodle_url('/local/moodlexhub/report_edit.php', ['reportid' => $id]);
$returnurl = new moodle_url('/local/moodlexhub/reports.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$title = get_string($record ? 'reportedit' : 'reportnew', 'local_moodlexhub');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$form = new report_builder($url, ['courses' => $courses]);
if ($record) {
    $form->set_data(['reportid' => $id] + (array) $record);
}
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    $service = new builder();
    $course = $service->require_source($data->source, $data->courseid ?? 0);
    $interval = (int) $data->intervalvalue;
    if (!in_array($interval, builder::INTERVALS, true)) {
        throw new invalid_parameter_exception();
    }
    $filter = match ($data->source) {
        'quality' => max(1, min(3, (int) $data->filtervalue)),
        'learners' => max(1, min(365, (int) $data->filtervalue)),
        default => 0,
    };
    $save = (object) [
        'title' => $data->title,
        'source' => $data->source,
        'courseid' => $course->id ?? 0,
        'filtervalue' => $filter,
        'intervalvalue' => $interval,
        'nextdue' => $interval ? time() + $interval : 0,
        'timemodified' => time(),
    ];
    if ($record) {
        $save->id = $id;
        $DB->update_record(builder::REPORTS, $save);
    } else {
        $save->userid = $USER->id;
        $id = $DB->insert_record(builder::REPORTS, $save);
    }
    redirect(new moodle_url($returnurl, ['reportid' => $id]));
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
