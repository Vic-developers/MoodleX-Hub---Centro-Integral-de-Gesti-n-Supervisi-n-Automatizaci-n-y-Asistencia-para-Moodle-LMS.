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
use context_user;
use core_privacy\local\request\approved_contextlist;
use local_moodlexhub\privacy\provider;
use local_moodlexhub\student\planner;
use local_moodlexhub\support\tickets;

/**
 * Privacy checks for a personal context and protected ticket attachments.
 */
final class privacy_test extends advanced_testcase {
    /**
     * An approved personal request removes only the selected user's notes.
     */
    public function test_personal_notes_are_deleted_in_the_approved_context(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        foreach ([$first, $second] as $user) {
            $DB->insert_record(planner::NOTES, (object) [
                'userid' => $user->id, 'title' => 'Private note', 'body' => 'Sample note',
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $context = context_user::instance($first->id);
        $contextids = array_map('intval', provider::get_contexts_for_userid($first->id)->get_contextids());
        $this->assertContains((int) $context->id, $contextids);
        provider::delete_data_for_user(new approved_contextlist($first, 'local_moodlexhub', [$context->id]));
        $this->assertFalse($DB->record_exists(planner::NOTES, ['userid' => $first->id]));
        $this->assertTrue($DB->record_exists(planner::NOTES, ['userid' => $second->id]));
    }

    /**
     * The file callback refuses an authenticated but unrelated ticket reader.
     */
    public function test_ticket_attachment_is_not_served_to_an_unrelated_user(): void {
        global $CFG, $SITE;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/moodlexhub/lib.php');
        $requester = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($requester);
        $id = (new tickets())->create((object) ['subject' => 'Attachment needed', 'body' => 'Sample question', 'priority' => 'normal']);
        $context = context_system::instance();
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'local_moodlexhub', 'filearea' => tickets::FILEAREA,
            'itemid' => $id, 'filepath' => '/', 'filename' => 'proof.txt',
        ], 'Sample attachment');

        $this->setUser($other);
        $this->assertFalse(local_moodlexhub_pluginfile($SITE, null, $context, tickets::FILEAREA, [$id, 'proof.txt'], false));
    }
}
