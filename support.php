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

use local_moodlexhub\support\tickets;

require_login(null, false);
\local_moodlexhub\module_config::require_enabled('support');
$context = context_system::instance();
if (!has_any_capability([tickets::USE, tickets::MANAGE, tickets::RESPOND], $context)) {
    require_capability(tickets::USE, $context);
}
$manage = has_capability(tickets::MANAGE, $context);
$respond = has_capability(tickets::RESPOND, $context);
$use = has_capability(tickets::USE, $context);
$status = optional_param('status', '', PARAM_ALPHA);
$search = trim(optional_param('search', '', PARAM_TEXT));
$download = optional_param('download', '', PARAM_ALPHA);
if ($status && !in_array($status, tickets::STATUSES, true)) {
    throw new invalid_parameter_exception();
}
$page = max(0, min(intdiv(PHP_INT_MAX, 25), optional_param('page', 0, PARAM_INT)));
$path = '/local/moodlexhub/support.php';
$url = new moodle_url($path, ['search' => $search, 'status' => $status]);
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('support'));
$PAGE->set_heading($label('support'));

$where = ['1 = 1'];
$params = [];
if (!$manage) {
    $scope = [];
    if ($use) {
        $scope[] = 'userid = :requester';
        $params['requester'] = $USER->id;
    }
    if ($respond) {
        $scope[] = 'assignedto = :assignee';
        $params['assignee'] = $USER->id;
    }
    $where[] = '(' . implode(' OR ', $scope) . ')';
}
if ($search !== '') {
    $where[] = $DB->sql_like('subject', ':search', false);
    $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
}
if ($status !== '') {
    $where[] = 'status = :status';
    $params['status'] = $status;
}

$select = implode(' AND ', $where);
$formatrow = fn($ticket): array => [
    'subject' => $ticket->subject,
    'status' => $label('ticketstatus_' . $ticket->status),
    'priority' => $label('ticketpriority_' . $ticket->priority),
    'date' => userdate($ticket->timemodified),
];
if ($download === 'csv') {
    require_all_capabilities([tickets::MANAGE, 'local/moodlexhub:exportreports'], $context);
    $columns = [
        'subject' => $label('ticketsubject'), 'status' => $label('ticketstatus'), 'priority' => $label('ticketpriority'),
        'date' => $label('ticketupdated'),
    ];
    $records = $DB->get_recordset_select(tickets::TABLE, $select, $params, 'timemodified DESC, id DESC');
    $iterator = (function () use ($records, $formatrow): Generator {
        try {
            foreach ($records as $ticket) {
                yield $formatrow($ticket);
            }
        } finally {
            $records->close();
        }
    })();
    \core\dataformat::download_data('moodlexhub-support-' . gmdate('Ymd-His'), 'csv', $columns, $iterator);
    exit;
}
$total = $DB->count_records_select(tickets::TABLE, $select, $params);
$tickets = [];
foreach ($DB->get_records_select(tickets::TABLE, $select, $params, 'timemodified DESC, id DESC', '*', $page * 25, 25) as $ticket) {
    $tickets[] = $formatrow($ticket) + ['url' => (new moodle_url('/local/moodlexhub/ticket.php', ['id' => $ticket->id]))->out(false)];
}
$statuses = [['id' => '', 'name' => $label('ticketallstatuses'), 'selected' => !$status]];
foreach (tickets::STATUSES as $state) {
    $statuses[] = ['id' => $state, 'name' => $label('ticketstatus_' . $state), 'selected' => $state === $status];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/support', [
    'dashboardurl' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
    'createurl' => $use ? (new moodle_url('/local/moodlexhub/ticket_create.php'))->out(false) : null,
    'knowledgeurl' => (new moodle_url('/local/moodlexhub/knowledge.php'))->out(false),
    'exporturl' => $manage && has_capability('local/moodlexhub:exportreports', $context)
        ? (new moodle_url($url, ['download' => 'csv']))->out(false) : null,
    'action' => (new moodle_url($path))->out(false),
    'search' => $search,
    'statuses' => $statuses,
    'tickets' => $tickets,
    'hastickets' => (bool) $tickets,
]);
echo $OUTPUT->paging_bar($total, $page, 25, $url);
echo $OUTPUT->footer();
