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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_flowboard\local\event\event_seen_repository;
use tool_flowboard\local\run\engine;

/**
 * "Probar": walks a drawing that has not even been saved yet against a sample
 * of the event it listens for, so the canvas can light up the path it took.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_run extends external_api {
    /** @var string A placeholder value looks like this in an anonymised sample. */
    private const PLACEHOLDER_PATTERN = '/^\((\w+)\)$/';

    /**
     * What this service accepts.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'eventname' => new external_value(PARAM_RAW_TRIMMED, 'The trigger node\'s own event'),
            'graph' => new external_value(PARAM_RAW, 'nodes and edges, as drawn right now, as JSON'),
        ]);
    }

    /**
     * Runs the drawing, in memory only, against one sample of the event.
     *
     * @param string $eventname
     * @param string $graph
     * @return array
     */
    public static function execute(string $eventname, string $graph): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'eventname' => $eventname,
            'graph' => $graph,
        ]);

        self::validate_context(\context_system::instance());
        require_capability('tool/flowboard:manage', \context_system::instance());

        $decoded = json_decode($params['graph'], true);

        if (!is_array($decoded) || !isset($decoded['nodes']) || !is_array($decoded['nodes'])) {
            throw new \moodle_exception('error:graphnode', 'tool_flowboard');
        }

        $event = self::sample_event($params['eventname']);
        $visited = engine::preview([
            'nodes' => $decoded['nodes'],
            'edges' => is_array($decoded['edges'] ?? null) ? $decoded['edges'] : [],
        ], $event);

        $path = [];

        foreach ($visited as $nodekey => $outcome) {
            $path[] = [
                'nodekey' => (string) $nodekey,
                'status' => (string) $outcome['status'],
                'port' => $outcome['port'] ?? '',
                'summary' => json_encode($outcome['summary']),
                'error' => $outcome['error'] ?? '',
            ];
        }

        return ['path' => $path];
    }

    /**
     * A realistic-shaped event to test against: what has actually been seen
     * of it, with the parts the catalogue anonymised for storage — an id, a
     * name — filled back in with a value that is a real number rather than
     * the placeholder it was replaced with, so a node reading `subjectid`
     * out of it does not simply stop for want of one.
     *
     * @param string $eventname
     * @return array
     */
    private static function sample_event(string $eventname): array {
        $seen = event_seen_repository::get($eventname);

        if ($seen === null) {
            return ['eventname' => $eventname];
        }

        $sample = json_decode($seen->sample, true);

        return is_array($sample) ? self::fill_placeholders($sample) : ['eventname' => $eventname];
    }

    /**
     * Replaces every `(type)` placeholder in a sample with an ordinary value
     * of that type, recursively.
     *
     * @param array $sample
     * @return array
     */
    private static function fill_placeholders(array $sample): array {
        $filled = [];

        foreach ($sample as $key => $value) {
            if (is_array($value)) {
                $filled[$key] = self::fill_placeholders($value);

                continue;
            }

            if (is_string($value) && preg_match(self::PLACEHOLDER_PATTERN, $value, $matches) === 1) {
                $filled[$key] = self::placeholder_value($matches[1]);

                continue;
            }

            $filled[$key] = $value;
        }

        return $filled;
    }

    /**
     * One value to stand in for a type a sample only named.
     *
     * @param string $type As PHP's own `gettype()` spells it.
     * @return mixed
     */
    private static function placeholder_value(string $type) {
        switch ($type) {
            case 'integer':
                return 1;

            case 'double':
                return 1.0;

            case 'boolean':
                return true;

            case 'array':
                return [];

            default:
                return 'sample';
        }
    }

    /**
     * What this service returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'path' => new external_multiple_structure(
                new external_single_structure([
                    'nodekey' => new external_value(PARAM_RAW, 'Which node'),
                    'status' => new external_value(PARAM_ALPHA, 'ok, stopped or failed'),
                    'port' => new external_value(PARAM_RAW, 'The way out it left by, empty when it failed'),
                    'summary' => new external_value(PARAM_RAW, 'What it did, as JSON'),
                    'error' => new external_value(PARAM_RAW, 'Empty unless it failed'),
                ]),
                'One entry per node the run actually visited, in order'
            ),
        ]);
    }
}
