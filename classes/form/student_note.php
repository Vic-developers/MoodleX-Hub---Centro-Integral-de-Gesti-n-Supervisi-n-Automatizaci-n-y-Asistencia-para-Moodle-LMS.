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

namespace local_moodlexhub\form;

/**
 * Create or update a private planner note.
 */
final class student_note extends \moodleform {
    #[\Override]
    protected function definition(): void {
        $form = $this->_form;
        $form->addElement('hidden', 'noteid');
        $form->setType('noteid', PARAM_INT);
        $form->addElement('text', 'title', get_string('studentnotetitle', 'local_moodlexhub'), ['maxlength' => 120]);
        $form->setType('title', PARAM_TEXT);
        $form->addRule('title', get_string('required'), 'required');
        $form->addRule('title', get_string('maximumchars', '', 120), 'maxlength', 120, 'server');
        $form->addElement('textarea', 'body', get_string('studentnotebody', 'local_moodlexhub'), ['rows' => 5, 'maxlength' => 6000]);
        $form->setType('body', PARAM_RAW_TRIMMED);
        $form->addRule('body', get_string('required'), 'required');
        $form->addRule('body', get_string('maximumchars', '', 6000), 'maxlength', 6000, 'server');
        $this->add_action_buttons(true);
    }
}
