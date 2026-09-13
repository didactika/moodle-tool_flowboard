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
 * Everything a flow could be built on: the events this site can fire.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_flowboard\local\event_catalogue;
use tool_flowboard\output\event_catalogue_page;

admin_externalpage_setup('tool_flowboard_events');

$component = optional_param('component', '', PARAM_COMPONENT);
$search = trim(optional_param('search', '', PARAM_TEXT));

$page = new event_catalogue_page(event_catalogue::all(), $component, $search);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('events:heading', 'tool_flowboard'));
echo $OUTPUT->render_from_template('tool_flowboard/event_catalogue', $page->export_for_template($OUTPUT));
echo $OUTPUT->footer();
