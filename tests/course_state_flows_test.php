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

use mod_forum\subscriptions;
use tool_flowboard\local\actor\actor_provisioner;
use tool_flowboard\local\run\engine;
use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\flow\graph_repository;
use tool_flowboard\local\run\run_repository;

/**
 * The two rules this whole plugin was started for, built as real flows and run
 * against events shaped exactly like the ones `local_coursestate` fires.
 *
 * These are not unit tests of one piece; they are what the plan promised:
 * "a student who starts a course ends up subscribed to the forums that match,
 * and the history explains why".
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\run\engine
 */
final class course_state_flows_test extends \advanced_testcase {
    /** @var \stdClass The course the student is in. */
    private \stdClass $course;

    /** @var \stdClass The student. */
    private \stdClass $student;

    /** @var \stdClass The forum that matches the pattern used below. */
    private \stdClass $forum;

    /**
     * A course, a student, and a welcome forum to subscribe them to.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        // See the tearDown() note below for why this is necessary.
        \mod_forum\subscriptions::reset_forum_cache();
        \mod_forum\subscriptions::reset_discussion_cache();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'name' => 'General forum',
        ]);
    }

    /**
     * R1: a student starting a course is subscribed to the matching forums.
     *
     * `course_started` names the acting user in `userid`, because starting a
     * course is the student's own action.
     */
    public function test_r1_starting_a_course_subscribes_to_the_welcome_forum(): void {
        [$flow, $versionid] = $this->r1_flow();

        $runid = engine::run($flow, $versionid, [
            'eventname' => '\local_coursestate\event\course_started',
            'userid' => (int) $this->student->id,
            'courseid' => (int) $this->course->id,
            'other' => ['previous_state' => 'OPEN_NOT_STARTED'],
        ]);

        $this->assertSame(run_repository::STATUS_OK, run_repository::get($runid)->status);
        $this->assertTrue(subscriptions::is_subscribed((int) $this->student->id, $this->forum));
    }

    /**
     * R2: a state change that is neither "just started" nor "finished" removes
     * the subscription.
     *
     * `course_state_updated` names the affected student in `relateduserid`,
     * because whoever decided the new state may not be the student at all.
     *
     * @dataProvider r2_state_provider
     * @param string $newstate One of `local_coursestate\local\course_state`'s states.
     * @param bool $shouldremainsubscribed Whether that state keeps the subscription.
     */
    public function test_r2_state_changes(string $newstate, bool $shouldremainsubscribed): void {
        subscriptions::subscribe_user((int) $this->student->id, $this->forum);

        [$flow, $versionid] = $this->r2_flow();

        engine::run($flow, $versionid, [
            'eventname' => '\local_coursestate\event\course_state_updated',
            'relateduserid' => (int) $this->student->id,
            'courseid' => (int) $this->course->id,
            'other' => ['state' => $newstate, 'previous_state' => 'OPEN_STARTED'],
        ]);

        $this->assertSame(
            $shouldremainsubscribed,
            subscriptions::is_subscribed((int) $this->student->id, $this->forum)
        );
    }

    /**
     * The states that keep the subscription, and the states that end it —
     * settled with the user: `RECOGNIZED` (a recognised/credited course) does
     * not keep it either.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function r2_state_provider(): array {
        return [
            'OPEN_STARTED keeps it' => ['OPEN_STARTED', true],
            'FINALIZED keeps it' => ['FINALIZED', true],
            'NOT_OPEN removes it' => ['NOT_OPEN', false],
            'OPEN_NOT_STARTED removes it' => ['OPEN_NOT_STARTED', false],
            'CLOSED removes it' => ['CLOSED', false],
            'RECOGNIZED removes it' => ['RECOGNIZED', false],
        ];
    }

    /**
     * R1 as a real, published flow: course_started -> subscribe.
     *
     * @return array{0: \stdClass, 1: int}
     */
    private function r1_flow(): array {
        $flow = flow_repository::create('r1-welcome-forums', 'Welcome to the forums');
        $versionid = $this->publish_raw((int) $flow->id, [
            'nodes' => [
                [
                    'key' => 'trigger',
                    'type' => 'trigger_event',
                    'config' => ['eventname' => '\local_coursestate\event\course_started', 'subject' => 'userid'],
                ],
                [
                    'key' => 'subscribe',
                    'type' => 'action_forum_subscribe',
                    'config' => ['match' => 'name', 'operator' => 'contains', 'pattern' => 'general'],
                ],
            ],
            'edges' => [
                ['from' => 'trigger', 'port' => 'out', 'to' => 'subscribe'],
            ],
        ]);
        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_LIVE);

