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

namespace mod_board\local;

use mod_board\board;
use stdClass;

/**
 * Note helper class.
 *
 * @package    mod_board
 * @copyright  2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class note {
    /**
     * Add a note to the board.
     *
     * @param int $columnid
     * @param int $ownerid
     * @param int|null $groupid
     * @param string $heading
     * @param string $content
     * @param array $attachment
     * @param int|null $userid NULL means current user
     * @return stdClass note record with extra historyid property
     */
    public static function create(
        int $columnid, int $ownerid, ?int $groupid, string $heading, string $content, array $attachment, ?int $userid = null
    ): stdClass {
        global $DB, $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }
        if (!$userid || !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            throw new \core\exception\invalid_parameter_exception('Invalid userid');
        }

        $column = board::get_column($columnid, MUST_EXIST);
        $board = board::get_board($column->boardid, MUST_EXIST);
        $context = board::context_for_board($board);

        $heading = empty($heading) ? null : \core_text::substr($heading, 0, board::LENGTH_HEADING);
        $content = empty($content) ? "" : \core_text::substr($content, 0, get_config('mod_board', 'post_max_length'));
        $content = clean_text($content, FORMAT_HTML);

        if (!$groupid) {
            $groupid = null;
        } else if ($board->singleusermode != board::SINGLEUSER_DISABLED) {
            throw new \core\exception\invalid_parameter_exception('groupid is not allowed in single user mode');
        } else if (!$DB->record_exists('groups', ['id' => $groupid])) {
            throw new \core\exception\invalid_parameter_exception('Invalid groupid');
        }

        if (!$ownerid) {
            throw new \core\exception\invalid_parameter_exception('ownerid is required');
        }
        if (!$DB->record_exists('user', ['id' => $ownerid, 'deleted' => 0])) {
            throw new \core\exception\invalid_parameter_exception('Invalid ownerid');
        }
        if ($board->singleusermode == board::SINGLEUSER_DISABLED && $userid != $ownerid) {
            throw new \core\exception\invalid_parameter_exception('ownerid must match userid if single user mode disabled');
        }

        $transaction = $DB->start_delegated_transaction();

        // Get the count of notes in the column to add to bottom of sort order.
        $countnotes = $DB->count_records('board_notes', ['columnid' => $columnid, 'deleted' => 0]);

        $type = !empty($attachment['type']) ? $attachment['type'] : 0;
        $info = !empty($type) ? \core_text::substr(s($attachment['info']), 0, board::LENGTH_INFO) : null;
        $url = !empty($type) ? \core_text::substr($attachment['url'], 0, board::LENGTH_URL) : null;

        $notecreated = time();
        $noteid = $DB->insert_record('board_notes', [
            'groupid' => $groupid,
            'columnid' => $columnid,
            'ownerid' => $ownerid,
            'heading' => $heading,
            'content' => $content,
            'type' => $type,
            'info' => $info,
            'url' => $url,
            'userid' => $userid,
            'timecreated' => $notecreated,
            'sortorder' => $countnotes,
            'deleted' => 0,
        ]);

        $attachment = self::update_note_attachment($noteid, $attachment);
        $url = $attachment['url'];
        $DB->update_record('board_notes', ['id' => $noteid, 'url' => $url]);

        $note = board::get_note($noteid, MUST_EXIST);

        $historyid = $DB->insert_record('board_history', ['boardid' => $board->id, 'groupid' => $groupid,
            'action' => 'add_note', 'ownerid' => $ownerid, 'userid' => $userid,
            'content' => json_encode(['id' => $note->id, 'columnid' => $columnid,
                'heading' => $heading, 'content' => $content,
                'attachment' => ['type' => $type, 'info' => $info, 'url' => $url], 'rating' => 0,
                'timecreated' => $notecreated, 'sortorder' => $countnotes]),
            'timecreated' => time()]);

        $DB->set_field('board', 'historyid', $historyid, ['id' => $board->id]);
        $board->historyid = (string)$historyid;

        $transaction->allow_commit();

        if ($board->completionnotes) {
            $cm = board::coursemodule_for_board($board);
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm);
            }
        }

        $event = \mod_board\event\add_note::create_from_note($note, $attachment, $column, $board, $context);
        $event->trigger();;

        $note->historyid = $historyid;

        board::clear_history();
        return $note;
    }

    /**
     * Update a note.
     *
     * @param int $id
     * @param string $heading
     * @param string $content
     * @param array $attachment
     * @return stdClass note record with extra historyid property
     */
    public static function update(int $id, string $heading, string $content, array $attachment): stdClass {
        global $DB, $USER;

        $heading = empty($heading) ? null : \core_text::substr($heading, 0, board::LENGTH_HEADING);
        $content = empty($content) ? "" : \core_text::substr($content, 0, get_config('mod_board', 'post_max_length'));
        $content = clean_text($content, FORMAT_HTML);

        $note = board::get_note($id, MUST_EXIST);
        $column = board::get_column($note->columnid, MUST_EXIST);
        $board = board::get_board($column->boardid, MUST_EXIST);
        $context = board::context_for_board($board);

        $transaction = $DB->start_delegated_transaction();

        $previoustype = $note->type;
        $attachment = self::update_note_attachment($id, $attachment, $previoustype);

        $type = !empty($attachment['type']) ? $attachment['type'] : 0;
        $info = !empty($type) ? \core_text::substr(s($attachment['info']), 0, board::LENGTH_INFO) : null;
        $url = !empty($type) ? \core_text::substr($attachment['url'], 0, board::LENGTH_URL) : null;

        $DB->update_record('board_notes', [
            'id' => $note->id,
            'heading' => $heading,
            'content' => $content,
            'type' => $type,
            'info' => $info,
            'url' => $url,
        ]);
        $note = board::get_note($note->id, MUST_EXIST);

        $historyid = $DB->insert_record('board_history', ['boardid' => $board->id, 'action' => 'update_note',
            'ownerid' => $note->ownerid, 'userid' => $USER->id, 'content' => json_encode(['id' => $id,
                'columnid' => $column->id, 'heading' => $heading, 'content' => $content,
                'attachment' => ['type' => $type, 'info' => $info, 'url' => $url]]),
            'timecreated' => time()]);

        $DB->set_field('board', 'historyid', $historyid, ['id' => $board->id]);
        $board->historyid = (string)$historyid;

        $transaction->allow_commit();

        $event = \mod_board\event\update_note::create_from_note($note, $attachment, $column, $board, $context);
        $event->trigger();;

        board::clear_history();

        $note->historyid = $historyid;

        return $note;
    }

    /**
     * Delete a note from the board.
     *
     * @param int $id
     * @return int history id
     */
    public static function delete(int $id): int {
        global $DB, $USER;

        $note = board::get_note($id, MUST_EXIST);
        $column = board::get_column($note->columnid, MUST_EXIST);
        $board = board::get_board($column->boardid, MUST_EXIST);
        $context = board::context_for_board($board);

        $sortorder = $note->sortorder;

        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('board_note_ratings', ['noteid' => $note->id]);
        self::delete_note_file($note->id);

        // Delete all note comments.
        $commentrecords = $DB->get_records('board_comments', ['noteid' => $note->id]);
        foreach ($commentrecords as $commentrecord) {
            comment::delete($commentrecord->id);
        }

        $DB->update_record('board_notes', ['id' => $id, 'deleted' => 1]);
        $historyid = $DB->insert_record('board_history', ['boardid' => $board->id, 'action' => 'delete_note',
            'ownerid' => 0, 'content' => json_encode(['id' => $id, 'columnid' => $column->id]),
            'userid' => $USER->id, 'timecreated' => time()]);

        $sql = "UPDATE {board_notes}
                   SET sortorder = sortorder - 1
                 WHERE sortorder > :sortorder AND columnid = :columnid";
        $DB->execute($sql, ['sortorder' => $sortorder, 'columnid' => $column->id]);

        $DB->set_field('board', 'historyid', $historyid, ['id' => $board->id]);
        $board->historyid = (string)$historyid;

        $transaction->allow_commit();

        $event = \mod_board\event\delete_note::create_from_note($note, $column, $board, $context);
        $event->trigger();

        board::clear_history();

        return $historyid;
    }

    /**
     * Move a note to a different column or position in the same column.
     *
     * @param int $id
     * @param int $columnid
     * @param int $sortorder The order in the column the note was placed.
     * @return int history id
     */
    public static function move(int $id, int $columnid, int $sortorder): int {
        global $DB, $USER;

        $note = board::get_note($id, MUST_EXIST);
        $column = board::get_column($note->columnid, MUST_EXIST);
        $board = board::get_board($column->boardid, MUST_EXIST);
        $context = board::context_for_board($board);

        $newcolumn = $DB->get_record('board_columns', ['id' => $columnid], '*', MUST_EXIST);
        if ($newcolumn->boardid != $column->boardid) {
            throw new \invalid_parameter_exception('note cannot be moved to a different board');
        }

        $transaction = $DB->start_delegated_transaction();

        $DB->insert_record('board_history', ['boardid' => $board->id, 'action' => 'delete_note',
            'content' => json_encode(['id' => $note->id, 'columnid' => $note->columnid]),
            'ownerid' => $note->ownerid, 'userid' => $USER->id, 'timecreated' => time()]);
        $historyid = $DB->insert_record('board_history', ['boardid' => $board->id, 'groupid' => $note->groupid,
            'action' => 'add_note', 'userid' => $note->userid, 'ownerid' => $note->ownerid,
            'content' => json_encode(['id' => $note->id, 'columnid' => $columnid,
                'heading' => $note->heading, 'content' => $note->content,
                'attachment' => ['type' => $note->type, 'info' => $note->info,
                    'url' => $note->url], 'timecreated' => $note->timecreated,
                'rating' => self::get_rating($note->id), 'sortorder' => $sortorder]),
            'timecreated' => time()]);
        // Checking if we move the note up or down.
        $ismovingup = $note->sortorder < $sortorder;
        $ismovingdown = $note->sortorder > $sortorder;
        $issamecolumn = ($columnid == $note->columnid);
        // Check whether it is the same column and then increment or decrement notes above or below
        // the set sortorder according to whether the sortorder has moved up or down.
        if ($issamecolumn) {
            $params = ['newsort' => $sortorder, 'oldsort' => $note->sortorder, 'columnid' => $columnid];
            if ($ismovingup) {
                $sql = "UPDATE {board_notes}
                           SET sortorder = sortorder - 1
                         WHERE sortorder <= :newsort
                               AND sortorder >= :oldsort
                               AND columnid = :columnid";
                $DB->execute($sql, $params);
            } else if ($ismovingdown) {
                $sql = "UPDATE {board_notes}
                           SET sortorder = sortorder + 1
                         WHERE sortorder >= :newsort
                               AND sortorder <= :oldsort
                               AND columnid = :columnid";
                $DB->execute($sql, $params);
            }
        } else {
            // Increment the new column notes to fit the moved note.
            $sql = "UPDATE {board_notes}
                       SET sortorder = sortorder + 1
                     WHERE sortorder >= :newsort AND columnid = :columnid";
            $DB->execute($sql, ['newsort' => $sortorder, 'columnid' => $columnid]);
            // Decrement the old column notes above where the moved note left.
            $sql = "UPDATE {board_notes}
                       SET sortorder = sortorder - 1
                     WHERE sortorder > :oldsort AND columnid = :columnid";
            $DB->execute($sql, ['oldsort' => $note->sortorder, 'columnid' => $note->columnid]);
        }
        // Update the note record.
        $DB->update_record('board_notes', [
            'id' => $note->id,
            'columnid' => $columnid,
            'sortorder' => $sortorder,
        ]);
        $note = board::get_note($note->id, MUST_EXIST);

        $DB->set_field('board', 'historyid', $historyid, ['id' => $board->id]);
        $board->historyid = (string)$historyid;

        $transaction->allow_commit();

        $event = \mod_board\event\move_note::create_from_note($note, $column, $board, $context);
        $event->trigger();

        board::clear_history();

        return $historyid;
    }

    /**
     * Checks to see if the user can rate the note.
     *
     * @param int $noteid
     * @return bool
     */
    public static function can_rate(int $noteid): bool {
        global $USER;

        $note = board::get_note($noteid);
        if (!$note) {
            return false;
        }
        $column = board::get_column($note->columnid, MUST_EXIST);
        $board = board::get_board($column->boardid, MUST_EXIST);
        $context = board::context_for_board($board);

        if (!board::board_rating_enabled($board)) {
            return false;
        }

        if (board::board_readonly($board, $note->groupid)) {
            return false;
        }

        if (!has_capability('mod/board:post', $context)) {
            return false;
        }

        $iseditor = has_capability('mod/board:manageboard', $context);

        if ($board->addrating == board::RATINGBYSTUDENTS && $iseditor) {
            return false;
        }

        if ($board->addrating == board::RATINGBYTEACHERS && !$iseditor) {
            return false;
        }

        if (!$iseditor) {
            if ($board->singleusermode == board::SINGLEUSER_PRIVATE) {
                if ($note->userid != $USER->id && $note->ownerid != $USER->id) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Rate or unrate the note.
     *
     * @param int $noteid
     * @return int history id
     */
    public static function rate(int $noteid): int {
        global $DB, $USER;

        $note = board::get_note($noteid, MUST_EXIST);
        $column = board::get_column($note->columnid, MUST_EXIST);
        $board = board::get_board($column->boardid, MUST_EXIST);
        $context = board::context_for_board($board);

        $transaction = $DB->start_delegated_transaction();

        $hasrating = $DB->record_exists('board_note_ratings', ['userid' => $USER->id, 'noteid' => $noteid]);
        $action = $hasrating ? 'delete_note_rating' : 'add_note_rating';
        if ($hasrating) {
            $DB->delete_records('board_note_ratings', ['userid' => $USER->id, 'noteid' => $noteid]);
        } else {
            $DB->insert_record('board_note_ratings', [
                'userid' => $USER->id,
                'noteid' => $noteid,
                'timecreated' => time(),
            ]);
        }

        $rating = self::get_rating($noteid);
        $historyid = $DB->insert_record('board_history', ['boardid' => $board->id, 'action' => $action,
            'content' => json_encode(['id' => $note->id, 'rating' => $rating]),
            'userid' => $USER->id, 'timecreated' => time()]);

        $DB->set_field('board', 'historyid', $historyid, ['id' => $board->id]);
        $board->historyid = (string)$historyid;

        $transaction->allow_commit();

        $event = \mod_board\event\rate_note::create_from_note($note, $rating, $column, $board, $context);
        $event->trigger();

        board::clear_history();

        return $historyid;
    }

    /**
     * Retrieves a record of the rating for the selected note.
     *
     * @param int $noteid
     * @return int
     */
    public static function get_rating($noteid) {
        global $DB;
        return $DB->count_records('board_note_ratings', ['noteid' => $noteid]);
    }

    /**
     * Retrieve the file added to a note.
     *
     * @param int $noteid
     * @return \stored_file|bool
     */
    public static function get_note_file(int $noteid): ?\stored_file {
        $note = board::get_note($noteid);
        if (!$note || empty($note->url)) {
            return null;
        }
        $file = self::get_file_storage_settings($noteid);
        $fs = get_file_storage();
        $f = $fs->get_file($file->contextid, $file->component, $file->filearea, $file->itemid,
            $file->filepath, basename($note->url));
        if ($f === false) {
            $f = null;
        }
        return $f;
    }

    /**
     * Delete the stored file.
     *
     * @param int $noteid
     * @return void
     */
    public static function delete_note_file(int $noteid): void {
        $storedfile = self::get_note_file($noteid);
        if ($storedfile) {
            $storedfile->delete();
        }
    }

    /**
     * Store the added file.
     *
     * @param int $noteid
     * @param int $draftitemid
     * @return string|null
     */
    protected static function store_note_file(int $noteid, int $draftitemid) {
        $settings = self::get_file_storage_settings($noteid);

        file_save_draft_area_files($draftitemid, $settings->contextid, $settings->component, $settings->filearea,
            $settings->itemid);

        $fs = get_file_storage();
        $files = $fs->get_area_files($settings->contextid, $settings->component, $settings->filearea, $settings->itemid,
            'itemid, filepath, filename', false);

        $storedfile = reset($files);
        if (!$storedfile) {
            // This means there is no file here.
            return null;
        }

        return \moodle_url::make_pluginfile_url($storedfile->get_contextid(), $storedfile->get_component(),
            $storedfile->get_filearea(), $storedfile->get_itemid(), $storedfile->get_filepath(),
            $storedfile->get_filename())->get_path();
    }

    /**
     * Update the attachment.
     *
     * @param int $noteid
     * @param array $attachment
     * @param int|null $previoustype
     * @return array
     */
    protected static function update_note_attachment(int $noteid, $attachment, $previoustype = null): array {
        if (!empty($attachment['draftitemid'])) {
            $attachment['url'] = self::store_note_file($noteid, $attachment['draftitemid']);
            unset($attachment['draftitemid']);
        }

        if (empty($attachment['info']) && empty($attachment['url'])) {
            // In this case, we want to reset the media type to none.
            $attachment['type'] = 0;
            $attachment['info'] = null;
            $attachment['url'] = null;
        }

        if ($previoustype) {
            if (isset($attachment['type']) && $attachment['type'] != 2 && $previoustype == 2) {
                // This case is if we are changing from a picture type to a non-picture type. We should remove files.
                $fs = get_file_storage();
                $settings = self::get_file_storage_settings($noteid);

                $fs->delete_area_files($settings->contextid, $settings->component, $settings->filearea, $settings->itemid);
            }
        }

        return $attachment;
    }

    /**
     * Get the supported filetype extensions
     *
     * @return array of strings of supported file extensions.
     */
    public static function get_accepted_file_extensions(): array {
        $config = get_config('mod_board');
        if (isset($config->acceptedfiletypeforcontent)) {
            $extensions = explode(',', $config->acceptedfiletypeforcontent);
        } else {
            $extensions = [];
        }
        return $extensions;
    }

    /**
     * Returns basic options for the image file picker.
     *
     * @return array
     */
    public static function get_image_picker_options(): array {
        $extensions = self::get_accepted_file_extensions();

        $extensions = array_map(function($extension) {
            return '.' . $extension;
        }, $extensions);

        return [
            'accepted_types' => $extensions,
            'maxfiles' => 1,
            'subdirs' => 0,
            'maxbytes' => board::ACCEPTED_FILE_MAX_SIZE,
        ];
    }

    /**
     * Retrieves the file storage settings
     *
     * @param int $noteid
     * @return stdClass|null
     */
    public static function get_file_storage_settings(int $noteid): ?stdClass {
        $note = board::get_note($noteid);
        if (!$note) {
            return null;
        }

        $column = board::get_column($note->columnid);
        if (!$column) {
            return null;
        }

        return (object) [
            'contextid' => board::context_for_board($column->boardid)->id,
            'component' => 'mod_board',
            'filearea'  => 'images',
            'itemid'    => $noteid,
            'filepath'  => '/',
        ];
    }
}
