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
 * Upgrades between versions of this plugin.
 *
 * @package    tool_flowboard
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @copyright  2026 Didactika.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Brings an installed copy of this plugin up to the current version.
 *
 * @param int $oldversion The version currently installed.
 * @return bool True once it is up to date.
 */
function xmldb_tool_flowboard_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026091401) {
        // The canvas's own autosave: what is drawn but not yet published.
        // Until this version a flow had nowhere to keep unpublished work at
        // all — editing meant publishing there and then.
        $table = new xmldb_table('tool_flowboard_flow');
        $field = new xmldb_field('draftgraph', XMLDB_TYPE_TEXT, null, null, null, null, null, 'currentversionid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091401, 'tool', 'flowboard');
    }

    if ($oldversion < 2026091402) {
        // MariaDB/MySQL's default collation folds punctuation and case as
        // "variable weight" for uniqueness, so idnumbers like 'welcome!' and
        // 'welcome?' collide in the unique index even though they are
        // different bytes; Postgres already compares exact. Making the
        // column itself case- and punctuation-sensitive on the mysql family
        // matches what Postgres already does, so a site does not lose the
        // ability to tell two idnumbers apart depending on which database it
        // happens to run.
        if ($DB->get_dbfamily() === 'mysql') {
            $DB->execute("ALTER TABLE {tool_flowboard_flow}
                MODIFY idnumber VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");
        }

        upgrade_plugin_savepoint(true, 2026091402, 'tool', 'flowboard');
    }

    return true;
}
