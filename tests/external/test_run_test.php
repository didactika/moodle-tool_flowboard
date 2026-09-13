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
use tool_flowboard\local\event\event_seen_repository;

/**
 * "Probar": walking an unsaved drawing against a sample event.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\external\test_run
 */
final class test_run_test extends \advanced_testcase {
    /**
     * With no sample recorded at all, there is nothing to fill even the
     * event's own name with a subject from — the preview says so honestly
     * rather than inventing a person.
     */
    public function test_it_stops_honestly_when_nothing_has_been_seen(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $graph = [
            'nodes' => [['key' => 'trigger', 'type' => 'trigger_event', 'config' => ['subject' => 'relateduserid']]],
            'edges' => [],
        ];

        $result = external_api::clean_returnvalue(
            test_run::execute_returns(),
            test_run::execute('\core\event\course_viewed', json_encode($graph))
        );

        $this->assertSame('trigger', $result['path'][0]['nodekey']);
        $this->assertSame('stopped', $result['path'][0]['status']);
    }

    /**
     * A recorded sample's own shape is what the preview actually runs
     * against — including a field found only in `other`.
     */
    public function test_it_runs_against_what_was_actually_seen(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        event_seen_repository::record('\some\event', 'core', [
            'eventname' => '\some\event',
            'relateduserid' => 42,
            'other' => ['state' => 'OPEN_STARTED'],
        ]);

        $graph = [
            'nodes' => [
                ['key' => 'trigger', 'type' => 'trigger_event', 'config' => ['subject' => 'relateduserid']],
                [
                    'key' => 'check',
                    'type' => 'condition_payload',
                    'config' => ['field' => 'other.state', 'operator' => 'equals', 'value' => 'OPEN_STARTED'],
                ],
            ],
            'edges' => [['from' => 'trigger', 'port' => 'out', 'to' => 'check']],
        ];

        $result = external_api::clean_returnvalue(
            test_run::execute_returns(),
            test_run::execute('\some\event', json_encode($graph))
        );

        $this->assertSame('true', $result['path'][1]['port'], 'other.state was really OPEN_STARTED in the sample.');
    }

    /**
     * A drawing that never resolves anything the graph's own JSON is refused.
     */
    public function test_something_that_is_not_a_graph_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        test_run::execute('\core\event\course_viewed', json_encode(['nothing' => 'here']));
    }
}
