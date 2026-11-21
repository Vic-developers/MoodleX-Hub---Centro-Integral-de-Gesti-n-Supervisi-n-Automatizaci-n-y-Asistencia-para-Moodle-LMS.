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
use local_moodlexhub\ai\assistant;
use local_moodlexhub\output\monitoring;
use moodle_exception;
use required_capability_exception;

/**
 * Server-side gates for sensitive operations.
 */
final class controllers_test extends advanced_testcase {
    /**
     * The assistant is disabled in an unconfigured Moodle site even for admins.
     */
    public function test_ai_is_off_by_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->expectException(moodle_exception::class);
        assistant::require_access();
    }

    /**
     * Ordinary users cannot access runtime and task information.
     */
    public function test_monitoring_requires_capability(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(required_capability_exception::class);
        (new monitoring('hub', 0))->export_for_template($PAGE->get_renderer('core'));
    }
}
