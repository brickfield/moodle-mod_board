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

defined('MOODLE_INTERNAL') || die;

function board_supports($feature) {
    switch($feature) {
        case FEATURE_SHOW_DESCRIPTION:        return true;
        default: return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function board_reset_userdata($data) {

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.

    return array();
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function board_get_view_actions() {
    return array('view','view all');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function board_get_post_actions() {
    return array('update', 'add');
}

/**
 * Add board instance.
 * @param stdClass $data
 * @param mod_board_mod_form $mform
 * @return int new board instance id
 */
function board_add_instance($data, $mform = null) {
    global $DB;
    
    $boardid = $DB->insert_record('board', $data);
    if ($boardid) {
        $column_heading = get_string('default_column_heading', 'mod_board');
        $DB->insert_record('board_columns', array('boardid' => $boardid, 'name' => $column_heading));
        $DB->insert_record('board_columns', array('boardid' => $boardid, 'name' => $column_heading));
        $DB->insert_record('board_columns', array('boardid' => $boardid, 'name' => $column_heading));
    }
    
    return $boardid;
}

/**
 * Update board instance.
 * @param object $data
 * @param object $mform
 * @return bool true
 */
function board_update_instance($data, $mform) {
    global $DB;
    
    $data->id = $data->instance;
    $DB->update_record('board', $data);
    
    return true;
}

/**
 * Delete board instance.
 * @param int $id
 * @return bool true
 */
function board_delete_instance($id) {
    global $DB;

    if (!$board = $DB->get_record('board', array('id'=>$id))) {
        return false;
    }

    // remove notes
    $columns = $DB->get_records('board_columns', array('boardid' => $board->id), '', 'id');
    foreach($columns AS $columnid => $column) {
        $DB->delete_records('board_notes', array('columnid'=>$columnid));
    }
    
    //remove columns
    $DB->delete_records('board_columns', array('boardid'=>$board->id));
    
    $DB->delete_records('board', array('id'=>$board->id));

    return true;
}

function board_extend_settings_navigation($settings, $boardnode) {
    global $PAGE;

    if (has_capability('mod/board:manageboard', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/board/download.php',
                array('id'=>$PAGE->cm->id));
        $node = navigation_node::create(get_string('export_csv', 'board'), $url,
                navigation_node::TYPE_SETTING, null, null,
                new pix_icon('i/export', ''));
        $boardnode->add_node($node);
    }
}