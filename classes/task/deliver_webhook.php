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

use local_moodlexhub\integration\webhooks;

/**
 * Deliver one signed event to an allowed endpoint.
 */
final class deliver_webhook extends \core\task\adhoc_task {
    #[\Override]
    public function get_name(): string {
        return get_string('taskdeliverwebhook', 'local_moodlexhub');
    }

    #[\Override]
    protected function get_default_concurrency_limit(): int {
        return 1;
    }

    #[\Override]
    public function execute(): void {
        webhooks::send((int) $this->get_custom_data()->deliveryid, $this->get_attempts_available());
    }
}
