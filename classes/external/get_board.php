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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use mod_board\board;

/**
 * Returns bord data.
 *
 * @package    mod_board
 * @copyright  2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_board extends external_api {
    /**
     * Describes the external function arguments.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The board id', VALUE_REQUIRED),
            'ownerid' => new external_value(PARAM_INT, 'The ownerid', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute function.
     *
     * @param int $id
     * @param int $ownerid
     * @return array
     */
    public static function execute(int $id, int $ownerid = 0): array {
        // Validate received parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'ownerid' => $ownerid,
        ]);

        // Request and permission validation.
        $context = board::context_for_board($params['id']);
        self::validate_context($context);

        return board::board_get($params['id'], $params['ownerid']);
    }

    /**
     * Describes the external function result.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'column id'),
                    'name' => new external_value(PARAM_TEXT, 'column name'),
                    'locked' => new external_value(PARAM_BOOL, 'column locked'),
                    'notes' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'post id'),
                                'userid' => new external_value(PARAM_INT, 'user id'),
                                'heading' => new external_value(PARAM_TEXT, 'post heading'),
                                'content' => new external_value(PARAM_RAW, 'post content'),
                                'type' => new external_value(PARAM_INT, 'type'),
                                'info' => new external_value(PARAM_TEXT, 'info'),
                                'url' => new external_value(PARAM_TEXT, 'url'),
                                'timecreated' => new external_value(PARAM_INT, 'timecreated'),
                                'rating' => new external_value(PARAM_INT, 'rating'),
                                'sortorder' => new external_value(PARAM_INT, 'note sort order'),
                            ]
                        )
                    ),
                ]
            )
        );
    }
}
