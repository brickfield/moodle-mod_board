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
 * Downloads a user's board submissions.
 * @package     mod_board
 * @author      Karen Holland <karen@brickfieldlabs.ie>
 * @copyright   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use mod_board\board;

$id      = optional_param('id', 0, PARAM_INT); // Course Module ID.

if (!$cm = get_coursemodule_from_id('board', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}
$board = $DB->get_record('board', array('id' => $cm->instance), '*', MUST_EXIST);

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/board:manageboard', $context);

header('Content-Type: text/csv;charset=utf-8');
header("Content-disposition: attachment; filename=\"" . strip_tags($board->name).'_userposts_'.date('YmdHis').'.csv' . "\"");
header("Pragma: no-cache");
header("Expires: 0");

$fp = fopen('php://output', 'w');
$boarddata = board::board_get($board->id);

$users = [];

fputcsv($fp, [get_string('export_firstname', 'mod_board'), get_string('export_lastname', 'mod_board'),
get_string('export_email', 'mod_board'), get_string('export_heading', 'mod_board'),
get_string('export_content', 'mod_board'), get_string('export_info', 'mod_board'),
get_string('export_url', 'mod_board'), get_string('export_timecreated', 'mod_board')]);

foreach ($boarddata as $columnid => $column) {
    foreach ($column->notes as $noteid => $note) {
        if (!isset($users[$note->userid])) {
            $users[$note->userid] = $DB->get_record('user', array('id' => $note->userid));
        }
        $user = $users[$note->userid];
        fputcsv($fp, [$user->firstname, $user->lastname, $user->email, $note->heading, board::get_export_submission($note->content),
        $note->info, $note->url, $note->timecreated ? userdate($note->timecreated) : null]);
    }
}

fclose($fp);
exit();
