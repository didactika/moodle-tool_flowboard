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

/**
 * The canvas's own mount point, and the toolbar around it.
 *
 * Everything the canvas actually draws comes from its own webservice call
 * once the page has loaded — this class hands over nothing but where things
 * go and where "Publish" sends somebody back to.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor_page implements \renderable, \templatable {
    /** @var int The flow being edited, or 0 for a new one. */
    private int $flowid;

    /**
     * The flow this page hosts the canvas for.
     *
     * @param int $flowid
     */
    public function __construct(int $flowid) {
        $this->flowid = $flowid;
    }

    /**
     * What the template draws.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'flowid' => $this->flowid,
            'listurl' => (new \moodle_url('/admin/tool/flowboard/index.php'))->out(false),
        ];
    }
}
