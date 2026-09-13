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

use tool_flowboard\local\actor\actor_provisioner;

/**
 * The flows themselves: what exists, and which of them are running.
 *
 * A flow is a name and a state; what it actually does lives in its published
 * version ({@see graph_repository}). Splitting the two is what lets a run be
 * explained months later by the drawing it ran, rather than by whatever the
 * flow has been edited into since.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class flow_repository {
    /** @var string Being written; it does not react to anything yet. */
    public const STATUS_DRAFT = 'draft';

    /** @var string Published and reacting. */
    public const STATUS_LIVE = 'live';

    /** @var string Published, but told to stop. */
    public const STATUS_PAUSED = 'paused';

    /** @var string The table this repository owns. */
    private const TABLE = 'tool_flowboard_flow';

    /**
     * Creates a flow, as a draft. Nothing reacts to anything until it is
     * published.
     *
     * @param string $idnumber Stable name, unique on the site.
     * @param string $name What a person calls it.
     * @param string $description Optional; what it is for.
     * @return \stdClass The stored flow.
     */
    public static function create(string $idnumber, string $name, string $description = ''): \stdClass {
        global $DB, $USER;

        $now = time();
        $flow = (object) [
            'idnumber' => $idnumber,
            'name' => $name,
            'description' => $description,
            'status' => self::STATUS_DRAFT,
            'dryrun' => 0,
            'currentversionid' => null,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => (int) $USER->id,
        ];
        $flow->id = $DB->insert_record(self::TABLE, $flow);

        return $flow;
    }

    /**
     * One flow by its id.
     *
     * @param int $flowid
     * @return \stdClass|null Null when there is no such flow.
     */
    public static function get(int $flowid): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $flowid]) ?: null;
    }

    /**
     * One flow by the name that travels with it.
     *
     * @param string $idnumber
     * @return \stdClass|null
     */
    public static function get_by_idnumber(string $idnumber): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['idnumber' => $idnumber]) ?: null;
    }

    /**
     * Every flow, in the order a person would read them.
     *
     * @return \stdClass[] Keyed by id.
     */
    public static function all(): array {
        global $DB;

        return $DB->get_records(self::TABLE, null, 'name ASC');
    }

    /**
     * The flows that are actually reacting to things.
     *
     * @return \stdClass[] Keyed by id.
     */
    public static function live(): array {
        global $DB;

        return $DB->get_records(self::TABLE, ['status' => self::STATUS_LIVE], 'name ASC');
    }

    /**
     * Renames a flow, or changes what it says about itself.
     *
     * The idnumber is deliberately not editable here: exports and the flow's
     * own actor are named after it, so changing it would quietly orphan both.
     *
     * @param int $flowid
     * @param array $fields Any of name, description, dryrun.
     */
    public static function update(int $flowid, array $fields): void {
        global $DB, $USER;

        $allowed = array_intersect_key($fields, array_flip(['name', 'description', 'dryrun']));

        if ($allowed === []) {
            return;
        }

        $record = (object) ($allowed + [
            'id' => $flowid,
            'timemodified' => time(),
            'usermodified' => (int) $USER->id,
        ]);
        $DB->update_record(self::TABLE, $record);
    }

    /**
     * Moves a flow between draft, live and paused.
     *
     * @param int $flowid
     * @param string $status One of the STATUS_* constants.
     */
    public static function set_status(int $flowid, string $status): void {
        global $DB, $USER;

        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_LIVE, self::STATUS_PAUSED], true)) {
            throw new \coding_exception('Unknown flow status: ' . $status);
        }

        $DB->update_record(self::TABLE, (object) [
            'id' => $flowid,
            'status' => $status,
            'timemodified' => time(),
            'usermodified' => (int) $USER->id,
        ]);

        // A flow that is not live must not be able to act, whatever state it
        // moves to instead: a paused flow and a flow put back into draft are
        // both "not running" as far as its actor is concerned. Live is the one
        // state that gets it back — for a flow with no actor yet (never
        // published) this is a no-op; publishing is what creates one.
        if ($status === self::STATUS_LIVE) {
            actor_provisioner::resume($flowid);
        } else {
            actor_provisioner::suspend($flowid);
        }

        // Who is waiting for what has just changed.
        flow_index::invalidate();
    }

    /**
     * Points a flow at the version it now runs.
     *
     * @param int $flowid
     * @param int $versionid
     */
    public static function set_current_version(int $flowid, int $versionid): void {
        global $DB, $USER;

        $DB->update_record(self::TABLE, (object) [
            'id' => $flowid,
            'currentversionid' => $versionid,
            'timemodified' => time(),
            'usermodified' => (int) $USER->id,
        ]);

        // A published flow may now be listening for something else entirely.
        flow_index::invalidate();
    }

    /**
     * Deletes a flow and everything that belonged to it.
     *
     * The run history goes too, which is a real loss — it is the answer to
     * "why did this happen to this person" — so deleting a flow is not the
     * same as pausing one, and the interface has to say so.
     *
     * @param int $flowid
     */
    public static function delete(int $flowid): void {
        global $DB;

        $runids = $DB->get_fieldset_select('tool_flowboard_run', 'id', 'flowid = :flowid', ['flowid' => $flowid]);

        if ($runids !== []) {
            [$insql, $params] = $DB->get_in_or_equal($runids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('tool_flowboard_run_node', "runid {$insql}", $params);
        }

        // The real user account and role, not just the row that records them:
        // dropping only tool_flowboard_actor would leave both existing forever
        // with nothing left pointing at why.
        actor_provisioner::deprovision($flowid);

        $DB->delete_records('tool_flowboard_run', ['flowid' => $flowid]);
        $DB->delete_records('tool_flowboard_edge', ['flowid' => $flowid]);
        $DB->delete_records('tool_flowboard_node', ['flowid' => $flowid]);
        $DB->delete_records('tool_flowboard_version', ['flowid' => $flowid]);
        $DB->delete_records('tool_flowboard_actor', ['flowid' => $flowid]);
        $DB->delete_records(self::TABLE, ['id' => $flowid]);

        flow_index::invalidate();
    }
}
