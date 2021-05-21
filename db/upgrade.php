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

defined('MOODLE_INTERNAL') || die();

function xmldb_board_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2020091504) {
        $table = new xmldb_table('board_history');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('boardid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('action', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('columnid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('noteid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('boardid', XMLDB_KEY_FOREIGN, array('boardid'), 'board', array('id'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('board');
        $field = new xmldb_field('historyid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Board savepoint reached.
        upgrade_mod_savepoint(true, 2020091504, 'board');
    }

    if ($oldversion < 2020092201) {

        // Define field introformat to be dropped from board.
        $table = new xmldb_table('board');
        $field = new xmldb_field('printintro');

        // Conditionally launch drop field introformat.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Changing the default of field columnid on table board_history to 0.
        $table = new xmldb_table('board_history');
        $field = new xmldb_field('columnid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'action');

        // Launch change of default for field columnid.
        $dbman->change_field_default($table, $field);

        $field = new xmldb_field('noteid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'columnid');

        // Launch change of default for field noteid.
        $dbman->change_field_default($table, $field);

        $field = new xmldb_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null, 'userid');

        // Launch change of nullability for field content.
        $dbman->change_field_notnull($table, $field);

        // Board savepoint reached.
        upgrade_mod_savepoint(true, 2020092201, 'board');
    }

    if ($oldversion < 2020101801) {

        // Define field background_color to be added to board.
        $table = new xmldb_table('board');
        $field = new xmldb_field('background_color', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'historyid');

        // Conditionally launch add field background_color.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Board savepoint reached.
        upgrade_mod_savepoint(true, 2020101801, 'board');
    }

    if ($oldversion < 2020111000) {

        // Define field heading to be added to board_notes.
        $table = new xmldb_table('board_notes');
        $field = new xmldb_field('heading', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'content');

        // Conditionally launch add field heading.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field type to be added to board_notes.
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'heading');

        // Conditionally launch add field type.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field info to be added to board_notes.
        $field = new xmldb_field('info', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'type');

        // Conditionally launch add field info.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field url to be added to board_notes.
        $field = new xmldb_field('url', XMLDB_TYPE_CHAR, '200', null, null, null, null, 'info');

        // Conditionally launch add field url.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field columnid to be dropped from board_history.
        $table = new xmldb_table('board_history');
        $field = new xmldb_field('columnid');

        // Conditionally launch drop field columnid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field noteid to be dropped from board_history.
        $field = new xmldb_field('noteid');

        // Conditionally launch drop field noteid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Board savepoint reached.
        upgrade_mod_savepoint(true, 2020111000, 'board');
    }

    if ($oldversion < 2020112300) {

        // Define field groupid to be added to board_notes.
        $table = new xmldb_table('board_notes');
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'userid');

        // Conditionally launch add field groupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field groupid to be added to board_history.
        $table = new xmldb_table('board_history');
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'boardid');

        // Conditionally launch add field groupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Board savepoint reached.
        upgrade_mod_savepoint(true, 2020112300, 'board');
    }

    if ($oldversion < 2020120700) {

        // Define field timecreated to be added to board_notes.
        $table = new xmldb_table('board_notes');
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'url');

        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Board savepoint reached.
        upgrade_mod_savepoint(true, 2020120700, 'board');
    }

    if ($oldversion < 2021030100) {

        // Define field sortorder to be added to board_notes.
        $table = new xmldb_table('board_notes');
        $field = new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timecreated');

        // Conditionally launch add field sortorder.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $columns = $DB->get_records('board_columns');
        foreach ($columns as $columnid => $column) {
            $notes = $DB->get_records('board_notes', array('columnid' => $column->id), 'id', 'id');
            $noteorder = 1;
            foreach ($notes as $noteid => $note) {
                $DB->update_record('board_notes', array('id' => $note->id, 'sortorder' => $noteorder++));
            }
        }

        // Board savepoint reached.
        upgrade_mod_savepoint(true, 2021030100, 'board');
    }

    if ($oldversion < 2021031612) {

        // Define field addrating to be added to board.
        $table = new xmldb_table('board');
        $field = new xmldb_field('addrating', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'background_color');

        // Conditionally launch add field addrating.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field hideheaders to be added to board.
        $field = new xmldb_field('hideheaders', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'addrating');

        // Conditionally launch add field hideheaders.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field sortby to be added to board.
        $field = new xmldb_field('sortby', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'hideheaders');

        // Conditionally launch add field sortby.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field postby to be added to board.
        $field = new xmldb_field('postby', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'sortby');

        // Conditionally launch add field postby.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table board_note_ratings to be created.
        $table = new xmldb_table('board_note_ratings');

        // Adding fields to table board_note_ratings.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('noteid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table board_note_ratings.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_rating_noteid', XMLDB_KEY_FOREIGN, ['noteid'], 'board_notes', ['id']);

        // Adding indexes to table board_note_ratings.
        $table->add_index('uq_note_user', XMLDB_INDEX_UNIQUE, ['noteid', 'userid']);

        // Conditionally launch create table for board_note_ratings.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define field sortorder to be dropped from board_notes.
        $table = new xmldb_table('board_notes');
        $field = new xmldb_field('sortorder');

        // Conditionally launch drop field sortorder.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Board savepoint reached.
        upgrade_mod_savepoint(true, 2021031612, 'board');
    }

    return true;
}