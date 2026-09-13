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

/**
 * What this plugin listens to: everything.
 *
 * `*` is not a shortcut. Moodle caches the observers declared here, so a plugin
 * cannot rewrite this file when somebody creates a flow — which means the only
 * way to offer every event the site can fire, including the ones belonging to
 * plugins installed after this one, is to be listening for all of them and to
 * decide per event whether anybody cares.
 *
 * `internal => false` is the other half of it. An observer marked internal runs
 * inside whatever transaction fired the event; a non-internal one is held until
 * that transaction commits, and dropped entirely if it rolls back. Both matter
 * here: a flow must not write inside somebody else's transaction, and it must
 * not act on something that, in the end, did not happen.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '*',
        'callback' => '\tool_flowboard\observer::dispatch',
        'internal' => false,
    ],
];
