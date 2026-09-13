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

namespace tool_flowboard\local\flow;

use tool_flowboard\local\actor\actor_provisioner;
use tool_flowboard\local\node\node_registry;

/**
 * What a flow is drawn as: nodes, and the edges between them.
 *
 * A published version is immutable. Editing a flow writes a new one, and the
 * flow points at the latest; a run records which version it used. That is what
 * lets somebody answer "why did this happen in March" with the drawing from
 * March instead of the drawing from today.
 *
 * The graph is stored twice on purpose: once whole, as the JSON the editor
 * saved (positions and all), and once exploded into node and edge rows. The
 * JSON is the truth; the rows exist so the database can be asked questions the
 * JSON cannot answer cheaply — which flows use this kind of node, which ones
 * listen to this event.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class graph_repository {
    /** @var string A node's only way out, where it has just the one. */
    public const PORT_OUT = 'out';

    /** @var string The way out of a condition that was met. */
    public const PORT_TRUE = 'true';

    /** @var string The way out of a condition that was not. */
    public const PORT_FALSE = 'false';

    /** @var string The way out taken when a node fails. */
    public const PORT_ERROR = 'error';

    /**
     * Stores a drawing as a new version of a flow, and points the flow at it.
     *
     * @param int $flowid
     * @param array $graph Nodes and edges; see {@see self::validate()} for the shape.
     * @param string $note What changed, for the version history.
     * @return int The new version's id.
     */
    public static function publish(int $flowid, array $graph, string $note = ''): int {
        global $DB, $USER;

        self::validate($graph);

        // Checked before a single row is written, not inside the transaction
        // below: a delegated transaction only rolls back automatically once an
        // exception escapes the entire request uncaught, and refusing this is
        // exactly the kind of thing calling code is meant to catch and turn
        // into a friendly message instead of letting it do that. Checking
        // first means there is nothing to undo either way.
        actor_provisioner::require_publisher_can_grant($graph['nodes']);

        $transaction = $DB->start_delegated_transaction();

        try {
            $versionnumber = (int) $DB->get_field_sql(
                'SELECT COALESCE(MAX(versionnumber), 0) + 1 FROM {tool_flowboard_version} WHERE flowid = :flowid',
                ['flowid' => $flowid]
            );

            $versionid = $DB->insert_record('tool_flowboard_version', (object) [
                'flowid' => $flowid,
                'versionnumber' => $versionnumber,
                'graph' => json_encode($graph),
                'note' => $note === '' ? null : $note,
                'timecreated' => time(),
                'usermodified' => (int) $USER->id,
            ]);

            $sortorder = 0;

            foreach ($graph['nodes'] as $node) {
                $DB->insert_record('tool_flowboard_node', (object) [
                    'flowid' => $flowid,
                    'versionid' => $versionid,
                    'nodekey' => $node['key'],
                    'type' => $node['type'],
                    'config' => json_encode($node['config'] ?? []),
                    'sortorder' => $sortorder++,
                ]);
            }

            foreach ($graph['edges'] ?? [] as $edge) {
                $DB->insert_record('tool_flowboard_edge', (object) [
                    'flowid' => $flowid,
                    'versionid' => $versionid,
                    'fromnode' => $edge['from'],
                    'fromport' => $edge['port'] ?? self::PORT_OUT,
                    'tonode' => $edge['to'],
                ]);
            }

            flow_repository::set_current_version($flowid, $versionid);

            // Reconciled inside the same transaction as the version it is
            // derived from: either the new drawing and the actor it needs both
            // take effect, or - if the publisher does not hold a capability
            // the new nodes would need - neither does. A capability check that
            // could pass while the graph it was checked against never actually
            // became current would be checking the wrong thing.
            actor_provisioner::ensure_for_version($flowid, $versionid);
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        $transaction->allow_commit();

        // Publishing is what a draft was for; there is nothing left to keep
        // once the drawing it held is now the published version.
        flow_repository::discard_draft($flowid);

        return $versionid;
    }

    /**
     * One version's row.
     *
     * @param int $versionid
     * @return \stdClass|null
     */
    public static function version(int $versionid): ?\stdClass {
        global $DB;

        return $DB->get_record('tool_flowboard_version', ['id' => $versionid]) ?: null;
    }

    /**
     * Every version of a flow, newest first.
     *
     * @param int $flowid
     * @return \stdClass[] Keyed by id.
     */
    public static function versions(int $flowid): array {
        global $DB;

        return $DB->get_records('tool_flowboard_version', ['flowid' => $flowid], 'versionnumber DESC');
    }

    /**
     * The drawing of one version, as it was saved.
     *
     * @param int $versionid
     * @return array{nodes: array, edges: array, comments: array} Empty arrays where the version is unknown.
     */
    public static function graph(int $versionid): array {
        $version = self::version($versionid);

        if ($version === null) {
            return ['nodes' => [], 'edges' => [], 'comments' => []];
        }

        $graph = json_decode($version->graph, true);

        return [
            'nodes' => $graph['nodes'] ?? [],
            'edges' => $graph['edges'] ?? [],
            'comments' => $graph['comments'] ?? [],
        ];
    }

    /**
     * The nodes of a version as rows, keyed by their own key.
     *
     * @param int $versionid
     * @return \stdClass[]
     */
    public static function nodes(int $versionid): array {
        global $DB;

        $nodes = [];

        foreach ($DB->get_records('tool_flowboard_node', ['versionid' => $versionid], 'sortorder ASC') as $node) {
            $node->config = json_decode($node->config ?? '', true) ?: [];
            $nodes[$node->nodekey] = $node;
        }

        return $nodes;
    }

    /**
     * Where each node leads, grouped by the node it leaves from.
     *
     * @param int $versionid
     * @return array<string, \stdClass[]> Edges by the key of the node they leave.
     */
    public static function edges(int $versionid): array {
        global $DB;

        $edges = [];

        foreach ($DB->get_records('tool_flowboard_edge', ['versionid' => $versionid], 'id ASC') as $edge) {
            $edges[$edge->fromnode][] = $edge;
        }

        return $edges;
    }

    /**
     * Refuses a drawing that is not one, or that could not actually run.
     *
     * The shape is checked first — nodes are nodes, keys are unique, no edge
     * leads nowhere — and any problem there stops everything else, because
     * nothing past it could be checked meaningfully anyway. What is checked
     * after is whether the drawing means something: exactly one trigger,
     * nothing feeding into it, every edge leaving by a port its own node
     * actually has, and every node's own configuration being one it could run
     * with. Every one of those is collected and reported together, rather
     * than one at a time — a flow with three mistakes should be told about
     * three mistakes, not sent back to be told about the fourth.
     *
     * @param array $graph
     */
    public static function validate(array $graph): void {
        if (empty($graph['nodes']) || !is_array($graph['nodes'])) {
            throw new \moodle_exception('error:graphempty', 'tool_flowboard');
        }

        $keys = [];

        foreach ($graph['nodes'] as $node) {
            if (!is_array($node) || !isset($node['key'], $node['type'])) {
                throw new \moodle_exception('error:graphnode', 'tool_flowboard');
            }

            if (isset($keys[$node['key']])) {
                throw new \moodle_exception('error:graphduplicatekey', 'tool_flowboard', '', $node['key']);
            }

            $keys[$node['key']] = true;
        }

        foreach ($graph['edges'] ?? [] as $edge) {
            if (!is_array($edge) || !isset($edge['from'], $edge['to'])) {
                throw new \moodle_exception('error:graphedge', 'tool_flowboard');
            }

            foreach ([$edge['from'], $edge['to']] as $end) {
                if (!isset($keys[$end])) {
                    throw new \moodle_exception('error:graphedgeunknown', 'tool_flowboard', '', $end);
                }
            }
        }

        $problems = array_merge(
            self::node_problems($graph['nodes']),
            self::shape_problems($graph['nodes'], $graph['edges'] ?? [])
        );

        if ($problems !== []) {
            throw new \moodle_exception('error:graphinvalid', 'tool_flowboard', '', implode("\n", $problems));
        }
    }

    /**
     * Every node's own type and configuration, checked against what that
     * kind of node actually declares it needs.
     *
     * @param array $nodes
     * @return string[] One sentence per problem found.
     */
    private static function node_problems(array $nodes): array {
        $problems = [];

        foreach ($nodes as $node) {
            $class = node_registry::all()[$node['type']] ?? null;

            if ($class === null) {
                $problems[] = get_string('error:graphunknowntype', 'tool_flowboard', (object) [
                    'node' => $node['key'],
                    'type' => $node['type'],
                ]);

                continue;
            }

            foreach ($class::validate_config($node['config'] ?? []) as $error) {
                $problems[] = get_string('error:graphnodeconfig', 'tool_flowboard', (object) [
                    'node' => $node['key'],
                    'error' => $error,
                ]);
            }
        }

        return $problems;
    }

    /**
     * Whether the drawing has exactly the one trigger every run needs to
     * start from, nothing feeding into it, and every edge leaving by a port
     * its own node actually draws.
     *
     * @param array $nodes
     * @param array $edges
     * @return string[] One sentence per problem found.
     */
    private static function shape_problems(array $nodes, array $edges): array {
        $problems = [];
        $triggers = [];
        $ports = [];

        foreach ($nodes as $node) {
            $class = node_registry::all()[$node['type']] ?? null;

            if ($class === null) {
                continue;
            }

            if ($class::is_trigger()) {
                $triggers[] = $node['key'];
            }

            $ports[$node['key']] = $class::ports();
        }

        if (count($triggers) === 0) {
            $problems[] = get_string('error:graphnotrigger', 'tool_flowboard');
        } else if (count($triggers) > 1) {
            $problems[] = get_string('error:graphmultipletriggers', 'tool_flowboard', implode(', ', $triggers));
        }

        foreach ($edges as $edge) {
            if (in_array($edge['to'], $triggers, true)) {
                $problems[] = get_string('error:graphedgeintotrigger', 'tool_flowboard', $edge['to']);
            }

            $fromports = $ports[$edge['from']] ?? [];

            if ($fromports !== [] && !in_array($edge['port'] ?? self::PORT_OUT, $fromports, true)) {
                $problems[] = get_string('error:graphunknownport', 'tool_flowboard', (object) [
                    'node' => $edge['from'],
                    'port' => $edge['port'] ?? self::PORT_OUT,
                ]);
            }
        }

        return $problems;
    }
}
