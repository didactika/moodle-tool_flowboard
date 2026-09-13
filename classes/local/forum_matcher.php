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
 * The forums of a course whose name or idnumber matches a pattern.
 *
 * A forum has no idnumber of its own to match against — only its course
 * module does — so "idnumber" here always means `course_modules.idnumber`,
 * the same field a teacher sets under an activity's own settings.
 *
 * Read from the course's module cache rather than queried, the way every other
 * "which activities does this course have" question in this plugin is
 * answered: it costs nothing extra once the course is already loaded, and it
 * excludes what should not be found — an activity mid-deletion, one belonging
 * to a course that no longer exists.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class forum_matcher {
    /** @var string Match against the activity's name. */
    public const FIELD_NAME = 'name';

    /** @var string Match against the activity's idnumber. */
    public const FIELD_IDNUMBER = 'idnumber';

    /**
     * The forums of one course that match a pattern.
     *
     * @param int $courseid
     * @param string $field One of the FIELD_* constants.
     * @param string $operator One of {@see pattern_matcher::operators()}.
     * @param string $pattern
     * @return \stdClass[] Each: cmid, name, idnumber, instance (the forum id),
     *         keyed by cmid.
     */
    public static function matching(int $courseid, string $field, string $operator, string $pattern): array {
        $modinfo = get_fast_modinfo($courseid);
        $matches = [];

        foreach ($modinfo->get_instances_of('forum') as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }

            $subject = $field === self::FIELD_IDNUMBER
                ? (string) $cm->idnumber
                : format_string($cm->name, true, ['context' => $cm->context]);

            if (!pattern_matcher::matches($subject, $operator, $pattern)) {
                continue;
            }

            $matches[(int) $cm->id] = (object) [
                'cmid' => (int) $cm->id,
                'name' => format_string($cm->name, true, ['context' => $cm->context]),
                'idnumber' => (string) $cm->idnumber,
                'instance' => (int) $cm->instance,
            ];
        }

        return $matches;
    }

    /**
     * The forum records behind a set of matches, in one query.
     *
     * @param \stdClass[] $matches From {@see self::matching()}.
     * @return \stdClass[] Forum records, keyed by cmid — not by forum id, so
     *         the caller can pair each record back up with its match without
     *         a second lookup.
     */
    public static function forum_records(array $matches): array {
        global $DB;

        if ($matches === []) {
            return [];
        }

        $instanceids = array_unique(array_map(static function (\stdClass $match): int {
            return $match->instance;
        }, $matches));

        [$insql, $params] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);
        $forums = $DB->get_records_select('forum', "id {$insql}", $params);
        $records = [];

        foreach ($matches as $cmid => $match) {
            if (isset($forums[$match->instance])) {
                $records[$cmid] = $forums[$match->instance];
            }
        }

        return $records;
    }
}
