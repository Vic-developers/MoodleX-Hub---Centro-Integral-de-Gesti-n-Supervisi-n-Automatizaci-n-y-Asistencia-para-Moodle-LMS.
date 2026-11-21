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

defined('MOODLE_INTERNAL') || die();

if (has_capability('local/moodlexhub:manageconfiguration', context_system::instance())) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_moodlexhub',
        get_string('pluginname', 'local_moodlexhub'),
        new moodle_url('/local/moodlexhub/index.php'),
        'local/moodlexhub:manageconfiguration',
    ));
    $settings = new admin_settingpage(
        'local_moodlexhub_settings',
        get_string('settings', 'local_moodlexhub'),
        'local/moodlexhub:manageconfiguration',
    );
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            'local_moodlexhub/dashboardcourses',
            get_string('showcourses', 'local_moodlexhub'),
            get_string('showcourses_desc', 'local_moodlexhub'),
            1,
        ));
        $settings->add(new admin_setting_configselect(
            'local_moodlexhub/followupreminderdays',
            get_string('followupreminders', 'local_moodlexhub'),
            get_string('followupreminderdesc', 'local_moodlexhub'),
            0,
            [0 => get_string('disabled', 'local_moodlexhub'), 1 => '1', 2 => '2', 3 => '3', 7 => '7'],
        ));
        $settings->add(new admin_setting_configselect(
            'local_moodlexhub/importbatchsize',
            get_string('importbatchsize', 'local_moodlexhub'),
            get_string('importbatchsize_desc', 'local_moodlexhub'),
            100,
            [25 => '25', 50 => '50', 100 => '100', 250 => '250', 500 => '500'],
        ));
        if (has_all_capabilities(['local/moodlexhub:manageintegrations', 'moodle/site:config'], context_system::instance())) {
            $settings->add(new admin_setting_configcheckbox(
                'local_moodlexhub/webhooksenabled',
                get_string('webhooksenabled', 'local_moodlexhub'),
                get_string('webhooksenabled_desc', 'local_moodlexhub'),
                0,
            ));
        }
    }
    $ADMIN->add('localplugins', $settings);
    $modules = new admin_settingpage(
        'local_moodlexhub_modules',
        get_string('modulesettings', 'local_moodlexhub'),
        'local/moodlexhub:manageconfiguration'
    );
    if ($ADMIN->fulltree) {
        foreach (\local_moodlexhub\module_config::LABELS as $key => $label) {
            if ($key === 'assistant') {
                continue;
            }
            $modules->add(new admin_setting_configcheckbox(
                'local_moodlexhub/enable_' . $key,
                get_string($label, 'local_moodlexhub'),
                get_string('modulesettingdesc', 'local_moodlexhub'),
                1,
            ));
        }
    }
    $ADMIN->add('localplugins', $modules);
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_moodlexhub_setup',
        get_string('setupwizard', 'local_moodlexhub'),
        new moodle_url('/local/moodlexhub/setup_wizard.php'),
        'local/moodlexhub:manageconfiguration',
    ));
}

if (has_capability('local/moodlexhub:managequalityrules', context_system::instance())) {
    $qualitysettings = new admin_settingpage(
        'local_moodlexhub_quality',
        get_string('qualitysettings', 'local_moodlexhub'),
        'local/moodlexhub:managequalityrules',
    );
    if ($ADMIN->fulltree) {
        $label = fn(string $key): string => get_string($key, 'local_moodlexhub');
        $weights = [0 => $label('disabled'), 1 => $label('severitylow'), 2 => $label('severitymedium'), 3 => $label('severityhigh')];
        foreach (\local_moodlexhub\quality\auditor::WEIGHTS as $rule => $default) {
            $qualitysettings->add(new admin_setting_configselect(
                'local_moodlexhub/quality_' . $rule,
                $label('rule_' . $rule),
                $label('rule_' . $rule . '_recommendation'),
                $default,
                $weights,
            ));
        }
        $period = [30 => '30', 90 => '90', 180 => '180', 365 => '365', 730 => '730', 1095 => '1095'];
        $qualitysettings->add(new admin_setting_configselect(
            'local_moodlexhub/auditretentiondays',
            $label('auditretentiondays'),
            $label('auditretentiondays_desc'),
            365,
            $period,
        ));
    }
    $ADMIN->add('localplugins', $qualitysettings);
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_moodlexhub_qualitytemplates',
        get_string('qualitytemplate', 'local_moodlexhub'),
        new moodle_url('/local/moodlexhub/quality_template.php'),
        'local/moodlexhub:managequalitytemplates',
    ));
}

if (has_capability('local/moodlexhub:manageai', context_system::instance())) {
    $assistantsettings = new admin_settingpage(
        'local_moodlexhub_assistant',
        get_string('assistantsettings', 'local_moodlexhub'),
        'local/moodlexhub:manageai'
    );
    if ($ADMIN->fulltree) {
        $assistantsettings->add(new admin_setting_configcheckbox(
            'local_moodlexhub/assistantenabled',
            get_string('assistantenabled', 'local_moodlexhub'),
            get_string('assistantenabled_desc', 'local_moodlexhub'),
            0,
        ));
        $assistantsettings->add(new admin_setting_configselect(
            'local_moodlexhub/assistantdaily',
            get_string('assistantdaily', 'local_moodlexhub'),
            get_string('assistantdaily_desc', 'local_moodlexhub'),
            10,
            [5 => '5', 10 => '10', 20 => '20', 50 => '50', 100 => '100'],
        ));
    }
    $ADMIN->add('localplugins', $assistantsettings);
}
