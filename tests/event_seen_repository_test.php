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

use tool_flowboard\local\event\event_seen_repository;

/**
 * Learning what an event carries, without learning anything about anybody.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\event\event_seen_repository
 */
final class event_seen_repository_test extends \advanced_testcase {
    /**
     * The keys are learned, including the ones nested inside `other`, which is
     * exactly the part Moodle never declares.
     */
    public function test_it_learns_the_keys_an_event_carries(): void {
        $this->resetAfterTest();

        event_seen_repository::record('\local_coursestate\event\course_state_updated', 'local_coursestate', [
            'courseid' => 7,
            'relateduserid' => 42,
            'other' => ['state' => 'OPEN_STARTED', 'previous_state' => 'OPEN_NOT_STARTED'],
        ]);

        $seen = event_seen_repository::get('\local_coursestate\event\course_state_updated');
        $keys = json_decode($seen->payloadkeys, true);

        $this->assertContains('courseid', $keys);
        $this->assertContains('relateduserid', $keys);
        $this->assertContains('other.state', $keys);
        $this->assertContains('other.previous_state', $keys);
    }

    /**
     * The same event seen twice with different keys keeps both: a field that
     * only turns up sometimes is still a field somebody may build a rule on.
     */
    public function test_keys_seen_on_different_occasions_are_kept_together(): void {
        $this->resetAfterTest();

        event_seen_repository::record('\core\event\course_updated', 'core', [
            'courseid' => 1,
            'other' => ['shortname' => 'A'],
        ]);
        event_seen_repository::record('\core\event\course_updated', 'core', [
            'courseid' => 1,
            'other' => ['fullname' => 'B'],
        ]);

        $seen = event_seen_repository::get('\core\event\course_updated');
        $keys = json_decode($seen->payloadkeys, true);

        $this->assertContains('other.shortname', $keys);
        $this->assertContains('other.fullname', $keys);
        $this->assertSame(2, (int) $seen->seencount);
    }

    /**
     * The sample is a shape, not a person. This is the test that matters: the
     * catalogue is site-wide, anybody who can open it sees it, and nobody can
     * be erased from it — so nothing that identifies a person goes in, however
     * deep in the payload it is hiding.
     */
    public function test_the_sample_keeps_no_one_identifiable(): void {
        $this->resetAfterTest();

        event_seen_repository::record('\local_coursestate\event\course_state_updated', 'local_coursestate', [
            'courseid' => 7,
            'relateduserid' => 42,
            'other' => [
                'state' => 'OPEN_STARTED',
                'user_idnumber' => '12345678Z',
                'course_idnumber' => 'MAT-101',
            ],
        ]);

        $sample = json_decode(
            event_seen_repository::get('\local_coursestate\event\course_state_updated')->sample,
            true
        );
        $flattened = json_encode($sample);

        $this->assertStringNotContainsString('12345678Z', $flattened, 'A person got into the sample.');
        $this->assertStringNotContainsString('42', $flattened);
        $this->assertSame('(integer)', $sample['relateduserid']);
        $this->assertSame('(string)', $sample['other']['user_idnumber']);
        $this->assertSame(
            'OPEN_STARTED',
            $sample['other']['state'],
            'What is not about a person is kept, or the sample would be useless.'
        );
    }

    /**
     * A long string is kept as its type: a sample is there to show the shape,
     * not to copy content into a second place.
     */
    public function test_a_long_value_is_kept_as_its_type(): void {
        $this->resetAfterTest();

        event_seen_repository::record('\core\event\course_viewed', 'core', [
            'other' => ['summary' => str_repeat('a', 200)],
        ]);

        $sample = json_decode(event_seen_repository::get('\core\event\course_viewed')->sample, true);

        $this->assertSame('(string)', $sample['other']['summary']);
    }
}
