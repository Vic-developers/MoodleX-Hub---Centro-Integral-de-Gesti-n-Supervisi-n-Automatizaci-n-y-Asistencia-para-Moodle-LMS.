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

use local_moodlexhub\integration\webhooks;

require_login(null, false);
webhooks::require_management();
$context = context_system::instance();
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$url = new moodle_url('/local/moodlexhub/integrations.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('integrations'));
$PAGE->set_heading($label('integrations'));
$page = max(0, min(intdiv(PHP_INT_MAX, 25), optional_param('page', 0, PARAM_INT)));
$endpointid = optional_param('endpointid', 0, PARAM_INT);
$conditions = $endpointid ? ['endpointid' => $endpointid] : [];
$editurl = new moodle_url('/local/moodlexhub/integration_edit.php');
$endpoints = [];
$names = $DB->get_records_menu(webhooks::ENDPOINTS, null, 'name ASC', 'id,name');
$configured = webhooks::is_configured();
foreach ($DB->get_records(webhooks::ENDPOINTS, null, 'name ASC', 'id,name,topic,enabled') as $item) {
    $endpoints[] = [
        'name' => $item->name, 'topic' => $label('webhooktopic_' . $item->topic),
        'status' => $label($item->enabled ? 'enabled' : 'disabled'),
        'editurl' => $configured ? (new moodle_url($editurl, ['endpointid' => $item->id]))->out(false) : null,
        'logsurl' => (new moodle_url($url, ['endpointid' => $item->id]))->out(false),
    ];
}
$history = [];
foreach ($DB->get_records(webhooks::LOG, $conditions, 'timemodified DESC, id DESC', '*', $page * 25, 25) as $entry) {
    $history[] = ['cells' => [
        userdate($entry->timecreated), $names[$entry->endpointid] ?? '—', $label('webhooktopic_' . $entry->topic),
        $label('import_' . $entry->status), $entry->attempts, $entry->httpcode ?: '—',
    ]];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/integrations', [
    'dashboardurl' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
    'eligible' => $configured,
    'enabled' => (bool) get_config('local_moodlexhub', 'webhooksenabled'),
    'newurl' => $editurl->out(false),
    'servicesurl' => (new moodle_url('/' . $CFG->admin . '/settings.php', ['section' => 'externalservices']))->out(false),
    'endpoints' => $endpoints,
    'hasendpoints' => (bool) $endpoints,
    'history' => [
        'title' => $label('webhookhistory'),
        'headings' => [get_string('date'), get_string('name'), $label('webhooktopic'), $label('ticketstatus'),
            $label('importattempts'), $label('webhookhttp')],
        'rows' => $history,
        'hasrows' => (bool) $history,
    ],
]);
echo $OUTPUT->paging_bar($DB->count_records(webhooks::LOG, $conditions), $page, 25, new moodle_url($url, $conditions));
echo $OUTPUT->footer();
