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

namespace local_moodlexhub\event;

use core\event\base;

/**
 * A user requested an export of the course catalogue.
 */
final class course_catalogue_exported extends base {
    #[\Override]
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('eventcoursecatalogueexported', 'local_moodlexhub');
    }

    #[\Override]
    public function get_description(): string {
        return "The user with id '$this->userid' requested a CSV export of the MoodleX Hub course catalogue.";
    }

    #[\Override]
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/moodlexhub/courses.php');
    }
}
