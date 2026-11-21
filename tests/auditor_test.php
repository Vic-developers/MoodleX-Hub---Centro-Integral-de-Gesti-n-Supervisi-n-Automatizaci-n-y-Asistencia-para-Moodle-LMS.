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
use local_moodlexhub\quality\auditor;
use local_moodlexhub\quality\category_template;
use required_capability_exception;

/**
 * Course audit scoring and persistence.
 */
final class auditor_test extends advanced_testcase {
    /**
     * Disabled and inapplicable rules leave the score denominator.
     */
    public function test_audit_persists_rule_results_and_weighted_score(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['summary' => '', 'startdate' => time(), 'enddate' => 0]);
        foreach (auditor::WEIGHTS as $rule => $default) {
            set_config('quality_' . $rule, 0, 'local_moodlexhub');
        }
        set_config('quality_coursedescription', 3, 'local_moodlexhub');
        set_config('quality_coursedates', 2, 'local_moodlexhub');
        $auditid = (new auditor())->run($course);
        $audit = $DB->get_record(auditor::TABLE, ['id' => $auditid], '*', MUST_EXIST);

        $this->assertEquals($course->id, $audit->courseid);
        $this->assertEquals($USER->id, $audit->userid);
        $this->assertEquals(0, $audit->score);
        $this->assertSame([
            ['rule' => 'coursedescription', 'weight' => 3, 'count' => 1, 'template' => 0],
            ['rule' => 'coursedates', 'weight' => 2, 'count' => null, 'template' => 0],
        ], json_decode($audit->findings, true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * A student without audit rights cannot persist an audit.
     */
    public function test_student_cannot_run_course_audit(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(required_capability_exception::class);
        (new auditor())->run($course);
    }

    /**
     * Category templates inherit, override and affect persisted audit results.
     */
    public function test_category_template_controls_child_course_audits(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $parent = $generator->create_category();
        $child = $generator->create_category(['parent' => $parent->id]);
        $course = $generator->create_course(['category' => $child->id, 'summary' => '']);
        $service = new category_template();
        $weights = array_fill_keys(array_keys(auditor::WEIGHTS), 0);
        $weights['coursedescription'] = 2;
        $service->save($parent->id, $weights);
        $this->assertSame((int) $parent->id, $service->resolve($child->id)['category']);
        $id = (new auditor())->run($course);
        $findings = $DB->get_field(auditor::TABLE, 'findings', ['id' => $id], MUST_EXIST);
        $this->assertSame([[
            'rule' => 'coursedescription', 'weight' => 2, 'count' => 1, 'template' => (int) $parent->id,
        ]], json_decode($findings, true, flags: JSON_THROW_ON_ERROR));
        $weights['coursedescription'] = 3;
        $service->save($child->id, $weights);
        $this->assertSame($weights, $service->resolve($child->id)['weights']);
        $service->save($child->id, null);
        $this->assertSame(2, $service->resolve($child->id)['weights']['coursedescription']);
    }
}
