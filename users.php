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

require_login(null, false);
\local_moodlexhub\module_config::require_enabled('users');
$context = context_system::instance();
require_all_capabilities(['local/moodlexhub:viewusers', 'moodle/site:viewuseridentity'], $context);
$search = trim(optional_param('search', '', PARAM_TEXT));
$filter = optional_param('filter', 'all', PARAM_ALPHA);
if (!in_array($filter, ['all', 'suspended', 'unenrolled', 'duplicates'], true)) {
    throw new invalid_parameter_exception();
}
$page = max(0, min(intdiv(PHP_INT_MAX, 30), optional_param('page', 0, PARAM_INT)));
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$path = '/local/moodlexhub/users.php';
$url = new moodle_url($path, ['search' => $search, 'filter' => $filter]);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('userdirectory'));
$PAGE->set_heading($label('userdirectory'));

[$query, $params] = users_search_sql($DB->sql_like_escape($search), 'u', USER_SEARCH_CONTAINS, ['username']);
$clauses = [$query];
switch ($filter) {
    case 'suspended':
        $clauses[] = 'u.suspended = 1';
        break;
    case 'unenrolled':
        $clauses[] = 'NOT EXISTS (SELECT 1 FROM {user_enrolments} ue WHERE ue.userid = u.id)';
        break;
    case 'duplicates':
        $clauses[] = 'u.email <> :noemail AND EXISTS (SELECT 1 FROM {user} possible
                         WHERE possible.deleted = 0 AND possible.id <> u.id AND LOWER(possible.email) = LOWER(u.email))';
        $params['noemail'] = '';
        break;
}
$where = implode(' AND ', $clauses);
$total = $DB->count_records_sql("SELECT COUNT(u.id) FROM {user} u WHERE $where", $params);
$namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$users = $DB->get_records_sql(
    "SELECT u.id, u.username, u.suspended, u.lastaccess, $namefields
       FROM {user} u WHERE $where ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC",
    $params,
    $page * 30,
    30,
);
$items = [];
foreach ($users as $user) {
    $items[] = [
        'name' => fullname($user, has_capability('moodle/site:viewfullnames', $context)),
        'username' => $user->username,
        'status' => $label($user->suspended ? 'directorysuspended' : 'directoryactive'),
        'date' => $user->lastaccess ? userdate($user->lastaccess) : get_string('never'),
        'profile' => has_capability('moodle/user:viewdetails', context_user::instance($user->id))
            ? (new moodle_url('/user/profile.php', ['id' => $user->id]))->out(false) : null,
        'logs' => has_capability('report/log:view', $context)
            ? (new moodle_url('/report/log/index.php', ['id' => SITEID, 'user' => $user->id, 'chooselog' => 1]))->out(false) : null,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/users', [
    'dashboardurl' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
    'action' => (new moodle_url($path))->out(false),
    'search' => $search,
    'filters' => array_map(fn(string $value): array => [
        'value' => $value, 'name' => $label('directoryfilter_' . $value), 'selected' => $filter === $value,
    ], ['all', 'suspended', 'unenrolled', 'duplicates']),
    'users' => $items,
    'hasusers' => (bool) $items,
    'duplicates' => $filter === 'duplicates',
]);
echo $OUTPUT->paging_bar($total, $page, 30, $url);
echo $OUTPUT->footer();
