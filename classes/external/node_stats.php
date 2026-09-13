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
use tool_flowboard\local\run\run_repository;

/**
 * How each node of a flow has been doing lately — the small ok/fail badge
 * the canvas draws on every node.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class node_stats extends external_api {
    /**
     * What this service accepts.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'flowid' => new external_value(PARAM_INT, 'The flow being edited'),
            'days' => new external_value(PARAM_INT, 'How far back to look', VALUE_DEFAULT, 7),
        ]);
    }

    /**
     * The counts, as JSON keyed by node key.
     *
     * @param int $flowid
     * @param int $days
     * @return array
     */
    public static function execute(int $flowid, int $days = 7): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'flowid' => $flowid,
            'days' => $days,
        ]);

        self::validate_context(\context_system::instance());
        require_capability('tool/flowboard:manage', \context_system::instance());

        $stats = run_repository::node_stats($params['flowid'], max(1, $params['days']));

        return ['stats' => json_encode($stats)];
    }

    /**
     * What this service returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'stats' => new external_value(PARAM_RAW, 'Node key => {ok, failed, skipped}, as JSON'),
        ]);
    }
}
