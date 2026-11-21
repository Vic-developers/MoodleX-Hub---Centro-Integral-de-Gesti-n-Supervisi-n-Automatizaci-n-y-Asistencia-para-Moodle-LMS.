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

use local_moodlexhub\form\ticket_update;
use local_moodlexhub\support\tickets;

require_login(null, false);
\local_moodlexhub\module_config::require_enabled('support');
$id = required_param('id', PARAM_INT);
$context = context_system::instance();
$ticket = $DB->get_record(tickets::TABLE, ['id' => $id]);
$service = new tickets();
if (!$ticket || !$service->can_access($ticket, $USER->id)) {
    throw new moodle_exception('ticketunavailable', 'local_moodlexhub');
}
$url = new moodle_url('/local/moodlexhub/ticket.php', ['id' => $id]);
$listurl = new moodle_url('/local/moodlexhub/support.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_title($label('ticketopen'));
$PAGE->set_heading($label('ticketopen'));

$states = $service->available_states($ticket, $USER->id);
$form = null;
if ($ticket->status !== 'closed' || has_capability(tickets::MANAGE, $context)) {
    $states = array_combine($states, array_map(fn($state) => $label('ticketstatus_' . $state), $states));
    $priorities = array_combine(tickets::PRIORITIES, array_map(fn($key) => $label('ticketpriority_' . $key), tickets::PRIORITIES));
    $manage = has_capability(tickets::MANAGE, $context);
    $agents = [0 => $label('ticketunassigned')];
    if ($manage) {
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        foreach (get_users_by_capability($context, tickets::RESPOND, 'u.id,u.deleted,u.suspended,u.confirmed,' . $names) as $user) {
            if (!$user->deleted && !$user->suspended && $user->confirmed && !isguestuser($user->id)) {
                $agents[$user->id] = fullname($user);
            }
        }
        if ($ticket->assignedto && !isset($agents[$ticket->assignedto])) {
            $agents[$ticket->assignedto] = $label('ticketinactiveagent');
        }
    }
    $form = new ticket_update($url, ['states' => $states, 'manage' => $manage, 'priorities' => $priorities, 'agents' => $agents]);
    $form->set_data([
        'id' => $id, 'status' => $ticket->status, 'priority' => $ticket->priority, 'assignedto' => $ticket->assignedto ?: 0,
    ]);
    if ($data = $form->get_data()) {
        $service->update($id, $data);
        redirect($url);
    }
}
$files = [];
foreach (get_file_storage()->get_area_files($context->id, 'local_moodlexhub', tickets::FILEAREA, $id, 'filename', false) as $file) {
    $files[] = [
        'name' => $file->get_filename(),
        'url' => moodle_url::make_pluginfile_url(
            $context->id,
            'local_moodlexhub',
            tickets::FILEAREA,
            $id,
            $file->get_filepath(),
            $file->get_filename(),
            true,
        )->out(false),
    ];
}
$page = max(0, min(intdiv(PHP_INT_MAX, 20), optional_param('page', 0, PARAM_INT)));
$conditions = ['ticketid' => $id];
$posts = [];
foreach ($DB->get_records(tickets::POSTS, $conditions, 'timecreated DESC, id DESC', '*', $page * 20, 20) as $post) {
    $posts[] = [
        'date' => userdate($post->timecreated),
        'body' => $post->body,
        'author' => (int) $post->userid === (int) $ticket->userid ? $label('ticketrequester') : $label('ticketstaff'),
        'statuschange' => $post->statusold !== $post->statusnew
            ? $label('ticketstatus_' . $post->statusold) . ' → ' . $label('ticketstatus_' . $post->statusnew) : '',
        'prioritychange' => $post->priorityold !== $post->prioritynew
            ? $label('ticketpriority_' . $post->priorityold) . ' → ' . $label('ticketpriority_' . $post->prioritynew) : '',
        'assignmentchanged' => (bool) $post->assignmentchanged,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/ticket', [
    'listurl' => $listurl->out(false),
    'subject' => $ticket->subject,
    'body' => $ticket->body,
    'date' => userdate($ticket->timecreated),
    'status' => $label('ticketstatus_' . $ticket->status),
    'priority' => $label('ticketpriority_' . $ticket->priority),
    'files' => $files,
    'hasfiles' => (bool) $files,
    'posts' => $posts,
    'hasposts' => (bool) $posts,
]);
echo $OUTPUT->paging_bar($DB->count_records(tickets::POSTS, $conditions), $page, 20, $url);
if ($form) {
    $form->display();
}
echo $OUTPUT->footer();
