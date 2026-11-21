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

namespace local_moodlexhub\support;

use context_system;
use core\lock\lock_config;
use core\task\manager;
use local_moodlexhub\task\ticket_notice;
use stdClass;

/**
 * Ticket permissions, state transitions, comments and attachments.
 */
final class tickets {
    /** @var string XMLDB table for this module's records. */
    public const TABLE = 'local_moodlexhub_ticket';
    /** @var string Table of ticket replies and change records. */
    public const POSTS = 'local_moodlexhub_tk_post';
    /** @var string Capability to use the module's authenticated tools. */
    public const USE = 'local/moodlexhub:usesupport';
    /** @var string Capability to administer this module. */
    public const MANAGE = 'local/moodlexhub:managetickets';
    /** @var string Capability to respond to assigned tickets. */
    public const RESPOND = 'local/moodlexhub:respondtickets';
    /** @var string Private File API area for ticket attachments. */
    public const FILEAREA = 'ticket_attachment';
    /** @var string[] Supported support priorities. */
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    /** @var string[] Supported ticket states. */
    public const STATUSES = ['new', 'assigned', 'inprogress', 'waiting', 'resolved', 'closed'];

    /** @var array Allowed next states indexed by current state. */
    private const TRANSITIONS = [
        'new' => ['assigned'],
        'assigned' => ['inprogress', 'resolved'],
        'inprogress' => ['waiting', 'resolved'],
        'waiting' => ['inprogress', 'resolved'],
        'resolved' => ['closed', 'inprogress'],
        'closed' => ['inprogress'],
    ];

    /**
     * Check whether the actor can open a ticket.
     *
     * @param stdClass $ticket Ticket record.
     * @param int $userid Actor identifier.
     * @return bool
     */
    public function can_access(stdClass $ticket, int $userid): bool {
        if (!\local_moodlexhub\module_config::enabled('support')) {
            return false;
        }
        $context = context_system::instance();
        return has_capability(self::MANAGE, $context, $userid)
            || ((int) $ticket->userid === $userid && has_capability(self::USE, $context, $userid))
            || ((int) $ticket->assignedto === $userid && has_capability(self::RESPOND, $context, $userid));
    }

    /**
     * Count tickets per supported state for an authorized manager.
     *
     * @return array<string, int>
     */
    public function status_counts(): array {
        global $DB;
        \local_moodlexhub\module_config::require_enabled('support');
        require_capability(self::MANAGE, context_system::instance());
        $counts = $DB->get_records_sql_menu('SELECT status, COUNT(id) FROM {' . self::TABLE . '} GROUP BY status');
        return array_replace(array_fill_keys(self::STATUSES, 0), array_map('intval', $counts));
    }

    /**
     * Check whether an unresolved ticket was last modified before the given cutoff.
     *
     * @param int $cutoff Unix timestamp.
     * @return bool
     */
    public function has_pending_before(int $cutoff): bool {
        global $DB;
        \local_moodlexhub\module_config::require_enabled('support');
        require_capability(self::MANAGE, context_system::instance());
        return $DB->record_exists_select(
            self::TABLE,
            'status <> :closed AND status <> :resolved AND timemodified < :cutoff',
            ['closed' => 'closed', 'resolved' => 'resolved', 'cutoff' => $cutoff],
        );
    }

    /**
     * Return allowed states, scoped to the current actor.
     *
     * @param stdClass $ticket Current ticket.
     * @param int $userid Actor identifier.
     * @return string[]
     */
    public function available_states(stdClass $ticket, int $userid): array {
        if (!$this->can_access($ticket, $userid)) {
            return [];
        }
        $context = context_system::instance();
        $manage = has_capability(self::MANAGE, $context, $userid);
        $staff = $manage || ((int) $ticket->assignedto === $userid && has_capability(self::RESPOND, $context, $userid));
        $allowed = $staff ? self::TRANSITIONS[$ticket->status] : match ($ticket->status) {
            'waiting' => ['inprogress'],
            'resolved' => ['closed', 'inprogress'],
            default => [],
        };
        if (!$manage && $ticket->status === 'closed') {
            $allowed = [];
        }
        return [$ticket->status, ...$allowed];
    }

