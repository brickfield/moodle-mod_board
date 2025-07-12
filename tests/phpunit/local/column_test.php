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

namespace mod_board\phpunit\local;

use mod_board\local\column;
use mod_board\board;

/**
 * Test column helper class.
 *
 * @package    mod_board
 * @copyright  2025 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_board\local\column
 */
final class column_test extends \advanced_testcase {
    public function test_create(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([]);

        $board = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
        ]);

        $column4 = column::create($board->id, 'Col X');
        $this->assertNotEmpty($column4->historyid);
        $this->assertSame($board->id, $column4->boardid);
        $this->assertSame('Col X', $column4->name);
        $this->assertSame('0', $column4->locked);
        $this->assertSame('4', $column4->sortorder);

        unset($column4->historyid);
        $this->assertEquals($column4, $DB->get_record('board_columns', ['id' => $column4->id]));
    }

    public function test_update(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([]);

        /** @var \mod_board_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_board');

        $board = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
        ]);
        $column4 = $generator->create_column(['boardid' => $board->id, 'name' => 'Col X']);

        $column4 = column::update($column4->id, 'Col Y');
        $this->assertNotEmpty($column4->historyid);
        $this->assertSame($board->id, $column4->boardid);
        $this->assertSame('Col Y', $column4->name);
        $this->assertSame('0', $column4->locked);
        $this->assertSame('4', $column4->sortorder);

        unset($column4->historyid);
        $this->assertEquals($column4, $DB->get_record('board_columns', ['id' => $column4->id]));
    }

    public function test_lock(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([]);

        /** @var \mod_board_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_board');

        $board = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
        ]);
        $column4 = $generator->create_column(['boardid' => $board->id, 'name' => 'Col X']);

        $historyid = column::lock($column4->id, true);
        $this->assertNotEmpty($historyid);
        $column4 = $DB->get_record('board_columns', ['id' => $column4->id], '*', MUST_EXIST);
        $this->assertSame($board->id, $column4->boardid);
        $this->assertSame('Col X', $column4->name);
        $this->assertSame('1', $column4->locked);
        $this->assertSame('4', $column4->sortorder);

        $historyid = column::lock($column4->id, false);
        $this->assertNotEmpty($historyid);
        $column4 = $DB->get_record('board_columns', ['id' => $column4->id], '*', MUST_EXIST);
        $this->assertSame($board->id, $column4->boardid);
        $this->assertSame('Col X', $column4->name);
        $this->assertSame('0', $column4->locked);
        $this->assertSame('4', $column4->sortorder);
    }

    public function test_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([]);
        $user = $this->getDataGenerator()->create_user();

        /** @var \mod_board_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_board');

        $board = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
        ]);
        $column4 = $generator->create_column(['boardid' => $board->id, 'name' => 'Col X']);
        $note = $generator->create_note(['columnid' => $column4->id, 'userid' => $user->id]);

        $historyid = column::delete($column4->id);
        $this->assertNotEmpty($historyid);
        $this->assertFalse($DB->record_exists('board_columns', ['id' => $column4->id]));
        $note = $DB->get_record('board_notes', ['id' => $note->id], '*', MUST_EXIST);
        $this->assertSame('1', $note->deleted);
    }

    public function test_move(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([]);

        /** @var \mod_board_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_board');

        $board = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
        ]);
        $generator->create_column(['boardid' => $board->id, 'name' => 'Col X']);
        list($column1, $column2, $column3, $column4)
            = array_values($DB->get_records('board_columns', ['boardid' => $board->id], 'id ASC'));
        $this->assertSame('1', $column1->sortorder);
        $this->assertSame('2', $column2->sortorder);
        $this->assertSame('3', $column3->sortorder);
        $this->assertSame('4', $column4->sortorder);

        $historyid = column::move($column4->id, 2);
        $this->assertNotEmpty($historyid);
        list($column1, $column2, $column3, $column4)
            = array_values($DB->get_records('board_columns', ['boardid' => $board->id], 'id ASC'));
        $this->assertSame('1', $column1->sortorder);
        $this->assertSame('2', $column2->sortorder);
        $this->assertSame('3', $column4->sortorder);
        $this->assertSame('4', $column3->sortorder);

        $historyid = column::move($column4->id, 2);
        $this->assertNotEmpty($historyid);        list($column1, $column2, $column3, $column4)
            = array_values($DB->get_records('board_columns', ['boardid' => $board->id], 'id ASC'));
        $this->assertSame('1', $column1->sortorder);
        $this->assertSame('2', $column2->sortorder);
        $this->assertSame('3', $column4->sortorder);
        $this->assertSame('4', $column3->sortorder);

        $historyid = column::move($column4->id, 0);
        $this->assertNotEmpty($historyid);
        list($column1, $column2, $column3, $column4)
            = array_values($DB->get_records('board_columns', ['boardid' => $board->id], 'id ASC'));
        $this->assertSame('1', $column4->sortorder);
        $this->assertSame('2', $column1->sortorder);
        $this->assertSame('3', $column2->sortorder);
        $this->assertSame('4', $column3->sortorder);

        $historyid = column::move($column4->id, 10);
        $this->assertNotEmpty($historyid);
        list($column1, $column2, $column3, $column4)
            = array_values($DB->get_records('board_columns', ['boardid' => $board->id], 'id ASC'));
        $this->assertSame('1', $column1->sortorder);
        $this->assertSame('2', $column2->sortorder);
        $this->assertSame('3', $column3->sortorder);
        $this->assertSame('4', $column4->sortorder);
    }
}
