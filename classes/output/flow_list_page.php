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

namespace tool_flowboard\output;

use renderer_base;
use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\run\run_repository;

/**
 * Every flow the site has, what it is doing, and the plain links to manage it.
 *
 * A row here answers the question this whole screen exists for: is this flow
 * running, and did it last succeed. Everything else — editing, history,
 * pausing — is one link away, each of them a plain GET a screen reader or a
 * keyboard-only visitor can use exactly as anyone else would.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_list_page implements \renderable, \templatable {
    /**
     * What the template draws.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $rows = [];

        foreach (flow_repository::all() as $flow) {
            $rows[] = $this->row($flow);
        }

        return [
            'rows' => $rows,
            'hasrows' => $rows !== [],
            'newurl' => (new \moodle_url('/admin/tool/flowboard/edit.php'))->out(false),
        ];
    }

    /**
     * One flow's row.
     *
     * @param \stdClass $flow
     * @return array
     */
    private function row(\stdClass $flow): array {
        $runs = run_repository::recent((int) $flow->id, 1);
        $lastrun = reset($runs) ?: null;
        $sesskeyed = ['id' => $flow->id, 'sesskey' => sesskey()];

        return [
            'id' => $flow->id,
            'name' => format_string($flow->name),
            'status' => $flow->status,
            'statuslabel' => get_string('flow:status_' . $flow->status, 'tool_flowboard'),
            'islive' => $flow->status === flow_repository::STATUS_LIVE,
            'ispaused' => $flow->status === flow_repository::STATUS_PAUSED,
            'isdraft' => $flow->status === flow_repository::STATUS_DRAFT,
            'lastrun' => $lastrun !== null
                ? userdate((int) $lastrun->timestarted, get_string('strftimedatetimeshort', 'core_langconfig'))
                : get_string('flow:neverrun', 'tool_flowboard'),
            'editurl' => (new \moodle_url('/admin/tool/flowboard/edit.php', ['id' => $flow->id]))->out(false),
            'historyurl' => (new \moodle_url('/admin/tool/flowboard/history.php', ['id' => $flow->id]))->out(false),
            'liveurl' => (new \moodle_url('/admin/tool/flowboard/index.php', $sesskeyed + ['action' => 'live']))->out(false),
            'pauseurl' => (new \moodle_url('/admin/tool/flowboard/index.php', $sesskeyed + ['action' => 'pause']))->out(false),
            'deleteurl' => (new \moodle_url('/admin/tool/flowboard/delete.php', ['id' => $flow->id]))->out(false),
        ];
    }
}
