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

use local_moodlexhub\ai\assistant;
use local_moodlexhub\automation\engine;
use local_moodlexhub\integration\webhooks;
use local_moodlexhub\quality\bulk;
use local_moodlexhub\quality\auditor;
use local_moodlexhub\report\builder;
use local_moodlexhub\report\snapshots;

/**
 * Apply the configured retention period to historical quality audits.
 */
final class purge_audits extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('taskpurgeaudits', 'local_moodlexhub');
    }

    #[\Override]
    public function execute(): void {
        global $DB;
        $configured = get_config('local_moodlexhub', 'auditretentiondays');
        $days = $configured === false ? 365 : max(1, min(3650, (int) $configured));
        $DB->delete_records_select(auditor::TABLE, 'timecreated < :cutoff', ['cutoff' => time() - DAYSECS * $days]);
        $DB->delete_records_select(snapshots::TABLE, 'timemodified < :cutoff', ['cutoff' => time() - DAYSECS * $days]);
        $DB->delete_records_select(assistant::TABLE, 'timecreated < :cutoff', ['cutoff' => time() - DAYSECS * 90]);
        $history = ['cutoff' => time() - DAYSECS * $days];
        foreach ([builder::RUNS, engine::RUNS, webhooks::LOG] as $table) {
            $DB->delete_records_select($table, 'timemodified < :cutoff', $history);
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records_select(
            bulk::ROWS,
            'jobid IN (SELECT id FROM {' . bulk::JOBS . '} WHERE timemodified < :cutoff)',
            $history
        );
        $DB->delete_records_select(bulk::JOBS, 'timemodified < :cutoff', $history);
        $transaction->allow_commit();
    }
}
