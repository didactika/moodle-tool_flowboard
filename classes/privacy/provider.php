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

namespace tool_flowboard\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * What Flowboard knows about people, and how to hand it over or forget it.
 *
 * Three things here are about a person: the run history says which person a
 * flow acted on and when, the flows themselves remember who last edited them,
 * and a flow's actor is a user account of its own.
 *
 * The run history is the interesting case, because it is the answer to "why is
 * this student subscribed to this forum" — worth keeping, and worth being able
 * to hand to the student who asks. It is all site-level: a flow reaches across
 * courses, so its record belongs to the system context rather than to any one
 * of them.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * What this plugin stores about people.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_flowboard_run', [
            'subjectid' => 'privacy:metadata:tool_flowboard_run:subjectid',
            'eventdata' => 'privacy:metadata:tool_flowboard_run:eventdata',
            'timestarted' => 'privacy:metadata:tool_flowboard_run:timestarted',
        ], 'privacy:metadata:tool_flowboard_run');

        $collection->add_database_table('tool_flowboard_flow', [
            'usermodified' => 'privacy:metadata:tool_flowboard_flow:usermodified',
            'timemodified' => 'privacy:metadata:tool_flowboard_flow:timemodified',
        ], 'privacy:metadata:tool_flowboard_flow');

        $collection->add_database_table('tool_flowboard_version', [
            'usermodified' => 'privacy:metadata:tool_flowboard_version:usermodified',
            'timecreated' => 'privacy:metadata:tool_flowboard_version:timecreated',
        ], 'privacy:metadata:tool_flowboard_version');

        $collection->add_database_table('tool_flowboard_actor', [
            'userid' => 'privacy:metadata:tool_flowboard_actor:userid',
            'flowid' => 'privacy:metadata:tool_flowboard_actor:flowid',
        ], 'privacy:metadata:tool_flowboard_actor');

        return $collection;
    }

    /**
     * Where a user's data lives, which for this plugin is only ever the site
     * itself.
     *
     * @param int $userid
     * @return \core_privacy\local\request\contextlist
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        if (self::has_data_for($userid)) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Everyone this plugin holds something about, in a given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $userlist->add_from_sql('subjectid', 'SELECT subjectid FROM {tool_flowboard_run} WHERE subjectid IS NOT NULL', []);
        $userlist->add_from_sql('usermodified', 'SELECT usermodified FROM {tool_flowboard_flow}', []);
        $userlist->add_from_sql('usermodified', 'SELECT usermodified FROM {tool_flowboard_version}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {tool_flowboard_actor}', []);
    }

    /**
     * Hands a person what this plugin has about them.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!self::includes_system($contextlist)) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;
        $runs = $DB->get_records_sql(
            "SELECT r.id, r.timestarted, r.status, r.eventname, f.name AS flowname
               FROM {tool_flowboard_run} r
               JOIN {tool_flowboard_flow} f ON f.id = r.flowid
              WHERE r.subjectid = :userid
           ORDER BY r.timestarted ASC",
            ['userid' => $userid]
        );

        if ($runs === []) {
            return;
        }

        $data = [];

        foreach ($runs as $run) {
            $data[] = (object) [
                'flow' => $run->flowname,
                'event' => $run->eventname,
                'status' => $run->status,
                'timestarted' => transform::datetime($run->timestarted),
            ];
        }

        writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'tool_flowboard')],
            (object) ['runs' => $data]
        );
    }

    /**
     * Forgets everyone in a context.
     *
     * The runs are not deleted, they are detached: a run is also a record of
     * what the site did to itself, and deleting it would erase the answer to
     * "why is this forum subscription here" along with the person's name. What
     * goes is the link to the person, which is the part that is about them.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $DB->set_field_select('tool_flowboard_run', 'subjectid', null, 'subjectid IS NOT NULL');
        $DB->set_field_select('tool_flowboard_run', 'eventdata', null, 'eventdata IS NOT NULL');
    }

    /**
     * Forgets one person.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (!self::includes_system($contextlist)) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;
        $DB->set_field('tool_flowboard_run', 'subjectid', null, ['subjectid' => $userid]);
        $DB->set_field('tool_flowboard_run', 'eventdata', null, ['subjectid' => $userid]);
    }

    /**
     * Forgets a list of people.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();

        if ($userids === []) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->set_field_select('tool_flowboard_run', 'subjectid', null, "subjectid {$insql}", $params);
        $DB->set_field_select('tool_flowboard_run', 'eventdata', null, "subjectid {$insql}", $params);
    }

    /**
     * Whether this plugin holds anything about a person at all.
     *
     * @param int $userid
     * @return bool
     */
    private static function has_data_for(int $userid): bool {
        global $DB;

        return $DB->record_exists('tool_flowboard_run', ['subjectid' => $userid])
            || $DB->record_exists('tool_flowboard_flow', ['usermodified' => $userid])
            || $DB->record_exists('tool_flowboard_version', ['usermodified' => $userid])
            || $DB->record_exists('tool_flowboard_actor', ['userid' => $userid]);
    }

    /**
     * Whether an approved list includes the site itself, which is the only
     * place this plugin keeps anything.
     *
     * @param approved_contextlist $contextlist
     * @return bool
     */
    private static function includes_system(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                return true;
            }
        }

        return false;
    }
}
