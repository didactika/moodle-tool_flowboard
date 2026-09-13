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

namespace tool_flowboard;

use tool_flowboard\local\flow\flow_repository;
use tool_flowboard\local\flow\graph_repository;
use tool_flowboard\local\run\run_repository;
use tool_flowboard\output\flow_list_page;
use tool_flowboard\output\run_history_page;

/**
 * The flow list and the run history, drawn for real.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\output\flow_list_page
 * @covers     \tool_flowboard\output\run_history_page
 */
final class flow_list_page_test extends \advanced_testcase {
    /**
     * An empty site still draws a page, with the four templates offered.
     */
    public function test_an_empty_list_still_draws(): void {
        global $OUTPUT, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/tool/flowboard/index.php');

        $output = $PAGE->get_renderer('core');
        $page = new flow_list_page();
        $html = $output->render_from_template('tool_flowboard/flow_list', $page->export_for_template($output));

        $this->assertStringContainsString(get_string('flow:none', 'tool_flowboard'), $html);
        $this->assertStringContainsString(get_string('template:r1', 'tool_flowboard'), $html);
        $this->assert_no_complaints($html);
    }

    /**
     * A real flow, live and once run, draws with its status and its last run
     * time.
     */
    public function test_a_live_flow_is_listed_with_its_status(): void {
        global $OUTPUT, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/tool/flowboard/index.php');

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        graph_repository::publish((int) $flow->id, $this->one_node_graph());
        flow_repository::set_status((int) $flow->id, flow_repository::STATUS_LIVE);

        $output = $PAGE->get_renderer('core');
        $page = new flow_list_page();
        $html = $output->render_from_template('tool_flowboard/flow_list', $page->export_for_template($output));

        $this->assertStringContainsString('Welcome to the forums', $html);
        $this->assertStringContainsString(get_string('flow:status_live', 'tool_flowboard'), $html);
        $this->assert_no_complaints($html);
    }

    /**
     * A flow's history draws, including a node's summary and its subject's
     * name.
     */
    public function test_run_history_draws_a_runs_nodes(): void {
        global $OUTPUT, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/tool/flowboard/history.php');

        $student = $this->getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Lopez']);
        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        $versionid = graph_repository::publish((int) $flow->id, $this->one_node_graph());

        $runid = run_repository::start(flow_repository::get((int) $flow->id), $versionid, [
            'eventname' => '\core\event\role_assigned',
            'subjectid' => (int) $student->id,
        ]);
        $summary = ['eventname' => '\core\event\role_assigned'];
        run_repository::record_node($runid, 'trigger', 'trigger_event', 'ok', $summary);
        run_repository::finish($runid, run_repository::STATUS_OK);

        $output = $PAGE->get_renderer('core');
        $page = new run_history_page(flow_repository::get((int) $flow->id));
        $html = $output->render_from_template('tool_flowboard/run_history', $page->export_for_template($output));

        $this->assertStringContainsString('Ana Lopez', $html);
        $this->assertStringContainsString('trigger_event', $html);
        $this->assert_no_complaints($html);
    }

    /**
     * A flow that has never run says so, plainly.
     */
    public function test_a_flow_with_no_runs_says_so(): void {
        global $OUTPUT, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/tool/flowboard/history.php');

        $flow = flow_repository::create('never-run', 'Never run');

        $output = $PAGE->get_renderer('core');
        $page = new run_history_page($flow);
        $html = $output->render_from_template('tool_flowboard/run_history', $page->export_for_template($output));

        $this->assertStringContainsString(get_string('history:none', 'tool_flowboard'), $html);
    }

    /**
     * One trigger node, needing no capability — enough to publish without an
     * actor's capabilities getting in the way of what this test is about.
     *
     * @return array
     */
    private function one_node_graph(): array {
        return [
            'nodes' => [['key' => 'trigger', 'type' => 'trigger_event', 'config' => ['eventname' => '\core\event\role_assigned']]],
            'edges' => [],
        ];
    }

    /**
     * Fails if PHP complained anywhere in the rendered output.
     *
     * @param string $html
     */
    private function assert_no_complaints(string $html): void {
        $matches = [];
        preg_match_all(
            '/.{0,80}(Warning|Notice|Undefined|Deprecated|Fatal error).{0,80}/',
            strip_tags($html),
            $matches
        );

        $this->assertEmpty(
            $matches[0] ?? [],
            'PHP complained while the page was drawn: ' . implode(' | ', array_unique($matches[0] ?? []))
        );
    }
}
