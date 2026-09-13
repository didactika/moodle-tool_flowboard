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

namespace tool_flowboard\local\actor;

use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\flow\graph_repository;
use tool_flowboard\local\node\node_registry;

/**
 * Gives a flow its own user and role, holding exactly what its nodes need.
 *
 * A motor de automatización that writes "as admin" is an administrator account
 * with a graphical front end: any flow could do anything, and the log would
 * say it was a person who was asleep. So every flow acts as itself. Its role
 * is defined once, from the nodes it is actually built from — a flow that
 * only subscribes to forums holds `mod/forum:managesubscriptions` and nothing
 * else — and it is recomputed every time the flow's drawing changes, so
 * removing a node that needed a capability takes that capability away too.
 *
 * Nobody can hand the actor a capability they do not hold themselves: before
 * granting anything, the person publishing is checked against the very
 * capabilities about to be granted. A flow cannot reach further than its own
 * author, which is the whole point of D16.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class actor_provisioner {
    /**
     * Makes sure a flow's actor holds exactly the capabilities its published
     * nodes need — creating the actor on the first call, adjusting its role
     * on every one after.
     *
     * Refuses before changing anything if the person calling this does not
     * hold every one of those capabilities themselves, at system context —
     * the same context the actor's role is assigned at.
     *
     * @param int $flowid
     * @param int $versionid The version whose nodes decide what is needed.
     * @return \stdClass The actor record.
     */
    public static function ensure_for_version(int $flowid, int $versionid): \stdClass {
        $flow = flow_repository::get($flowid);

        if ($flow === null) {
            throw new \coding_exception('Cannot provision an actor for a flow that does not exist.');
        }

        $nodes = graph_repository::nodes($versionid);
        $capabilities = node_registry::capabilities_for($nodes);

        self::require_publisher_holds($capabilities);

        $existing = actor_repository::for_flow($flowid);

        if ($existing === null) {
            return self::create($flow, $capabilities);
        }

        self::sync_capabilities($existing, $capabilities);

        return actor_repository::upsert(
            $flowid,
            (int) $existing->userid,
            (int) $existing->roleid,
            $capabilities,
            self::context_ids($existing)
        );
    }

    /**
     * What would change if a flow's actor were reconciled with a given set of
     * capabilities, without changing anything.
     *
     * This is the diff a publish screen shows before it lets the change
     * through: "this also needs enrol/manual:unenrol, confirm?".
     *
     * @param int $flowid
     * @param string[] $capabilities What the flow's nodes would need.
     * @return array{added: string[], removed: string[]}
     */
    public static function capability_diff(int $flowid, array $capabilities): array {
        $existing = actor_repository::for_flow($flowid);
        $current = $existing === null ? [] : actor_repository::capabilities($existing);

        return [
            'added' => array_values(array_diff($capabilities, $current)),
            'removed' => array_values(array_diff($current, $capabilities)),
        ];
    }

    /**
     * Stands a flow's actor down: the role is unassigned, so `has_capability`
     * fails immediately even if a task is already mid-flight, and the account
     * is suspended so it stops appearing as one anybody could still act as.
     *
     * @param int $flowid
     */
    public static function suspend(int $flowid): void {
        $actor = actor_repository::for_flow($flowid);

        if ($actor === null) {
            return;
        }

        foreach (self::context_ids($actor) as $contextid) {
            role_unassign((int) $actor->roleid, (int) $actor->userid, $contextid, 'tool_flowboard');
        }

        self::suspend_user((int) $actor->userid, true);
        actor_repository::set_status($flowid, actor_repository::STATUS_SUSPENDED);
    }

    /**
     * Brings a suspended actor back: re-assigns its role where it was granted
     * and lifts the account suspension.
     *
     * A flow with no actor yet (never published) has nothing to resume —
     * {@see self::ensure_for_version()} is what creates one.
     *
     * @param int $flowid
     */
    public static function resume(int $flowid): void {
        $actor = actor_repository::for_flow($flowid);

        if ($actor === null) {
            return;
        }

        foreach (self::context_ids($actor) as $contextid) {
            role_assign((int) $actor->roleid, (int) $actor->userid, $contextid, 'tool_flowboard');
        }

        self::suspend_user((int) $actor->userid, false);
        actor_repository::set_status($flowid, actor_repository::STATUS_ACTIVE);
    }

    /**
     * Removes a flow's actor entirely: the role (which unassigns it from
     * everyone as part of being deleted) and the account itself.
     *
     * Called when the flow it belongs to is deleted. `{@see actor_repository}`
     * dropping its own row is not enough on its own — that would leave a real
     * Moodle user and a real role, with real capabilities, existing forever
     * with nothing pointing back at why.
     *
     * @param int $flowid
     */
    public static function deprovision(int $flowid): void {
        global $CFG, $DB;

        $actor = actor_repository::for_flow($flowid);

        if ($actor === null) {
            return;
        }

        require_once($CFG->libdir . '/accesslib.php');

        delete_role((int) $actor->roleid);

        $user = $DB->get_record('user', ['id' => $actor->userid]);

        if ($user !== false) {
            delete_user($user);
        }
    }

    /**
     * Builds the user and the role from nothing, for a flow's first publish.
     *
     * @param \stdClass $flow
     * @param string[] $capabilities
     * @return \stdClass The stored actor.
     */
    private static function create(\stdClass $flow, array $capabilities): \stdClass {
        global $CFG;

        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->libdir . '/accesslib.php');

        $slug = self::slug($flow->idnumber);
        $systemcontext = \context_system::instance();

        $user = new \stdClass();
        $user->username = self::unique_username('flow.' . $slug);
        $user->firstname = get_string('actor:firstname', 'tool_flowboard');
        $user->lastname = $flow->name;
        $user->email = $user->username . '@' . self::devnull_domain();
        $user->auth = 'nologin';
        $user->confirmed = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->timecreated = time();
        $user->timemodified = time();
        $userid = (int) user_create_user($user, false, true);

        $roleshortname = self::unique_shortname('flow_' . $slug);
        $roleid = create_role(
            get_string('actor:rolename', 'tool_flowboard', $flow->name),
            $roleshortname,
            get_string('actor:roledescription', 'tool_flowboard', $flow->name)
        );
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);

        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $systemcontext->id, true);
        }

        role_assign($roleid, $userid, $systemcontext->id, 'tool_flowboard');

        return actor_repository::upsert($flow->id, $userid, $roleid, $capabilities, [(int) $systemcontext->id]);
    }

    /**
     * Grants what an existing actor's role is missing and takes away what it
     * no longer needs.
     *
     * @param \stdClass $actor
     * @param string[] $capabilities What the role should hold from now on.
     */
    private static function sync_capabilities(\stdClass $actor, array $capabilities): void {
        $current = actor_repository::capabilities($actor);
        $added = array_diff($capabilities, $current);
        $removed = array_diff($current, $capabilities);
        $systemcontext = \context_system::instance();

        foreach ($added as $capability) {
            assign_capability($capability, CAP_ALLOW, (int) $actor->roleid, $systemcontext->id, true);
        }

        foreach ($removed as $capability) {
            unassign_capability($capability, (int) $actor->roleid, $systemcontext->id);
        }
    }

    /**
     * Refuses if whoever is publishing lacks any capability a drawing's nodes
     * would need — D16: a flow cannot reach further than its own author.
     *
     * Meant to be called on the raw, not-yet-saved node list before a single
     * row is written: checking it only once the write has already started
     * would mean either rolling that write back (delegated transactions
     * nested inside one already open — which is exactly what a PHPUnit test
     * does — do not roll back until the outermost one is disposed, so nothing
     * could even confirm this had worked) or, in real use, leaving a
     * transaction open for whatever calling code catches the refusal to
     * display it. Refusing before anything opens avoids both.
     *
     * @param array $nodes A drawing's nodes, each a \stdClass or an array —
     *        either shape {@see node_registry::capabilities_for()} accepts.
     */
    public static function require_publisher_can_grant(array $nodes): void {
        self::require_publisher_holds(node_registry::capabilities_for($nodes));
    }

    /**
     * The actual check: does the current user hold every one of these
     * capabilities at system context, where the actor's role lives?
     *
     * @param string[] $capabilities
     */
    private static function require_publisher_holds(array $capabilities): void {
        $systemcontext = \context_system::instance();
        $missing = [];

        foreach ($capabilities as $capability) {
            if (!has_capability($capability, $systemcontext)) {
                $missing[] = $capability;
            }
        }

        if ($missing !== []) {
            throw new \moodle_exception(
                'error:missingcapability',
                'tool_flowboard',
                '',
                implode(', ', $missing)
            );
        }
    }

    /**
     * Suspends or unsuspends the actor's own account.
     *
     * @param int $userid
     * @param bool $suspended
     */
    private static function suspend_user(int $userid, bool $suspended): void {
        global $DB;

        $DB->set_field('user', 'suspended', $suspended ? 1 : 0, ['id' => $userid]);
        \core\session\manager::destroy_user_sessions($userid);
    }

    /**
     * Where an actor's role is assigned, so it can be unassigned and
     * reassigned symmetrically.
     *
     * @param \stdClass $actor
     * @return int[]
     */
    private static function context_ids(\stdClass $actor): array {
        $stored = json_decode($actor->contextids ?? '', true);

        return is_array($stored) && $stored !== [] ? array_map('intval', $stored) : [(int) \context_system::instance()->id];
    }

    /**
     * A flow's idnumber, turned into something safe to build a username and a
     * role shortname out of.
     *
     * @param string $idnumber
     * @return string
     */
    private static function slug(string $idnumber): string {
        $slug = clean_param($idnumber, PARAM_USERNAME);

        return $slug !== '' ? $slug : 'flow';
    }

    /**
     * A username nobody has yet, trying the plain one first.
     *
     * @param string $base
     * @return string
     */
    private static function unique_username(string $base): string {
        return self::unique('user', 'username', clean_param($base, PARAM_USERNAME));
    }

    /**
     * A role shortname nobody has yet.
     *
     * @param string $base
     * @return string
     */
    private static function unique_shortname(string $base): string {
        return self::unique('role', 'shortname', $base);
    }

    /**
     * The first "$base", "$base-2", "$base-3", ... that is not already taken
     * in a table's column.
     *
     * @param string $table
     * @param string $column
     * @param string $base
     * @return string
     */
    private static function unique(string $table, string $column, string $base): string {
        global $DB;

        $candidate = $base;
        $suffix = 2;

        while ($DB->record_exists($table, [$column => $candidate])) {
            $candidate = $base . '-' . $suffix++;
        }

        return $candidate;
    }

    /**
     * A domain that never receives mail, for accounts that never log in and
     * never need to. Derived from the site's own address, the same way
     * `local_servicemanager` derives one for its service accounts.
     *
     * @return string
     */
    private static function devnull_domain(): string {
        global $CFG;

        $host = parse_url($CFG->wwwroot, PHP_URL_HOST) ?: 'localhost';
        $parts = explode('.', $host);

        if (count($parts) > 2) {
            $host = implode('.', array_slice($parts, 1));
        }

        return 'devnull.' . $host;
    }
}
