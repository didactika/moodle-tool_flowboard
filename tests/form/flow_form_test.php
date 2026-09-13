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
 * The form is built for real, not just decided.
 *
 * Testing only the validation logic missed exactly this kind of bug before,
 * in a different plugin of this same suite: an undefined variable that only
 * shows up once the form's elements are actually rendered. So this builds it.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\form\flow_form
 */
final class flow_form_test extends \advanced_testcase {
    /**
     * A blank form renders without PHP complaining.
     */
    public function test_a_new_flow_form_renders(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/tool/flowboard/edit.php');

        $form = new flow_form(new \moodle_url('/admin/tool/flowboard/edit.php'), ['flowid' => 0]);
        $html = $form->render();

        $this->assertStringContainsString('id="id_name"', $html);
        $this->assertStringContainsString('id="id_idnumber"', $html);
        $this->assert_no_complaints($html);
    }

    /**
     * Editing an existing flow renders with its own values already filled
     * in, and its stable name frozen rather than editable.
     */
    public function test_an_existing_flow_renders_prefilled_and_frozen(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/tool/flowboard/edit.php');

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');

        $form = new flow_form(new \moodle_url('/admin/tool/flowboard/edit.php'), ['flowid' => $flow->id]);
        $form->set_data((object) [
            'flowid' => $flow->id,
            'name' => $flow->name,
            'idnumber' => $flow->idnumber,
            'description' => $flow->description,
        ]);
        $html = $form->render();

        $this->assertStringContainsString('Welcome to the forums', $html);
        $this->assertStringContainsString('disabled', $html, 'The stable name is frozen once the flow exists.');
        $this->assert_no_complaints($html);
    }

    /**
     * A name already used by another flow is refused.
     */
    public function test_a_taken_idnumber_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        flow_repository::create('welcome-forums', 'Welcome to the forums');

        $form = new flow_form(new \moodle_url('/admin/tool/flowboard/edit.php'), ['flowid' => 0]);
        $errors = $form->validation(
            ['flowid' => 0, 'name' => 'Something else', 'idnumber' => 'welcome-forums', 'description' => ''],
            []
        );

        $this->assertArrayHasKey('idnumber', $errors);
    }

    /**
     * A flow editing its own idnumber is not refused for holding it already.
     */
    public function test_a_flow_keeping_its_own_idnumber_is_not_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');

        $form = new flow_form(new \moodle_url('/admin/tool/flowboard/edit.php'), ['flowid' => (int) $flow->id]);
        $errors = $form->validation(
            ['flowid' => (int) $flow->id, 'name' => 'Renamed', 'idnumber' => 'welcome-forums', 'description' => ''],
            []
        );

        $this->assertArrayNotHasKey('idnumber', $errors);
    }

    /**
     * Fails if PHP complained anywhere in the rendered output.
     *
     * @param string $html
     * @param string $context
     */
    private function assert_no_complaints(string $html, string $context = ''): void {
        $matches = [];
        preg_match_all(
            '/.{0,80}(Warning|Notice|Undefined|Deprecated|Fatal error).{0,80}/',
            strip_tags($html),
            $matches
        );

        $this->assertEmpty(
            $matches[0] ?? [],
            trim("PHP complained while the form was drawn ({$context}): " . implode(' | ', array_unique($matches[0] ?? [])))
        );
    }
}
