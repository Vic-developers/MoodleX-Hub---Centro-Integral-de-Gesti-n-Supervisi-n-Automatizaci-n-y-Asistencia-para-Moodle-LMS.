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

namespace local_moodlexhub\storage;

use context_course;
use core\lock\lock_config;
use core\task\manager;
use local_moodlexhub\report\snapshots;
use local_moodlexhub\task\scan_storage;
use stdClass;

/**
 * Inspect stored-file metadata in a Moodle course context tree.
 */
final class scan {
    /** @var string Storage snapshot source key. */
    public const SOURCE = 'storage';
    /** @var string Moodle capability required for this operation. */
    public const CAPABILITY = 'local/moodlexhub:viewstorage';

    /**
     * Check permissions for aggregate storage inspection.
     *
     * @param stdClass $course Course record.
     * @return context_course
     */
    public function require_access(stdClass $course): context_course {
        \local_moodlexhub\module_config::require_enabled('storage');
        if ($course->id == SITEID || !\core_course_category::can_view_course_info($course)) {
            throw new \moodle_exception('courseunavailable', 'local_moodlexhub');
        }
        $context = context_course::instance($course->id);
        require_capability(self::CAPABILITY, $context);
        require_capability('moodle/course:update', $context);
        return $context;
    }

    /**
     * Enqueue one scan per course, keeping the job state and task atomic.
     *
     * @param stdClass $course Course record.
     * @return int Entry identifier.
     */
    public function schedule(stdClass $course): int {
        global $DB, $USER;

        $this->require_access($course);
        $lock = lock_config::get_lock_factory('local_moodlexhub_storage')->get_lock('course_' . $course->id, 5);
        if (!$lock) {
            throw new \moodle_exception('storagebusy', 'local_moodlexhub');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            $pending = $DB->get_records(snapshots::TABLE, [
                'courseid' => $course->id,
                'source' => self::SOURCE,
                'status' => 'queued',
            ], 'id DESC', 'id', 0, 1);
            if ($pending) {
                $transaction->allow_commit();
                return (int) key($pending);
            }

            $id = snapshots::create($course->id, self::SOURCE);
            $task = new scan_storage();
            $task->set_userid($USER->id);
            $task->set_attempts_available(3);
            $task->set_custom_data(['courseid' => (int) $course->id, 'snapshotid' => $id]);
            manager::queue_adhoc_task($task, true);
            $transaction->allow_commit();
            return $id;
        } finally {
            $lock->release();
        }
    }

    /**
     * Read metadata through the File API and return aggregate results.
     *
     * @param stdClass $course Course record.
     * @return array
     */
    public function collect(stdClass $course): array {
        global $DB;

        $context = $this->require_access($course);
        $contextids = [(int) $context->id];
        foreach ($context->get_child_contexts() as $child) {
            $contextids[] = (int) $child->id;
        }
        $storage = get_file_storage();
        $counts = [];
        $largest = [];
        $bytes = 0;
        $filecount = 0;
        $references = 0;
        $orphaned = 0;

        foreach (array_chunk($contextids, 500) as $ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ctx');
            $params['dot'] = '.';
            // File API area reads require a component and an area. Discover those keys without accessing file contents.
            $areas = $DB->get_records_sql(
                'SELECT MIN(id) AS id, contextid, component, filearea FROM {files}
                   WHERE contextid ' . $insql . ' AND filename <> :dot
                   GROUP BY contextid, component, filearea',
                $params,
            );
            foreach ($areas as $area) {
                $orphaned += $this->known_orphans($area);
                $offset = 0;
                do {
                    $files = $storage->get_area_files(
                        $area->contextid,
                        $area->component,
                        $area->filearea,
                        false,
                        'id ASC',
                        false,
                        0,
                        $offset,
                        200,
                    );
                    foreach ($files as $file) {
                        $size = $file->get_filesize();
                        $bytes += $size;
                        $filecount++;
                        $references += (int) $file->is_external_file();
                        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
                        $extension = preg_match('/^[a-z0-9]{1,12}$/D', $extension) ? $extension : '';
                        $counts[$extension] ??= ['files' => 0, 'bytes' => 0];
                        $counts[$extension]['files']++;
                        $counts[$extension]['bytes'] += $size;

                        if (count($largest) < 20 || end($largest)['bytes'] < $size) {
                            $largest[] = ['area' => $area->component . '/' . $area->filearea, 'ext' => $extension, 'bytes' => $size];
                            usort($largest, static fn(array $a, array $b): int => $b['bytes'] <=> $a['bytes']);
                            if (count($largest) > 20) {
                                array_pop($largest);
                            }
                        }
                    }
                    $offset += count($files);
                } while (count($files) === 200);
            }
        }

        // Use a database aggregate over metadata to deduplicate local content across the whole course.
        // This never reads file payloads or the file pool, and cannot account for other courses sharing the same hashes.
        [$insql, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'scope');
        $params['dot'] = '.';
        $summary = $DB->get_record_sql(
            'SELECT COALESCE(SUM(sizebytes), 0) AS physicalbytes, COALESCE(SUM(copies - 1), 0) AS duplicates
               FROM (
                 SELECT MAX(filesize) AS sizebytes, COUNT(id) AS copies
                   FROM {files}
                  WHERE contextid ' . $insql . ' AND filename <> :dot AND referencefileid IS NULL
                    AND filesize > 0
               GROUP BY contenthash
               ) h',
            $params,
            MUST_EXIST,
        );
        uasort($counts, static fn(array $a, array $b): int => $b['bytes'] <=> $a['bytes']);
        return [
            'bytes' => $bytes,
            'filecount' => $filecount,
            'external' => $references,
            'physicalbytes' => (int) $summary->physicalbytes,
            'duplicates' => (int) $summary->duplicates,
            'orphans' => $orphaned,
            'extensions' => $counts,
            'largest' => $largest,
        ];
    }

    /**
     * Count file records whose expected owner is missing in two known assignment areas.
     *
     * @param stdClass $area Moodle File API area.
     * @return int
     */
    private function known_orphans(stdClass $area): int {
        global $DB;
        $owners = [
            'assignsubmission_file/submission_files' => 'assign_submission',
            'assignfeedback_file/feedback_files' => 'assign_grades',
        ];
        $table = $owners[$area->component . '/' . $area->filearea] ?? null;
        if (!$table) {
            return 0;
        }
        return $DB->count_records_sql(
            'SELECT COUNT(f.id) FROM {files} f
              WHERE f.contextid = :contextid AND f.component = :component AND f.filearea = :filearea AND f.filename <> :dot
                AND NOT EXISTS (SELECT 1 FROM {' . $table . '} recordowner WHERE recordowner.id = f.itemid)',
            [
                'contextid' => $area->contextid, 'component' => $area->component, 'filearea' => $area->filearea, 'dot' => '.',
            ],
        );
    }
}
