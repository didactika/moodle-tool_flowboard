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

namespace tool_flowboard\local;

/**
 * Which user and role a flow acts as.
 *
 * Only the record of it. Creating the user, building the role and working out
 * which capabilities it should hold is the actor provisioning's job; this is
 * where the answer is kept so that the same actor is found again, and so that
 * a change in what a flow needs can be shown as a difference against what it
 * was already given.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class actor_repository {
    /** @var string The actor may act. */
    public const STATUS_ACTIVE = 'active';

    /** @var string The actor exists but has been stood down with its flow. */
    public const STATUS_SUSPENDED = 'suspended';

    /** @var string The table this repository owns. */
    private const TABLE = 'tool_flowboard_actor';

    /**
     * Remembers the actor of a flow, or updates what it was given.
     *
     * @param int $flowid
     * @param int $userid The user the flow acts as.
     * @param int $roleid The role that carries its capabilities.
     * @param string[] $capabilities What that role was granted.
     * @param int[] $contextids Where the role was assigned.
     * @return \stdClass The stored actor.
     */
    public static function upsert(
        int $flowid,
        int $userid,
        int $roleid,
        array $capabilities = [],
        array $contextids = []
    ): \stdClass {
        global $DB;

        $now = time();
        $existing = self::for_flow($flowid);

        $record = (object) [
            'flowid' => $flowid,
            'userid' => $userid,
            'roleid' => $roleid,
            'capabilities' => json_encode(array_values($capabilities)),
            'contextids' => json_encode(array_values($contextids)),
            'status' => $existing->status ?? self::STATUS_ACTIVE,
            'timemodified' => $now,
        ];

        if ($existing !== null) {
            $record->id = $existing->id;
            $DB->update_record(self::TABLE, $record);

            return self::for_flow($flowid);
        }

        $record->timecreated = $now;
        $record->id = $DB->insert_record(self::TABLE, $record);

        return $record;
    }

    /**
     * The actor of one flow.
     *
     * @param int $flowid
     * @return \stdClass|null Null while the flow has never been published.
     */
    public static function for_flow(int $flowid): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['flowid' => $flowid]) ?: null;
    }

    /**
     * Every actor, so a site can see who these users are and what they serve.
     *
     * @return \stdClass[] Keyed by id.
     */
    public static function all(): array {
        global $DB;

        return $DB->get_records(self::TABLE, null, 'flowid ASC');
    }

    /**
     * The capabilities an actor was granted.
     *
     * @param \stdClass $actor
     * @return string[]
     */
    public static function capabilities(\stdClass $actor): array {
        return json_decode($actor->capabilities ?? '', true) ?: [];
    }

    /**
     * Stands an actor down, or lets it act again.
     *
     * @param int $flowid
     * @param string $status One of the STATUS_* constants.
     */
    public static function set_status(int $flowid, string $status): void {
        global $DB;

        if (!in_array($status, [self::STATUS_ACTIVE, self::STATUS_SUSPENDED], true)) {
            throw new \coding_exception('Unknown actor status: ' . $status);
        }

        $DB->set_field(self::TABLE, 'status', $status, ['flowid' => $flowid]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['flowid' => $flowid]);
    }
}
