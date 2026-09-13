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
 * The observer that sees every event the site fires.
 *
 * The first test here is the one that decides whether this plugin can be
 * installed on a real site: this code runs hundreds of times per request, and
 * on a site where nothing is waiting for the event it must not touch the
 * database at all.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\observer
 */
final class observer_test extends \advanced_testcase {
    /** @var \stdClass A course to fire events in. */
    private \stdClass $course;

    /**
     * A course, and a clean slate — outside a transaction.
     *
     * `preventResetByRollback()` is not a detail here, it is the only way these
     * tests can mean anything. PHPUnit runs each test inside a transaction and
     * rolls it back afterwards, and this plugin's observer is deliberately
     * declared `internal => false`: Moodle holds such observers until the
     * transaction commits and drops them if it rolls back. So inside the
     * default test transaction the observer is never reached at all, and a
     * test that fired an event and found nothing queued would be testing
     * PHPUnit rather than the plugin.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * With nothing waiting for an event, seeing it costs no queries at all.
     *
     * This is the whole reason the index is a cache. If this test ever starts
     * failing, the plugin has become something a busy site cannot afford.
     */
    public function test_an_event_nobody_waits_for_costs_no_queries(): void {
        global $DB;

        $event = $this->an_event();

        // The first look builds the index; it is the second that has to be free.
        observer::dispatch($event);

        $before = $DB->perf_get_reads();
        observer::dispatch($event);
        observer::dispatch($event);
        observer::dispatch($event);
        $after = $DB->perf_get_reads();

        $this->assertSame(
            $before,
            $after,
            'Seeing an event nobody is waiting for must not touch the database.'
        );
    }

    /**
     * A live flow waiting for the event gets a task, and the task carries the
     * event with it.
     */
    public function test_an_event_with_a_flow_waiting_queues_a_task(): void {
        global $DB;

        $flow = $this->a_live_flow('\core\event\course_viewed');

        $this->an_event()->trigger();

        $tasks = $DB->get_records('task_adhoc', ['classname' => '\tool_flowboard\task\run_flow']);
        $this->assertCount(1, $tasks);

        $data = (array) json_decode(reset($tasks)->customdata, true);
        $this->assertSame((int) $flow->id, (int) $data['flowid']);
        $this->assertSame('\core\event\course_viewed', $data['eventname']);
        $this->assertSame((int) $this->course->id, (int) $data['eventdata']['courseid']);
    }

    /**
     * An event fired inside a transaction that then rolls back never happened,
     * and nothing is queued for it.
     *
     * This is what `internal => false` buys, and it is worth a test of its own:
     * a flow acting on something the site decided not to do would be very hard
     * to explain to whoever it happened to.
     */
    public function test_an_event_rolled_back_queues_nothing(): void {
        global $DB;

        $this->a_live_flow('\core\event\course_viewed');

        $transaction = $DB->start_delegated_transaction();
        $this->an_event()->trigger();

        try {
            $transaction->rollback(new \moodle_exception('error'));
        } catch (\moodle_exception $e) {
            $this->assertSame('error', $e->errorcode);
        }

        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\tool_flowboard\task\run_flow']));
    }

    /**
     * With the master switch off, nothing is queued — so switching it back on
     * does not release a backlog of everything that happened meanwhile.
     */
    public function test_the_master_switch_queues_nothing(): void {
        global $DB;

        $this->a_live_flow('\core\event\course_viewed');
        set_config('enabled', 0, 'tool_flowboard');

        $this->an_event()->trigger();

        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\tool_flowboard\task\run_flow']));
    }

    /**
     * A flow that has not been published is a drawing, not an instruction.
     */
    public function test_a_draft_flow_is_not_waiting_for_anything(): void {
        global $DB;

        $flow = flow_repository::create('draft-flow', 'A draft');
        graph_repository::publish((int) $flow->id, $this->graph('\core\event\course_viewed'));

        $this->an_event()->trigger();

        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\tool_flowboard\task\run_flow']));
        $this->assertSame([], flow_index::for_event('\core\event\course_viewed'));
    }

    /**
     * Pausing a flow stops it hearing anything, without deleting what it did.
     */
    public function test_a_paused_flow_stops_hearing(): void {
        global $DB;

        $flow = $this->a_live_flow('\core\event\course_viewed');
        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_PAUSED);

        $this->an_event()->trigger();

        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\tool_flowboard\task\run_flow']));
    }

    /**
     * A published, live flow listening for one event.
     *
     * @param string $eventname
     * @return \stdClass
     */
    private function a_live_flow(string $eventname): \stdClass {
        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        graph_repository::publish((int) $flow->id, $this->graph($eventname));
        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_LIVE);

        return $flow;
    }

    /**
     * The smallest drawing there is: one trigger.
     *
     * @param string $eventname
     * @return array
     */
    private function graph(string $eventname): array {
        return [
            'nodes' => [[
                'key' => 'trigger',
                'type' => 'trigger_event',
                'config' => ['eventname' => $eventname, 'subject' => 'userid'],
            ]],
            'edges' => [],
        ];
    }

    /**
     * An event this test can fire as often as it likes.
     *
     * @return \core\event\course_viewed
     */
    private function an_event(): \core\event\course_viewed {
        return \core\event\course_viewed::create([
            'context' => \context_course::instance($this->course->id),
        ]);
    }
}
