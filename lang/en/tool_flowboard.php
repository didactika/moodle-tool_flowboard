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

$string['crud:create'] = 'Creates';
$string['crud:delete'] = 'Deletes';
$string['crud:read'] = 'Reads';
$string['crud:update'] = 'Updates';
$string['edulevel:other'] = 'Neither';
$string['edulevel:participating'] = 'Taking part';
$string['edulevel:teaching'] = 'Teaching';
$string['error:graphduplicatekey'] = 'Two nodes in this flow share the key "{$a}". Each node needs one of its own, because that is how the edges say where they lead.';
$string['error:graphedge'] = 'A connection in this flow does not say what it joins.';
$string['error:graphedgeunknown'] = 'A connection leads to "{$a}", which is not a node of this flow.';
$string['error:graphempty'] = 'A flow needs at least one node.';
$string['error:graphnode'] = 'A node in this flow has no key or no type.';
$string['events:allcomponents'] = 'All components';
$string['events:clearfilter'] = 'Clear';
$string['events:component'] = 'Component';
$string['events:componentcolumn'] = 'Component';
$string['events:count'] = 'Showing {$a->shown} of {$a->total} events.';
$string['events:crud'] = 'Does';
$string['events:edulevel'] = 'About';
$string['events:event'] = 'Event';
$string['events:filter'] = 'Filter';
$string['events:heading'] = 'Event catalogue';
$string['events:none'] = 'No event matches what you asked for.';
$string['events:notseenyet'] = 'Not seen yet';
$string['events:payload'] = 'Carries';
$string['events:search'] = 'Search';
$string['flowboard:manage'] = 'Create and manage flows';
$string['flowboard:viewruns'] = 'See what flows have done';
$string['node:action_forum_subscribe'] = 'Subscribe to matching forums';
$string['node:action_forum_unsubscribe'] = 'Unsubscribe from matching forums';
$string['node:condition_payload'] = 'Ask about the event';
$string['node:trigger_event'] = 'When something happens';
$string['pluginname'] = 'Flowboard';
$string['privacy:metadata:tool_flowboard_actor'] = 'The user account a flow acts as, so that what it does is attributed to the flow rather than to a person.';
$string['privacy:metadata:tool_flowboard_actor:flowid'] = 'The flow this account belongs to.';
$string['privacy:metadata:tool_flowboard_actor:userid'] = 'The account created for the flow.';
$string['privacy:metadata:tool_flowboard_flow'] = 'The flows themselves, and who last changed each one.';
$string['privacy:metadata:tool_flowboard_flow:timemodified'] = 'When it was last changed.';
$string['privacy:metadata:tool_flowboard_flow:usermodified'] = 'Who last changed it.';
$string['privacy:metadata:tool_flowboard_run'] = 'What each flow did, to whom, and when. This is what answers why something happened to a particular person.';
$string['privacy:metadata:tool_flowboard_run:eventdata'] = 'The event that set the flow off, as Moodle handed it over.';
$string['privacy:metadata:tool_flowboard_run:subjectid'] = 'The person the flow acted on.';
$string['privacy:metadata:tool_flowboard_run:timestarted'] = 'When the flow ran.';
$string['privacy:metadata:tool_flowboard_version'] = 'Each published drawing of a flow, and who published it.';
$string['privacy:metadata:tool_flowboard_version:timecreated'] = 'When it was published.';
$string['privacy:metadata:tool_flowboard_version:usermodified'] = 'Who published it.';
$string['setting:enabled'] = 'Run flows';
$string['setting:enabled_desc'] = 'The master switch. While it is off no flow runs, whatever each one says, and nothing is queued. Leave it on unless something is going wrong: it is the fastest way to stop everything at once.';
$string['setting:maxdepth'] = 'How many flows deep';
$string['setting:maxdepth_desc'] = 'A flow acts, acting fires events, and those events can set off flows. That is useful until it is a loop, so past this depth nothing else is set off. Raise it only if you have a chain of flows that genuinely needs to be longer.';
$string['setting:maxtargets'] = 'Most targets per run';
$string['setting:maxtargets_desc'] = 'How many people or objects one run of a flow may act on before it stops and says so. A flow that suddenly matches everybody is a mistake, not a workload, and it should fail loudly rather than quietly reach every account on the site.';
$string['setting:retentiondays'] = 'Keep run history for';
$string['setting:retentiondays_desc'] = 'Days of history to keep. The history is what answers "why did this happen to this person", so it is worth keeping longer than it feels necessary; it carries personal data, so it is not worth keeping forever. Zero keeps it indefinitely.';
$string['settings:general'] = 'Settings';
$string['task:run_flow'] = 'Run a flow';
