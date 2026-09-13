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
use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\flow\graph_repository;
use tool_flowboard\local\run\run_repository;

/**
 * The small ok/fail badge the canvas draws on every node.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\external\node_stats
 */
final class node_stats_test extends \advanced_testcase {
    /**
     * The counts come back keyed by node, as JSON.
     */
    public function test_it_answers_with_the_counts_as_json(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        $versionid = graph_repository::publish((int) $flow->id, [
            'nodes' => [['key' => 'trigger', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']]],
            'edges' => [],
        ]);
        $runid = run_repository::start(flow_repository::get((int) $flow->id), $versionid);
        run_repository::record_node($runid, 'trigger', 'trigger_event', 'ok');

        $result = external_api::clean_returnvalue(
            node_stats::execute_returns(),
            node_stats::execute((int) $flow->id, 7)
        );
        $stats = json_decode($result['stats'], true);

        $this->assertSame(['ok' => 1, 'failed' => 0, 'skipped' => 0], $stats['trigger']);
    }

    /**
     * A flow with no history at all answers with nothing, not an error.
     */
    public function test_a_flow_with_no_history_answers_with_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');

        $result = external_api::clean_returnvalue(
            node_stats::execute_returns(),
            node_stats::execute((int) $flow->id, 7)
        );

        $this->assertSame([], json_decode($result['stats'], true));
    }
}
