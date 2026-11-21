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
use completion_info;
use context_course;
use context_module;
use local_moodlexhub\academic\activity_workload;
use local_moodlexhub\academic\followup;
use local_moodlexhub\privacy\followup_records;
use moodle_exception;

/**
 * Validate academic evidence, scoped access and the intervention lifecycle.
 */
final class followup_test extends advanced_testcase {
    /**
     * A teacher can manage a case for a member of their group and audit the interventions.
     */
    public function test_case_lifecycle_respects_group_and_privacy(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $context = context_course::instance($course->id);
        $teacher = $generator->create_user();
        $learner = $generator->create_user();
        $outsider = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($learner->id, $course->id, 'student');
        $generator->enrol_user($outsider->id, $course->id, 'student');
        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $teacherrole, $context->id);
        $group = $generator->create_group(['courseid' => $course->id]);
        $othergroup = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $learner->id]);
        $generator->create_group_member(['groupid' => $othergroup->id, 'userid' => $outsider->id]);
        $service = new followup();
        $othercase = $service->open($course, $othergroup->id, $outsider->id, 0);

        $this->setUser($teacher);
        $id = $service->open($course, $group->id, $learner->id, 0);
        $this->assertSame($id, $service->open($course, $group->id, $learner->id, 0));
        $this->assertEquals([$id], array_column($service->queue($course, $group->id, 'open', 0, 0, 15)['rows'], 'id'));
        $service->update($course, $group->id, $id, 'take', '');
        $this->assertEquals($teacher->id, $service->get($course, $group->id, $id)->assignee);
        $service->update($course, $group->id, $id, 'note', 'Called the learner.');
        $service->update($course, $group->id, $id, 'close', 'Course access restored.');
        $this->assertSame('closed', $service->get($course, $group->id, $id)->status);
        $this->assertEquals([$id], array_column($service->queue($course, $group->id, 'closed', 0, 0, 15)['rows'], 'id'));
        $service->update($course, $group->id, $id, 'reopen', 'Review needed.');
        $this->assertSame('open', $service->get($course, $group->id, $id)->status);
        $logs = $DB->get_records(followup::HISTORY, ['caseid' => $id], 'id ASC');
        $this->assertSame(['opened', 'take', 'note', 'close', 'reopen'], array_column($logs, 'action'));
        try {
            $service->get($course, $group->id, $othercase);
            $this->fail('A case for another group was accessible.');
        } catch (moodle_exception $exception) {
            $this->assertSame('followupunavailable', $exception->errorcode);
        }

        followup_records::delete_users($course->id, [$teacher->id]);
        $this->assertFalse($DB->record_exists(followup::HISTORY, ['caseid' => $id, 'actorid' => $teacher->id]));
        $this->assertNotContains('Called the learner.', $DB->get_fieldset_select(followup::HISTORY, 'note', 'caseid = ?', [$id]));
        followup_records::delete_users($course->id, [$learner->id]);
        $this->assertFalse($DB->record_exists(followup::CASES, ['id' => $id]));
        $this->assertFalse($DB->record_exists(followup::HISTORY, ['caseid' => $id]));
        $this->assertTrue($DB->record_exists(followup::CASES, ['id' => $othercase]));
    }

    /**
     * Assignment extensions, quiz closing times and forum completion use Moodle data.
     */
    public function test_selected_activity_uses_native_deadlines_and_completion(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $learner = $generator->create_user();
        $generator->enrol_user($learner->id, $course->id, 'student');
        $assign = $generator->create_module('assign', [
            'course' => $course->id, 'duedate' => time() - DAYSECS, 'assignsubmission_onlinetext_enabled' => 1,
            'submissiondrafts' => 0,
        ]);
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'timeclose' => time() - DAYSECS]);
        $essayquiz = $generator->create_module('quiz', ['course' => $course->id, 'timeclose' => time() + DAYSECS]);
        $bank = $generator->create_module('qbank', ['course' => $course->id]);
        $questions = $generator->get_plugin_generator('core_question');
        $category = $questions->create_question_category(['contextid' => context_module::instance($bank->cmid)->id]);
        $essay = $questions->create_question('essay', null, ['category' => $category->id]);
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        quiz_add_quiz_question($essay->id, $quiz);
        quiz_add_quiz_question($essay->id, $essayquiz);
        \mod_quiz\quiz_settings::create($essayquiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        $forum = $generator->create_module('forum', [
            'course' => $course->id, 'completion' => 1, 'completionexpected' => time() - DAYSECS,
        ]);
        $blind = $generator->create_module('assign', [
            'course' => $course->id, 'blindmarking' => 1, 'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $workload = new activity_workload();
        $activities = $workload->activities($course, 0);
        $this->assertArrayNotHasKey($blind->cmid, $activities);
        foreach ([$assign, $quiz, $forum] as $activity) {
            $this->assertSame('late', $workload->inspect($course, $activities[$activity->cmid], $learner->id)['state']);
        }

        $generator->get_plugin_generator('mod_assign')->create_extension([
            'cmid' => $assign->cmid, 'userid' => $learner->id, 'extensionduedate' => time() + DAYSECS,
        ]);
        $this->assertSame('pending', $workload->inspect($course, $activities[$assign->cmid], $learner->id)['state']);
        $generator->get_plugin_generator('mod_assign')->create_submission([
            'cmid' => $assign->cmid, 'userid' => $learner->id, 'onlinetext' => 'Submitted work',
        ]);
        $this->assertSame('grading', $workload->inspect($course, $activities[$assign->cmid], $learner->id)['state']);

        $this->setUser($learner);
        $quizgenerator = $generator->get_plugin_generator('mod_quiz');
        $attempt = $quizgenerator->create_attempt($essayquiz->id, $learner->id);
        $quizgenerator->submit_responses($attempt->id, [1 => 'An explanation awaiting grading.'], false, true);
        $this->setAdminUser();
        $this->assertSame('grading', $workload->inspect($course, $activities[$essayquiz->cmid], $learner->id)['state']);

        require_once($CFG->libdir . '/completionlib.php');
        (new completion_info($course))->update_state($activities[$forum->cmid], COMPLETION_COMPLETE, $learner->id);
        $this->assertSame('done', $workload->inspect($course, $activities[$forum->cmid], $learner->id)['state']);
    }
}
