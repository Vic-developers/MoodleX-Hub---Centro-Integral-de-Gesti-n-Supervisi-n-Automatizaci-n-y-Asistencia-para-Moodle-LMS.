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
use context_module;
use local_moodlexhub\question\catalogue;

/**
 * The question catalogue follows Moodle's question bank contexts.
 */
final class questions_test extends advanced_testcase {
    /**
     * An instructor with view permission can find duplicate titles in an authorized bank.
     */
    public function test_question_search_counts_only_permitted_entries(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $bank = $generator->create_module('qbank', ['course' => $course->id]);
        $context = context_module::instance($bank->cmid);
        $qgenerator = $generator->get_plugin_generator('core_question');
        $category = $qgenerator->create_question_category(['contextid' => $context->id]);
        $qgenerator->create_question('essay', null, ['category' => $category->id, 'name' => 'Review problem']);
        $qgenerator->create_question('essay', null, ['category' => $category->id, 'name' => 'Review problem']);
        $service = new catalogue();
        $filters = ['search' => 'Review', 'bank' => $context->id, 'category' => $category->id, 'author' => 0, 'type' => 'essay'];
        $data = $service->search($service->scope($course), $filters, 0, 20);
        $this->assertSame(2, $data['total']);
        $this->assertSame([1, 1], array_map(static fn(array $row): int => $row['cells'][7], $data['rows']));
    }
}
