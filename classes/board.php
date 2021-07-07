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
 * @package     mod_board
 * @author      Jay Churchward <jay@brickfieldlabs.ie>
 * @copyright   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_board;

defined('MOODLE_INTERNAL') || die;

class board {

    /** @var string String of accepted file types. */
    const ACCEPTED_FILE_EXTENSIONS = 'jpg,jpeg,png,bmp,gif';

    /** @var int Minumum file size of 100 bytes. */
    const ACCEPTED_FILE_MIN_SIZE = 100;

    /** @var int Maximum file size of 10Mb. */
    const ACCEPTED_FILE_MAX_SIZE = 1024 * 1024 * 10;

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
     * Retrieves a record of the selected board.
     *
     * @param int $id
     * @return object
     */
    public static function get_board($id) {
        global $DB;
        return $DB->get_record('board', array('id' => $id));
    }

    /**
     * Retrieves a record of the selected column.
     *
     * @param int $id
     * @return object
     */
    public static function get_column($id) {
        global $DB;
        return $DB->get_record('board_columns', array('id' => $id));
    }

    /**
     * Retrieves a record of the selected note.
     *
     * @param int $id
     * @return object
     */
    public static function get_note($id) {
        global $DB;
        return $DB->get_record('board_notes', array('id' => $id));
    }

    /**
     * Retrieves a record of the rating for the selected note.
     *
     * @param int $noteid
     * @return int
     */
    public static function get_note_rating($noteid) {
        global $DB;
        return $DB->count_records('board_note_ratings', array('noteid' => $noteid));
    }

    /**
     * Retrieves the context of the selected board.
     *
     * @param int $id
     * @return object
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
     * @return object
     */
    public static function context_for_column($id) {
        if (!$column = static::get_column($id)) {
            return null;
        }

        return static::context_for_board($column->boardid);
    }

    /**
     * Adds a capability check to view the board.
     *
     * @param int $id
     * @return void
     */
    public static function require_capability_for_board_view($id) {
        $context = static::context_for_board($id);
        if ($context) {
            require_capability('mod/board:view', $context);
        }
    }

    /**
     * Adds a capability check for the board.
     *
     * @param int $id
     * @return void
     */
    public static function require_capability_for_board($id) {
        $context = static::context_for_board($id);
        if ($context) {
            require_capability('mod/board:manageboard', $context);
        }
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
     * @return mixed
     */
    public static function require_access_for_group($groupid, $boardid) {
        $cm = static::coursemodule_for_board(static::get_board($boardid));
        $context = \context_module::instance($cm->id);

        if (has_capability('mod/board:manageboard', $context)) {
            return true;
        }

        $groupmode = groups_get_activity_groupmode($cm);
        if (!in_array($groupmode, [VISIBLEGROUPS, SEPARATEGROUPS])) {
            return true;
        }

        if (!static::can_access_group($groupid, $context)) {
            throw new \Exception('Invalid group');
        }
    }

    /**
     * Clears the records in the history table for the last minute.
     *
     * @return bool
     */
    public static function clear_history() {
        global $DB;

        return $DB->delete_records_select('board_history', 'timecreated < :timecreated',
                                        array('timecreated' => time() - 60)); // 1 minute history
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
     * Retrieves the board.
     *
     * @param int $boardid
     * @return object
     */
    public static function board_get($boardid) {
        global $DB;

        static::require_capability_for_board_view($boardid);

        if (!$board = $DB->get_record('board', array('id' => $boardid))) {
            return [];
        }

        $groupid = groups_get_activity_group(static::coursemodule_for_board(static::get_board($boardid)), true) ?: null;
        $hideheaders = static::board_hide_headers($boardid);

        $columns = $DB->get_records('board_columns', array('boardid' => $boardid), 'id', 'id, name');
        $columnindex = 0;
        foreach ($columns as $columnid => $column) {
            if ($hideheaders) {
                $column->name = ++$columnindex;
            }
            $params = array('columnid' => $columnid);
            if (!empty($groupid)) {
                $params['groupid'] = $groupid;
            }
            $column->notes = $DB->get_records('board_notes', $params, 'id',
                                            'id, userid, heading, content, type, info, url, timecreated');
            foreach ($column->notes as $colid => $note) {
                $note->rating = static::get_note_rating($note->id);
            }
        }

        static::clear_history();
        return $columns;
    }

    /**
     * Retrieves the boards history.
     *
     * @param int $boardid
     * @param int $since
     * @return mixed
     */
    public static function board_history($boardid, $since) {
        global $DB;

        static::require_capability_for_board_view($boardid);

        if (!$board = $DB->get_record('board', array('id' => $boardid))) {
            return [];
        }

        $groupid = groups_get_activity_group(static::coursemodule_for_board(static::get_board($boardid)), true) ?: null;

        static::clear_history();

        $condition = "boardid=:boardid AND id > :since";
        $params = array('boardid' => $boardid, 'since' => $since);
        if (!empty($groupid)) {
            $condition .= " AND groupid=:groupid";
            $params['groupid'] = $groupid;
        }

        return $DB->get_records_select('board_history', $condition, $params);
    }

    /**
     * Adds a column to the board
     *
     * @param int $boardid
     * @param string $name
     * @return array
     */
    public static function board_add_column($boardid, $name) {
        global $DB, $USER;

        $name = substr($name, 0, 100);

        static::require_capability_for_board($boardid);

        $transaction = $DB->start_delegated_transaction();

        $columnid = $DB->insert_record('board_columns', array('boardid' => $boardid, 'name' => $name));
        $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'action' => 'add_column',
                                        'userid' => $USER->id, 'content' => json_encode(array('id' => $columnid, 'name' => $name)),
                                        'timecreated' => time()));
        $DB->update_record('board', array('id' => $boardid, 'historyid' => $historyid));
        $transaction->allow_commit();

        static::board_add_column_log($boardid, $name, $columnid);

        static::clear_history();
        return array('id' => $columnid, 'historyid' => $historyid);
    }

