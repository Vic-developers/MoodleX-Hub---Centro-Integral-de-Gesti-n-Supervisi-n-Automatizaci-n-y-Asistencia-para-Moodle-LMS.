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

use core_course\management\helper;
use local_moodlexhub\report\snapshots;
use local_moodlexhub\storage\scan;

$course = get_course(required_param('id', PARAM_INT));
require_login($course, false);
\local_moodlexhub\module_config::require_enabled('courses');
$context = context_course::instance($course->id);
require_all_capabilities(['local/moodlexhub:managecourses', ...helper::get_course_copy_capabilities()], $context);
if ($course->id == SITEID) {
    throw new invalid_parameter_exception();
}
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$courseurl = new moodle_url('/local/moodlexhub/course.php', ['id' => $course->id]);
$returnurl = has_capability('local/moodlexhub:viewcourses', $context) ? $courseurl : new moodle_url('/course/view.php', ['id' => $course->id]);
$url = new moodle_url('/local/moodlexhub/copy.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('coursecopy'));
$PAGE->set_heading($label('coursecopy'));

$temp = $CFG->tempdir;
$space = is_dir($temp) && is_readable($temp) ? disk_free_space($temp) : false;
$estimate = null;
if (has_all_capabilities([scan::CAPABILITY, 'moodle/course:update'], $context)) {
    foreach (snapshots::recent($course->id, scan::SOURCE) as $entry) {
        if ($entry->status === 'finished') {
            $estimate = json_decode($entry->summary, true, flags: JSON_THROW_ON_ERROR)['bytes'];
            break;
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/copy', [
    'courseurl' => $returnurl->out(false),
    'copyurl' => (new moodle_url('/backup/copy.php', ['id' => $course->id, 'returnto' => 'url', 'returnurl' => $url->out(false)]))->out(false),
    'progressurl' => (new moodle_url('/backup/copyprogress.php', ['id' => $course->id]))->out(false),
    'space' => $space === false ? $label('notapplicable') : display_size($space),
    'estimate' => $estimate === null ? null : display_size($estimate),
]);
echo $OUTPUT->footer();
