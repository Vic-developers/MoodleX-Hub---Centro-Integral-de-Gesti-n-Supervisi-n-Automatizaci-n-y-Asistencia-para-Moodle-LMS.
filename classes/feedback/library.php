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

namespace local_moodlexhub\feedback;

use context_course;
use context_user;
use core_favourites\service_factory;
use stdClass;

/**
 * Personal and course-shared grading feedback templates.
 */
final class library {
    /** @var string XMLDB table for this module's records. */
    public const TABLE = 'local_moodlexhub_feedback';
    /** @var string Moodle capability required for this operation. */
    public const CAPABILITY = 'local/moodlexhub:managefeedback';
    /** @var string Item type stored by the Moodle Favourites API. */
    public const FAVOURITE = 'feedback_template';
    /** @var string[] Supported content types. */
    public const TYPES = ['comment', 'rubric', 'draft'];

    /**
     * Verify course-level permission to prepare feedback.
     *
     * @param stdClass $course Moodle course record.
     * @return context_course
     */
    public function require_access(stdClass $course): context_course {
        \local_moodlexhub\module_config::require_enabled('feedback');
        if ($course->id == SITEID || !\core_course_category::can_view_course_info($course)) {
            throw new \moodle_exception('courseunavailable', 'local_moodlexhub');
        }
        $context = context_course::instance($course->id);
        require_all_capabilities([self::CAPABILITY, 'mod/assign:grade'], $context);
        return $context;
    }

    /**
     * Return the template if it is in the selected course and readable.
     *
     * @param int $id Template identifier.
     * @param stdClass $course Selected course.
     * @return stdClass
     */
    public function require_template(int $id, stdClass $course): stdClass {
        global $DB, $USER;
        $this->require_access($course);
        $template = $DB->get_record(self::TABLE, ['id' => $id, 'courseid' => $course->id], '*', MUST_EXIST);
        if ($template->userid != $USER->id && !$template->shared) {
            throw new \moodle_exception('feedbackunavailable', 'local_moodlexhub');
        }
        return $template;
    }

    /**
     * Check an assignment in this course before attaching a draft to it.
     *
     * @param int $cmid Course-module ID.
     * @param stdClass $course Selected course.
     * @return \cm_info
     */
    public function require_assignment(int $cmid, stdClass $course): \cm_info {
        $this->require_access($course);
        $cm = get_fast_modinfo($course)->get_cm($cmid);
        if ($cm->modname !== 'assign' || !$cm->uservisible || $cm->deletioninprogress) {
            throw new \moodle_exception('feedbackunavailable', 'local_moodlexhub');
        }
        require_capability('mod/assign:grade', $cm->context);
        return $cm;
    }

    /**
     * Find the current user's bookmarked feedback templates.
     *
     * @return array<int, bool>
     */
    public function get_favourites(): array {
        global $USER;
        $service = service_factory::get_service_for_user_context(context_user::instance($USER->id));
        return array_fill_keys(array_column($service->find_favourites_by_type('local_moodlexhub', self::FAVOURITE), 'itemid'), true);
    }

    /**
     * Set the bookmark state of an accessible template.
     *
     * @param stdClass $template Authorized template.
     * @param context_course $context Course context.
     * @param bool $save New state.
     */
    public function favourite(stdClass $template, context_course $context, bool $save): void {
        global $USER;
        $service = service_factory::get_service_for_user_context(context_user::instance($USER->id));
        $old = $service->favourite_exists('local_moodlexhub', self::FAVOURITE, $template->id, $context);
        if ($save && !$old) {
            $service->create_favourite('local_moodlexhub', self::FAVOURITE, $template->id, $context);
        } else if (!$save && $old) {
            $service->delete_favourite('local_moodlexhub', self::FAVOURITE, $template->id, $context);
        }
    }
}
