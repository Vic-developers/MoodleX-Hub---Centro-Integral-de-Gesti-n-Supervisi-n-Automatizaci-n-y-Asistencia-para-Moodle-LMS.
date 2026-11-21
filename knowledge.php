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

use local_moodlexhub\support\knowledge;

require_login(null, false);
\local_moodlexhub\module_config::require_enabled('support');
knowledge::require_access();
$context = context_system::instance();
$manage = has_capability(knowledge::MANAGE, $context);
$id = optional_param('id', 0, PARAM_INT);
$search = trim(optional_param('search', '', PARAM_TEXT));
$type = optional_param('type', '', PARAM_ALPHA);
$category = trim(optional_param('category', '', PARAM_TEXT));
if ($type && !in_array($type, knowledge::TYPES, true)) {
    throw new invalid_parameter_exception();
}
$page = max(0, min(intdiv(PHP_INT_MAX, 25), optional_param('page', 0, PARAM_INT)));
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$baseurl = new moodle_url('/local/moodlexhub/knowledge.php');
$baseconditions = $manage ? [] : ['published' => 1];
$article = $id ? $DB->get_record(knowledge::TABLE, $baseconditions + ['id' => $id], '*', MUST_EXIST) : null;
$url = new moodle_url($baseurl, $article ? ['id' => $id] : ['search' => $search, 'type' => $type, 'category' => $category]);
$title = $article ? $article->title : $label('knowledge');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$rows = [];
$categories = [];
$types = [];
$total = 0;
if (!$article) {
    $where = [$manage ? '1 = 1' : 'published = 1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(' . $DB->sql_like('title', ':titlelike', false) . ' OR ' . $DB->sql_like('body', ':bodylike', false) . ')';
        $params['titlelike'] = $params['bodylike'] = '%' . $DB->sql_like_escape($search) . '%';
    }
    if ($category !== '') {
        $where[] = 'category = :category';
        $params['category'] = $category;
    }
    if ($type !== '') {
        $where[] = 'type = :type';
        $params['type'] = $type;
    }
    $select = implode(' AND ', $where);
    $total = $DB->count_records_select(knowledge::TABLE, $select, $params);
    foreach (
        $DB->get_records_select(
            knowledge::TABLE,
            $select,
            $params,
            'timemodified DESC, id DESC',
            'id,title,category,type,published',
            $page * 25,
            25
        ) as $record
    ) {
        $rows[] = [
            'title' => $record->title,
            'category' => $record->category,
            'type' => $label('knowledgetype_' . $record->type),
            'draft' => !$record->published,
            'url' => (new moodle_url($baseurl, ['id' => $record->id]))->out(false),
        ];
    }

    $categories[] = ['key' => '', 'name' => $label('allcategories'), 'selected' => !$category];
    foreach (
        $DB->get_fieldset_sql('SELECT DISTINCT category FROM {' . knowledge::TABLE . '} WHERE '
            . ($manage ? '1 = 1' : 'published = 1') . ' ORDER BY category ASC') as $name
    ) {
        $categories[] = ['key' => $name, 'name' => $name, 'selected' => $name === $category];
    }
    $types[] = ['key' => '', 'name' => $label('knowledgealltypes'), 'selected' => !$type];
    foreach (knowledge::TYPES as $key) {
        $types[] = ['key' => $key, 'name' => $label('knowledgetype_' . $key), 'selected' => $key === $type];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/knowledge', [
    'supporturl' => (new moodle_url('/local/moodlexhub/support.php'))->out(false),
    'knowledgeurl' => $baseurl->out(false),
    'createurl' => $manage ? (new moodle_url('/local/moodlexhub/knowledge_edit.php'))->out(false) : null,
    'article' => $article ? [
        'type' => $label('knowledgetype_' . $article->type),
        'category' => $article->category,
        'draft' => !$article->published,
        'body' => format_text($article->body, FORMAT_MARKDOWN, ['context' => $context, 'noclean' => false]),
        'editurl' => $manage ? (new moodle_url('/local/moodlexhub/knowledge_edit.php', ['id' => $id]))->out(false) : null,
    ] : null,
    'search' => $search,
    'categories' => $categories,
    'types' => $types,
    'rows' => $rows,
    'hasrows' => (bool) $rows,
]);
if (!$article) {
    echo $OUTPUT->paging_bar($total, $page, 25, $url);
}
echo $OUTPUT->footer();
