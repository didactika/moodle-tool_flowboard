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

namespace tool_flowboard\privacy;

use core_privacy\local\metadata\null_provider;

/**
 * Nothing is stored yet.
 *
 * This is true of the plugin as it stands and will stop being true as soon as
 * it records what its flows have done: a run says which person a flow acted
 * on, which is personal data by any reading. When those tables land this class
 * is replaced by a real provider that exports and deletes them — a null
 * provider left in place after that point would be a false statement, not an
 * omission.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements null_provider {
    /**
     * Why there is nothing to describe.
     *
     * @return string The identifier of a string explaining it.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
