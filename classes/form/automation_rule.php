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

use local_moodlexhub\automation\engine;

/**
 * Configure an allowlisted schedule, recipient, condition and action.
 */
final class automation_rule extends \moodleform {
    #[\Override]
    protected function definition(): void {
        $form = $this->_form;
        $label = fn(string $key): string => get_string($key, 'local_moodlexhub');
        $form->addElement('hidden', 'ruleid');
        $form->setType('ruleid', PARAM_INT);
        $form->addElement('text', 'title', $label('automationtitle'), ['maxlength' => 160]);
        $form->setType('title', PARAM_TEXT);
        $form->addRule('title', get_string('required'), 'required');
        $form->addRule('title', get_string('maximumchars', '', 160), 'maxlength', 160, 'server');
        $form->addElement('textarea', 'description', get_string('description'), ['rows' => 4, 'maxlength' => 4000]);
        $form->setType('description', PARAM_RAW_TRIMMED);
        $form->addRule('description', get_string('maximumchars', '', 4000), 'maxlength', 4000, 'server');
        $form->addElement('select', 'courseid', get_string('course'), $this->_customdata['courses']);
        $form->addElement('select', 'conditiontype', $label('automationcondition'), array_combine(
            engine::CONDITIONS,
            array_map(fn($key) => $label('automationcondition_' . $key), engine::CONDITIONS)
        ));
        $form->addElement('text', 'threshold', $label('automationthreshold'));
        $form->setType('threshold', PARAM_INT);
        $form->setDefault('threshold', 7);
        $form->addElement('select', 'actiontype', $label('automationaction'), array_combine(
            engine::ACTIONS,
            array_map(fn($key) => $label('automationaction_' . $key), engine::ACTIONS)
        ));
        $form->addElement('select', 'reportid', $label('reports'), $this->_customdata['reports']);
        $form->disabledIf('reportid', 'actiontype', 'neq', 'report');
        $form->addElement('text', 'recipient', $label('automationrecipientfield'));
        $form->setType('recipient', PARAM_INT);
        $form->disabledIf('recipient', 'actiontype', 'neq', 'notify');
        $form->addElement('select', 'intervalvalue', $label('reportschedule'), [
            HOURSECS => $label('schedulehourly'), DAYSECS => $label('scheduledaily'), WEEKSECS => $label('scheduleweekly'),
        ]);
        $form->addElement('advcheckbox', 'enabled', $label('enabled'));
        $this->add_action_buttons();
    }
}
