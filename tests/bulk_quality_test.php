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
use local_moodlexhub\quality\bulk;

/**
 * Category audits create one result per course in the authorized target set.
 */
final class bulk_quality_test extends advanced_testcase {
    /**
     * A later task can continue the batch without duplicating completed audits.
     */
    public function test_category_batch_resumes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $generator->create_course(['category' => $category->id]);
        $generator->create_course(['category' => $category->id]);
        $other = $generator->create_course();
        set_config('importbatchsize', 1, 'local_moodlexhub');
        $id = bulk::start($category->id);

        bulk::process($id, 0);
        $this->assertEquals(1, $DB->count_records(auditor::TABLE));
        bulk::process($id, 1);
        bulk::process($id, 1);
        $this->assertEquals(2, $DB->count_records(auditor::TABLE));
        $this->assertFalse($DB->record_exists(auditor::TABLE, ['courseid' => $other->id]));
        $this->assertSame('finished', $DB->get_field(bulk::JOBS, 'status', ['id' => $id], MUST_EXIST));
    }
}
