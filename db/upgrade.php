<?php

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
    
    return true;
}