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
use tool_flowboard\local\node\action_forum_unsubscribe;

/**
 * Unsubscribing the run's subject from forums that match a pattern.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\node\action_forum_unsubscribe
 */
final class action_forum_unsubscribe_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The student being unsubscribed. */
    private \stdClass $student;

    /**
     * A course with a subscribed student.
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
     * The ordinary case: subscribed, and this removes it.
     */
    public function test_it_unsubscribes_from_a_matching_forum(): void {
        $forum = $this->a_forum('Welcome forum');
        subscriptions::subscribe_user((int) $this->student->id, $forum);

        $result = $this->run_node('name', 'contains', 'welcome');

        $this->assertFalse(subscriptions::is_subscribed((int) $this->student->id, $forum));
        $this->assertSame(['Welcome forum'], $result->summary()['unsubscribed']);
    }

    /**
     * Somebody never subscribed is reported as such, not as newly removed.
     */
    public function test_somebody_not_subscribed_is_reported_as_such(): void {
        $this->a_forum('Welcome forum');

        $result = $this->run_node('name', 'contains', 'welcome');

        $this->assertSame([], $result->summary()['unsubscribed']);
        $this->assertSame(['Welcome forum'], $result->summary()['wasnt']);
    }

    /**
     * A forum that forces subscription on everyone cannot actually be left:
     * the node must not report success for something that did not happen.
     *
     * `subscriptions::is_subscribed()` answers true for a forced forum
     * regardless of any row this node deletes, as long as the person holds
     * `mod/forum:allowforcesubscribe` — which they do here, since nothing
     * revoked it. Reporting "unsubscribed" in that case would be recording a
     * fact that is not true: the next email still reaches them.
     */
    public function test_a_forced_forum_reports_still_forced_rather_than_unsubscribed(): void {
        $forum = $this->a_forum('Everyone is here', FORUM_FORCESUBSCRIBE);

        $result = $this->run_node('name', 'contains', 'everyone');

        $this->assertTrue(
            subscriptions::is_subscribed((int) $this->student->id, $forum),
            'The forum still says they are subscribed - the node must agree.'
        );
        $this->assertSame([], $result->summary()['unsubscribed']);
        $this->assertSame(['Everyone is here'], $result->summary()['stillforced']);
    }

    /**
     * A disabled forum's subscribers can still be removed by this node,
     * mirroring what {@see action_forum_subscribe} does on the way in.
     */
    public function test_a_disabled_forum_can_still_be_unsubscribed(): void {
        $forum = $this->a_forum('Read only forum', FORUM_DISALLOWSUBSCRIBE);
        subscriptions::subscribe_user((int) $this->student->id, $forum);

        $result = $this->run_node('name', 'contains', 'read only');

        $this->assertFalse(subscriptions::is_subscribed((int) $this->student->id, $forum));
        $this->assertSame(['Read only forum'], $result->summary()['unsubscribed']);
    }

    /**
     * A dry run finds the same forum but leaves the subscription in place.
     */
    public function test_a_dry_run_writes_nothing(): void {
        $forum = $this->a_forum('Welcome forum');
        subscriptions::subscribe_user((int) $this->student->id, $forum);

        $node = new action_forum_unsubscribe();
        $context = new flow_context((object) ['id' => 1], [], true);
        $context->set_subject((int) $this->student->id);
        $context->set('courseid', (int) $this->course->id);

        $result = $node->run($context, ['match' => 'name', 'operator' => 'contains', 'pattern' => 'welcome']);

        $this->assertTrue($result->summary()['dryrun']);
        $this->assertTrue(subscriptions::is_subscribed((int) $this->student->id, $forum));
    }

    /**
     * This node needs the same capability as its subscribe counterpart, for
     * the same reason: overriding what the forum's own mode would otherwise
     * enforce.
     */
    public function test_it_requires_managesubscriptions(): void {
        $this->assertSame(
            ['mod/forum:managesubscriptions'],
            action_forum_unsubscribe::required_capabilities([])
        );
    }

    /**
     * A forum with the given subscription mode.
     *
     * @param string $name
     * @param int $forcesubscribe One of the FORUM_* subscription mode constants.
     * @return \stdClass
     */
    private function a_forum(string $name, int $forcesubscribe = FORUM_CHOOSESUBSCRIBE): \stdClass {
        return $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'name' => $name,
            'forcesubscribe' => $forcesubscribe,
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
        $node = new action_forum_unsubscribe();
        $context = new flow_context((object) ['id' => 1], []);
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
