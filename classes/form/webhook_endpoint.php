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

use local_moodlexhub\integration\webhook_security;
use local_moodlexhub\integration\webhooks;

/**
 * Manage an authenticated webhook destination without redisplaying credentials.
 */
final class webhook_endpoint extends \moodleform {
    #[\Override]
    protected function definition(): void {
        $form = $this->_form;
        $label = fn(string $key): string => get_string($key, 'local_moodlexhub');
        $form->addElement('hidden', 'endpointid');
        $form->setType('endpointid', PARAM_INT);
        $form->addElement('text', 'name', get_string('name'), ['maxlength' => 160]);
        $form->setType('name', PARAM_TEXT);
        $form->addRule('name', get_string('required'), 'required');
        $form->addRule('name', get_string('maximumchars', '', 160), 'maxlength', 160, 'server');
        $form->addElement('text', 'url', $label('webhookurl'), ['maxlength' => 512]);
        $form->setType('url', PARAM_URL);
        $form->addRule('url', get_string('required'), 'required');
        $form->addElement('select', 'topic', $label('webhooktopic'), array_combine(
            webhooks::TOPICS,
            array_map(fn($key) => $label('webhooktopic_' . $key), webhooks::TOPICS),
        ));
        $form->addElement('password', 'secret', $label('webhooksecret'), ['maxlength' => 256, 'autocomplete' => 'new-password']);
        $form->setType('secret', PARAM_RAW);
        $form->addElement('advcheckbox', 'enabled', $label('enabled'));
        $this->add_action_buttons();
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!webhook_security::allowed_host($data['url']) || strlen($data['url']) > 512) {
            $errors['url'] = get_string('webhookbadurl', 'local_moodlexhub');
        }
        $secret = $data['secret'] ?? '';
        if ((!$this->_customdata['existing'] || $data['url'] !== $this->_customdata['existing']) && $secret === '') {
            $errors['secret'] = get_string('required');
        } else if ($secret !== '' && (strlen($secret) < 32 || strlen($secret) > 256)) {
            $errors['secret'] = get_string('webhooksecretlength', 'local_moodlexhub');
        }
        return $errors;
    }
}
