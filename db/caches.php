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
 * Caches this plugin defines.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [

    // Every event the site can fire, read off the installed components.
    //
    // Building it means reflecting over every class in every component's event
    // namespace, which is far too expensive to do per request, and the answer
    // only changes when a plugin is installed or upgraded — at which point
    // Moodle purges its caches anyway.
    'eventcatalogue' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
    ],

    // Which flows are waiting for which event.
    //
    // This one is read on the way past every event the site fires, which is
    // the busiest path this plugin has by a wide margin and the reason it is a
    // cache at all: on a site with no flow waiting for an event, answering
    // costs one cache read and no query. Static acceleration matters as much
    // as the cache itself here, because the same request asks the same
    // question hundreds of times.
    'flowindex' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
    ],
];
