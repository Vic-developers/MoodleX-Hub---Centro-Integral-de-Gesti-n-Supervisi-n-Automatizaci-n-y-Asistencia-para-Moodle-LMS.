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
use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_moodlexhub\academic\followup;

/**
 * Privacy discovery, export and removal of academic follow-up data.
 */
final class followup_records {
    /**
     * Describe case and intervention data.
     *
     * @param collection $collection Moodle privacy metadata.
     */
    public static function describe(collection $collection): void {
        $collection->add_database_table(followup::CASES, array_fill_keys(
            ['courseid', 'userid', 'cmid', 'reason', 'status', 'assignee', 'occurrence', 'reviewdue', 'reminded',
                'timecreated', 'timemodified'],
            'privacy:metadata:saved:content',
        ), 'followup');
        $collection->add_database_table(followup::HISTORY, array_fill_keys(
            ['caseid', 'actorid', 'action', 'note', 'timecreated'],
            'privacy:metadata:saved:content',
        ), 'followup');
    }

    /**
     * Find courses where the user is a learner, assignee or intervention author.
     *
     * @param contextlist $list Moodle discovery result.
     * @param int $userid Subject of the privacy request.
     */
    public static function contexts(contextlist $list, int $userid): void {
        $list->add_from_sql(
            'SELECT ctx.id FROM {context} ctx WHERE ctx.contextlevel = :level AND EXISTS (
                SELECT 1 FROM {' . followup::CASES . '} c WHERE c.courseid = ctx.instanceid
                AND (c.userid = :learner OR c.assignee = :assignee OR EXISTS (
                    SELECT 1 FROM {' . followup::HISTORY . '} h WHERE h.caseid = c.id AND h.actorid = :actor
                ))
            )',
            ['level' => CONTEXT_COURSE, 'learner' => $userid, 'assignee' => $userid, 'actor' => $userid],
        );
    }

    /**
     * Add the participants in academic follow-up to a course privacy request.
     *
     * @param userlist $list Moodle course user list.
     * @param int $courseid Course ID.
     */
    public static function users(userlist $list, int $courseid): void {
        foreach (['userid', 'assignee'] as $field) {
            $list->add_from_sql(
                $field,
                'SELECT ' . $field . ' FROM {' . followup::CASES . '} WHERE courseid = :courseid AND ' . $field . ' IS NOT NULL',
                ['courseid' => $courseid],
            );
        }
        $list->add_from_sql(
            'actorid',
            'SELECT h.actorid FROM {' . followup::HISTORY . '} h JOIN {' . followup::CASES . '} c ON c.id = h.caseid
              WHERE c.courseid = :courseid AND h.actorid IS NOT NULL',
            ['courseid' => $courseid],
        );
    }

    /**
     * Export a learner's cases and intervention data, and staff contributions.
     *
     * @param context_course $context Approved course context.
     * @param int $userid Data subject.
     */
    public static function export(context_course $context, int $userid): void {
        global $DB;
        $exporter = writer::with_context($context);
        $label = get_string('followup', 'local_moodlexhub');
        $cases = $DB->get_recordset_select(
            followup::CASES,
            'courseid = :course AND (userid = :learner OR assignee = :assignee)',
            ['course' => $context->instanceid, 'learner' => $userid, 'assignee' => $userid],
        );
        try {
            foreach ($cases as $case) {
                $learner = (int) $case->userid === $userid;
                $data = ['modified' => transform::datetime($case->timemodified)];
                if ($case->reviewdue) {
                    $data['reviewdue'] = transform::datetime($case->reviewdue);
                }
                if ($learner) {
                    $data += [
                        'activity' => $case->cmid,
                        'reason' => $case->reason,
                        'status' => $case->status,
                        'created' => transform::datetime($case->timecreated),
                    ];
                }
                $exporter->export_data([$label, (string) $case->id], (object) $data);
            }
        } finally {
            $cases->close();
        }
        $history = $DB->get_recordset_sql(
            'SELECT h.id, h.caseid, h.action, h.note, h.timecreated
               FROM {' . followup::HISTORY . '} h JOIN {' . followup::CASES . '} c ON c.id = h.caseid
              WHERE c.courseid = :course AND (c.userid = :learner OR h.actorid = :actor)',
            ['course' => $context->instanceid, 'learner' => $userid, 'actor' => $userid],
        );
        try {
            foreach ($history as $entry) {
                $exporter->export_data([$label, (string) $entry->caseid, (string) $entry->id], (object) [
                    'action' => $entry->action,
                    'note' => $entry->note,
                    'created' => transform::datetime($entry->timecreated),
                ]);
            }
        } finally {
            $history->close();
        }
    }

    /**
     * Remove learner cases and erase staff authorship/assignment from other cases.
     *
     * @param int $courseid Approved course.
     * @param int[] $userids Approved users.
     */
    public static function delete_users(int $courseid, array $userids): void {
        global $DB;
        foreach (array_chunk($userids, 500) as $batch) {
            [$insql, $params] = $DB->get_in_or_equal($batch, SQL_PARAMS_NAMED);
            $params['courseid'] = $courseid;
            self::delete('courseid = :courseid AND userid ' . $insql, $params);
            $DB->set_field_select(followup::CASES, 'assignee', null, 'courseid = :courseid AND assignee ' . $insql, $params);
            $filter = 'caseid IN (SELECT id FROM {' . followup::CASES . '} WHERE courseid = :courseid) AND actorid ' . $insql;
            $transaction = $DB->start_delegated_transaction();
            $DB->set_field_select(followup::HISTORY, 'note', null, $filter, $params);
            $DB->set_field_select(followup::HISTORY, 'actorid', null, $filter, $params);
            $transaction->allow_commit();
        }
    }

    /**
     * Remove all records for a context being erased or an activity being deleted.
     *
     * @param string $select Internal case filter.
     * @param array $params Bound parameters.
     */
    public static function delete(string $select, array $params): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records_select(followup::HISTORY, 'caseid IN (SELECT id FROM {' . followup::CASES . '} WHERE ' . $select . ')', $params);
        $DB->delete_records_select(followup::CASES, $select, $params);
        $transaction->allow_commit();
    }
}
