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
require('external.php');

use mod_board\board;

$id      = optional_param('id', 0, PARAM_INT); // Course Module ID.
$ownerid = optional_param('ownerid', 0, PARAM_INT); // The ID of the board owner.
$includedeleted = optional_param('include_deleted', 0, PARAM_INT); // Whether to include deleted comments.
$getcsv = optional_param('get_csv', 0, PARAM_INT); // Whether to get the CSV or request the download type.

if (!$cm = get_coursemodule_from_id('board', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}
$board = $DB->get_record('board', array('id' => $cm->instance), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/board:manageboard', $context);

if ($getcsv) {
    header('Content-Type: text/csv;charset=utf-8');
    header("Content-disposition: attachment; filename=\"" . strip_tags($board->name).'_comments_'.date('YmdHis').'.csv' . "\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $fp = fopen('php://output', 'w');

    board::require_capability_for_board_view($board->id);
    $boarddata = board::board_get($board->id, $ownerid);

    $users = [];

    $heading = [get_string('export_firstname', 'mod_board'), get_string('export_lastname', 'mod_board'),
    get_string('export_email', 'mod_board'), get_string('export_heading', 'mod_board'),
    get_string('export_timecreated', 'mod_board'), get_string('export_firstname', 'mod_board'),
    get_string('export_lastname', 'mod_board'),
    'Note Content', get_string('export_timecreated', 'mod_board')];
    if ($includedeleted) {
        $heading[] = get_string('export_deleted', 'mod_board');
    }
    fputcsv($fp, $heading);

    foreach ($boarddata as $columnid => $column) {
        foreach ($column->notes as $noteid => $note) {
            if (!isset($users[$note->userid])) {
                $users[$note->userid] = $DB->get_record('user', array('id' => $note->userid));
            }
            $user = $users[$note->userid];
            $notestring = board::get_export_note($column->notes[$noteid]);
            // Shorten the note string if it's too long.
            if (strlen($notestring) > 100) {
                $notestring = substr($notestring, 0, 100) . '...';
            }
            $params = ['noteid' => $noteid];
            if (!$includedeleted) {
                $params['deleted'] = 0;
            }
            $commentrecords = $DB->get_records('board_comments', $params, 'timecreated ASC');
            $comments = [];
            foreach ($commentrecords as $cr) {
                $comment = (object)[];
                $comment->content = $cr->content;
                $comment->date = userdate($cr->timecreated);
                if (!isset($users[$cr->userid])) {
                    $users[$cr->userid] = $DB->get_record('user', array('id' => $cr->userid));
                }
                $comment->user = $DB->get_record('user', ['id' => $cr->userid]);
                $comment->deleted = $cr->deleted;
                $comments[] = $comment;
            }
            if (count($comments) > 0) {
                // Add a line about the note.
                // postusername, postuseremail, notestring, note date, comment user, comment date, comment content
                $postdate = $note->timecreated ? userdate($note->timecreated) : null;
                $line = [$users[$note->userid]->firstname, $users[$note->userid]->lastname, $user->email, $notestring, $postdate];
                foreach ($comments as $comment) {
                    $line[] = $comment->user->firstname;
                    $line[] = $comment->user->lastname;
                    $line[] = strip_tags($comment->content);
                    $line[] = $comment->date;
                    if ($includedeleted) {
                        $line[] = $comment->deleted ? 'Deleted' : '';
                    }

                    fputcsv($fp, $line);
                    // Create a new empty array with 5 empty elements.
                    $line = ['', '', '', '', ''];
                }
            }
        }
    }

    fclose($fp);
    exit();
} else {
    // Ask for confirmation to download comments including deleted comments.
    $PAGE->set_url('/mod/board/download_comments.php', ['id' => $id, 'ownerid' => $ownerid]);
    $PAGE->set_title(get_string('export_comments', 'mod_board'));
    $PAGE->set_heading(get_string('export_comments', 'mod_board'));
    $PAGE->set_pagelayout('incourse');

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('export_comments', 'mod_board'));

    echo html_writer::start_div('board-export-comments');
    echo html_writer::start_div('board-export-comments__content');
    echo html_writer::tag('p', get_string('export_comments_description', 'mod_board'));
    echo html_writer::tag('p', get_string('export_comments_include_deleted', 'mod_board'));
    echo html_writer::end_div();
    echo html_writer::start_div('board-export-comments__actions');
    echo html_writer::start_div('button mb-2');
    echo html_writer::link(new moodle_url('/mod/board/download_comments.php',
        ['id' => $id, 'ownerid' => $ownerid, 'include_deleted' => 0, 'get_csv' => 1]),
        get_string('export_comments', 'mod_board'), ['class' => 'btn btn-primary']);
    echo html_writer::end_div();
    echo html_writer::start_div('button mb-2');
    echo html_writer::link(new moodle_url('/mod/board/download_comments.php',
        ['id' => $id, 'ownerid' => $ownerid, 'include_deleted' => 1, 'get_csv' => 1]),
        get_string('export_comments_include_deleted_button', 'mod_board'), ['class' => 'btn btn-primary']);
    echo html_writer::end_div();
    echo html_writer::start_div('button');
    echo html_writer::link(new moodle_url('/mod/board/view.php', ['id' => $id]),
        get_string('export_backtoboard', 'mod_board'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}
