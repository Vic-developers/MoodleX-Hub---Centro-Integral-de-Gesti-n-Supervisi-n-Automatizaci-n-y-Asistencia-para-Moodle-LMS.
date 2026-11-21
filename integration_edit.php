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

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_moodlexhub\form\webhook_endpoint;
use local_moodlexhub\integration\webhooks;

require_login(null, false);
webhooks::require_management();
if (!webhooks::is_configured()) {
    throw new moodle_exception('webhookkeymissing', 'local_moodlexhub');
}
$id = optional_param('endpointid', 0, PARAM_INT);
$endpoint = $id ? $DB->get_record(webhooks::ENDPOINTS, ['id' => $id], '*', MUST_EXIST) : null;
$url = new moodle_url('/local/moodlexhub/integration_edit.php', ['endpointid' => $id]);
$returnurl = new moodle_url('/local/moodlexhub/integrations.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$title = get_string($id ? 'webhookedit' : 'webhooknew', 'local_moodlexhub');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$form = new webhook_endpoint($url, ['existing' => $endpoint->url ?? null]);
if ($endpoint) {
    $form->set_data([
        'endpointid' => $id, 'name' => $endpoint->name, 'url' => $endpoint->url,
        'topic' => $endpoint->topic, 'enabled' => $endpoint->enabled,
    ]);
}
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    if (!in_array($data->topic, webhooks::TOPICS, true)) {
        throw new invalid_parameter_exception();
    }
    $secret = $data->secret ?? '';
    $cipher = $secret === '' ? $endpoint->cipher : webhooks::encrypt($secret);
    if ($secret !== '') {
        sodium_memzero($secret);
        unset($data->secret);
    }
    $save = (object) [
        'name' => $data->name, 'url' => $data->url, 'topic' => $data->topic, 'cipher' => $cipher,
        'enabled' => (int) !empty($data->enabled), 'timemodified' => time(),
    ];
    if ($endpoint) {
        $save->id = $id;
        $DB->update_record(webhooks::ENDPOINTS, $save);
    } else {
        $DB->insert_record(webhooks::ENDPOINTS, $save);
    }
    redirect($returnurl);
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
