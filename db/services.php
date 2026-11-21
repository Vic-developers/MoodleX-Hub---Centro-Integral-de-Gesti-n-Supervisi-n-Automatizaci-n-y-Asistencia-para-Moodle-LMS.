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

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_moodlexhub_get_course_report' => [
        'classname' => \local_moodlexhub\external\get_course_report::class,
        'description' => 'Read an aggregate report in an authorized course.',
        'type' => 'read',
        'ajax' => false,
        'capabilities' => 'local/moodlexhub:useapi, local/moodlexhub:exportreports',
    ],
    'local_moodlexhub_get_ticket_status' => [
        'classname' => \local_moodlexhub\external\get_ticket_status::class,
        'description' => 'Read the status and priority of an authorized support ticket.',
        'type' => 'read',
        'ajax' => false,
        'capabilities' => 'local/moodlexhub:useapi',
    ],
];

$services = [
    'MoodleX Hub API' => [
        'functions' => ['local_moodlexhub_get_course_report', 'local_moodlexhub_get_ticket_status'],
        'restrictedusers' => 1,
        'enabled' => 0,
        'shortname' => 'moodlexhubapi',
    ],
];