    /**
     * Create a ticket and optionally transfer its draft attachments to a private file area.
     *
     * @param stdClass $form Form data validated by Moodle.
     * @return int Ticket identifier.
     */
    public function create(stdClass $form): int {
        \local_moodlexhub\module_config::require_enabled('support');
        global $DB, $CFG, $USER;
        require_once($CFG->libdir . '/filelib.php');
        $context = context_system::instance();
        require_capability(self::USE, $context);
        if (!in_array($form->priority, self::PRIORITIES, true)) {
            throw new \invalid_parameter_exception();
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $id = $DB->insert_record(self::TABLE, (object) [
            'userid' => $USER->id,
            'subject' => $form->subject,
            'body' => $form->body,
            'priority' => $form->priority,
            'status' => 'new',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        if (!empty($form->attachments)) {
            file_save_draft_area_files(
                $form->attachments,
                $context->id,
                'local_moodlexhub',
                self::FILEAREA,
                $id,
                self::file_options()
            );
        }
        $transaction->allow_commit();
        return $id;
    }

    /**
     * Apply a comment or state transition to the latest ticket revision.
     *
     * @param int $id Ticket identifier.
     * @param stdClass $form Form request.
     */
    public function update(int $id, stdClass $form): void {
        global $DB, $USER;
        $lock = lock_config::get_lock_factory('local_moodlexhub_support')->get_lock('ticket_' . $id, 5);
        if (!$lock) {
            throw new \moodle_exception('ticketbusy', 'local_moodlexhub');
        }
        try {
            $record = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
            $actor = (int) $USER->id;
            $context = context_system::instance();
            if (!$this->can_access($record, $actor)) {
                throw new \moodle_exception('ticketunavailable', 'local_moodlexhub');
            }

            $status = $form->status ?? $record->status;
            if (!in_array($status, $this->available_states($record, $actor), true)) {
                throw new \moodle_exception('tickettransition', 'local_moodlexhub');
            }
            $canmanage = has_capability(self::MANAGE, $context);
            $assigned = $canmanage && isset($form->assignedto) ? (int) $form->assignedto : (int) $record->assignedto;
            $priority = $canmanage && isset($form->priority) ? $form->priority : $record->priority;
            if (!in_array($priority, self::PRIORITIES, true)) {
                throw new \invalid_parameter_exception();
            }
            if ($assigned && $assigned !== (int) $record->assignedto) {
                $user = \core_user::get_user($assigned, 'id,deleted,suspended,confirmed', IGNORE_MISSING);
                if (
                    !$user || $user->deleted || $user->suspended || !$user->confirmed
                    || !has_capability(self::RESPOND, $context, $assigned)
                ) {
                    throw new \moodle_exception('ticketassigneeunavailable', 'local_moodlexhub');
                }
            }
            if ($record->status === 'new' && $assigned) {
                $status = 'assigned';
            }
            if ($status === 'assigned' && !$assigned) {
                throw new \moodle_exception('ticketassignmentrequired', 'local_moodlexhub');
            }
            $body = trim($form->body ?? '');
            if ($body !== '' && $record->status === 'closed' && $status === 'closed') {
                throw new \moodle_exception('tickettransition', 'local_moodlexhub');
            }
            if (
                $body === '' && $status === $record->status && $priority === $record->priority
                && $assigned === (int) $record->assignedto
            ) {
                return;
            }

            $transaction = $DB->start_delegated_transaction();
            $now = time();
            $post = $DB->insert_record(self::POSTS, (object) [
                'ticketid' => $id,
                'userid' => $actor,
                'body' => $body,
                'statusold' => $record->status,
                'statusnew' => $status,
                'priorityold' => $record->priority,
                'prioritynew' => $priority,
                'assignmentchanged' => (int) ($assigned !== (int) $record->assignedto),
                'timecreated' => $now,
            ]);
            $DB->update_record(self::TABLE, (object) [
                'id' => $id,
                'assignedto' => $assigned ?: null,
                'priority' => $priority,
                'status' => $status,
                'timemodified' => $now,
            ]);
            $notice = new ticket_notice();
            $notice->set_attempts_available(3);
            $notice->set_custom_data(['ticketid' => $id, 'postid' => $post]);
            manager::queue_adhoc_task($notice, true);
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /**
     * Moodle file manager restrictions for private ticket attachments.
     *
     * @return array
     */
    public static function file_options(): array {
        global $CFG;
        $maxbytes = get_user_max_upload_file_size(context_system::instance(), $CFG->maxbytes, 0, 10 * 1024 * 1024);
        return ['subdirs' => 0, 'maxfiles' => 5, 'maxbytes' => $maxbytes, 'accepted_types' => ['document', 'image', '.txt']];
    }
}
