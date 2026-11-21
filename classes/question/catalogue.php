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

namespace local_moodlexhub\question;

use context_course;
use stdClass;

/**
 * Inspect the latest versions of questions within the actor's authorized banks.
 */
final class catalogue {
    /** @var string[] Moodle question version states supported by this catalogue. */
    public const STATUSES = ['ready', 'draft', 'hidden'];
    /** @var string Moodle capability required for this operation. */
    public const CAPABILITY = 'local/moodlexhub:viewquestions';

    /**
     * Find course and activity contexts where Moodle permits viewing bank entries.
     *
     * @param stdClass $course Course being inspected.
     * @return array
     */
    public function scope(stdClass $course): array {
        \local_moodlexhub\module_config::require_enabled('questions');
        $context = context_course::instance($course->id);
        require_capability(self::CAPABILITY, $context);
        if ($course->id == SITEID || !\core_course_category::can_view_course_info($course)) {
            throw new \moodle_exception('courseunavailable', 'local_moodlexhub');
        }
        $contexts = [$context->id => $context];
        $usagecontexts = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if ($cm->deletioninprogress || !$cm->uservisible) {
                continue;
            }
            if (in_array($cm->modname, ['qbank', 'quiz'], true)) {
                $contexts[$cm->context->id] = $cm->context;
            }
            if ($cm->modname === 'quiz' && has_capability('mod/quiz:viewreports', $cm->context)) {
                $usagecontexts[] = $cm->context->id;
            }
        }
        $all = $mine = [];
        $labels = [];
        foreach ($contexts as $id => $candidate) {
            if (has_capability('moodle/question:viewall', $candidate)) {
                $all[] = $id;
            } else if (has_capability('moodle/question:viewmine', $candidate)) {
                $mine[] = $id;
            } else {
                continue;
            }
            $labels[$id] = html_to_text($candidate->get_context_name(false), 0, false);
        }
        return ['all' => $all, 'mine' => $mine, 'labels' => $labels, 'usage' => $usagecontexts];
    }

    /**
     * Load a filtered page, including authorized duplicate and usage counts.
     *
     * @param array $scope Resolved bank scope.
     * @param array $filters Search, bank, category, author and question type.
     * @param int $offset Page offset.
     * @param int $limit Page size.
     * @param int $compareid Question bank entry to compare, or zero.
     * @param int $comparepage Batch of candidate questions to compare.
     * @return array
     */
    public function search(array $scope, array $filters, int $offset, int $limit, int $compareid = 0, int $comparepage = 0): array {
        global $DB, $USER;

        $clauses = [];
        $params = [];
        if ($scope['all']) {
            [$insql, $p] = $DB->get_in_or_equal($scope['all'], SQL_PARAMS_NAMED, 'fullbank');
            $clauses[] = "qc.contextid $insql";
            $params += $p;
        }
        if ($scope['mine']) {
            [$insql, $p] = $DB->get_in_or_equal($scope['mine'], SQL_PARAMS_NAMED, 'ownbank');
            $clauses[] = "(qc.contextid $insql AND q.createdby = :ownauthor)";
            $params += $p + ['ownauthor' => $USER->id];
        }
        if (!$clauses) {
            return ['total' => 0, 'rows' => [], 'categories' => [], 'types' => [], 'authors' => [], 'comparison' => null];
        }
        $base = ' FROM {question_bank_entries} e
                  JOIN {question_categories} qc ON qc.id = e.questioncategoryid
                  JOIN {question_versions} v ON v.questionbankentryid = e.id
                  JOIN {question} q ON q.id = v.questionid
                  LEFT JOIN {user} u ON u.id = q.createdby
                 WHERE (' . implode(' OR ', $clauses) . ')
                   AND v.version = (SELECT MAX(lv.version) FROM {question_versions} lv WHERE lv.questionbankentryid = e.id)';
        $filtered = $base;
        $selectedparams = $params;
        $columns = [
            'bank' => 'qc.contextid',
            'category' => 'qc.id',
            'author' => 'q.createdby',
            'type' => 'q.qtype',
            'status' => 'v.status',
        ];
        foreach ($columns as $key => $field) {
            if (!empty($filters[$key])) {
                $filtered .= " AND $field = :filter$key";
                $selectedparams['filter' . $key] = $filters[$key];
            }
        }
        if ($filters['search'] !== '') {
            $filtered .= ' AND ' . $DB->sql_like('q.name', ':findname', false);
            $selectedparams['findname'] = '%' . $DB->sql_like_escape($filters['search']) . '%';
        }
        $total = $DB->count_records_sql('SELECT COUNT(e.id)' . $filtered, $selectedparams);
        $items = $DB->get_records_sql(
            'SELECT e.id, q.name, q.qtype, qc.name AS category, qc.contextid, q.createdby, v.version, v.status,
                    u.firstname, u.lastname' . $filtered . ' ORDER BY q.name ASC, e.id ASC',
            $selectedparams,
            $offset,
            $limit,
        );
        $duplicates = [];
        $usage = [];
        if ($items) {
            $names = array_unique(array_map(fn($item) => \core_text::strtolower($item->name), $items));
            [$insql, $duplicateparams] = $DB->get_in_or_equal(array_values($names), SQL_PARAMS_NAMED, 'dupename');
            $duplicates = $DB->get_records_sql_menu(
                'SELECT LOWER(q.name), COUNT(e.id)' . $base . ' AND LOWER(q.name) ' . $insql . ' GROUP BY LOWER(q.name)',
                $params + $duplicateparams,
            );
            if ($scope['usage']) {
                [$entrysql, $entryparams] = $DB->get_in_or_equal(array_keys($items), SQL_PARAMS_NAMED, 'qentry');
                [$contextsql, $contextparams] = $DB->get_in_or_equal($scope['usage'], SQL_PARAMS_NAMED, 'qscope');
                $usage = $DB->get_records_sql_menu(
                    'SELECT questionbankentryid, COUNT(id) FROM {question_references}
                       WHERE questionbankentryid ' . $entrysql . ' AND usingcontextid ' . $contextsql . ' GROUP BY questionbankentryid',
                    $entryparams + $contextparams,
                );
            }
        }
        $rows = [];
        foreach ($items as $item) {
            $rows[] = ['entryid' => $item->id, 'cells' => [
                $item->name,
                $this->type_name($item->qtype),
                $item->category,
                $scope['labels'][$item->contextid],
                trim(($item->firstname ?? '') . ' ' . ($item->lastname ?? '')),
                $item->version . ' · ' . get_string('questionstate_' . $item->status, 'local_moodlexhub'),
                $scope['usage'] ? (int) ($usage[$item->id] ?? 0) : get_string('notapplicable', 'local_moodlexhub'),
                max(0, (int) ($duplicates[\core_text::strtolower($item->name)] ?? 0) - 1),
            ]];
        }
        return [
            'total' => $total,
            'comparison' => $compareid ? $this->compare($scope, $base, $params, $compareid, $comparepage) : null,
            'rows' => $rows,
            'categories' => $DB->get_records_sql_menu('SELECT DISTINCT qc.id, qc.name' . $base . ' ORDER BY qc.name ASC', $params),
            'types' => $DB->get_fieldset_sql('SELECT DISTINCT q.qtype' . $base . ' ORDER BY q.qtype ASC', $params),
            'authors' => $DB->get_records_sql(
                'SELECT DISTINCT q.createdby AS id, u.firstname, u.lastname' . $base
                    . ' AND q.createdby IS NOT NULL ORDER BY u.lastname ASC, u.firstname ASC',
                $params,
            ),
        ];
    }

    /**
     * Compare the latest permitted question versions in bounded batches.
     *
     * @param array $scope Authorized bank scope.
     * @param string $base Fixed Moodle DML joins and scope.
     * @param array $params SQL parameters.
     * @param int $id Entry to compare.
     * @param int $page Candidate batch.
     * @return array
     */
    private function compare(array $scope, string $base, array $params, int $id, int $page): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        $fields = 'SELECT e.id, q.id AS questionid, q.name, q.qtype, q.questiontext, qc.contextid';
        $focus = $DB->get_record_sql($fields . $base . ' AND e.id = :focus', $params + ['focus' => $id], MUST_EXIST);
        $candidates = $DB->get_records_sql(
            $fields . $base . ' AND e.id <> :focus ORDER BY e.id',
            $params + ['focus' => $id],
            $page * 200,
            201,
        );
        $more = count($candidates) > 200;
        if ($more) {
            array_pop($candidates);
        }
        $choiceids = [];
        if ($focus->qtype === 'multichoice') {
            $choiceids = [$focus->questionid];
            foreach ($candidates as $candidate) {
                if ($candidate->qtype === 'multichoice') {
                    $choiceids[] = $candidate->questionid;
                }
            }
        }
        $options = [];
        if ($choiceids) {
            [$insql, $choiceparams] = $DB->get_in_or_equal($choiceids);
            $answers = $DB->get_records_select('question_answers', "question $insql", $choiceparams, 'id', 'id,question,answer');
            foreach ($answers as $answer) {
                $normal = $this->normalise($answer->answer);
                if ($normal !== '') {
                    $options[$answer->question][] = $normal;
                }
            }
            foreach ($options as &$values) {
                sort($values, SORT_STRING);
            }
            unset($values);
        }
        $stem = $this->normalise($focus->questiontext);
        $words = array_unique(explode(' ', $stem));
        $choices = $options[$focus->questionid] ?? [];
        $matches = [];
        $canpreview = \core\plugininfo\qbank::is_plugin_enabled('qbank_previewquestion');
        foreach ($candidates as $candidate) {
            $candidateprompt = $this->normalise($candidate->questiontext);
            $other = array_unique(explode(' ', $candidateprompt));
            $exact = $stem !== '' && $stem === $candidateprompt;
            $similar = min(count($words), count($other)) >= 5
                && count(array_intersect($words, $other)) / count(array_unique(array_merge($words, $other))) >= 0.8;
            $samechoices = count(array_unique($choices)) >= 2 && $choices === ($options[$candidate->questionid] ?? []);
            if (!$exact && !$similar && !(count(array_unique($choices)) >= 3 && $samechoices)) {
                continue;
            }
            $url = '';
            if ($canpreview && question_has_capability_on($candidate->questionid, 'use')) {
                $url = \qbank_previewquestion\helper::question_preview_url(
                    $candidate->questionid,
                    context: \context::instance_by_id($candidate->contextid),
                )->out(false);
            }
            $matches[] = [
                'name' => $candidate->name,
                'bank' => $scope['labels'][$candidate->contextid],
                'exact' => $exact,
                'similar' => !$exact && $similar,
                'options' => $samechoices,
                'url' => $url,
            ];
        }
        return ['name' => $focus->name, 'matches' => $matches, 'more' => $more];
    }

    /**
     * Convert question and answer HTML into a comparable word sequence.
     *
     * @param string $text Question or answer text.
     * @return string
     */
    private function normalise(string $text): string {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', \core_text::strtolower(html_to_text($text, 0, false))));
    }

    /**
     * Retrieve the Moodle display name of a question type.
     *
     * @param string $name Question type code.
     * @return string
     */
    public function type_name(string $name): string {
        return get_string_manager()->string_exists('pluginname', 'qtype_' . $name)
            ? get_string('pluginname', 'qtype_' . $name) : $name;
    }
}
