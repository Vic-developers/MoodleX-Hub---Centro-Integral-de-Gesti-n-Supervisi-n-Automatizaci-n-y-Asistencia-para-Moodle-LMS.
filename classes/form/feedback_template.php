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

use local_moodlexhub\feedback\library;

/**
 * Create a personal or a course-shared feedback template, or save a draft.
 */
final class feedback_template extends \moodleform {
    #[\Override]
    protected function definition(): void {
        $form = $this->_form;
        $label = fn(string $key): string => get_string($key, 'local_moodlexhub');
        foreach (['courseid', 'templateid', 'source'] as $key) {
            $form->addElement('hidden', $key);
            $form->setType($key, PARAM_INT);
        }
        $form->addElement('text', 'title', $label('feedbacktitle'), ['maxlength' => 160]);
        $form->setType('title', PARAM_TEXT);
        $form->addRule('title', get_string('required'), 'required');
        $form->addRule('title', get_string('maximumchars', '', 160), 'maxlength', 160, 'server');
        $form->addElement('text', 'category', $label('knowledgecategory'), ['maxlength' => 100]);
        $form->setType('category', PARAM_TEXT);
        $form->addRule('category', get_string('maximumchars', '', 100), 'maxlength', 100, 'server');
        $types = array_combine(library::TYPES, array_map(fn($type) => $label('feedbacktype_' . $type), library::TYPES));
        $form->addElement('select', 'type', $label('knowledgetype'), $types);
        $form->addElement('textarea', 'body', $label('feedbackbody'), ['rows' => 10, 'maxlength' => 15000]);
        $form->setType('body', PARAM_RAW_TRIMMED);
        $form->addRule('body', get_string('required'), 'required');
        $form->addRule('body', get_string('maximumchars', '', 15000), 'maxlength', 15000, 'server');
        $form->addElement('select', 'cmid', $label('feedbackassignment'), $this->_customdata['assignments']);
        if ($this->_customdata['share']) {
            $form->addElement('advcheckbox', 'shared', $label('feedbackshare'));
            $form->disabledIf('shared', 'type', 'eq', 'draft');
        }
        $this->add_action_buttons();
    }
}
