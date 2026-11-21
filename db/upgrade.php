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

/**
 * Upgrade MoodleX Hub data without removing existing records.
 *
 * @param int $oldversion Previously installed MoodleX Hub version.
 * @return bool Whether the upgrade succeeded.
 */
function xmldb_local_moodlexhub_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026112002) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moodlexhub_audit');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('score', XMLDB_TYPE_INTEGER, '3');
        $table->add_field('findings', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('course', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('course-time', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112002, 'local', 'moodlexhub');
    }
    if ($oldversion < 2026112004) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moodlexhub_import');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('roleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('course', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('user-course-time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid', 'timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_moodlexhub_imp_row');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('importid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('position', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('reason', XMLDB_TYPE_CHAR, '16');
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('import', XMLDB_KEY_FOREIGN, ['importid'], 'local_moodlexhub_import', ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('import-row', XMLDB_INDEX_UNIQUE, ['importid', 'position']);
        $table->add_index('import-status', XMLDB_INDEX_NOTUNIQUE, ['importid', 'status', 'attempts']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112004, 'local', 'moodlexhub');
    }

    if ($oldversion < 2026112006) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moodlexhub_snapshot');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('source', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('summary', XMLDB_TYPE_TEXT);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('course', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('course-source-time', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'source', 'timecreated']);
        $table->add_index('modified', XMLDB_INDEX_NOTUNIQUE, ['timemodified']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112006, 'local', 'moodlexhub');
    }

    if ($oldversion < 2026112007) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moodlexhub_note');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('title', XMLDB_TYPE_CHAR, '120', null, XMLDB_NOTNULL);
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('user-time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timemodified']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112007, 'local', 'moodlexhub');
    }

    if ($oldversion < 2026112009) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moodlexhub_ticket');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('assignedto', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('subject', XMLDB_TYPE_CHAR, '160', null, XMLDB_NOTNULL);
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('priority', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('actor', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('assignee', XMLDB_KEY_FOREIGN, ['assignedto'], 'user', ['id']);
        $table->add_index('owner-time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timemodified']);
        $table->add_index('assigned-status', XMLDB_INDEX_NOTUNIQUE, ['assignedto', 'status']);
        $table->add_index('status-time', XMLDB_INDEX_NOTUNIQUE, ['status', 'timemodified']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_moodlexhub_tk_post');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ticketid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('body', XMLDB_TYPE_TEXT);
        $table->add_field('statusold', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('statusnew', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('priorityold', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('prioritynew', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('assignmentchanged', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('ticket', XMLDB_KEY_FOREIGN, ['ticketid'], 'local_moodlexhub_ticket', ['id']);
        $table->add_key('actor', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('ticket-date', XMLDB_INDEX_NOTUNIQUE, ['ticketid', 'timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026112009, 'local', 'moodlexhub');
    }

    if ($oldversion < 2026112010) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moodlexhub_article');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('title', XMLDB_TYPE_CHAR, '160', null, XMLDB_NOTNULL);
        $table->add_field('category', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('type', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('published', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('published-category', XMLDB_INDEX_NOTUNIQUE, ['published', 'category']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112010, 'local', 'moodlexhub');
    }

    if ($oldversion < 2026112012) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moodlexhub_ai_use');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('mode', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('characters', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('inputtokens', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('outputtokens', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('user-time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
        $table->add_index('created', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112012, 'local', 'moodlexhub');
    }

    if ($oldversion < 2026112013) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moodlexhub_feedback');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('title', XMLDB_TYPE_CHAR, '160', null, XMLDB_NOTNULL);
        $table->add_field('category', XMLDB_TYPE_CHAR, '100');
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('type', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('shared', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('course', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('course-user', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'userid']);
        $table->add_index('course-shared', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'shared']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112013, 'local', 'moodlexhub');
    }

    if ($oldversion < 2026112014) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moodlexhub_report');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('title', XMLDB_TYPE_CHAR, '160', null, XMLDB_NOTNULL);
        $table->add_field('source', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('filtervalue', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('intervalvalue', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextdue', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('owner', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timemodified']);
        $table->add_index('due', XMLDB_INDEX_NOTUNIQUE, ['nextdue']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_moodlexhub_rpt_run');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timeslot', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('payload', XMLDB_TYPE_TEXT);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('report', XMLDB_KEY_FOREIGN, ['reportid'], 'local_moodlexhub_report', ['id']);
        $table->add_index('report-slot', XMLDB_INDEX_UNIQUE, ['reportid', 'timeslot']);
        $table->add_index('report-time', XMLDB_INDEX_NOTUNIQUE, ['reportid', 'timemodified']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_moodlexhub_auto');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('recipient', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('title', XMLDB_TYPE_CHAR, '160', null, XMLDB_NOTNULL);
        $table->add_field('description', XMLDB_TYPE_TEXT);
        $table->add_field('conditiontype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('actiontype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('threshold', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('intervalvalue', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('nextdue', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('owner', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timemodified']);
        $table->add_index('due', XMLDB_INDEX_NOTUNIQUE, ['enabled', 'nextdue']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_moodlexhub_auto_run');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ruleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timeslot', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('resultid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('rule', XMLDB_KEY_FOREIGN, ['ruleid'], 'local_moodlexhub_auto', ['id']);
        $table->add_index('rule-slot', XMLDB_INDEX_UNIQUE, ['ruleid', 'timeslot']);
        $table->add_index('rule-time', XMLDB_INDEX_NOTUNIQUE, ['ruleid', 'timemodified']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026112014, 'local', 'moodlexhub');
    }

    if ($oldversion < 2026112015) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moodlexhub_endpoint');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('name', XMLDB_TYPE_CHAR, '160', null, XMLDB_NOTNULL);
        $table->add_field('url', XMLDB_TYPE_CHAR, '512', null, XMLDB_NOTNULL);
        $table->add_field('topic', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('cipher', XMLDB_TYPE_CHAR, '440', null, XMLDB_NOTNULL);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('event', XMLDB_INDEX_NOTUNIQUE, ['enabled', 'topic']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_moodlexhub_api_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('endpointid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('topic', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('reference', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('httpcode', XMLDB_TYPE_INTEGER, '3');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('endpoint', XMLDB_KEY_FOREIGN, ['endpointid'], 'local_moodlexhub_endpoint', ['id']);
        $table->add_index('delivery', XMLDB_INDEX_UNIQUE, ['endpointid', 'topic', 'reference']);
        $table->add_index('time', XMLDB_INDEX_NOTUNIQUE, ['timemodified']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026112015, 'local', 'moodlexhub');
    }

    if ($oldversion < 2026112017) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moodlexhub_aud_batch');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('actor', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('owner-time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_moodlexhub_aud_row');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('jobid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('auditid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('batch', XMLDB_KEY_FOREIGN, ['jobid'], 'local_moodlexhub_aud_batch', ['id']);
        $table->add_index('job-course', XMLDB_INDEX_UNIQUE, ['jobid', 'courseid']);
        $table->add_index('job-state', XMLDB_INDEX_NOTUNIQUE, ['jobid', 'status', 'attempts']);
        $table->add_index('course', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026112017, 'local', 'moodlexhub');
    }

    return true;
}
