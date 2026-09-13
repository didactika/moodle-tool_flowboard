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

namespace tool_flowboard\form;

use tool_flowboard\local\event\event_catalogue;
use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\matching\forum_matcher;
use tool_flowboard\local\node\trigger_event;
use tool_flowboard\local\matching\pattern_matcher;

/**
 * A flow, in the one shape phase 1's nodes can build: a trigger, an optional
 * question about it, and one action.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_form extends \moodleform {
    /**
     * Builds the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $editing = !empty($this->_customdata['flowid']);

        $mform->addElement('hidden', 'flowid', $this->_customdata['flowid'] ?? 0);
        $mform->setType('flowid', PARAM_INT);

        $mform->addElement('header', 'flowheader', get_string('flow:sectionflow', 'tool_flowboard'));

        $mform->addElement('text', 'name', get_string('flow:name', 'tool_flowboard'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');

        $mform->addElement('text', 'idnumber', get_string('flow:idnumber', 'tool_flowboard'));
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', null, 'required');
        $mform->addHelpButton('idnumber', 'flow:idnumber', 'tool_flowboard');
        // An existing flow's stable name is not editable here — see
        // flow_repository::update()'s own reasoning: exports and the flow's
        // actor are both named after it, and changing it would orphan both.
        if ($editing) {
            $mform->hardFreeze('idnumber');
        }

        $mform->addElement('textarea', 'description', get_string('flow:description', 'tool_flowboard'));
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('header', 'triggerheader', get_string('flow:sectiontrigger', 'tool_flowboard'));

        $mform->addElement('text', 'eventname', get_string('flow:eventname', 'tool_flowboard'));
        $mform->setType('eventname', PARAM_RAW_TRIMMED);
        $mform->addRule('eventname', null, 'required');
        $mform->addHelpButton('eventname', 'flow:eventname', 'tool_flowboard');

        $mform->addElement('select', 'subject', get_string('flow:subject', 'tool_flowboard'), [
            trigger_event::SUBJECT_RELATED => get_string('flow:subject_related', 'tool_flowboard'),
            trigger_event::SUBJECT_ACTOR => get_string('flow:subject_actor', 'tool_flowboard'),
        ]);
        $mform->addHelpButton('subject', 'flow:subject', 'tool_flowboard');

        $mform->addElement('header', 'conditionheader', get_string('flow:sectioncondition', 'tool_flowboard'));
        $mform->setExpanded('conditionheader', false);

        $mform->addElement('static', 'conditionhint', '', get_string('flow:conditionhint', 'tool_flowboard'));

        $mform->addElement('text', 'conditionfield', get_string('flow:conditionfield', 'tool_flowboard'));
        $mform->setType('conditionfield', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('conditionfield', 'flow:conditionfield', 'tool_flowboard');

        $mform->addElement('select', 'conditionoperator', get_string('flow:conditionoperator', 'tool_flowboard'), [
            'equals' => get_string('flow:operator_equals', 'tool_flowboard'),
            'notequals' => get_string('flow:operator_notequals', 'tool_flowboard'),
            'in' => get_string('flow:operator_in', 'tool_flowboard'),
            'notin' => get_string('flow:operator_notin', 'tool_flowboard'),
            'contains' => get_string('flow:operator_contains', 'tool_flowboard'),
            'matches' => get_string('flow:operator_matches', 'tool_flowboard'),
            'empty' => get_string('flow:operator_empty', 'tool_flowboard'),
            'notempty' => get_string('flow:operator_notempty', 'tool_flowboard'),
        ]);

        $mform->addElement('text', 'conditionvalue', get_string('flow:conditionvalue', 'tool_flowboard'));
        $mform->setType('conditionvalue', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('conditionvalue', 'flow:conditionvalue', 'tool_flowboard');

        $mform->addElement('header', 'actionheader', get_string('flow:sectionaction', 'tool_flowboard'));

        $mform->addElement(
            'select',
            'actiontype',
            get_string('flow:actiontype', 'tool_flowboard'),
            \tool_flowboard\local\flow\flow_editor::action_types()
        );

        $mform->addElement('select', 'matchfield', get_string('flow:matchfield', 'tool_flowboard'), [
            forum_matcher::FIELD_NAME => get_string('flow:matchfield_name', 'tool_flowboard'),
            forum_matcher::FIELD_IDNUMBER => get_string('flow:matchfield_idnumber', 'tool_flowboard'),
        ]);

        $mform->addElement('select', 'matchoperator', get_string('flow:matchoperator', 'tool_flowboard'), [
            pattern_matcher::OPERATOR_EXACT => get_string('flow:operator_exact', 'tool_flowboard'),
            pattern_matcher::OPERATOR_CONTAINS => get_string('flow:operator_contains', 'tool_flowboard'),
            pattern_matcher::OPERATOR_STARTSWITH => get_string('flow:operator_startswith', 'tool_flowboard'),
            pattern_matcher::OPERATOR_REGEX => get_string('flow:operator_regex', 'tool_flowboard'),
        ]);

        $mform->addElement('text', 'pattern', get_string('flow:pattern', 'tool_flowboard'));
        $mform->setType('pattern', PARAM_RAW_TRIMMED);
        $mform->addRule('pattern', null, 'required');
        $mform->addHelpButton('pattern', 'flow:pattern', 'tool_flowboard');

        $this->add_action_buttons(true, get_string('flow:save', 'tool_flowboard'));
    }

    /**
     * Checks what a graph built from this data would actually need to be
     * true: the event exists, the pattern is one its operator can use, the
     * idnumber is not already somebody else's.
     *
     * @param array $data
     * @param array $files
     * @return array<string, string> Field name => error message.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (trim($data['eventname']) !== '' && !event_catalogue::exists(trim($data['eventname']))) {
            $errors['eventname'] = get_string('flow:error_unknownevent', 'tool_flowboard');
        }

        $hascondition = trim($data['conditionfield'] ?? '') !== '';
        $needsvalue = !in_array($data['conditionoperator'], ['empty', 'notempty'], true);
        $valuegiven = trim($data['conditionvalue'] ?? '') !== '';

        if ($hascondition && $needsvalue && !$valuegiven) {
            $errors['conditionvalue'] = get_string('flow:error_valuerequired', 'tool_flowboard');
        }

        $pattern = trim($data['pattern'] ?? '');
        $patternvalid = pattern_matcher::is_valid_pattern($data['matchoperator'], $pattern);

        if ($pattern !== '' && !$patternvalid) {
            $errors['pattern'] = $data['matchoperator'] === pattern_matcher::OPERATOR_REGEX
                ? get_string('flow:error_invalidregex', 'tool_flowboard')
                : get_string('flow:error_invalidpattern', 'tool_flowboard');
        }

        $idnumber = trim($data['idnumber'] ?? '');

        if ($idnumber !== '') {
            $existing = flow_repository::get_by_idnumber($idnumber);

            if ($existing !== null && (int) $existing->id !== (int) ($data['flowid'] ?? 0)) {
                $errors['idnumber'] = get_string('flow:error_idnumbertaken', 'tool_flowboard');
            }
        }

        return $errors;
    }
}
