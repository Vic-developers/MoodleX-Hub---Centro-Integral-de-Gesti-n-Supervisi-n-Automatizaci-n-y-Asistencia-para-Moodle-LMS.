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

namespace local_moodlexhub;

use advanced_testcase;
use core\dataformat;
use local_moodlexhub\report\builder;
use local_moodlexhub\support\tickets;

/**
 * Build a durable report and write the three distribution formats using Moodle.
 */
final class report_formats_test extends advanced_testcase {
    /**
     * CSV, XLSX and PDF get data from a persisted report run.
     */
    public function test_moodle_report_formats(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $service = new builder();
        $id = $DB->insert_record(builder::REPORTS, (object) [
            'userid' => $USER->id, 'courseid' => 0, 'title' => 'Support metrics', 'source' => 'tickets',
            'filtervalue' => 0, 'intervalvalue' => 0, 'nextdue' => 0, 'timemodified' => time(),
        ]);
        (new tickets())->create((object) ['subject' => 'Sample ticket', 'body' => 'Question', 'priority' => 'normal']);
        $run = $service->enqueue($id, -time());
        $service->run($run, 3);

        $saved = $DB->get_record(builder::RUNS, ['id' => $run, 'reportid' => $id], '*', MUST_EXIST);
        $this->assertSame('finished', $saved->status);
        $data = json_decode($saved->payload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $data['rows'][0]['value']);
        foreach (['csv' => '', 'excel' => 'PK', 'pdf' => '%PDF'] as $format => $signature) {
            $path = dataformat::write_data('moodlexhub-test', $format, ['metric' => 'Indicator', 'value' => 'Value'], $data['rows']);
            $this->assertGreaterThan(5, filesize($path));
            if ($signature !== '') {
                $this->assertStringStartsWith($signature, file_get_contents($path));
            }
        }
    }
}
