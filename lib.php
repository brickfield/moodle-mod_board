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
 * @package     mod_board
 * @author      Karen Holland <karen@brickfieldlabs.ie>
 * @copyright   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use mod_board\board;

function board_supports($feature) {
    switch($feature) {
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
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
    return array('view', 'view all');
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

    if (!isset($data->hideheaders)) {
        $data->hideheaders = 0;
    }
    if (empty($data->postbyenabled)) {
        $data->postby = 0;
    }

    // Add 3 default columns.
    $boardid = $DB->insert_record('board', $data);
    if ($boardid) {
        $columnheading = get_string('default_column_heading', 'mod_board');
        $DB->insert_record('board_columns', array('boardid' => $boardid, 'name' => $columnheading));
        $DB->insert_record('board_columns', array('boardid' => $boardid, 'name' => $columnheading));
        $DB->insert_record('board_columns', array('boardid' => $boardid, 'name' => $columnheading));
    }

    // Save background image if set.
    $cmid = $data->coursemodule;
    $context = context_module::instance($cmid);
    if (!empty($data->background_image)) {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_board', 'background');
        file_save_draft_area_files($data->background_image, $context->id, 'mod_board', 'background',
            0, array('subdirs' => 0, 'maxfiles' => 1));
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

    if (!isset($data->hideheaders)) {
        $data->hideheaders = 0;
    }

    if (empty($data->postbyenabled)) {
        $data->postby = 0;
    }

    $data->id = $data->instance;
    $DB->update_record('board', $data);

    // Save background image if set.
    $cmid = $data->coursemodule;
    $context = context_module::instance($cmid);
    if (!empty($data->background_image)) {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_board', 'background');
        file_save_draft_area_files($data->background_image, $context->id, 'mod_board', 'background',
            0, array('subdirs' => 0, 'maxfiles' => 1));
    }

    return true;
}

/**
 * Delete board instance.
 * @param int $id
 * @return bool true
 */
function board_delete_instance($id) {
    global $DB;

    if (!$board = $DB->get_record('board', array('id' => $id))) {
        return false;
    }

    // Remove notes.
    $columns = $DB->get_records('board_columns', array('boardid' => $board->id), '', 'id');
    foreach ($columns as $columnid => $column) {
        $notes = $DB->get_records('board_notes', array('columnid' => $columnid));
        foreach ($notes as $noteid => $note) {
            $DB->delete_records('board_note_ratings', array('noteid' => $noteid));
        }
        $DB->delete_records('board_notes', array('columnid' => $columnid));
    }

    // Remove columns.
    $DB->delete_records('board_columns', array('boardid' => $board->id));

    $DB->delete_records('board', array('id' => $board->id));

    return true;
}

function board_extend_settings_navigation($settings, $boardnode) {
    global $PAGE;

    if (has_capability('mod/board:manageboard', $PAGE->cm->context)) {
        $node = navigation_node::create(get_string('export_board', 'board'),
                new moodle_url('/mod/board/download_board.php', array('id' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, null,
                new pix_icon('i/export', ''));
        $boardnode->add_node($node);

        $node = navigation_node::create(get_string('export_submissions', 'board'),
                new moodle_url('/mod/board/download_submissions.php', array('id' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, null,
                new pix_icon('i/export', ''));
        $boardnode->add_node($node);
    }
}

function mod_board_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if ($filearea === 'images') {
        $note = board::get_note($args[0]);
        if (!$note) {
            return false;
        }
        $column = board::get_column($note->columnid);
        if (!$column) {
            return false;
        }

        board::require_capability_for_board_view($column->boardid);

        $relativepath = implode('/', $args);
        $fullpath = '/' . $context->id . '/mod_board/images/' . $relativepath;

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }

        send_stored_file($file, 0, 0, $forcedownload);
    } else if ($filearea === 'background') {
        require_capability('mod/board:addinstance', $context);
        $relativepath = implode('/', $args);
        $fullpath = '/' . $context->id . '/mod_board/background/' . $relativepath;

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }

        send_stored_file($file, 0, 0, $forcedownload);
    }

    return false;
}
