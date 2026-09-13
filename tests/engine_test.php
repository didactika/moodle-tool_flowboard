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

use tool_flowboard\local\run\engine;
use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\flow\graph_repository;
use tool_flowboard\local\run\run_repository;

/**
 * Walking a flow.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\run\engine
 */
final class engine_test extends \advanced_testcase {
    /** @var \stdClass The student the events are about. */
    private \stdClass $student;

    /**
     * Somebody for the flows to be about.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->student = $this->getDataGenerator()->create_user();
    }

    /**
     * The drawing decides the order, and every node is written down as it runs.
     */
    public function test_it_follows_the_drawing_and_writes_down_each_node(): void {
        [$flow, $versionid] = $this->a_flow($this->graph());

        $runid = engine::run($flow, $versionid, $this->event('OPEN_STARTED'));

        $run = run_repository::get($runid);
        $nodes = array_values(run_repository::nodes($runid));

        $this->assertSame(run_repository::STATUS_OK, $run->status);
        $this->assertSame(['trigger', 'check', 'after'], array_column($nodes, 'nodekey'));
        $this->assertEquals($this->student->id, $run->subjectid, 'The trigger settled who this is about.');
    }

    /**
     * A question answered the other way takes the other path.
     */
    public function test_a_condition_sends_the_run_the_other_way(): void {
        [$flow, $versionid] = $this->a_flow($this->graph());

        $runid = engine::run($flow, $versionid, $this->event('CLOSED'));
        $nodes = array_values(run_repository::nodes($runid));

        $this->assertSame(['trigger', 'check'], array_column($nodes, 'nodekey'), 'Nothing is drawn on the other port.');
        $this->assertFalse(json_decode($nodes[1]->summary, true)['matched']);
    }

    /**
     * Which person a run is about is the flow's choice, because Moodle is not
     * consistent about it and cannot be.
     */
    public function test_the_flow_says_which_person_the_event_is_about(): void {
        $actor = $this->getDataGenerator()->create_user();

        [$flow, $versionid] = $this->a_flow([
            'nodes' => [[
                'key' => 'trigger',
                'type' => 'trigger_event',
                'config' => ['eventname' => '\core\event\course_viewed', 'subject' => 'userid'],
            ]],
            'edges' => [],
        ]);

        $runid = engine::run($flow, $versionid, [
            'eventname' => '\core\event\course_viewed',
            'userid' => (int) $actor->id,
            'relateduserid' => (int) $this->student->id,
        ]);

        $this->assertEquals(
            $actor->id,
            run_repository::get($runid)->subjectid,
            'This flow asked for the person who acted, not the person acted upon.'
        );
    }

    /**
     * An event that does not name the person the flow asked for stops the run
     * rather than guessing at the other field.
     */
    public function test_a_missing_subject_stops_the_run(): void {
        [$flow, $versionid] = $this->a_flow([
            'nodes' => [[
                'key' => 'trigger',
                'type' => 'trigger_event',
                'config' => ['eventname' => '\core\event\course_viewed', 'subject' => 'relateduserid'],
            ]],
            'edges' => [],
        ]);

        $runid = engine::run($flow, $versionid, ['eventname' => '\core\event\course_viewed', 'userid' => 5]);

        $this->assertSame(run_repository::STATUS_SKIPPED, run_repository::get($runid)->status);
        $this->assertSame('nosubject', json_decode(
            array_values(run_repository::nodes($runid))[0]->summary,
            true
        )['reason']);
    }

