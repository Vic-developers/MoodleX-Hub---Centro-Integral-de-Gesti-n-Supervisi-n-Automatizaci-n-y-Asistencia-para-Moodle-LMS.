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

use core\lock\lock_config;
use core\message\message;
use core_user;
use local_moodlexhub\academic\followup;
use local_moodlexhub\module_config;
use moodle_url;

/**
 * Remind still-authorized staff about assigned cases with a review date.
 */
final class followup_reminders extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('followupreminders', 'local_moodlexhub');
    }

    #[\Override]
    public function execute(): void {
        global $DB;
        $days = min(7, (int) get_config('local_moodlexhub', 'followupreminderdays'));
        if ($days <= 0 || !module_config::enabled('teacher')) {
            return;
        }
        $until = time() + $days * DAYSECS;
        $select = 'status = :open AND assignee IS NOT NULL AND reviewdue > 0 AND reviewdue <= :until AND reminded = 0';
        $params = ['open' => 'open', 'until' => $until];
        $cases = $DB->get_records_select(followup::CASES, $select, $params, 'reviewdue ASC, id ASC', 'id', 0, 200);
        $service = new followup();
        $factory = lock_config::get_lock_factory('local_moodlexhub_followup');
        foreach ($cases as $candidate) {
            $lock = $factory->get_lock('case:' . $candidate->id, 0);
            if (!$lock) {
                continue;
            }
            try {
                $case = $DB->get_record_select(followup::CASES, 'id = :id AND ' . $select, ['id' => $candidate->id] + $params);
                if (!$case) {
                    continue;
                }
                $course = $DB->get_record('course', ['id' => $case->courseid]);
                if (!$course || !$service->can_handle($course, $case, $case->assignee)) {
                    $DB->set_field(followup::CASES, 'reminded', -1, ['id' => $case->id]);
                    continue;
                }
                $staff = core_user::get_user($case->assignee, '*', MUST_EXIST);
                $name = get_string_manager()->get_string('followupreminderbody', 'local_moodlexhub', null, $staff->lang);
                $url = new moodle_url('/local/moodlexhub/followup.php', ['id' => $course->id, 'view' => 'mine']);
                $message = new message();
                $message->component = 'local_moodlexhub';
                $message->name = 'followupreminder';
                $message->userfrom = core_user::get_noreply_user();
                $message->userto = $staff;
                $message->subject = $name;
                $message->fullmessage = $name;
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = '';
                $message->smallmessage = $name;
                $message->notification = 1;
                $message->contexturl = $url->out(false);
                $message->contexturlname = $name;
                if (message_send($message)) {
                    $DB->set_field(followup::CASES, 'reminded', time(), ['id' => $case->id]);
                }
            } finally {
                $lock->release();
            }
        }
    }
}
