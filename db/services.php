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
 * The services the canvas calls.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_flowboard_get_editor_data' => [
        'classname' => 'tool_flowboard\external\get_editor_data',
        'description' => 'The flow being opened, and everything the canvas can draw it from.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_flowboard_save_draft' => [
        'classname' => 'tool_flowboard\external\save_draft',
        'description' => 'Autosaves the canvas\'s own unpublished drawing.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_flowboard_publish' => [
        'classname' => 'tool_flowboard\external\publish',
        'description' => 'Freezes the drawing into a new, immutable, running version.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_flowboard_test_run' => [
        'classname' => 'tool_flowboard\external\test_run',
        'description' => 'Walks an unsaved drawing against a sample event, for the "probar" button.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'tool_flowboard_node_stats' => [
        'classname' => 'tool_flowboard\external\node_stats',
        'description' => 'How each node of a flow has been doing lately.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
