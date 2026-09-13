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

namespace tool_flowboard\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_flowboard\local\flow\flow_repository;

/**
 * The canvas's own autosave: what is drawn right now, kept, but never run.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_draft extends external_api {
    /**
     * What this service accepts.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'flowid' => new external_value(PARAM_INT, 'The flow being edited'),
            'graph' => new external_value(PARAM_RAW, 'nodes, edges and comments, as JSON'),
        ]);
    }

    /**
     * Saves the drawing as-is, refusing only what could never be a graph at
     * all — a half-drawn edge or an unfinished node is still worth keeping
     * for the next autosave to build on.
     *
     * @param int $flowid
     * @param string $graph
     * @return array
     */
    public static function execute(int $flowid, string $graph): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'flowid' => $flowid,
            'graph' => $graph,
        ]);

        self::validate_context(\context_system::instance());
        require_capability('tool/flowboard:manage', \context_system::instance());

        if (flow_repository::get($params['flowid']) === null) {
            throw new \moodle_exception('flow:error_notfound', 'tool_flowboard');
        }

        $decoded = json_decode($params['graph'], true);

        if (!is_array($decoded) || !isset($decoded['nodes']) || !is_array($decoded['nodes'])) {
            throw new \moodle_exception('error:graphnode', 'tool_flowboard');
        }

        // A draft may be empty, half-connected, or missing a trigger — that is
        // exactly what "not published yet" means — so it is kept as drawn
        // rather than checked against publish()'s stricter rules.
        flow_repository::save_draft($params['flowid'], [
            'nodes' => $decoded['nodes'],
            'edges' => is_array($decoded['edges'] ?? null) ? $decoded['edges'] : [],
            'comments' => is_array($decoded['comments'] ?? null) ? $decoded['comments'] : [],
        ]);

        return ['saved' => true];
    }

    /**
     * What this service returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'saved' => new external_value(PARAM_BOOL, 'Always true; a failure throws instead'),
        ]);
    }
}
