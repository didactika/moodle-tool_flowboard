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

use mod_forum\subscriptions;
use tool_flowboard\local\flow\flow_context;
use tool_flowboard\local\matching\forum_matcher;
use tool_flowboard\local\matching\pattern_matcher;
use tool_flowboard\local\run\reference_resolver;

/**
 * Unsubscribes the run's subject from the forums of a course that match a
 * pattern.
 *
 * The mirror of {@see action_forum_subscribe}, and it shares the same
 * capability for the same reason: a forum set to force-subscribe would
 * otherwise put a student straight back on the next cron run, and only
 * `mod/forum:managesubscriptions` is allowed to override that — see
 * `subscribe.php`'s own removal path.
 *
 * A forced forum needs a second thought, though. `subscriptions::is_subscribed()`
 * answers true for it regardless of any row in `forum_subscriptions`, as long
 * as the person holds `mod/forum:allowforcesubscribe` there — which almost
 * everyone does by default. So deleting the row and calling that person
 * "unsubscribed" would be recording something that did not actually happen:
 * the forum will still show them as subscribed, and the next email will still
 * reach them. The fix is to ask again after writing, rather than assume the
 * write did what it usually does.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_forum_unsubscribe extends base_node {
    /** @var string How flows refer to this node. */
    public const TYPE = 'action_forum_unsubscribe';

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
        return get_string('node:action_forum_unsubscribe', 'tool_flowboard');
    }

    /**
     * Whether this node changes anything.
     *
     * @return bool
     */
    public static function acts(): bool {
        return true;
    }

    /**
     * What a flow using this node has to be allowed to do.
     *
     * @param array $config
     * @return string[]
     */
    public static function required_capabilities(array $config): array {
        return ['mod/forum:managesubscriptions'];
    }

    /**
     * Which forums to unsubscribe from, matched by name or idnumber against a
     * pattern — the pattern may itself be mapped from an earlier field.
     *
     * @return array
     */
    public static function config_schema(): array {
        return [
            ['key' => 'match', 'label' => 'flow:matchfield', 'type' => 'select', 'required' => true, 'options' => [
                forum_matcher::FIELD_NAME => 'flow:matchfield_name',
                forum_matcher::FIELD_IDNUMBER => 'flow:matchfield_idnumber',
            ]],
            ['key' => 'operator', 'label' => 'flow:matchoperator', 'type' => 'select', 'required' => true, 'options' => [
                pattern_matcher::OPERATOR_EXACT => 'flow:operator_exact',
                pattern_matcher::OPERATOR_CONTAINS => 'flow:operator_contains',
                pattern_matcher::OPERATOR_STARTSWITH => 'flow:operator_startswith',
                pattern_matcher::OPERATOR_REGEX => 'flow:operator_regex',
            ]],
            ['key' => 'pattern', 'label' => 'flow:pattern', 'type' => 'text', 'referenceable' => true, 'required' => true],
        ];
    }

    /**
     * A real match field, a real operator, and a pattern that operator can
     * actually use.
     *
     * @param array $config
     * @return array<string, string>
     */
    public static function validate_config(array $config): array {
        $errors = [];
        $match = (string) ($config['match'] ?? '');
        $operator = (string) ($config['operator'] ?? '');
        $pattern = $config['pattern'] ?? '';

        if (!in_array($match, [forum_matcher::FIELD_NAME, forum_matcher::FIELD_IDNUMBER], true)) {
            $errors['match'] = get_string('flow:error_required', 'tool_flowboard');
        }

        if (!in_array($operator, pattern_matcher::operators(), true)) {
            $errors['operator'] = get_string('flow:error_required', 'tool_flowboard');
        }

        if (reference_resolver::is_reference($pattern)) {
            return $errors;
        }

        $pattern = trim((string) $pattern);

        if ($pattern === '') {
            $errors['pattern'] = get_string('flow:error_required', 'tool_flowboard');
        } else if (!pattern_matcher::is_valid_pattern($operator, $pattern)) {
            $errors['pattern'] = $operator === pattern_matcher::OPERATOR_REGEX
                ? get_string('flow:error_invalidregex', 'tool_flowboard')
                : get_string('flow:error_invalidpattern', 'tool_flowboard');
        }

        return $errors;
    }

    /**
     * Finds the matching forums and unsubscribes the subject from each.
     *
     * @param flow_context $context
     * @param array $config match, operator, pattern.
     * @return node_result
     */
    public function run(flow_context $context, array $config): node_result {
        $userid = $context->subject();

        if ($userid === 0) {
            return node_result::stopped(['reason' => 'nosubject']);
        }

        $courseid = (int) $context->get('courseid', 0);

        if ($courseid === 0) {
            return node_result::stopped(['reason' => 'nocourse']);
        }

        $field = ($config['match'] ?? forum_matcher::FIELD_NAME) === forum_matcher::FIELD_IDNUMBER
            ? forum_matcher::FIELD_IDNUMBER
            : forum_matcher::FIELD_NAME;
        $operator = (string) ($config['operator'] ?? pattern_matcher::OPERATOR_CONTAINS);
        $pattern = (string) ($config['pattern'] ?? '');

        if (!pattern_matcher::is_valid_pattern($operator, $pattern)) {
            return node_result::stopped(['reason' => 'badpattern']);
        }

        $matches = forum_matcher::matching($courseid, $field, $operator, $pattern);

        if ($matches === []) {
            return node_result::stopped([
                'reason' => 'noforums',
                'match' => $field,
                'pattern' => $pattern,
            ]);
        }

        if ($context->is_dry_run()) {
            return node_result::went_on([
                'dryrun' => true,
                'matched' => array_values(array_column($matches, 'name')),
            ]);
        }

        $forums = forum_matcher::forum_records($matches);
        $unsubscribed = [];
        $wasnt = [];
        $stillforced = [];

        foreach ($matches as $cmid => $match) {
            $forum = $forums[$cmid] ?? null;

            if ($forum === null) {
                continue;
            }

            if (!subscriptions::is_subscribed($userid, $forum)) {
                $wasnt[] = $match->name;

                continue;
            }

            subscriptions::unsubscribe_user($userid, $forum, \context_module::instance($cmid));

            // The forum may force this person back on regardless of what was
            // just removed; ask again rather than assume the write held.
            if (subscriptions::is_subscribed($userid, $forum)) {
                $stillforced[] = $match->name;

                continue;
            }

            $unsubscribed[] = $match->name;
        }

        return node_result::went_on([
            'matched' => count($matches),
            'unsubscribed' => $unsubscribed,
            'wasnt' => $wasnt,
            'stillforced' => $stillforced,
        ]);
    }
}
