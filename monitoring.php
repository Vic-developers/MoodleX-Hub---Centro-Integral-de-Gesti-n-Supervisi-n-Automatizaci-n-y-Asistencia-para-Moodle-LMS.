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

use local_moodlexhub\output\monitoring;

require_login(null, false);
\local_moodlexhub\module_config::require_enabled('monitoring');
$context = context_system::instance();
require_capability(monitoring::CAPABILITY, $context);
$PAGE->set_context($context);
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_url(new moodle_url('/local/moodlexhub/monitoring.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('monitoring'));
$PAGE->set_heading($label('monitoring'));
$PAGE->navbar->add($label('pluginname'), new moodle_url('/local/moodlexhub/index.php'));

$filter = optional_param('scope', 'hub', PARAM_ALPHA);
if (!in_array($filter, ['hub', 'site'], true)) {
    throw new invalid_parameter_exception();
}
$page = max(0, min(intdiv(PHP_INT_MAX, 20), optional_param('page', 0, PARAM_INT)));
$view = new monitoring($filter, $page);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/monitoring', $view->export_for_template($OUTPUT));
echo $OUTPUT->paging_bar($view->get_total(), $page, 20, new moodle_url($PAGE->url, ['scope' => $filter]));
echo $OUTPUT->footer();
