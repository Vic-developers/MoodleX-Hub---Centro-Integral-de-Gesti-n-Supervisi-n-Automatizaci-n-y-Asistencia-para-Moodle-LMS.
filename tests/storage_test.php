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
use local_moodlexhub\storage\scan;

/**
 * Storage amounts come from actual Moodle files.
 */
final class storage_test extends advanced_testcase {
    /**
     * Duplicate content contributes logical copies but only one local blob.
     */
    public function test_scan_distinguishes_logical_and_deduplicated_bytes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        foreach (['one.txt' => 'abc', 'two.txt' => 'abc', 'three.txt' => 'abcd'] as $filename => $content) {
            get_file_storage()->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'course',
                'filearea' => 'overviewfiles',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $filename,
            ], $content);
        }

        $result = (new scan())->collect($course);
        $this->assertSame(10, $result['bytes']);
        $this->assertSame(7, $result['physicalbytes']);
        $this->assertSame(1, $result['duplicates']);
        $this->assertSame(['files' => 3, 'bytes' => 10], $result['extensions']['txt']);
    }
}
