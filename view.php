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
$group = optional_param('group', 0, PARAM_INT);  // Group ID.
$ownerid = optional_param('ownerid', 0, PARAM_INT);  // Board owner ID.

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
$config = get_config('mod_board');

// Update 'viewed' state if required by completion system.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$pageurl = new moodle_url('/mod/board/view.php', array('id' => $cm->id));
$PAGE->set_url($pageurl);

$PAGE->set_title(format_string($board->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($board);

if ($ownerid && !board::can_view_user($board->id, $ownerid)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('nopermission', 'mod_board'));
    echo $OUTPUT->footer();
    die();
}
if (!$ownerid) {
    $ownerid = $USER->id;
}

$PAGE->requires->js_call_amd('mod_board/main', 'initialize', array('board' => $board, 'options' => array(
    'isEditor' => board::board_is_editor($board->id),
    'usersCanEdit' => board::board_users_can_edit($board->id),
    'userId' => $USER->id,
    'ownerId' => $ownerid,
    'readonly' => (board::board_readonly($board->id) || !board::can_post($board->id, $USER->id, $ownerid)),
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
    'colours' => board::get_column_colours(),
    'enableblanktarget' => $board->enableblanktarget
), 'contextid' => $context->id));

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($board->name));
if ($board->enableblanktarget) {
    echo html_writer::tag('div', get_string('blanktargetenabled', 'mod_board'), ['class' => 'small']);
}

if (trim(strip_tags($board->intro))) {
    echo $OUTPUT->box_start('mod_introbox', 'pageintro');
    echo format_module_intro('board', $board, $cm->id);
    echo $OUTPUT->box_end();
}

echo $OUTPUT->box_start('mod_introbox', 'group_menu');
echo groups_print_activity_menu($cm, $pageurl, true);
echo $OUTPUT->box_end();

if ($board->singleusermode == board::SINGLEUSER_PUBLIC ||
    (has_capability('mod/board:manageboard', $context) && $board->singleusermode == board::SINGLEUSER_PRIVATE)) {
    $users = board::get_users_for_board($board->id, $group);
    if (count($users) == 0) {
        echo $OUTPUT->box_start('mod_introbox', 'pageintro');
        echo $OUTPUT->notification(get_string('nousers', 'mod_board'));
        echo $OUTPUT->box_end();
    } else {
        $select = new single_select($pageurl, 'ownerid', $users, $ownerid);
        $select->label = get_string('selectuser', 'mod_board');
        echo html_writer::tag('div', $OUTPUT->render($select));
    }
}

$extrabackground = '';
if (!empty($board->background_color)) {
    $color = '#' . str_replace('#', '', $board->background_color);
    $extrabackground = "background-color: {$color};";
}

if (($board->singleusermode == board::SINGLEUSER_PUBLIC || $board->singleusermode == board::SINGLEUSER_PRIVATE) &&
    ($ownerid == $USER->id && !is_enrolled(context_course::instance($course->id), $USER->id, '', true))) {

    echo $OUTPUT->box_start('mod_introbox', 'pageintro');
    echo $OUTPUT->notification(get_string('selectuserplease', 'mod_board'));
    echo $OUTPUT->box_end();
} else {

    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_board', 'background', 0, '', false);
    if (count($files)) {
        $file = reset($files);
        $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $file->get_filename())->get_path();
        $extrabackground = "background:url({$url}) no-repeat center center; -webkit-background-size: cover;
        -moz-background-size: cover; -o-background-size: cover; background-size: cover;";
    }
    echo '<div class="mod_board_wrapper class="d-flex">
        <div class="mod_board flex-fill" style="' . $extrabackground . '"></div></div>';
}

echo $OUTPUT->footer();
