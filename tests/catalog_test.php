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
use context_system;
use local_moodlexhub\course\catalog;
use required_capability_exception;

/**
 * Course catalogue filters and scope.
 */
final class catalog_test extends advanced_testcase {
    /**
     * Course capabilities and Moodle visibility apply before any data is returned.
     */
    public function test_course_search_respects_context_and_visibility(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $first = $generator->create_course(['category' => $category->id, 'fullname' => 'Catalog alpha']);
        $other = $generator->create_course(['category' => $category->id, 'fullname' => 'Catalog beta']);
        $hidden = $generator->create_course(['category' => $category->id, 'fullname' => 'Catalog hidden', 'visible' => 0]);
        $user = $generator->create_user();
        $role = $generator->create_role();
        assign_capability(catalog::VIEW, CAP_ALLOW, $role, context_system::instance()->id);
        $generator->role_assign($role, $user->id, context_course::instance($first->id)->id);
        $generator->role_assign($role, $user->id, context_course::instance($hidden->id)->id);
        $this->setUser($user);

        $catalogue = new catalog();
        $ids = array_keys(iterator_to_array($catalogue->matching('catalog', $category->id, 0)));
        $this->assertSame([(int) $first->id], $ids);
        $this->assertEmpty(iterator_to_array($catalogue->matching('catalog', $category->id, 2)));

        $this->expectException(required_capability_exception::class);
        $catalogue->require_course($other);
    }
}
