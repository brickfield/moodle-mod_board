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
 * The main lib file.
 * @package     mod_board
 * @author      Karen Holland <karen@brickfieldlabs.ie>
 * @copyright   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use mod_board\board;

/**
 * Specify what the plugin supports.
 * @param string $feature
 * @return bool|null
 */
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
 * @param array $data the data submitted from the reset course.
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

/**
 * Extend navigation.
 * @param object $settings
 * @param object $boardnode
 */
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

/**
 * Handle plugin files.
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return false
 */
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
        require_capability('mod/board:view', $context);
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

/**
 * Returns a fragment, which contains the form for the modal popup note editor.
 *
 * @param array $args
 * @return string
 */
function mod_board_output_fragment_note_form($args) {
    global $DB;

    // Get the arguments and decode them.
    $args = (object)$args;
    $noteid = clean_param(($args->noteid ?? 0), PARAM_INT);
    $columnid = clean_param(($args->columnid ?? 0), PARAM_INT);

    if (empty($columnid)) {
        throw new \coding_exception('invalidformrequest');
    }

    $column = board::get_column($columnid);
    $context = board::context_for_board($column->boardid);

    $formdata = [
        'columnid' => $columnid
    ];

    if ($noteid) {
        // Load data for an existing note.
        $note = $DB->get_record('board_notes', ['id' => $noteid]);
        $itemid = $noteid;

        if (!$note) {
            throw new \coding_exception('notenotfound');
        }

        $formdata['noteid'] = $note->id;
        $formdata['heading'] = $note->heading;
        $formdata['content'] = $note->content;
        $formdata['mediatype'] = $note->type;

        switch ($note->type) {
            case 1:
                $formdata['youtubetitle'] = $note->info;
                $formdata['youtubeurl'] = $note->url;
                break;
            case 2:
                $formdata['imagetitle'] = $note->info;
                break;
            case 3:
                $formdata['linktitle'] = $note->info;
                $formdata['linkurl'] = $note->url;
                break;
        }
    } else {
        $itemid = 0;
    }

    // Set up the filearea.
    $pickerparams = board::get_image_picker_options();
    $draftareaid = null;
    file_prepare_draft_area($draftareaid, $context->id, 'mod_board', 'images', $itemid, $pickerparams);
    $formdata['imagefile'] = $draftareaid;

    // Make the form and setup the data.
    $form = new \mod_board\note_form(null, null, 'post', '', null, true);
    $form->set_data($formdata);

    return $form->render();
}

/**
 * Deletes board note ratings database records where ratings are not
 * attached to any existing notes.
 *
 * @return void
 */
function mod_board_remove_unattached_ratings() {
    global $DB;
    // Getting the ratings.
    $sql = "SELECT r.id, n.id AS noteid
              FROM {board_note_ratings} r
         LEFT JOIN {board_notes} n ON r.noteid = n.id";
    $recordset = $DB->get_recordset_sql($sql);
    // Iterating.
    foreach ($recordset as $record) {
        if (!isset($record->noteid)) {
            // If the noteid wasn't set, delete the record.
            $DB->delete_records('board_note_ratings', ['id' => $record->id]);
        }
    }
    $recordset->close();
}

/**
 * Dynamically change the activity to not show a link if we want to embed it.
 * This is called via a automatic callback if this method exists.
 * @param cm_info $cm
 * @return void
 */
function board_cm_info_dynamic(cm_info $cm) {

    // Look up the board based on the course module.
    $board = board::get_board($cm->instance);

    // If we are embedding the board, turn off the view link.
    if ($board->embed) {
        $cm->set_no_view_link();
    }

}

/**
 * This method overrides the content returned if we want to embed the board.
 * However it only supports returning a cached_cm_info() object, so can require a purge of caches
 * if anything in the content (such as the width and height variables) changes.
 * @param stdClass $cm
 * @return cached_cm_info|void
 * @throws dml_exception
 * @throws moodle_exception
 */
function board_get_coursemodule_info(stdClass $cm) {

    $board = board::get_board($cm->instance);
    if (!$board->embed) {
        return;
    }

    $url = new moodle_url('/mod/board/view.php', [
        'id' => $cm->id,
        'embed' => 1
    ]);

    // Get the height and width to use.
    $width = get_config('mod_board', 'embed_width');
    $height = get_config('mod_board', 'embed_height');

    $info = new cached_cm_info();
    $info->name = $board->name;
    $info->content = '<iframe src="' . $url->out() . '" style="width:' . $width . ';height:' . $height . ';" class="mod_board-iframe"></iframe>';

    return $info;

}