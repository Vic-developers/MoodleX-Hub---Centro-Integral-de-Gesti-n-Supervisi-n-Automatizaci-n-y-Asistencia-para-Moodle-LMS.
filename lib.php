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

/**
 * Serve support attachments only to users who can access the corresponding ticket.
 *
 * @param stdClass $course Moodle course record.
 * @param cm_info|null $cm Moodle course module.
 * @param context $context File context.
 * @param string $filearea File area.
 * @param array $args Path arguments.
 * @param bool $forcedownload Whether the client requested a download.
 * @param array $options File serving options.
 * @return bool False when access is denied or a file is missing.
 */
function local_moodlexhub_pluginfile(
    $course,
    $cm,
    context $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    global $CFG, $DB, $USER;

    if (
        !$context instanceof context_system || $filearea !== \local_moodlexhub\support\tickets::FILEAREA || count($args) !== 2
        || !ctype_digit((string) $args[0])
    ) {
        return false;
    }
    require_login(null, false);
    $ticket = $DB->get_record(\local_moodlexhub\support\tickets::TABLE, ['id' => (int) $args[0]]);
    if (!$ticket || !(new \local_moodlexhub\support\tickets())->can_access($ticket, $USER->id)) {
        return false;
    }
    $file = get_file_storage()->get_file($context->id, 'local_moodlexhub', $filearea, $ticket->id, '/', $args[1]);
    if (!$file || $file->is_directory()) {
        return false;
    }
    require_once($CFG->libdir . '/filelib.php');
    send_stored_file($file, 0, 0, true, $options);
    return true;
}

/**
 * Add the dashboard to Moodle's global navigation for eligible users.
 *
 * @param global_navigation $navigation Navigation tree.
 */
function local_moodlexhub_extend_navigation(global_navigation $navigation): void {
    if (!isloggedin() || isguestuser() || !has_capability('local/moodlexhub:viewdashboard', context_system::instance())) {
        return;
    }
    $navigation->add(
        get_string('pluginname', 'local_moodlexhub'),
        new moodle_url('/local/moodlexhub/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_moodlexhub',
    );
}

/**
 * Add the course diagnostics entry to Moodle's course navigation.
 *
 * @param navigation_node $navigation Course navigation.
 * @param stdClass $course Moodle course record.
 * @param context_course $context Course context.
 */
function local_moodlexhub_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context,
): void {
    if (!isloggedin() || isguestuser() || $course->id == SITEID) {
        return;
    }
    if (\local_moodlexhub\module_config::enabled('courses') && has_capability(\local_moodlexhub\course\catalog::VIEW, $context)) {
        $navigation->add(
            get_string('courseoverview', 'local_moodlexhub'),
            new moodle_url('/local/moodlexhub/course.php', ['id' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_moodlexhub_course',
        );
    }
    foreach (['teacher' => 'teacherpanel', 'analytics' => 'courseanalytics'] as $view => $name) {
        if (
            \local_moodlexhub\module_config::enabled($view)
            && has_all_capabilities(['local/moodlexhub:view' . $view, 'moodle/course:viewparticipants'], $context)
        ) {
            $navigation->add(
                get_string($name, 'local_moodlexhub'),
                new moodle_url('/local/moodlexhub/academic.php', ['courseid' => $course->id, 'view' => $view]),
                navigation_node::TYPE_CUSTOM,
                null,
                'local_moodlexhub_' . $view,
            );
        }
    }
}
