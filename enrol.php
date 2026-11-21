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
require_once($CFG->libdir . '/filelib.php');

use local_moodlexhub\enrol\imports;
use local_moodlexhub\form\enrol_preview;

require_login(null, false);
$course = get_course(required_param('id', PARAM_INT));
$imports = new imports();
$context = $imports->require_access($course);
$id = optional_param('importid', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$baseurl = new moodle_url('/local/moodlexhub/enrol.php', ['id' => $course->id]);
$url = $id ? new moodle_url($baseurl, ['importid' => $id]) : $baseurl;
$label = fn(string $key): string => get_string($key, 'local_moodlexhub');
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($label('enrolimports'));
$PAGE->set_heading($label('enrolimports'));
$catalog = has_capability('local/moodlexhub:viewcourses', $context);
$courseurl = $catalog
    ? new moodle_url('/local/moodlexhub/course.php', ['id' => $course->id])
    : new moodle_url('/course/view.php', ['id' => $course->id]);

if ($download === 'template' && !$id) {
    send_file("username\r\n", 'moodlexhub-enrolment-template.csv', 0, 0, true, true, 'text/csv');
}

$form = null;
$job = $id ? $imports->require_job($id, $course) : null;
if (!$job) {
    $methods = [];
    foreach ($imports->available_methods($context) as $instance) {
        $methods[$instance->id] = enrol_get_plugin($instance->enrol)->get_instance_name($instance);
    }
    $roles = get_assignable_roles($context);
    if ($methods && $roles) {
        $maxbytes = get_user_max_upload_file_size($context, $CFG->maxbytes, $course->maxbytes, 20 * 1024 * 1024);
        $form = new enrol_preview($url, [
            'courseid' => $course->id,
            'methods' => $methods,
            'roles' => $roles,
            'maxbytes' => $maxbytes,
        ]);
        if ($data = $form->get_data()) {
            $stream = $form->get_csv()->get_content_file_handle();
            if (!$stream) {
                throw new moodle_exception('importbadfile', 'local_moodlexhub');
            }
            try {
                $previewid = $imports->preview($course, $data->instanceid, $data->roleid, $stream);
            } finally {
                fclose($stream);
            }
            redirect(new moodle_url($url, ['importid' => $previewid]));
        }
    }
}

$jobs = [];
$rows = [];
$summary = [];
$canconfirm = false;
$canretry = false;
$total = 0;
$page = max(0, min(intdiv(PHP_INT_MAX, 50), optional_param('page', 0, PARAM_INT)));
if ($job) {
    if (data_submitted() && optional_param('confirmrun', 0, PARAM_BOOL)) {
        require_sesskey();
        $imports->schedule($job, $course);
        redirect($url);
    }
    if (data_submitted() && optional_param('retryrun', 0, PARAM_BOOL)) {
        require_sesskey();
        $imports->schedule($job, $course, true);
        redirect($url);
    }
    $conditions = ['importid' => $id];
    $statuscolumns = ['position', 'status', 'reason', 'attempts'];
    $formatrow = fn($row): array => [
        'position' => $row->position,
        'status' => $label('import_' . $row->status),
        'reason' => !empty($row->reason) ? $label('import_' . $row->reason) : '',
        'attempts' => $row->attempts,
    ];
    if ($download === 'csv') {
        $columns = array_combine($statuscolumns, array_map(fn($column) => $label('import' . $column), $statuscolumns));
        $records = $DB->get_recordset(imports::ROWS, $conditions, 'position ASC');
        $generator = (function () use ($records, $formatrow): Generator {
            try {
                foreach ($records as $row) {
                    yield $formatrow($row);
                }
            } finally {
                $records->close();
            }
        })();
        \core\dataformat::download_data('moodlexhub-enrolment-' . $id, 'csv', $columns, $generator);
        exit;
    }
    $total = $DB->count_records(imports::ROWS, $conditions);
    $counts = $DB->get_records_sql(
        'SELECT status, COUNT(id) AS total FROM {' . imports::ROWS . '} WHERE importid = :id GROUP BY status',
        ['id' => $id],
    );
    foreach ($counts as $count) {
        $summary[] = ['name' => $label('import_' . $count->status), 'value' => $count->total];
    }
    foreach ($DB->get_records(imports::ROWS, $conditions, 'position ASC', '*', $page * 50, 50) as $row) {
        $rows[] = $formatrow($row);
    }
    $canconfirm = $job->status === 'preview' && isset($counts['ready']);
    $canretry = ($job->status === 'finished' && isset($counts['failed']))
        || ($job->status === 'blocked' && (isset($counts['ready']) || isset($counts['retry']) || isset($counts['failed'])));
} else {
    $conditions = ['courseid' => $course->id, 'userid' => $USER->id];
    $total = $DB->count_records(imports::JOBS, $conditions);
    foreach ($DB->get_records(imports::JOBS, $conditions, 'timecreated DESC, id DESC', '*', $page * 50, 50) as $entry) {
        $jobs[] = [
            'date' => userdate($entry->timecreated),
            'status' => $label('import_' . $entry->status),
            'url' => (new moodle_url($url, ['importid' => $entry->id]))->out(false),
        ];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moodlexhub/enrol', [
    'courseurl' => $courseurl->out(false),
    'baseurl' => $baseurl->out(false),
    'jobview' => (bool) $job,
    'canconfirm' => $canconfirm,
    'canretry' => $canretry,
    'jobstatus' => $job ? $label('import_' . $job->status) : null,
    'joburl' => $job ? $url->out(false) : null,
    'templateurl' => $job ? null : (new moodle_url($url, ['download' => 'template']))->out(false),
    'exporturl' => $job ? (new moodle_url($url, ['download' => 'csv']))->out(false) : null,
    'sesskey' => sesskey(),
    'jobs' => $jobs,
    'hasjobs' => (bool) $jobs,
    'rows' => $rows,
    'summary' => $summary,
    'canupload' => (bool) $form,
]);
if ($form) {
    $form->display();
}
echo $OUTPUT->paging_bar($total, $page, 50, $url);
echo $OUTPUT->footer();
