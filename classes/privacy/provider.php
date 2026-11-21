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

namespace local_moodlexhub\privacy;

use context_course;
use context_module;
use context_system;
use context_user;
use core_favourites\privacy\provider as favourites_privacy;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\writer;
use local_moodlexhub\ai\assistant;
use local_moodlexhub\enrol\imports;
use local_moodlexhub\enrol\privacy as import_privacy;
use local_moodlexhub\feedback\library;
use local_moodlexhub\output\dashboard;
use local_moodlexhub\quality\auditor;
use local_moodlexhub\student\planner;
use local_moodlexhub\support\privacy as ticket_privacy;

/**
 * Privacy declarations for MoodleX Hub.
 */
final class provider implements core_userlist_provider, metadata_provider, plugin_provider, user_preference_provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        followup_records::describe($collection);
        foreach (dashboard::PREFERENCES as $name) {
            $collection->add_user_preference('local_moodlexhub_' . $name, 'privacy:metadata:' . $name);
        }
        $collection->add_database_table(
            auditor::TABLE,
            [
                'courseid' => 'privacy:metadata:audit:courseid',
                'userid' => 'privacy:metadata:audit:userid',
                'score' => 'privacy:metadata:audit:score',
                'findings' => 'privacy:metadata:audit:findings',
                'timecreated' => 'privacy:metadata:audit:timecreated',
            ],
            'privacy:metadata:audit',
        );
        import_privacy::add_metadata($collection);
        $collection->add_database_table(planner::NOTES, [
            'userid' => 'privacy:metadata:notes:userid',
            'title' => 'privacy:metadata:notes:title',
            'body' => 'privacy:metadata:notes:body',
            'timecreated' => 'privacy:metadata:notes:time',
            'timemodified' => 'privacy:metadata:notes:time',
        ], 'privacy:metadata:notes');
        $collection->add_subsystem_link('core_favourites', [], 'privacy:metadata:favourites');
        ticket_privacy::add_metadata($collection);
        $collection->add_database_table(assistant::TABLE, [
            'userid' => 'privacy:metadata:aiusage:userid',
            'mode' => 'privacy:metadata:aiusage:mode',
            'characters' => 'privacy:metadata:aiusage:volume',
            'status' => 'privacy:metadata:aiusage:status',
            'inputtokens' => 'privacy:metadata:aiusage:volume',
            'outputtokens' => 'privacy:metadata:aiusage:volume',
            'timecreated' => 'privacy:metadata:aiusage:date',
        ], 'privacy:metadata:aiusage');
        $collection->add_subsystem_link('core_ai', [], 'privacy:metadata:aiusage:core');
        owned_records::add_metadata($collection);
        return $collection;
    }

    #[\Override]
    public static function export_user_preferences(int $userid): void {
        foreach (dashboard::PREFERENCES as $name) {
            $preference = get_user_preferences('local_moodlexhub_' . $name, null, $userid);
            if ($preference !== null) {
                writer::export_user_preference(
                    'local_moodlexhub',
                    'local_moodlexhub_' . $name,
                    (string) $preference,
                    get_string('privacy:metadata:' . $name, 'local_moodlexhub'),
                );
            }
        }
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contexts = new contextlist();
        followup_records::contexts($contexts, $userid);
        $contexts->add_from_sql(
            'SELECT DISTINCT ctx.id
               FROM {context} ctx
               JOIN {' . auditor::TABLE . '} a ON a.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :contextlevel AND a.userid = :userid',
            ['contextlevel' => CONTEXT_COURSE, 'userid' => $userid],
        );
        import_privacy::add_contexts($contexts, $userid);
        $contexts->add_from_sql(
            'SELECT DISTINCT ctx.id FROM {context} ctx JOIN {' . planner::NOTES . '} n ON n.userid = ctx.instanceid
              WHERE ctx.contextlevel = :level AND n.userid = :userid',
            ['level' => CONTEXT_USER, 'userid' => $userid],
        );
        favourites_privacy::add_contexts_for_userid($contexts, $userid, 'local_moodlexhub', planner::FAVOURITE);
        owned_records::add_contexts($contexts, $userid);
        ticket_privacy::add_contexts($contexts, $userid);
        $contexts->add_from_sql(
            'SELECT DISTINCT ctx.id FROM {context} ctx JOIN {' . assistant::TABLE . '} u ON u.userid = ctx.instanceid
              WHERE ctx.contextlevel = :level AND u.userid = :userid',
            ['level' => CONTEXT_USER, 'userid' => $userid],
        );
        return $contexts;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context instanceof context_system) {
            ticket_privacy::add_users($userlist);
        }
        if ($context instanceof context_user) {
            $identity = ['userid' => $context->instanceid];
            if (
                $DB->record_exists(planner::NOTES, $identity) || $DB->record_exists(assistant::TABLE, $identity)
                || owned_records::has_user_data($context->instanceid)
            ) {
                $userlist->add_user($context->instanceid);
            }
        }
        if ($context instanceof context_module) {
            favourites_privacy::add_userids_for_context($userlist, planner::FAVOURITE);
        }
        if ($context instanceof context_course) {
            followup_records::users($userlist, $context->instanceid);
            favourites_privacy::add_userids_for_context($userlist, library::FAVOURITE);
            $userlist->add_from_sql(
                'userid',
                'SELECT DISTINCT userid FROM {' . library::TABLE . '} WHERE courseid = :courseid',
                ['courseid' => $context->instanceid],
            );
            import_privacy::add_users($userlist, $context);
            $userlist->add_from_sql(
                'userid',
                'SELECT DISTINCT userid FROM {' . auditor::TABLE . '} WHERE courseid = :courseid AND userid IS NOT NULL',
                ['courseid' => $context->instanceid],
            );
        }
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            $userid = (int) $contextlist->get_user()->id;
            if ($context instanceof context_system) {
                ticket_privacy::export($context, $userid);
            }
            if ($context instanceof context_user && $context->instanceid == $userid) {
                owned_records::export_personal($context);
                $usage = $DB->get_recordset(assistant::TABLE, ['userid' => $userid], 'id ASC');
                try {
                    foreach ($usage as $entry) {
                        unset($entry->userid);
                        $entry->timecreated = transform::datetime($entry->timecreated);
                        writer::with_context($context)->export_data(
                            [get_string('assistanthistory', 'local_moodlexhub'), (string) $entry->id],
                            $entry,
                        );
                    }
                } finally {
                    $usage->close();
                }
                $notes = $DB->get_recordset(planner::NOTES, ['userid' => $userid], 'id ASC');
                try {
                    foreach ($notes as $note) {
                        writer::with_context($context)->export_data(
                            [get_string('studentnotes', 'local_moodlexhub'), (string) $note->id],
                            (object) [
                                'title' => $note->title,
                                'body' => $note->body,
                                'timecreated' => transform::datetime($note->timecreated),
                                'timemodified' => transform::datetime($note->timemodified),
                            ],
                        );
                    }
                } finally {
                    $notes->close();
                }
            }
            if ($context instanceof context_module) {
                $favourite = favourites_privacy::get_favourites_info_for_user(
                    $userid,
                    $context,
                    'local_moodlexhub',
                    planner::FAVOURITE,
                    $context->instanceid,
                );
                if ($favourite) {
                    writer::with_context($context)->export_data(
                        [get_string('studentfilter_favourites', 'local_moodlexhub')],
                        (object) $favourite,
                    );
                }
            }
            if (!$context instanceof context_course) {
                continue;
            }
            import_privacy::export($context, (int) $contextlist->get_user()->id);
            owned_records::export_feedback($context, $userid);
            followup_records::export($context, $userid);
            $records = $DB->get_recordset(auditor::TABLE, [
                'courseid' => $context->instanceid,
                'userid' => $contextlist->get_user()->id,
            ], 'timecreated ASC, id ASC');
            $path = get_string('quality', 'local_moodlexhub');
            try {
                foreach ($records as $record) {
                    writer::with_context($context)->export_data([$path, (string) $record->id], (object) [
                        'score' => $record->score,
                        'findings' => json_decode($record->findings, true, flags: JSON_THROW_ON_ERROR),
                        'timecreated' => transform::datetime($record->timecreated),
                    ]);
                }
            } finally {
                $records->close();
            }
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof context_system) {
            ticket_privacy::delete_matching('1 = 1', []);
        }
        if ($context instanceof context_user) {
            owned_records::delete_personal($context->instanceid);
            $DB->delete_records(planner::NOTES, ['userid' => $context->instanceid]);
            $DB->delete_records(assistant::TABLE, ['userid' => $context->instanceid]);
        }
        if ($context instanceof context_module) {
            favourites_privacy::delete_favourites_for_all_users($context, 'local_moodlexhub', planner::FAVOURITE);
        }
        if ($context instanceof context_course) {
            favourites_privacy::delete_favourites_for_all_users($context, 'local_moodlexhub', library::FAVOURITE);
            owned_records::delete_course($context->instanceid);
            followup_records::delete('courseid = :courseid', ['courseid' => $context->instanceid]);
            $DB->delete_records(auditor::TABLE, ['courseid' => $context->instanceid]);
            imports::delete_course_data($context->instanceid);
        }
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if ($contextlist->get_contextids()) {
            favourites_privacy::delete_favourites_for_user($contextlist, 'local_moodlexhub', planner::FAVOURITE);
            favourites_privacy::delete_favourites_for_user($contextlist, 'local_moodlexhub', library::FAVOURITE);
        }
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                ticket_privacy::delete_users([(int) $contextlist->get_user()->id]);
            }
            if ($context instanceof context_user && $context->instanceid == $contextlist->get_user()->id) {
                owned_records::delete_personal($context->instanceid);
                $DB->delete_records(planner::NOTES, ['userid' => $context->instanceid]);
                $DB->delete_records(assistant::TABLE, ['userid' => $context->instanceid]);
            }
            if ($context instanceof context_course) {
                followup_records::delete_users($context->instanceid, [(int) $contextlist->get_user()->id]);
                owned_records::delete_feedback(['courseid' => $context->instanceid, 'userid' => $contextlist->get_user()->id]);
                import_privacy::anonymise($context, [(int) $contextlist->get_user()->id]);
                $DB->set_field(auditor::TABLE, 'userid', null, [
                    'courseid' => $context->instanceid,
                    'userid' => $contextlist->get_user()->id,
                ]);
            }
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context instanceof context_system) {
            ticket_privacy::delete_users($userlist->get_userids());
        }
        if ($context instanceof context_user && in_array($context->instanceid, $userlist->get_userids())) {
            owned_records::delete_personal($context->instanceid);
            $DB->delete_records(planner::NOTES, ['userid' => $context->instanceid]);
            $DB->delete_records(assistant::TABLE, ['userid' => $context->instanceid]);
        }
        if ($context instanceof context_module) {
            favourites_privacy::delete_favourites_for_userlist($userlist, planner::FAVOURITE);
        }
        if ($context instanceof context_course && $userlist->get_userids()) {
            followup_records::delete_users($context->instanceid, $userlist->get_userids());
            favourites_privacy::delete_favourites_for_userlist($userlist, library::FAVOURITE);
            foreach ($userlist->get_userids() as $userid) {
                owned_records::delete_feedback(['courseid' => $context->instanceid, 'userid' => $userid]);
            }
            import_privacy::anonymise($context, $userlist->get_userids());
            foreach (array_chunk($userlist->get_userids(), 500) as $batch) {
                [$insql, $params] = $DB->get_in_or_equal($batch, SQL_PARAMS_NAMED);
                $DB->set_field_select(
                    auditor::TABLE,
                    'userid',
                    null,
                    'courseid = :courseid AND userid ' . $insql,
                    $params + ['courseid' => $context->instanceid],
                );
            }
        }
    }
}
