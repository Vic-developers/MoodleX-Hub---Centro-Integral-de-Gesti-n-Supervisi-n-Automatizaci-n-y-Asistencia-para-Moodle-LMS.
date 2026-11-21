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

namespace local_moodlexhub\output;

use context_course;
use context_system;
use local_moodlexhub\module_config;
use moodle_url;
use renderer_base;
use templatable;

/**
 * Data for the dashboard template.
 */
final class dashboard implements templatable {
    /** @var string[] Personal dashboard preferences. */
    public const PREFERENCES = ['showcourses', 'showrecent', 'shownotifications'];

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        global $CFG, $USER;

        $system = context_system::instance();
        $label = fn(string $key): string => get_string($key, 'local_moodlexhub');
        $setting = get_config('local_moodlexhub', 'dashboardcourses');
        $showcourses = ($setting === false || (bool) $setting) && (bool) get_user_preferences('local_moodlexhub_showcourses', 1);
        $showrecent = (bool) get_user_preferences('local_moodlexhub_showrecent', 1);
        $shownotifications = (bool) get_user_preferences('local_moodlexhub_shownotifications', 1);
        $courses = [];
        $recent = [];
        if ($showcourses) {
            foreach (enrol_get_my_courses(null, 'visible DESC, sortorder ASC', 5) as $course) {
                $context = context_course::instance($course->id);
                $courses[] = [
                    'name' => html_to_text(format_string($course->fullname, true, ['context' => $context]), 0, false),
                    'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                ];
            }
        }
        if ($showrecent) {
            require_once($CFG->dirroot . '/course/lib.php');
            foreach (course_get_recent_courses($USER->id, 5) as $course) {
                $context = context_course::instance($course->id);
                $recent[] = [
                    'name' => html_to_text(format_string($course->fullname, true, ['context' => $context]), 0, false),
                    'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    'date' => userdate($course->timeaccess),
                ];
            }
        }
        $canmonitor = module_config::enabled('monitoring') && has_capability(monitoring::CAPABILITY, $system);
        $capabilities = [
            'student' => ['local/moodlexhub:viewstudent', '/student.php'],
            'reports' => ['local/moodlexhub:exportreports', '/reports.php'],
            'automation' => ['local/moodlexhub:manageautomation', '/automation.php'],
            'monitoring' => [monitoring::CAPABILITY, '/monitoring.php'],
            'courses' => [\local_moodlexhub\course\catalog::VIEW, '/courses.php'],
        ];
        $navigation = [];
        foreach ($capabilities as $key => [$capability, $path]) {
            if (module_config::enabled($key) && has_capability($capability, $system)) {
                $navigation[] = [
                    'label' => $label(module_config::LABELS[$key]), 'url' => (new moodle_url('/local/moodlexhub' . $path))->out(false),
                ];
            }
        }
        foreach (['teacher' => 'teacherpanel', 'analytics' => 'courseanalytics'] as $view => $key) {
            if (
                module_config::enabled($view) && (has_capability('local/moodlexhub:view' . $view, $system)
                || get_user_capability_course('local/moodlexhub:view' . $view, null, true, '', '', 1))
            ) {
                $navigation[] = ['label' => $label($key), 'url' => (new moodle_url(
                    '/local/moodlexhub/academic.php',
                    ['view' => $view],
                ))->out(false)];
            }
        }
        if (
            module_config::enabled('support') && has_any_capability([
            'local/moodlexhub:usesupport', 'local/moodlexhub:managetickets', 'local/moodlexhub:respondtickets',
            ], $system)
        ) {
            $navigation[] = ['label' => $label('support'), 'url' => (new moodle_url('/local/moodlexhub/support.php'))->out(false)];
        }
        if (module_config::enabled('users') && has_all_capabilities(['local/moodlexhub:viewusers', 'moodle/site:viewuseridentity'], $system)) {
            $navigation[] = ['label' => $label('userdirectory'), 'url' => (new moodle_url('/local/moodlexhub/users.php'))->out(false)];
        }
        if (get_config('local_moodlexhub', 'assistantenabled') && has_capability('local/moodlexhub:useassistant', $system)) {
            $navigation[] = ['label' => $label('assistant'), 'url' => (new moodle_url('/local/moodlexhub/assistant.php'))->out(false)];
        }
        if (
            module_config::enabled('integrations')
            && has_all_capabilities(['local/moodlexhub:manageintegrations', 'moodle/site:config'], $system)
        ) {
            $navigation[] = ['label' => $label('integrations'), 'url' => (new moodle_url('/local/moodlexhub/integrations.php'))->out(false)];
        }

        $langs = [];
        foreach (get_string_manager()->get_list_of_translations() as $code => $name) {
            $langs[] = ['label' => html_to_text($name, 0, false), 'code' => $code, 'selected' => $code === current_language()];
        }

        return [
            'navigation' => $navigation,
            'searchurl' => (new moodle_url('/local/moodlexhub/search.php'))->out(false),
            'homeurl' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
            'languages' => $langs,
            'haslanguages' => count($langs) > 1,
            'showcourses' => $showcourses,
            'courses' => $courses,
            'hascourses' => !empty($courses),
            'showrecent' => $showrecent,
            'recent' => $recent,
            'hasrecent' => !empty($recent),
            'shownotifications' => $shownotifications,
            'notificationcount' => $shownotifications ? \message_popup\api::count_unread_popup_notifications($USER->id) : null,
            'notificationurl' => (new moodle_url('/message/output/popup/notifications.php'))->out(false),
            'monitortile' => $canmonitor ? [
                'url' => (new moodle_url('/local/moodlexhub/monitoring.php'))->out(false),
                'failures' => $this->recent_failures(),
            ] : null,
            'preferencesurl' => (new moodle_url('/local/moodlexhub/preferences.php'))->out(false),
            'settingsurl' => has_capability('local/moodlexhub:manageconfiguration', context_system::instance())
                ? (new moodle_url('/' . $CFG->admin . '/settings.php', ['section' => 'local_moodlexhub_settings']))->out(false)
                : null,
            'mycoursesurl' => (new moodle_url('/my/courses.php'))->out(false),
        ];
    }

    /**
     * Count recorded MoodleX Hub task failures in the last 24 hours.
     *
     * @return int
     */
    private function recent_failures(): int {
        global $DB;
        return $DB->count_records_select(
            'task_log',
            'component = :component AND result <> 0 AND timestart >= :since',
            ['component' => 'local_moodlexhub', 'since' => time() - DAYSECS]
        );
    }
}
