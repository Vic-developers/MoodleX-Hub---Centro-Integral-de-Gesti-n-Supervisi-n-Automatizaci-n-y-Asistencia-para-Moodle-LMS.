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

use local_moodlexhub\course\catalog;
use local_moodlexhub\support\knowledge;
use local_moodlexhub\support\tickets;

require_login(null, false);
$context = context_system::instance();
require_capability('local/moodlexhub:viewdashboard', $context);
$query = core_text::substr(trim(required_param('q', PARAM_TEXT)), 0, 200);
$path = '/local/moodlexhub/search.php';
$url = new moodle_url($path, ['q' => $query]);
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('dashboardsearch'));
$PAGE->set_heading($label('dashboardsearch'));

$groups = [];
if ($query !== '') {
    $listall = \local_moodlexhub\module_config::enabled('courses') && has_capability(catalog::VIEW, $context);
    $coursehits = [];
    $courses = $listall ? (new catalog())->matching($query, 0, 0) : enrol_get_my_courses();
    $needle = core_text::strtolower($query);
    foreach ($courses as $course) {
        if (!$listall && !str_contains(core_text::strtolower($course->fullname . ' ' . $course->shortname), $needle)) {
            continue;
        }
        $coursehits[] = [
            'name' => html_to_text(format_string($course->fullname, true, ['context' => context_course::instance($course->id)]), 0, false),
            'url' => (new moodle_url($listall ? '/local/moodlexhub/course.php' : '/course/view.php', ['id' => $course->id]))->out(false),
        ];
        if (count($coursehits) === 10) {
            break;
        }
    }
    $groups[] = [
        'name' => $label('courses'), 'items' => $coursehits, 'hasitems' => (bool) $coursehits,
        'more' => (new moodle_url($listall ? '/local/moodlexhub/courses.php' : '/my/courses.php', $listall ? ['search' => $query] : []))->out(false),
    ];

    $sample = '%' . $DB->sql_like_escape($query) . '%';
    if (
        \local_moodlexhub\module_config::enabled('support')
        && has_any_capability([tickets::USE, tickets::RESPOND, tickets::MANAGE, knowledge::MANAGE], $context)
    ) {
        $articlehits = [];
        $where = $DB->sql_like('title', ':query', false)
            . (has_capability(knowledge::MANAGE, $context) ? '' : ' AND published = 1');
        foreach ($DB->get_records_select(knowledge::TABLE, $where, ['query' => $sample], 'title ASC,id ASC', 'id,title', 0, 10) as $item) {
            $articlehits[] = ['name' => $item->title,
                'url' => (new moodle_url('/local/moodlexhub/knowledge.php', ['id' => $item->id]))->out(false)];
        }
        $groups[] = [
            'name' => $label('knowledge'), 'items' => $articlehits, 'hasitems' => (bool) $articlehits,
            'more' => (new moodle_url('/local/moodlexhub/knowledge.php', ['search' => $query]))->out(false),
        ];
    }

    if (\local_moodlexhub\module_config::enabled('support') && has_any_capability([tickets::USE, tickets::RESPOND, tickets::MANAGE], $context)) {
        $tickethits = [];
        $params = ['query' => $sample];
        $where = $DB->sql_like('subject', ':query', false);
        if (!has_capability(tickets::MANAGE, $context)) {
            $clauses = [];
            if (has_capability(tickets::USE, $context)) {
                $clauses[] = 'userid = :owner';
                $params['owner'] = $USER->id;
            }
            if (has_capability(tickets::RESPOND, $context)) {
                $clauses[] = 'assignedto = :agent';
                $params['agent'] = $USER->id;
            }
            $where .= ' AND (' . implode(' OR ', $clauses) . ')';
        }
        foreach ($DB->get_records_select(tickets::TABLE, $where, $params, 'timemodified DESC,id DESC', 'id,subject', 0, 10) as $item) {
            $tickethits[] = ['name' => $item->subject,
                'url' => (new moodle_url('/local/moodlexhub/ticket.php', ['id' => $item->id]))->out(false)];
        }
        $groups[] = [
            'name' => $label('ticketlist'), 'items' => $tickethits, 'hasitems' => (bool) $tickethits,
            'more' => (new moodle_url('/local/moodlexhub/support.php', ['search' => $query]))->out(false),
        ];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/search', [
    'dashboardurl' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
    'action' => (new moodle_url($path))->out(false),
    'query' => $query,
    'groups' => $groups,
]);
echo $OUTPUT->footer();
