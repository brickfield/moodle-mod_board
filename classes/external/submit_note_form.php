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
use mod_board\board;
use mod_board\note_form;
use moodle_exception;
use mod_board\local\note;

/**
 * Submit note form - create or update.
 *
 * @package    mod_board
 * @copyright  2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class submit_note_form extends external_api {
    /**
     * Describes the external function arguments.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create note form, json encoded string'),
            ]
        );
    }

    /**
     * Execute function.
     *
     * @param int $contextid
     * @param string $jsonformdata
     * @return array
     */
    public static function execute(int $contextid, string $jsonformdata): array {
        global $USER, $DB;

        [
            'contextid' => $contextid,
            'jsonformdata' => $jsonformdata,
        ] = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'jsonformdata' => $jsonformdata,
        ]);

        // Check the context.
        $context = \context::instance_by_id($contextid);
        self::validate_context($context);
        require_capability('mod/board:view', $context);
        require_capability('mod/board:post', $context);

        // Extract data out of the form content.
        $serialiseddata = json_decode($jsonformdata);
        $data = [];
        parse_str($serialiseddata, $data);
        $data = str_replace(["\r", "\n"], '', $data);

        // Make the form with the ajax data to validate.
        $form = new note_form(null, null, 'post', '', null, true, $data);
        $data = $form->get_data();
        if ($data) {
            // Check that the passed context, and the context with this note/column match.
            $column = board::get_column($data->columnid, MUST_EXIST);
            $board = board::get_board($column->boardid, MUST_EXIST);
            $colcontext = board::context_for_board($board);
            if ($context->id !== $colcontext->id) {
                throw new moodle_exception('formcontextmismatch');
            }

            // Extract the attachment data.
            $attachment = [
                'type' => $data->mediatype,
                'info' => '',
                'url' => '',
            ];
            switch ($data->mediatype) {
                case 1:
                    $attachment['info'] = $data->youtubetitle ?? '';
                    $attachment['url'] = $data->youtubeurl ?? '';
                    break;
                case 2:
                    if (!empty($data->imagefile)) {
                        $attachment['info'] = $data->imagetitle ?? '';
                        $attachment['url'] = $data->filepicker ?? '';
                        $attachment['draftitemid'] = $data->imagefile;
                    }
                    break;
                case 3:
                    $attachment['info'] = $data->linktitle ?? '';
                    $attachment['url'] = $data->linkurl ?? '';
                    break;
            }
            // Check if heading and content and attachment are empty.
            if (empty($data->heading) && empty($data->content) && empty($data->imagefile) && empty($attachment['url'])) {
                $result = [
                    'status' => false,
                    'action' => 'none',
                    'note' => [
                        'id' => 0,
                        'userid' => 0,
                        'heading' => '',
                        'content' => '',
                        'type' => 0,
                        'info' => '',
                        'url' => '',
                        'timecreated' => 0,
                        'rating' => 0,
                    ],
                    'historyid' => 0,
                ];
                return $result;
            }

            // Process either as an update or insert.
            if ($data->noteid) {
                $note = board::get_note($data->noteid, MUST_EXIST);
                if (!$note || $note->columnid != $column->id) {
                    throw new moodle_exception('formsubmissioninvalid');
                }
                if ($USER->id != $note->userid) {
                    require_capability('mod/board:manageboard', $context);
                }
                if (!empty($note->groupid)) {
                    board::require_access_for_group($board, $note->groupid);
                }
                if (board::board_readonly($board, $note->groupid)) {
                    throw new \Exception('board_update_note not available');
                }
                $result = note::update($data->noteid, $data->heading, $data->content, $attachment);
                $result['action'] = 'update';

            } else {
                if ($board->singleusermode != board::SINGLEUSER_DISABLED) {
                    // Groups are not used in single-user-mode apart from user selection.
                    $data->groupid = null;
                } else {
                    $cm = board::coursemodule_for_board($board);
                    $groupmode = groups_get_activity_groupmode($cm);
                    if ($groupmode == NOGROUPS) {
                        $data->groupid = null;
                    } else {
                        if ($data->groupid) {
                            board::require_access_for_group($board, $data->groupid);
                        } else {
                            // Only managers can post in "All groups".
                            require_capability('mod/board:manageboard', $context);
                        }
                    }
                }

                if (board::board_readonly($board, $data->groupid)) {
                    throw new \Exception('board_add_note not available');
                }

                if ($board->singleusermode == board::SINGLEUSER_DISABLED) {
                    if ($data->ownerid) {
                        debugging('ownerid should be used only in single-user modes', DEBUG_DEVELOPER);
                        if ($data->ownerid != $USER->id) {
                            throw new \Exception('board_add_note not available');
                        }
                    }
                    $data->ownerid = $USER->id;
                } else {
                    if (!$data->ownerid) {
                        debugging('ownerid is required in single-user modes', DEBUG_DEVELOPER);
                        $data->ownerid = $USER->id;
                    }
                }

                if (!board::can_post($board, $data->ownerid)) {
                    throw new \Exception('board_add_note not available');
                }

                $result = note::create(
                    $data->columnid, $data->ownerid, $data->groupid, $data->heading, $data->content, $attachment);
                $result['action'] = 'insert';
            }

            return $result;
        } else {
            throw new moodle_exception('formsubmissioninvalid');
        }
    }

    /**
     * Describes the external function result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The status'),
            'action' => new external_value(PARAM_TEXT, 'The action that was performed'),
            'note' => new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'post id'),
                    'userid' => new external_value(PARAM_INT, 'user id'),
                    'heading' => new external_value(PARAM_RAW, 'post heading'),
                    'content' => new external_value(PARAM_RAW, 'post content'),
                    'type' => new external_value(PARAM_INT, 'type'),
                    'info' => new external_value(PARAM_TEXT, 'info'),
                    'url' => new external_value(PARAM_TEXT, 'url'),
                    'timecreated' => new external_value(PARAM_INT, 'timecreated'),
                    'rating' => new external_value(PARAM_INT, 'rating', VALUE_OPTIONAL),
                ]
            ),
            'historyid' => new external_value(PARAM_INT, 'The last history id'),
        ]);
    }
}
