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
 * What events were actually carrying when they went past.
 *
 * Moodle describes an event's name, component and purpose, but not the
 * contents of its `other` array — there is no schema for it, and a plugin can
 * put whatever it likes in there. So the only honest way to offer somebody a
 * field to build a rule on is to have seen it.
 *
 * What is kept is the *shape*: which keys an event carries, and a sample with
 * anything that names a person taken out. Nobody needs to know that user 42
 * started course 7 in order to learn that this event carries `courseid` and
 * `other.state`.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_seen_repository {
    /** @var string The table this repository owns. */
    private const TABLE = 'tool_flowboard_event_seen';

    /**
     * Keys whose value is dropped from the sample wherever they appear,
     * however deep. These are the ones that name a person or point straight at
     * one, and no rule needs their value to be written — only their presence.
     */
    private const IDENTIFYING = [
        'userid',
        'relateduserid',
        'realuserid',
        'objectid',
        'courseid',
        'contextid',
        'contextinstanceid',
    ];

    /**
     * Anything whose key reads like a person is dropped too. The list above
     * cannot be complete: `other` is a free array and a plugin may put
     * anything in it — `local_coursestate` puts `user_idnumber` there — so the
     * sample also refuses keys that look personal, rather than keeping
     * whatever it has not been told about.
     */
    private const PERSONAL_PATTERN = '/user|email|name|idnumber|phone|address|\bip\b|token|password/i';

    /** @var int A value longer than this is kept as its type: samples are shapes, not content. */
    private const MAX_SAMPLE_LENGTH = 40;

    /**
     * Notes that an event went past, and what it was carrying.
     *
     * @param string $eventname The event class, with its leading backslash.
     * @param string $component
     * @param array $data The event's own data, from `get_data()`.
     */
    public static function record(string $eventname, string $component, array $data): void {
        global $DB;

        $now = time();
        $keys = self::keys_of($data);
        $existing = $DB->get_record(self::TABLE, ['eventname' => $eventname]);

        if ($existing === false) {
            $DB->insert_record(self::TABLE, (object) [
                'eventname' => $eventname,
                'component' => $component,
                'payloadkeys' => json_encode($keys),
                'sample' => json_encode(self::anonymise($data)),
                'seencount' => 1,
                'timefirstseen' => $now,
                'timelastseen' => $now,
            ]);

            return;
        }

        // The keys are merged rather than replaced: the same event can carry
        // different keys on different occasions, and a field that only appears
        // sometimes is still a field somebody may want to build a rule on.
        $known = json_decode($existing->payloadkeys ?? '', true) ?: [];
        $merged = array_values(array_unique(array_merge($known, $keys)));
        sort($merged);

        $DB->update_record(self::TABLE, (object) [
            'id' => $existing->id,
            'payloadkeys' => json_encode($merged),
            'sample' => json_encode(self::anonymise($data)),
            'seencount' => (int) $existing->seencount + 1,
            'timelastseen' => $now,
        ]);
    }

    /**
     * What is known about one event's payload.
     *
     * @param string $eventname
     * @return \stdClass|null Null where this event has never been seen.
     */
    public static function get(string $eventname): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['eventname' => $eventname]) ?: null;
    }

    /**
     * Everything that has been seen.
     *
     * @return \stdClass[] Keyed by event name.
     */
    public static function all(): array {
        global $DB;

        $seen = [];

        foreach ($DB->get_records(self::TABLE, null, 'eventname ASC') as $record) {
            $seen[$record->eventname] = $record;
        }

        return $seen;
    }

    /**
     * The keys an event carries, including the ones nested in `other`.
     *
     * @param array $data
     * @return string[] Sorted, so two recordings of the same event compare equal.
     */
    private static function keys_of(array $data): array {
        $keys = [];

        foreach ($data as $key => $value) {
            if ($key === 'other' && is_array($value)) {
                foreach (array_keys($value) as $otherkey) {
                    $keys[] = 'other.' . $otherkey;
                }

                continue;
            }

            $keys[] = (string) $key;
        }

        sort($keys);

        return $keys;
    }

    /**
     * The sample: the shape of an event, not its contents.
     *
     * A value survives only when it is a short scalar whose key does not read
     * like a person. Everything else is replaced by its type, which is all a
     * field picker needs in order to offer the field. The site-wide catalogue
     * is not a place where one student's data can be found by anybody who can
     * open it, and it is not somewhere a person could be erased from either —
     * so nothing that identifies one goes in.
     *
     * @param array $data
     * @return array
     */
    private static function anonymise(array $data): array {
        $sample = [];

        foreach ($data as $key => $value) {
            $key = (string) $key;

            if (is_array($value)) {
                $sample[$key] = self::anonymise($value);

                continue;
            }

            if (in_array($key, self::IDENTIFYING, true) || preg_match(self::PERSONAL_PATTERN, $key)) {
                $sample[$key] = '(' . gettype($value) . ')';

                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                $sample[$key] = '(' . gettype($value) . ')';

                continue;
            }

            if (is_string($value) && \core_text::strlen($value) > self::MAX_SAMPLE_LENGTH) {
                $sample[$key] = '(string)';

                continue;
            }

            $sample[$key] = $value;
        }

        return $sample;
    }
}
