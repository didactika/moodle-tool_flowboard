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
 * Language strings for tool_flowboard.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['flowboard:manage'] = 'Create and manage flows';
$string['flowboard:viewruns'] = 'See what flows have done';
$string['pluginname'] = 'Flowboard';
$string['privacy:metadata'] = 'Flowboard does not store any personal data yet. It will once it records what its flows have done, and this statement will describe it.';
$string['setting:enabled'] = 'Run flows';
$string['setting:enabled_desc'] = 'The master switch. While it is off no flow runs, whatever each one says, and nothing is queued. Leave it on unless something is going wrong: it is the fastest way to stop everything at once.';
$string['setting:maxtargets'] = 'Most targets per run';
$string['setting:maxtargets_desc'] = 'How many people or objects one run of a flow may act on before it stops and says so. A flow that suddenly matches everybody is a mistake, not a workload, and it should fail loudly rather than quietly reach every account on the site.';
$string['setting:retentiondays'] = 'Keep run history for';
$string['setting:retentiondays_desc'] = 'Days of history to keep. The history is what answers "why did this happen to this person", so it is worth keeping longer than it feels necessary; it carries personal data, so it is not worth keeping forever. Zero keeps it indefinitely.';
$string['settings:general'] = 'General';
