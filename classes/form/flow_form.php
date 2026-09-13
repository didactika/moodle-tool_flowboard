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

use tool_flowboard\local\flow\flow_repository;

/**
 * A flow's own metadata: what it is called, and what it is for.
 *
 * What the flow actually does is drawn on the canvas, not here — this is the
 * one part of a flow that was never a node and never will be: a name is not
 * a step, so it does not belong on the lienzo.
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

        $this->add_action_buttons(true, get_string('flow:save', 'tool_flowboard'));
    }

    /**
     * Checks the one thing this form is responsible for: that the stable
     * name is not already somebody else's.
     *
     * @param array $data
     * @param array $files
     * @return array<string, string> Field name => error message.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

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