    /**
     * A flow drawn against a node that no longer exists fails where somebody
     * will see it, instead of doing nothing quietly.
     *
     * Publishing a graph like this is refused outright these days — this is
     * what happens to one published while the node's own plugin still
     * existed, which nothing here can un-publish after the fact.
     */
    public function test_a_node_that_no_longer_exists_fails_loudly(): void {
        global $DB;

        [$flow, $versionid] = $this->a_flow([
            'nodes' => [
                ['key' => 'trigger', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']],
                ['key' => 'gone', 'type' => 'condition_payload', 'config' => ['field' => 'userid', 'operator' => 'notempty']],
            ],
            'edges' => [['from' => 'trigger', 'port' => 'out', 'to' => 'gone']],
        ]);

        $DB->set_field(
            'tool_flowboard_node',
            'type',
            'action_from_a_plugin_since_removed',
            ['versionid' => $versionid, 'nodekey' => 'gone']
        );

        $runid = engine::run($flow, $versionid, $this->event('OPEN_STARTED'));
        $nodes = array_values(run_repository::nodes($runid));

        $this->assertSame(run_repository::STATUS_FAILED, run_repository::get($runid)->status);
        $this->assertSame('unknownnodetype', end($nodes)->error);
    }

    /**
     * A drawing with no starting point cannot be run, and says so.
     *
     * Publishing one like this is refused outright these days — this is what
     * happens to a flow whose own trigger's plugin disappeared after it was
     * published, which nothing here can un-publish after the fact.
     */
    public function test_a_drawing_with_no_starting_point_fails(): void {
        global $DB;

        [$flow, $versionid] = $this->a_flow([
            'nodes' => [
                ['key' => 'trigger', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']],
                ['key' => 'check', 'type' => 'condition_payload', 'config' => ['field' => 'userid', 'operator' => 'notempty']],
            ],
            'edges' => [['from' => 'trigger', 'port' => 'out', 'to' => 'check']],
        ]);

        $DB->set_field(
            'tool_flowboard_node',
            'type',
            'condition_payload',
            ['versionid' => $versionid, 'nodekey' => 'trigger']
        );

        $runid = engine::run($flow, $versionid, $this->event('OPEN_STARTED'));

        $this->assertSame(run_repository::STATUS_FAILED, run_repository::get($runid)->status);
        $this->assertSame('nostartingnode', array_values(run_repository::nodes($runid))[0]->error);
    }

    /**
     * Two nodes pointing at each other is two clicks in an editor, and the
     * engine cannot tell that loop from one that would end. So it counts.
     */
    public function test_a_drawing_that_loops_is_stopped(): void {
        [$flow, $versionid] = $this->a_flow([
            'nodes' => [
                ['key' => 'trigger', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']],
                ['key' => 'check', 'type' => 'condition_payload', 'config' => ['field' => 'userid', 'operator' => 'notempty']],
            ],
            'edges' => [
                ['from' => 'trigger', 'port' => 'out', 'to' => 'check'],
                ['from' => 'check', 'port' => 'true', 'to' => 'check'],
            ],
        ]);

        $runid = engine::run($flow, $versionid, $this->event('OPEN_STARTED'));
        $nodes = run_repository::nodes($runid);

        $this->assertSame(run_repository::STATUS_FAILED, run_repository::get($runid)->status);
        $this->assertLessThan(105, count($nodes), 'It stopped counting rather than running forever.');
        $this->assertSame('toomanysteps', end($nodes)->error);
    }

    /**
     * How deep inside flows a run is travels with it, which is what stops a
     * chain of flows going round forever.
     */
    public function test_a_run_remembers_how_deep_it_is(): void {
        [$flow, $versionid] = $this->a_flow($this->graph());

        $runid = engine::run($flow, $versionid, $this->event('OPEN_STARTED'), ['depth' => 2]);

        $this->assertSame(2, (int) run_repository::get($runid)->depth);
        $this->assertSame(0, engine::current_depth(), 'Outside a run, nothing is in progress.');
    }

    /**
     * A preview walks a drawing that was never published at all, and writes
     * nothing down — it says which port each node left by, not why.
     */
    public function test_a_preview_walks_an_unpublished_drawing_and_writes_nothing_down(): void {
        global $DB;

        $visited = engine::preview($this->graph(), $this->event('OPEN_STARTED'));

        $this->assertSame(['trigger', 'check', 'after'], array_keys($visited));
        $this->assertSame('out', $visited['trigger']['port']);
        $this->assertSame('true', $visited['check']['port']);
        $this->assertSame(0, $DB->count_records('tool_flowboard_run'), 'A preview is not a run.');
        $this->assertSame(0, $DB->count_records('tool_flowboard_run_node'));
    }

    /**
     * A preview follows a condition exactly as a real run would: the wrong
     * way out is a dead end, not a continuation.
     */
    public function test_a_preview_stops_where_a_condition_says_no(): void {
        $visited = engine::preview($this->graph(), $this->event('FINISHED'));

        $this->assertSame('false', $visited['check']['port']);
        $this->assertArrayNotHasKey('after', $visited, 'Nothing is drawn from the false port in this graph.');
    }

    /**
     * A published flow to run.
     *
     * @param array $graph
     * @return array{0: \stdClass, 1: int}
     */
    private function a_flow(array $graph): array {
        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        $versionid = graph_repository::publish((int) $flow->id, $graph);

        return [flow_repository::get((int) $flow->id), $versionid];
    }

    /**
     * A trigger, a question about the state, and a node after it.
     *
     * @return array
     */
    private function graph(): array {
        return [
            'nodes' => [
                [
                    'key' => 'trigger',
                    'type' => 'trigger_event',
                    'config' => ['eventname' => '\core\event\course_viewed', 'subject' => 'relateduserid'],
                ],
                [
                    'key' => 'check',
                    'type' => 'condition_payload',
                    'config' => ['field' => 'other.state', 'operator' => 'in', 'value' => 'OPEN_STARTED,FINALIZED'],
                ],
                [
                    'key' => 'after',
                    'type' => 'condition_payload',
                    'config' => ['field' => 'relateduserid', 'operator' => 'notempty'],
                ],
            ],
            'edges' => [
                ['from' => 'trigger', 'port' => 'out', 'to' => 'check'],
                ['from' => 'check', 'port' => 'true', 'to' => 'after'],
            ],
        ];
    }

    /**
     * An event shaped like the one local_coursestate fires.
     *
     * @param string $state
     * @return array
     */
    private function event(string $state): array {
        return [
            'eventname' => '\core\event\course_viewed',
            'userid' => 2,
            'relateduserid' => (int) $this->student->id,
            'courseid' => 7,
            'other' => ['state' => $state, 'previous_state' => 'OPEN_NOT_STARTED'],
        ];
    }
}
