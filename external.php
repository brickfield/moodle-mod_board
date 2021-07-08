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

use mod_board\board;

/**
 * Provides the mod_board external functions.
 * @package     mod_board
 * @author      Karen Holland <karen@brickfieldlabs.ie>
 * @copyright   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_board_external extends external_api {
    /**
     * Function board_history_parameters.
     * @return external_function_parameters
     */
    public static function board_history_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The board id', VALUE_REQUIRED),
            'since' => new external_value(PARAM_INT, 'The last historyid', VALUE_REQUIRED)
        ]);
    }

    /**
     * Function board_history,
     * @param int $id
     * @param int $since
     * @return array
     */
    public static function board_history(int $id, int $since): array {
        // Validate recieved parameters.
        $params = self::validate_parameters(self::board_history_parameters(), [
            'id' => $id,
            'since' => $since,
        ]);

        // Request and permission validation.
        $context = board::context_for_board($params['id']);
        self::validate_context($context);

        return board::board_history($params['id'], $params['since']);
    }

    /**
     * Function board_history_returns.
     * @return external_multiple_structure
     */
    public static function board_history_returns(): external_multiple_structure {
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

    /**
     * Function get_board_parameters.
     * @return external_function_parameters
     */
    public static function get_board_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The board id', VALUE_REQUIRED)
        ]);
    }

    /**
     * Function get_board.
     * @param int $id
     * @return array
     */
    public static function get_board(int $id): array {
        // Validate recieved parameters.
        $params = self::validate_parameters(self::get_board_parameters(), [
            'id' => $id,
        ]);

        // Request and permission validation.
        $context = board::context_for_board($params['id']);
        self::validate_context($context);

        return board::board_get($params['id']);
    }

    /**
     * Function get_board_returns.
     * @return external_multiple_structure
     */
    public static function get_board_returns(): external_multiple_structure {
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
                                'heading' => new external_value(PARAM_TEXT, 'post heading'),
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

    /**
     * Function add_column_parameters.
     * @return external_function_parameters
     */
    public static function add_column_parameters(): external_function_parameters {
        return new external_function_parameters([
            'boardid' => new external_value(PARAM_INT, 'The board id', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'The column name', VALUE_REQUIRED)
        ]);
    }

    /**
     * Function add_column.
     * @param int $boardid
     * @param string $name
     * @return array
     */
    public static function add_column(int $boardid, string $name): array {
        // Validate recieved parameters.
        $params = self::validate_parameters(self::add_column_parameters(), [
            'boardid' => $boardid,
            'name' => $name,
        ]);

        // Request and permission validation.
        $context = board::context_for_board($params['boardid']);
        self::validate_context($context);

        return board::board_add_column($params['boardid'], $params['name']);
    }

    /**
     * Function add_column_returns.
     * @return external_single_structure
     */
    public static function add_column_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The new column id'),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    /**
     * Function update_column_parameters.
     * @return external_function_parameters
     */
    public static function update_column_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The column id', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'The column name', VALUE_REQUIRED)
        ]);
    }

    /**
     * Function update_column.
     * @param int $id
     * @param string $name
     * @return array
     */
    public static function update_column(int $id, string $name): array {
        // Validate recieved parameters.
        $params = self::validate_parameters(self::update_column_parameters(), [
            'id' => $id,
            'name' => $name,
        ]);

        // Request and permission validation.
        $column = board::get_column($params['id']);
        $context = board::context_for_board($column->boardid);
        self::validate_context($context);

        return board::board_update_column($params['id'], $params['name']);
    }

    /**
     * Function update_column_returns.
     * @return external_single_structure
     */
    public static function update_column_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The update status'),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    /**
     * Function delete_column_parameters.
     * @return external_function_parameters
     */
    public static function delete_column_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The column id', VALUE_REQUIRED)
        ]);
    }

    /**
     * Function delete_column.
     * @param int $id
     * @return array
     */
    public static function delete_column(int $id): array {
        // Validate recieved parameters.
        $params = self::validate_parameters(self::delete_column_parameters(), [
            'id' => $id,
        ]);

        // Request and permission validation.
        $column = board::get_column($params['id']);
        $context = board::context_for_board($column->boardid);
        self::validate_context($context);

        return board::board_delete_column($params['id']);
    }

    /**
     * Function delete_column_returns.
     * @return external_single_structure
     */
    public static function delete_column_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The delete status'),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    /**
     * Function add_note_parameters.
     * @return external_function_parameters
     */
    public static function add_note_parameters(): external_function_parameters {
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

    /**
     * Function add_note.
     * @param int $columnid
     * @param string $heading
     * @param string $content
     * @param array $attachment
     * @return array
     */
    public static function add_note(int $columnid, string $heading, string $content, array $attachment): array {
        // Validate recieved parameters.
        $params = self::validate_parameters(self::add_note_parameters(), [
            'columnid' => $columnid,
            'heading' => $heading,
            'content' => $content,
            'attachment' => $attachment
        ]);

        // Request and permission validation.
        $column = board::get_column($params['columnid']);
        $context = board::context_for_board($column->boardid);
        self::validate_context($context);

        return board::board_add_note($params['columnid'], $params['heading'], $params['content'], $params['attachment']);
    }

    /**
     * Function add_note_returns.
     * @return external_single_structure
     */
    public static function add_note_returns(): external_single_structure {
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

    /**
     * Function update_note_parameters.
     * @return external_function_parameters
     */
    public static function update_note_parameters(): external_function_parameters {
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

    /**
     * Function update_note.
     * @param int $id
     * @param string $heading
     * @param string $content
     * @param array $attachment
     * @return array
     */
    public static function update_note(int $id, string $heading, string $content, array $attachment): array {
        // Validate recieved parameters.
        $params = self::validate_parameters(self::update_note_parameters(), [
            'id' => $id,
            'heading' => $heading,
            'content' => $content,
            'attachment' => $attachment
        ]);

        // Request and permission validation.
        $note = board::get_note($params['id']);
        $column = board::get_column($note->columnid);
        $context = board::context_for_board($column->boardid);
        self::validate_context($context);

        return board::board_update_note($params['id'], $params['heading'], $params['content'], $params['attachment']);
    }

    /**
     * Function update_note_returns.
     * @return external_single_structure
     */
    public static function update_note_returns(): external_single_structure {
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

    /**
     * Function delete_note_parameters.
     * @return external_function_parameters
     */
    public static function delete_note_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The note id', VALUE_REQUIRED)
        ]);
    }

    /**
     * Function delete_note.
     * @param int $id
     * @return array
     */
    public static function delete_note(int $id): array {
        // Validate recieved parameters.
        $params = self::validate_parameters(self::delete_note_parameters(), [
            'id' => $id,
        ]);

        // Request and permission validation.
        $note = board::get_note($params['id']);
        $column = board::get_column($note->columnid);
        $context = board::context_for_board($column->boardid);
        self::validate_context($context);

        return board::board_delete_note($params['id']);
    }

    /**
     * Function delete_note_returns.
     * @return external_single_structure
     */
    public static function delete_note_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The delete status'),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    /**
     * Function move_note_parameters.
     * @return external_function_parameters
     */
    public static function move_note_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The note id', VALUE_REQUIRED),
            'columnid' => new external_value(PARAM_INT, 'The new column id', VALUE_REQUIRED)
        ]);
    }

    /**
     * Function move_note.
     * @param int $id
     * @param int $columnid
     * @return array
     */
    public static function move_note(int $id, int $columnid): array {
        // Validate recieved parameters.
        $params = self::validate_parameters(self::move_note_parameters(), [
            'id' => $id,
            'columnid' => $columnid,
        ]);

        // Request and permission validation.
        $column = board::get_column($params['columnid']);
        $context = board::context_for_board($column->boardid);
        self::validate_context($context);

        return board::board_move_note($params['id'], $params['columnid']);
    }

    /**
     * Function move_note_returns.
     * @return external_single_structure
     */
    public static function move_note_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The move status'),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }

    /**
     * Function can_rate_note_parameters.
     * @return external_function_parameters
     */
    public static function can_rate_note_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The note id', VALUE_REQUIRED)
        ]);
    }

    /**
     * Function can_rate_note.
     * @param int $id
     * @return bool
     */
    public static function can_rate_note(int $id): bool {
        // Validate recieved parameters.
        $params = self::validate_parameters(self::can_rate_note_parameters(), [
            'id' => $id,
        ]);

        // Request and permission validation.
        $note = board::get_note($params['id']);
        $column = board::get_column($note->columnid);
        $context = board::context_for_board($column->boardid);
        self::validate_context($context);

        return board::board_can_rate_note($params['id']);
    }

    /**
     * Function can_rate_note_returns.
     * @return external_value
     */
    public static function can_rate_note_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Can rate status');
    }

    /**
     * Function rate_note_parameters.
     * @return external_function_parameters
     */
    public static function rate_note_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The note id', VALUE_REQUIRED)
        ]);
    }

    /**
     * Function rate_note.
     * @param int $id
     * @return array
     */
    public static function rate_note(int $id): array {
        // Validate recieved parameters.
        $params = self::validate_parameters(self::rate_note_parameters(), [
            'id' => $id,
        ]);

        // Request and permission validation.
        $note = board::get_note($params['id']);
        $column = board::get_column($note->columnid);
        $context = board::context_for_board($column->boardid);
        self::validate_context($context);

        return board::board_rate_note($params['id']);
    }

    /**
     * Function rate_note_returns.
     * @return external_single_structure
     */
    public static function rate_note_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The rate status'),
            'rating' => new external_value(PARAM_INT, 'The new rating id'),
            'historyid' => new external_value(PARAM_INT, 'The last history id')
        ]);
    }
}
