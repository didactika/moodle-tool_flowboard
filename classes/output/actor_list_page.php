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

namespace tool_flowboard\output;

use renderer_base;
use tool_flowboard\local\actor\actor_repository;
use tool_flowboard\local\flow\flow_repository;

/**
 * Every flow's actor, in one place: who it is, what it may do, which flow it
 * belongs to.
 *
 * This is the page that answers "who is `flow.welcome-forums`, and why does it
 * exist" for whoever notices the account in a user list — a question a site
 * would otherwise have to answer by reading this plugin's own tables by hand.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class actor_list_page implements \renderable, \templatable {
    /**
     * What the template draws.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $actors = actor_repository::all();
        $rows = [];

        foreach ($actors as $actor) {
            $flow = flow_repository::get((int) $actor->flowid);

            if ($flow === null) {
                // Deleting a flow deletes its actor with it; a row here for a
                // flow that no longer exists would mean that failed partway.
                continue;
            }

            $user = $DB->get_record('user', ['id' => $actor->userid], 'id, username, suspended');
            $capabilities = actor_repository::capabilities($actor);
            sort($capabilities);

            // No link to the flow itself yet: the list and editor screens are
            // built in the phase after this one. This page only has to say
            // whose account this is, not manage it.
            $rows[] = [
                'flowname' => $flow->name,
                'flowstatus' => $flow->status,
                'username' => $user !== false ? $user->username : '',
                'suspended' => $actor->status === actor_repository::STATUS_SUSPENDED,
                'capabilities' => $capabilities,
                'hascapabilities' => $capabilities !== [],
            ];
        }

        return [
            'rows' => $rows,
            'hasrows' => $rows !== [],
        ];
    }
}
