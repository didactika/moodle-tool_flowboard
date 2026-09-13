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
 * Deletes a flow, once its own confirmation page has said yes.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_flowboard\local\flow\flow_repository;

admin_externalpage_setup('tool_flowboard_index');

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$listurl = new moodle_url('/admin/tool/flowboard/index.php');

$flow = flow_repository::get($id);

if ($flow === null) {
    redirect($listurl);
}

if ($confirm && confirm_sesskey()) {
    flow_repository::delete($id);
    redirect(
        $listurl,
        get_string('flow:deleted', 'tool_flowboard', format_string($flow->name)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('flow:delete', 'tool_flowboard'));
echo $OUTPUT->confirm(
    get_string('flow:deleteconfirm', 'tool_flowboard', format_string($flow->name)),
    new moodle_url('/admin/tool/flowboard/delete.php', ['id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
    $listurl
);
echo $OUTPUT->footer();
