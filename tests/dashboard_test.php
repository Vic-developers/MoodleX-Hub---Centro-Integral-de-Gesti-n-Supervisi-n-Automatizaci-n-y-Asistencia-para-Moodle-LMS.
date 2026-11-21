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
use local_moodlexhub\output\dashboard;

/**
 * Dashboard data and preferences in Moodle.
 */
final class dashboard_test extends advanced_testcase {
    /**
     * Dashboard data is drawn from the active Moodle user's enrolments.
     */
    public function test_dashboard_lists_enrolled_courses_only(): void {
        global $PAGE;
        $this->resetAfterTest();
        set_config('dashboardcourses', 1, 'local_moodlexhub');
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course(['fullname' => 'Enrolled course']);
        $generator->create_course(['fullname' => 'Other course']);
        $generator->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $data = (new dashboard())->export_for_template($PAGE->get_renderer('core'));
        $this->assertTrue($data['showcourses']);
        $this->assertSame(['Enrolled course'], array_column($data['courses'], 'name'));
        $this->assertNull($data['settingsurl']);
    }

    /**
     * Personal and site preferences can independently disable the course widget.
     */
    public function test_preferences_hide_course_widget(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('dashboardcourses', 1, 'local_moodlexhub');
        set_user_preference('local_moodlexhub_showcourses', 0);

        $dashboard = new dashboard();
        $renderer = $PAGE->get_renderer('core');
        $this->assertFalse($dashboard->export_for_template($renderer)['showcourses']);

        set_config('dashboardcourses', 0, 'local_moodlexhub');
        set_user_preference('local_moodlexhub_showcourses', 1);
        $this->assertFalse($dashboard->export_for_template($renderer)['showcourses']);
    }
}
