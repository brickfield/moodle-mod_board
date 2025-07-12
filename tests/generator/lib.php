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

use mod_board\board;

/**
 * Board test generator.
 *
 * @package    mod_board
 * @copyright  2020 onward: Brickfield Education Labs, www.brickfield.ie
 * @author     Jay Churchward (jay@brickfieldlabs.ie)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_board_generator extends testing_module_generator {
    /**
     * @var int keep track of how many columns have been created.
     */
    protected $columncount = 3;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->columncount = 3;
        parent::reset();
    }

    #[\Override]
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;

        // Apply the same defaults as in mod_form.

        if (!isset($record->background_color)) {
            $record->background_color = '';
        }

        if (!isset($record->addrating)) {
            $record->addrating = board::RATINGDISABLED;
        }

        if (!isset($record->hideheaders)) {
            $record->hideheaders = 0;
        }

        if (!isset($record->sortby)) {
            $record->sortby = board::SORTBYNONE;
        }

        if (!isset($record->singleusermode)) {
            $record->singleusermode = board::SINGLEUSER_DISABLED;
        }

        if (!isset($record->userscanedit)) {
            $record->userscanedit = 0;
        }

        if (!isset($record->enableblanktarget)) {
            $record->enableblanktarget = 0;
        }

        if (!empty($record->postby)) {
            $record->postbyenabled = 1;
        }

        return parent::create_instance($record, $options);
    }

    /**
     * Create a new board column.
     *
     * @param array|stdClass|null $record
     * @return stdClass column record
     */
    public function create_column($record = null): stdClass {
        global $DB;

        $record = (object)(array)$record;

        $this->columncount++;

        if (empty($record->boardid)) {
            throw new coding_exception('Column generator requires $record->boardid');
        }

        if (empty($record->name)) {
            $record->name = "Column {$this->columncount}";
        }

        $column = \mod_board\local\column::create($record->boardid, $record->name);
        unset($column->historyid);

        return $column;
    }

    /**
     * Create new a note.
     *
     * @param array|stdClass|null $record
     * @return stdClass column record
     */
    public function create_note($record = null): stdClass {
        global $DB, $USER;

        $record = (object)(array)$record;

        if (empty($record->columnid)) {
            throw new coding_exception('Note generator requires $record->columnid');
        }

        if (empty($record->heading) && empty($record->content)) {
            $record->heading = 'Some note';
        }
        $heading = $record->heading ?? '';
        $content = $record->content ?? '';
        $userid = $record->userid ?? $USER->id;
        $ownerid = $record->ownerid ?? $userid;
        $groupid = $record->groupid ?? 0;
        $attachment = []; // Not supported here for now.

        $id = \mod_board\local\note::create(
            $record->columnid, $ownerid, $groupid, $heading, $content, $attachment, $userid
        )['note']->id;

        return $DB->get_record('board_notes', ['id' => $id], '*', MUST_EXIST);
    }
}
