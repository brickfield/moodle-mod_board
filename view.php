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
 * Main view file.
 * @package     mod_board
 * @author      Karen Holland <karen@brickfieldlabs.ie>
 * @copyright   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use mod_board\board;

$id      = optional_param('id', 0, PARAM_INT); // Course Module ID.
$b       = optional_param('b', 0, PARAM_INT);  // Board instance ID.

if ($b) {
    if (!$board = $DB->get_record('board', array('id' => $b))) {
        throw new \moodle_exception('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('board', $board->id, $board->course, false, MUST_EXIST);

} else {
    if (!$cm = get_coursemodule_from_id('board', $id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    $board = $DB->get_record('board', array('id' => $cm->instance), '*', MUST_EXIST);
}

// Make sure the board history ID is set.
$board->historyid = $board->historyid ?? 0;

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/board:view', $context);

$pageurl = new moodle_url('/mod/board/view.php', array('id' => $cm->id));
$PAGE->set_url($pageurl);

$config = get_config('mod_board');
$PAGE->requires->js_call_amd('mod_board/main', 'initialize', array('board' => $board, 'options' => array(
    'isEditor' => board::board_is_editor($board->id),
    'usersCanEdit' => board::board_users_can_edit($board->id),
    'userId' => $USER->id,
    'readonly' => board::board_readonly($board->id),
    'columnicon' => $config->new_column_icon,
    'noteicon' => $config->new_note_icon,
    'mediaselection' => $config->media_selection,
    'post_max_length' => $config->post_max_length,
    'history_refresh' => $config->history_refresh,
    'file' => array(
        'extensions' => explode(',', board::ACCEPTED_FILE_EXTENSIONS),
        'size_min' => board::ACCEPTED_FILE_MIN_SIZE,
        'size_max' => board::ACCEPTED_FILE_MAX_SIZE
    ),
    'ratingenabled' => board::board_rating_enabled($board->id),
    'hideheaders' => board::board_hide_headers($board->id),
    'sortby' => $board->sortby,
    'colours' => board::get_column_colours()
), 'contextid' => $context->id));

$PAGE->set_title(format_string($board->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($board);

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($board->name));

if (trim(strip_tags($board->intro))) {
    echo $OUTPUT->box_start('mod_introbox', 'pageintro');
    echo format_module_intro('board', $board, $cm->id);
    echo $OUTPUT->box_end();
}

echo $OUTPUT->box_start('mod_introbox', 'group_menu');
echo groups_print_activity_menu($cm, $pageurl, true);
echo $OUTPUT->box_end();


$extrabackground = '';
if (!empty($board->background_color)) {
    $color = '#' . str_replace('#', '', $board->background_color);
    $extrabackground = "background-color: {$color};";
}
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_board', 'background', 0, '', false);
if (count($files)) {
    $file = reset($files);
    $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
            $file->get_itemid(), $file->get_filepath(), $file->get_filename())->get_path();
    $extrabackground = "background:url({$url}) no-repeat center center; -webkit-background-size: cover;
    -moz-background-size: cover; -o-background-size: cover; background-size: cover;";
}
echo '<div class="mod_board" style="' . $extrabackground . '"></div>';

echo $OUTPUT->footer();
