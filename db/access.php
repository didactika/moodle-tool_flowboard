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
 * Capabilities this plugin defines.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Creating, editing and publishing flows.
    //
    // Deliberately not granted to any archetype. A flow acts on the site on
    // its own, so whoever writes one can reach further than they could by
    // hand in an afternoon; that is a decision a site takes explicitly, for
    // a named person, not something a role inherits because it happens to be
    // called "manager".
    'tool/flowboard:manage' => [
        'riskbitmask' => RISK_SPAM | RISK_DATALOSS | RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],

    // Reading the history: what ran, over whom, and what it did.
    //
    // Separate from managing, because the two audiences are different. The
    // history answers "why is this student subscribed to this forum", which
    // someone supporting users needs to see without being able to change
    // what the site does next.
    'tool/flowboard:viewruns' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
