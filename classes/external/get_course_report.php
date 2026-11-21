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

namespace local_moodlexhub\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moodlexhub\event\api_read;
use local_moodlexhub\integration\rate_limit;
use local_moodlexhub\report\builder;

/**
 * Read a permitted course aggregate through Moodle Web Services.
 */
final class get_course_report extends external_api {
    /**
     * Declare the course report inputs for Moodle Web Services.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'source' => new external_value(PARAM_ALPHA, 'quality, storage, grades or learners'),
        ]);
    }

    /**
     * Build the requested aggregate for the authenticated actor.
     *
     * @param int $courseid Course ID.
     * @param string $source Approved source.
     * @return array
     */
    public static function execute(int $courseid, string $source): array {
        $args = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'source' => $source]);
        if (!in_array($args['source'], ['quality', 'storage', 'grades', 'learners'], true)) {
            throw new \invalid_parameter_exception();
        }
        $context = context_course::instance($args['courseid']);
        self::validate_context($context);
        require_capability('local/moodlexhub:useapi', \context_system::instance());
        rate_limit::check();
        $report = (new builder())->generate((object) [
            'source' => $args['source'], 'courseid' => $args['courseid'],
            'filtervalue' => $args['source'] === 'quality' ? 1 : 14, 'title' => '',
        ]);
        api_read::create(['context' => $context, 'other' => ['operation' => 'course_report']])->trigger();
        return [
            'courseid' => $report['courseid'],
            'source' => $report['source'],
            'generated' => $report['generated'],
            'metrics' => array_map(static fn(array $row): array => [
                'name' => $row['metric'], 'value' => (string) $row['value'],
            ], $report['rows']),
        ];
    }

    /**
     * Declare the aggregate response contract.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'source' => new external_value(PARAM_ALPHA, 'Data source'),
            'generated' => new external_value(PARAM_INT, 'Unix generation time'),
            'metrics' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Metric label'),
                'value' => new external_value(PARAM_TEXT, 'Metric value, localized when applicable'),
            ])),
        ]);
    }
}
