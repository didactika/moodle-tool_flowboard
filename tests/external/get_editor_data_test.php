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
use tool_flowboard\local\node\trigger_event;

/**
 * Everything the canvas needs in one call.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\external\get_editor_data
 */
final class get_editor_data_test extends \advanced_testcase {
    /**
     * A new flow opens to an empty canvas, with the palette ready.
     */
    public function test_a_new_flow_opens_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = external_api::clean_returnvalue(
            get_editor_data::execute_returns(),
            get_editor_data::execute(0)
        );

        $graph = json_decode($result['graph'], true);
        $nodetypes = json_decode($result['nodetypes'], true);

        $this->assertSame([], $graph['nodes']);
        $this->assertArrayHasKey(trigger_event::TYPE, $nodetypes);
        $this->assertFalse($result['haddraft']);
    }

    /**
     * A flow with a draft opens to it, not to its last published version.
     */
    public function test_a_flow_with_a_draft_opens_to_it(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        graph_repository::publish((int) $flow->id, [
            'nodes' => [['key' => 'a', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']]],
            'edges' => [],
        ]);
        flow_repository::save_draft((int) $flow->id, [
            'nodes' => [['key' => 'b', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\user_loggedin']]],
            'edges' => [],
            'comments' => [],
        ]);

        $result = external_api::clean_returnvalue(
            get_editor_data::execute_returns(),
            get_editor_data::execute((int) $flow->id)
        );
        $graph = json_decode($result['graph'], true);

        $this->assertSame('b', $graph['nodes'][0]['key']);
        $this->assertTrue($result['haddraft']);
    }

    /**
     * Without a draft, a published flow opens to its published drawing.
     */
    public function test_a_published_flow_without_a_draft_opens_to_its_version(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        graph_repository::publish((int) $flow->id, [
            'nodes' => [['key' => 'a', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']]],
            'edges' => [],
        ]);

        $result = external_api::clean_returnvalue(
            get_editor_data::execute_returns(),
            get_editor_data::execute((int) $flow->id)
        );
        $graph = json_decode($result['graph'], true);

        $this->assertSame('a', $graph['nodes'][0]['key']);
        $this->assertFalse($result['haddraft']);
    }

    /**
     * An event nobody has ever seen fire still offers the fields every
     * event carries — a flow is not built blind just because nothing has
     * happened yet.
     */
    public function test_an_unseen_event_still_offers_its_standard_fields(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = external_api::clean_returnvalue(
            get_editor_data::execute_returns(),
            get_editor_data::execute(0)
        );
        $eventfields = json_decode($result['eventfields'], true);

        $this->assertContains('relateduserid', $eventfields['\core\event\course_viewed']);
        $this->assertContains('courseid', $eventfields['\core\event\course_viewed']);
    }

    /**
     * A field actually seen on this site is offered alongside the standard
     * ones, not instead of them.
     */
    public function test_a_seen_field_is_offered_alongside_the_standard_ones(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        \tool_flowboard\local\event\event_seen_repository::record('\core\event\course_viewed', 'core', [
            'eventname' => '\core\event\course_viewed',
            'other' => ['modulename' => 'forum'],
        ]);

        $result = external_api::clean_returnvalue(
            get_editor_data::execute_returns(),
            get_editor_data::execute(0)
        );
        $eventfields = json_decode($result['eventfields'], true);

        $this->assertContains('other.modulename', $eventfields['\core\event\course_viewed']);
        $this->assertContains('relateduserid', $eventfields['\core\event\course_viewed']);
    }

    /**
     * A flow that does not exist is refused rather than answered with
     * nothing.
     */
    public function test_an_unknown_flow_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        get_editor_data::execute(999999);
    }

    /**
     * Somebody without the capability cannot open the editor at all.
     */
    public function test_a_user_without_the_capability_is_refused(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        get_editor_data::execute(0);
    }
}
