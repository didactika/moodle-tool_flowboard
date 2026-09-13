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
use tool_flowboard\local\run\run_repository;

/**
 * One flow's history: what ran, over whom, and — for each run — what every
 * node in it did.
 *
 * This is the screen that answers "why is this student subscribed to this
 * forum". A run without its nodes would only say that something happened; the
 * node detail is what says why.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_history_page implements \renderable, \templatable {
    /** @var \stdClass The flow this history belongs to. */
    private \stdClass $flow;

    /**
     * Remembers which flow this history is about.
     *
     * @param \stdClass $flow
     */
    public function __construct(\stdClass $flow) {
        $this->flow = $flow;
    }

    /**
     * What the template draws.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $runs = run_repository::recent((int) $this->flow->id, 50);
        $rows = [];

        foreach ($runs as $run) {
            $rows[] = $this->row($run);
        }

        return [
            'flowname' => format_string($this->flow->name),
            'indexurl' => (new \moodle_url('/admin/tool/flowboard/index.php'))->out(false),
            'rows' => $rows,
            'hasrows' => $rows !== [],
        ];
    }

    /**
     * One run, with each of its nodes underneath it.
     *
     * @param \stdClass $run
     * @return array
     */
    private function row(\stdClass $run): array {
        global $DB;

        $subjectname = '';

        if (!empty($run->subjectid)) {
            $fields = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename';
            $user = $DB->get_record('user', ['id' => $run->subjectid], $fields);

            if ($user !== false) {
                $subjectname = fullname($user);
            }
        }

        $nodes = [];

        foreach (run_repository::nodes((int) $run->id) as $node) {
            $summary = $node->summary !== null ? json_decode($node->summary, true) : null;
            $nodes[] = [
                'nodekey' => $node->nodekey,
                'nodetype' => $node->nodetype,
                'status' => $node->status,
                'isfailed' => $node->status === 'failed',
                'summary' => $summary !== null ? $this->format_summary($summary) : '',
                'error' => $node->error,
            ];
        }

        return [
            'id' => $run->id,
            'when' => userdate((int) $run->timestarted, get_string('strftimedatetimeshort', 'core_langconfig')),
            'status' => $run->status,
            'statuslabel' => get_string('history:status_' . $run->status, 'tool_flowboard'),
            'isfailed' => $run->status === 'failed',
            'subjectname' => $subjectname !== '' ? $subjectname : get_string('history:nosubject', 'tool_flowboard'),
            'dryrun' => (bool) $run->dryrun,
            'nodes' => $nodes,
        ];
    }

    /**
     * A node's summary, as one readable line rather than raw JSON.
     *
     * @param array $summary
     * @return string
     */
    private function format_summary(array $summary): string {
        $parts = [];

        foreach ($summary as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $parts[] = $key . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : $value);
        }

        return implode(', ', $parts);
    }
}
