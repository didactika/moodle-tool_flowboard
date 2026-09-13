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

use tool_flowboard\local\flow\flow_context;
use tool_flowboard\local\matching\pattern_matcher;
use tool_flowboard\local\run\reference_resolver;

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
     * A question has two ways out: the answer was yes, or it was no.
     *
     * @return string[]
     */
    public static function ports(): array {
        return ['true', 'false'];
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
     * A field from the triggering event, an operator, and what to compare it
     * against — which may itself be a literal or a mapped field.
     *
     * @return array
     */
    public static function config_schema(): array {
        $operators = [];

        foreach (self::operators() as $operator) {
            $operators[$operator] = 'flow:operator_' . $operator;
        }

        return [
            ['key' => 'field', 'label' => 'flow:conditionfield', 'type' => 'eventfield', 'required' => true],
            [
                'key' => 'operator',
                'label' => 'flow:conditionoperator',
                'type' => 'select',
                'options' => $operators,
                'required' => true,
            ],
            [
                'key' => 'value',
                'label' => 'flow:conditionvalue',
                'type' => 'text',
                'referenceable' => true,
                'required' => true,
                'requiredunless' => ['operator' => ['empty', 'notempty']],
            ],
        ];
    }

    /**
     * A field to read, a comparison this node actually knows, and — unless
     * the comparison needs none — something to compare it against.
     *
     * @param array $config
     * @return array<string, string>
     */
    public static function validate_config(array $config): array {
        $errors = [];
        $field = trim((string) ($config['field'] ?? ''));
        $operator = (string) ($config['operator'] ?? '');
        $value = $config['value'] ?? '';

        if ($field === '') {
            $errors['field'] = get_string('flow:error_required', 'tool_flowboard');
        }

        if (!in_array($operator, self::operators(), true)) {
            $errors['operator'] = get_string('flow:error_required', 'tool_flowboard');
        }

        $needsvalue = !in_array($operator, ['empty', 'notempty'], true);
        $valuegiven = trim((string) $value) !== '';

        if ($needsvalue && !$valuegiven && !reference_resolver::is_reference($value)) {
            $errors['value'] = get_string('flow:error_valuerequired', 'tool_flowboard');
        }

        return $errors;
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
     * `contains` and `matches` are handed to {@see pattern_matcher} rather than
     * compared here: it is the one place a regular expression's safety is
     * looked after, and every node that matches text against a pattern shares
     * it instead of growing its own copy.
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
                return pattern_matcher::matches(
                    self::as_text($actual),
                    pattern_matcher::OPERATOR_CONTAINS,
                    self::as_text($expected)
                );

            case 'matches':
                return pattern_matcher::matches(
                    self::as_text($actual),
                    pattern_matcher::OPERATOR_REGEX,
                    self::as_text($expected)
                );

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
