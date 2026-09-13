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
use tool_flowboard\local\flow\flow_context;
use tool_flowboard\local\node\action_forum_subscribe;

/**
 * Subscribing the run's subject to forums that match a pattern.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\node\action_forum_subscribe
 */
final class action_forum_subscribe_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The student being subscribed. */
    private \stdClass $student;

    /**
     * A course with a student and a forum in the ordinary, choose-for-yourself
     * subscription mode.
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
    }

    /**
     * The ordinary case: a forum whose name matches gets the subject
     * subscribed.
     */
    public function test_it_subscribes_to_a_matching_forum(): void {
        $forum = $this->a_forum('Welcome forum');

        $result = $this->run_node('name', 'contains', 'welcome');

        $this->assertTrue(subscriptions::is_subscribed((int) $this->student->id, $forum));
        $this->assertSame(['Welcome forum'], $result->summary()['subscribed']);
        $this->assertSame(1, $result->summary()['matched']);
    }

    /**
     * A forum whose *subscription mode is disabled* still gets subscribed.
     *
     * This is not a special case this node invents: it is what Moodle's own
     * `subscribe.php` does. It refuses a disabled forum only when the actor
     * also lacks `mod/forum:managesubscriptions` — which this node requires —
     * so the same override applies here. Blocking it would contradict the
     * capability the node itself declares as a requirement.
     */
    public function test_a_disabled_forum_can_still_be_subscribed(): void {
        $forum = $this->a_forum('Read only forum', FORUM_DISALLOWSUBSCRIBE);

        $this->assertFalse(
            subscriptions::is_subscribable($forum),
            'Confirms the forum really is in disabled mode before testing the override.'
        );

        $result = $this->run_node('name', 'contains', 'read only');

        $this->assertTrue(subscriptions::is_subscribed((int) $this->student->id, $forum));
        $this->assertSame(['Read only forum'], $result->summary()['subscribed']);
    }

    /**
     * A forum that forces subscription on everyone reports the subject as
     * already there, rather than writing a redundant row.
     */
    public function test_a_forced_forum_is_reported_as_already_subscribed(): void {
        $this->a_forum('Everyone is here', FORUM_FORCESUBSCRIBE);

        $result = $this->run_node('name', 'contains', 'everyone');

        $this->assertSame([], $result->summary()['subscribed']);
        $this->assertSame(['Everyone is here'], $result->summary()['already']);
    }

    /**
     * Somebody already subscribed by hand is reported as already there too.
     */
    public function test_somebody_already_subscribed_is_reported_as_such(): void {
        $forum = $this->a_forum('Welcome forum');
        subscriptions::subscribe_user((int) $this->student->id, $forum);

        $result = $this->run_node('name', 'contains', 'welcome');

        $this->assertSame([], $result->summary()['subscribed']);
        $this->assertSame(['Welcome forum'], $result->summary()['already']);
    }

    /**
     * Matching by idnumber reaches the activity's own field.
     */
    public function test_it_can_match_by_idnumber(): void {
        $this->a_forum('Whatever it is called', FORUM_CHOOSESUBSCRIBE, 'FORO-BIENVENIDA');

        $result = $this->run_node('idnumber', 'startswith', 'FORO-');

        $this->assertSame(1, $result->summary()['matched']);
    }

    /**
     * No forum matches the pattern: the run stops, and says why.
     */
    public function test_no_matching_forum_stops_the_run(): void {
        $this->a_forum('Something else entirely');

        $result = $this->run_node('name', 'contains', 'nonexistent');

        $this->assertTrue($result->stops());
        $this->assertSame('noforums', $result->summary()['reason']);
    }

    /**
     * An invalid pattern stops the run rather than matching by accident.
     */
    public function test_an_invalid_pattern_stops_the_run(): void {
        $this->a_forum('Welcome forum');

        $result = $this->run_node('name', 'regex', '[');

        $this->assertTrue($result->stops());
        $this->assertSame('badpattern', $result->summary()['reason']);
    }

    /**
     * A dry run finds the same forums but writes nothing.
     */
    public function test_a_dry_run_writes_nothing(): void {
        $forum = $this->a_forum('Welcome forum');

        $node = new action_forum_subscribe();
        $context = new flow_context((object) ['id' => 1], [
            'relateduserid' => (int) $this->student->id,
            'courseid' => (int) $this->course->id,
        ], true);
        $context->set_subject((int) $this->student->id);
        $context->set('courseid', (int) $this->course->id);

        $result = $node->run($context, ['match' => 'name', 'operator' => 'contains', 'pattern' => 'welcome']);

        $this->assertTrue($result->summary()['dryrun']);
        $this->assertFalse(subscriptions::is_subscribed((int) $this->student->id, $forum));
    }

    /**
     * This node needs `mod/forum:managesubscriptions` to do what it does,
     * because that is the capability that lets it override a disabled forum.
     */
    public function test_it_requires_managesubscriptions(): void {
        $this->assertSame(
            ['mod/forum:managesubscriptions'],
            action_forum_subscribe::required_capabilities([])
        );
    }

    /**
     * A run with no subject settled yet stops rather than acting on nobody.
     */
    public function test_no_subject_stops_the_run(): void {
        $node = new action_forum_subscribe();
        $context = new flow_context((object) ['id' => 1], []);

        $result = $node->run($context, ['match' => 'name', 'operator' => 'contains', 'pattern' => 'welcome']);

        $this->assertTrue($result->stops());
        $this->assertSame('nosubject', $result->summary()['reason']);
    }

    /**
     * A run with a subject but no course stops too: there is nowhere to look
     * for a forum to subscribe them to.
     */
    public function test_no_course_stops_the_run(): void {
        $node = new action_forum_subscribe();
        $context = new flow_context((object) ['id' => 1], []);
        $context->set_subject((int) $this->student->id);

        $result = $node->run($context, ['match' => 'name', 'operator' => 'contains', 'pattern' => 'welcome']);

        $this->assertTrue($result->stops());
        $this->assertSame('nocourse', $result->summary()['reason']);
    }

    /**
     * A forum with the given subscription mode and, optionally, idnumber.
     *
     * @param string $name
     * @param int $forcesubscribe One of the FORUM_* subscription mode constants.
     * @param string $idnumber
     * @return \stdClass
     */
    private function a_forum(string $name, int $forcesubscribe = FORUM_CHOOSESUBSCRIBE, string $idnumber = ''): \stdClass {
        return $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'name' => $name,
            'forcesubscribe' => $forcesubscribe,
            'idnumber' => $idnumber,
        ]);
    }

    /**
     * Runs the node against the course and student set up in {@see self::setUp()}.
     *
     * @param string $match
     * @param string $operator
     * @param string $pattern
     * @return \tool_flowboard\local\node\node_result
     */
    private function run_node(string $match, string $operator, string $pattern) {
        $node = new action_forum_subscribe();
        $context = new flow_context((object) ['id' => 1], [
            'relateduserid' => (int) $this->student->id,
            'courseid' => (int) $this->course->id,
        ]);
        $context->set_subject((int) $this->student->id);
        $context->set('courseid', (int) $this->course->id);

        return $node->run($context, ['match' => $match, 'operator' => $operator, 'pattern' => $pattern]);
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
