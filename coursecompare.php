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
use local_moodlexhub\module_config;

require_login(null, false);
module_config::require_enabled('courses');
$service = new catalog();
$first = get_course(required_param('id', PARAM_INT));
$context = $service->require_course($first);
require_capability('moodle/course:update', $context);
$otherid = optional_param('other', 0, PARAM_INT);
$second = $otherid ? get_course($otherid) : null;
$secondcontext = null;
if ($second) {
    $secondcontext = $service->require_course($second);
    require_capability('moodle/course:update', $secondcontext);
}
$path = '/local/moodlexhub/coursecompare.php';
$url = new moodle_url($path, ['id' => $first->id, 'other' => $otherid]);
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_course($first);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('coursecompare'));
$PAGE->set_heading($label('coursecompare'));

$options = [['id' => 0, 'name' => $label('studentchoosecourse'), 'selected' => !$otherid]];
foreach ($service->matching('', 0, 0) as $course) {
    $coursecontext = context_course::instance($course->id);
    if ($course->id != $first->id && has_capability('moodle/course:update', $coursecontext)) {
        $options[] = [
            'id' => $course->id,
            'name' => html_to_text(format_string($course->fullname, true, ['context' => $coursecontext]), 0, false),
            'selected' => $course->id == $otherid,
        ];
    }
}

$report = null;
if ($second) {
    $value = fn($course): array => [
        $course->format,
        $label($course->visible ? 'coursevisible' : 'coursehidden'),
        $course->startdate ? userdate($course->startdate) : get_string('none'),
        $course->enddate ? userdate($course->enddate) : get_string('none'),
        $label($course->enablecompletion ? 'enabled' : 'disabled'),
        match ((int) $course->groupmode) {
            SEPARATEGROUPS => get_string('groupsseparate'),
            VISIBLEGROUPS => get_string('groupsvisible'),
            default => get_string('groupsnone'),
        },
        $course->maxbytes ? display_size($course->maxbytes) : $label('courseusesitemax'),
    ];
    $left = $value($first);
    $right = $value($second);
    $labels = [get_string('format'), $label('visibility'), get_string('startdate'), get_string('enddate'),
        $label('comparecompletion'), get_string('groupmode'), get_string('maximumupload')];
    $rows = [];
    foreach ($labels as $index => $name) {
        $rows[] = ['cells' => [$name, $left[$index], $right[$index], get_string($left[$index] === $right[$index] ? 'no' : 'yes')]];
    }
    $report = [
        'title' => $label('coursecompare'),
        'hasrows' => true,
        'headings' => [
            $label('coursefield'),
            html_to_text(format_string($first->fullname, true, ['context' => $context]), 0, false),
            html_to_text(format_string($second->fullname, true, ['context' => $secondcontext]), 0, false),
            $label('coursemismatch'),
        ],
        'rows' => $rows,
    ];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/coursecompare', [
    'courseurl' => (new moodle_url('/local/moodlexhub/course.php', ['id' => $first->id]))->out(false),
    'action' => (new moodle_url($path))->out(false),
    'id' => $first->id,
    'options' => $options,
    'report' => $report,
]);
echo $OUTPUT->footer();
