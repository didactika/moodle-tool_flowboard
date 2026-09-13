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

/**
 * The history: what ran, over whom, and what each node did.
 *
 * This is the part of the plugin that answers the question somebody will
 * actually ask — "why is this student subscribed to this forum?" — so a run is
 * written as it happens rather than summarised at the end. A run that dies
 * halfway leaves its own evidence behind.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class run_repository {
    /** @var string Still going. */
    public const STATUS_RUNNING = 'running';

    /** @var string Finished, and did what it set out to do. */
    public const STATUS_OK = 'ok';

    /** @var string Stopped on an error. */
    public const STATUS_FAILED = 'failed';

    /** @var string Nothing to do: a condition said no. */
    public const STATUS_SKIPPED = 'skipped';

    /** @var string Sleeping until something wakes it. */
    public const STATUS_WAITING = 'waiting';

    /** @var string The table this repository owns. */
    private const TABLE = 'tool_flowboard_run';

    /** @var string Its detail rows. */
    private const NODE_TABLE = 'tool_flowboard_run_node';

    /**
     * Opens a run.
     *
     * @param \stdClass $flow The flow being run.
     * @param int $versionid The version being run, which may not be the flow's current one.
     * @param array $context What set it off: eventname, eventdata, contextid, subjectid, depth, parentrunid, triggertype.
     * @return int The run's id.
     */
    public static function start(\stdClass $flow, int $versionid, array $context = []): int {
        global $DB;

        return (int) $DB->insert_record(self::TABLE, (object) [
            'flowid' => (int) $flow->id,
            'versionid' => $versionid,
            'triggertype' => $context['triggertype'] ?? 'event',
            'eventname' => $context['eventname'] ?? null,
            'eventdata' => isset($context['eventdata']) ? json_encode($context['eventdata']) : null,
            'status' => self::STATUS_RUNNING,
            'dryrun' => !empty($flow->dryrun) || !empty($context['dryrun']) ? 1 : 0,
            'contextid' => $context['contextid'] ?? null,
            'subjectid' => $context['subjectid'] ?? null,
            'depth' => (int) ($context['depth'] ?? 0),
            'parentrunid' => $context['parentrunid'] ?? null,
            'error' => null,
            'timestarted' => time(),
            'timefinished' => null,
        ]);
    }

    /**
     * Records what one node did.
     *
     * @param int $runid
     * @param string $nodekey
     * @param string $nodetype
     * @param string $status ok, failed, skipped or stopped.
     * @param array $summary What the node was given and what it answered.
     * @param string|null $error
     * @param int $durationms
     */
    public static function record_node(
        int $runid,
        string $nodekey,
        string $nodetype,
        string $status,
        array $summary = [],
        ?string $error = null,
        int $durationms = 0
    ): void {
        global $DB;

        $sortorder = (int) $DB->count_records(self::NODE_TABLE, ['runid' => $runid]);

        $DB->insert_record(self::NODE_TABLE, (object) [
            'runid' => $runid,
            'nodekey' => $nodekey,
            'nodetype' => $nodetype,
            'status' => $status,
            'summary' => $summary === [] ? null : json_encode($summary),
            'error' => $error,
            'durationms' => $durationms,
            'sortorder' => $sortorder,
        ]);
    }

    /**
     * Closes a run.
     *
     * @param int $runid
     * @param string $status One of the STATUS_* constants.
     * @param string|null $error Why, when it failed.
     */
    public static function finish(int $runid, string $status, ?string $error = null): void {
        global $DB;

        $DB->update_record(self::TABLE, (object) [
            'id' => $runid,
            'status' => $status,
            'error' => $error,
            'timefinished' => time(),
        ]);
    }

    /**
     * Notes who the run turned out to be about, once the flow has worked it
     * out — which is usually after the run has already been opened.
     *
     * @param int $runid
     * @param int $subjectid
     */
    public static function set_subject(int $runid, int $subjectid): void {
        global $DB;

        $DB->set_field(self::TABLE, 'subjectid', $subjectid, ['id' => $runid]);
    }

    /**
     * One run.
     *
     * @param int $runid
     * @return \stdClass|null
     */
    public static function get(int $runid): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $runid]) ?: null;
    }

    /**
     * What each node of a run did, in the order it happened.
     *
     * @param int $runid
     * @return \stdClass[]
     */
    public static function nodes(int $runid): array {
        global $DB;

        return $DB->get_records(self::NODE_TABLE, ['runid' => $runid], 'sortorder ASC');
    }

    /**
     * The most recent runs of a flow, or of every flow.
     *
     * @param int $flowid Zero for all of them.
     * @param int $limit
     * @return \stdClass[]
     */
    public static function recent(int $flowid = 0, int $limit = 50): array {
        global $DB;

        $conditions = $flowid > 0 ? ['flowid' => $flowid] : [];

        return $DB->get_records(self::TABLE, $conditions, 'timestarted DESC, id DESC', '*', 0, $limit);
    }

    /**
     * Forgets runs older than the site is willing to keep.
     *
     * The history carries personal data — which person a flow acted on — so it
     * is not kept forever by default. Zero days means the site has decided to
     * keep it anyway, and nothing is deleted.
     *
     * @param int $days Days to keep; zero keeps everything.
     * @return int How many runs were forgotten.
     */
    public static function purge_older_than(int $days): int {
        global $DB;

        if ($days <= 0) {
            return 0;
        }

        $cutoff = time() - ($days * DAYSECS);
        $runids = $DB->get_fieldset_select(self::TABLE, 'id', 'timestarted < :cutoff', ['cutoff' => $cutoff]);

        if ($runids === []) {
            return 0;
        }

        foreach (array_chunk($runids, 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $DB->delete_records_select(self::NODE_TABLE, "runid {$insql}", $params);
            $DB->delete_records_select(self::TABLE, "id {$insql}", $params);
        }

        return count($runids);
    }
}
