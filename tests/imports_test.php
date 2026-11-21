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
use local_moodlexhub\enrol\imports;

/**
 * Bulk enrolment preview and execution against Moodle.
 */
final class imports_test extends advanced_testcase {
    /**
     * Preview is side-effect free and confirmed imports are repeatable without duplication.
     */
    public function test_preview_and_repeated_processing(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $account = $generator->create_user(['username' => 'catalogue-learner']);
        $method = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username\ncatalogue-learner\nCATALOGUE-LEARNER\nmissing-account\n");
        rewind($stream);
        $imports = new imports();
        try {
            $id = $imports->preview($course, (int) $method->id, (int) $studentrole, $stream);
        } finally {
            fclose($stream);
        }
        $context = context_course::instance($course->id);
        $this->assertFalse(is_enrolled($context, $account));
        $rows = $DB->get_records(imports::ROWS, ['importid' => $id], 'position');
        $this->assertSame(['ready', 'invalid', 'invalid'], array_column($rows, 'status'));
        $this->assertSame(['duplicate', 'unknown'], array_values(array_filter(array_column($rows, 'reason'))));

        $imports->schedule($imports->require_job($id, $course), $course);
        $imports->process($id, 0);
        $imports->process($id, 0);

        $this->assertTrue(is_enrolled($context, $account, '', true));
        $this->assertEquals(1, $DB->count_records('user_enrolments', ['enrolid' => $method->id, 'userid' => $account->id]));
        $this->assertSame('finished', $DB->get_field(imports::JOBS, 'status', ['id' => $id], MUST_EXIST));
    }

    /**
     * A four-digit row count remains scoped to existing accounts and batched lookups.
     */
    public function test_large_preview_keeps_one_valid_row_per_username(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->create_user(['username' => 'large-import-learner']);
        $method = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $role = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username\n" . str_repeat("large-import-learner\n", 1100));
        rewind($stream);
        try {
            $id = (new imports())->preview($course, $method->id, $role, $stream);
        } finally {
            fclose($stream);
        }
        $this->assertEquals(1100, $DB->count_records(imports::ROWS, ['importid' => $id]));
        $this->assertEquals(1, $DB->count_records(imports::ROWS, ['importid' => $id, 'status' => 'ready']));
        $this->assertEquals(1099, $DB->count_records(imports::ROWS, ['importid' => $id, 'reason' => 'duplicate']));
    }
}
