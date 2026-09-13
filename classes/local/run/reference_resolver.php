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

namespace tool_flowboard\local\run;

use tool_flowboard\local\flow\flow_context;

/**
 * Lets a node's own configuration point at a field instead of only ever
 * spelling out a literal — the thing a "map from…" picker in the canvas
 * writes into a config value.
 *
 * `{{event:PATH}}` reads the triggering event itself, dotted path and all;
 * `{{context:KEY}}` reads something an earlier node worked out and wrote down
 * (its own {@see \tool_flowboard\local\node\base_node::produces()}). Neither
 * is an expression: there is no function, no operator, nothing to evaluate —
 * a token names one value and is replaced by it, which is what keeps a config
 * value auditable by reading it rather than running it. A field that names
 * neither is left exactly as it was typed: a literal, not a mistake.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reference_resolver {
    /** @var string Matches a config value that is nothing but one token. */
    private const WHOLE_PATTERN = '/^\{\{(event|context):([^{}]+)\}\}$/';

    /** @var string Matches a token anywhere inside a longer string. */
    private const PART_PATTERN = '/\{\{(event|context):([^{}]+)\}\}/';

    /**
     * Whether a config value is a reference rather than a literal — the shape
     * validation (a real event name, a valid pattern) has to skip, because a
     * reference cannot be checked until a run actually resolves it.
     *
     * @param mixed $value
     * @return bool
     */
    public static function is_reference($value): bool {
        return is_string($value) && preg_match(self::WHOLE_PATTERN, $value) === 1;
    }

    /**
     * A node's configuration, with every reference swapped for what it names.
     *
     * @param array $config As drawn: literals and references mixed freely.
     * @param flow_context $context What the run knows so far.
     * @return array The same shape, ready for the node to read.
     */
    public static function resolve(array $config, flow_context $context): array {
        $resolved = [];

        foreach ($config as $key => $value) {
            $resolved[$key] = self::resolve_value($value, $context);
        }

        return $resolved;
    }

    /**
     * One value: an array is walked recursively, a string is checked for a
     * token, anything else is a literal that stands as it is.
     *
     * @param mixed $value
     * @param flow_context $context
     * @return mixed
     */
    private static function resolve_value($value, flow_context $context) {
        if (is_array($value)) {
            return self::resolve($value, $context);
        }

        if (!is_string($value) || strpos($value, '{{') === false) {
            return $value;
        }

        if (preg_match(self::WHOLE_PATTERN, $value, $matches) === 1) {
            return self::read($matches[1], trim($matches[2]), $context);
        }

        return preg_replace_callback(self::PART_PATTERN, static function (array $matches) use ($context): string {
            $found = self::read($matches[1], trim($matches[2]), $context);

            return is_scalar($found) ? (string) $found : '';
        }, $value);
    }

    /**
     * One token's value, read from wherever it names.
     *
     * @param string $source `event` or `context`.
     * @param string $path
     * @param flow_context $context
     * @return mixed Null where the source has nothing at that path.
     */
    private static function read(string $source, string $path, flow_context $context) {
        return $source === 'event' ? $context->event_field($path) : $context->get($path);
    }
}
