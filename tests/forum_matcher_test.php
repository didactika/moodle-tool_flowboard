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

use tool_flowboard\local\matching\forum_matcher;
use tool_flowboard\local\matching\pattern_matcher;

/**
 * Finding a course's forums by name or by idnumber.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flowboard\local\matching\forum_matcher
 */
final class forum_matcher_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /**
     * A course with two forums and one page, so matching has something to
     * tell apart.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'name' => 'General discussion',
            'idnumber' => 'FORO-GEN',
        ]);
        $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'name' => 'Announcements',
            'idnumber' => 'FORO-NEWS',
        ]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'name' => 'Not a forum',
        ]);
    }

    /**
     * Matching by name finds the forums and only the forums.
     */
    public function test_matching_by_name(): void {
        $matches = forum_matcher::matching(
            (int) $this->course->id,
            forum_matcher::FIELD_NAME,
            pattern_matcher::OPERATOR_CONTAINS,
            'discussion'
        );

        $this->assertCount(1, $matches);
        $this->assertSame('General discussion', reset($matches)->name);
    }

    /**
     * Matching by idnumber reaches the course module's own field, since a
     * forum has no idnumber of its own.
     */
    public function test_matching_by_idnumber(): void {
        $matches = forum_matcher::matching(
            (int) $this->course->id,
            forum_matcher::FIELD_IDNUMBER,
            pattern_matcher::OPERATOR_STARTSWITH,
            'FORO-'
        );

        $this->assertCount(2, $matches);
    }

    /**
     * A page is not a forum, however its name reads.
     */
    public function test_only_forums_are_returned(): void {
        $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'name' => 'General discussion page',
        ]);

        $matches = forum_matcher::matching(
            (int) $this->course->id,
            forum_matcher::FIELD_NAME,
            pattern_matcher::OPERATOR_CONTAINS,
            'discussion'
        );

        $this->assertCount(1, $matches);
    }

    /**
     * The forum records behind the matches come back in one query, keyed by
     * the course module id so the caller can pair each one straight back up.
     */
    public function test_forum_records_are_keyed_by_cmid(): void {
        $matches = forum_matcher::matching(
            (int) $this->course->id,
            forum_matcher::FIELD_IDNUMBER,
            pattern_matcher::OPERATOR_STARTSWITH,
            'FORO-'
        );

        $records = forum_matcher::forum_records($matches);

        foreach ($matches as $cmid => $match) {
            $this->assertArrayHasKey($cmid, $records);
            $this->assertEquals($match->instance, $records[$cmid]->id);
        }
    }

    /**
     * Nothing to match means nothing to look up: no query is spent fetching
     * forum records for an empty list of matches.
     */
    public function test_no_matches_means_no_records(): void {
        $this->assertSame([], forum_matcher::forum_records([]));
    }
}
