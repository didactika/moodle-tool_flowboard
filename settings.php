<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Where this plugin lives in the site administration.
 *
 * Admin tools are handed `$ADMIN` and are expected to add their own nodes, so
 * everything the plugin offers hangs off one category of its own rather than
 * scattering entries through Tools.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_category(
        'tool_flowboard',
        new lang_string('pluginname', 'tool_flowboard')
    ));

    // The flow list first: it is what the category is for. Settings, the
    // event catalogue and the actor list follow it.
    $ADMIN->add('tool_flowboard', new admin_externalpage(
        'tool_flowboard_index',
        new lang_string('flow:heading', 'tool_flowboard'),
        new moodle_url('/admin/tool/flowboard/index.php'),
        'tool/flowboard:manage'
    ));

    $settings = new admin_settingpage(
        'tool_flowboard_settings',
        new lang_string('settings:general', 'tool_flowboard')
    );

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            'tool_flowboard/enabled',
            new lang_string('setting:enabled', 'tool_flowboard'),
            new lang_string('setting:enabled_desc', 'tool_flowboard'),
            1
        ));

        $settings->add(new admin_setting_configtext(
            'tool_flowboard/maxtargets',
            new lang_string('setting:maxtargets', 'tool_flowboard'),
            new lang_string('setting:maxtargets_desc', 'tool_flowboard'),
            200,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'tool_flowboard/maxdepth',
            new lang_string('setting:maxdepth', 'tool_flowboard'),
            new lang_string('setting:maxdepth_desc', 'tool_flowboard'),
            3,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'tool_flowboard/retentiondays',
            new lang_string('setting:retentiondays', 'tool_flowboard'),
            new lang_string('setting:retentiondays_desc', 'tool_flowboard'),
            120,
            PARAM_INT
        ));
    }

    $ADMIN->add('tool_flowboard', $settings);

    $ADMIN->add('tool_flowboard', new admin_externalpage(
        'tool_flowboard_events',
        new lang_string('events:heading', 'tool_flowboard'),
        new moodle_url('/admin/tool/flowboard/events.php'),
        'tool/flowboard:manage'
    ));

    $ADMIN->add('tool_flowboard', new admin_externalpage(
        'tool_flowboard_actors',
        new lang_string('actors:heading', 'tool_flowboard'),
        new moodle_url('/admin/tool/flowboard/actors.php'),
        'tool/flowboard:manage'
    ));
}
