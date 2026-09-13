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

        $transaction = $DB->start_delegated_transaction();

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

        $transaction->allow_commit();

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
     * @return array{nodes: array, edges: array} Empty arrays where the version is unknown.
     */
    public static function graph(int $versionid): array {
        $version = self::version($versionid);

        if ($version === null) {
            return ['nodes' => [], 'edges' => []];
        }

        $graph = json_decode($version->graph, true);

        return [
            'nodes' => $graph['nodes'] ?? [],
            'edges' => $graph['edges'] ?? [],
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
     * Refuses a drawing that is not one.
     *
     * Structure only: that the nodes are nodes, that their keys are unique,
     * and that no edge leads somewhere that does not exist. Whether the
     * drawing *means* anything — that it starts at a trigger, that every path
     * ends — is a question about the nodes themselves, and the engine asks it
     * where it knows them.
     *
     * @param array $graph
     */
    private static function validate(array $graph): void {
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
    }
}
