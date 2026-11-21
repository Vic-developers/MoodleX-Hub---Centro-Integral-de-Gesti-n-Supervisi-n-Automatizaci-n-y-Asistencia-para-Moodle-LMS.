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
use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy export, deletion and retention for support tickets.
 */
final class privacy {
    /**
     * Add support table and file-area metadata.
     *
     * @param collection $collection Moodle privacy metadata.
     */
    public static function add_metadata(collection $collection): void {
        $collection->add_database_table(tickets::TABLE, [
            'userid' => 'privacy:metadata:ticket:actor',
            'assignedto' => 'privacy:metadata:ticket:agent',
            'subject' => 'privacy:metadata:ticket:content',
            'body' => 'privacy:metadata:ticket:content',
            'priority' => 'privacy:metadata:ticket:state',
            'status' => 'privacy:metadata:ticket:state',
            'timecreated' => 'privacy:metadata:ticket:date',
            'timemodified' => 'privacy:metadata:ticket:date',
        ], 'privacy:metadata:ticket');
        $collection->add_database_table(tickets::POSTS, [
            'ticketid' => 'privacy:metadata:ticket',
            'userid' => 'privacy:metadata:ticket:actor',
            'body' => 'privacy:metadata:ticket:content',
            'statusold' => 'privacy:metadata:ticket:state',
            'statusnew' => 'privacy:metadata:ticket:state',
            'priorityold' => 'privacy:metadata:ticket:state',
            'prioritynew' => 'privacy:metadata:ticket:state',
            'assignmentchanged' => 'privacy:metadata:ticket:state',
            'timecreated' => 'privacy:metadata:ticket:date',
        ], 'privacy:metadata:ticket:posts');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:ticket:files');
    }

    /**
     * Add the site context when a user participates in tickets.
     *
     * @param contextlist $contexts Moodle list of contexts.
     * @param int $userid Target user.
     */
    public static function add_contexts(contextlist $contexts, int $userid): void {
        global $DB;
        if (
            $DB->record_exists_select(tickets::TABLE, 'userid = :owner OR assignedto = :agent', ['owner' => $userid, 'agent' => $userid])
            || $DB->record_exists(tickets::POSTS, ['userid' => $userid])
        ) {
            $contexts->add_system_context();
        }
    }

    /**
     * Add people identified in tickets and posts.
     *
     * @param userlist $list Site user list.
     */
    public static function add_users(userlist $list): void {
        $list->add_from_sql('userid', 'SELECT DISTINCT userid FROM {' . tickets::TABLE . '} WHERE userid IS NOT NULL', []);
        $list->add_from_sql('assignedto', 'SELECT DISTINCT assignedto FROM {' . tickets::TABLE . '} WHERE assignedto IS NOT NULL', []);
        $list->add_from_sql('userid', 'SELECT DISTINCT userid FROM {' . tickets::POSTS . '} WHERE userid IS NOT NULL', []);
    }

    /**
     * Export content authored by the user, related attachments and assigned ticket identifiers.
     *
     * @param context_system $context Site context.
     * @param int $userid Target user.
     */
    public static function export(context_system $context, int $userid): void {
        global $DB;

        $writer = writer::with_context($context);
        $base = get_string('support', 'local_moodlexhub');
        $records = $DB->get_recordset_select(
            tickets::TABLE,
            'userid = :owner OR assignedto = :agent',
            ['owner' => $userid, 'agent' => $userid],
            'id ASC'
        );
        try {
            foreach ($records as $ticket) {
                $owned = (int) $ticket->userid === $userid;
                $path = [$base, (string) $ticket->id];
                $data = (object) [
                    'requester' => $owned,
                    'assignee' => (int) $ticket->assignedto === $userid,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'timecreated' => transform::datetime($ticket->timecreated),
                ];
                if ($owned) {
                    $data->subject = $ticket->subject;
                    $data->body = $ticket->body;
                    $writer->export_area_files($path, 'local_moodlexhub', tickets::FILEAREA, $ticket->id);
                }
                $writer->export_data($path, $data);
            }
        } finally {
            $records->close();
        }

        $posts = $DB->get_recordset(tickets::POSTS, ['userid' => $userid], 'id ASC');
        try {
            foreach ($posts as $post) {
                $writer->export_data(
                    [$base, (string) $post->ticketid, get_string('tickethistory', 'local_moodlexhub'), (string) $post->id],
                    (object) [
                        'body' => $post->body,
                        'status' => $post->statusnew,
                        'priority' => $post->prioritynew,
                        'timecreated' => transform::datetime($post->timecreated),
                    ],
                );
            }
        } finally {
            $posts->close();
        }
    }

    /**
     * Delete a user's tickets and anonymise their assignment and other posts.
     *
     * @param int[] $userids User IDs included in the approved request.
     */
    public static function delete_users(array $userids): void {
        global $DB;
        foreach (array_chunk($userids, 250) as $batch) {
            [$in, $params] = $DB->get_in_or_equal($batch, SQL_PARAMS_NAMED, 'userid');
            self::delete_matching('userid ' . $in, $params);
            $DB->set_field_select(tickets::TABLE, 'assignedto', null, 'assignedto ' . $in, $params);
            $DB->set_field_select(tickets::POSTS, 'body', null, 'userid ' . $in, $params);
            $DB->set_field_select(tickets::POSTS, 'userid', null, 'userid ' . $in, $params);
        }
    }

    /**
     * Delete matching tickets and associated data in bounded batches.
     *
     * @param string $select Internal DML condition.
     * @param array $params Query parameters.
     */
    public static function delete_matching(string $select, array $params): void {
        global $DB;
        $contextid = context_system::instance()->id;
        $fs = get_file_storage();
        do {
            $ids = array_keys($DB->get_records_select(tickets::TABLE, $select, $params, 'id ASC', 'id', 0, 100));
            foreach ($ids as $id) {
                $lock = lock_config::get_lock_factory('local_moodlexhub_support')->get_lock('ticket_' . $id, 5);
                if (!$lock) {
                    throw new \moodle_exception('ticketbusy', 'local_moodlexhub');
                }
                try {
                    if (
                        $DB->record_exists_select(
                            tickets::TABLE,
                            '(' . $select . ') AND id = :deleteticketid',
                            $params + ['deleteticketid' => $id]
                        )
                    ) {
                        $transaction = $DB->start_delegated_transaction();
                        $fs->delete_area_files($contextid, 'local_moodlexhub', tickets::FILEAREA, $id);
                        $DB->delete_records(tickets::POSTS, ['ticketid' => $id]);
                        $DB->delete_records(tickets::TABLE, ['id' => $id]);
                        $transaction->allow_commit();
                    }
                } finally {
                    $lock->release();
                }
            }
        } while (count($ids) === 100);
    }
}
