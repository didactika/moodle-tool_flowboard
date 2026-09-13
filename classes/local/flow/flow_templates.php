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

namespace tool_flowboard\local\flow;

use tool_flowboard\local\matching\forum_matcher;
use tool_flowboard\local\matching\pattern_matcher;
use tool_flowboard\local\node\action_forum_subscribe;
use tool_flowboard\local\node\action_forum_unsubscribe;
use tool_flowboard\local\node\trigger_event;

/**
 * The four rules this plugin was built to answer, ready in one click.
 *
 * Each template fills in the shape of the flow — which event, whose action it
 * is, whether a condition applies — and leaves the one thing only a site can
 * answer blank: the pattern. Filling that in is the only step left to whoever
 * uses the template.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class flow_templates {
    /** @var string A student starting a course. */
    public const R1 = 'r1';

    /** @var string A student's course state changing. */
    public const R2 = 'r2';

    /** @var string Somebody given a role in a course. */
    public const R3 = 'r3';

    /** @var string Somebody's role in a course taken away. */
    public const R4 = 'r4';

    /**
     * Every template, in the order they are offered.
     *
     * @return string[]
     */
    public static function all(): array {
        return [self::R1, self::R2, self::R3, self::R4];
    }

    /**
     * What a template is called and what it is for, for the buttons that
     * offer it.
     *
     * @param string $template One of this class's constants.
     * @return array{name: string, description: string}
     */
    public static function describe(string $template): array {
        return [
            'name' => get_string("template:{$template}", 'tool_flowboard'),
            'description' => get_string("template:{$template}_desc", 'tool_flowboard'),
        ];
    }

    /**
     * The form fields a template fills in.
     *
     * @param string $template One of this class's constants.
     * @return array Data shaped like {@see flow_editor::from_graph()} returns.
     */
    public static function data(string $template): array {
        switch ($template) {
            case self::R1:
                return [
                    'name' => get_string('template:r1', 'tool_flowboard'),
                    'eventname' => '\local_coursestate\event\course_started',
                    'subject' => trigger_event::SUBJECT_ACTOR,
                    'conditionfield' => '',
                    'conditionoperator' => 'equals',
                    'conditionvalue' => '',
                    'actiontype' => action_forum_subscribe::TYPE,
                    'matchfield' => forum_matcher::FIELD_NAME,
                    'matchoperator' => pattern_matcher::OPERATOR_CONTAINS,
                    'pattern' => '',
                ];

            case self::R2:
                return [
                    'name' => get_string('template:r2', 'tool_flowboard'),
                    'eventname' => '\local_coursestate\event\course_state_updated',
                    'subject' => trigger_event::SUBJECT_RELATED,
                    'conditionfield' => 'other.state',
                    'conditionoperator' => 'notin',
                    'conditionvalue' => 'OPEN_STARTED,FINALIZED',
                    'actiontype' => action_forum_unsubscribe::TYPE,
                    'matchfield' => forum_matcher::FIELD_NAME,
                    'matchoperator' => pattern_matcher::OPERATOR_CONTAINS,
                    'pattern' => '',
                ];

            case self::R3:
                return [
                    'name' => get_string('template:r3', 'tool_flowboard'),
                    'eventname' => '\core\event\role_assigned',
                    'subject' => trigger_event::SUBJECT_RELATED,
                    'conditionfield' => '',
                    'conditionoperator' => 'equals',
                    'conditionvalue' => '',
                    'actiontype' => action_forum_subscribe::TYPE,
                    'matchfield' => forum_matcher::FIELD_IDNUMBER,
                    'matchoperator' => pattern_matcher::OPERATOR_CONTAINS,
                    'pattern' => '',
                ];

            case self::R4:
                return [
                    'name' => get_string('template:r4', 'tool_flowboard'),
                    'eventname' => '\core\event\role_unassigned',
                    'subject' => trigger_event::SUBJECT_RELATED,
                    'conditionfield' => '',
                    'conditionoperator' => 'equals',
                    'conditionvalue' => '',
                    'actiontype' => action_forum_unsubscribe::TYPE,
                    'matchfield' => forum_matcher::FIELD_IDNUMBER,
                    'matchoperator' => pattern_matcher::OPERATOR_CONTAINS,
                    'pattern' => '',
                ];

            default:
                throw new \coding_exception('Unknown flow template: ' . $template);
        }
    }
}
