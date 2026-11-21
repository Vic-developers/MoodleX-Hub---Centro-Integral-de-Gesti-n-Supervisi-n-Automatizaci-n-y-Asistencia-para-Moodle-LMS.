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

use local_moodlexhub\automation\engine;

/**
 * Dispatch due MoodleX Hub rules.
 */
final class schedule_automation extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('taskscheduleautomation', 'local_moodlexhub');
    }

    #[\Override]
    public function execute(): void {
        global $DB;
        if (!\local_moodlexhub\module_config::enabled('automation')) {
            return;
        }
        $rules = $DB->get_records_select(
            engine::RULES,
            'enabled = 1 AND nextdue > 0 AND nextdue <= :time',
            ['time' => time()],
            'nextdue ASC, id ASC',
            'id,userid,nextdue',
            0,
            100
        );
        $service = new engine();
        foreach ($rules as $rule) {
            $account = \core_user::get_user($rule->userid, 'id,deleted,suspended', IGNORE_MISSING);
            if (!$account || $account->deleted || $account->suspended) {
                $DB->set_field(engine::RULES, 'enabled', 0, ['id' => $rule->id]);
                continue;
            }
            $service->enqueue($rule->id, $rule->nextdue);
        }
    }
}
