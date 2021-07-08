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
 * Downloads the entire board.
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
header("Content-disposition: attachment; filename=\"" . strip_tags($board->name).'_boardposts_'.date('YmdHis').'.csv' . "\"");
header("Pragma: no-cache");
header("Expires: 0");

$fp = fopen('php://output', 'w');
$boarddata = board::board_get($board->id);

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
        array_push($line, isset($notes[$noterow]) ? board::get_export_note($notes[$noterow]) : '');
    }
    $noterow++;
    fputcsv($fp, $line);
}

fclose($fp);
exit();
