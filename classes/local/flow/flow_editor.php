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
use tool_flowboard\local\node\node_registry;
use tool_flowboard\local\node\action_forum_subscribe;
use tool_flowboard\local\node\action_forum_unsubscribe;
use tool_flowboard\local\node\condition_payload;
use tool_flowboard\local\node\trigger_event;

/**
 * Turns a form's fields into a graph, and a graph back into a form's fields.
 *
 * The accessible editor phase 1 offers is one fixed shape — a trigger, an
 * optional question about it, and one action — because that is exactly what
 * this plugin's own nodes support so far. A form that could draw any shape at
 * all is the visual canvas, and that is phase 3's job; asking for more here
 * than the node set can back up would be a form that lies about what it can
 * do. Growing the node set (fase 5) grows this at the same pace.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class flow_editor {
    /** @var string The key every trigger node is drawn with, in this fixed shape. */
    private const KEY_TRIGGER = 'trigger';

    /** @var string The key the optional condition node is drawn with. */
    private const KEY_CONDITION = 'condition';

    /** @var string The key the action node is drawn with. */
    private const KEY_ACTION = 'action';

    /** @var string[] The kinds of node this editor's "action" step may be. */
    private const ACTION_TYPES = [
        action_forum_subscribe::TYPE,
        action_forum_unsubscribe::TYPE,
    ];

    /**
     * The kinds of node offered as an action, with their labels.
     *
     * @return array<string, string> Node type => label.
     */
    public static function action_types(): array {
        $options = [];

        foreach (self::ACTION_TYPES as $type) {
            $node = node_registry::get($type);

            if ($node !== null) {
                $options[$type] = $node::name();
            }
        }

        return $options;
    }

    /**
     * Builds a graph from what the form submitted.
     *
     * @param \stdClass $data Validated form data.
     * @return array A graph, ready for {@see graph_repository::publish()}.
     */
    public static function to_graph(\stdClass $data): array {
        $nodes = [[
            'key' => self::KEY_TRIGGER,
            'type' => trigger_event::TYPE,
            'config' => [
                'eventname' => trim($data->eventname),
                'subject' => $data->subject,
            ],
        ]];

        $hascondition = trim($data->conditionfield ?? '') !== '';
        $edges = [];

        if ($hascondition) {
            $nodes[] = [
                'key' => self::KEY_CONDITION,
                'type' => condition_payload::TYPE,
                'config' => [
                    'field' => trim($data->conditionfield),
                    'operator' => $data->conditionoperator,
                    'value' => trim($data->conditionvalue ?? ''),
                ],
            ];
            $edges[] = ['from' => self::KEY_TRIGGER, 'port' => graph_repository::PORT_OUT, 'to' => self::KEY_CONDITION];
            $edges[] = ['from' => self::KEY_CONDITION, 'port' => graph_repository::PORT_TRUE, 'to' => self::KEY_ACTION];
        } else {
            $edges[] = ['from' => self::KEY_TRIGGER, 'port' => graph_repository::PORT_OUT, 'to' => self::KEY_ACTION];
        }

        $nodes[] = [
            'key' => self::KEY_ACTION,
            'type' => $data->actiontype,
            'config' => [
                'match' => $data->matchfield,
                'operator' => $data->matchoperator,
                'pattern' => trim($data->pattern),
            ],
        ];

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Reads a graph back into the fields the form shows, for editing an
     * existing flow.
     *
     * A graph that was not drawn by this editor — imported, or built by a
     * later phase's canvas with a shape this form cannot represent — is
     * reported rather than guessed at: editing it here would silently narrow
     * it down to whatever this form happens to understand.
     *
     * @param array $graph From {@see graph_repository::graph()}.
     * @return array{data: array, representable: bool}
     */
    public static function from_graph(array $graph): array {
        $data = [
            'eventname' => '',
            'subject' => trigger_event::SUBJECT_RELATED,
            'conditionfield' => '',
            'conditionoperator' => 'equals',
            'conditionvalue' => '',
            'actiontype' => action_forum_subscribe::TYPE,
            'matchfield' => forum_matcher::FIELD_NAME,
            'matchoperator' => pattern_matcher::OPERATOR_CONTAINS,
            'pattern' => '',
        ];

        $keys = [];

        foreach ($graph['nodes'] ?? [] as $node) {
            $key = (string) self::field($node, 'key');
            $type = (string) self::field($node, 'type');
            $config = self::field($node, 'config') ?: [];
            $keys[] = $key;

            if ($type === trigger_event::TYPE) {
                $data['eventname'] = (string) ($config['eventname'] ?? '');
                $data['subject'] = (string) ($config['subject'] ?? trigger_event::SUBJECT_RELATED);
            } else if ($type === condition_payload::TYPE) {
                $data['conditionfield'] = (string) ($config['field'] ?? '');
                $data['conditionoperator'] = (string) ($config['operator'] ?? 'equals');
                $data['conditionvalue'] = (string) ($config['value'] ?? '');
            } else if (in_array($type, self::ACTION_TYPES, true)) {
                $data['actiontype'] = $type;
                $data['matchfield'] = (string) ($config['match'] ?? forum_matcher::FIELD_NAME);
                $data['matchoperator'] = (string) ($config['operator'] ?? pattern_matcher::OPERATOR_CONTAINS);
                $data['pattern'] = (string) ($config['pattern'] ?? '');
            }
        }

        sort($keys);
        $expected = $data['conditionfield'] !== ''
            ? [self::KEY_ACTION, self::KEY_CONDITION, self::KEY_TRIGGER]
            : [self::KEY_ACTION, self::KEY_TRIGGER];

        return ['data' => $data, 'representable' => $keys === $expected];
    }

    /**
     * One field of a node, whichever shape it is in.
     *
     * @param \stdClass|array $node
     * @param string $field
     * @return mixed
     */
    private static function field($node, string $field) {
        return is_array($node) ? ($node[$field] ?? null) : ($node->$field ?? null);
    }
}
