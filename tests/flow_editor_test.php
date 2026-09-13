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

namespace tool_flowboard;

use tool_flowboard\local\flow\flow_editor;
use tool_flowboard\local\node\action_forum_subscribe;
use tool_flowboard\local\node\trigger_event;

/**
 * Turning the accessible editor's fields into a graph, and back.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\flow\flow_editor
 */
final class flow_editor_test extends \advanced_testcase {
    /**
     * With no condition, the graph is a straight line: trigger to action.
     */
    public function test_no_condition_builds_a_straight_line(): void {
        $graph = flow_editor::to_graph((object) [
            'eventname' => '\core\event\role_assigned',
            'subject' => trigger_event::SUBJECT_RELATED,
            'conditionfield' => '',
            'conditionoperator' => 'equals',
            'conditionvalue' => '',
            'actiontype' => action_forum_subscribe::TYPE,
            'matchfield' => 'idnumber',
            'matchoperator' => 'contains',
            'pattern' => 'FORO',
        ]);

        $this->assertCount(2, $graph['nodes']);
        $this->assertCount(1, $graph['edges']);
        $this->assertSame('trigger', $graph['edges'][0]['from']);
        $this->assertSame('action', $graph['edges'][0]['to']);
        $this->assertSame('out', $graph['edges'][0]['port']);
    }

    /**
     * A condition inserts itself between the trigger and the action, wired to
     * the action by the "true" port — the action only happens when the
     * question is answered yes.
     */
    public function test_a_condition_sits_between_trigger_and_action(): void {
        $graph = flow_editor::to_graph((object) [
            'eventname' => '\local_coursestate\event\course_state_updated',
            'subject' => trigger_event::SUBJECT_RELATED,
            'conditionfield' => 'other.state',
            'conditionoperator' => 'notin',
            'conditionvalue' => 'OPEN_STARTED,FINALIZED',
            'actiontype' => 'action_forum_unsubscribe',
            'matchfield' => 'name',
            'matchoperator' => 'contains',
            'pattern' => 'general',
        ]);

        $this->assertCount(3, $graph['nodes']);
        $edgesbyfrom = [];

        foreach ($graph['edges'] as $edge) {
            $edgesbyfrom[$edge['from']] = $edge;
        }

        $this->assertSame('condition', $edgesbyfrom['trigger']['to']);
        $this->assertSame('action', $edgesbyfrom['condition']['to']);
        $this->assertSame('true', $edgesbyfrom['condition']['port']);
    }

    /**
     * A graph built by this editor reads back into the exact fields it was
     * built from.
     */
    public function test_a_built_graph_reads_back_into_the_same_fields(): void {
        $original = (object) [
            'eventname' => '\core\event\role_assigned',
            'subject' => trigger_event::SUBJECT_ACTOR,
            'conditionfield' => 'objectid',
            'conditionoperator' => 'in',
            'conditionvalue' => '3,4',
            'actiontype' => action_forum_subscribe::TYPE,
            'matchfield' => 'idnumber',
            'matchoperator' => 'startswith',
            'pattern' => 'FORO-',
        ];

        $read = flow_editor::from_graph(flow_editor::to_graph($original));

        $this->assertTrue($read['representable']);
        $this->assertSame((array) $original, $read['data']);
    }

    /**
     * A graph this editor did not build — an imported one, or one a later
     * phase's visual canvas drew with a shape this form has no fields for —
     * is reported as such rather than quietly narrowed down.
     */
    public function test_a_graph_with_an_unexpected_shape_is_not_representable(): void {
        $graph = [
            'nodes' => [
                ['key' => 'trigger', 'type' => trigger_event::TYPE, 'config' => []],
                ['key' => 'extra', 'type' => 'condition_payload', 'config' => []],
                ['key' => 'another', 'type' => 'condition_payload', 'config' => []],
                ['key' => 'action', 'type' => action_forum_subscribe::TYPE, 'config' => []],
            ],
            'edges' => [],
        ];

        $this->assertFalse(flow_editor::from_graph($graph)['representable']);
    }

    /**
     * A graph made of stdClass nodes, the shape {@see graph_repository::nodes()}
     * actually returns, is read the same way as plain arrays.
     */
    public function test_it_reads_stdclass_nodes_too(): void {
        $triggerconfig = ['eventname' => '\x', 'subject' => 'userid'];
        $actionconfig = ['match' => 'name', 'operator' => 'exact', 'pattern' => 'X'];
        $graph = [
            'nodes' => [
                (object) ['key' => 'trigger', 'type' => trigger_event::TYPE, 'config' => $triggerconfig],
                (object) ['key' => 'action', 'type' => action_forum_subscribe::TYPE, 'config' => $actionconfig],
            ],
        ];

        $read = flow_editor::from_graph($graph);

        $this->assertTrue($read['representable']);
        $this->assertSame('\x', $read['data']['eventname']);
        $this->assertSame('X', $read['data']['pattern']);
    }
}
