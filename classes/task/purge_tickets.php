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

use local_moodlexhub\support\privacy;

/**
 * Apply the configured retention of closed support tickets.
 */
final class purge_tickets extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('taskpurgetickets', 'local_moodlexhub');
    }

    #[\Override]
    public function execute(): void {
        $configured = get_config('local_moodlexhub', 'ticketretentiondays');
        $days = $configured === false ? 365 : max(1, min(3650, (int) $configured));
        privacy::delete_matching('status = :closed AND timemodified < :cutoff', [
            'closed' => 'closed', 'cutoff' => time() - DAYSECS * $days,
        ]);
    }
}
