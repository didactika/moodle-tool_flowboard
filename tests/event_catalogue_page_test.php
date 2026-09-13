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

use tool_flowboard\local\event_catalogue;
use tool_flowboard\local\event_seen_repository;
use tool_flowboard\output\event_catalogue_page;

/**
 * The catalogue as a page: drawn for real, not just decided.
 *
 * Rendering is where a page actually breaks — a string that was never added, a
 * template variable nobody passed — and none of that shows up in a test of the
 * class behind it.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\output\event_catalogue_page
 */
final class event_catalogue_page_test extends \advanced_testcase {
    /**
     * The page draws, and PHP has nothing to say while it happens.
     */
    public function test_the_page_draws(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/tool/flowboard/events.php');
        $output = $PAGE->get_renderer('core');

        $page = new event_catalogue_page(event_catalogue::all());
        $html = $output->render_from_template('tool_flowboard/event_catalogue', $page->export_for_template($output));

        $this->assertStringContainsString('tool-flowboard-catalogue', $html);
        $this->assertStringContainsString('\core\event\course_viewed', $html);
        $this->assert_no_complaints($html);
    }

    /**
     * Asking for one component narrows the table to it.
     */
    public function test_filtering_by_component_narrows_the_table(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/tool/flowboard/events.php');
        $output = $PAGE->get_renderer('core');

        $page = new event_catalogue_page(event_catalogue::all(), 'mod_forum');
        $context = $page->export_for_template($output);

        $this->assertNotEmpty($context['rows']);
        $this->assertTrue($context['filtered']);

        foreach ($context['rows'] as $row) {
            $this->assertStringContainsString('mod_forum', $row['eventname']);
        }
    }

    /**
     * Searching looks at what the event is called as well as at its class.
     */
    public function test_searching_looks_at_the_name_too(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/tool/flowboard/events.php');
        $output = $PAGE->get_renderer('core');

        $page = new event_catalogue_page(event_catalogue::all(), '', 'course viewed');
        $context = $page->export_for_template($output);

        $names = array_column($context['rows'], 'eventname');

        $this->assertContains('\core\event\course_viewed', $names);
    }

    /**
     * A search that matches nothing says so, rather than drawing an empty
     * table and leaving the reader to work it out.
     */
    public function test_a_search_that_matches_nothing_says_so(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/tool/flowboard/events.php');
        $output = $PAGE->get_renderer('core');

        $page = new event_catalogue_page(event_catalogue::all(), '', 'zzzzzzzznothing');
        $html = $output->render_from_template('tool_flowboard/event_catalogue', $page->export_for_template($output));

        $this->assertStringContainsString(get_string('events:none', 'tool_flowboard'), $html);
    }

    /**
     * Once an event has been seen, the page says what it was carrying — which
     * is the whole point of learning it.
     */
    public function test_a_seen_event_shows_what_it_carries(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/tool/flowboard/events.php');
        $output = $PAGE->get_renderer('core');

        event_seen_repository::record('\core\event\course_viewed', 'core', [
            'courseid' => 4,
            'other' => ['sectionnumber' => 2],
        ]);

        $page = new event_catalogue_page(event_catalogue::all(), '', 'course viewed');
        $html = $output->render_from_template('tool_flowboard/event_catalogue', $page->export_for_template($output));

        $this->assertStringContainsString('other.sectionnumber', $html);
        $this->assertStringNotContainsString(
            get_string('events:notseenyet', 'tool_flowboard'),
            $html,
            'This one has been seen.'
        );
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
