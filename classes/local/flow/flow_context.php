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

/**
 * What a run knows, carried from node to node.
 *
 * The event that set it off never changes; everything else is what the nodes
 * have worked out so far — who the run turned out to be about, what was found
 * to act on. A node reads what it needs and may add to it, which is how a
 * lookup node feeds an action node without either knowing the other exists.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class flow_context {
    /** @var array The event, exactly as Moodle handed it over. */
    private array $event;

    /** @var \stdClass The flow being run. */
    private \stdClass $flow;

    /** @var bool Whether this run only says what it would have done. */
    private bool $dryrun;

    /** @var array Anything the nodes have added. */
    private array $values = [];

    /**
     * Starts the bag off with the one thing that never changes.
     *
     * @param \stdClass $flow
     * @param array $event From `\core\event\base::get_data()`.
     * @param bool $dryrun
     */
    public function __construct(\stdClass $flow, array $event, bool $dryrun = false) {
        $this->flow = $flow;
        $this->event = $event;
        $this->dryrun = $dryrun;
    }

    /**
     * The flow being run.
     *
     * @return \stdClass
     */
    public function flow(): \stdClass {
        return $this->flow;
    }

    /**
     * The event that set the run off.
     *
     * @return array
     */
    public function event(): array {
        return $this->event;
    }

    /**
     * Whether nothing should actually be written.
     *
     * @return bool
     */
    public function is_dry_run(): bool {
        return $this->dryrun;
    }

    /**
     * One field of the event, by the dotted path a flow refers to it by.
     *
     * `relateduserid` reads the event's own field; `other.state` reaches into
     * the free array underneath, which is where most of what a rule wants to
     * ask about actually lives.
     *
     * @param string $path
     * @return mixed Null where the event does not carry it.
     */
    public function event_field(string $path) {
        $value = $this->event;

        foreach (explode('.', $path) as $step) {
            if (!is_array($value) || !array_key_exists($step, $value)) {
                return null;
            }

            $value = $value[$step];
        }

        return $value;
    }

    /**
     * Something a node worked out.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null) {
        return $this->values[$key] ?? $default;
    }

    /**
     * Remembers something for the nodes further down.
     *
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, $value): void {
        $this->values[$key] = $value;
    }

    /**
     * Who the run is about, once something has worked it out.
     *
     * @return int Zero while nothing has.
     */
    public function subject(): int {
        return (int) $this->get('subjectid', 0);
    }

    /**
     * Says who the run is about.
     *
     * @param int $userid
     */
    public function set_subject(int $userid): void {
        $this->set('subjectid', $userid);
    }
}
