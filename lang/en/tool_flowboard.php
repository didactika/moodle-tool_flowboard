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

$string['actor:firstname'] = 'Flow';
$string['actor:roledescription'] = 'Holds exactly the capabilities the flow "{$a}" needs, and nothing else. Managed by Flowboard; do not assign this role to anyone by hand.';
$string['actor:rolename'] = 'Flow: {$a}';
$string['actors:account'] = 'Account';
$string['actors:active'] = 'Active';
$string['actors:capabilities'] = 'Holds';
$string['actors:flow'] = 'Flow';
$string['actors:heading'] = 'Flow actors';
$string['actors:noflows'] = 'No flow has been published yet, so there is no actor to show.';
$string['actors:none'] = 'Nothing yet';
$string['actors:status'] = 'Status';
$string['actors:suspended'] = 'Suspended';
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
$string['error:missingcapability'] = 'This flow needs {$a}, which you do not hold yourself. A flow cannot be given a capability its own publisher does not have.';
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
$string['flow:actionscolumn'] = 'Actions';
$string['flow:actiontype'] = 'Action';
$string['flow:back'] = 'Back to flows';
$string['flow:conditionfield'] = 'Field';
$string['flow:conditionfield_help'] = 'A dotted path into what the event carries, e.g. `courseid` or `other.state`. Open the event catalogue to see what an event actually carries before naming a field here.';
$string['flow:conditionhint'] = 'Optional. Leave the field blank to always continue to the action.';
$string['flow:conditionoperator'] = 'Is';
$string['flow:conditionvalue'] = 'Value';
$string['flow:conditionvalue_help'] = 'For "in" or "not in", separate several values with commas.';
$string['flow:delete'] = 'Delete';
$string['flow:deleteconfirm'] = 'Delete the flow "{$a}"? Its history goes with it, and cannot be recovered.';
$string['flow:deleted'] = 'Deleted "{$a}".';
$string['flow:description'] = 'Description';
$string['flow:edit'] = 'Edit';
$string['flow:error_idnumbertaken'] = 'Another flow already uses this name.';
$string['flow:error_invalidpattern'] = 'This pattern is not valid.';
$string['flow:error_invalidregex'] = 'This is not a regular expression PHP can use.';
$string['flow:error_notfound'] = 'That flow does not exist.';
$string['flow:error_notrepresentable'] = 'This flow was not built with this editor - its drawing has a shape this form cannot show, so editing it here would narrow it down to what this form understands. It can still be run, paused or deleted from the flow list.';
$string['flow:error_unknownevent'] = 'This site cannot fire that event. Check the event catalogue for the exact class name.';
$string['flow:error_valuerequired'] = 'A value is required for this comparison.';
$string['flow:eventname'] = 'Event';
$string['flow:eventname_help'] = 'The exact class name of the event, such as `\core\event\role_assigned`. Open the event catalogue to search by name or by component.';
$string['flow:golive'] = 'Make live';
$string['flow:heading'] = 'Flows';
$string['flow:history'] = 'History';
$string['flow:idnumber'] = 'Stable name';
$string['flow:idnumber_help'] = 'Used to name this flow\'s actor account and, later, to export it. Cannot be changed once the flow exists.';
$string['flow:lastrun'] = 'Last run';
$string['flow:matchfield'] = 'Match';
$string['flow:matchfield_idnumber'] = 'the activity\'s idnumber';
$string['flow:matchfield_name'] = 'the activity\'s name';
$string['flow:matchoperator'] = 'How';
$string['flow:name'] = 'Name';
$string['flow:neverrun'] = 'Never run';
$string['flow:new'] = 'New flow';
$string['flow:none'] = 'No flow has been created yet.';
$string['flow:nowlive'] = '"{$a}" is now live.';
$string['flow:nowpaused'] = '"{$a}" is now paused.';
$string['flow:operator_contains'] = 'contains';
$string['flow:operator_empty'] = 'is empty';
$string['flow:operator_equals'] = 'equals';
$string['flow:operator_exact'] = 'is exactly';
$string['flow:operator_in'] = 'is one of';
$string['flow:operator_matches'] = 'matches the regular expression';
$string['flow:operator_notempty'] = 'is not empty';
$string['flow:operator_notequals'] = 'does not equal';
$string['flow:operator_notin'] = 'is not one of';
$string['flow:operator_regex'] = 'matches the regular expression';
$string['flow:operator_startswith'] = 'starts with';
$string['flow:pattern'] = 'Pattern';
$string['flow:pattern_help'] = 'What to look for. What it means depends on "How": plain text for "is exactly", "contains" and "starts with"; a PHP-compatible regular expression for "matches".';
$string['flow:pause'] = 'Pause';
$string['flow:save'] = 'Save flow';
$string['flow:saved'] = '"{$a->name}" saved. It needs: {$a->capabilities}.';
$string['flow:savednocapabilities'] = '"{$a}" saved. It needs no capabilities beyond what any account already has.';
$string['flow:sectionaction'] = 'Then';
$string['flow:sectioncondition'] = 'Only if';
$string['flow:sectionflow'] = 'About this flow';
$string['flow:sectiontrigger'] = 'When';
$string['flow:starthere'] = 'Start from a template, or build your own';
$string['flow:status'] = 'Status';
$string['flow:status_draft'] = 'Draft';
$string['flow:status_live'] = 'Live';
$string['flow:status_paused'] = 'Paused';
$string['flow:subject'] = 'About';
$string['flow:subject_actor'] = 'the person the event says acted';
$string['flow:subject_help'] = 'Moodle is not consistent about who an event names as its subject: some events name the person who acted, others name the person acted upon. Check the event catalogue for the event you chose above to see which field it actually carries.';
$string['flow:subject_related'] = 'the person the event says was affected';
$string['flow:usetemplate'] = 'Use this';
$string['flowboard:manage'] = 'Create and manage flows';
$string['flowboard:viewruns'] = 'See what flows have done';
$string['history:back'] = 'Back to flows';
$string['history:dryrun'] = 'Dry run';
$string['history:heading'] = 'History: {$a}';
$string['history:node'] = 'Node';
$string['history:nodesheading'] = 'What each node did';
$string['history:nodestatus'] = 'Status';
$string['history:nodesummary'] = 'Summary';
$string['history:none'] = 'This flow has not run yet.';
$string['history:nosubject'] = 'Nobody in particular';
$string['history:status_failed'] = 'Failed';
$string['history:status_ok'] = 'Succeeded';
$string['history:status_running'] = 'Running';
$string['history:status_skipped'] = 'Skipped';
$string['history:status_waiting'] = 'Waiting';
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
$string['template:r1'] = 'A student starts a course';
$string['template:r1_desc'] = 'Subscribes the student to the forums that match a pattern you choose.';
$string['template:r2'] = 'A student\'s course state changes';
$string['template:r2_desc'] = 'Unsubscribes the student from the forums that match, unless the course has just started or just finished.';
$string['template:r3'] = 'Somebody is given a role in a course';
$string['template:r3_desc'] = 'Subscribes them to the forums whose idnumber matches a pattern you choose.';
$string['template:r4'] = 'Somebody\'s role in a course is taken away';
$string['template:r4_desc'] = 'Unsubscribes them from the forums whose idnumber matches a pattern you choose.';
