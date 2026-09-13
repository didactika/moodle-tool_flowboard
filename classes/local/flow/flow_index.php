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

namespace tool_flowboard\local\flow;

use tool_flowboard\local\node\trigger_event;

/**
 * Which flows are waiting for which event.
 *
 * This exists for one reason, and it decides whether this plugin can be
 * installed on a real site at all. The observer that feeds it runs on **every**
 * event Moodle fires — hundreds of times in a busy request — and almost always
 * has nothing to do. So the question "is anyone waiting for this?" has to be
 * answered without touching the database: one read of an application cache,
 * and out.
 *
 * The index is rebuilt, not edited: it is small, it changes only when a flow is
 * published, paused or deleted, and a rebuild is one query.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class flow_index {
    /** @var string The one key the cache holds. */
    private const KEY = 'index';

    /**
     * The flows waiting for one event.
     *
     * @param string $eventname The event class, as Moodle names it.
     * @return int[] Flow ids, empty when nobody is waiting.
     */
    public static function for_event(string $eventname): array {
        $index = self::index();

        return $index[$eventname] ?? [];
    }

    /**
     * The whole index: every event with somebody waiting for it.
     *
     * @return array<string, int[]>
     */
    public static function index(): array {
        $cache = self::cache();
        $index = $cache->get(self::KEY);

        if ($index !== false) {
            return $index;
        }

        $index = self::build();
        $cache->set(self::KEY, $index);

        return $index;
    }

    /**
     * Throws the index away, so the next event rebuilds it.
     *
     * Called whenever a flow is published, paused, resumed or deleted — the
     * four things that change who is waiting for what.
     */
    public static function invalidate(): void {
        self::cache()->delete(self::KEY);
    }

    /**
     * Reads who is waiting for what out of the published flows.
     *
     * Only live flows, and only the version each one is actually running: a
     * draft is a drawing nobody has agreed to yet, and an older version is a
     * record rather than an instruction.
     *
     * @return array<string, int[]>
     */
    private static function build(): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT n.id, n.config, f.id AS flowid
               FROM {tool_flowboard_node} n
               JOIN {tool_flowboard_flow} f ON f.currentversionid = n.versionid
              WHERE f.status = :live AND n.type = :triggertype",
            ['live' => flow_repository::STATUS_LIVE, 'triggertype' => trigger_event::TYPE]
        );
        $index = [];

        foreach ($rows as $row) {
            $config = json_decode($row->config ?? '', true) ?: [];
            $eventname = trim((string) ($config['eventname'] ?? ''));

            if ($eventname === '') {
                continue;
            }

            $index[$eventname][(int) $row->flowid] = (int) $row->flowid;
        }

        foreach ($index as $eventname => $flowids) {
            $index[$eventname] = array_values($flowids);
        }

        return $index;
    }

    /**
     * Where the index lives.
     *
     * @return \cache_loader
     */
    private static function cache(): \cache_loader {
        return \cache::make('tool_flowboard', 'flowindex');
    }
}
