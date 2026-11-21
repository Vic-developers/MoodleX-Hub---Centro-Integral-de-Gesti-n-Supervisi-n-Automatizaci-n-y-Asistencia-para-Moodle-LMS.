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

namespace local_moodlexhub\academic;

use completion_info;
use context_course;
use core\event\base as event_base;
use core\log\sql_reader;
use stdClass;

/**
 * Permission-aware course completion, access and log measurements.
 */
final class course_metrics {
    /** @var string Capability to view course analytics. */
    public const ANALYTICS = 'local/moodlexhub:viewanalytics';
    /** @var string Capability to use the teacher panel. */
    public const TEACHER = 'local/moodlexhub:viewteacher';
    /** @var string Native Moodle capability identifying tracked learners. */
    private const STUDENT = 'moodle/course:isincompletionreports';

    /**
     * Authorize one of the academic views.
     *
     * @param stdClass $course Course being inspected.
     * @param string $capability Plugin capability for the requested page.
     * @return context_course
     */
    public function require_access(stdClass $course, string $capability): context_course {
        \local_moodlexhub\module_config::require_enabled($capability === self::TEACHER ? 'teacher' : 'analytics');
        if ($course->id == SITEID || !\core_course_category::can_view_course_info($course)) {
            throw new \moodle_exception('courseunavailable', 'local_moodlexhub');
        }
        $context = context_course::instance($course->id);
        require_all_capabilities([$capability, 'moodle/course:viewparticipants'], $context);
        return $context;
    }

    /**
     * Resolve the group filter using Moodle course group visibility.
     *
     * @param stdClass $course Course being inspected.
     * @param context_course $context Course context.
     * @param int $requested Requested group, or 0.
     * @return array{0:int,1:array}
     */
    public function group_scope(stdClass $course, context_course $context, int $requested): array {
        global $USER;
        $restricted = groups_get_course_groupmode($course) === SEPARATEGROUPS
            && !has_capability('moodle/site:accessallgroups', $context);
        $groups = groups_get_all_groups($course->id, $restricted ? $USER->id : 0);
        if ($requested && !isset($groups[$requested])) {
            throw new \moodle_exception('academicgroupunavailable', 'local_moodlexhub');
        }
        if (!$requested && $restricted) {
            $requested = $groups ? (int) key($groups) : -1;
        }
        $options = $restricted ? [] : [[
            'id' => 0,
            'name' => get_string('allparticipants'),
            'selected' => !$requested,
        ]];
        foreach ($groups as $group) {
            $options[] = [
                'id' => $group->id,
                'name' => html_to_text(format_string($group->name, true, ['context' => $context]), 0, false),
                'selected' => $group->id == $requested,
            ];
        }
        return [$requested, $options];
    }

    /**
     * Read a page of enrolled learners with last course access and activity progress.
     *
     * @param stdClass $course Course being inspected.
     * @param context_course $context Course context.
     * @param int $groupid Group filter resolved by group_scope().
     * @param int $offset Page offset.
     * @param int $limit Page size.
     * @param int $inactivecutoff Show only learners without a recorded access since this time.
     * @param int $userid Optional learner filter within the authorized scope.
     * @return array{total:int,rows:array,tracked:int}
     */
    public function learners(
        stdClass $course,
        context_course $context,
        int $groupid,
        int $offset,
        int $limit,
        int $inactivecutoff,
        int $userid = 0
    ): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        if ($groupid < 0) {
            return ['total' => 0, 'rows' => [], 'tracked' => 0];
        }

        [$enrolledsql, $params] = get_enrolled_sql($context, self::STUDENT, $groupid, true);
        $params['courseid'] = $course->id;
        $from = " FROM {user} u
                  JOIN ($enrolledsql) e ON e.id = u.id
             LEFT JOIN {user_lastaccess} la ON la.userid = u.id AND la.courseid = :courseid
                 WHERE u.deleted = 0 AND u.suspended = 0";
        if ($inactivecutoff) {
            $from .= ' AND (la.timeaccess IS NULL OR la.timeaccess < :cutoff)';
            $params['cutoff'] = $inactivecutoff;
        }
        if ($userid) {
            $from .= ' AND u.id = :learnerfilter';
            $params['learnerfilter'] = $userid;
        }
        $total = $DB->count_records_sql("SELECT COUNT(u.id) $from", $params);
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = $DB->get_records_sql(
            "SELECT u.id, $fields, la.timeaccess $from ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC",
            $params,
            $offset,
            $limit,
        );

