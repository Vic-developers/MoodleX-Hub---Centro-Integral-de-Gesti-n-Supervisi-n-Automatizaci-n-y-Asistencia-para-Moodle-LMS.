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

namespace local_moodlexhub\grade;

use context_course;
use grade_category;
use grade_item;
use stdClass;

/**
 * Read course grade structure and flag anomalies without touching grades.
 */
final class diagnostics {
    /** @var string Moodle capability required for this operation. */
    public const CAPABILITY = 'local/moodlexhub:viewgradesdiagnostics';
    /** @var string[] Native Moodle permissions for gradebook inspection. */
    public const CORE_CAPABILITIES = ['moodle/grade:manage', 'moodle/grade:viewhidden'];

    /**
     * Require both plug-in and core grade inspection permissions.
     *
     * @param stdClass $course Course record.
     * @return context_course
     */
    public function require_access(stdClass $course): context_course {
        \local_moodlexhub\module_config::require_enabled('grades');
        if ($course->id == SITEID || !\core_course_category::can_view_course_info($course)) {
            throw new \moodle_exception('courseunavailable', 'local_moodlexhub');
        }
        $context = context_course::instance($course->id);
        require_all_capabilities([self::CAPABILITY, ...self::CORE_CAPABILITIES], $context);
        return $context;
    }

    /**
     * Get diagnostic rows for categories, totals and activities.
     *
     * @param stdClass $course Course record.
     * @return array{categories: array, items: array}
     */
    public function inspect(stdClass $course): array {
        global $CFG;

        $this->require_access($course);
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/lib.php');
        $label = fn(string $key): string => get_string($key, 'local_moodlexhub');
        $categories = grade_category::fetch_all(['courseid' => $course->id]) ?: [];
        $items = grade_item::fetch_all(['courseid' => $course->id]) ?: [];
        $aggregations = \grade_helper::get_aggregation_strings();
        $modinfo = get_fast_modinfo($course);
        $categoryrows = [];
        foreach ($categories as $category) {
            $finding = '';
            if ($category->parent && !isset($categories[$category->parent])) {
                $finding = $label('gradeorphanparent');
            } else if (!isset($aggregations[$category->aggregation])) {
                $finding = $label('gradeunknownaggregation');
            }
            $categoryrows[] = [
                'name' => html_to_text($category->get_name(), 0, false),
                'type' => $aggregations[$category->aggregation] ?? $label('gradeunknownaggregation'),
                'finding' => $finding,
            ];
        }
        $itemrows = [];
        foreach ($items as $item) {
            $finding = [];
            $type = $item->itemtype === 'mod' ? $item->itemmodule : $item->itemtype;
            $itemcategory = $item->categoryid ? ($categories[$item->categoryid] ?? null) : null;
            if (!$itemcategory && in_array($item->itemtype, ['mod', 'manual'], true)) {
                $finding[] = $label('gradeuncategorised');
            }
            if ($item->gradetype == GRADE_TYPE_VALUE && $item->grademin >= $item->grademax) {
                $finding[] = $label('gradeinvalidrange');
            }
            if ($item->gradetype == GRADE_TYPE_SCALE && (!$item->scaleid || !$item->load_scale())) {
                $finding[] = $label('grademissingscale');
            }
            if ($item->itemtype === 'mod' && empty($modinfo->get_instances_of($item->itemmodule)[$item->iteminstance])) {
                $finding[] = $label('grademissingactivity');
            }
            $range = match ((int) $item->gradetype) {
                GRADE_TYPE_VALUE => format_float($item->grademin, 2) . ' – ' . format_float($item->grademax, 2),
                GRADE_TYPE_SCALE => $label('gradescale'),
                GRADE_TYPE_TEXT => $label('gradetext'),
                default => $label('gradenone'),
            };
            $itemrows[] = [
                'name' => html_to_text($item->get_name(), 0, false),
                'type' => $type,
                'category' => $itemcategory ? html_to_text($itemcategory->get_name(), 0, false) : '',
                'visibility' => $label($item->is_hidden() ? 'coursehidden' : 'coursevisible'),
                'range' => $range,
                'finding' => implode(' ', $finding),
            ];
        }

        return ['categories' => $categoryrows, 'items' => $itemrows];
    }
}
