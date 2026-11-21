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

namespace local_moodlexhub\enrol;

use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy operations for MoodleX Hub enrolment imports.
 */
final class privacy {
    /**
     * Describe the data stored for imports.
     *
     * @param collection $collection Moodle privacy metadata collection.
     */
    public static function add_metadata(collection $collection): void {
        $collection->add_database_table(imports::JOBS, [
            'courseid' => 'privacy:metadata:audit:courseid',
            'userid' => 'privacy:metadata:import:actor',
            'instanceid' => 'privacy:metadata:import:method',
            'roleid' => 'privacy:metadata:import:role',
            'status' => 'privacy:metadata:import:status',
            'timecreated' => 'privacy:metadata:import:date',
            'timemodified' => 'privacy:metadata:import:date',
        ], 'privacy:metadata:import');
        $collection->add_database_table(imports::ROWS, [
            'importid' => 'privacy:metadata:import',
            'position' => 'privacy:metadata:import:position',
            'userid' => 'privacy:metadata:import:account',
            'status' => 'privacy:metadata:import:status',
            'reason' => 'privacy:metadata:import:status',
            'attempts' => 'privacy:metadata:import:attempts',
        ], 'privacy:metadata:import:rows');
    }

    /**
     * Locate contexts for import requests and resolved CSV rows.
     *
     * @param contextlist $contexts Moodle privacy contexts.
     * @param int $userid Target user.
     */
    public static function add_contexts(contextlist $contexts, int $userid): void {
        $params = ['contextlevel' => CONTEXT_COURSE, 'userid' => $userid];
        $contexts->add_from_sql(
            'SELECT DISTINCT ctx.id FROM {context} ctx
                JOIN {' . imports::JOBS . '} j ON j.courseid = ctx.instanceid
                WHERE ctx.contextlevel = :contextlevel AND j.userid = :userid',
            $params,
        );
        $contexts->add_from_sql(
            'SELECT DISTINCT ctx.id FROM {context} ctx
                JOIN {' . imports::JOBS . '} j ON j.courseid = ctx.instanceid
                JOIN {' . imports::ROWS . '} r ON r.importid = j.id
                WHERE ctx.contextlevel = :contextlevel AND r.userid = :userid',
            $params,
        );
    }

    /**
     * Find people identified in course imports.
     *
     * @param userlist $list Moodle privacy user list.
     * @param context_course $context Course context.
     */
    public static function add_users(userlist $list, context_course $context): void {
        $params = ['courseid' => $context->instanceid];
        $list->add_from_sql(
            'userid',
            'SELECT DISTINCT userid FROM {' . imports::JOBS . '} WHERE courseid = :courseid AND userid IS NOT NULL',
            $params,
        );
        $list->add_from_sql(
            'userid',
            'SELECT DISTINCT r.userid FROM {' . imports::ROWS . '} r
                JOIN {' . imports::JOBS . '} j ON j.id = r.importid
                WHERE j.courseid = :courseid AND r.userid IS NOT NULL',
            $params,
        );
    }

    /**
     * Export only records associated with this user.
     *
     * @param context_course $context Course context.
     * @param int $userid Target user.
     */
    public static function export(context_course $context, int $userid): void {
        global $DB;

        $base = get_string('enrolimports', 'local_moodlexhub');
        $courseid = $context->instanceid;
        $records = $DB->get_recordset(imports::JOBS, ['courseid' => $courseid, 'userid' => $userid], 'id ASC');
        try {
            foreach ($records as $record) {
                writer::with_context($context)->export_data([$base, (string) $record->id], (object) [
                    'method' => $record->instanceid,
                    'role' => $record->roleid,
                    'status' => $record->status,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ]);
            }
        } finally {
            $records->close();
        }

        $records = $DB->get_recordset_sql(
            'SELECT r.* FROM {' . imports::ROWS . '} r JOIN {' . imports::JOBS . '} j ON j.id = r.importid
              WHERE j.courseid = :courseid AND r.userid = :userid ORDER BY r.id ASC',
            ['courseid' => $courseid, 'userid' => $userid],
        );
        try {
            foreach ($records as $record) {
                writer::with_context($context)->export_data(
                    [$base, (string) $record->importid, get_string('importrows', 'local_moodlexhub'), (string) $record->position],
                    (object) ['status' => $record->status, 'reason' => $record->reason, 'attempts' => $record->attempts],
                );
            }
        } finally {
            $records->close();
        }
    }

    /**
     * De-identify approved users and block pending jobs they owned.
     *
     * @param context_course $context Course context.
     * @param int[] $userids Users listed in an approved privacy request.
     */
    public static function anonymise(context_course $context, array $userids): void {
        global $DB;

        foreach (array_chunk($userids, 500) as $batch) {
            [$insql, $params] = $DB->get_in_or_equal($batch, SQL_PARAMS_NAMED, 'user');
            $params['courseid'] = $context->instanceid;
            $jobselect = 'courseid = :courseid AND userid ' . $insql;
            $transaction = $DB->start_delegated_transaction();
            $DB->set_field_select(imports::JOBS, 'status', 'blocked', $jobselect
                . ' AND (status = :queued OR status = :running)', $params + ['queued' => 'queued', 'running' => 'running']);
            $DB->set_field_select(imports::JOBS, 'userid', null, $jobselect, $params);
            $DB->set_field_select(
                imports::ROWS,
                'userid',
                null,
                'userid ' . $insql . ' AND importid IN (SELECT id FROM {' . imports::JOBS . '} WHERE courseid = :courseid)',
                $params
            );
            $transaction->allow_commit();
        }
    }
}
