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
 * Upgrade functions.
 * @package     mod_board
 * @author      Mike Churchward <mike@brickfieldlabs.ie>
 * @copyright   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The main upgrade function.
 * @param int $oldversion
 * @return bool
 */
function xmldb_board_upgrade(int $oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2021052400) {
        // Board savepoint reached.
        upgrade_mod_savepoint(true, 2021052400, 'board');
    }

    if ($oldversion < 2021052405) {
        // Define field userscanedit to be added to board.
        $table = new xmldb_table('board');
        $field = new xmldb_field('userscanedit', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'postby');

        // Conditionally launch add field userscanedit.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Board savepoint reached.
        upgrade_mod_savepoint(true, 2021052405, 'board');
    }

    if ($oldversion < 2021052406) {

        // Define field sortorder to be added to board_notes.
        $table = new xmldb_table('board_notes');
        $field = new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');

        // Conditionally launch add field sortorder.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Board savepoint reached.
        upgrade_mod_savepoint(true, 2021052406, 'board');
    }

    if ($oldversion < 2021052407) {
        mod_board_remove_unattached_ratings();
        // Board savepoint reached.
        upgrade_mod_savepoint(true, 2021052407, 'board');
    }

    return true;
}
