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

use local_moodlexhub\report\snapshots;
use local_moodlexhub\storage\scan;

/**
 * Compute the storage inventory of a course in the background.
 */
final class scan_storage extends \core\task\adhoc_task {
    #[\Override]
    public function get_name(): string {
        return get_string('taskscanstorage', 'local_moodlexhub');
    }

    #[\Override]
    protected function get_default_concurrency_limit(): int {
        return 1;
    }

    #[\Override]
    public function execute(): void {
        global $DB;
        $data = $this->get_custom_data();
        $entry = snapshots::get((int) $data->snapshotid, (int) $data->courseid, scan::SOURCE, IGNORE_MISSING);
        if (!$entry || $entry->status !== 'queued') {
            return;
        }
        $service = new scan();
        try {
            $course = get_course($entry->courseid);
            $service->require_access($course);
        } catch (\moodle_exception $exception) {
            snapshots::update($entry->id, 'blocked');
            return;
        }
        try {
            $result = $service->collect($course);
            $transaction = $DB->start_delegated_transaction();
            try {
                snapshots::update($entry->id, 'finished', $result);
                \local_moodlexhub\integration\webhooks::publish('storage', $entry->id);
                $transaction->allow_commit();
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            }
        } catch (\Throwable $exception) {
            if ($this->get_attempts_available() <= 1) {
                snapshots::update($entry->id, 'failed');
            }
            throw $exception;
        }
    }
}
