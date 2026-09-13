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

/**
 * Freezing the canvas's own drawing into a version.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\external\publish
 */
final class publish_test extends \advanced_testcase {
    /**
     * Publishing writes a real version, and clears whatever draft led to it.
     */
    public function test_publishing_writes_a_version_and_clears_the_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        $graph = [
            'nodes' => [['key' => 'a', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']]],
            'edges' => [],
        ];
        flow_repository::save_draft((int) $flow->id, $graph + ['comments' => []]);

        $result = external_api::clean_returnvalue(
            publish::execute_returns(),
            publish::execute((int) $flow->id, json_encode($graph))
        );

        $this->assertSame($result['versionid'], (int) flow_repository::get((int) $flow->id)->currentversionid);
        $this->assertNull(flow_repository::draft((int) $flow->id));
    }

    /**
     * A graph publish() itself would refuse — nothing drawn at all — is
     * refused here the same way.
     */
    public function test_an_empty_graph_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');

        $this->expectException(\moodle_exception::class);
        publish::execute((int) $flow->id, json_encode(['nodes' => [], 'edges' => []]));
    }

    /**
     * A publisher who could not grant a node's own capability is refused,
     * same as calling {@see graph_repository::publish()} directly.
     */
    public function test_a_publisher_without_the_capability_is_refused(): void {
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_and_enrol(
            $this->getDataGenerator()->create_course(),
            'editingteacher'
        );

        $syscontext = \context_system::instance();
        $roleid = create_role('Flow publisher', 'flowpublisher', 'Can manage flows, tested only');
        assign_capability('tool/flowboard:manage', CAP_ALLOW, $roleid, $syscontext->id, true);
        role_assign($roleid, $teacher->id, $syscontext->id);

        $this->setUser($teacher);

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        $graph = [
            'nodes' => [
                ['key' => 'trigger', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']],
                [
                    'key' => 'action',
                    'type' => 'action_forum_subscribe',
                    'config' => ['match' => 'name', 'operator' => 'contains', 'pattern' => 'general'],
                ],
            ],
            'edges' => [['from' => 'trigger', 'port' => 'out', 'to' => 'action']],
        ];

        $this->expectException(\moodle_exception::class);
        publish::execute((int) $flow->id, json_encode($graph));
    }
}
