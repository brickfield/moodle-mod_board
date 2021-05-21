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
require_once('locallib.php');

$id      = optional_param('id', 0, PARAM_INT); // Course Module ID.

if (!$cm = get_coursemodule_from_id('board', $id)) {
    print_error('invalidcoursemodule');
}
$board = $DB->get_record('board', array('id' => $cm->instance), '*', MUST_EXIST);

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/board:manageboard', $context);

header('Content-Type: text/csv;charset=utf-8');
header("Content-disposition: attachment; filename=\"" . $board->name.'_boardposts_'.date('YmdHis').'.csv' . "\"");
header("Pragma: no-cache");
header("Expires: 0");

$fp = fopen('php://output', 'w');
$boarddata = board_get($board->id);

$maxnotes = 0;
$line = [];
foreach ($boarddata as $index => $column) {
    $countnotes = count($column->notes);
    $maxnotes = $countnotes > $maxnotes ? $countnotes : $maxnotes;

    array_push($line, $column->name);
}
fputcsv($fp, $line);

$noterow = 0;
while ($noterow < $maxnotes) {
    $line = [];
    foreach ($boarddata as $index => $column) {
        $notes = array_values($column->notes);
        array_push($line, isset($notes[$noterow]) ? sanitize_note($notes[$noterow]) : '');
    }
    $noterow++;
    fputcsv($fp, $line);
}

fclose($fp);
exit();

function sanitize_note($note) {
    $breaks = array("<br />", "<br>", "<br/>");

    $rowstring = '';
    if (!empty($note->heading)) {
        $rowstring .= $note->heading;
    }
    if (!empty($note->content)) {
        if (!empty($rowstring)) {
            $rowstring .= "\n";
        }
        $rowstring .= str_ireplace($breaks, "\n", $note->content);
    }
    if (!empty($note->type)) {
        if (!empty($rowstring)) {
            $rowstring .= "\n";
        }
        $rowstring .= (!empty($note->info) ? ($note->info.' ') : '') . $note->url;
    }
    return $rowstring;
}