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
use context_course;
use local_moodlexhub\student\planner;

/**
 * The planner uses Moodle activity completion and keeps bookmarks user-scoped.
 */
final class planner_test extends advanced_testcase {
    /**
     * Untracked activities stay untracked and favouriting only affects the current user.
     */
    public function test_activity_bookmarks_belong_to_the_current_student(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $resource = $generator->create_module('page', ['course' => $course->id, 'completion' => 0]);
        $first = $generator->create_user();
        $second = $generator->create_user();
        $generator->enrol_user($first->id, $course->id, 'student');
        $generator->enrol_user($second->id, $course->id, 'student');

        $this->setUser($first);
        $service = new planner();
        $service->set_favourite($resource->cmid, true);
        $firstview = $service->activities($course->id);
        $this->assertCount(1, $firstview);
        $this->assertTrue($firstview[0]['favourite']);
        $this->assertFalse($firstview[0]['completed']);
        $this->assertFalse($firstview[0]['tracked']);

        $this->setUser($second);
        $this->assertFalse($service->activities($course->id)[0]['favourite']);
    }
}
