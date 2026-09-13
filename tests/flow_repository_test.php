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
 * The flows themselves.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\flow\flow_repository
 */
final class flow_repository_test extends \advanced_testcase {
    /**
     * A new flow is a draft: nothing reacts to anything until somebody says so.
     */
    public function test_a_new_flow_is_a_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');

        $this->assertSame(flow_repository::STATUS_DRAFT, $flow->status);
        $this->assertNull($flow->currentversionid);
        $this->assertSame([], flow_repository::live());
    }

    /**
     * A flow is found by its id and by the name that travels with it.
     */
    public function test_a_flow_is_found_by_either_name(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');

        $this->assertEquals($flow->id, flow_repository::get((int) $flow->id)->id);
        $this->assertEquals($flow->id, flow_repository::get_by_idnumber('welcome-forums')->id);
        $this->assertNull(flow_repository::get_by_idnumber('nothing-like-this'));
    }

    /**
     * Two flows cannot share the name that exports and actors are built from.
     */
    public function test_two_flows_cannot_share_an_idnumber(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        flow_repository::create('welcome-forums', 'Welcome to the forums');

        $this->expectException(\dml_exception::class);
        flow_repository::create('welcome-forums', 'Another one entirely');
    }

    /**
     * Renaming a flow does not touch the name it is known by elsewhere.
     */
    public function test_renaming_leaves_the_idnumber_alone(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        flow_repository::update((int) $flow->id, [
            'name' => 'Forum welcome',
            'idnumber' => 'something-else',
        ]);

        $updated = flow_repository::get((int) $flow->id);

        $this->assertSame('Forum welcome', $updated->name);
        $this->assertSame('welcome-forums', $updated->idnumber);
    }

    /**
     * Only a published flow is one the engine will look at.
     */
    public function test_only_live_flows_are_listed_as_live(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $live = flow_repository::create('one', 'One');
        flow_repository::create('two', 'Two');
        flow_repository::set_status((int) $live->id, flow_repository::STATUS_LIVE);

        $this->assertSame([(int) $live->id], array_map('intval', array_keys(flow_repository::live())));
        $this->assertCount(2, flow_repository::all());
    }

    /**
     * A status nobody defined is a programming mistake, not a value.
     */
    public function test_an_unknown_status_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('one', 'One');

        $this->expectException(\coding_exception::class);
        flow_repository::set_status((int) $flow->id, 'whenever');
    }

    /**
     * Deleting a flow takes its versions, its nodes and its history with it.
     */
    public function test_deleting_a_flow_leaves_nothing_behind(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('one', 'One');
        graph_repository::publish((int) $flow->id, [
            'nodes' => [['key' => 'a', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']]],
            'edges' => [],
        ]);

        flow_repository::delete((int) $flow->id);

        $this->assertNull(flow_repository::get((int) $flow->id));
        $this->assertSame(0, $DB->count_records('tool_flowboard_version', ['flowid' => $flow->id]));
        $this->assertSame(0, $DB->count_records('tool_flowboard_node', ['flowid' => $flow->id]));
    }

    /**
     * A flow with nothing drawn yet has no draft.
     */
    public function test_a_fresh_flow_has_no_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('one', 'One');

        $this->assertNull(flow_repository::draft((int) $flow->id));
    }

    /**
     * The canvas's own autosave is remembered exactly as it was drawn.
     */
    public function test_a_saved_draft_comes_back_as_it_was(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('one', 'One');
        $graph = [
            'nodes' => [['key' => 'a', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']]],
            'edges' => [],
            'comments' => [['id' => 'c1', 'x' => 10, 'y' => 20, 'text' => 'remember why']],
        ];

        flow_repository::save_draft((int) $flow->id, $graph);

        $this->assertSame($graph, flow_repository::draft((int) $flow->id));
    }

    /**
     * Publishing is what a draft was for; there is nothing left to keep once
     * its drawing is the published version.
     */
    public function test_publishing_clears_the_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('one', 'One');
        flow_repository::save_draft((int) $flow->id, [
            'nodes' => [['key' => 'a', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']]],
            'edges' => [],
        ]);

        graph_repository::publish((int) $flow->id, [
            'nodes' => [['key' => 'a', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']]],
            'edges' => [],
        ]);

        $this->assertNull(flow_repository::draft((int) $flow->id));
    }

    /**
     * A draft can be thrown away deliberately too, without publishing it.
     */
    public function test_a_draft_can_be_discarded_without_publishing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('one', 'One');
        flow_repository::save_draft((int) $flow->id, ['nodes' => [], 'edges' => []]);

        flow_repository::discard_draft((int) $flow->id);

        $this->assertNull(flow_repository::draft((int) $flow->id));
    }
}
