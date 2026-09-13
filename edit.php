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
 * Creates or edits a flow: a trigger, an optional question about it, and one
 * action.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_flowboard\form\flow_form;
use tool_flowboard\local\actor\actor_repository;
use tool_flowboard\local\flow\flow_editor;
use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\flow\flow_templates;
use tool_flowboard\local\flow\graph_repository;

admin_externalpage_setup('tool_flowboard_index');

$id = optional_param('id', 0, PARAM_INT);
$template = optional_param('template', '', PARAM_ALPHANUMEXT);
$listurl = new moodle_url('/admin/tool/flowboard/index.php');
$flow = null;
$defaults = ['flowid' => 0];

if ($id !== 0) {
    $flow = flow_repository::get($id);

    if ($flow === null) {
        throw new moodle_exception('flow:error_notfound', 'tool_flowboard');
    }

    $defaults['flowid'] = $flow->id;
    $defaults['name'] = $flow->name;
    $defaults['idnumber'] = $flow->idnumber;
    $defaults['description'] = $flow->description;

    if ($flow->currentversionid) {
        $graph = graph_repository::graph((int) $flow->currentversionid);
        $read = flow_editor::from_graph($graph);

        if (!$read['representable']) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($flow->name));
            echo $OUTPUT->notification(get_string('flow:error_notrepresentable', 'tool_flowboard'), 'warning');
            echo $OUTPUT->continue_button($listurl);
            echo $OUTPUT->footer();
            exit;
        }

        $defaults += $read['data'];
    }
} else if ($template !== '' && in_array($template, flow_templates::all(), true)) {
    $defaults += flow_templates::data($template);
}

$PAGE->set_url('/admin/tool/flowboard/edit.php', array_filter(['id' => $id, 'template' => $template]));
$PAGE->set_heading($flow !== null ? format_string($flow->name) : get_string('flow:new', 'tool_flowboard'));

$form = new flow_form(null, ['flowid' => $id]);
$form->set_data((object) $defaults);

if ($form->is_cancelled()) {
    redirect($listurl);
}

if ($data = $form->get_data()) {
    $graph = flow_editor::to_graph($data);

    try {
        if ($id === 0) {
            $flow = \tool_flowboard\local\flow\flow_repository::create(
                trim($data->idnumber),
                trim($data->name),
                trim($data->description ?? '')
            );
        } else {
            flow_repository::update($id, [
                'name' => trim($data->name),
                'description' => trim($data->description ?? ''),
            ]);
            $flow = flow_repository::get($id);
        }

        graph_repository::publish((int) $flow->id, $graph);
    } catch (\moodle_exception $e) {
        // The flow (and, if it was new, its draft row) is left exactly where
        // it was before this attempt: publish() checks before writing
        // anything, so there is nothing here to undo.
        \core\notification::error($e->getMessage());
        $form->set_data((object) (['flowid' => (int) ($flow->id ?? 0)] + (array) $data));

        echo $OUTPUT->header();
        echo $OUTPUT->heading($flow !== null ? format_string($flow->name) : get_string('flow:new', 'tool_flowboard'));
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }

    $actor = actor_repository::for_flow((int) $flow->id);
    $capabilities = $actor !== null ? actor_repository::capabilities($actor) : [];
    $message = $capabilities === []
        ? get_string('flow:savednocapabilities', 'tool_flowboard', format_string($flow->name))
        : get_string('flow:saved', 'tool_flowboard', (object) [
            'name' => format_string($flow->name),
            'capabilities' => implode(', ', $capabilities),
        ]);

    redirect($listurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($flow !== null ? format_string($flow->name) : get_string('flow:new', 'tool_flowboard'));
$form->display();
echo $OUTPUT->footer();
