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

use tool_flowboard\local\engine;
use tool_flowboard\local\flow_repository;

/**
 * Runs one flow against one event, away from the request that caused it.
 *
 * Everything is checked again here, because time has passed since the observer
 * queued this: the flow may have been paused, republished or deleted, and the
 * site may have pulled the master switch. A task that acted on what was true
 * when it was queued would be acting on the past.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_flow extends \core\task\adhoc_task {
    /**
     * What this task is called in the task logs.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:run_flow', 'tool_flowboard');
    }

    /**
     * Runs the flow, if it is still a flow that should run.
     */
    public function execute(): void {
        $data = (array) $this->get_custom_data();
        $flowid = (int) ($data['flowid'] ?? 0);
        $event = (array) ($data['eventdata'] ?? []);

        if ($flowid === 0 || $event === []) {
            return;
        }

        if (!get_config('tool_flowboard', 'enabled')) {
            mtrace('Flowboard is switched off; flow ' . $flowid . ' was not run.');

            return;
        }

        $flow = flow_repository::get($flowid);

        if ($flow === null || $flow->status !== flow_repository::STATUS_LIVE) {
            mtrace('Flow ' . $flowid . ' is no longer live; nothing was run.');

            return;
        }

        if (empty($flow->currentversionid)) {
            mtrace('Flow ' . $flowid . ' has no published version; nothing was run.');

            return;
        }

        $runid = engine::run($flow, (int) $flow->currentversionid, self::as_array($event), [
            'depth' => (int) ($data['depth'] ?? 0),
            'triggertype' => 'event',
        ]);

        mtrace('Flow ' . $flow->idnumber . ' ran as ' . $runid . '.');
    }

    /**
     * The event as an array all the way down.
     *
     * Adhoc task data comes back as objects, because it travels as JSON; the
     * nodes read the event the way Moodle handed it over, which is arrays.
     *
     * @param array|object $data
     * @return array
     */
    private static function as_array($data): array {
        $decoded = json_decode(json_encode($data), true);

        return is_array($decoded) ? $decoded : [];
    }
}
