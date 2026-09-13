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

/**
 * Runs a piece of work as if a given user were logged in, then puts the
 * previous one back.
 *
 * A flow's actions are attributed to its actor by whichever `$USER` is active
 * when the write happens — that is simply how Moodle's own event system
 * decides `userid` (`\core\event\base::create()` defaults it from `$USER->id`
 * when nothing is passed explicitly), and it is true of the raw writes this
 * plugin's nodes call too, like `mod_forum\subscriptions::subscribe_user()`,
 * which never accepts a "who did this" argument at all. Impersonation is not
 * a trick here; it is the only way to make attribution true.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class acting_as {
    /**
     * Runs `$callback` with `$USER` set to the given user.
     *
     * The previous user is restored even when the callback throws. Uses
     * `\core\cron::setup_user()` — the supported way to become a user outside
     * a real login — with `$leavepagealone` set, both to keep `$PAGE` intact
     * and because that flag is what lets it run outside a CLI script (a flow
     * can be run from a web request too, e.g. a manual trigger in a later
     * phase).
     *
     * @param int $userid The user to act as.
     * @param callable $callback
     * @return mixed Whatever the callback returns.
     */
    public static function user(int $userid, callable $callback) {
        global $USER, $DB;

        $previous = $USER;
        $target = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        \core\cron::setup_user($target, null, true);

        try {
            return $callback();
        } finally {
            // A previous user with no id is the empty/not-logged-in session a
            // task starts from; null puts the default cron user back, which is
            // what the task runner would have set up anyway.
            \core\cron::setup_user(empty($previous->id) ? null : $previous, null, true);
        }
    }
}
