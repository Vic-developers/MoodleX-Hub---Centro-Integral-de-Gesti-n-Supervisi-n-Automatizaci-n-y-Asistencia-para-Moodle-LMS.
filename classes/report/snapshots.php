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

namespace local_moodlexhub\report;

use stdClass;

/**
 * History of aggregate course diagnostics.
 */
final class snapshots {
    /** @var string XMLDB table for this module's records. */
    public const TABLE = 'local_moodlexhub_snapshot';

    /**
     * Create an entry for a diagnostic that is ready or waiting for a task.
     *
     * @param int $courseid Course identifier.
     * @param string $source Stable module identifier.
     * @param array|null $data Aggregate results, or null for a queued job.
     * @return int Entry identifier.
     */
    public static function create(int $courseid, string $source, ?array $data = null): int {
        global $DB;
        $now = time();
        return $DB->insert_record(self::TABLE, (object) [
            'courseid' => $courseid,
            'source' => $source,
            'status' => $data === null ? 'queued' : 'finished',
            'summary' => $data === null ? null : json_encode($data, JSON_THROW_ON_ERROR),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Load an entry scoped to a course and a module.
     *
     * @param int $id Entry identifier.
     * @param int $courseid Course identifier.
     * @param string $source Stable module identifier.
     * @param int $strictness Standard Moodle DML strictness.
     * @return stdClass|false
     */
    public static function get(int $id, int $courseid, string $source, int $strictness = MUST_EXIST): stdClass|false {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id, 'courseid' => $courseid, 'source' => $source], '*', $strictness);
    }

    /**
     * Save an aggregate diagnostic result.
     *
     * @param int $id Entry identifier.
     * @param string $status Operation state.
     * @param array|null $summary Aggregate results, when available.
     */
    public static function update(int $id, string $status, ?array $summary = null): void {
        global $DB;
        $DB->update_record(self::TABLE, (object) [
            'id' => $id,
            'status' => $status,
            'summary' => $summary === null ? null : json_encode($summary, JSON_THROW_ON_ERROR),
            'timemodified' => time(),
        ]);
    }

    /**
     * The most recent entries for a particular course and diagnostic.
     *
     * @param int $courseid Course identifier.
     * @param string $source Stable module identifier.
     * @param int $limit Number of historical entries.
     * @return stdClass[]
     */
    public static function recent(int $courseid, string $source, int $limit = 10): array {
        global $DB;
        return $DB->get_records(
            self::TABLE,
            ['courseid' => $courseid, 'source' => $source],
            'timecreated DESC, id DESC',
            '*',
            0,
            $limit,
        );
    }
}
