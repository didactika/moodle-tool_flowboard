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

use tool_flowboard\local\matching\pattern_matcher;

/**
 * The four ways a flow may ask "does this text match what I typed".
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\matching\pattern_matcher
 */
final class pattern_matcher_test extends \advanced_testcase {
    /**
     * Each operator, matched and not, case-insensitively.
     */
    public function test_each_operator(): void {
        $this->assertTrue(pattern_matcher::matches('General Forum', pattern_matcher::OPERATOR_EXACT, 'general forum'));
        $this->assertFalse(pattern_matcher::matches('General Forum', pattern_matcher::OPERATOR_EXACT, 'general'));

        $this->assertTrue(pattern_matcher::matches('General Forum', pattern_matcher::OPERATOR_CONTAINS, 'FORUM'));
        $this->assertFalse(pattern_matcher::matches('General Forum', pattern_matcher::OPERATOR_CONTAINS, 'chat'));

        $this->assertTrue(pattern_matcher::matches('General Forum', pattern_matcher::OPERATOR_STARTSWITH, 'general'));
        $this->assertFalse(pattern_matcher::matches('General Forum', pattern_matcher::OPERATOR_STARTSWITH, 'forum'));

        $this->assertTrue(pattern_matcher::matches('FORO-101', pattern_matcher::OPERATOR_REGEX, '^FORO-\d+$'));
        $this->assertFalse(pattern_matcher::matches('FORO-abc', pattern_matcher::OPERATOR_REGEX, '^FORO-\d+$'));
    }

    /**
     * An empty pattern matches nothing, whatever the operator: a flow with a
     * blank pattern is a flow that has not been finished, not one that
     * matches everything.
     */
    public function test_an_empty_pattern_matches_nothing(): void {
        foreach (pattern_matcher::operators() as $operator) {
            $this->assertFalse(pattern_matcher::matches('anything', $operator, ''));
        }
    }

    /**
     * A pattern too long to be reasonable is refused before it ever runs.
     */
    public function test_a_pattern_too_long_is_invalid(): void {
        $this->assertFalse(pattern_matcher::is_valid_pattern(
            pattern_matcher::OPERATOR_EXACT,
            str_repeat('a', 256)
        ));
    }

    /**
     * A regular expression PCRE cannot parse is invalid, checked at the time
     * somebody types it.
     */
    public function test_a_broken_regex_is_invalid(): void {
        $this->assertFalse(pattern_matcher::is_valid_pattern(pattern_matcher::OPERATOR_REGEX, '['));
        $this->assertTrue(pattern_matcher::is_valid_pattern(pattern_matcher::OPERATOR_REGEX, '^FORO-\d+$'));
    }

    /**
     * A broken regular expression answers "no match" rather than raising a
     * warning that would otherwise reach a live site's error log on every
     * matching event.
     */
    public function test_a_broken_regex_does_not_match_and_does_not_warn(): void {
        $this->assertFalse(pattern_matcher::matches('anything', pattern_matcher::OPERATOR_REGEX, '['));
    }

    /**
     * A pattern containing the delimiter this class happens to use internally
     * is still a plain character to match, not a syntax error.
     */
    public function test_a_pattern_containing_the_delimiter_still_works(): void {
        $this->assertTrue(pattern_matcher::matches('a~b', pattern_matcher::OPERATOR_REGEX, 'a~b'));
    }

    /**
     * An operator nobody defined matches nothing.
     */
    public function test_an_unknown_operator_matches_nothing(): void {
        $this->assertFalse(pattern_matcher::matches('anything', 'whatever', 'anything'));
    }
}
