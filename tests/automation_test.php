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
use local_moodlexhub\automation\engine;
use local_moodlexhub\quality\auditor;
use local_moodlexhub\report\builder;

/**
 * Confirm that scheduled operations persist outcomes without creating duplicates.
 */
final class automation_test extends advanced_testcase {
    /**
     * The same scheduled slot queues one job and performs one audit.
     */
    public function test_run_is_idempotent_and_report_uses_moodle_audit(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $now = time();
        $id = $DB->insert_record(engine::RULES, (object) [
            'userid' => $USER->id, 'courseid' => $course->id, 'title' => 'Audit',
            'conditiontype' => 'always', 'actiontype' => 'audit', 'enabled' => 1,
            'threshold' => 1, 'intervalvalue' => DAYSECS, 'nextdue' => $now, 'timemodified' => $now,
        ]);

        $engine = new engine();
        $run = $engine->enqueue($id, $now);
        $this->assertGreaterThan(0, $run);
        $engine->enqueue($id, $now);
        $this->assertEquals(1, $DB->count_records(engine::RUNS, ['ruleid' => $id]));

        $engine->process($run, 3);
        $engine->process($run, 3);
        $this->assertEquals(1, $DB->count_records(auditor::TABLE, ['courseid' => $course->id]));
        $result = (new builder())->generate((object) [
            'title' => 'Audit report', 'source' => 'quality', 'courseid' => $course->id, 'filtervalue' => 1,
        ]);
        $this->assertNotEmpty($result['rows']);
    }
}
