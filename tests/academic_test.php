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
use context_system;
use local_moodlexhub\academic\assignment_queue;
use local_moodlexhub\academic\course_metrics;
use local_moodlexhub\grade\diagnostics;
use local_moodlexhub\report\builder;

/**
 * Course reports read real enrolments and native activity and grade data.
 */
final class academic_test extends advanced_testcase {
    /**
     * Group and learner filters apply to report rows and totals.
     */
    public function test_academic_metrics_use_moodle_enrolment_and_group_scope(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $first = $generator->create_user(['firstname' => 'Group', 'lastname' => 'Member']);
        $second = $generator->create_user(['firstname' => 'Outside', 'lastname' => 'Group']);
        $generator->enrol_user($first->id, $course->id, 'student');
        $generator->enrol_user($second->id, $course->id, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $first->id]);
        $DB->insert_record('user_lastaccess', (object) ['userid' => $first->id, 'courseid' => $course->id, 'timeaccess' => time()]);
        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Research task', 'duedate' => time() + DAYSECS]);
        $context = context_course::instance($course->id);

        $service = new course_metrics();
        $result = $service->learners($course, $context, $group->id, 0, 25, 0);
        $this->assertSame(1, $result['total']);
        $this->assertSame('Group Member', $result['rows'][0]['cells'][0]);
        $this->assertSame((int) $first->id, $result['rows'][0]['userid']);
        $this->assertSame(0, $service->learners($course, $context, $group->id, 0, 25, 0, $second->id)['total']);
        $this->assertSame(1, $service->learners($course, $context, 0, 0, 25, time() - DAYSECS)['total']);

        $queue = (new assignment_queue())->get_rows($course, 7, 0);
        $this->assertSame('Research task', $queue[0]['name']);
        $this->assertEquals(2, $queue[0]['expected']);
        $this->assertContains('Research task', array_column($service->upcoming($course, 7), 'name'));
        $grade = (new diagnostics())->inspect($course);
        $this->assertContains('Research task', array_column($grade['items'], 'name'));
    }

    /**
     * A teacher restricted to separate groups cannot select a different group's data.
     */
    public function test_teacher_is_restricted_to_their_separate_group(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $context = context_course::instance($course->id);
        $teacher = $generator->create_user();
        $member = $generator->create_user();
        $other = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($member->id, $course->id, 'student');
        $generator->enrol_user($other->id, $course->id, 'student');
        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $teacherrole, $context->id);
        assign_capability(course_metrics::ANALYTICS, CAP_ALLOW, $teacherrole, $context->id);
        $reportrole = $generator->create_role();
        $system = context_system::instance();
        assign_capability(builder::CAPABILITY, CAP_ALLOW, $reportrole, $system->id);
        $generator->role_assign($reportrole, $teacher->id, $system->id);
        $group = $generator->create_group(['courseid' => $course->id]);
        $othergroup = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $member->id]);
        $generator->create_group_member(['groupid' => $othergroup->id, 'userid' => $other->id]);
        $generator->create_module('assign', ['course' => $course->id, 'duedate' => time() + DAYSECS]);

        $this->setUser($teacher);
        $service = new course_metrics();
        $this->assertEquals($context->id, $service->require_access($course, course_metrics::TEACHER)->id);
        [$groupid, $options] = $service->group_scope($course, $context, 0);
        $this->assertSame((int) $group->id, $groupid);
        $this->assertEquals([$group->id], array_column($options, 'id'));
        $rows = $service->learners($course, $context, $groupid, 0, 25, 0)['rows'];
        $this->assertSame([(int) $member->id], array_column($rows, 'userid'));
        $assignments = (new assignment_queue())->get_rows($course, 7, $groupid);
        $this->assertCount(1, $assignments);
        $this->assertEquals(1, $assignments[0]['expected']);
        $service->require_access($course, course_metrics::ANALYTICS);
        try {
            (new builder())->require_source('learners', $course->id);
            $this->fail('A user limited to one group cannot export the whole course learner report.');
        } catch (\required_capability_exception $exception) {
            $this->assertFalse(has_capability('moodle/site:accessallgroups', $context));
        }

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('academicgroupunavailable', 'local_moodlexhub'));
        $service->group_scope($course, $context, $othergroup->id);
    }
}
