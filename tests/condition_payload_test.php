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

use tool_flowboard\local\flow\flow_context;
use tool_flowboard\local\flow\graph_repository;
use tool_flowboard\local\node\condition_payload;

/**
 * A question about the event's own payload.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\node\condition_payload
 */
final class condition_payload_test extends \advanced_testcase {
    /**
     * Every comparison the node offers, matching and not.
     *
     * @dataProvider operator_provider
     * @param string $operator
     * @param mixed $value What the event carries.
     * @param mixed $expected What the flow asks for.
     * @param bool $answer Whether they should be judged to match.
     */
    public function test_operators(string $operator, $value, $expected, bool $answer): void {
        $this->resetAfterTest();

        $node = new condition_payload();
        $context = new flow_context((object) ['id' => 1], ['field' => $value]);

        $result = $node->run($context, ['field' => 'field', 'operator' => $operator, 'value' => $expected]);

        $this->assertSame($answer, $result->summary()['matched']);
        $this->assertSame(
            $answer ? graph_repository::PORT_TRUE : graph_repository::PORT_FALSE,
            $result->port()
        );
    }

    /**
     * The comparisons {@see self::test_operators()} runs through.
     *
     * @return array<string, array{0: string, 1: mixed, 2: mixed, 3: bool}>
     */
    public static function operator_provider(): array {
        return [
            'equals, matching' => ['equals', 'OPEN_STARTED', 'OPEN_STARTED', true],
            'equals, not matching' => ['equals', 'OPEN_STARTED', 'CLOSED', false],
            'equals, numeric and string agree' => ['equals', 7, '7', true],
            'notequals, matching' => ['notequals', 'OPEN_STARTED', 'CLOSED', true],
            'in, present' => ['in', 'CLOSED', 'OPEN_STARTED,CLOSED,FINALIZED', true],
            'in, absent' => ['in', 'RECOGNIZED', 'OPEN_STARTED,CLOSED,FINALIZED', false],
            'notin, absent' => ['notin', 'RECOGNIZED', 'OPEN_STARTED,CLOSED,FINALIZED', true],
            'contains, case-insensitive' => ['contains', 'Welcome Forum', 'FORUM', true],
            'contains, absent' => ['contains', 'Welcome Forum', 'chat', false],
            'matches, regex' => ['matches', 'FORO-101', '^FORO-\d+$', true],
            'matches, regex fails' => ['matches', 'FORO-abc', '^FORO-\d+$', false],
            'empty, is empty' => ['empty', '', 'unused', true],
            'empty, is not' => ['empty', 'something', 'unused', false],
            'notempty, is not' => ['notempty', 'something', 'unused', true],
        ];
    }

    /**
     * A field the event does not carry reads as empty, not as a fatal error.
     */
    public function test_a_missing_field_reads_as_empty(): void {
        $this->resetAfterTest();

        $node = new condition_payload();
        $context = new flow_context((object) ['id' => 1], ['other' => ['state' => 'OPEN_STARTED']]);

        $result = $node->run($context, ['field' => 'other.nosuchkey', 'operator' => 'empty']);

        $this->assertTrue($result->summary()['matched']);
    }

    /**
     * An operator nobody defined answers no rather than yes: a flow that
     * matches nothing is a bug somebody notices, one that matches everything
     * is not.
     */
    public function test_an_unknown_operator_answers_no(): void {
        $this->resetAfterTest();

        $node = new condition_payload();
        $context = new flow_context((object) ['id' => 1], ['field' => 'anything']);

        $result = $node->run($context, ['field' => 'field', 'operator' => 'whatever', 'value' => 'anything']);

        $this->assertFalse($result->summary()['matched']);
    }

    /**
     * A regular expression that cannot be parsed answers no rather than
     * warning on every event that reaches it.
     */
    public function test_a_broken_regex_answers_no(): void {
        $this->resetAfterTest();

        $node = new condition_payload();
        $context = new flow_context((object) ['id' => 1], ['field' => 'anything']);

        $result = $node->run($context, ['field' => 'field', 'operator' => 'matches', 'value' => '[']);

        $this->assertFalse($result->summary()['matched']);
    }

    /**
     * A complete configuration has nothing wrong with it.
     */
    public function test_a_complete_configuration_is_valid(): void {
        $errors = condition_payload::validate_config(['field' => 'other.state', 'operator' => 'equals', 'value' => 'OPEN']);

        $this->assertSame([], $errors);
    }

    /**
     * "empty" and "notempty" need no value; every other operator does.
     */
    public function test_empty_and_notempty_need_no_value(): void {
        $this->assertSame([], condition_payload::validate_config(['field' => 'x', 'operator' => 'empty']));
        $this->assertSame([], condition_payload::validate_config(['field' => 'x', 'operator' => 'notempty']));
        $this->assertArrayHasKey('value', condition_payload::validate_config(['field' => 'x', 'operator' => 'equals']));
    }

    /**
     * A missing field, an operator nobody defined, and a missing value are
     * each their own problem.
     */
    public function test_every_missing_piece_is_its_own_problem(): void {
        $errors = condition_payload::validate_config(['field' => '', 'operator' => 'whatever', 'value' => '']);

        $this->assertArrayHasKey('field', $errors);
        $this->assertArrayHasKey('operator', $errors);
        $this->assertArrayHasKey('value', $errors);
    }

    /**
     * A value mapped from an earlier field is not required to be non-empty
     * as typed — it is not empty once resolved, which is all that can be
     * checked before a run.
     */
    public function test_a_mapped_value_is_not_flagged_as_missing(): void {
        $errors = condition_payload::validate_config([
            'field' => 'x',
            'operator' => 'equals',
            'value' => '{{context:courseid}}',
        ]);

        $this->assertArrayNotHasKey('value', $errors);
    }
}
