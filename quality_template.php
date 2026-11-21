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

use local_moodlexhub\quality\category_template;

require_login(null, false);
$categoryid = optional_param('category', 0, PARAM_INT);
if (!$categoryid) {
    \local_moodlexhub\module_config::require_enabled('quality');
    $categories = core_course_category::make_categories_list(category_template::CAPABILITY);
    if (!$categories) {
        require_capability(category_template::CAPABILITY, context_system::instance());
    }
    $url = new moodle_url('/local/moodlexhub/quality_template.php');
    $title = get_string('qualitytemplate', 'local_moodlexhub');
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url($url);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title($title);
    $PAGE->set_heading($title);
    echo $OUTPUT->header();
    $select = new single_select($url, 'category', $categories, '', ['' => get_string('choose')]);
    $select->set_label(get_string('category'));
    echo $OUTPUT->render($select);
    echo $OUTPUT->footer();
    exit;
}
$category = core_course_category::get($categoryid, MUST_EXIST);
$context = context_coursecat::instance($categoryid);
\local_moodlexhub\module_config::require_enabled('quality');
require_capability(category_template::CAPABILITY, $context);
$service = new category_template();
$url = new moodle_url('/local/moodlexhub/quality_template.php', ['category' => $categoryid]);
if (data_submitted()) {
    require_sesskey();
    $service->save($categoryid, optional_param('inherit', 0, PARAM_BOOL) ? null : required_param_array('weights', PARAM_INT));
    redirect($url);
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$label = static fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_title($label('qualitytemplate'));
$PAGE->set_heading($label('qualitytemplate'));

$template = $service->resolve($categoryid);
$rules = [];
foreach ($template['weights'] as $rule => $weight) {
    $rules[] = [
        'rule' => $rule,
        'label' => $label('rule_' . $rule),
        'choices' => array_map(
            static fn(int $value): array => ['value' => $value, 'selected' => $weight === $value],
            [0, 1, 2, 3],
        ),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/quality_template', [
    'action' => $url->out(false),
    'home' => (new moodle_url('/local/moodlexhub/index.php'))->out(false),
    'category' => format_string($category->get_formatted_name()),
    'sesskey' => sesskey(),
    'own' => $template['category'] === $categoryid,
    'rules' => $rules,
]);
echo $OUTPUT->footer();
