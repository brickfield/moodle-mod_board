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

namespace mod_board\external;

use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_api;
use core_external\external_single_structure;
use core_external\external_warnings;
use mod_board\board;

/**
 * Delete note comment.
 *
 * @package    mod_board
 * @copyright  2022 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class delete_comment extends external_api {
    /**
     * Describes the external function arguments.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'commentid' => new external_value(PARAM_INT, 'The comment id'),
        ]);
    }

    /**
     * Execute function.
     *
     * @param int $commentid The id of the comment.
     * @return array of results
     */
    public static function execute(int $commentid): array {
        global $DB;

        $warnings = [];
        $arrayparams = [
            'commentid' => $commentid,
        ];
        $params = self::validate_parameters(self::execute_parameters(), $arrayparams);

        $c = $DB->get_record('board_comments', ['id' => $params['commentid']], '*', MUST_EXIST);

        $context = board::can_view_note($c->noteid);
        if (!$context) {
            throw new \invalid_parameter_exception('cannot access note');
        }

        self::validate_context($context);

        $comment = new \mod_board\comment($params);
        if (!$comment->delete()) {
            $warnings[] = [
                'item' => $comment->id,
                'warningcode' => 'errorcommentnotdeleted',
                'message' => 'The comment could not be deleted.',
            ];
        }

        $results = [
            'id' => $comment->id,
            'warnings' => $warnings,
        ];
        return $results;
    }

    /**
     * Describes the external function result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'id' => new external_value(PARAM_INT, 'The comment id.'),
                'warnings' => new external_warnings(),
            ]
        );
    }
}
