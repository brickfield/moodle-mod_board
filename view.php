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

require('../../config.php');

$id      = optional_param('id', 0, PARAM_INT); // Course Module ID
$b       = optional_param('b', 0, PARAM_INT);  // Board instance ID

if ($b) {
    if (!$board = $DB->get_record('board', array('id'=>$b))) {
        print_error('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('board', $board->id, $board->course, false, MUST_EXIST);

} else {
    if (!$cm = get_coursemodule_from_id('board', $id)) {
        print_error('invalidcoursemodule');
    }
    $board = $DB->get_record('board', array('id'=>$cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/board:view', $context);

$PAGE->set_url('/mod/board/view.php', array('id' => $cm->id));

$PAGE->requires->js_call_amd('mod_board/main', 'initialize', array('params' => array('board' => $board, 'editor' => has_capability('mod/board:manageboard', $context), 'id' => $USER->id, 'columnicon' => $CFG->new_column_icon, 'noteicon' => $CFG->new_note_icon)));

$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($board);

echo $OUTPUT->header();

echo '<h1>'.$board->name.'</h1>';

if (trim(strip_tags($board->intro))) {
    echo $OUTPUT->box_start('mod_introbox', 'pageintro');
    echo format_module_intro('board', $board, $cm->id);
    echo $OUTPUT->box_end();
}

$extra_background_color = '';
if (!empty($board->background_color)) {
    $color = '#' . str_replace('#', '', $board->background_color);
    $extra_background_color = "style=\"background-color: {$color}\"";
}

echo '<div class="mod_board" ' . $extra_background_color . '></div>';

echo $OUTPUT->footer();
