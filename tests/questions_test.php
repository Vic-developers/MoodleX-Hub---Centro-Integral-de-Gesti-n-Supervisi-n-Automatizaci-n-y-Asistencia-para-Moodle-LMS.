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
        $this->assertSame(2, $service->search($service->scope($course), $filters + ['status' => 'ready'], 0, 20)['total']);
        $this->assertSame(0, $service->search($service->scope($course), $filters + ['status' => 'draft'], 0, 20)['total']);
    }

    /**
     * Matching stems and choices are detected across differently named accessible questions.
     */
    public function test_question_content_comparison_respects_bank_scope(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $bank = $generator->create_module('qbank', ['course' => $course->id]);
        $outside = $generator->create_module('qbank', ['course' => $generator->create_course()->id]);
        $questions = $generator->get_plugin_generator('core_question');
        $category = $questions->create_question_category(['contextid' => context_module::instance($bank->cmid)->id]);
        $hidden = $questions->create_question_category(['contextid' => context_module::instance($outside->cmid)->id]);
        $prompt = '<p>Which element belongs to this family?</p>';
        foreach (['Reference', 'Renamed entry', 'Another course'] as $name) {
            $questions->create_question('multichoice', null, [
                'category' => $name === 'Another course' ? $hidden->id : $category->id,
                'name' => $name,
                'questiontext' => ['text' => $prompt, 'format' => FORMAT_HTML],
            ]);
        }
        $service = new catalogue();
        $scope = $service->scope($course);
        $filters = ['search' => 'Reference'];
        $page = $service->search($scope, $filters, 0, 10);
        $study = $service->search($scope, $filters, 0, 10, $page['rows'][0]['entryid'])['comparison'];
        $this->assertSame(['Renamed entry'], array_column($study['matches'], 'name'));
        $this->assertTrue($study['matches'][0]['exact']);
        $this->assertTrue($study['matches'][0]['options']);
        $this->assertStringContainsString('/question/bank/previewquestion/preview.php', $study['matches'][0]['url']);
    }
}
