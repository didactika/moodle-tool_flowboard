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

namespace tool_flowboard\output;

use renderer_base;
use tool_flowboard\local\event\event_catalogue;
use tool_flowboard\local\event\event_seen_repository;

/**
 * The event catalogue, narrowed to what somebody asked to see.
 *
 * Filtering happens here rather than in the browser, and without JavaScript: a
 * site can have well over a thousand events, and a page that ships all of them
 * so a script can hide most is slower to load and harder to use with a
 * keyboard than a form that asks the server.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_catalogue_page implements \renderable, \templatable {
    /** @var array<string, array> Every event on the site. */
    private array $catalogue;

    /** @var string The component asked for, or empty for all of them. */
    private string $component;

    /** @var string Part of a name to look for. */
    private string $search;

    /**
     * Builds the page around a catalogue and what was asked of it.
     *
     * @param array $catalogue From {@see event_catalogue::all()}.
     * @param string $component
     * @param string $search
     */
    public function __construct(array $catalogue, string $component = '', string $search = '') {
        $this->catalogue = $catalogue;
        $this->component = $component;
        $this->search = $search;
    }

    /**
     * What the template draws.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $seen = event_seen_repository::all();
        $rows = [];

        foreach ($this->catalogue as $event) {
            if (!$this->matches($event)) {
                continue;
            }

            $rows[] = $this->row($event, $seen[$event['eventname']] ?? null);
        }

        return [
            'formurl' => (new \moodle_url('/admin/tool/flowboard/events.php'))->out(false),
            'search' => $this->search,
            'components' => $this->component_options(),
            'rows' => $rows,
            'hasrows' => $rows !== [],
            'countlabel' => get_string('events:count', 'tool_flowboard', (object) [
                'shown' => count($rows),
                'total' => count($this->catalogue),
            ]),
            'filtered' => $this->component !== '' || $this->search !== '',
        ];
    }

    /**
     * Whether one event is one of the ones asked for.
     *
     * @param array $event
     * @return bool
     */
    private function matches(array $event): bool {
        if ($this->component !== '' && $event['component'] !== $this->component) {
            return false;
        }

        if ($this->search === '') {
            return true;
        }

        $needle = \core_text::strtolower($this->search);

        foreach ([$event['eventname'], $event['name'], $event['componentname']] as $haystack) {
            if (strpos(\core_text::strtolower($haystack), $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * One row of the table.
     *
     * @param array $event
     * @param \stdClass|null $seen What this event was carrying when it last went past.
     * @return array
     */
    private function row(array $event, ?\stdClass $seen): array {
        $keys = $seen === null ? [] : (json_decode($seen->payloadkeys ?? '', true) ?: []);

        return [
            'eventname' => $event['eventname'],
            'name' => $event['name'],
            'component' => $event['componentname'],
            'crud' => self::crud_label($event['crud']),
            'edulevel' => self::edulevel_label($event['edulevel']),
            'objecttable' => $event['objecttable'] ?? '',
            'since' => $event['since'],
            'seen' => $seen !== null,
            'payloadkeys' => implode(', ', $keys),
            'seencount' => $seen === null ? 0 : (int) $seen->seencount,
        ];
    }

    /**
     * The component picker's options, with the one in use marked.
     *
     * @return array<int, array{value: string, label: string, selected: bool}>
     */
    private function component_options(): array {
        $options = [[
            'value' => '',
            'label' => get_string('events:allcomponents', 'tool_flowboard'),
            'selected' => $this->component === '',
        ]];

        foreach (event_catalogue::components() as $component => $label) {
            $options[] = [
                'value' => $component,
                'label' => $label,
                'selected' => $component === $this->component,
            ];
        }

        return $options;
    }

    /**
     * What an event does to the thing it is about, in words.
     *
     * @param string $crud One of c, r, u, d.
     * @return string
     */
    private static function crud_label(string $crud): string {
        $known = ['c' => 'create', 'r' => 'read', 'u' => 'update', 'd' => 'delete'];

        return isset($known[$crud])
            ? get_string('crud:' . $known[$crud], 'tool_flowboard')
            : $crud;
    }

    /**
     * Whether an event is about teaching, about taking part, or about neither.
     *
     * @param int $edulevel
     * @return string
     */
    private static function edulevel_label(int $edulevel): string {
        switch ($edulevel) {
            case \core\event\base::LEVEL_TEACHING:
                return get_string('edulevel:teaching', 'tool_flowboard');

            case \core\event\base::LEVEL_PARTICIPATING:
                return get_string('edulevel:participating', 'tool_flowboard');

            default:
                return get_string('edulevel:other', 'tool_flowboard');
        }
    }
}
