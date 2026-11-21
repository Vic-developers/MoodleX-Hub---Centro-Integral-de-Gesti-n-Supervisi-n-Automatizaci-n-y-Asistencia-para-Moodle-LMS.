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

namespace local_moodlexhub\quality;

use cm_info;
use context_course;
use core_course_category;
use core_course_list_element;
use stdClass;

/**
 * Measure the configured rules on a course and store a dated result.
 */
final class auditor {
    /** @var string Moodle capability required for this operation. */
    public const CAPABILITY = 'local/moodlexhub:auditcourses';
    /** @var string XMLDB table for this module's records. */
    public const TABLE = 'local_moodlexhub_audit';
    /** @var int[] Default weights indexed by audit rule. */
    public const WEIGHTS = [
        'coursedescription' => 3,
        'courseimage' => 1,
        'coursecontacts' => 3,
        'courseactivities' => 3,
        'emptysections' => 1,
        'emptyresources' => 3,
        'hiddenactivities' => 1,
        'activitydescriptions' => 2,
        'coursedates' => 2,
        'coursereference' => 0,
        'activitycompletion' => 2,
    ];

    /**
     * Build and persist an audit as the current user.
     *
     * @param stdClass $course Moodle course record.
     * @return int ID of the stored audit.
     */
    public function run(stdClass $course): int {
        global $DB, $USER;

        $context = $this->require_access($course);
        $observations = $this->inspect($course, $context);
        $findings = [];
        $totalweight = 0;
        $passedweight = 0;

        $template = (new category_template())->resolve($course->category);
        foreach ($template['weights'] as $rule => $weight) {
            if (!$weight) {
                continue;
            }

            $count = $observations[$rule];
            if ($count !== null) {
                $totalweight += $weight;
                $passedweight += $count === 0 ? $weight : 0;
            }
            $findings[] = ['rule' => $rule, 'weight' => $weight, 'count' => $count, 'template' => $template['category']];
        }

        $transaction = $DB->start_delegated_transaction();
        $id = $DB->insert_record(self::TABLE, (object) [
            'courseid' => $course->id,
            'userid' => $USER->id,
            'score' => $totalweight ? (int) round(100 * $passedweight / $totalweight) : null,
            'findings' => json_encode($findings, JSON_THROW_ON_ERROR),
            'timecreated' => time(),
        ]);
        \local_moodlexhub\integration\webhooks::publish('quality', $id);
        $transaction->allow_commit();
        return $id;
    }

    /**
     * Authorize course quality operations.
     *
     * @param stdClass $course Moodle course record.
     * @return context_course Course context.
     */
    public function require_access(stdClass $course): context_course {
        \local_moodlexhub\module_config::require_enabled('quality');
        if ($course->id == SITEID || !core_course_category::can_view_course_info($course)) {
            throw new \moodle_exception('courseunavailable', 'local_moodlexhub');
        }
        $context = context_course::instance($course->id);
        require_capability(\local_moodlexhub\course\catalog::VIEW, $context);
        require_capability(self::CAPABILITY, $context);
        require_capability('moodle/course:update', $context);
        return $context;
    }

    /**
     * Return the latest audit visible to the current actor.
     *
     * @param stdClass $course Moodle course record.
     * @return stdClass|null Latest audit, when available.
     */
    public function latest(stdClass $course): ?stdClass {
        global $DB;
        $this->require_access($course);
        $records = $DB->get_records(
            self::TABLE,
            ['courseid' => $course->id],
            'timecreated DESC, id DESC',
            'id,score,findings',
            0,
            1,
        );
        return $records ? reset($records) : null;
    }

    /**
     * Find rule observations. Null means the rule does not apply.
     *
     * @param stdClass $course Moodle course record.
     * @param context_course $context Course context.
     * @return array<string, ?int>
     */
    private function inspect(stdClass $course, context_course $context): array {
        global $CFG;

        $modinfo = get_fast_modinfo($course);
        $activities = [];
        $populatedsections = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->deletioninprogress || (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $cm->context))) {
                continue;
            }
            $populatedsections[$cm->section] = true;
            if ($cm->modname !== 'label') {
                $activities[$cm->id] = $cm;
            }
        }

        $emptysections = 0;
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section > 0 && $section->uservisible && empty($populatedsections[$section->id])) {
                $emptysections++;
            }
        }
        $storage = get_file_storage();
        $overview = $storage->get_area_files($context->id, 'course', 'overviewfiles', 0, 'id', false);
        $contactsconfigured = !empty($CFG->coursecontact);
        $courseinfo = new core_course_list_element($course);
        $introcounts = $this->count_missing_intros($activities);

        return [
            'coursedescription' => (int) $this->is_empty($course->summary ?? ''),
            'courseimage' => (int) !array_filter($overview, static fn($file): bool => $file->is_valid_image()),
            'coursecontacts' => $contactsconfigured ? (int) !$courseinfo->has_course_contacts() : null,
            'courseactivities' => (int) !$activities,
            'emptysections' => $emptysections,
            'emptyresources' => $this->count_empty_resources($activities),
            'hiddenactivities' => $activities ? count(array_filter($activities, static fn(cm_info $cm): bool => !$cm->visible)) : null,
            'activitydescriptions' => $introcounts,
            'coursedates' => $course->startdate && $course->enddate ? (int) ($course->enddate < $course->startdate) : null,
            'coursereference' => (int) (trim($course->idnumber ?? '') === ''),
            'activitycompletion' => !empty($CFG->enablecompletion) && $course->enablecompletion && $activities
                ? count(array_filter($activities, static fn(cm_info $cm): bool => !$cm->completion)) : null,
        ];
    }

    /**
     * Inspect the standard intro fields for activity types that declare support.
     *
     * @param cm_info[] $activities Activities visible to the auditor.
     * @return int|null Number of empty introductions, or null if inapplicable.
     */
    private function count_missing_intros(array $activities): ?int {
        global $DB;

        $instances = [];
        foreach ($activities as $cm) {
            if (plugin_supports('mod', $cm->modname, FEATURE_MOD_INTRO, false)) {
                $instances[$cm->modname][] = $cm->instance;
            }
        }
        $count = 0;
        foreach ($instances as $table => $ids) {
            foreach (array_chunk($ids, 500) as $batch) {
                $records = $DB->get_records_list($table, 'id', $batch, '', 'id,intro');
                foreach ($records as $record) {
                    $count += (int) $this->is_empty($record->intro);
                }
            }
        }
        return $instances ? $count : null;
    }

    /**
     * Inspect the official file area for core File resources.
     *
     * @param cm_info[] $activities Activities visible to the auditor.
     * @return int|null Number of empty resources, or null if inapplicable.
     */
    private function count_empty_resources(array $activities): ?int {
        $resources = array_filter($activities, static fn(cm_info $cm): bool => $cm->modname === 'resource');
        $storage = get_file_storage();
        $count = 0;
        foreach ($resources as $cm) {
            $files = $storage->get_area_files($cm->context->id, 'mod_resource', 'content', 0, 'id', false);
            $count += (int) !array_filter($files, static fn($file): bool => $file->get_filesize() > 0);
        }
        return $resources ? $count : null;
    }

    /**
     * Treat meaningful embedded media as content even without plain text.
     *
     * @param string $content Stored course or activity description.
     * @return bool
     */
    private function is_empty(string $content): bool {
        return trim(html_to_text($content, 0, false)) === ''
            && !preg_match('~<(?:img|video|audio|iframe|object)\b~i', $content);
    }
}
