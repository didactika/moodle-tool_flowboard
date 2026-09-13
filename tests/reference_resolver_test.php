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

use tool_flowboard\local\flow\flow_context;
use tool_flowboard\local\run\reference_resolver;

/**
 * Config values that name a field instead of spelling one out.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\run\reference_resolver
 */
final class reference_resolver_test extends \advanced_testcase {
    /**
     * A literal is left exactly as it was typed.
     */
    public function test_a_literal_is_untouched(): void {
        $this->resetAfterTest();

        $context = new flow_context((object) [], ['courseid' => 7]);
        $resolved = reference_resolver::resolve(['pattern' => 'General Forum'], $context);

        $this->assertSame('General Forum', $resolved['pattern']);
    }

    /**
     * A config value that is nothing but one token is replaced by whatever
     * that field actually holds, keeping its own type.
     */
    public function test_a_whole_value_token_resolves_to_the_events_own_type(): void {
        $this->resetAfterTest();

        $context = new flow_context((object) [], ['courseid' => 7, 'other' => ['state' => 'OPEN_STARTED']]);
        $resolved = reference_resolver::resolve([
            'value' => '{{event:courseid}}',
            'state' => '{{event:other.state}}',
        ], $context);

        $this->assertSame(7, $resolved['value'], 'An int stays an int, not "7".');
        $this->assertSame('OPEN_STARTED', $resolved['state']);
    }

    /**
     * A token inside a longer string is substituted as text, and the rest of
     * the string is left as it was.
     */
    public function test_a_token_inside_a_longer_string_is_interpolated(): void {
        $this->resetAfterTest();

        $context = new flow_context((object) [], ['courseid' => 7]);
        $resolved = reference_resolver::resolve(['pattern' => 'course-{{event:courseid}}-forum'], $context);

        $this->assertSame('course-7-forum', $resolved['pattern']);
    }

    /**
     * What an earlier node wrote down is read back the same way as what the
     * event itself carried, just from the other source.
     */
    public function test_a_context_reference_reads_what_an_earlier_node_produced(): void {
        $this->resetAfterTest();

        $context = new flow_context((object) [], []);
        $context->set('courseid', 42);

        $resolved = reference_resolver::resolve(['value' => '{{context:courseid}}'], $context);

        $this->assertSame(42, $resolved['value']);
    }

    /**
     * A field the event or the run does not carry resolves to nothing, rather
     * than the token itself leaking into what the node reads.
     */
    public function test_a_field_that_does_not_exist_resolves_to_null(): void {
        $this->resetAfterTest();

        $context = new flow_context((object) [], []);
        $resolved = reference_resolver::resolve(['value' => '{{event:nothinghere}}'], $context);

        $this->assertNull($resolved['value']);
    }

    /**
     * A value with no token at all, and values of every other type, pass
     * through unexamined.
     */
    public function test_non_string_and_token_free_values_pass_through(): void {
        $this->resetAfterTest();

        $context = new flow_context((object) [], []);
        $resolved = reference_resolver::resolve([
            'count' => 3,
            'flag' => true,
            'nested' => ['pattern' => 'course-{{event:courseid}}'],
        ], $context);

        $this->assertSame(3, $resolved['count']);
        $this->assertTrue($resolved['flag']);
        $this->assertSame('course-', $resolved['nested']['pattern']);
    }
}
