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

namespace local_moodlexhub\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_moodlexhub\event\api_read;
use local_moodlexhub\integration\rate_limit;
use local_moodlexhub\support\tickets;

/**
 * Read authorized ticket status without retrieving private message contents.
 */
final class get_ticket_status extends external_api {
    /**
     * Declare the ticket status input.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(['ticketid' => new external_value(PARAM_INT, 'Ticket ID')]);
    }

    /**
     * Return status metadata after checking ticket permissions.
     *
     * @param int $ticketid Ticket ID.
     * @return array
     */
    public static function execute(int $ticketid): array {
        global $DB, $USER;
        $args = self::validate_parameters(self::execute_parameters(), ['ticketid' => $ticketid]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moodlexhub:useapi', $context);
        rate_limit::check();
        $ticket = $DB->get_record(tickets::TABLE, ['id' => $args['ticketid']], 'id,userid,assignedto,status,priority,timemodified');
        if (!$ticket || !(new tickets())->can_access($ticket, $USER->id)) {
            throw new \moodle_exception('ticketunavailable', 'local_moodlexhub');
        }
        api_read::create(['context' => $context, 'other' => ['operation' => 'ticket_status']])->trigger();
        return [
            'id' => $ticket->id, 'status' => $ticket->status, 'priority' => $ticket->priority, 'modified' => $ticket->timemodified,
        ];
    }

    /**
     * Declare the ticket status response.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Ticket ID'),
            'status' => new external_value(PARAM_ALPHA, 'Ticket status'),
            'priority' => new external_value(PARAM_ALPHA, 'Ticket priority'),
            'modified' => new external_value(PARAM_INT, 'Unix modification time'),
        ]);
    }
}
