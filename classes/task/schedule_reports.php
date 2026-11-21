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

use local_moodlexhub\report\builder;

/**
 * Dispatch reports whose saved schedule is due.
 */
final class schedule_reports extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('taskschedulereports', 'local_moodlexhub');
    }

    #[\Override]
    public function execute(): void {
        global $DB;
        if (!\local_moodlexhub\module_config::enabled('reports')) {
            return;
        }
        $records = $DB->get_records_select(
            builder::REPORTS,
            'nextdue > 0 AND nextdue <= :time',
            ['time' => time()],
            'nextdue ASC, id ASC',
            'id,userid,nextdue',
            0,
            100
        );
        $service = new builder();
        foreach ($records as $report) {
            $account = \core_user::get_user($report->userid, 'id,deleted,suspended', IGNORE_MISSING);
            if (!$account || $account->deleted || $account->suspended) {
                $DB->set_field(builder::REPORTS, 'nextdue', 0, ['id' => $report->id]);
                continue;
            }
            $service->enqueue($report->id, $report->nextdue);
        }
    }
}
