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
 * Every flow the site has.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\output\flow_list_page;

admin_externalpage_setup('tool_flowboard_index');

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

if ($id !== 0 && $action !== '') {
    require_sesskey();

    $flow = flow_repository::get($id);

    if ($flow === null) {
        throw new \moodle_exception('flow:error_notfound', 'tool_flowboard');
    }

    if ($action === 'live') {
        flow_repository::set_status($id, flow_repository::STATUS_LIVE);
        redirect(
            new moodle_url('/admin/tool/flowboard/index.php'),
            get_string('flow:nowlive', 'tool_flowboard', format_string($flow->name)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($action === 'pause') {
        flow_repository::set_status($id, flow_repository::STATUS_PAUSED);
        redirect(
            new moodle_url('/admin/tool/flowboard/index.php'),
            get_string('flow:nowpaused', 'tool_flowboard', format_string($flow->name)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$page = new flow_list_page();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('flow:heading', 'tool_flowboard'));
echo $OUTPUT->render_from_template('tool_flowboard/flow_list', $page->export_for_template($OUTPUT));
echo $OUTPUT->footer();
