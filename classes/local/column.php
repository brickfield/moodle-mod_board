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

/**
 * Column helper class.
 *
 * @package    mod_board
 * @copyright  2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class column {
    /**
     * Adds a column to the board
     *
     * @param int $boardid
     * @param string $name
     * @return array
     */
    public static function create(int $boardid, string $name): array {
        global $DB, $USER;

        $name = mb_substr($name, 0, board::LENGTH_COLNAME);

        $transaction = $DB->start_delegated_transaction();

        $maxsortorder = $DB->get_field('board_columns', 'MAX(sortorder)', ['boardid' => $boardid]);

        $columnid = $DB->insert_record('board_columns', ['boardid' => $boardid, 'name' => $name,
            'sortorder' => $maxsortorder + 1]);
        $historyid = $DB->insert_record('board_history', ['boardid' => $boardid, 'action' => 'add_column',
            'ownerid' => 0, 'userid' => $USER->id, 'content' => json_encode(['id' => $columnid, 'name' => $name]),
            'timecreated' => time()]);
        $DB->update_record('board', ['id' => $boardid, 'historyid' => $historyid]);
        $transaction->allow_commit();

        self::board_add_column_log($boardid, $name, $columnid);

        board::clear_history();
        return ['id' => $columnid, 'historyid' => $historyid];
    }

    /**
     * Triggers the add column event log.
     *
     * @param int $boardid
     * @param string $name
     * @param int $columnid
     * @return void
     */
    protected static function board_add_column_log($boardid, $name, $columnid) {
        if (!get_config('mod_board', 'addcolumnnametolog')) {
            $name = '';
        }
        $event = \mod_board\event\add_column::create([
            'objectid' => $columnid,
            'context' => \context_module::instance(board::coursemodule_for_board(board::get_board($boardid))->id),
            'other' => ['name' => $name],
        ]);
        $event->trigger();
    }

    /**
     * Updates the column.
     *
     * @param int $id
     * @param string $name
     * @return array
     */
    public static function update(int $id, string $name): array {
        global $DB, $USER;

        $name = mb_substr($name, 0, board::LENGTH_COLNAME);

        $boardid = $DB->get_field('board_columns', 'boardid', ['id' => $id]);
        if ($boardid) {
            $transaction = $DB->start_delegated_transaction();
            $update = $DB->update_record('board_columns', ['id' => $id, 'name' => $name]);
            $historyid = $DB->insert_record('board_history', ['boardid' => $boardid, 'action' => 'update_column',
                'ownerid' => 0, 'userid' => $USER->id, 'content' => json_encode(['id' => $id, 'name' => $name]),
                'timecreated' => time()]);
            $DB->update_record('board', ['id' => $id, 'historyid' => $historyid]);
            $transaction->allow_commit();

            self::board_update_column_log($boardid, $name, $id);
        } else {
            $update = false;
            $historyid = 0;
        }

        board::clear_history();
        return ['status' => $update, 'historyid' => $historyid];
    }

    /**
     * Triggers the update column log.
     *
     * @param int $boardid
     * @param string $name
     * @param int $columnid
     * @return void
     */
    protected static function board_update_column_log($boardid, $name, $columnid) {
        if (!get_config('mod_board', 'addcolumnnametolog')) {
            $name = '';
        }
        $event = \mod_board\event\update_column::create([
            'objectid' => $columnid,
            'context' => \context_module::instance(board::coursemodule_for_board(board::get_board($boardid))->id),
            'other' => ['name' => $name],
        ]);
        $event->trigger();
    }

    /**
     * Deletes a column.
     *
     * @param int $id
     * @return array
     */
    public static function delete(int $id): array {
        global $DB, $USER;

        $boardid = $DB->get_field('board_columns', 'boardid', ['id' => $id]);
        if ($boardid) {
            $transaction = $DB->start_delegated_transaction();
            $notes = $DB->get_records('board_notes', ['columnid' => $id]);
            foreach ($notes as $noteid => $note) {
                $DB->delete_records('board_note_ratings', ['noteid' => $note->id]);
                $DB->update_record('board_notes', ['id' => $note->id, 'deleted' => 1]);
                board::delete_note_file($note->id);
            }
            $delete = $DB->delete_records('board_columns', ['id' => $id]);
            $historyid = $DB->insert_record('board_history', ['boardid' => $boardid, 'action' => 'delete_column',
                'ownerid' => 0, 'content' => json_encode(['id' => $id]),
                'userid' => $USER->id, 'timecreated' => time()]);
            $DB->update_record('board', ['id' => $boardid, 'historyid' => $historyid]);
            $transaction->allow_commit();

            $event = \mod_board\event\delete_column::create([
                'objectid' => $id,
                'context' => \context_module::instance(board::coursemodule_for_board(board::get_board($boardid))->id),
            ]);
            $event->trigger();

        } else {
            $delete = false;
            $historyid = 0;
        }

        board::clear_history();
        return ['status' => $delete, 'historyid' => $historyid];
    }

    /**
     * Locks a columns
     *
     * @param int $id
     * @param bool $locked True to lock the column, false to unlock it.
     * @return array
     */
    public static function lock(int $id, bool $locked): array {
        global $DB, $USER;

        $boardid = $DB->get_field('board_columns', 'boardid', ['id' => $id]);

        $result = $DB->set_field('board_columns', 'locked', $locked, ['id' => $id]);
        $historyid = $DB->insert_record('board_history', ['boardid' => $boardid, 'action' => 'lock_column',
            'content' => json_encode(['id' => $id, 'locked' => $locked]),
            'userid' => $USER->id, 'timecreated' => time()]);
        return ['status' => $result, 'historyid' => $historyid];
    }

    /**
     * Moves a column to a new position.
     *
     * @param int $id the column id
     * @param int $sortorder the new sortorder
     */
    public static function move(int $id, int $sortorder): array {
        global $DB, $USER;

        $boardid = $DB->get_field('board_columns', 'boardid', ['id' => $id]);

        $columns = $DB->get_records('board_columns', ['boardid' => $boardid], 'sortorder ASC, id ASC');
        board::repositionan_array_element($columns, $id, $sortorder);
        $sortorder = 1;
        $neworder = [];
        foreach ($columns as $column) {
            $column->sortorder = $sortorder++;
            $neworder[] = $column->id;
            $DB->update_record('board_columns', $column);
        }
        $historyid = $DB->insert_record('board_history', [
            'boardid' => $column->boardid, 'action' => 'move_column',
            'content' => json_encode(['sortorder' => $neworder]),
            'userid' => $USER->id, 'timecreated' => time()]);
        return ['status' => true, 'historyid' => $historyid];
    }
}