    /**
     * Triggers the add column event log.
     *
     * @param int $boardid
     * @param string $name
     * @param int $columnid
     * @return void
     */
    public static function board_add_column_log($boardid, $name, $columnid) {
        $event = \mod_board\event\add_column::create(array(
            'objectid' => $columnid,
            'context' => \context_module::instance(static::coursemodule_for_board(static::get_board($boardid))->id),
            'other' => array('name' => $name)
        ));
        $event->trigger();
    }

    /**
     * Updates the column.
     *
     * @param int $id
     * @param string $name
     * @return array
     */
    public static function board_update_column($id, $name) {
        global $DB, $USER;

        $name = substr($name, 0, 100);

        static::require_capability_for_column($id);

        $boardid = $DB->get_field('board_columns', 'boardid', array('id' => $id));
        if ($boardid) {
            $transaction = $DB->start_delegated_transaction();
            $update = $DB->update_record('board_columns', array('id' => $id, 'name' => $name));
            $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'action' => 'update_column',
                                            'userid' => $USER->id, 'content' => json_encode(array('id' => $id, 'name' => $name)),
                                            'timecreated' => time()));
            $DB->update_record('board', array('id' => $id, 'historyid' => $historyid));
            $transaction->allow_commit();

            static::board_update_column_log($boardid, $name, $id);
        } else {
            $update = false;
            $historyid = 0;
        }

        static::clear_history();
        return array('status' => $update, 'historyid' => $historyid);
    }

    /**
     * Triggers the update column log.
     *
     * @param int $boardid
     * @param string $name
     * @param int $columnid
     * @return void
     */
    public static function board_update_column_log($boardid, $name, $columnid) {
        $event = \mod_board\event\update_column::create(array(
            'objectid' => $columnid,
            'context' => \context_module::instance(static::coursemodule_for_board(static::get_board($boardid))->id),
            'other' => array('name' => $name)
        ));
        $event->trigger();
    }

    /**
     * Deletes a column.
     *
     * @param int $id
     * @return array
     */
    public static function board_delete_column($id) {
        global $DB, $USER;

        static::require_capability_for_column($id);

        $boardid = $DB->get_field('board_columns', 'boardid', array('id' => $id));
        if ($boardid) {
            $transaction = $DB->start_delegated_transaction();
            $notes = $DB->get_records('board_notes', array('columnid' => $id));
            foreach ($notes as $noteid => $note) {
                static::delete_note_file($note->id);
            }
            $DB->delete_records('board_notes', array('columnid' => $id));
            $delete = $DB->delete_records('board_columns', array('id' => $id));
            $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'action' => 'delete_column',
                                            'content' => json_encode(array('id' => $id)),
                                            'userid' => $USER->id, 'timecreated' => time()));
            $DB->update_record('board', array('id' => $boardid, 'historyid' => $historyid));
            $transaction->allow_commit();

            static::board_delete_column_log($boardid, $id);
        } else {
            $delete = false;
            $historyid = 0;
        }

        static::clear_history();
        return array('status' => $delete, 'historyid' => $historyid);
    }

    /**
     * Triggers the delete column log.
     *
     * @param int $boardid
     * @param int $columnid
     * @return void
     */
    public static function board_delete_column_log($boardid, $columnid) {
        $event = \mod_board\event\delete_column::create(array(
            'objectid' => $columnid,
            'context' => \context_module::instance(static::coursemodule_for_board(static::get_board($boardid))->id)
        ));
        $event->trigger();
    }

    /**
     * Adds a capability check for the notes.
     *
     * @param int $id
     * @return void
     */
    public static function require_capability_for_note($id) {
        global $DB, $USER;

        if (!$note = $DB->get_record('board_notes', array('id' => $id))) {
            return false;
        }

        $context = static::context_for_column($note->columnid);
        if ($context) {
            require_capability('mod/board:view', $context);

            if ($USER->id != $note->userid) {
                require_capability('mod/board:manageboard', $context);
            }
        }
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
            'filepath'  => '/'
        ];
    }

    /**
     * Retrieves the file added to a note.
     *
     * @param int $noteid
     * @return object
     */
    public static function get_note_file($noteid) {
        $note = static::get_note($noteid);
        if (!$note || empty($note->url)) {
            return null;
        }
        $file = static::get_file_storage_settings($noteid);
        $fs = get_file_storage();
        return $fs->get_file($file->contextid, $file->component, $file->filearea, $file->itemid,
                             $file->filepath, basename($note->url));
    }

    /**
     * Deletes the stored file.
     *
     * @param int $noteid
     * @return void
     */
    public static function delete_note_file($noteid) {
        $storedfile = static::get_note_file($noteid);
        if ($storedfile) {
            $storedfile->delete();
        }
    }

    /**
     * Stores the added file.
     *
     * @param int $noteid
     * @param array $attachment
     * @return \moodle_url
     */
    public static function store_note_file($noteid, $attachment) {
        $file = static::get_file_storage_settings($noteid);
        $file->filename = $attachment['filename'];

        static::delete_note_file($noteid);
        $fs = get_file_storage();

        $storedfile = static::get_note_file($noteid);
        if ($storedfile) {
            $storedfile->delete();
        }

        $storedfile = $fs->create_file_from_string($file, $attachment['filecontents']);

        return \moodle_url::make_pluginfile_url($storedfile->get_contextid(), $storedfile->get_component(),
                $storedfile->get_filearea(), $storedfile->get_itemid(), $storedfile->get_filepath(),
                $file->filename)->get_path();
    }

    /**
     * Check if the file fits the requirements to be uploaded.
     *
     * @param array $attachment
     * @return bool
     */
    public static function valid_for_upload($attachment) {
        $fileparts = explode('.', basename($attachment['filename']));
        $fileextension = strtolower(array_pop($fileparts));
        if (!in_array($fileextension, explode(',', ACCEPTED_FILE_EXTENSIONS))) {
            return false;
        }
        $filelength = strlen($attachment['filecontents']);

        if ($filelength < self::ACCEPTED_FILE_MIN_SIZE) {
            return false;
        }
        if ($filelength > self::ACCEPTED_FILE_MAX_SIZE) {
            return false;
        }

        return true;
    }

    /**
     * Updates the attachment.
     *
     * @param int $noteid
     * @param array $attachment
     * @return array
     */
    public static function board_note_update_attachment($noteid, $attachment) {
        global $DB;

        if (!empty($attachment['filename'])) {
            $attachment['filecontents'] = base64_decode(explode(',', $attachment['filecontents'])[1]);
            if (static::valid_for_upload($attachment)) {
                $attachment['url'] = static::store_note_file($noteid, $attachment);
            }
            unset($attachment['filename']);
            unset($attachment['filecontents']);
        }
        return $attachment;
    }

    /**
     * Adds a note to the board
     *
     * @param int $columnid
     * @param string $heading
     * @param string $content
     * @param array $attachment
     * @return array
     */
    public static function board_add_note($columnid, $heading, $content, $attachment) {
        global $DB, $USER;

        $context = static::context_for_column($columnid);
        if ($context) {
            require_capability('mod/board:view', $context);
        }

        $heading = empty($heading) ? null : substr($heading, 0, 100);
        $content = empty($content) ? "" : substr($content, 0, get_config('mod_board', 'post_max_length'));
        $content = clean_text($content, FORMAT_HTML);

        $boardid = $DB->get_field('board_columns', 'boardid', array('id' => $columnid));

        if ($boardid) {
            $cm = static::coursemodule_for_board(static::get_board($boardid));
            $groupid = groups_get_activity_group($cm, true) ?: null;
            static::require_access_for_group($groupid, $boardid);

            if (static::board_readonly($boardid)) {
                throw new \Exception('board_add_note not available');
            }
            $transaction = $DB->start_delegated_transaction();
            $type = !empty($attachment['type']) ? $attachment['type'] : 0;
            $info = !empty($type) ? substr($attachment['info'], 0, 100) : null;
            $url = !empty($type) ? substr($attachment['url'], 0, 200) : null;

            $notecreated = time();
            $noteid = $DB->insert_record('board_notes', array('groupid' => $groupid, 'columnid' => $columnid,
                                        'heading' => $heading, 'content' => $content, 'type' => $type, 'info' => $info,
                                        'url' => $url, 'userid' => $USER->id, 'timecreated' => $notecreated));

            $attachment = static::board_note_update_attachment($noteid, $attachment);
            $url = $attachment['url'];
            $DB->update_record('board_notes', array('id' => $noteid, 'url' => $url));

            $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'groupid' => $groupid,
                                            'action' => 'add_note', 'userid' => $USER->id,
                                            'content' => json_encode(array('id' => $noteid, 'columnid' => $columnid,
                                            'heading' => $heading, 'content' => $content,
                                            'attachment' => array('type' => $type, 'info' => $info, 'url' => $url), 'rating' => 0,
                                            'timecreated' => $notecreated)),
                                            'timecreated' => time()));

            $DB->update_record('board', array('id' => $boardid, 'historyid' => $historyid));
            $transaction->allow_commit();

            static::board_add_note_log($boardid, $groupid, $heading, $content, $attachment, $columnid, $noteid);

            $note = static::get_note($noteid);
            $note->rating = 0;

        } else {
            $note = null;
            $historyid = 0;
        }

        static::clear_history();
        return array('status' => !empty($note), 'note' => $note, 'historyid' => $historyid);
    }

    /**
     * Triggers the add note log.
     *
     * @param int $boardid
     * @param int $groupid
     * @param string $heading
     * @param string $content
     * @param array $attachment
     * @param int $columnid
     * @param int $noteid
     * @return void
     */
    public static function board_add_note_log($boardid, $groupid, $heading, $content, $attachment, $columnid, $noteid) {
        $event = \mod_board\event\add_note::create(array(
            'objectid' => $noteid,
            'context' => \context_module::instance(static::coursemodule_for_board(static::get_board($boardid))->id),
            'other' => array('groupid' => $groupid, 'columnid' => $columnid, 'heading' => $heading,
                            'content' => $content, 'attachment' => $attachment)
        ));
        $event->trigger();
    }

    /**
     * Updates a note.
     *
     * @param int $id
     * @param string $heading
     * @param string $content
     * @param array $attachment
     * @return array
     */
    public static function board_update_note($id, $heading, $content, $attachment) {
        global $DB, $USER;

        static::require_capability_for_note($id);

        $heading = empty($heading) ? null : substr($heading, 0, 100);
        $content = empty($content) ? "" : substr($content, 0, get_config('mod_board', 'post_max_length'));
        $content = clean_text($content, FORMAT_HTML);

        $note = static::get_note($id);
        $columnid = $note->columnid;
        $boardid = $DB->get_field('board_columns', 'boardid', array('id' => $columnid));

        if (!empty($note->groupid)) {
            static::require_access_for_group($note->groupid, $boardid);
        }

        if (static::board_readonly($boardid)) {
            throw new \Exception('board_update_note not available');
        }

        if ($columnid && $boardid) {
            $transaction = $DB->start_delegated_transaction();
            $attachment = static::board_note_update_attachment($id, $attachment);

            $type = !empty($attachment['type']) ? $attachment['type'] : 0;
            $info = !empty($type) ? substr($attachment['info'], 0, 100) : null;
            $url = !empty($type) ? substr($attachment['url'], 0, 200) : null;

            $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'action' => 'update_note',
                                            'userid' => $USER->id, 'content' => json_encode(array('id' => $id,
                                            'columnid' => $columnid, 'heading' => $heading, 'content' => $content,
                                            'attachment' => array('type' => $type, 'info' => $info, 'url' => $url))),
                                            'timecreated' => time()));
            $update = $DB->update_record('board_notes', array('id' => $id, 'heading' => $heading, 'content' => $content,
                                        'type' => $type, 'info' => $info, 'url' => $url));
            $DB->update_record('board', array('id' => $boardid, 'historyid' => $historyid));

            $transaction->allow_commit();

            static::board_update_note_log($boardid, $heading, $content, $attachment, $columnid, $id);

            $note = static::get_note($id);
        } else {
            $note = null;
            $update = false;
            $historyid = 0;
        }

        static::clear_history();
        return array('status' => $update, 'note' => $note, 'historyid' => $historyid);
    }

    /**
     * Triggers the update note log.
     *
     * @param int $boardid
     * @param string $heading
     * @param string $content
     * @param array $attachment
     * @param int $columnid
     * @param int $noteid
     * @return void
     */
    public static function board_update_note_log($boardid, $heading, $content, $attachment, $columnid, $noteid) {
        $event = \mod_board\event\update_note::create(array(
            'objectid' => $noteid,
            'context' => \context_module::instance(static::coursemodule_for_board(static::get_board($boardid))->id),
            'other' => array('columnid' => $columnid, 'heading' => $heading, 'content' => $content, 'attachment' => $attachment)
        ));
        $event->trigger();
    }

    /**
     * Deletes a note from the board.
     *
     * @param int $id
     * @return array
     */
    public static function board_delete_note($id) {
        global $DB, $USER;

        static::require_capability_for_note($id);

        $note = static::get_note($id);
        $columnid = $note->columnid;
        $boardid = $DB->get_field('board_columns', 'boardid', array('id' => $columnid));

        if (!empty($note->groupid)) {
            static::require_access_for_group($note->groupid, $boardid);
        }

        if (static::board_readonly($boardid)) {
            throw new \Exception('board_delete_note not available');
        }

        if ($columnid && $boardid) {

            static::delete_note_file($note->id);

            $transaction = $DB->start_delegated_transaction();
            $delete = $DB->delete_records('board_notes', array('id' => $id));
            $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'action' => 'delete_note',
                                            'content' => json_encode(array('id' => $id, 'columnid' => $columnid)),
                                            'userid' => $USER->id, 'timecreated' => time()));
            $DB->update_record('board', array('id' => $boardid, 'historyid' => $historyid));
            $transaction->allow_commit();

            static::board_delete_note_log($boardid, $columnid, $id);
        } else {
            $delete = false;
            $historyid = 0;
        }
        static::clear_history();
        return array('status' => $delete, 'historyid' => $historyid);
    }

    /**
     * Triggers the delete note log.
     *
     * @param int $boardid
     * @param int $columnid
     * @param int $noteid
     * @return void
     */
    public static function board_delete_note_log($boardid, $columnid, $noteid) {
        $event = \mod_board\event\delete_note::create(array(
            'objectid' => $noteid,
            'context' => \context_module::instance(static::coursemodule_for_board(static::get_board($boardid))->id),
            'other' => array('columnid' => $columnid)
        ));
        $event->trigger();
    }

    /**
     * Moves a note to a different column
     *
     * @param int $id
     * @param int $columnid
     * @return array
     */
    public static function board_move_note($id, $columnid) {
        global $DB, $USER;

        $note = static::get_note($id);
        static::require_capability_for_column($note->columnid);

        $boardid = $DB->get_field('board_columns', 'boardid', array('id' => $columnid));

        if ($columnid && $boardid) {

            $transaction = $DB->start_delegated_transaction();

            $DB->insert_record('board_history', array('boardid' => $boardid, 'action' => 'delete_note',
                            'content' => json_encode(array('id' => $note->id, 'columnid' => $note->columnid)),
                            'userid' => $USER->id, 'timecreated' => time()));
            $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'groupid' => $note->groupid,
                                            'action' => 'add_note', 'userid' => $note->userid,
                                            'content' => json_encode(array('id' => $note->id, 'columnid' => $columnid,
                                            'heading' => $note->heading, 'content' => $note->content,
                                            'attachment' => array('type' => $note->type, 'info' => $note->info,
                                            'url' => $note->url), 'timecreated' => $note->timecreated,
                                            'rating' => static::get_note_rating($note->id))),
                                            'timecreated' => time()));

            $note->columnid = $columnid;
            $move = $DB->update_record('board_notes', $note);

            $DB->update_record('board', array('id' => $boardid, 'historyid' => $historyid));
            $transaction->allow_commit();

            static::board_move_note_log($boardid, $columnid, $id);
        } else {
            $move = false;
            $historyid = 0;
        }
        static::clear_history();
        return array('status' => $move, 'historyid' => $historyid);
    }

    /**
     * Triggers the move note log.
     *
     * @param int $boardid
     * @param int $columnid
     * @param int $noteid
     * @return void
     */
    public static function board_move_note_log($boardid, $columnid, $noteid) {
        $event = \mod_board\event\move_note::create(array(
            'objectid' => $noteid,
            'context' => \context_module::instance(static::coursemodule_for_board(static::get_board($boardid))->id),
            'other' => array('columnid' => $columnid)
        ));
        $event->trigger();
    }

    /**
     * Checks to see if the user can rate the note.
     *
     * @param int $noteid
     * @return bool
     */
    public static function board_can_rate_note($noteid) {
        global $DB, $USER;

        $note = static::get_note($noteid);
        if (!$note) {
            return false;
        }

        $column = static::get_column($note->columnid);
        if (!$column) {
            return false;
        }

        $board = static::get_board($column->boardid);
        if (!$board) {
            return false;
        }

        if (!static::board_rating_enabled($board->id)) {
            return false;
        }

        if (static::board_readonly($board->id)) {
            return false;
        }

        $context = static::context_for_board($board->id);
        if (!has_capability('mod/board:view', $context)) {
            return false;
        }

        $iseditor = has_capability('mod/board:manageboard', $context);

        if ($board->addrating == self::RATINGBYSTUDENTS && $iseditor) {
            return false;
        }

        if ($board->addrating == self::RATINGBYTEACHERS && !$iseditor) {
            return false;
        }

        return !$DB->record_exists('board_note_ratings', array('userid' => $USER->id, 'noteid' => $noteid));
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
     * Rates the note
     *
     * @param int $noteid
     * @return array
     */
    public static function board_rate_note($noteid) {
        global $DB, $USER;

        $note = static::get_note($noteid);
        if (!$note) {
            return false;
        }

        $column = static::get_column($note->columnid);
        if (!$column) {
            return false;
        }

        $boardid = $column->boardid;
        if (!static::board_can_rate_note($noteid)) {
            return false;
        }
        if (static::board_readonly($boardid)) {
            return false;
        }

        if ($note) {
            $transaction = $DB->start_delegated_transaction();

            $DB->insert_record('board_note_ratings', array('userid' => $USER->id, 'noteid' => $noteid, 'timecreated' => time()));
            $rating = static::get_note_rating($noteid);
            $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'action' => 'rate_note',
                                            'content' => json_encode(array('id' => $note->id, 'rating' => $rating)),
                                            'userid' => $USER->id, 'timecreated' => time()));

            $DB->update_record('board', array('id' => $boardid, 'historyid' => $historyid));

            $transaction->allow_commit();

            static::board_rate_note_log($boardid, $noteid, $rating);
        } else {
            $rate = false;
            $rating = 0;
            $historyid = 0;
        }
        static::clear_history();
        return array('status' => $rate, 'rating' => $rating, 'historyid' => $historyid);
    }

    /**
     * Triggers the rate note log.
     *
     * @param int $boardid
     * @param int $noteid
     * @param int $rating
     * @return void
     */
    public static function board_rate_note_log($boardid, $noteid, $rating) {
        $event = \mod_board\event\rate_note::create(array(
            'objectid' => $noteid,
            'context' => \context_module::instance(static::coursemodule_for_board(static::get_board($boardid))->id),
            'other' => array('rating' => $rating)
        ));
        $event->trigger();
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
        global $USER;

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
     * Checks if the user can only view the board
     *
     * @param int $boardid
     * @return mixed
     */
    public static function board_readonly($boardid) {
        if (!$board = static::get_board($boardid)) {
            return false;
        }

        $iseditor = static::board_is_editor($boardid);
        $cm = static::coursemodule_for_board($board);
        $groupmode = groups_get_activity_groupmode($cm);
        $postbyoverdue = !empty($board->postby) && time() > $board->postby;

        $readonlyboard = !$iseditor && (($groupmode == VISIBLEGROUPS &&
                         !static::can_access_group(groups_get_activity_group($cm, true),
        $context)) || $postbyoverdue);

        return $readonlyboard;
    }
}