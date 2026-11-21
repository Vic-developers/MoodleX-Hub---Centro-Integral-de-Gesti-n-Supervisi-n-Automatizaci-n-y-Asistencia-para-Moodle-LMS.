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
require_once($CFG->libdir . '/formslib.php');

use local_moodlexhub\form\knowledge_article;
use local_moodlexhub\support\knowledge;

require_login(null, false);
\local_moodlexhub\module_config::require_enabled('support');
$context = context_system::instance();
require_capability(knowledge::MANAGE, $context);
$id = optional_param('id', 0, PARAM_INT);
$article = $id ? $DB->get_record(knowledge::TABLE, ['id' => $id], '*', MUST_EXIST) : null;
$url = new moodle_url('/local/moodlexhub/knowledge_edit.php', ['id' => $id]);
$base = new moodle_url('/local/moodlexhub/knowledge.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$title = get_string($id ? 'knowledgeedit' : 'knowledgecreate', 'local_moodlexhub');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$form = new knowledge_article($url);
$form->set_data($article ?? ['id' => 0, 'type' => 'article', 'published' => 0]);
if ($form->is_cancelled()) {
    redirect($article ? new moodle_url($base, ['id' => $id]) : $base);
}
if ($data = $form->get_data()) {
    if (!in_array($data->type, knowledge::TYPES, true)) {
        throw new invalid_parameter_exception();
    }
    $record = (object) [
        'title' => trim($data->title), 'category' => trim($data->category), 'type' => $data->type,
        'body' => $data->body, 'published' => (int) $data->published, 'timemodified' => time(),
    ];
    if ($article) {
        $record->id = $id;
        $DB->update_record(knowledge::TABLE, $record);
    } else {
        $record->timecreated = $record->timemodified;
        $id = $DB->insert_record(knowledge::TABLE, $record);
    }
    redirect(new moodle_url($base, ['id' => $id]));
}
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
