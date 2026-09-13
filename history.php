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
 * What a flow has done: every run, and every node inside it.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\output\run_history_page;

admin_externalpage_setup('tool_flowboard_index');
require_capability('tool/flowboard:viewruns', context_system::instance());

$id = required_param('id', PARAM_INT);
$flow = flow_repository::get($id);

if ($flow === null) {
    throw new moodle_exception('flow:error_notfound', 'tool_flowboard');
}

$PAGE->set_url('/admin/tool/flowboard/history.php', ['id' => $id]);
$PAGE->set_heading(format_string($flow->name));

$page = new run_history_page($flow);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('history:heading', 'tool_flowboard', format_string($flow->name)));
echo $OUTPUT->render_from_template('tool_flowboard/run_history', $page->export_for_template($OUTPUT));
echo $OUTPUT->footer();
