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

use tool_flowboard\local\flow\graph_repository;

/**
 * What a node answered: which way the run leaves it, and what to write down.
 *
 * A node never decides what happens next — it says which of its own ways out
 * was taken, and the engine follows the edges drawn from that port. That is
 * what keeps a node ignorant of the flow it is part of, and what makes the
 * drawing, rather than the code, the thing that decides the order.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class node_result {
    /** @var string The way out taken. */
    private string $port;

    /** @var array What the node did, in its own words, for the history. */
    private array $summary;

    /** @var bool Whether the run should stop here rather than follow an edge. */
    private bool $stop;

    /**
     * Use the named constructors; they say what they mean at the call site.
     *
     * @param string $port
     * @param array $summary
     * @param bool $stop
     */
    private function __construct(string $port, array $summary, bool $stop) {
        $this->port = $port;
        $this->summary = $summary;
        $this->stop = $stop;
    }

    /**
     * The node did its job and the run carries on the only way it can.
     *
     * @param array $summary
     * @return self
     */
    public static function went_on(array $summary = []): self {
        return new self(graph_repository::PORT_OUT, $summary, false);
    }

    /**
     * A question was asked and answered, and the run leaves by the matching
     * way out.
     *
     * @param bool $answer
     * @param array $summary
     * @return self
     */
    public static function answered(bool $answer, array $summary = []): self {
        return new self(
            $answer ? graph_repository::PORT_TRUE : graph_repository::PORT_FALSE,
            $summary,
            false
        );
    }

    /**
     * There is nothing more to do, and that is not a failure: a condition said
     * no, or an action found nothing to act on.
     *
     * @param array $summary
     * @return self
     */
    public static function stopped(array $summary = []): self {
        return new self(graph_repository::PORT_OUT, $summary, true);
    }

    /**
     * Which way out was taken.
     *
     * @return string
     */
    public function port(): string {
        return $this->port;
    }

    /**
     * What to write in the history.
     *
     * @return array
     */
    public function summary(): array {
        return $this->summary;
    }

    /**
     * Whether the run ends here.
     *
     * @return bool
     */
    public function stops(): bool {
        return $this->stop;
    }
}
