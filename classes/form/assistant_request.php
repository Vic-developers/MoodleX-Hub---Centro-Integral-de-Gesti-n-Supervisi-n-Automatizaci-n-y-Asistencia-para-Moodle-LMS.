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

use local_moodlexhub\ai\assistant;

/**
 * Collect an AI request and explicit consent for sharing its context.
 */
final class assistant_request extends \moodleform {
    #[\Override]
    protected function definition(): void {
        $form = $this->_form;
        $label = fn(string $key): string => get_string($key, 'local_moodlexhub');
        $modes = $this->_customdata['testonly'] ? ['connection'] : array_diff(assistant::MODES, ['connection']);
        $form->addElement('select', 'mode', $label('assistantmode'), array_combine(
            $modes,
            array_map(fn($mode) => $label('assistantmode_' . $mode), $modes),
        ));
        if (!$this->_customdata['testonly']) {
            $form->addElement('textarea', 'question', $label('assistantquestion'), ['rows' => 7, 'maxlength' => 3000]);
            $form->setType('question', PARAM_RAW_TRIMMED);
            $form->addRule('question', get_string('required'), 'required');
            $form->addRule('question', get_string('maximumchars', '', 3000), 'maxlength', 3000, 'server');
            $form->addElement('text', 'reference', $label('assistantreference'));
            $form->setType('reference', PARAM_INT);
        }
        $form->addElement('advcheckbox', 'consent', $label('assistantconsent'));
        $this->add_action_buttons(false, $label('assistantask'));
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($data['consent'])) {
            $errors['consent'] = get_string('assistantconsentrequired', 'local_moodlexhub');
        }
        if (in_array($data['mode'], ['knowledge', 'quality'], true) && empty($data['reference'])) {
            $errors['reference'] = get_string('required');
        }
        return $errors;
    }
}
