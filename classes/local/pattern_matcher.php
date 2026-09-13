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
 * Does this piece of text match what an administrator typed?
 *
 * One place for the four ways a flow is allowed to ask that question — exact,
 * contains, starts with, or a regular expression — and for the safety a
 * regular expression needs before it is allowed to run on every matching
 * event. Every node that matches a course, an activity or a forum by name or
 * idnumber shares this rather than growing its own copy.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pattern_matcher {
    /** @var string The whole text, exactly. */
    public const OPERATOR_EXACT = 'exact';

    /** @var string The pattern appears somewhere in the text. */
    public const OPERATOR_CONTAINS = 'contains';

    /** @var string The text begins with the pattern. */
    public const OPERATOR_STARTSWITH = 'startswith';

    /** @var string The pattern is a regular expression. */
    public const OPERATOR_REGEX = 'regex';

    /** @var int The most characters a pattern may have, so a flow cannot hang the cron on a regex. */
    private const MAX_PATTERN_LENGTH = 255;

    /**
     * The ways a flow may ask this question.
     *
     * @return string[]
     */
    public static function operators(): array {
        return [
            self::OPERATOR_EXACT,
            self::OPERATOR_CONTAINS,
            self::OPERATOR_STARTSWITH,
            self::OPERATOR_REGEX,
        ];
    }

    /**
     * Whether a piece of text matches a pattern.
     *
     * @param string $subject The text to test, e.g. an activity's name.
     * @param string $operator One of {@see self::operators()}.
     * @param string $pattern What was typed.
     * @return bool
     */
    public static function matches(string $subject, string $operator, string $pattern): bool {
        if ($pattern === '') {
            return false;
        }

        switch ($operator) {
            case self::OPERATOR_EXACT:
                return \core_text::strtolower($subject) === \core_text::strtolower($pattern);

            case self::OPERATOR_CONTAINS:
                return \core_text::strpos(
                    \core_text::strtolower($subject),
                    \core_text::strtolower($pattern)
                ) !== false;

            case self::OPERATOR_STARTSWITH:
                return \core_text::substr(
                    \core_text::strtolower($subject),
                    0,
                    \core_text::strlen($pattern)
                ) === \core_text::strtolower($pattern);

            case self::OPERATOR_REGEX:
                return self::regex_matches($subject, $pattern);

            default:
                // An operator nobody defined matches nothing: a flow that
                // stops acting is a bug somebody notices, one that starts
                // matching everything is a bug nobody notices until too late.
                return false;
        }
    }

    /**
     * Whether a pattern is safe and well-formed for the operator it is paired
     * with, checked at the time somebody types it rather than at the time it
     * runs on the next matching event.
     *
     * @param string $operator One of {@see self::operators()}.
     * @param string $pattern
     * @return bool
     */
    public static function is_valid_pattern(string $operator, string $pattern): bool {
        if ($pattern === '' || \core_text::strlen($pattern) > self::MAX_PATTERN_LENGTH) {
            return false;
        }

        if ($operator === self::OPERATOR_REGEX) {
            return @preg_match(self::delimit($pattern), '') !== false;
        }

        return true;
    }

    /**
     * A regular expression, run with the guards a pattern typed into a form
     * needs before it is trusted to run on the way past every matching event.
     *
     * @param string $subject
     * @param string $pattern
     * @return bool
     */
    private static function regex_matches(string $subject, string $pattern): bool {
        if (\core_text::strlen($pattern) > self::MAX_PATTERN_LENGTH) {
            return false;
        }

        // A pattern that blows PCRE's backtracking limit answers "no match"
        // rather than taking the cron down with it; @ is deliberate here, not
        // a shortcut around checking the result.
        $result = @preg_match(self::delimit($pattern), $subject);

        return $result === 1;
    }

    /**
     * Wraps a pattern in delimiters, escaping the delimiter itself if the
     * pattern happens to contain one.
     *
     * @param string $pattern
     * @return string
     */
    private static function delimit(string $pattern): string {
        return '~' . str_replace('~', '\~', $pattern) . '~u';
    }
}
