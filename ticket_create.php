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

use local_moodlexhub\form\ticket_create;
use local_moodlexhub\support\tickets;

require_login(null, false);
\local_moodlexhub\module_config::require_enabled('support');
$context = context_system::instance();
require_capability(tickets::USE, $context);
$url = new moodle_url('/local/moodlexhub/ticket_create.php');
$listurl = new moodle_url('/local/moodlexhub/support.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$title = get_string('ticketcreate', 'local_moodlexhub');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$form = new ticket_create($url);
if ($form->is_cancelled()) {
    redirect($listurl);
}
if ($data = $form->get_data()) {
    $id = (new tickets())->create($data);
    redirect(new moodle_url('/local/moodlexhub/ticket.php', ['id' => $id]));
}
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
