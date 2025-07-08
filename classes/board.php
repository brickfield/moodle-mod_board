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

namespace mod_board;

/**
 * The main board class functions.
 * @package     mod_board
 * @author      Jay Churchward <jay@brickfieldlabs.ie>
 * @copyright   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class board {

    /** @var int Minumum file size of 100 bytes. */
    const ACCEPTED_FILE_MIN_SIZE = 100;

    /** @var int Maximum file size of 10Mb. */
    const ACCEPTED_FILE_MAX_SIZE = 1024 * 1024 * 10;

    /** @var int Value for the max column name length, consistent with db */
    const LENGTH_COLNAME = 100;

    /** @var int Value for the max heading length, consistent with db */
    const LENGTH_HEADING = 100;

    /** @var int Value for the max info length, consistent with db */
    const LENGTH_INFO = 100;

    /** @var int Value for the max url length, consistent with db */
    const LENGTH_URL = 200;

    /** @var int Value for disabling rating. */
    const RATINGDISABLED = 0;

    /** @var int Value for allowing students to rate posts. */
    const RATINGBYSTUDENTS = 1;

    /** @var int Value for allowing teachers to rate posts */
    const RATINGBYTEACHERS = 2;

    /** @var int Value for allowing all roles to rate posts */
    const RATINGBYALL = 3;

    /** @var int Value for sorting all posts by date */
    const SORTBYDATE = 1;

    /** @var int Value for sorting all posts by rating */
    const SORTBYRATING = 2;

    /** @var int Value for no sorting on posts */
    const SORTBYNONE = 3;

    /** @var int Value for the singlusermode not set*/
    const SINGLEUSER_DISABLED = 0;

    /** @var int Value for the singlusermode setting in private mode*/
    const SINGLEUSER_PRIVATE = 1;

    /** @var int Value for the singleusermode setting in public mode*/
    const SINGLEUSER_PUBLIC = 2;

    /**
     * Retrieves the course module for the board
     *
     * @param object $board
     * @return object
     */
    public static function coursemodule_for_board($board) {
        return get_coursemodule_from_instance('board', $board->id, $board->course, false, MUST_EXIST);
    }

    /**
     * Get the supported filetype extensions
     *
     * @return array of strings of supported file extensions.
     */
    public static function get_accepted_file_extensions() {
        $config = get_config('mod_board');
        if (isset($config->acceptedfiletypeforcontent)) {
            $extensions = explode(',', $config->acceptedfiletypeforcontent);
        } else {
            $extensions = [];
        }
        return $extensions;
    }

    /**
     * Retrieves a record of the selected board.
     *
     * @param int $id
     * @return object
     */
    public static function get_board($id) {
        global $DB;
        return $DB->get_record('board', ['id' => $id]);
    }

    /**
     * Retrieves a record of the selected column.
     *
     * @param int $id
     * @return object
     */
    public static function get_column($id) {
        global $DB;
        return $DB->get_record('board_columns', ['id' => $id]);
    }

    /**
     * Retrieves a record of the selected note.
     *
     * @param int $id
     * @return object
     */
    public static function get_note($id) {
        global $DB;
        return $DB->get_record('board_notes', ['id' => $id, 'deleted' => 0]);
    }

    /**
     * Retrieves the context of the selected board.
     *
     * @param int $id
     * @return \context
     */
    public static function context_for_board($id) {
        if (!$board = static::get_board($id)) {
            return null;
        }

        $cm = static::coursemodule_for_board($board);
        return \context_module::instance($cm->id);
    }

    /**
     * Retrieves the context of the selected column.
     *
     * @param int $id
     * @return \context
     */
    public static function context_for_column($id) {
        if (!$column = static::get_column($id)) {
            return null;
        }

        return static::context_for_board($column->boardid);
    }

    /**
     * Adds a capability check for the columns.
     *
     * @param int $id
     * @return void
     */
    public static function require_capability_for_column($id) {
        $context = static::context_for_column($id);
        if ($context) {
            require_capability('mod/board:manageboard', $context);
        }
    }

    /**
     * Requires the users to be in groups.
     *
     * @param int $groupid
     * @param int $boardid
     * @return void
     */
    public static function require_access_for_group(int $groupid, int $boardid) {
        if (!$groupid) {
            debugging('groupid expected', DEBUG_DEVELOPER);
        }

        $cm = static::coursemodule_for_board(static::get_board($boardid));
        $context = \context_module::instance($cm->id);

        if (has_capability('mod/board:manageboard', $context)) {
            return;
        }

        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == NOGROUPS) {
            return;
        }

        if (!static::can_access_group($groupid, $context)) {
            throw new \Exception('Invalid group');
        }
    }

    /**
     * Can current user view the note?
     *
     * @param int $noteid
     * @return \context|null null means user cannot view the note
     */
    public static function can_view_note(int $noteid): ?\context {
        global $USER;

        $note = static::get_note($noteid);
        if (!$note) {
            return null;
        }
        $column = static::get_column($note->columnid);
        if (!$column) {
            return null;
        }
        $board = static::get_board($column->boardid);
        if (!$board) {
            return null;
        }

        $cm = static::coursemodule_for_board($board);
        $context = \context_module::instance($cm->id);

        if (!has_capability('mod/board:view', $context)) {
            return null;
        }

        if (!has_capability('mod/board:manageboard', $context)) {
            if ($board->singleusermode == static::SINGLEUSER_PRIVATE) {
                if (!$USER->id) {
                    return null;
                }
                if ($note->userid != $USER->id && $note->ownerid != $USER->id) {
                    return null;
                }
            }

            if ($note->groupid) {
                $groupmode = groups_get_activity_groupmode($cm);
                if ($groupmode == SEPARATEGROUPS) {
                    if (!static::can_access_group($note->groupid, $context)) {
                        return null;
                    }
                }
            }
        }

        return $context;
    }

    /**
     * Clears the records in the history table for the last minute.
     *
     * @return bool
     */
    public static function clear_history() {
        global $DB;

        return $DB->delete_records_select('board_history', 'timecreated < :timecreated',
                                        ['timecreated' => time() - 60]); // 1 minute history
    }

    /**
     * Hides the headers of the board.
     *
     * @param int $boardid
     * @return bool
     */
    public static function board_hide_headers($boardid) {
        $board = static::get_board($boardid);
        if (!$board->hideheaders) {
            return false;
        }

        $context = static::context_for_board($boardid);
        $iseditor = has_capability('mod/board:manageboard', $context);
        return !$iseditor;
    }

    /**
     * Check if there are any notes on this board.
     *
     * @param int $boardid
     * @return bool true if there are notes.
     */
    public static function board_has_notes($boardid): bool {
        global $DB;
        $sql = "SELECT COUNT(*) FROM {board_notes}
            LEFT JOIN {board_columns} ON {board_notes}.columnid = {board_columns}.id
            WHERE {board_columns}.boardid = :boardid
            AND {board_notes}.deleted = 0";
        return $DB->count_records_sql($sql, ['boardid' => $boardid]) > 0;
    }

    /**
     * Retrieves the file storage settings
     *
     * @param int $noteid
     * @return object
     */
    public static function get_file_storage_settings($noteid) {
        $note = static::get_note($noteid);
        if (!$note) {
            return null;
        }

        $column = static::get_column($note->columnid);
        if (!$column) {
            return null;
        }

        return (object) [
            'contextid' => static::context_for_board($column->boardid)->id,
            'component' => 'mod_board',
            'filearea'  => 'images',
            'itemid'    => $noteid,
            'filepath'  => '/',
        ];
    }

    /**
     * Reposition an array element by its key.
     *
     * @param array      $array The array being reordered.
     * @param string|int $key They key of the element you want to reposition.
     * @param int        $order The position in the array you want to move the element to. (0 is first)
     *
     * @throws \Exception
     */
    public static function repositionan_array_element(array &$array, $key, int $order): void {
        if (($a = array_search($key, array_keys($array))) === false) {
            throw new \Exception("The {$key} cannot be found in the given array.");
        }
        $p1 = array_splice($array, $a, 1);
        $p2 = array_splice($array, 0, $order);
        $array = array_merge($p2, $p1, $array);
    }

    /**
     * Checks to see if rating has been enabled for the board.
     *
     * @param int $boardid
     * @return bool
     */
    public static function board_rating_enabled($boardid) {
        $board = static::get_board($boardid);
        if (!$board) {
            return false;
        }

        return !empty($board->addrating);
    }

    /**
     * Checks if the user can access all groups.
     *
     * @param mixed $context
     * @return boolean
     */
    public static function can_access_all_groups($context) {
        return has_capability('moodle/site:accessallgroups', $context);
    }

    /**
     * Checks if the user can access a specific group.
     *
     * @param int $groupid
     * @param mixed $context
     * @return boolean
     */
    public static function can_access_group($groupid, $context) {
        if (static::can_access_all_groups($context)) {
            return true;
        }

        return groups_is_member($groupid);
    }

    /**
     * Checks if the user can edit the board.
     *
     * @param int $boardid
     * @return bool
     */
    public static function board_is_editor($boardid) {
        $context = static::context_for_board($boardid);
        return has_capability('mod/board:manageboard', $context);
    }

    /**
     * Asserts whether users may edit their own note placement on
     * a particular board.
     *
     * @param int $boardid
     * @return boolean
     */
    public static function board_users_can_edit($boardid) {
        global $DB;

        $context = static::context_for_board($boardid);
        if (!has_capability('mod/board:post', $context)) {
            // The user is not allowed to post via capabilities.
            return false;
        }

        return $DB->get_field('board', 'userscanedit', ['id' => $boardid], IGNORE_MISSING);
    }

    /**
     * Checks if the user can only view the board
     *
     * @param int $boardid
     * @param int|null $groupid
     * @return mixed
     */
    public static function board_readonly(int $boardid, ?int $groupid): bool {
        if (!$board = static::get_board($boardid)) {
            return false;
        }

        $iseditor = static::board_is_editor($boardid);
        $cm = static::coursemodule_for_board($board);
        $context = static::context_for_board($boardid);
        $groupmode = groups_get_activity_groupmode($cm);
        $postbyoverdue = !empty($board->postby) && time() > $board->postby;

        $readonlyboard = !$iseditor && (($groupmode != NOGROUPS && $board->singleusermode == self::SINGLEUSER_DISABLED
                            && !static::can_access_group((int)$groupid, $context)) || $postbyoverdue);

        return $readonlyboard;
    }

    /**
     * Prepares board notes for export.
     * @param object $note
     * @return string
     */
    public static function get_export_note($note) {
        $breaks = ["<br />", "<br>", "<br/>"];

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

    /**
     * Prepares submissions for export.
     * @param string $content
     * @return array|string|string[]
     */
    public static function get_export_submission(string $content) {
        $breaks = ["<br />", "<br>", "<br/>"];
        return str_ireplace($breaks, "\n", $content);
    }

    /**
     * Returns basic options for the image file picker.
     *
     * @return array
     */
    public static function get_image_picker_options() {
        $extensions = self::get_accepted_file_extensions();

        $extensions = array_map(function($extension) {
            return '.' . $extension;
        }, $extensions);

        return [
            'accepted_types' => $extensions,
            'maxfiles' => 1,
            'subdirs' => 0,
            'maxbytes' => self::ACCEPTED_FILE_MAX_SIZE,
        ];
    }

    /**
     * Gets the available column colours in order or the backup
     * colours if the config is not set.
     * @return string[] An array of hex colour strings.
     */
    public static function get_column_colours() {
        $colours = explode(PHP_EOL, get_config('mod_board', 'column_colours'));
        foreach ($colours as $index => $colour) {
            $colours[$index] = trim($colour,  "\t\n\r\0\x0B#");
            $matched = preg_match('/\b[A-Fa-f0-9]{6}\b|\b[A-Fa-f0-9]{3}\b/', $colours[$index]);
            if ($matched != 1) {
                // One hex was wrong, use the default.
                return self::get_default_colours();
            }
        }
        return $colours;
    }

    /**
     * Returns a single string containing the 7 default colours for
     * column headings.
     * @return string[]
     */
    public static function get_default_colours() {
        return ["1B998B", "2D3047", "FFFD82", "FF9B71", "E84855", "AF9BB6", "F18F01"];
    }

    /**
     * Get the users you can view if the board is set to single user with public posts.
     * @param int $boardid the board id.
     * @param int $groupid the group id.
     * @return array the users.
     */
    public static function get_users_for_board($boardid, $groupid = 0): array {
        if ($groupid) {
            $groups[] = $groupid;
        } else {
            $groups = 0;
        }
        $context = static::context_for_board($boardid);
        $onlyactive = !has_capability('mod/board:manageboard', $context);
        $userlist = get_enrolled_users(static::context_for_board($boardid), 'mod/board:view', $groups, 'u.id,
            u.lastname, u.firstname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename',
            onlyactive: $onlyactive);
        $users = [];
        foreach ($userlist as $user) {
            $users[$user->id] = fullname($user);
        }
        return $users;
    }

    /**
     * Check if you can view the notes for this user.
     *
     * @param int $boardid the board id.
     * @param int $ownerid the user id.
     * @return bool true if you can view the notes, false otherwise.
     */
    public static function can_view_owner(int $boardid, int $ownerid): bool {
        global $USER;

        $board = static::get_board($boardid);
        $context = static::context_for_board($boardid);
        if (has_capability('mod/board:manageboard', $context)) {
            return true;
        }
        if (!is_enrolled($context, $ownerid, 'mod/board:view', true)) {
            // Non-managers can only view boards of enrolled users.
            return false;
        }
        if ($board->singleusermode == self::SINGLEUSER_PUBLIC) {
            return true;
        }
        if ($board->singleusermode == self::SINGLEUSER_PRIVATE && $USER->id == $ownerid) {
            return true;
        }
        return false;
    }

    /**
     * Check if current user can post on this board
     *
     * @param int $boardid the board id.
     * @param int $ownerid the board owner
     */
    public static function can_post(int $boardid, int $ownerid): bool {
        global $USER;

        $board = static::get_board($boardid);
        $context = static::context_for_board($boardid);

        if ($board->singleusermode == self::SINGLEUSER_DISABLED) {
            return has_capability('mod/board:post', $context);
        }

        if ($USER->id == $ownerid) {
            return has_capability('mod/board:post', $context);
        }

        return has_capability('mod/board:manageboard', $context);
    }
}
