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

use tool_flowboard\local\actor\actor_provisioner;
use tool_flowboard\local\actor\actor_repository;
use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\flow\graph_repository;

/**
 * Giving a flow its own user and role, holding exactly what its nodes need.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\actor\actor_provisioner
 */
final class actor_provisioner_test extends \advanced_testcase {
    /**
     * Publishing a flow for the first time creates its actor: a real account,
     * a real role, holding exactly the capabilities its nodes declared.
     */
    public function test_publishing_creates_an_actor_with_exactly_the_right_capabilities(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        graph_repository::publish((int) $flow->id, $this->forum_flow_graph());

        $actor = actor_repository::for_flow((int) $flow->id);
        $this->assertNotNull($actor);
        $this->assertSame(['mod/forum:managesubscriptions'], actor_repository::capabilities($actor));

        $user = $DB->get_record('user', ['id' => $actor->userid], '*', MUST_EXIST);
        $this->assertSame('flow.welcome-forums', $user->username);
        $this->assertSame('nologin', $user->auth);
        $this->assertSame(0, (int) $user->suspended);

        $role = $DB->get_record('role', ['id' => $actor->roleid], '*', MUST_EXIST);
        $this->assertSame('', $role->archetype);
        $this->assertSame('flow_welcome-forums', $role->shortname);

        $this->assertTrue(has_capability(
            'mod/forum:managesubscriptions',
            \context_system::instance(),
            (int) $user->id
        ));
    }

    /**
     * Publishing a new drawing with different nodes recomputes the role:
     * a capability the new nodes do not need is taken away, not just left
     * granted from before.
     */
    public function test_republishing_with_fewer_nodes_takes_the_capability_away(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        graph_repository::publish((int) $flow->id, $this->forum_flow_graph());
        graph_repository::publish((int) $flow->id, [
            'nodes' => [
                ['key' => 'trigger', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\course_viewed']],
            ],
            'edges' => [],
        ]);

        $actor = actor_repository::for_flow((int) $flow->id);
        $this->assertSame([], actor_repository::capabilities($actor));
        $this->assertFalse(has_capability(
            'mod/forum:managesubscriptions',
            \context_system::instance(),
            (int) $actor->userid
        ));
    }

    /**
     * The same flow keeps the same actor across republishes: this is a role
     * that gets adjusted, not a new one each time.
     */
    public function test_republishing_reuses_the_same_actor(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        graph_repository::publish((int) $flow->id, $this->forum_flow_graph());
        $first = actor_repository::for_flow((int) $flow->id);

        graph_repository::publish((int) $flow->id, $this->forum_flow_graph());
        $second = actor_repository::for_flow((int) $flow->id);

        $this->assertEquals($first->userid, $second->userid);
        $this->assertEquals($first->roleid, $second->roleid);
    }

    /**
     * A flow cannot be given a capability its own publisher does not hold —
     * D16. Nothing is created: no user, no role, and the flow's drawing is
     * not published either, because it is all one transaction.
     *
     * A course teacher holds their role in that course's context, never at
     * system context — which is exactly where a flow's actor is granted its
     * capabilities — so they are the ordinary case of "does not hold it here",
     * with nothing needing to be specially revoked to prove it.
     */
    public function test_a_publisher_without_the_capability_is_refused(): void {
        global $DB;

        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_and_enrol(
            $this->getDataGenerator()->create_course(),
            'editingteacher'
        );
        $this->setUser($teacher);

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        $usersbefore = $DB->count_records('user');

        $this->expectException(\moodle_exception::class);

        try {
            graph_repository::publish((int) $flow->id, $this->forum_flow_graph());
        } finally {
            $this->assertNull(flow_repository::get((int) $flow->id)->currentversionid);
            $this->assertNull(actor_repository::for_flow((int) $flow->id));
            $this->assertSame($usersbefore, $DB->count_records('user'));
        }
    }

    /**
     * Two flows whose idnumbers would produce the same account name still get
     * two different ones.
     */
    public function test_two_flows_with_colliding_slugs_get_distinct_accounts(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $first = flow_repository::create('Welcome!', 'First');
        $second = flow_repository::create('welcome!', 'Second');

        graph_repository::publish((int) $first->id, $this->forum_flow_graph());
        graph_repository::publish((int) $second->id, $this->forum_flow_graph());

        $firstuser = $DB->get_record('user', ['id' => actor_repository::for_flow((int) $first->id)->userid]);
        $seconduser = $DB->get_record('user', ['id' => actor_repository::for_flow((int) $second->id)->userid]);

        $this->assertNotSame($firstuser->username, $seconduser->username);
    }

    /**
     * Pausing a flow takes its actor's role away immediately, and suspends
     * the account; making it live again gives both back.
     */
    public function test_pausing_and_resuming_a_flow_toggles_its_actor(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        graph_repository::publish((int) $flow->id, $this->forum_flow_graph());
        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_LIVE);

        $actor = actor_repository::for_flow((int) $flow->id);
        $this->assertTrue(has_capability(
            'mod/forum:managesubscriptions',
            \context_system::instance(),
            (int) $actor->userid
        ));

        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_PAUSED);

