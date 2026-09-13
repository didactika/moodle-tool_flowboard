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

use tool_flowboard\local\flow_repository;
use tool_flowboard\local\graph_repository;
use tool_flowboard\local\run_repository;

/**
 * The history, which is the answer to "why did this happen to this person".
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\run_repository
 */
final class run_repository_test extends \advanced_testcase {
    /** @var \stdClass The flow being run. */
    private \stdClass $flow;

    /** @var int Its published version. */
    private int $versionid;

    /**
     * A published flow to run.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        $this->versionid = graph_repository::publish((int) $this->flow->id, [
            'nodes' => [['key' => 'trigger', 'type' => 'trigger_event', 'config' => []]],
            'edges' => [],
        ]);
    }

    /**
     * A run is written as it happens, so a run that dies halfway still says
     * what it managed to do.
     */
    public function test_a_run_records_each_node_as_it_goes(): void {
        $student = $this->getDataGenerator()->create_user();

        $runid = run_repository::start($this->flow, $this->versionid, [
            'eventname' => '\core\event\role_assigned',
            'eventdata' => ['objectid' => 3],
            'subjectid' => (int) $student->id,
        ]);

        run_repository::record_node($runid, 'trigger', 'trigger_event', 'ok', ['eventname' => 'role_assigned']);
        run_repository::record_node($runid, 'subscribe', 'action_forum_subscribe', 'ok', ['forums' => 2], null, 12);

        $run = run_repository::get($runid);
        $nodes = array_values(run_repository::nodes($runid));

        $this->assertSame(run_repository::STATUS_RUNNING, $run->status, 'It has not been closed yet.');
        $this->assertEquals($student->id, $run->subjectid);
        $this->assertCount(2, $nodes);
        $this->assertSame('trigger', $nodes[0]->nodekey);
        $this->assertSame('subscribe', $nodes[1]->nodekey);
        $this->assertSame(12, (int) $nodes[1]->durationms);
    }

    /**
     * Closing a run says how it ended, and when.
     */
    public function test_a_run_is_closed_with_its_outcome(): void {
        $runid = run_repository::start($this->flow, $this->versionid);
        run_repository::finish($runid, run_repository::STATUS_FAILED, 'No forum matched the pattern.');

        $run = run_repository::get($runid);

        $this->assertSame(run_repository::STATUS_FAILED, $run->status);
        $this->assertSame('No forum matched the pattern.', $run->error);
        $this->assertNotNull($run->timefinished);
    }

    /**
     * A run remembers the version it ran, not the one the flow has now.
     */
    public function test_a_run_remembers_the_version_it_ran(): void {
        $runid = run_repository::start($this->flow, $this->versionid);

        graph_repository::publish((int) $this->flow->id, [
            'nodes' => [['key' => 'trigger', 'type' => 'trigger_event', 'config' => ['changed' => true]]],
            'edges' => [],
        ]);

        $this->assertEquals($this->versionid, run_repository::get($runid)->versionid);
        $this->assertNotEquals(
            $this->versionid,
            flow_repository::get((int) $this->flow->id)->currentversionid,
            'The flow has moved on; the run has not.'
        );
    }

    /**
     * A flow set to say what it would have done marks its runs as such, so
     * nobody reads a rehearsal as a record.
     */
    public function test_a_dry_run_is_marked_as_one(): void {
        flow_repository::update((int) $this->flow->id, ['dryrun' => 1]);
        $flow = flow_repository::get((int) $this->flow->id);

        $runid = run_repository::start($flow, $this->versionid);

        $this->assertSame(1, (int) run_repository::get($runid)->dryrun);
    }

    /**
     * Old history is forgotten, and its detail goes with it.
     */
    public function test_old_runs_are_forgotten(): void {
        global $DB;

        $old = run_repository::start($this->flow, $this->versionid);
        run_repository::record_node($old, 'trigger', 'trigger_event', 'ok');
        $DB->set_field('tool_flowboard_run', 'timestarted', time() - (200 * DAYSECS), ['id' => $old]);

        $recent = run_repository::start($this->flow, $this->versionid);

        $this->assertSame(1, run_repository::purge_older_than(120));
        $this->assertNull(run_repository::get($old));
        $this->assertNotNull(run_repository::get($recent));
        $this->assertSame(0, $DB->count_records('tool_flowboard_run_node', ['runid' => $old]));
    }

    /**
     * A site that has decided to keep its history keeps it.
     */
    public function test_keeping_history_forever_keeps_it(): void {
        $old = run_repository::start($this->flow, $this->versionid);

        $this->assertSame(0, run_repository::purge_older_than(0));
        $this->assertNotNull(run_repository::get($old));
    }
}
