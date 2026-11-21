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

require_login(null, false);
$context = context_system::instance();
require_capability('local/moodlexhub:viewdashboard', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moodlexhub/index.php'));
$PAGE->set_pagelayout('standard');
$title = get_string('pluginname', 'local_moodlexhub');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$data = (new \local_moodlexhub\output\dashboard())->export_for_template($PAGE->get_renderer('core'));
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/dashboard', $data);
echo $OUTPUT->footer();
