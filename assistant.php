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

use local_moodlexhub\ai\assistant;
use local_moodlexhub\form\assistant_request;

require_login(null, false);
$test = (bool) optional_param('test', 0, PARAM_BOOL);
assistant::require_access($test);
$context = context_user::instance($USER->id);
$path = '/local/moodlexhub/assistant.php';
$url = new moodle_url($path, ['test' => (int) $test]);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_title($label('assistant'));
$PAGE->set_heading($label('assistant'));
$form = new assistant_request($url, ['testonly' => $test]);
$answer = '';
if ($data = $form->get_data()) {
    if ($test !== ($data->mode === 'connection')) {
        throw new invalid_parameter_exception();
    }
    $answer = (new assistant())->request($data->mode, $data->question ?? '', $data->reference ?? 0, (bool) $data->consent);
}

$history = [];
foreach ($DB->get_records(assistant::TABLE, ['userid' => $USER->id], 'timecreated DESC, id DESC', '*', 0, 20) as $entry) {
    $history[] = ['cells' => [
        userdate($entry->timecreated), $label('assistantmode_' . $entry->mode),
        $label('assistantstatus_' . $entry->status), $entry->characters, $entry->inputtokens ?? '—', $entry->outputtokens ?? '—',
    ]];
}
$canmanage = has_capability(assistant::MANAGE, context_system::instance());

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/assistant', [
    'dashboardurl' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
    'testurl' => $canmanage && !$test ? (new moodle_url($path, ['test' => 1]))->out(false) : null,
    'providerurl' => $canmanage && has_capability('moodle/site:config', context_system::instance())
        ? (new moodle_url('/' . $CFG->admin . '/settings.php', ['section' => 'aiprovider']))->out(false) : null,
    'answer' => $answer,
    'history' => [
        'title' => $label('assistanthistory'),
        'headings' => [get_string('date'), $label('assistantmode'), $label('ticketstatus'),
            $label('assistantcharacters'), $label('assistantinputtokens'), $label('assistantoutputtokens')],
        'hasrows' => (bool) $history,
        'rows' => $history,
    ],
]);
$form->display();
echo $OUTPUT->footer();
