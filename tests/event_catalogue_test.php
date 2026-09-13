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

/**
 * The catalogue of things a flow can react to.
 *
 * What is being tested is that the list is *found* rather than written down:
 * it has to hold events this plugin has never heard of, and to leave out the
 * classes that are not events anybody could build on.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\event_catalogue
 */
final class event_catalogue_test extends \advanced_testcase {
    /**
     * Core's own events are in the catalogue, described.
     */
    public function test_it_finds_core_events_and_describes_them(): void {
        $this->resetAfterTest();

        $catalogue = event_catalogue::all();
        $event = $catalogue['\core\event\course_viewed'] ?? null;

        $this->assertNotNull($event, 'A catalogue without course_viewed is not reading the site.');
        $this->assertSame('core', $event['component']);
        $this->assertSame('r', $event['crud']);
        $this->assertNotSame('', $event['name'], 'Every event can say what it is called.');
    }

    /**
     * It reaches past core: the events of installed plugins are there too,
     * which is the whole reason for reading them off the site.
     */
    public function test_it_reaches_past_core(): void {
        $this->resetAfterTest();

        $components = [];

        foreach (event_catalogue::all() as $event) {
            $components[$event['component']] = true;
        }

        $this->assertArrayHasKey('mod_forum', $components);
        $this->assertGreaterThan(
            10,
            count($components),
            'A real site fires events from many components.'
        );
    }

    /**
     * The classes that are not events a flow could wait for are left out:
     * the abstract base nobody fires, and core's stand-in for a log entry
     * whose class has gone away.
     */
    public function test_it_leaves_out_what_cannot_be_waited_for(): void {
        $this->resetAfterTest();

        $catalogue = event_catalogue::all();

        $this->assertArrayNotHasKey('\core\event\base', $catalogue);
        $this->assertArrayNotHasKey('\core\event\unknown_logged', $catalogue);
    }

    /**
     * An event is looked up by name, and one that does not exist says so
     * rather than pretending.
     */
    public function test_one_event_is_looked_up_by_name(): void {
        $this->resetAfterTest();

        $this->assertTrue(event_catalogue::exists('\core\event\course_viewed'));
        $this->assertNotNull(event_catalogue::get('\core\event\course_viewed'));

        $this->assertFalse(event_catalogue::exists('\nothing\event\like_this'));
        $this->assertNull(event_catalogue::get('\nothing\event\like_this'));
    }

    /**
     * The components are offered with the names people know them by.
     */
    public function test_components_are_named_as_people_know_them(): void {
        $this->resetAfterTest();

        $components = event_catalogue::components();

        $this->assertArrayHasKey('mod_forum', $components);
        $this->assertSame(get_string('pluginname', 'mod_forum'), $components['mod_forum']);
        $this->assertSame(get_string('coresystem'), $components['core']);
    }

    /**
     * Reading the catalogue is expensive, so it is only read once.
     */
    public function test_the_catalogue_is_only_read_once(): void {
        $this->resetAfterTest();

        $first = event_catalogue::all();
        $cache = \cache::make('tool_flowboard', 'eventcatalogue');

        $this->assertNotFalse($cache->get('all'));

        $cache->set('all', ['\made\event\up' => ['eventname' => '\made\event\up']]);

        $this->assertArrayHasKey('\made\event\up', event_catalogue::all());
        $this->assertArrayHasKey('\core\event\course_viewed', $first);
    }
}
