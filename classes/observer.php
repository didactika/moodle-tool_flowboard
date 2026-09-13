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

use tool_flowboard\local\engine;
use tool_flowboard\local\flow_index;
use tool_flowboard\task\run_flow;

/**
 * Everything that happens on the site comes through here.
 *
 * Moodle lets an observer register for `*`, which means this method runs for
 * every event the site fires — hundreds of times in a busy request, and almost
 * always with nothing to do. So the first thing it does is the cheapest
 * question there is: one read of an application cache. No flow is waiting for
 * this event, and it is gone before it has touched the database.
 *
 * What it never does is the work. An event is fired inside whatever was
 * happening at the time, often inside a transaction and always inside somebody
 * else's request; a flow may act on hundreds of people. So the observer only
 * decides that there is something to do, and hands it to a task.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Something happened. Is anybody waiting for it?
     *
     * @param \core\event\base $event
     */
    public static function dispatch(\core\event\base $event): void {
        $flowids = flow_index::for_event($event->eventname);

        if ($flowids === []) {
            return;
        }

        if (!get_config('tool_flowboard', 'enabled')) {
            // The site has pulled the master switch. Nothing is queued, so
            // switching it back on does not release a backlog of everything
            // that happened while it was off.
            return;
        }

        $depth = engine::current_depth();

        if ($depth >= self::max_depth()) {
            // A flow set off a flow that set off a flow. Somewhere in there is
            // a loop, and the safe assumption is that this is it.
            return;
        }

        foreach ($flowids as $flowid) {
            self::queue($flowid, $event, $depth);
        }
    }

    /**
     * Hands one flow and one event to a task.
     *
     * @param int $flowid
     * @param \core\event\base $event
     * @param int $depth
     */
    private static function queue(int $flowid, \core\event\base $event, int $depth): void {
        $task = new run_flow();
        $task->set_custom_data((object) [
            'flowid' => $flowid,
            'eventname' => $event->eventname,
            'eventdata' => $event->get_data(),
            'depth' => $depth,
        ]);

        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * How many flows deep the site is willing to go.
     *
     * @return int
     */
    private static function max_depth(): int {
        $configured = (int) get_config('tool_flowboard', 'maxdepth');

        return $configured > 0 ? $configured : 3;
    }
}
