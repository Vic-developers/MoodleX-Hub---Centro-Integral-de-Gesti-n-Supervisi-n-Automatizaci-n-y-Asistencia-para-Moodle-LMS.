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

namespace local_moodlexhub\course;

use context_course;
use core_course_category;
use stdClass;

/**
 * Course catalogue scoped to the current user's Moodle capabilities.
 */
final class catalog {
    /** @var string Capability to inspect the course catalogue. */
    public const VIEW = 'local/moodlexhub:viewcourses';
    /** @var string[] Supported catalogue diagnostics. */
    public const HEALTH = ['empty', 'inactive', 'nocontacts', 'duplicates'];

    /**
     * Read accessible course records for a catalogue view or export.
     *
     * @param string $search Text to find in full name, short name or ID number.
     * @param int $category Moodle category ID, or zero for all categories.
     * @param int $visibility Zero for all, one for visible and two for hidden.
     * @param string $health Optional administrative catalogue filter.
     * @return \Generator<int, stdClass>
     */
    public function matching(string $search, int $category, int $visibility, string $health = ''): \Generator {
        $courses = get_user_capability_course(
            self::VIEW,
            null,
            true,
            'fullname,shortname,idnumber,category,visible,ctxid,ctxpath,ctxdepth,ctxlevel,ctxinstance,ctxlocked',
            'sortorder ASC, id ASC',
        );
        $needle = \core_text::strtolower(trim($search));
        $visible = [];
        foreach ($courses ?: [] as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            \context_helper::preload_from_record($course);
            if (core_course_category::can_view_course_info($course)) {
                $visible[$course->id] = $course;
            }
        }
        foreach ($this->filter_health($visible, $health) as $course) {
            if (
                ($category && $course->category != $category)
                || ($visibility && (int) $course->visible !== (int) ($visibility === 1))
            ) {
                continue;
            }
            if ($needle !== '' && !$this->matches_text($course, $needle)) {
                continue;
            }
            yield $course->id => $course;
        }
    }

    /**
     * Apply one diagnostic to courses previously checked for view access.
     *
     * @param stdClass[] $courses Authorized Moodle course records.
     * @param string $health Health filter.
     * @return stdClass[]
     */
    private function filter_health(array $courses, string $health): array {
        global $CFG, $DB;
        if ($health === '' || !$courses) {
            return $courses;
        }
        if ($health === 'duplicates') {
            $count = [];
            foreach ($courses as $course) {
                $title = \core_text::strtolower(trim($course->fullname));
                $count[$title] = ($count[$title] ?? 0) + 1;
            }
            return array_filter($courses, static fn($course): bool => $count[\core_text::strtolower(trim($course->fullname))] > 1);
        }
        if ($health === 'nocontacts') {
            if (empty($CFG->coursecontact)) {
                return [];
            }
            core_course_category::preload_course_contacts($courses);
            return array_filter($courses, static fn($course): bool => empty($course->managers));
        }
        $active = [];
        foreach (array_chunk(array_keys($courses), 500) as $ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'course');
            if ($health === 'inactive') {
                $params['cutoff'] = time() - 30 * DAYSECS;
                $recent = $DB->get_fieldset_sql('SELECT DISTINCT courseid FROM {user_lastaccess}
                    WHERE courseid ' . $insql . ' AND timeaccess >= :cutoff', $params);
            } else {
                $params['label'] = 'label';
                $recent = $DB->get_fieldset_sql('SELECT DISTINCT cm.course FROM {course_modules} cm
                    JOIN {modules} m ON m.id = cm.module
                    WHERE cm.course ' . $insql . ' AND cm.deletioninprogress = 0 AND m.name <> :label', $params);
            }
            $active += array_fill_keys($recent, true);
        }
        return array_diff_key($courses, $active);
    }

    /**
     * Confirm the requested course can be inspected in the current session.
     *
     * @param stdClass $course Moodle course record.
     * @return context_course Authoritative course context.
     */
    public function require_course(stdClass $course): context_course {
        $context = context_course::instance($course->id);
        require_capability(self::VIEW, $context);
        if ($course->id == SITEID || !core_course_category::can_view_course_info($course)) {
            throw new \moodle_exception('courseunavailable', 'local_moodlexhub');
        }
        return $context;
    }

    /**
     * Compare the search text against plain course metadata.
     *
     * @param stdClass $course Moodle course record.
     * @param string $needle Lowercase text.
     * @return bool
     */
    private function matches_text(stdClass $course, string $needle): bool {
        foreach ([$course->fullname, $course->shortname, $course->idnumber] as $value) {
            if (str_contains(\core_text::strtolower($value ?? ''), $needle)) {
                return true;
            }
        }
        return false;
    }
}
