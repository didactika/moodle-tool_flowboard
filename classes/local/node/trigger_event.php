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

namespace tool_flowboard\local\node;

use tool_flowboard\local\event\event_catalogue;
use tool_flowboard\local\flow\flow_context;

/**
 * Where a run begins: something happened in Moodle.
 *
 * It also settles who the run is about, and that is not a detail. Moodle is not
 * consistent about it, and it cannot be: `course_started` is the student's own
 * action, so the student is `userid`, while `course_state_updated` is decided
 * by a service on the student's behalf, so the student is `relateduserid` and
 * `userid` is whatever process did it. A flow that assumed either one would be
 * wrong half the time, so the flow says which.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trigger_event extends base_node {
    /** @var string How flows refer to this node. */
    public const TYPE = 'trigger_event';

    /** @var string The person the event is about acted themselves. */
    public const SUBJECT_ACTOR = 'userid';

    /** @var string The event was about somebody else. */
    public const SUBJECT_RELATED = 'relateduserid';

    /**
     * What this kind of node is called, as flows refer to it.
     *
     * @return string
     */
    public static function type(): string {
        return self::TYPE;
    }

    /**
     * What it is called where a person reads it.
     *
     * @return string
     */
    public static function name(): string {
        return get_string('node:trigger_event', 'tool_flowboard');
    }

    /**
     * Whether this node is where a run begins.
     *
     * @return bool
     */
    public static function is_trigger(): bool {
        return true;
    }

    /**
     * The event to listen for, and which of its own fields names who the run
     * is about — any field the event carries, not only the two Moodle itself
     * happens to call `userid`/`relateduserid`.
     *
     * @return array
     */
    public static function config_schema(): array {
        return [
            ['key' => 'eventname', 'label' => 'flow:eventname', 'type' => 'event', 'required' => true],
            ['key' => 'subject', 'label' => 'flow:subject', 'type' => 'eventfield', 'required' => false],
        ];
    }

    /**
     * What every downstream node may read once this one has run.
     *
     * @return string[]
     */
    public static function produces(): array {
        return ['subjectid', 'courseid', 'contextid'];
    }

    /**
     * An event this site can actually fire, named exactly.
     *
     * @param array $config
     * @return array<string, string>
     */
    public static function validate_config(array $config): array {
        $errors = [];
        $eventname = trim((string) ($config['eventname'] ?? ''));

        if ($eventname === '') {
            $errors['eventname'] = get_string('flow:error_required', 'tool_flowboard');
        } else if (!event_catalogue::exists($eventname)) {
            $errors['eventname'] = get_string('flow:error_unknownevent', 'tool_flowboard');
        }

        return $errors;
    }

    /**
     * Takes the event apart into the things the rest of the flow works with.
     *
     * @param flow_context $context
     * @param array $config
     * @return node_result
     */
    public function run(flow_context $context, array $config): node_result {
        $event = $context->event();
        $field = trim((string) ($config['subject'] ?? '')) !== ''
            ? (string) $config['subject']
            : self::SUBJECT_RELATED;

        $subject = (int) ($context->event_field($field) ?? 0);

        if ($subject === 0) {
            // The flow was told to act on somebody the event does not name.
            // Stopping is the honest answer: acting on the other field would
            // be guessing at a person.
            return node_result::stopped([
                'reason' => 'nosubject',
                'expected' => $field,
            ]);
        }

        $context->set_subject($subject);
        $context->set('courseid', (int) ($event['courseid'] ?? 0));
        $context->set('contextid', (int) ($event['contextid'] ?? 0));

        return node_result::went_on([
            'eventname' => $event['eventname'] ?? '',
            'subject' => $field,
        ]);
    }
}