        return [flow_repository::get((int) $flow->id), $versionid];
    }

    /**
     * R2 as a real, published flow: course_state_updated -> unsubscribe,
     * unless the new state is one that keeps the subscription.
     *
     * @return array{0: \stdClass, 1: int}
     */
    private function r2_flow(): array {
        $flow = flow_repository::create('r2-state-changed', 'Leave the forums on state change');
        $versionid = $this->publish_raw((int) $flow->id, [
            'nodes' => [
                [
                    'key' => 'trigger',
                    'type' => 'trigger_event',
                    'config' => ['eventname' => '\local_coursestate\event\course_state_updated', 'subject' => 'relateduserid'],
                ],
                [
                    'key' => 'keeps',
                    'type' => 'condition_payload',
                    'config' => ['field' => 'other.state', 'operator' => 'in', 'value' => 'OPEN_STARTED,FINALIZED'],
                ],
                [
                    'key' => 'unsubscribe',
                    'type' => 'action_forum_unsubscribe',
                    'config' => ['match' => 'name', 'operator' => 'contains', 'pattern' => 'general'],
                ],
            ],
            'edges' => [
                ['from' => 'trigger', 'port' => 'out', 'to' => 'keeps'],
                ['from' => 'keeps', 'port' => 'false', 'to' => 'unsubscribe'],
            ],
        ]);
        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_LIVE);

        return [flow_repository::get((int) $flow->id), $versionid];
    }

    /**
     * Publishes a graph the way {@see graph_repository::publish()} always
     * has, without its one check this file cannot satisfy: that the
     * triggering event exists on this site. R1 and R2 are drawn against
     * `local_coursestate`'s own events on purpose — that plugin is not
     * installed here, on a site that really ran this flow it would be, and
     * this file is about the engine's own behaviour, not the catalogue.
     *
     * @param int $flowid
     * @param array $graph
     * @return int The new version's id.
     */
    private function publish_raw(int $flowid, array $graph): int {
        global $DB, $USER;

        $versionid = $DB->insert_record('tool_flowboard_version', (object) [
            'flowid' => $flowid,
            'versionnumber' => 1,
            'graph' => json_encode($graph),
            'note' => null,
            'timecreated' => time(),
            'usermodified' => (int) $USER->id,
        ]);

        $sortorder = 0;

        foreach ($graph['nodes'] as $node) {
            $DB->insert_record('tool_flowboard_node', (object) [
                'flowid' => $flowid,
                'versionid' => $versionid,
                'nodekey' => $node['key'],
                'type' => $node['type'],
                'config' => json_encode($node['config'] ?? []),
                'sortorder' => $sortorder++,
            ]);
        }

        foreach ($graph['edges'] as $edge) {
            $DB->insert_record('tool_flowboard_edge', (object) [
                'flowid' => $flowid,
                'versionid' => $versionid,
                'fromnode' => $edge['from'],
                'fromport' => $edge['port'] ?? graph_repository::PORT_OUT,
                'tonode' => $edge['to'],
            ]);
        }

        flow_repository::set_current_version($flowid, $versionid);
        actor_provisioner::ensure_for_version($flowid, $versionid);

        return $versionid;
    }

    /**
     * mod_forum\subscriptions keeps its own static cache of who is
     * subscribed to what, and nothing about resetAfterTest() clears a static
     * property belonging to a different class. Core's own subscription tests
     * reset it themselves for the same reason (see mod_forum's own
     * subscriptions_test.php): without this, a forum id reused by a later
     * test (ids are recycled once the database is truncated between tests)
     * can read a subscription state left behind by an earlier one.
     */
    protected function tearDown(): void {
        \mod_forum\subscriptions::reset_forum_cache();
        \mod_forum\subscriptions::reset_discussion_cache();

        parent::tearDown();
    }
}
