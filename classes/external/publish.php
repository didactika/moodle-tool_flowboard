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
use tool_flowboard\local\flow\graph_repository;

/**
 * Freezes the canvas's own drawing into a new, immutable version — the only
 * way a flow's drawing ever starts reacting to anything.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publish extends external_api {
    /**
     * What this service accepts.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'flowid' => new external_value(PARAM_INT, 'The flow being published'),
            'graph' => new external_value(PARAM_RAW, 'nodes, edges and comments, as JSON'),
            'note' => new external_value(PARAM_TEXT, 'What changed, for the version history', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Validates the drawing the way {@see graph_repository::publish()} always
     * has, publishes it, and returns what its actor now needs.
     *
     * @param int $flowid
     * @param string $graph
     * @param string $note
     * @return array
     */
    public static function execute(int $flowid, string $graph, string $note = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'flowid' => $flowid,
            'graph' => $graph,
            'note' => $note,
        ]);

        self::validate_context(\context_system::instance());
        require_capability('tool/flowboard:manage', \context_system::instance());

        if (flow_repository::get($params['flowid']) === null) {
            throw new \moodle_exception('flow:error_notfound', 'tool_flowboard');
        }

        $decoded = json_decode($params['graph'], true);

        if (!is_array($decoded)) {
            throw new \moodle_exception('error:graphnode', 'tool_flowboard');
        }

        $versionid = graph_repository::publish($params['flowid'], [
            'nodes' => $decoded['nodes'] ?? [],
            'edges' => $decoded['edges'] ?? [],
            'comments' => $decoded['comments'] ?? [],
        ], $params['note']);

        return ['versionid' => $versionid];
    }

    /**
     * What this service returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'versionid' => new external_value(PARAM_INT, 'The version just published'),
        ]);
    }
}
