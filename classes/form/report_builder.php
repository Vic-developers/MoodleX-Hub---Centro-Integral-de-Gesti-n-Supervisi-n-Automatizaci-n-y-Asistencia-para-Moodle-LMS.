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

use local_moodlexhub\report\builder;

/**
 * Save a report definition and its optional schedule.
 */
final class report_builder extends \moodleform {
    #[\Override]
    protected function definition(): void {
        $form = $this->_form;
        $label = fn(string $key): string => get_string($key, 'local_moodlexhub');
        $form->addElement('hidden', 'reportid');
        $form->setType('reportid', PARAM_INT);
        $form->addElement('text', 'title', $label('reporttitle'), ['maxlength' => 160]);
        $form->setType('title', PARAM_TEXT);
        $form->addRule('title', get_string('required'), 'required');
        $form->addRule('title', get_string('maximumchars', '', 160), 'maxlength', 160, 'server');
        $sources = array_combine(builder::SOURCES, array_map(fn($key) => $label('reportsource_' . $key), builder::SOURCES));
        $form->addElement('select', 'source', $label('reportsource'), $sources);
        $form->addElement('select', 'courseid', get_string('course'), $this->_customdata['courses']);
        $form->disabledIf('courseid', 'source', 'eq', 'tickets');
        $form->addElement('text', 'filtervalue', $label('reportfiltervalue'));
        $form->setType('filtervalue', PARAM_INT);
        $form->setDefault('filtervalue', 1);
        $form->addElement('select', 'intervalvalue', $label('reportschedule'), [
            0 => $label('schedulemanual'), DAYSECS => $label('scheduledaily'), WEEKSECS => $label('scheduleweekly'),
        ]);
        $this->add_action_buttons();
    }
}
