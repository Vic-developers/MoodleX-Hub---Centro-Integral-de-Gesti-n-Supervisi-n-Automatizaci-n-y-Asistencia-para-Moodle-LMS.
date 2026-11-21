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

require_login(null, false);
$context = context_system::instance();
require_capability('local/moodlexhub:viewdashboard', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moodlexhub/preferences.php'));
$PAGE->set_pagelayout('standard');
$title = get_string('preferences', 'local_moodlexhub');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$returnurl = new moodle_url('/local/moodlexhub/index.php');

$form = new \local_moodlexhub\form\preferences($PAGE->url);
$values = [];
foreach (\local_moodlexhub\output\dashboard::PREFERENCES as $name) {
    $values[$name] = get_user_preferences('local_moodlexhub_' . $name, 1);
}
$form->set_data($values);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    foreach (\local_moodlexhub\output\dashboard::PREFERENCES as $name) {
        set_user_preference('local_moodlexhub_' . $name, (int) $data->$name);
    }
    redirect($returnurl, get_string('preferencessaved', 'local_moodlexhub'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
