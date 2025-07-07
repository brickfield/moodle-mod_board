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
 * Returns bord configuration.
 *
 * @package    mod_board
 * @copyright  2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_configuration extends external_api {
    /**
     * Describes the external function arguments.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The board id', VALUE_REQUIRED),
            'ownerid' => new external_value(PARAM_INT, 'The ownerid in separate user mode', VALUE_REQUIRED),
            'groupid' => new external_value(PARAM_INT, 'The groupid in grpup mode', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute function.
     *
     * @param int $id
     * @param int $ownerid
     * @param int $groupid
     * @return array
     */
    public static function execute(int $id, int $ownerid, int $groupid): array {
        // Validate received parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'ownerid' => $ownerid,
            'groupid' => $groupid,
        ]);

        // Request and permission validation.
        $context = board::context_for_board($params['id']);
        self::validate_context($context);

        $settings = board::get_configuration($params['id'], $params['ownerid'], $params['groupid']);

        $result['settings'] = json_encode($settings);

        $result['warnings'] = [];
        return $result;
    }

    /**
     * Describes the external function result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'settings' => new external_value(PARAM_RAW, 'The board settings'),
                'warnings' => new external_warnings(),
            ]
        );
    }
}
