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

namespace tool_flowboard\task;

use tool_flowboard\local\actor\actor_repository;
use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\flow\graph_repository;
use tool_flowboard\local\run\run_repository;

/**
 * The task really does run as the flow's own actor, not as whoever the cron
 * happens to be.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\task\run_flow
 */
final class run_flow_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The student who will be subscribed. */
    private \stdClass $student;

    /**
     * A course, a student, and a forum for a flow to subscribe them to.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        // See tearDown(): mod_forum\subscriptions keeps its own static cache.
        \mod_forum\subscriptions::reset_forum_cache();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'name' => 'General forum',
        ]);
    }

    /**
     * The subscription this run creates is attributed to the flow's own
     * actor. This is the whole reason the actor exists: without it, the event
     * would say Admin User, or whoever the cron happens to run as.
     */
    public function test_the_task_runs_as_the_flows_own_actor(): void {
        $flow = $this->a_live_flow();
        $actor = actor_repository::for_flow((int) $flow->id);

        $task = new run_flow();
        $task->set_custom_data((object) [
            'flowid' => (int) $flow->id,
            'eventname' => '\local_coursestate\event\course_started',
            'eventdata' => [
                'eventname' => '\local_coursestate\event\course_started',
                'userid' => (int) $this->student->id,
                'courseid' => (int) $this->course->id,
                'other' => [],
            ],
            'depth' => 0,
        ]);

        $sink = $this->redirectEvents();
        // The task reports what it did through mtrace(), the same as any
        // adhoc task run from cron; captured rather than left to print, the
        // way core's own adhoc task tests handle it.
        ob_start();
        $task->execute();
        ob_end_clean();
        $events = $sink->get_events();
        $sink->close();

        $subscribed = array_values(array_filter($events, static function (\core\event\base $event): bool {
            return $event->eventname === '\mod_forum\event\subscription_created';
        }));

        $this->assertCount(1, $subscribed);
        $this->assertEquals(
            $actor->userid,
            $subscribed[0]->userid,
            'The subscription must be attributed to the flow, not to whoever the cron runs as.'
        );

        // The global user is put back afterwards, rather than left as the
        // actor for whatever runs next in the same process.
        $this->assertEquals(get_admin()->id, $GLOBALS['USER']->id);
    }

    /**
     * A live flow whose actor was suspended out of band (data drifted, or a
     * bug elsewhere) is refused rather than run as an unattributed guess.
     */
    public function test_a_suspended_actor_refuses_to_run(): void {
        global $DB;

        $flow = $this->a_live_flow();
        $actor = actor_repository::for_flow((int) $flow->id);
        $DB->set_field('tool_flowboard_actor', 'status', actor_repository::STATUS_SUSPENDED, ['flowid' => $flow->id]);

        $task = new run_flow();
        $task->set_custom_data((object) [
            'flowid' => (int) $flow->id,
            'eventname' => '\local_coursestate\event\course_started',
            'eventdata' => [
                'eventname' => '\local_coursestate\event\course_started',
                'userid' => (int) $this->student->id,
                'courseid' => (int) $this->course->id,
                'other' => [],
            ],
        ]);

        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame([], run_repository::recent((int) $flow->id));
    }

    /**
     * A published, live flow that subscribes to the course's forum.
     *
     * @return \stdClass
     */
    private function a_live_flow(): \stdClass {
        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        graph_repository::publish((int) $flow->id, [
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
            'edges' => [['from' => 'trigger', 'port' => 'out', 'to' => 'subscribe']],
        ]);
        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_LIVE);

        return flow_repository::get((int) $flow->id);
    }

    /**
     * mod_forum\subscriptions keeps its own static cache of who is subscribed
     * to what, and nothing about resetAfterTest() clears a static property
     * belonging to a different class. Core's own subscription tests reset it
     * themselves for the same reason (see mod_forum's own
     * subscriptions_test.php): without this, a forum id reused by a later
     * test (ids are recycled once the database is truncated between tests)
     * can read a subscription state left behind by an earlier one.
     */
    protected function tearDown(): void {
        \mod_forum\subscriptions::reset_forum_cache();

        parent::tearDown();
    }
}
