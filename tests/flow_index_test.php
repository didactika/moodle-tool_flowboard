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

use tool_flowboard\local\flow\flow_index;
use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\flow\graph_repository;

/**
 * Who is waiting for what.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\flow\flow_index
 */
final class flow_index_test extends \advanced_testcase {
    /**
     * A live flow is listed against the event its trigger names.
     */
    public function test_a_live_flow_is_listed_against_its_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = $this->a_flow('one', '\core\event\role_assigned', flow_repository::STATUS_LIVE);

        $this->assertSame([(int) $flow->id], flow_index::for_event('\core\event\role_assigned'));
        $this->assertSame([], flow_index::for_event('\core\event\role_unassigned'));
    }

    /**
     * Several flows can wait for the same event, and all of them are told.
     */
    public function test_several_flows_can_wait_for_the_same_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $first = $this->a_flow('one', '\core\event\role_assigned', flow_repository::STATUS_LIVE);
        $second = $this->a_flow('two', '\core\event\role_assigned', flow_repository::STATUS_LIVE);

        $waiting = flow_index::for_event('\core\event\role_assigned');
        sort($waiting);

        $expected = [(int) $first->id, (int) $second->id];
        sort($expected);

        $this->assertSame($expected, $waiting);
    }

    /**
     * Only the published version counts: a draft is a drawing nobody has
     * agreed to, and an old version is a record rather than an instruction.
     */
    public function test_only_the_published_version_of_a_live_flow_counts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = $this->a_flow('one', '\core\event\role_assigned', flow_repository::STATUS_LIVE);

        graph_repository::publish((int) $flow->id, $this->graph('\core\event\user_enrolment_created'));

        $this->assertSame([], flow_index::for_event('\core\event\role_assigned'), 'That version is history now.');
        $this->assertSame([(int) $flow->id], flow_index::for_event('\core\event\user_enrolment_created'));
    }

    /**
     * Pausing, resuming and deleting all change who is waiting, and the index
     * keeps up with each of them.
     */
    public function test_the_index_keeps_up_with_the_flow(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = $this->a_flow('one', '\core\event\role_assigned', flow_repository::STATUS_LIVE);

        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_PAUSED);
        $this->assertSame([], flow_index::for_event('\core\event\role_assigned'));

        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_LIVE);
        $this->assertSame([(int) $flow->id], flow_index::for_event('\core\event\role_assigned'));

        flow_repository::delete((int) $flow->id);
        $this->assertSame([], flow_index::for_event('\core\event\role_assigned'));
    }

    /**
     * A trigger with no event named waits for nothing, rather than for
     * everything.
     */
    public function test_a_trigger_with_no_event_waits_for_nothing(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Publishing a trigger with no event configured is refused these
        // days — this is what the index still has to cope with safely if a
        // row like that ever ends up in the database some other way.
        $flow = flow_repository::create('one', 'One');
        $versionid = graph_repository::publish((int) $flow->id, [
            'nodes' => [['key' => 'trigger', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']]],
            'edges' => [],
        ]);
        $DB->set_field(
            'tool_flowboard_node',
            'config',
            json_encode([]),
            ['versionid' => $versionid, 'nodekey' => 'trigger']
        );
        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_LIVE);

        $this->assertSame([], flow_index::index());
    }

    /**
     * A published flow in whatever state, listening for one event.
     *
     * @param string $idnumber
     * @param string $eventname
     * @param string $status
     * @return \stdClass
     */
    private function a_flow(string $idnumber, string $eventname, string $status): \stdClass {
        $flow = flow_repository::create($idnumber, ucfirst($idnumber));
        graph_repository::publish((int) $flow->id, $this->graph($eventname));
        flow_repository::set_status((int) $flow->id, $status);

        return $flow;
    }

    /**
     * One trigger, waiting for one event.
     *
     * @param string $eventname
     * @return array
     */
    private function graph(string $eventname): array {
        return [
            'nodes' => [[
                'key' => 'trigger',
                'type' => 'trigger_event',
                'config' => ['eventname' => $eventname],
            ]],
            'edges' => [],
        ];
    }
}
