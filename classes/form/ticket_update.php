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

use local_moodlexhub\support\tickets;

/**
 * Form for a permitted reply or change to a ticket.
 */
final class ticket_update extends \moodleform {
    #[\Override]
    protected function definition(): void {
        $form = $this->_form;
        $label = fn(string $key): string => get_string($key, 'local_moodlexhub');
        $form->addElement('hidden', 'id');
        $form->setType('id', PARAM_INT);
        $form->addElement('select', 'status', $label('ticketstatus'), $this->_customdata['states']);
        if ($this->_customdata['manage']) {
            $form->addElement('select', 'priority', $label('ticketpriority'), $this->_customdata['priorities']);
            $form->addElement('autocomplete', 'assignedto', $label('ticketassignee'), $this->_customdata['agents']);
        }
        $form->addElement('textarea', 'body', $label('ticketreply'), ['rows' => 6, 'maxlength' => 10000]);
        $form->setType('body', PARAM_RAW_TRIMMED);
        $form->addRule('body', get_string('maximumchars', '', 10000), 'maxlength', 10000, 'server');
        $this->add_action_buttons(false);
    }
}
