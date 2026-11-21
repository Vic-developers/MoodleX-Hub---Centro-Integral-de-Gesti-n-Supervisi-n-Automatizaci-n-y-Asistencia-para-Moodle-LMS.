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

namespace local_moodlexhub\task;

use core\message\message;
use core_user;
use local_moodlexhub\support\tickets;
use moodle_url;

/**
 * Send a minimal Moodle notification to the currently authorised ticket participants.
 */
final class ticket_notice extends \core\task\adhoc_task {
    #[\Override]
    public function get_name(): string {
        return get_string('taskticketnotice', 'local_moodlexhub');
    }

    #[\Override]
    public function execute(): void {
        global $DB;
        $data = $this->get_custom_data();
        $ticket = $DB->get_record(tickets::TABLE, ['id' => $data->ticketid]);
        $post = $DB->get_record(tickets::POSTS, ['id' => $data->postid, 'ticketid' => $data->ticketid]);
        if (!$ticket || !$post) {
            return;
        }
        $url = new moodle_url('/local/moodlexhub/ticket.php', ['id' => $ticket->id]);
        $service = new tickets();
        foreach (array_unique([(int) $ticket->userid, (int) $ticket->assignedto]) as $userid) {
            if (!$userid || $userid === (int) $post->userid || !$service->can_access($ticket, $userid)) {
                continue;
            }
            $user = core_user::get_user($userid, '*', IGNORE_MISSING);
            if (!$user || $user->deleted || $user->suspended) {
                continue;
            }
            $message = new message();
            $message->component = 'local_moodlexhub';
            $message->name = 'ticketupdate';
            $message->userfrom = core_user::get_noreply_user();
            $message->userto = $user;
            $message->subject = get_string_manager()->get_string('ticketnotificationsubject', 'local_moodlexhub', null, $user->lang);
            $message->smallmessage = $message->subject;
            $message->fullmessage = $message->subject;
            $message->fullmessagehtml = '';
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string_manager()->get_string('ticketopen', 'local_moodlexhub', null, $user->lang);
            if (!message_send($message)) {
                throw new \moodle_exception('ticketnotificationfailed', 'local_moodlexhub');
            }
        }
    }
}
