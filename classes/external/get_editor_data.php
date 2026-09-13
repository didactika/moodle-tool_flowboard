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
use tool_flowboard\local\event\event_catalogue;
use tool_flowboard\local\event\event_seen_repository;
use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\flow\graph_repository;
use tool_flowboard\local\node\node_registry;

/**
 * Everything the canvas needs in one call: the flow's own drawing, and
 * everything it can be drawn from.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_editor_data extends external_api {
    /**
     * What this service accepts.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'flowid' => new external_value(PARAM_INT, 'The flow being edited, or 0 for a new one'),
        ]);
    }

    /**
     * The drawing, and the palette to draw more of it with.
     *
     * @param int $flowid
     * @return array
     */
    public static function execute(int $flowid): array {
        ['flowid' => $flowid] = self::validate_parameters(self::execute_parameters(), ['flowid' => $flowid]);

        self::validate_context(\context_system::instance());
        require_capability('tool/flowboard:manage', \context_system::instance());

        $graph = self::graph_for($flowid);
        $nodetypes = [];

        foreach (node_registry::all() as $type => $class) {
            $nodetypes[$type] = [
                'label' => $class::name(),
                'istrigger' => $class::is_trigger(),
                'acts' => $class::acts(),
            ] + node_registry::schema_for($type);
        }

        $events = [];
        $eventfields = [];

        foreach (event_catalogue::all() as $eventname => $event) {
            $events[$eventname] = $event;
            $seen = event_seen_repository::get($eventname);
            $observed = $seen !== null ? json_decode($seen->payloadkeys, true) : [];
            $eventfields[$eventname] = array_values(array_unique(array_merge(
                event_catalogue::STANDARD_FIELDS,
                $observed
            )));
        }

        return [
            'graph' => json_encode($graph),
            'nodetypes' => json_encode($nodetypes),
            'events' => json_encode($events),
            'eventfields' => json_encode($eventfields),
            'haddraft' => $flowid !== 0 && flow_repository::draft($flowid) !== null,
        ];
    }

    /**
     * What the canvas should open with: unpublished work first, then the
     * published drawing, then nothing at all for a flow that has neither.
     *
     * @param int $flowid
     * @return array{nodes: array, edges: array, comments: array}
     */
    private static function graph_for(int $flowid): array {
        if ($flowid === 0) {
            return ['nodes' => [], 'edges' => [], 'comments' => []];
        }

        $flow = flow_repository::get($flowid);

        if ($flow === null) {
            throw new \moodle_exception('flow:error_notfound', 'tool_flowboard');
        }

        $draft = flow_repository::draft($flowid);

        if ($draft !== null) {
            return $draft + ['nodes' => [], 'edges' => [], 'comments' => []];
        }

        if ($flow->currentversionid !== null) {
            return graph_repository::graph((int) $flow->currentversionid);
        }

        return ['nodes' => [], 'edges' => [], 'comments' => []];
    }

    /**
     * What this service returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'graph' => new external_value(PARAM_RAW, 'The drawing to open with, as JSON: nodes, edges, comments'),
            'nodetypes' => new external_value(PARAM_RAW, 'Every kind of node, as JSON: type => label, config schema, produces'),
            'events' => new external_value(PARAM_RAW, 'The event catalogue, as JSON'),
            'eventfields' => new external_value(PARAM_RAW, 'Known payload keys per event, as JSON'),
            'haddraft' => new external_value(PARAM_BOOL, 'Whether this open came from unpublished work'),
        ]);
    }
}
