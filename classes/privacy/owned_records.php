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
use context_user;
use core_favourites\privacy\provider as favourites_privacy;
use core_favourites\service_factory;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use local_moodlexhub\automation\engine;
use local_moodlexhub\feedback\library;
use local_moodlexhub\quality\bulk;
use local_moodlexhub\report\builder;

/**
 * Privacy and cascades for feedback, saved reports and automation rules.
 */
final class owned_records {
    /** @var array Owned parent tables with their history table, foreign key and scope. */
    private const USER_TABLES = [
        builder::REPORTS => ['reports', builder::RUNS, 'reportid', true],
        engine::RULES => ['automation', engine::RUNS, 'ruleid', true],
        bulk::JOBS => ['qualitybulk', bulk::ROWS, 'jobid', false],
    ];

    /**
     * Register personal metadata for saved records and execution history.
     *
     * @param collection $collection Privacy metadata.
     */
    public static function add_metadata(collection $collection): void {
        foreach (
            [
            library::TABLE => ['courseid', 'userid', 'title', 'category', 'body', 'type', 'cmid', 'shared', 'timecreated', 'timemodified'],
            builder::REPORTS => ['userid', 'courseid', 'title', 'source', 'filtervalue', 'intervalvalue', 'nextdue', 'timemodified'],
            builder::RUNS => ['reportid', 'timeslot', 'status', 'payload', 'timecreated', 'timemodified'],
            engine::RULES => ['userid', 'courseid', 'recipient', 'reportid', 'title', 'description', 'conditiontype', 'actiontype',
                'threshold', 'enabled', 'intervalvalue', 'nextdue', 'timemodified'],
            engine::RUNS => ['ruleid', 'timeslot', 'status', 'resultid', 'timecreated', 'timemodified'],
            bulk::JOBS => ['userid', 'categoryid', 'status', 'timecreated', 'timemodified'],
            bulk::ROWS => ['jobid', 'courseid', 'auditid', 'status', 'attempts'],
            ] as $table => $fields
        ) {
            $metadata = [];
            foreach ($fields as $field) {
                $metadata[$field] = match ($field) {
                    'userid' => 'privacy:metadata:saved:owner',
                    'recipient' => 'privacy:metadata:saved:recipient',
                    'timecreated', 'timemodified', 'nextdue', 'timeslot' => 'privacy:metadata:saved:time',
                    default => 'privacy:metadata:saved:content',
                };
            }
            $collection->add_database_table($table, $metadata, 'privacy:metadata:saved');
        }
    }

    /**
     * Add the user's saved-rule and template contexts.
     *
     * @param contextlist $contexts Privacy contexts.
     * @param int $userid Target user.
     */
    public static function add_contexts(contextlist $contexts, int $userid): void {
        if (self::has_user_data($userid)) {
            $contexts->add_user_context($userid);
        }
        $contexts->add_from_sql(
            'SELECT DISTINCT ctx.id FROM {context} ctx JOIN {' . library::TABLE . '} t ON t.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :level AND t.userid = :userid',
            ['level' => CONTEXT_COURSE, 'userid' => $userid],
        );
        favourites_privacy::add_contexts_for_userid($contexts, $userid, 'local_moodlexhub', library::FAVOURITE);
    }

    /**
     * Check for reports or rules associated with an individual context.
     *
     * @param int $userid Target user.
     * @return bool
     */
    public static function has_user_data(int $userid): bool {
        global $DB;
        return $DB->record_exists(builder::REPORTS, ['userid' => $userid]) || $DB->record_exists(bulk::JOBS, ['userid' => $userid])
            || $DB->record_exists_select(
                engine::RULES,
                'userid = :owner OR recipient = :recipient',
                ['owner' => $userid, 'recipient' => $userid]
            );
    }

