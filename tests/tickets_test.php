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

namespace local_moodlexhub;

use advanced_testcase;
use context_system;
use local_moodlexhub\support\tickets;
use moodle_exception;

/**
 * Ticket access and state transitions are checked with real Moodle roles.
 */
final class tickets_test extends advanced_testcase {
    /**
     * The requester and assigned responder can access a ticket. Other users cannot.
     */
    public function test_private_ticket_access_and_state_transitions(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $requester = $generator->create_user();
        $other = $generator->create_user();
        $agent = $generator->create_user();
        $role = $generator->create_role();
        $system = context_system::instance();
        assign_capability(tickets::RESPOND, CAP_ALLOW, $role, $system->id);
        $generator->role_assign($role, $agent->id, $system->id);
        $service = new tickets();

        $this->setUser($requester);
        $id = $service->create((object) ['subject' => 'Needs help', 'body' => 'A private message', 'priority' => 'normal']);
        $initial = $DB->get_record(tickets::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertTrue($service->can_access($initial, $requester->id));
        $this->assertFalse($service->can_access($initial, $other->id));
        $this->assertFalse($service->can_access($initial, $agent->id));

        $this->setAdminUser();
        $service->update($id, (object) ['status' => 'assigned', 'body' => '', 'assignedto' => $agent->id]);
        $unassignedid = $service->create((object) ['subject' => 'Separate ticket', 'body' => 'Another request', 'priority' => 'normal']);
        $this->setUser($agent);
        $unassigned = $DB->get_record(tickets::TABLE, ['id' => $unassignedid], '*', MUST_EXIST);
        $this->assertFalse($service->can_access($unassigned, $agent->id));
        $this->assertFalse(has_capability(tickets::MANAGE, $system));
        $service->update($id, (object) ['status' => 'inprogress', 'body' => 'Working on it.']);
        $updated = $DB->get_record(tickets::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('inprogress', $updated->status);
        $this->assertTrue($service->can_access($updated, $agent->id));
        $this->assertEquals(2, $DB->count_records(tickets::POSTS, ['ticketid' => $id]));

        $this->setUser($other);
        $this->expectException(moodle_exception::class);
        $service->update($id, (object) ['status' => 'resolved', 'body' => 'Unauthorized.']);
    }
}
