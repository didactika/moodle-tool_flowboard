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

use tool_flowboard\local\event\event_catalogue;
use tool_flowboard\local\flow\flow_editor;
use tool_flowboard\local\flow\flow_templates;
use tool_flowboard\local\node\node_registry;

/**
 * The four rules this plugin was built to answer, ready in one click.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\flow\flow_templates
 */
final class flow_templates_test extends \advanced_testcase {
    /**
     * Every template names a real event and a real node type — nothing here
     * quietly points at something this site cannot actually do.
     */
    public function test_every_template_names_things_that_really_exist(): void {
        $this->resetAfterTest();

        foreach (flow_templates::all() as $template) {
            $data = flow_templates::data($template);

            $this->assertTrue(
                event_catalogue::exists($data['eventname']),
                "{$template}'s event, {$data['eventname']}, must be a real event."
            );
            $this->assertTrue(
                node_registry::exists($data['actiontype']),
                "{$template}'s action, {$data['actiontype']}, must be a real node type."
            );
        }
    }

    /**
     * Every template leaves the pattern for the site to fill in: that is the
     * one thing only a site can answer.
     */
    public function test_every_template_leaves_the_pattern_blank(): void {
        foreach (flow_templates::all() as $template) {
            $this->assertSame('', flow_templates::data($template)['pattern']);
        }
    }

    /**
     * A template's data is a graph this editor can build and read straight
     * back — it is, after all, built from the very same fields the form has.
     */
    public function test_a_templates_data_is_representable(): void {
        foreach (flow_templates::all() as $template) {
            $data = flow_templates::data($template);
            unset($data['name']);
            $data['pattern'] = 'something';

            $graph = flow_editor::to_graph((object) $data);
            $read = flow_editor::from_graph($graph);

            $this->assertTrue($read['representable'], "{$template} must read back through its own editor.");
        }
    }

    /**
     * R2 keeps the subscription on the two states that mean "still enrolled
     * and engaged", and nothing else — the exact rule the user settled on.
     */
    public function test_r2_keeps_only_open_started_and_finalized(): void {
        $data = flow_templates::data(flow_templates::R2);

        $this->assertSame('other.state', $data['conditionfield']);
        $this->assertSame('notin', $data['conditionoperator']);
        $this->assertSame('OPEN_STARTED,FINALIZED', $data['conditionvalue']);
    }

    /**
     * Asking for a template that does not exist is a programming mistake, not
     * a silently empty flow.
     */
    public function test_an_unknown_template_is_refused(): void {
        $this->expectException(\coding_exception::class);
        flow_templates::data('nonexistent');
    }
}
