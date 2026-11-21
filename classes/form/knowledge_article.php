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

use local_moodlexhub\support\knowledge;

/**
 * Create or edit an article, FAQ or tutorial.
 */
final class knowledge_article extends \moodleform {
    #[\Override]
    protected function definition(): void {
        $form = $this->_form;
        $label = fn(string $key): string => get_string($key, 'local_moodlexhub');
        $form->addElement('hidden', 'id');
        $form->setType('id', PARAM_INT);
        foreach (['title' => 160, 'category' => 100] as $field => $limit) {
            $form->addElement('text', $field, $label('knowledge' . $field), ['maxlength' => $limit]);
            $form->setType($field, PARAM_TEXT);
            $form->addRule($field, get_string('required'), 'required');
            $form->addRule($field, get_string('maximumchars', '', $limit), 'maxlength', $limit, 'server');
        }
        $types = array_combine(knowledge::TYPES, array_map(fn($type) => $label('knowledgetype_' . $type), knowledge::TYPES));
        $form->addElement('select', 'type', $label('knowledgetype'), $types);
        $form->addElement('textarea', 'body', $label('knowledgebody'), ['rows' => 14, 'maxlength' => 30000]);
        $form->setType('body', PARAM_RAW_TRIMMED);
        $form->addRule('body', get_string('required'), 'required');
        $form->addRule('body', get_string('maximumchars', '', 30000), 'maxlength', 30000, 'server');
        $form->addElement('advcheckbox', 'published', $label('knowledgepublished'));
        $this->add_action_buttons(true);
    }
}
