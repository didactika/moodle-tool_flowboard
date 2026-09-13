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

namespace tool_flowboard\local\node;

use tool_flowboard\local\flow_context;

/**
 * A question about the event itself.
 *
 * The comparisons are a closed list on purpose. A rule engine that lets an
 * administrator type an expression and evaluates it is a back door with a form
 * around it, so nothing here is compiled, evaluated or executed: a field is
 * read, an operator is looked up, and two values are compared.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition_payload extends base_node {
    /** @var string How flows refer to this node. */
    public const TYPE = 'condition_payload';

    /** @var int The most characters a pattern may have, so a flow cannot hang the cron on a regex. */
    private const MAX_PATTERN_LENGTH = 255;

    /**
     * What this kind of node is called, as flows refer to it.
     *
     * @return string
     */
    public static function type(): string {
        return self::TYPE;
    }

    /**
     * What it is called where a person reads it.
     *
     * @return string
     */
    public static function name(): string {
        return get_string('node:condition_payload', 'tool_flowboard');
    }

    /**
     * The comparisons a flow may ask for.
     *
     * @return string[]
     */
    public static function operators(): array {
        return ['equals', 'notequals', 'in', 'notin', 'contains', 'matches', 'empty', 'notempty'];
    }

    /**
     * Reads the field, compares it, and leaves by the matching way out.
     *
     * @param flow_context $context
     * @param array $config
     * @return node_result
     */
    public function run(flow_context $context, array $config): node_result {
        $field = (string) ($config['field'] ?? '');
        $operator = (string) ($config['operator'] ?? 'equals');
        $expected = $config['value'] ?? '';
        $actual = $context->event_field($field);

        $answer = self::compare($operator, $actual, $expected);

        return node_result::answered($answer, [
            'field' => $field,
            'operator' => $operator,
            'expected' => is_scalar($expected) ? $expected : '',
            'matched' => $answer,
        ]);
    }

    /**
     * One comparison.
     *
     * @param string $operator One of {@see self::operators()}.
     * @param mixed $actual What the event carried.
     * @param mixed $expected What the flow asked for.
     * @return bool
     */
    private static function compare(string $operator, $actual, $expected): bool {
        switch ($operator) {
            case 'equals':
                return self::as_text($actual) === self::as_text($expected);

            case 'notequals':
                return self::as_text($actual) !== self::as_text($expected);

            case 'in':
                return in_array(self::as_text($actual), self::as_list($expected), true);

            case 'notin':
                return !in_array(self::as_text($actual), self::as_list($expected), true);

            case 'contains':
                return self::as_text($expected) !== ''
                    && strpos(self::as_text($actual), self::as_text($expected)) !== false;

            case 'matches':
                return self::matches(self::as_text($actual), self::as_text($expected));

            case 'empty':
                return self::as_text($actual) === '';

            case 'notempty':
                return self::as_text($actual) !== '';

            default:
                // An operator nobody defined answers no rather than yes: a
                // flow that stops doing anything is a bug somebody notices,
                // and one that starts acting on everything is a bug nobody
                // notices until it is too late.
                return false;
        }
    }

    /**
     * A regular expression, run with the guards an administrator's pattern
     * needs.
     *
     * A pattern is a small program written in a text box, and this one runs on
     * the way past every matching event. So it is capped in length, delimited
     * here rather than by whoever typed it, and checked afterwards: a pattern
     * that blew the backtracking limit answers no and says nothing matched,
     * instead of taking the cron down with it.
     *
     * @param string $subject
     * @param string $pattern
     * @return bool
     */
    private static function matches(string $subject, string $pattern): bool {
        if ($pattern === '' || \core_text::strlen($pattern) > self::MAX_PATTERN_LENGTH) {
            return false;
        }

        $result = @preg_match('~' . str_replace('~', '\~', $pattern) . '~u', $subject);

        return $result === 1;
    }

    /**
     * Whether a pattern is one PCRE will accept, asked at the time somebody
     * types it rather than at the time it runs.
     *
     * @param string $pattern
     * @return bool
     */
    public static function is_valid_pattern(string $pattern): bool {
        if ($pattern === '' || \core_text::strlen($pattern) > self::MAX_PATTERN_LENGTH) {
            return false;
        }

        return @preg_match('~' . str_replace('~', '\~', $pattern) . '~u', '') !== false;
    }

    /**
     * A value as text, so that 7 and "7" are the same answer.
     *
     * @param mixed $value
     * @return string
     */
    private static function as_text($value): string {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null || is_array($value) || is_object($value)) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * A comma-separated list, as a list.
     *
     * @param mixed $value
     * @return string[]
     */
    private static function as_list($value): array {
        if (is_array($value)) {
            return array_map([self::class, 'as_text'], $value);
        }

        $items = array_map('trim', explode(',', self::as_text($value)));

        return array_values(array_filter($items, static function (string $item): bool {
            return $item !== '';
        }));
    }
}
