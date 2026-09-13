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

use tool_flowboard\local\actor_repository;
use tool_flowboard\local\flow_repository;

/**
 * The record of which user and role a flow acts as.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\actor_repository
 */
final class actor_repository_test extends \advanced_testcase {
    /** @var \stdClass The flow the actor belongs to. */
    private \stdClass $flow;

    /**
     * A flow to give an actor to.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
    }

    /**
     * An actor is remembered with what it was granted, so the next publish can
     * be shown as a difference against it.
     */
    public function test_an_actor_is_remembered_with_its_capabilities(): void {
        $user = $this->getDataGenerator()->create_user();

        actor_repository::upsert((int) $this->flow->id, (int) $user->id, 7, ['mod/forum:managesubscriptions'], [1]);

        $actor = actor_repository::for_flow((int) $this->flow->id);

        $this->assertEquals($user->id, $actor->userid);
        $this->assertSame(['mod/forum:managesubscriptions'], actor_repository::capabilities($actor));
        $this->assertSame(actor_repository::STATUS_ACTIVE, $actor->status);
    }

    /**
     * A flow has one actor, not one per publish.
     */
    public function test_publishing_again_updates_the_same_actor(): void {
        $user = $this->getDataGenerator()->create_user();

        actor_repository::upsert((int) $this->flow->id, (int) $user->id, 7, ['mod/forum:managesubscriptions']);
        actor_repository::upsert((int) $this->flow->id, (int) $user->id, 7, [
            'mod/forum:managesubscriptions',
            'enrol/manual:enrol',
        ]);

        $this->assertCount(1, actor_repository::all());
        $this->assertCount(2, actor_repository::capabilities(actor_repository::for_flow((int) $this->flow->id)));
    }

    /**
     * Standing an actor down is how a paused flow stops being able to act.
     */
    public function test_an_actor_can_be_stood_down(): void {
        $user = $this->getDataGenerator()->create_user();
        actor_repository::upsert((int) $this->flow->id, (int) $user->id, 7);

        actor_repository::set_status((int) $this->flow->id, actor_repository::STATUS_SUSPENDED);

        $this->assertSame(
            actor_repository::STATUS_SUSPENDED,
            actor_repository::for_flow((int) $this->flow->id)->status
        );
    }

    /**
     * A flow that has never been published has no actor, and says so.
     */
    public function test_a_flow_without_an_actor_says_so(): void {
        $this->assertNull(actor_repository::for_flow((int) $this->flow->id));
    }
}
