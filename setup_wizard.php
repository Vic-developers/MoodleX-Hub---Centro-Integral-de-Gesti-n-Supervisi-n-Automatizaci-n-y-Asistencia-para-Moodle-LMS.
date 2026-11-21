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

use core\task\manager;
use local_moodlexhub\module_config;
use local_moodlexhub\task\schedule_reports;

require_login(null, false);
$system = context_system::instance();
require_capability('local/moodlexhub:manageconfiguration', $system);
$steps = ['roles', 'cron', 'modules'];
$step = optional_param('step', 'roles', PARAM_ALPHA);
if (!in_array($step, $steps, true)) {
    throw new invalid_parameter_exception();
}
$profiles = [
    'teaching' => ['teacher', 'student', 'quality', 'feedback'],
    'management' => ['courses', 'users', 'quality', 'monitoring', 'reports'],
    'support' => ['support', 'monitoring'],
];
$path = '/local/moodlexhub/setup_wizard.php';
$url = new moodle_url($path, ['step' => $step]);
$label = static fn(string $key, $a = null): string => get_string($key, 'local_moodlexhub', $a);
if (data_submitted()) {
    require_sesskey();
    $profile = required_param('profile', PARAM_ALPHA);
    if ($step !== 'modules' || !isset($profiles[$profile])) {
        throw new invalid_parameter_exception();
    }
    foreach ($profiles[$profile] as $module) {
        set_config('enable_' . $module, 1, 'local_moodlexhub');
    }
    redirect($url, $label('setupprofileapplied'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_context($system);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($label('setupwizard'));
$PAGE->set_heading($label('setupwizard'));
$checks = [];
$capabilities = [
    'manager' => 'local/moodlexhub:coordinatefollowup',
    'editingteacher' => 'local/moodlexhub:managefollowup',
    'user' => 'local/moodlexhub:viewstudent',
];
foreach ($capabilities as $archetype => $capability) {
    $roles = get_archetype_roles($archetype);
    foreach ($roles as $role) {
        $permission = $DB->get_field('role_capabilities', 'permission', [
            'roleid' => $role->id, 'contextid' => $system->id, 'capability' => $capability,
        ]);
        $checks[] = [
            'name' => role_get_name($role, $system) . ' · ' . get_capability_string($capability),
            'status' => $label((int) $permission === CAP_ALLOW ? 'setuproleallowed' : 'setuprolereview'),
        ];
    }
}
$task = manager::get_scheduled_task(schedule_reports::class);
$last = $task ? $task->get_last_run_time() : 0;
$cronresult = !$last ? $label('setupcronnever') : $label(
    $last < time() - HOURSECS ? 'setupcronstale' : 'setupcronrecent',
    userdate($last),
);
$modulechecks = [];
foreach (array_unique(array_merge(...array_values($profiles))) as $module) {
    $modulechecks[] = [
        'name' => $label(module_config::LABELS[$module]),
        'status' => $label(module_config::enabled($module) ? 'setupmoduleactive' : 'disabled'),
    ];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/setup_wizard', [
    'home' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
    'steps' => array_map(static fn(string $name): array => [
        'name' => $label('setupwizard' . $name),
        'url' => (new moodle_url($path, ['step' => $name]))->out(false),
        'selected' => $step === $name,
    ], $steps),
    'roles' => $step === 'roles',
    'rolechecks' => $checks,
    'roleurl' => has_capability('moodle/role:manage', $system) ? (new moodle_url('/admin/roles/manage.php'))->out(false) : '',
    'cron' => $step === 'cron',
    'cronresult' => $cronresult,
    'taskurl' => has_capability('moodle/site:config', $system) ? (new moodle_url('/admin/tool/task/scheduledtasks.php'))->out(false) : '',
    'modules' => $step === 'modules',
    'modulechecks' => $modulechecks,
    'moduleurl' => (new moodle_url('/admin/settings.php', ['section' => 'local_moodlexhub_modules']))->out(false),
    'profiles' => array_map(
        static fn(string $value): array => ['value' => $value, 'label' => $label('setupprofile_' . $value)],
        array_keys($profiles),
    ),
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'next' => (new moodle_url($path, ['step' => $step === 'roles' ? 'cron' : 'modules']))->out(false),
]);
echo $OUTPUT->footer();