        $paused = actor_repository::for_flow((int) $flow->id);
        $this->assertSame(actor_repository::STATUS_SUSPENDED, $paused->status);
        $this->assertSame(1, (int) $DB->get_field('user', 'suspended', ['id' => $actor->userid]));
        $this->assertFalse(has_capability(
            'mod/forum:managesubscriptions',
            \context_system::instance(),
            (int) $actor->userid
        ));

        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_LIVE);

        $resumed = actor_repository::for_flow((int) $flow->id);
        $this->assertSame(actor_repository::STATUS_ACTIVE, $resumed->status);
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $actor->userid]));
        $this->assertTrue(has_capability(
            'mod/forum:managesubscriptions',
            \context_system::instance(),
            (int) $actor->userid
        ));
    }

    /**
     * A flow that has never been published has no actor, so pausing or
     * resuming it is a safe no-op rather than an error.
     */
    public function test_pausing_a_never_published_flow_is_a_no_op(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('never-published', 'Never published');

        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_PAUSED);
        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_LIVE);

        $this->assertNull(actor_repository::for_flow((int) $flow->id));
    }

    /**
     * `capability_diff()` says what would change without changing anything —
     * what a publish screen would show before letting the change through.
     */
    public function test_capability_diff_reports_without_writing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        graph_repository::publish((int) $flow->id, $this->forum_flow_graph());

        $diff = actor_provisioner::capability_diff((int) $flow->id, ['mod/forum:managesubscriptions', 'enrol/manual:enrol']);

        $this->assertSame(['enrol/manual:enrol'], $diff['added']);
        $this->assertSame([], $diff['removed']);

        // Nothing was actually granted by asking.
        $actor = actor_repository::for_flow((int) $flow->id);
        $this->assertSame(['mod/forum:managesubscriptions'], actor_repository::capabilities($actor));
    }

    /**
     * Deleting a flow removes the real account and role behind it too, not
     * only the row that records them.
     */
    public function test_deleting_a_flow_deprovisions_its_actor(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        graph_repository::publish((int) $flow->id, $this->forum_flow_graph());
        $actor = actor_repository::for_flow((int) $flow->id);

        flow_repository::delete((int) $flow->id);

        $this->assertFalse($DB->record_exists('role', ['id' => $actor->roleid]));
        $this->assertSame(1, (int) $DB->get_field('user', 'deleted', ['id' => $actor->userid]));
    }

    /**
     * A flow whose nodes need nothing at all still gets an actor — an empty
     * role is not a missing one, and the account is still what attributes
     * whatever the flow does to it rather than to nobody in particular.
     */
    public function test_a_flow_needing_no_capabilities_still_gets_an_actor(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('just-a-trigger', 'Just a trigger');
        graph_repository::publish((int) $flow->id, [
            'nodes' => [['key' => 'trigger', 'type' => 'trigger_event', 'config' => []]],
            'edges' => [],
        ]);

        $actor = actor_repository::for_flow((int) $flow->id);
        $this->assertNotNull($actor);
        $this->assertSame([], actor_repository::capabilities($actor));
    }

    /**
     * A flow with a trigger, a condition and the forum-subscribe action —
     * needing exactly one capability.
     *
     * @return array
     */
    private function forum_flow_graph(): array {
        return [
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
        ];
    }
}
