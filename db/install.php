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

/**
 * What a brand new install of this plugin needs beyond what install.xml can
 * express.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs once, straight after install.xml has created the tables.
 *
 * XMLDB has no attribute for a column's collation, so a fresh install would
 * otherwise inherit whatever the site's default happens to be. On MariaDB/
 * MySQL that default usually folds punctuation and case as "variable weight"
 * for uniqueness, so idnumbers like 'welcome!' and 'welcome?' would collide
 * in the unique index despite being different bytes — Postgres already
 * compares exact. This keeps idnumber case- and punctuation-sensitive on the
 * mysql family too, matching {@see xmldb_tool_flowboard_upgrade()}'s own fix
 * for a site upgrading from before this was noticed.
 */
function xmldb_tool_flowboard_install(): void {
    global $DB;

    if ($DB->get_dbfamily() === 'mysql') {
        $DB->execute("ALTER TABLE {tool_flowboard_flow}
            MODIFY idnumber VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");
    }
}
