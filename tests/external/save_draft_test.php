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

namespace tool_flowboard\external;

use core_external\external_api;
use tool_flowboard\local\flow\flow_repository;

/**
 * The canvas's own autosave.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\external\save_draft
 */
final class save_draft_test extends \advanced_testcase {
    /**
     * Whatever is drawn is kept, even a half-connected drawing with no
     * trigger at all — a draft is not held to publish()'s own rules.
     */
    public function test_a_half_drawn_graph_is_still_saved(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');
        $graph = ['nodes' => [['key' => 'a', 'type' => 'condition_payload', 'config' => []]], 'edges' => []];

        $result = external_api::clean_returnvalue(
            save_draft::execute_returns(),
            save_draft::execute((int) $flow->id, json_encode($graph))
        );

        $this->assertTrue($result['saved']);
        $this->assertSame(['a'], array_column(flow_repository::draft((int) $flow->id)['nodes'], 'key'));
    }

    /**
     * Nothing that could not even be the shape of a graph is accepted.
     */
    public function test_something_that_is_not_a_graph_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $flow = flow_repository::create('welcome-forums', 'Welcome to the forums');

        $this->expectException(\moodle_exception::class);
        save_draft::execute((int) $flow->id, json_encode(['nothing' => 'here']));
    }

    /**
     * A flow that does not exist has nowhere to save a draft.
     */
    public function test_an_unknown_flow_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        save_draft::execute(999999, json_encode(['nodes' => [], 'edges' => []]));
    }
}
