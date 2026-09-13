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
use tool_flowboard\local\flow\graph_repository;
use tool_flowboard\local\node\node_registry;
use tool_flowboard\local\node\node_result;

/**
 * Walks a flow, one node at a time.
 *
 * The engine knows two things: how to get from a node to the next one, and how
 * to write down what happened. It knows nothing about forums, enrolments or
 * events — that is the nodes' business — and the order is the drawing's, not
 * the code's.
 *
 * Every node is written down as it runs rather than at the end, so a run that
 * dies halfway still explains what it managed to do. That is the difference
 * between a log and an answer.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class engine {
    /**
     * The most nodes one run may visit.
     *
     * A drawing can have a cycle in it — two nodes pointing at each other is
     * two clicks in an editor — and the engine cannot tell a loop that will
     * end from one that will not. So it counts, and stops. This is a ceiling
     * on an accident, not a feature: a flow that legitimately repeats will use
     * a loop node, which counts its own turns.
     */
    private const MAX_STEPS = 100;

    /** @var int How deep inside flows the current process is. */
    private static int $depth = 0;

    /**
     * How many flows deep the current process is.
     *
     * An action taken by a flow fires events like any other action, and those
     * events can set off flows. That is a feature, right up until it is a loop,
     * so what fires now knows how far down it already is.
     *
     * @return int Zero outside any flow.
     */
    public static function current_depth(): int {
        return self::$depth;
    }

    /**
     * Runs one flow against one event.
     *
     * @param \stdClass $flow
     * @param int $versionid The version to run, which is the flow's current one
     *        unless something is deliberately replaying an older run.
     * @param array $event The event's own data.
     * @param array $options depth, triggertype, parentrunid, dryrun.
     * @return int The run's id.
     */
    public static function run(\stdClass $flow, int $versionid, array $event, array $options = []): int {
        $depth = (int) ($options['depth'] ?? 0);
        $context = new flow_context($flow, $event, !empty($flow->dryrun) || !empty($options['dryrun']));

        $runid = run_repository::start($flow, $versionid, [
            'triggertype' => $options['triggertype'] ?? 'event',
            'eventname' => $event['eventname'] ?? null,
            'eventdata' => $event,
            'contextid' => $event['contextid'] ?? null,
            'depth' => $depth,
            'parentrunid' => $options['parentrunid'] ?? null,
            'dryrun' => !empty($options['dryrun']),
        ]);

        $before = self::$depth;
        self::$depth = $depth + 1;

        try {
            $status = self::walk($runid, $versionid, $context);
            run_repository::finish($runid, $status);
        } catch (\Throwable $e) {
            run_repository::finish($runid, run_repository::STATUS_FAILED, $e->getMessage());
        } finally {
            // What it was before this run, not what this run was told: one flow
            // setting off another must leave the counter where it found it.
            self::$depth = $before;

            if ($context->subject() > 0) {
                run_repository::set_subject($runid, $context->subject());
            }
        }

        return $runid;
    }

    /**
     * Follows the drawing from its starting node until there is nowhere left
     * to go.
     *
     * @param int $runid
     * @param int $versionid
     * @param flow_context $context
     * @return string How the run ended, as {@see run_repository} names it.
     */
    private static function walk(int $runid, int $versionid, flow_context $context): string {
        $nodes = graph_repository::nodes($versionid);
        $edges = graph_repository::edges($versionid);
        $current = self::entry_point($nodes);

        if ($current === null) {
            run_repository::record_node($runid, '', '', 'failed', [], 'nostartingnode');

            return run_repository::STATUS_FAILED;
        }

        $steps = 0;

        while ($current !== null) {
            if (++$steps > self::MAX_STEPS) {
                run_repository::record_node($runid, $current, '', 'stopped', ['steps' => $steps], 'toomanysteps');

                return run_repository::STATUS_FAILED;
            }

            $node = $nodes[$current];
            $result = self::run_node($runid, $node, $context);

            if ($result === null) {
                return run_repository::STATUS_FAILED;
            }

            if ($result->stops()) {
                return run_repository::STATUS_SKIPPED;
            }

            $current = self::next($edges, $current, $result->port());
        }

        return run_repository::STATUS_OK;
    }

    /**
     * Runs one node, writes down what it did, and hands back its answer.
     *
     * A node that throws is a node that failed, not a run that disappears: the
     * failure is written where somebody will look for it.
     *
     * @param int $runid
     * @param \stdClass $node
     * @param flow_context $context
     * @return node_result|null Null when the run cannot carry on.
     */
    private static function run_node(int $runid, \stdClass $node, flow_context $context): ?node_result {
        $implementation = node_registry::get($node->type);

        if ($implementation === null) {
            run_repository::record_node($runid, $node->nodekey, $node->type, 'failed', [], 'unknownnodetype');

            return null;
        }

        $started = microtime(true);

        try {
            $result = $implementation->run($context, $node->config ?? []);
        } catch (\Throwable $e) {
            run_repository::record_node(
                $runid,
                $node->nodekey,
                $node->type,
                'failed',
                [],
                $e->getMessage(),
                self::elapsed($started)
            );

            return null;
        }

        run_repository::record_node(
            $runid,
            $node->nodekey,
            $node->type,
            $result->stops() ? 'stopped' : 'ok',
            $result->summary(),
            null,
            self::elapsed($started)
        );

        return $result;
    }

    /**
     * Where the run starts: the trigger the flow was drawn around.
     *
     * @param \stdClass[] $nodes
     * @return string|null The node's key, or null where the drawing has no
     *         starting point — which the editor should never let happen, and
     *         an imported flow might.
     */
    private static function entry_point(array $nodes): ?string {
        foreach ($nodes as $key => $node) {
            $class = node_registry::all()[$node->type] ?? null;

            if ($class !== null && $class::is_trigger()) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * Where an edge leads from one node, leaving by one port.
     *
     * @param \stdClass[][] $edges Edges grouped by the node they leave, keyed by that node's key.
     * @param string $from
     * @param string $port
     * @return string|null Null where nothing is drawn from there, which is how
     *         a path ends.
     */
    private static function next(array $edges, string $from, string $port): ?string {
        foreach ($edges[$from] ?? [] as $edge) {
            if ($edge->fromport === $port) {
                return $edge->tonode;
            }
        }

        return null;
    }

    /**
     * How long a node took, in milliseconds.
     *
     * @param float $started
     * @return int
     */
    private static function elapsed(float $started): int {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
