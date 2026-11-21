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

use local_moodlexhub\quality\auditor;
use local_moodlexhub\quality\category_template;

require_login(null, false);
$course = get_course(required_param('id', PARAM_INT));
$auditor = new auditor();
$context = $auditor->require_access($course);
$label = fn(string $key, $a = null): string => get_string($key, 'local_moodlexhub', $a);
$url = new moodle_url('/local/moodlexhub/quality.php', ['id' => $course->id]);
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('quality'));
$PAGE->set_heading($label('quality'));

if (data_submitted() && optional_param('run', 0, PARAM_BOOL)) {
    require_sesskey();
    $auditid = $auditor->run($course);
    redirect(
        new moodle_url($url, ['audit' => $auditid]),
        $label('auditcompleted'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$page = max(0, min(intdiv(PHP_INT_MAX, 10), optional_param('page', 0, PARAM_INT)));
$auditid = optional_param('audit', 0, PARAM_INT);
$conditions = ['courseid' => $course->id];
$total = $DB->count_records(auditor::TABLE, $conditions);
$history = $DB->get_records(auditor::TABLE, $conditions, 'timecreated DESC, id DESC', 'id,score,timecreated', $page * 10, 10);
$audit = $auditid ? $DB->get_record(auditor::TABLE, $conditions + ['id' => $auditid], '*', MUST_EXIST) : null;
$scoretext = fn($score): string => $score === null ? $label('notapplicable') : $label('qualityscore', $score);
$historyrows = [];
foreach ($history as $entry) {
    $historyrows[] = [
        'date' => userdate($entry->timecreated),
        'score' => $scoretext($entry->score),
        'url' => (new moodle_url($url, ['audit' => $entry->id, 'page' => $page]))->out(false),
    ];
}
$findings = [];
if ($audit) {
    foreach (json_decode($audit->findings, true, flags: JSON_THROW_ON_ERROR) as $finding) {
        $rule = $finding['rule'];
        if (!isset(auditor::WEIGHTS[$rule])) {
            continue;
        }
        $status = match (true) {
            $finding['count'] === null => $label('notapplicable'),
            $finding['count'] === 0 => $label('qualitypassed'),
            default => $label('qualityproblems', $finding['count']),
        };
        $findings[] = [
            'rule' => $label('rule_' . $rule),
            'weight' => $finding['weight'],
            'status' => $status,
            'recommendation' => $finding['count'] > 0 ? $label('rule_' . $rule . '_recommendation') : '',
        ];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/quality', [
    'templateurl' => has_capability(category_template::CAPABILITY, context_coursecat::instance($course->category))
        ? (new moodle_url('/local/moodlexhub/quality_template.php', ['category' => $course->category]))->out(false) : null,
    'course' => html_to_text(format_string($course->fullname, true, ['context' => $context]), 0, false),
    'courseurl' => (new moodle_url('/local/moodlexhub/course.php', ['id' => $course->id]))->out(false),
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'history' => $historyrows,
    'hashistory' => (bool) $history,
    'viewaudit' => $audit ? [
        'date' => userdate($audit->timecreated),
        'score' => $scoretext($audit->score),
        'findings' => $findings,
    ] : null,
]);
echo $OUTPUT->paging_bar($total, $page, 10, $url);
echo $OUTPUT->footer();
