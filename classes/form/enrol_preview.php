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

use stored_file;

/**
 * Prepare a CSV enrolment preview.
 */
final class enrol_preview extends \moodleform {
    #[\Override]
    protected function definition(): void {
        $form = $this->_form;
        $label = fn(string $key): string => get_string($key, 'local_moodlexhub');
        $form->addElement('hidden', 'id', $this->_customdata['courseid']);
        $form->setType('id', PARAM_INT);
        $form->addElement('select', 'instanceid', $label('importmethod'), $this->_customdata['methods']);
        $form->addElement('select', 'roleid', $label('importrole'), $this->_customdata['roles']);
        $form->addElement('filepicker', 'upload', $label('importfile'), null, [
            'accepted_types' => ['.csv'],
            'maxbytes' => $this->_customdata['maxbytes'],
        ]);
        $form->addRule('upload', get_string('required'), 'required', null, 'client');
        $this->add_action_buttons(false, $label('importpreview'));
    }

    /**
     * Get the one CSV file declared on the submitted preview form.
     *
     * @return stored_file
     */
    public function get_csv(): stored_file {
        $files = $this->get_draft_files('upload');
        if (!$files || count($files) !== 1) {
            throw new \moodle_exception('importbadfile', 'local_moodlexhub');
        }
        $file = reset($files);
        if (
            $file->get_filesize() > $this->_customdata['maxbytes']
            || !str_ends_with(strtolower($file->get_filename()), '.csv')
        ) {
            throw new \moodle_exception('importbadfile', 'local_moodlexhub');
        }
        return $file;
    }
}
