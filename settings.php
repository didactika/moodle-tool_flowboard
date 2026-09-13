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
 * Site settings for tool_flowboard.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'tool_flowboard',
        get_string('pluginname', 'tool_flowboard')
    );

    $settings->add(new admin_setting_configcheckbox(
        'tool_flowboard/enabled',
        get_string('setting:enabled', 'tool_flowboard'),
        get_string('setting:enabled_desc', 'tool_flowboard'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'tool_flowboard/maxtargets',
        get_string('setting:maxtargets', 'tool_flowboard'),
        get_string('setting:maxtargets_desc', 'tool_flowboard'),
        200,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'tool_flowboard/retentiondays',
        get_string('setting:retentiondays', 'tool_flowboard'),
        get_string('setting:retentiondays_desc', 'tool_flowboard'),
        120,
        PARAM_INT
    ));

    $ADMIN->add('tools', $settings);
}
