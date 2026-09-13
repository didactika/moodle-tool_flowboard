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

use core_component;
use tool_flowboard\local\node\base_node;

/**
 * The kinds of node a flow can be built from.
 *
 * Found, not listed: a class under `local\node` that extends the base is a
 * node, and nothing else needs saying. The alternative — a list in a file
 * somewhere — is a second place to forget, and it is exactly what makes other
 * plugins of this kind extensible only by forking them.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class node_registry {
    /** @var array<string, string>|null Type to class, worked out once per request. */
    private static ?array $types = null;

    /**
     * Every kind of node there is.
     *
     * @return array<string, string> Node type => class name.
     */
    public static function all(): array {
        if (self::$types !== null) {
            return self::$types;
        }

        $types = [];
        $classes = core_component::get_component_classes_in_namespace('tool_flowboard', 'local\\node');

        foreach (array_keys($classes) as $class) {
            if (!is_subclass_of($class, base_node::class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            $types[$class::type()] = $class;
        }

        ksort($types);
        self::$types = $types;

        return $types;
    }

    /**
     * One kind of node, ready to run.
     *
     * @param string $type
     * @return base_node|null Null where nothing of that kind exists, which is
     *         what a flow drawn against a plugin that has since been removed
     *         looks like.
     */
    public static function get(string $type): ?base_node {
        $class = self::all()[$type] ?? null;

        return $class === null ? null : new $class();
    }

    /**
     * Whether a kind of node exists.
     *
     * @param string $type
     * @return bool
     */
    public static function exists(string $type): bool {
        return isset(self::all()[$type]);
    }

    /**
     * The kinds of node a run can start from.
     *
     * @return array<string, string> Node type => class name.
     */
    public static function triggers(): array {
        return array_filter(self::all(), static function (string $class): bool {
            return $class::is_trigger();
        });
    }

    /**
     * Everything a flow needs to be allowed to do, given how its nodes are set
     * up.
     *
     * This is what the flow's actor is granted, and nothing beyond it.
     *
     * Nodes are accepted either as {@see graph_repository::nodes()} returns
     * them (`\stdClass`, read back from the database) or as a drawing not yet
     * saved describes them (a plain array, straight from the request) — the
     * capabilities a graph would need have to be answerable before it is
     * written anywhere, which is what lets a publisher be checked against
     * them before a single row is inserted.
     *
     * @param array $nodes Each entry a \stdClass or an array.
     * @return string[] Capability names, without repeats.
     */
    public static function capabilities_for(array $nodes): array {
        $capabilities = [];

        foreach ($nodes as $node) {
            $type = is_array($node) ? ($node['type'] ?? '') : ($node->type ?? '');
            $config = is_array($node) ? ($node['config'] ?? []) : ($node->config ?? []);
            $class = self::all()[$type] ?? null;

            if ($class === null) {
                continue;
            }

            foreach ($class::required_capabilities($config) as $capability) {
                $capabilities[$capability] = true;
            }
        }

        $capabilities = array_keys($capabilities);
        sort($capabilities);

        return $capabilities;
    }

    /**
     * Forgets what it found, so that a test installing a node mid-run sees it.
     */
    public static function reset(): void {
        self::$types = null;
    }
}
