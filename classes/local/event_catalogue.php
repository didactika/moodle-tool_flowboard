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

use core_component;

/**
 * Every event this site can fire, found rather than listed.
 *
 * Moodle already knows: each component declares its events as classes under
 * its own `event` namespace, and each of them describes itself through
 * `get_static_info()` and `get_name()`. So the catalogue of things a flow can
 * react to is not something this plugin maintains — it is read off the site,
 * which means an event added by a plugin installed tomorrow can be built on
 * tomorrow, with no release here.
 *
 * What none of them declare is the contents of `other`. That is learned from
 * events as they go past; see {@see event_seen_repository}.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_catalogue {
    /**
     * Events that exist but must not be offered.
     *
     * `unknown_logged` is core's stand-in for a log entry whose class has gone
     * away. It is never fired, so a flow listening for it would wait forever.
     */
    private const NOT_OFFERED = [
        '\core\event\unknown_logged',
    ];

    /**
     * Every event on the site, by class name.
     *
     * @return array<string, array> Each entry: eventname, name, component,
     *         componentname, crud, edulevel, objecttable, since.
     */
    public static function all(): array {
        $cache = \cache::make('tool_flowboard', 'eventcatalogue');
        $cached = $cache->get('all');

        if ($cached !== false) {
            return $cached;
        }

        $catalogue = self::build();
        $cache->set('all', $catalogue);

        return $catalogue;
    }

    /**
     * One event, if the site has it.
     *
     * @param string $eventname The class, with its leading backslash.
     * @return array|null
     */
    public static function get(string $eventname): ?array {
        return self::all()[$eventname] ?? null;
    }

    /**
     * Whether an event is one a flow could be built on.
     *
     * @param string $eventname
     * @return bool
     */
    public static function exists(string $eventname): bool {
        return isset(self::all()[$eventname]);
    }

    /**
     * The components that fire events, and what they are called.
     *
     * @return array<string, string> Component => its human name, sorted by name.
     */
    public static function components(): array {
        $components = [];

        foreach (self::all() as $event) {
            $components[$event['component']] = $event['componentname'];
        }

        \core_collator::asort($components);

        return $components;
    }

    /**
     * Reads the catalogue off the site.
     *
     * Debugging is muted around this, exactly as core's own event list does
     * it: asking a deprecated event to describe itself makes it say so, and a
     * page that lists every event on the site would otherwise be a wall of
     * deprecation notices about events it is only naming.
     *
     * @return array<string, array>
     */
    private static function build(): array {
        global $CFG;

        $debug = $CFG->debug;
        $debugdisplay = $CFG->debugdisplay;
        $debugdeveloper = $CFG->debugdeveloper;
        $CFG->debug = 0;
        $CFG->debugdisplay = false;
        $CFG->debugdeveloper = false;

        $catalogue = [];

        try {
            foreach (array_keys(core_component::get_component_classes_in_namespace(null, 'event')) as $class) {
                $info = self::describe($class);

                if ($info !== null) {
                    $catalogue[$info['eventname']] = $info;
                }
            }
        } finally {
            $CFG->debug = $debug;
            $CFG->debugdisplay = $debugdisplay;
            $CFG->debugdeveloper = $debugdeveloper;
        }

        uasort($catalogue, static function (array $first, array $second): int {
            return strcmp(
                \core_text::strtolower($first['name']),
                \core_text::strtolower($second['name'])
            );
        });

        return $catalogue;
    }

    /**
     * What one class has to say about itself, or null if it is not an event
     * worth offering.
     *
     * A broken or half-installed plugin should cost this page a row, not the
     * whole page, so anything thrown while a class describes itself is taken
     * as "this one cannot be offered".
     *
     * @param string $class
     * @return array|null
     */
    private static function describe(string $class): ?array {
        $eventname = '\\' . ltrim($class, '\\');

        if (in_array($eventname, self::NOT_OFFERED, true)) {
            return null;
        }

        try {
            if (!is_a($class, \core\event\base::class, true)) {
                return null;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract()) {
                return null;
            }

            $info = $class::get_static_info();

            return [
                'eventname' => $eventname,
                'name' => $class::get_name(),
                'component' => $info['component'],
                'componentname' => self::component_name($info['component']),
                'crud' => $info['crud'],
                'edulevel' => (int) $info['edulevel'],
                'objecttable' => $info['objecttable'],
                'since' => self::since($reflection),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * What a component is called where a person reads it.
     *
     * @param string $component
     * @return string The component's own name, or its frankenstyle where it
     *         has none to give.
     */
    private static function component_name(string $component): string {
        if ($component === 'core' || strpos($component, 'core_') === 0) {
            return get_string('coresystem');
        }

        if (get_string_manager()->string_exists('pluginname', $component)) {
            return get_string('pluginname', $component);
        }

        return $component;
    }

    /**
     * Which Moodle version an event arrived in, where its docblock says.
     *
     * @param \ReflectionClass $reflection
     * @return string Empty when it does not say.
     */
    private static function since(\ReflectionClass $reflection): string {
        $docblock = $reflection->getDocComment();

        if ($docblock === false) {
            return '';
        }

        if (preg_match('/@since\s+Moodle\s+([0-9.]+)/i', $docblock, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
