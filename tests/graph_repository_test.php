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

use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\flow\graph_repository;

/**
 * Publishing a drawing, and getting it back exactly as it was.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\flow\graph_repository
 */
final class graph_repository_test extends \advanced_testcase {
    /** @var \stdClass The flow being drawn. */
    private \stdClass $flow;

    /**
     * A flow to hang versions off.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
    }

    /**
     * Publishing stores the drawing, explodes it into rows, and points the
     * flow at the version it now runs.
     */
    public function test_publishing_stores_the_drawing_and_its_pieces(): void {
        $versionid = graph_repository::publish((int) $this->flow->id, $this->graph());

        $nodes = graph_repository::nodes($versionid);
        $edges = graph_repository::edges($versionid);

        $this->assertSame(['trigger', 'check', 'subscribe'], array_keys($nodes));
        $this->assertSame('trigger_event', $nodes['trigger']->type);
        $this->assertSame('\core\event\role_assigned', $nodes['trigger']->config['eventname']);
        $this->assertCount(1, $edges['trigger']);
        $this->assertSame('check', $edges['trigger'][0]->tonode);
        $this->assertSame(graph_repository::PORT_TRUE, $edges['check'][0]->fromport);

        $flow = flow_repository::get((int) $this->flow->id);
        $this->assertEquals($versionid, $flow->currentversionid);
    }

    /**
     * The drawing comes back as it was saved, positions and all: it is the
     * editor's own file, not something rebuilt from rows.
     */
    public function test_the_drawing_comes_back_untouched(): void {
        $versionid = graph_repository::publish((int) $this->flow->id, $this->graph());

        $graph = graph_repository::graph($versionid);

        $this->assertSame($this->graph()['nodes'], $graph['nodes']);
        $this->assertSame($this->graph()['edges'], $graph['edges']);
    }

    /**
     * Publishing again leaves the previous version alone. That is the whole
     * point: a run from March is explained by March's drawing.
     */
    public function test_a_published_version_is_never_edited(): void {
        $first = graph_repository::publish((int) $this->flow->id, $this->graph(), 'First');

        $changed = $this->graph();
        $changed['nodes'][0]['config']['eventname'] = '\core\event\user_enrolment_created';
        $second = graph_repository::publish((int) $this->flow->id, $changed, 'Second');

        $this->assertNotEquals($first, $second);
        $this->assertSame(
            '\core\event\role_assigned',
            graph_repository::nodes($first)['trigger']->config['eventname'],
            'The first version still says what it always said.'
        );
        $this->assertCount(2, graph_repository::versions((int) $this->flow->id));
        $this->assertSame(2, (int) graph_repository::version($second)->versionnumber);
    }

    /**
     * A drawing with no nodes is not a drawing.
     */
    public function test_an_empty_drawing_is_refused(): void {
        $this->expectException(\moodle_exception::class);
        graph_repository::publish((int) $this->flow->id, ['nodes' => [], 'edges' => []]);
    }

    /**
     * Two nodes with the same key would make the edges ambiguous.
     */
    public function test_two_nodes_cannot_share_a_key(): void {
        $this->expectException(\moodle_exception::class);
        graph_repository::publish((int) $this->flow->id, [
            'nodes' => [
                ['key' => 'a', 'type' => 'trigger_event'],
                ['key' => 'a', 'type' => 'action_forum_subscribe'],
            ],
            'edges' => [],
        ]);
    }

    /**
     * An edge that leads nowhere is refused at the door, rather than becoming
     * a run that stops halfway with no explanation.
     */
    public function test_an_edge_must_lead_somewhere_real(): void {
        $this->expectException(\moodle_exception::class);
        graph_repository::publish((int) $this->flow->id, [
            'nodes' => [['key' => 'a', 'type' => 'trigger_event']],
            'edges' => [['from' => 'a', 'to' => 'somewhere-else']],
        ]);
    }

    /**
     * A small flow: an event, a question about it, and something to do.
     *
     * @return array
     */
    private function graph(): array {
        return [
            'nodes' => [
                [
                    'key' => 'trigger',
                    'type' => 'trigger_event',
                    'config' => ['eventname' => '\core\event\role_assigned'],
                    'position' => ['x' => 0, 'y' => 0],
                ],
                [
                    'key' => 'check',
                    'type' => 'condition_payload',
                    'config' => ['field' => 'objectid', 'operator' => 'in', 'value' => '3,4'],
                    'position' => ['x' => 0, 'y' => 120],
                ],
                [
                    'key' => 'subscribe',
                    'type' => 'action_forum_subscribe',
                    'config' => ['match' => 'name', 'pattern' => 'Welcome'],
                    'position' => ['x' => 0, 'y' => 240],
                ],
            ],
            'edges' => [
                ['from' => 'trigger', 'port' => 'out', 'to' => 'check'],
                ['from' => 'check', 'port' => 'true', 'to' => 'subscribe'],
            ],
        ];
    }
}
