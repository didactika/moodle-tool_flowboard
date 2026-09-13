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

/**
 * One kind of node: what it is called, what it needs to be allowed to do, and
 * what it does.
 *
 * Adding a node to Flowboard is adding a class here. Nothing registers it, no
 * list names it — the registry finds it, the editor offers it, and the flow's
 * own actor is granted whatever it declares it needs and nothing else. That
 * last part is why {@see self::required_capabilities()} is on this contract
 * rather than somewhere convenient: a node that could act without declaring
 * what it needs would quietly widen every flow that uses it.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_node {
    /**
     * What this kind of node is called, as flows refer to it.
     *
     * @return string
     */
    abstract public static function type(): string;

    /**
     * What it is called where a person reads it.
     *
     * @return string
     */
    abstract public static function name(): string;

    /**
     * Does the work, and says which way the run leaves.
     *
     * @param flow_context $context What the run knows so far; a node may add to it.
     * @param array $config How this node was set up.
     * @return node_result
     */
    abstract public function run(flow_context $context, array $config): node_result;

    /**
     * The shape of this node's own configuration, for the canvas to draw a
     * form from without knowing anything about this particular kind of node.
     *
     * Each entry: `key` (the config key), `label` (a lang string key in
     * `tool_flowboard`), `type` (`text`, `select`, `event` — a Moodle event
     * class, or `eventfield` — a dotted path into the triggering event's own
     * payload), `options` (for `select`, value => label) and `referenceable`
     * (whether this field may hold a `{{event:...}}`/`{{context:...}}` token
     * instead of a literal — see {@see \tool_flowboard\local\run\reference_resolver}).
     *
     * @return array
     */
    public static function config_schema(): array {
        return [];
    }

    /**
     * The ways out this kind of node draws on the canvas — {@see graph_repository}'s
     * own port constants. A trigger or an action has one; a question about
     * the run has two, and no edge may leave from any other.
     *
     * @return string[]
     */
    public static function ports(): array {
        return ['out'];
    }

    /**
     * The named values this node writes into {@see flow_context} for nodes
     * further down the flow to read — what a `{{context:...}}` reference on a
     * later node's config may point at.
     *
     * @return string[]
     */
    public static function produces(): array {
        return [];
    }

    /**
     * Whether this node's own configuration is one it could actually run
     * with — checked when a flow is published, not only once it runs and
     * fails. A reference (`{{event:...}}`/`{{context:...}}`) is never checked
     * beyond being present: what it resolves to is only known at run time.
     *
     * @param array $config
     * @return array<string, string> Config key => error message. Empty when
     *         there is nothing wrong with it.
     */
    public static function validate_config(array $config): array {
        return [];
    }

    /**
     * What a flow using this node has to be allowed to do.
     *
     * Declared per configuration, not per class: subscribing to a forum and
     * unsubscribing from one are not the same permission, and a node that can
     * be set up to do either should ask only for what it was set up to do.
     *
     * @param array $config
     * @return string[] Capability names.
     */
    public static function required_capabilities(array $config): array {
        return [];
    }

    /**
     * Whether this node is where a run begins.
     *
     * @return bool
     */
    public static function is_trigger(): bool {
        return false;
    }

    /**
     * Whether this node changes anything, as opposed to only deciding.
     *
     * What it is for: a flow made only of questions can be published without
     * ceremony; one that changes the site is the kind that needs simulating
     * first and confirming before it goes live.
     *
     * @return bool
     */
    public static function acts(): bool {
        return false;
    }
}
