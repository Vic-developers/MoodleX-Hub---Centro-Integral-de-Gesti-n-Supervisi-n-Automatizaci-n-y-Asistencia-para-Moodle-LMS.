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

namespace local_moodlexhub\quality;

use context_coursecat;
use core_course_category;

/**
 * Category audit templates inherit through Moodle's category tree.
 */
final class category_template {
    /** @var string Capability for editing criteria in a category. */
    public const CAPABILITY = 'local/moodlexhub:managequalitytemplates';

    /**
     * Find the closest category template and return its current settings.
     *
     * @param int $categoryid Course category.
     * @return array{category:int,weights:array}
     */
    public function resolve(int $categoryid): array {
        $category = core_course_category::get($categoryid, MUST_EXIST, true);
        foreach ([$categoryid, ...array_reverse($category->get_parents())] as $id) {
            $configured = get_config('local_moodlexhub', 'qualitytemplate_' . $id);
            if ($configured !== false) {
                return ['category' => (int) $id, 'weights' => json_decode($configured, true, flags: JSON_THROW_ON_ERROR)];
            }
        }
        $weights = [];
        foreach (auditor::WEIGHTS as $rule => $default) {
            $configured = get_config('local_moodlexhub', 'quality_' . $rule);
            $weights[$rule] = $configured === false ? $default : max(0, min(3, (int) $configured));
        }
        return ['category' => 0, 'weights' => $weights];
    }

    /**
     * Write a full category template or restore inherited criteria.
     *
     * @param int $categoryid Category to manage.
     * @param array|null $weights Rule weights or null to inherit.
     */
    public function save(int $categoryid, ?array $weights): void {
        \local_moodlexhub\module_config::require_enabled('quality');
        core_course_category::get($categoryid, MUST_EXIST);
        require_capability(self::CAPABILITY, context_coursecat::instance($categoryid));
        $key = 'qualitytemplate_' . $categoryid;
        if ($weights === null) {
            unset_config($key, 'local_moodlexhub');
            return;
        }
        $rules = [];
        foreach (auditor::WEIGHTS as $rule => $default) {
            if (!isset($weights[$rule]) || !in_array((int) $weights[$rule], [0, 1, 2, 3], true)) {
                throw new \invalid_parameter_exception();
            }
            $rules[$rule] = (int) $weights[$rule];
        }
        set_config($key, json_encode($rules, JSON_THROW_ON_ERROR), 'local_moodlexhub');
    }
}
