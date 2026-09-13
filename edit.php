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
 * A flow's own name and description, and — once it exists — the canvas it is
 * actually drawn on.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_flowboard\form\flow_form;
use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\output\editor_page;

admin_externalpage_setup('tool_flowboard_index');

$id = optional_param('id', 0, PARAM_INT);
$listurl = new moodle_url('/admin/tool/flowboard/index.php');
$flow = null;
$defaults = ['flowid' => 0];

if ($id !== 0) {
    $flow = flow_repository::get($id);

    if ($flow === null) {
        throw new moodle_exception('flow:error_notfound', 'tool_flowboard');
    }

    $defaults = [
        'flowid' => $flow->id,
        'name' => $flow->name,
        'idnumber' => $flow->idnumber,
        'description' => $flow->description,
    ];
}

$pageurl = new moodle_url('/admin/tool/flowboard/edit.php', array_filter(['id' => $id]));
$PAGE->set_url($pageurl);
$PAGE->set_heading($flow !== null ? format_string($flow->name) : get_string('flow:new', 'tool_flowboard'));

// The form posts back to this same URL, id and all — passed explicitly
// rather than left to moodleform's own default, which strips the query
// string and would otherwise turn "save" on an existing flow into "create
// a new one", the id having gone missing from the request that comes back.
$form = new flow_form($pageurl, ['flowid' => $id]);
$form->set_data((object) $defaults);

if ($form->is_cancelled()) {
    redirect($listurl);
}

if ($data = $form->get_data()) {
    // The id in the submitted data, not the one read from the URL: they are
    // the same thing by construction, but this is the one that actually
    // travelled with the request.
    $flowid = (int) $data->flowid;

    if ($flowid === 0) {
        $flow = flow_repository::create(trim($data->idnumber), trim($data->name), trim($data->description ?? ''));

        // The canvas is what draws the graph; a flow with a name but nothing
        // drawn yet is exactly what it should open to next.
        redirect(new moodle_url('/admin/tool/flowboard/edit.php', ['id' => $flow->id]));
    }

    flow_repository::update($flowid, [
        'name' => trim($data->name),
        'description' => trim($data->description ?? ''),
    ]);

    redirect(
        new moodle_url('/admin/tool/flowboard/edit.php', ['id' => $flowid]),
        get_string('flow:saved', 'tool_flowboard', (object) ['name' => format_string($data->name), 'capabilities' => '']),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading($flow !== null ? format_string($flow->name) : get_string('flow:new', 'tool_flowboard'));
$form->display();

if ($flow !== null) {
    $page = new editor_page((int) $flow->id);
    echo $OUTPUT->render_from_template('tool_flowboard/editor', $page->export_for_template($OUTPUT));
    $PAGE->requires->js_call_amd('tool_flowboard/canvas/main', 'init', [(int) $flow->id]);
}

echo $OUTPUT->footer();