        $completion = new completion_info($course);
        $tracked = [];
        if ($completion->is_enabled() && has_capability('report/progress:view', $context)) {
            foreach (get_fast_modinfo($course)->get_cms() as $cm) {
                if ($cm->visible && !$cm->deletioninprogress && $completion->is_enabled($cm)) {
                    $tracked[$cm->id] = true;
                }
            }
        }
        $progress = [];
        if ($users && $tracked) {
            // Read Moodle's recorded completion states in one query for the visible page.
            [$insql, $p] = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED, 'learner');
            $p['completioncourse'] = $course->id;
            $recordset = $DB->get_recordset_sql(
                'SELECT c.userid, c.coursemoduleid, c.completionstate
                   FROM {course_modules_completion} c
                   JOIN {course_modules} m ON m.id = c.coursemoduleid
                  WHERE m.course = :completioncourse AND c.userid ' . $insql,
                $p,
            );
            try {
                foreach ($recordset as $record) {
                    if (isset($tracked[$record->coursemoduleid]) && (int) $record->completionstate > COMPLETION_INCOMPLETE) {
                        $progress[$record->userid] = ($progress[$record->userid] ?? 0) + 1;
                    }
                }
            } finally {
                $recordset->close();
            }
        }

        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                'cells' => [
                    fullname($user, has_capability('moodle/site:viewfullnames', $context)),
                    $user->timeaccess ? userdate($user->timeaccess) : get_string('never'),
                    $tracked ? ($progress[$user->id] ?? 0) . ' / ' . count($tracked) : get_string('notapplicable', 'local_moodlexhub'),
                ],
                'userid' => (int) $user->id,
            ];
        }

        return ['total' => $total, 'rows' => $rows, 'tracked' => count($tracked)];
    }

    /**
     * Count Moodle log reads and participation events in two equal periods.
     *
     * @param context_course $context Course context.
     * @param int $groupid Group filter already checked.
     * @param int $days Days in each period.
     * @param int|null $through End of the current period as a Unix timestamp.
     * @param int $userid Optional learner already checked against enrolment and group scope.
     * @return array|null Both periods, or null if no eligible log reader is available.
     */
    public function trends(context_course $context, int $groupid, int $days, ?int $through = null, int $userid = 0): ?array {
        if ($groupid < 0 || !has_capability('report/log:view', $context)) {
            return null;
        }
        $readers = get_log_manager()->get_readers(sql_reader::class);
        if (!$readers) {
            return null;
        }
        $reader = reset($readers);
        $groups = [null];
        if ($groupid) {
            $members = groups_get_members($groupid, 'u.id');
            $groups = array_chunk(array_keys($members), 500);
        }
        $now = $through ?? time();
        $span = DAYSECS * $days;
        $periods = [];
        foreach ([$now - 2 * $span, $now - $span] as $start) {
            $metrics = ['read' => 0, 'participation' => 0];
            foreach ($groups as $members) {
                $where = 'courseid = ? AND timecreated >= ? AND timecreated < ? AND anonymous = ?';
                $params = [$context->instanceid, $start, $start + $span, 0];
                if ($userid) {
                    $where .= ' AND userid = ?';
                    $params[] = $userid;
                }
                if (is_array($members)) {
                    $where .= ' AND userid IN (' . implode(',', array_fill(0, count($members), '?')) . ')';
                    array_push($params, ...$members);
                }
                $metrics['read'] += $reader->get_events_select_count($where . ' AND crud = ?', [...$params, 'r']);
                $metrics['participation'] += $reader->get_events_select_count(
                    $where . ' AND edulevel = ? AND crud <> ?',
                    [...$params, event_base::LEVEL_PARTICIPATING, 'r'],
                );
            }
            $periods[] = $metrics;
        }
        return $periods;
    }

    /**
     * List upcoming activity dates available to the current course member.
     *
     * @param stdClass $course Moodle course record.
     * @param int $days Number of days to look ahead.
     * @return array The next activity dates, sorted chronologically.
     */
    public function upcoming(stdClass $course, int $days): array {
        global $USER;
        $now = time();
        $until = $now + DAYSECS * $days;
        $rows = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if ($cm->deletioninprogress || !$cm->uservisible || !$cm->url) {
                continue;
            }
            foreach (\core\activity_dates::get_dates_for_module($cm, $USER->id) as $date) {
                if (!$date['timestamp'] || $date['timestamp'] < $now || $date['timestamp'] > $until) {
                    continue;
                }
                $rows[] = [
                    'timestamp' => $date['timestamp'],
                    'label' => html_to_text($date['label'], 0, false),
                    'date' => userdate($date['timestamp']),
                    'name' => html_to_text($cm->get_formatted_name(), 0, false),
                    'url' => $cm->url->out(false),
                ];
            }
        }
        usort($rows, static fn(array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);
        return array_slice($rows, 0, 25);
    }
}
