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

use tool_flowboard\local\node\node_registry;
use tool_flowboard\local\node\action_forum_subscribe;
use tool_flowboard\local\node\action_forum_unsubscribe;
use tool_flowboard\local\node\condition_payload;
use tool_flowboard\local\node\trigger_event;

/**
 * Which kinds of node exist, found rather than listed.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\node\node_registry
 */
final class node_registry_test extends \advanced_testcase {
    /**
     * Every node this plugin ships with is found without being listed
     * anywhere: adding a node is adding a class.
     */
    public function test_it_finds_every_node_this_plugin_ships(): void {
        $this->resetAfterTest();

        $types = node_registry::all();

        $this->assertSame(trigger_event::class, $types[trigger_event::TYPE]);
        $this->assertSame(condition_payload::class, $types[condition_payload::TYPE]);
        $this->assertSame(action_forum_subscribe::class, $types[action_forum_subscribe::TYPE]);
        $this->assertSame(action_forum_unsubscribe::class, $types[action_forum_unsubscribe::TYPE]);
    }

    /**
     * A node type is handed back ready to run.
     */
    public function test_a_node_is_built_ready_to_use(): void {
        $this->resetAfterTest();

        $this->assertInstanceOf(trigger_event::class, node_registry::get(trigger_event::TYPE));
        $this->assertNull(node_registry::get('nothing_like_this'));
    }

    /**
     * Only the trigger is offered as somewhere a run can begin.
     */
    public function test_only_the_trigger_starts_a_run(): void {
        $this->resetAfterTest();

        $triggers = node_registry::triggers();

        $this->assertArrayHasKey(trigger_event::TYPE, $triggers);
        $this->assertArrayNotHasKey(condition_payload::TYPE, $triggers);
        $this->assertArrayNotHasKey(action_forum_subscribe::TYPE, $triggers);
    }

    /**
     * A flow's required capabilities are the union of what its nodes ask for,
     * without repeats — this is exactly what the flow's actor will be granted
     * and nothing more.
     */
    public function test_capabilities_are_the_union_of_the_nodes_used(): void {
        $this->resetAfterTest();

        $nodes = [
            (object) ['type' => trigger_event::TYPE, 'config' => []],
            (object) ['type' => condition_payload::TYPE, 'config' => []],
            (object) ['type' => action_forum_subscribe::TYPE, 'config' => []],
            (object) ['type' => action_forum_unsubscribe::TYPE, 'config' => []],
        ];

        $this->assertSame(
            ['mod/forum:managesubscriptions'],
            node_registry::capabilities_for($nodes),
            'Both forum nodes ask for the same capability; the flow needs it once, not twice.'
        );
    }

    /**
     * A node type nobody recognises contributes no capability rather than
     * failing the whole calculation — the same thing that happens when a
     * flow was drawn against a plugin since removed.
     */
    public function test_an_unknown_node_type_is_skipped(): void {
        $this->resetAfterTest();

        $nodes = [(object) ['type' => 'something_removed', 'config' => []]];

        $this->assertSame([], node_registry::capabilities_for($nodes));
    }

    /**
     * A node type's schema is what the canvas builds its inspector panel
     * from: its own config fields, and what it leaves behind for later nodes.
     */
    public function test_a_schema_names_the_configuration_and_what_it_produces(): void {
        $this->resetAfterTest();

        $schema = node_registry::schema_for(trigger_event::TYPE);

        $this->assertSame(['subjectid', 'courseid', 'contextid'], $schema['produces']);
        $this->assertSame(['out'], $schema['ports']);
        $this->assertSame('eventname', $schema['config'][0]['key']);
        $this->assertTrue($schema['config'][0]['required'], 'The event itself is not optional.');
        $this->assertSame('subject', $schema['config'][1]['key']);
        $this->assertFalse($schema['config'][1]['required'], 'The subject falls back to relateduserid on its own.');
    }

    /**
     * A question about the event draws two ways out, not one.
     */
    public function test_a_condition_has_two_ports(): void {
        $this->resetAfterTest();

        $this->assertSame(['true', 'false'], node_registry::schema_for(condition_payload::TYPE)['ports']);
    }

    /**
     * A node type nobody recognises has no schema at all, rather than one
     * full of nothing — there is a real difference between "produces
     * nothing" and "does not exist".
     */
    public function test_an_unknown_type_has_no_schema(): void {
        $this->resetAfterTest();

        $this->assertNull(node_registry::schema_for('something_removed'));
    }
}
