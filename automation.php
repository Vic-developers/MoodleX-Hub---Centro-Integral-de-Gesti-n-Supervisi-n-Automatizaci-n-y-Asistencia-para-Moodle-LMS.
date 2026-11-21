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

use local_moodlexhub\automation\engine;

require_login(null, false);
\local_moodlexhub\module_config::require_enabled('automation');
$context = context_system::instance();
require_capability(engine::CAPABILITY, $context);
$id = optional_param('ruleid', 0, PARAM_INT);
$rule = $id ? $DB->get_record(engine::RULES, ['id' => $id, 'userid' => $USER->id], '*', MUST_EXIST) : null;
$page = max(0, min(intdiv(PHP_INT_MAX, 25), optional_param('page', 0, PARAM_INT)));
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$base = new moodle_url('/local/moodlexhub/automation.php');
$url = $rule ? new moodle_url($base, ['ruleid' => $id]) : $base;
if ($rule && data_submitted() && optional_param('run', 0, PARAM_BOOL)) {
    require_sesskey();
    $service = new engine();
    $service->require_scope($rule);
    $service->enqueue($id, -time());
    redirect($url);
}
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('automation'));
$PAGE->set_heading($label('automation'));
$conditions = $rule ? ['ruleid' => $id] : ['userid' => $USER->id];
$table = $rule ? engine::RUNS : engine::RULES;
$total = $DB->count_records($table, $conditions);
$entries = [];
foreach ($DB->get_records($table, $conditions, 'timemodified DESC, id DESC', '*', $page * 25, 25) as $entry) {
    if ($rule) {
        $entries[] = ['cells' => [userdate($entry->timecreated), $label('import_' . $entry->status), $entry->resultid ?? '—']];
    } else {
        $entries[] = [
            'title' => $entry->title,
            'status' => $label($entry->enabled ? 'enabled' : 'disabled'),
            'url' => (new moodle_url($base, ['ruleid' => $entry->id]))->out(false),
        ];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/automation', [
    'dashboardurl' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
    'listurl' => $base->out(false),
    'editurl' => (new moodle_url('/local/moodlexhub/automation_edit.php', ['ruleid' => (int) $id]))->out(false),
    'joburl' => $url->out(false),
    'sesskey' => sesskey(),
    'entries' => !$rule ? $entries : [],
    'hasentries' => !$rule && (bool) $entries,
    'rule' => $rule ? [
        'title' => $rule->title,
        'description' => $rule->description,
        'status' => $label($rule->enabled ? 'enabled' : 'disabled'),
        'condition' => $label('automationcondition_' . $rule->conditiontype),
        'action' => $label('automationaction_' . $rule->actiontype),
        'next' => $rule->nextdue ? userdate($rule->nextdue) : get_string('never'),
        'runs' => [
            'title' => $label('monitorhistory'),
            'headings' => [get_string('date'), $label('ticketstatus'), $label('automationresultid')],
            'rows' => $entries,
            'hasrows' => (bool) $entries,
        ],
    ] : null,
]);
echo $OUTPUT->paging_bar($total, $page, 25, $url);
echo $OUTPUT->footer();