    /**
     * Export saved records and their operation history in the owner's personal context.
     *
     * @param context_user $context Authorized personal context.
     */
    public static function export_personal(context_user $context): void {
        global $DB;
        $userid = $context->instanceid;
        $exporter = writer::with_context($context);
        foreach (self::USER_TABLES as $table => [$language, $runs, $parent]) {
            $label = get_string($language, 'local_moodlexhub');
            $records = $DB->get_records($table, ['userid' => $userid], 'id ASC');
            foreach ($records as $record) {
                unset($record->userid, $record->recipient);
                $exporter->export_data([$label, (string) $record->id], $record);
            }
            $results = $DB->get_recordset_sql(
                'SELECT r.* FROM {' . $runs . '} r JOIN {' . $table . '} p ON p.id = r.' . $parent
                    . ' WHERE p.userid = :userid ORDER BY r.id ASC',
                ['userid' => $userid],
            );
            try {
                foreach ($results as $run) {
                    if (isset($run->timecreated, $run->timemodified)) {
                        $run->timecreated = transform::datetime($run->timecreated);
                        $run->timemodified = transform::datetime($run->timemodified);
                    }
                    $exporter->export_data([$label, (string) $run->$parent, get_string('monitorhistory', 'local_moodlexhub'),
                        (string) $run->id], $run);
                }
            } finally {
                $results->close();
            }
        }
        $recipients = $DB->get_records(engine::RULES, ['recipient' => $userid], 'id ASC', 'id,timemodified');
        foreach ($recipients as $rule) {
            $exporter->export_data(
                [get_string('automation', 'local_moodlexhub'),
                get_string('automationrecipientfield', 'local_moodlexhub'), (string) $rule->id],
                (object) ['lastupdated' => transform::datetime($rule->timemodified)]
            );
        }
    }

    /**
     * Export feedback authored in a course and bookmarks stored by the user.
     *
     * @param context_course $context Approved course context.
     * @param int $userid Target user.
     */
    public static function export_feedback(context_course $context, int $userid): void {
        global $DB;
        $exporter = writer::with_context($context);
        $label = get_string('feedback', 'local_moodlexhub');
        $records = $DB->get_recordset(library::TABLE, ['courseid' => $context->instanceid, 'userid' => $userid], 'id ASC');
        try {
            foreach ($records as $record) {
                unset($record->userid);
                $exporter->export_data([$label, (string) $record->id], $record);
            }
        } finally {
            $records->close();
        }
        $service = service_factory::get_service_for_user_context(context_user::instance($userid));
        $favourites = [];
        foreach ($service->find_favourites_by_type('local_moodlexhub', library::FAVOURITE) as $favourite) {
            if ($favourite->contextid == $context->id) {
                $favourites[] = $favourite->itemid;
            }
        }
        if ($favourites) {
            $exporter->export_data([$label, get_string('feedbacktype_favourites', 'local_moodlexhub')], (object) ['items' => $favourites]);
        }
    }

    /**
     * Delete owned personal data and clear references where the user was a notification recipient.
     *
     * @param int $userid Personal context owner.
     */
    public static function delete_personal(int $userid): void {
        global $DB;
        self::delete_parents('userid = :userid', ['userid' => $userid]);
        $DB->set_field(engine::RULES, 'recipient', null, ['recipient' => $userid]);
    }

    /**
     * Delete data that belongs to a course being removed.
     *
     * @param int $courseid Deleted course.
     */
    public static function delete_course(int $courseid): void {
        global $DB;
        self::delete_parents('courseid = :courseid', ['courseid' => $courseid], true);
        $DB->delete_records(bulk::ROWS, ['courseid' => $courseid]);
        self::delete_feedback(['courseid' => $courseid]);
    }

    /**
     * Delete feedback and any matching bookmarks using the Favourites API.
     *
     * @param array $conditions Moodle DML conditions.
     */
    public static function delete_feedback(array $conditions): void {
        global $DB;
        $service = service_factory::get_service_for_component('local_moodlexhub');
        do {
            $items = $DB->get_records(library::TABLE, $conditions, 'id ASC', 'id', 0, 100);
            foreach ($items as $record) {
                $service->delete_favourites_by_type_and_item(library::FAVOURITE, $record->id);
                $DB->delete_records(library::TABLE, ['id' => $record->id]);
            }
        } while (count($items) === 100);
    }

    /**
     * Delete saved reports and rules, including associated runs.
     *
     * @param string $filter Fixed internal DML condition.
     * @param array $params Bind values.
     * @param bool $courseonly Only parent tables with a course scope.
     */
    private static function delete_parents(string $filter, array $params, bool $courseonly = false): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        foreach (self::USER_TABLES as $table => [$label, $runs, $field, $hascourse]) {
            if ($courseonly && !$hascourse) {
                continue;
            }
            $DB->delete_records_select($runs, $field . ' IN (SELECT id FROM {' . $table . '} WHERE ' . $filter . ')', $params);
            $DB->delete_records_select($table, $filter, $params);
        }
        $transaction->allow_commit();
    }
}
