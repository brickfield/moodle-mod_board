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

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once('locallib.php');

class mod_board_external extends external_api {
    /* GET HISTORY */
    public static function board_history_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The board id', VALUE_REQUIRED),
            'since' => new external_value(PARAM_INT, 'The last historyid', VALUE_REQUIRED)
        ]);
    }

    public static function board_history($id, $since) {
        return board_history($id, $since);
    }

    public static function board_history_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id'),
                    'boardid' => new external_value(PARAM_INT, 'boardid'),
                    'action' => new external_value(PARAM_TEXT, 'action'),
                    'userid' => new external_value(PARAM_INT, 'userid'),
                    'content' => new external_value(PARAM_RAW, 'content')
                )
            )
        );
    }

    /* GET BOARD */
    public static function get_board_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The board id', VALUE_REQUIRED)
        ]);
    }

    public static function get_board($id) {
        $data = board_get($id);
        foreach ($data as $index => $column) {
            foreach ($column->notes as $nidx => $note) {
                $note->heading = self::sanitize($note->heading);
                $note->content = self::sanitize($note->content);
                $note->info = self::sanitize($note->info);
                $note->url = self::sanitize($note->url);
            }
        }
        return $data;
    }

    public static function get_board_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'column id'),
                    'name' => new external_value(PARAM_TEXT, 'column name'),
                    'notes' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'post id'),
                                'userid' => new external_value(PARAM_INT, 'user id'),
                                'heading' => new external_value(PARAM_RAW, 'post heading'),
                                'content' => new external_value(PARAM_RAW, 'post content'),
                                'type' => new external_value(PARAM_INT, 'type'),
                                'info' => new external_value(PARAM_TEXT, 'info'),
                                'url' => new external_value(PARAM_TEXT, 'url'),
                                'timecreated' => new external_value(PARAM_INT, 'timecreated'),
                                'rating' => new external_value(PARAM_INT, 'rating')
                            )
                        )
                    )
                )
            )
        );
    }

    /* ADD COLUMN */
    public static function add_column_parameters() {
        return new external_function_parameters([
            'boardid' => new external_value(PARAM_INT, 'The board id', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'The column name', VALUE_REQUIRED)
        ]);
    }

    public static function add_column($boardid, $name) {
        return board_add_column($boardid, self::sanitize($name));
    }

    public static function add_column_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The new column id'),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    /* UPDATE COLUMN */
    public static function update_column_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The column id', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'The column name', VALUE_REQUIRED)
        ]);
    }

    public static function update_column($id, $name) {
        return board_update_column($id, self::sanitize($name));
    }

    public static function update_column_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The update status'),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    /* DELETE COLUMN */
    public static function delete_column_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The column id', VALUE_REQUIRED)
        ]);
    }

    public static function delete_column($id) {
        return board_delete_column($id);
    }

    public static function delete_column_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The delete status'),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    /* ADD NOTE */
    public static function add_note_parameters() {
        return new external_function_parameters([
            'columnid' => new external_value(PARAM_INT, 'The column id', VALUE_REQUIRED),
            'heading' => new external_value(PARAM_TEXT, 'The note heading', VALUE_REQUIRED),
            'content' => new external_value(PARAM_RAW, 'The note content', VALUE_REQUIRED),
            'attachment' => new external_single_structure(array(
                'type' => new external_value(PARAM_INT, 'type'),
                'info' => new external_value(PARAM_TEXT, 'info'),
                'url' => new external_value(PARAM_TEXT, 'url'),
                'filename' => new external_value(PARAM_TEXT, 'filename'),
                'filecontents' => new external_value(PARAM_RAW, 'filecontents'),
            ), 'Post Attachment', VALUE_OPTIONAL)
        ]);
    }

    public static function add_note($columnid, $heading, $content, $attachment) {
        return board_add_note($columnid, self::sanitize($heading), self::sanitize($content), array(
            'type' => $attachment['type'],
            'info' => self::sanitize($attachment['info']),
            'url' => self::sanitize($attachment['url']),
            'filename' => self::sanitize($attachment['filename']),
            'filecontents' => $attachment['filecontents']
        ));
    }

    public static function add_note_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The insert status'),
            'note' => new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'post id'),
                    'userid' => new external_value(PARAM_INT, 'user id'),
                    'heading' => new external_value(PARAM_RAW, 'post heading'),
                    'content' => new external_value(PARAM_RAW, 'post content'),
                    'type' => new external_value(PARAM_INT, 'type'),
                    'info' => new external_value(PARAM_TEXT, 'info'),
                    'url' => new external_value(PARAM_TEXT, 'url'),
                    'timecreated' => new external_value(PARAM_INT, 'timecreated'),
                    'rating' => new external_value(PARAM_INT, 'rating')
                )
            ),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    /* UPDATE NOTE */
    public static function update_note_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The note id', VALUE_REQUIRED),
            'heading' => new external_value(PARAM_TEXT, 'The note heading', VALUE_REQUIRED),
            'content' => new external_value(PARAM_RAW, 'The note content', VALUE_REQUIRED),
            'attachment' => new external_single_structure(array(
                'type' => new external_value(PARAM_INT, 'type'),
                'info' => new external_value(PARAM_TEXT, 'info'),
                'url' => new external_value(PARAM_TEXT, 'url'),
                'filename' => new external_value(PARAM_TEXT, 'filename'),
                'filecontents' => new external_value(PARAM_RAW, 'filecontents'),
            ), 'Post Attachment', VALUE_OPTIONAL)
        ]);
    }

    public static function update_note($id, $heading, $content, $attachment) {
        return board_update_note($id, self::sanitize($heading), self::sanitize($content), array(
            'type' => $attachment['type'],
            'info' => self::sanitize($attachment['info']),
            'url' => self::sanitize($attachment['url']),
            'filename' => self::sanitize($attachment['filename']),
            'filecontents' => $attachment['filecontents']
        ));
    }

    public static function update_note_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The update status'),
            'note' => new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'post id'),
                    'userid' => new external_value(PARAM_INT, 'user id'),
                    'heading' => new external_value(PARAM_RAW, 'post heading'),
                    'content' => new external_value(PARAM_RAW, 'post content'),
                    'type' => new external_value(PARAM_INT, 'type'),
                    'info' => new external_value(PARAM_TEXT, 'info'),
                    'url' => new external_value(PARAM_TEXT, 'url'),
                    'timecreated' => new external_value(PARAM_INT, 'timecreated')
                )
            ),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    /* DELETE NOTE */
    public static function delete_note_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The note id', VALUE_REQUIRED)
        ]);
    }

    public static function delete_note($id) {
        return board_delete_note($id);
    }

    public static function delete_note_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The delete status'),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    /* MOVE NOTE */
    public static function move_note_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The note id', VALUE_REQUIRED),
            'columnid' => new external_value(PARAM_INT, 'The new column id', VALUE_REQUIRED)
        ]);
    }

    public static function move_note($id, $columnid) {
        return board_move_note($id, $columnid);
    }

    public static function move_note_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The move status'),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    /* CAN RATE NOTE */
    public static function can_rate_note_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The note id', VALUE_REQUIRED)
        ]);
    }

    public static function can_rate_note($id) {
        return board_can_rate_note($id);
    }

    public static function can_rate_note_returns() {
        return new external_value(PARAM_BOOL, 'Can rate status');
    }

    /* RATE NOTE */
    public static function rate_note_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The note id', VALUE_REQUIRED)
        ]);
    }

    public static function rate_note($id) {
        return board_rate_note($id);
    }

    public static function rate_note_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The rate status'),
            'rating' => new external_value(PARAM_INT, 'The new rating id'),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    private static function sanitize($text) {
        $text = preg_replace('/<br(\s+\/)?>/', "\n", $text);
        $text = str_replace('<', "&lt;", $text);
        $text = str_replace('>', "&gt;", $text);
        $text = str_replace("\n", '<br />', $text);
        return $text;
    }
}
