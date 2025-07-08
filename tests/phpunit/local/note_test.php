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

use mod_board\local\note;
use mod_board\board;

/**
 * Test note helper class.
 *
 * @package    mod_board
 * @copyright  2025 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_board\local\note
 */
final class note_test extends \advanced_testcase {
    public function test_create(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $board1 = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
        ]);
        $board2 = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_PRIVATE,
        ]);

        list($column1, $column2, $column3)
            = array_values($DB->get_records('board_columns', ['boardid' => $board1->id], 'id ASC'));

        $this->setUser($user1);
        $this->setCurrentTimeStart();

        $result = note::create($column1->id, $user1->id, null, 'NH 1', 'NC 1', ['type' => 0, 'info' => '', 'url' => '']);
        $this->assertSame(true, $result['status']);
        $this->assertNotEmpty($result['historyid']);
        $note1 = $result['note'];
        $this->assertSame($column1->id, $note1->columnid);
        $this->assertSame($user1->id, $note1->ownerid);
        $this->assertSame($user1->id, $note1->userid);
        $this->assertSame(null, $note1->groupid);
        $this->assertSame('NC 1', $note1->content);
        $this->assertSame('NH 1', $note1->heading);
        $this->assertSame('0', $note1->type);
        $this->assertSame(null, $note1->info);
        $this->assertSame(null, $note1->url);
        $this->assertTimeCurrent($note1->timecreated);
        $this->assertSame('0', $note1->sortorder);
        $this->assertSame('0', $note1->deleted);

        $result = note::create($column1->id, $user1->id, $group->id, 'NH 2', '', ['type' => 0, 'info' => '', 'url' => '']);
        $this->assertSame(true, $result['status']);
        $this->assertNotEmpty($result['historyid']);
        $note2 = $result['note'];
        $this->assertSame($column1->id, $note2->columnid);
        $this->assertSame($user1->id, $note2->ownerid);
        $this->assertSame($user1->id, $note2->userid);
        $this->assertSame($group->id, $note2->groupid);
        $this->assertSame('', $note2->content);
        $this->assertSame('NH 2', $note2->heading);
        $this->assertSame('0', $note2->type);
        $this->assertSame(null, $note2->info);
        $this->assertSame(null, $note2->url);
        $this->assertTimeCurrent($note2->timecreated);
        $this->assertSame('1', $note2->sortorder);
        $this->assertSame('0', $note2->deleted);

        list($column1, $column2, $column3)
            = array_values($DB->get_records('board_columns', ['boardid' => $board2->id], 'id ASC'));

        $result = note::create($column1->id, $user1->id, null, '', 'NC 3', ['type' => 0, 'info' => '', 'url' => ''], $user2->id);
        $this->assertSame(true, $result['status']);
        $this->assertNotEmpty($result['historyid']);
        $note3 = $result['note'];
        $this->assertSame($column1->id, $note3->columnid);
        $this->assertSame($user1->id, $note3->ownerid);
        $this->assertSame($user2->id, $note3->userid);
        $this->assertSame(null, $note3->groupid);
        $this->assertSame('NC 3', $note3->content);
        $this->assertSame(null, $note3->heading);
        $this->assertSame('0', $note3->type);
        $this->assertSame(null, $note3->info);
        $this->assertSame(null, $note3->url);
        $this->assertTimeCurrent($note3->timecreated);
        $this->assertSame('0', $note3->sortorder);
        $this->assertSame('0', $note3->deleted);

        $this->setUser($user1);

        list($column1, $column2, $column3)
            = array_values($DB->get_records('board_columns', ['boardid' => $board1->id], 'id ASC'));

        try {
            note::create($column1->id, 0, null, 'NH 1', 'NC 1', ['type' => 0, 'info' => '', 'url' => '']);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $ex);
            $this->assertSame('Invalid parameter value detected (ownerid is required)', $ex->getMessage());
        }

        try {
            note::create($column1->id, $user2->id, null, 'NH 1', 'NC 1', ['type' => 0, 'info' => '', 'url' => '']);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $ex);
            $this->assertSame(
                'Invalid parameter value detected (ownerid must match userid if single user mode disabled)',
                $ex->getMessage());
        }

        $this->setUser(null);

        try {
            note::create($column1->id, $user1->id, null, 'NH 1', 'NC 1', ['type' => 0, 'info' => '', 'url' => '']);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $ex);
            $this->assertSame('Invalid parameter value detected (Invalid userid)', $ex->getMessage());
        }

        $this->setUser($user1);

        try {
            note::create($column1->id, $user1->id, -1, 'NH 1', 'NC 1', ['type' => 0, 'info' => '', 'url' => '']);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $ex);
            $this->assertSame('Invalid parameter value detected (Invalid groupid)', $ex->getMessage());
        }

        list($column1, $column2, $column3)
            = array_values($DB->get_records('board_columns', ['boardid' => $board2->id], 'id ASC'));

        try {
            note::create($column1->id, $user1->id, $group->id, 'NH 2', '', ['type' => 0, 'info' => '', 'url' => '']);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $ex);
            $this->assertSame('Invalid parameter value detected (groupid is not allowed in single user mode)', $ex->getMessage());
        }
    }

    public function test_update(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([]);
        $user1 = $this->getDataGenerator()->create_user();

        $board1 = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
        ]);

        list($column1, $column2, $column3)
            = array_values($DB->get_records('board_columns', ['boardid' => $board1->id], 'id ASC'));

        $this->setUser($user1);

        $note1 = note::create($column1->id, $user1->id, null, 'NH 1', 'NC 1', ['type' => 0, 'info' => '', 'url' => ''])['note'];

        $result = note::update($note1->id, 'NH X', 'NC X', ['type' => 0, 'info' => '', 'url' => '']);
        $this->assertSame(true, $result['status']);
        $this->assertNotEmpty($result['historyid']);
        $note1 = $result['note'];
        $this->assertSame($column1->id, $note1->columnid);
        $this->assertSame($user1->id, $note1->ownerid);
        $this->assertSame($user1->id, $note1->userid);
        $this->assertSame(null, $note1->groupid);
        $this->assertSame('NC X', $note1->content);
        $this->assertSame('NH X', $note1->heading);
        $this->assertSame('0', $note1->type);
        $this->assertSame(null, $note1->info);
        $this->assertSame(null, $note1->url);
        $this->assertSame('0', $note1->sortorder);
        $this->assertSame('0', $note1->deleted);

        $result = note::update($note1->id, 'NH Y', '', ['type' => 0, 'info' => '', 'url' => '']);
        $this->assertSame(true, $result['status']);
        $this->assertNotEmpty($result['historyid']);
        $note1 = $result['note'];
        $this->assertSame($column1->id, $note1->columnid);
        $this->assertSame($user1->id, $note1->ownerid);
        $this->assertSame($user1->id, $note1->userid);
        $this->assertSame(null, $note1->groupid);
        $this->assertSame('', $note1->content);
        $this->assertSame('NH Y', $note1->heading);
        $this->assertSame('0', $note1->type);
        $this->assertSame(null, $note1->info);
        $this->assertSame(null, $note1->url);
        $this->assertSame('0', $note1->sortorder);
        $this->assertSame('0', $note1->deleted);

        $attachment = [
            'type' => 2,
            'info' => 'test info',
            'url' => 'test url',
            'filename' => 'testimage.png',
            'filecontents' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAABpAAAAQaCAIAhEUgAABpAAAAQaCAIAAADL9awBAAAACXBIWXMAASAS', // phpcs:ignore
        ];

        $result = note::update($note1->id, '', 'NC Z', $attachment);
        $this->assertSame(true, $result['status']);
        $this->assertNotEmpty($result['historyid']);
        $note1 = $result['note'];
        $this->assertSame($column1->id, $note1->columnid);
        $this->assertSame($user1->id, $note1->ownerid);
        $this->assertSame($user1->id, $note1->userid);
        $this->assertSame(null, $note1->groupid);
        $this->assertSame('NC Z', $note1->content);
        $this->assertSame(null, $note1->heading);
        $this->assertSame('2', $note1->type);
        $this->assertSame('test info', $note1->info);
        $this->assertSame('test url', $note1->url);
        $this->assertSame('0', $note1->sortorder);
        $this->assertSame('0', $note1->deleted);
    }

    public function test_delete(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([]);
        $user1 = $this->getDataGenerator()->create_user();

        $board1 = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
        ]);

        list($column1, $column2, $column3)
            = array_values($DB->get_records('board_columns', ['boardid' => $board1->id], 'id ASC'));

        $this->setUser($user1);

        $note1 = note::create($column1->id, $user1->id, null, 'NH 1', 'NC 1', ['type' => 0, 'info' => '', 'url' => ''])['note'];
        $note2 = note::create($column1->id, $user1->id, null, 'NH 2', 'NC 2', ['type' => 0, 'info' => '', 'url' => ''])['note'];
        $note3 = note::create($column1->id, $user1->id, null, 'NH 3', 'NC 3', ['type' => 0, 'info' => '', 'url' => ''])['note'];
        $note4 = note::create($column1->id, $user1->id, null, 'NH 4', 'NC 4', ['type' => 0, 'info' => '', 'url' => ''])['note'];

        $result = note::delete($note2->id);
        $this->assertSame(true, $result['status']);
        $this->assertNotEmpty($result['historyid']);

        list($note1, $note2, $note3, $note4)
            = array_values($DB->get_records('board_notes', ['columnid' => $column1->id], 'id ASC'));
        $this->assertSame('0', $note1->deleted);
        $this->assertSame('1', $note2->deleted);
        $this->assertSame('0', $note3->deleted);
        $this->assertSame('0', $note4->deleted);
        $this->assertSame('0', $note1->sortorder);
        $this->assertSame('1', $note2->sortorder);
        $this->assertSame('1', $note3->sortorder);
        $this->assertSame('2', $note4->sortorder);
    }

    public function test_move(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([]);
        $user1 = $this->getDataGenerator()->create_user();

        $board1 = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
        ]);

        list($column1, $column2, $column3)
            = array_values($DB->get_records('board_columns', ['boardid' => $board1->id], 'id ASC'));

        $this->setUser($user1);

        $note1 = note::create($column1->id, $user1->id, null, 'NH 1', 'NC 1', ['type' => 0, 'info' => '', 'url' => ''])['note'];
        $note2 = note::create($column1->id, $user1->id, null, 'NH 2', 'NC 2', ['type' => 0, 'info' => '', 'url' => ''])['note'];
        $note3 = note::create($column1->id, $user1->id, null, 'NH 3', 'NC 3', ['type' => 0, 'info' => '', 'url' => ''])['note'];
        $note4 = note::create($column1->id, $user1->id, null, 'NH 4', 'NC 4', ['type' => 0, 'info' => '', 'url' => ''])['note'];

        $result = note::move($note3->id, $column1->id, 1);
        $this->assertSame(true, $result['status']);
        $this->assertNotEmpty($result['historyid']);
        list($note1, $note2, $note3, $note4)
            = array_values($DB->get_records('board_notes', ['columnid' => $column1->id], 'id ASC'));
        $this->assertSame('0', $note1->sortorder);
        $this->assertSame('1', $note3->sortorder);
        $this->assertSame('2', $note2->sortorder);
        $this->assertSame('3', $note4->sortorder);

        $result = note::move($note3->id, $column1->id, 10);
        $this->assertSame(true, $result['status']);
        $this->assertNotEmpty($result['historyid']);
        list($note1, $note2, $note3, $note4)
            = array_values($DB->get_records('board_notes', ['columnid' => $column1->id], 'id ASC'));
        $this->assertSame('0', $note1->sortorder);
        $this->assertSame('1', $note2->sortorder);
        $this->assertSame('2', $note4->sortorder);
        $this->assertSame('10', $note3->sortorder);

        $result = note::move($note2->id, $column2->id, 0);
        $this->assertSame(true, $result['status']);
        $this->assertNotEmpty($result['historyid']);
        list($note1, $note3, $note4)
            = array_values($DB->get_records('board_notes', ['columnid' => $column1->id], 'id ASC'));
        $this->assertSame('0', $note1->sortorder);
        $this->assertSame('1', $note4->sortorder);
        $this->assertSame('9', $note3->sortorder);
        list($note2)
            = array_values($DB->get_records('board_notes', ['columnid' => $column2->id], 'id ASC'));
        $this->assertSame('0', $note2->sortorder);
    }

    public function test_can_rate(): void {
        global $DB;

        $this->resetAfterTest();

        /** @var \mod_board_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_board');

        $course = $this->getDataGenerator()->create_course([]);
        $board0 = $this->getDataGenerator()->create_module('board', [
            'name' => 'Board 1',
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
            'groupmode' => NOGROUPS,
            'addrating' => board::RATINGDISABLED,
        ]);
        $board1 = $this->getDataGenerator()->create_module('board', [
            'name' => 'Board 1',
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
            'groupmode' => NOGROUPS,
            'addrating' => board::RATINGBYALL,
        ]);
        $board2 = $this->getDataGenerator()->create_module('board', [
            'name' => 'Board 2',
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_PRIVATE,
            'groupmode' => NOGROUPS,
            'addrating' => board::RATINGBYALL,
        ]);
        $board3 = $this->getDataGenerator()->create_module('board', [
            'name' => 'Board 3',
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_PUBLIC,
            'groupmode' => NOGROUPS,
            'addrating' => board::RATINGBYALL,
        ]);
        $board4 = $this->getDataGenerator()->create_module('board', [
            'name' => 'Board 4',
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
            'groupmode' => SEPARATEGROUPS,
            'addrating' => board::RATINGBYALL,
        ]);
        $board5 = $this->getDataGenerator()->create_module('board', [
            'name' => 'Board 4',
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
            'groupmode' => VISIBLEGROUPS,
            'addrating' => board::RATINGBYALL,
        ]);

        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $teacher0 = $this->getDataGenerator()->create_user();
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $student3 = $this->getDataGenerator()->create_user();
        $student4 = $this->getDataGenerator()->create_user();
        $student5 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($teacher0->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($student3->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($student4->id, $course->id, 'guest');

        $this->getDataGenerator()->create_group_member(['userid' => $student1->id, 'groupid' => $group1->id]);
        $this->getDataGenerator()->create_group_member(['userid' => $student2->id, 'groupid' => $group2->id]);
        $this->getDataGenerator()->create_group_member(['userid' => $student3->id, 'groupid' => $group1->id]);

        $columns0 = array_values($DB->get_records('board_columns', ['boardid' => $board0->id], 'id ASC'));
        $columns1 = array_values($DB->get_records('board_columns', ['boardid' => $board1->id], 'id ASC'));
        $columns2 = array_values($DB->get_records('board_columns', ['boardid' => $board2->id], 'id ASC'));
        $columns3 = array_values($DB->get_records('board_columns', ['boardid' => $board3->id], 'id ASC'));
        $columns4 = array_values($DB->get_records('board_columns', ['boardid' => $board4->id], 'id ASC'));
        $columns5 = array_values($DB->get_records('board_columns', ['boardid' => $board5->id], 'id ASC'));

        $note0x1 = $generator->create_note(['columnid' => $columns0[0]->id, 'userid' => $student1->id]);
        $note1x1 = $generator->create_note(['columnid' => $columns1[0]->id, 'userid' => $student1->id]);
        $note2x1 = $generator->create_note(['columnid' => $columns2[0]->id, 'userid' => $student1->id]);
        $note3x1 = $generator->create_note(['columnid' => $columns3[0]->id, 'userid' => $student1->id]);
        $note4x1 = $generator->create_note(['columnid' => $columns4[0]->id, 'userid' => $student1->id, 'groupid' => $group1->id]);
        $note5x1 = $generator->create_note(['columnid' => $columns5[0]->id, 'userid' => $student1->id, 'groupid' => $group1->id]);

        $note0x0 = $generator->create_note(['columnid' => $columns0[0]->id, 'userid' => $teacher0->id]);
        $note1x0 = $generator->create_note(['columnid' => $columns1[0]->id, 'userid' => $teacher0->id]);
        $note2x0 = $generator->create_note(['columnid' => $columns2[0]->id, 'userid' => $teacher0->id, 'ownerid' => $student1->id]);
        $note3x0 = $generator->create_note(['columnid' => $columns3[0]->id, 'userid' => $teacher0->id, 'ownerid' => $student1->id]);
        $note4x0 = $generator->create_note(['columnid' => $columns4[0]->id, 'userid' => $teacher0->id, 'groupid' => 0]);
        $note4x0a = $generator->create_note(['columnid' => $columns4[0]->id, 'userid' => $teacher0->id, 'groupid' => $group1->id]);
        $note4x0b = $generator->create_note(['columnid' => $columns4[0]->id, 'userid' => $teacher0->id, 'groupid' => $group2->id]);
        $note5x0 = $generator->create_note(['columnid' => $columns5[0]->id, 'userid' => $teacher0->id, 'groupid' => 0]);
        $note5x0a = $generator->create_note(['columnid' => $columns5[0]->id, 'userid' => $teacher0->id, 'groupid' => $group1->id]);
        $note5x0b = $generator->create_note(['columnid' => $columns5[0]->id, 'userid' => $teacher0->id, 'groupid' => $group2->id]);

        $this->setUser($student1);
        $this->assertFalse(note::can_rate($note0x0->id));
        $this->assertTrue(note::can_rate($note1x0->id));
        $this->assertTrue(note::can_rate($note2x0->id));
        $this->assertTrue(note::can_rate($note3x0->id));
        $this->assertFalse(note::can_rate($note4x0->id));
        $this->assertTrue(note::can_rate($note4x0a->id));
        $this->assertFalse(note::can_rate($note4x0b->id));
        $this->assertFalse(note::can_rate($note5x0->id));
        $this->assertTrue(note::can_rate($note5x0a->id));
        $this->assertFalse(note::can_rate($note5x0b->id));
        $this->assertFalse(note::can_rate($note0x1->id));
        $this->assertTrue(note::can_rate($note1x1->id));
        $this->assertTrue(note::can_rate($note2x1->id));
        $this->assertTrue(note::can_rate($note3x1->id));
        $this->assertTrue(note::can_rate($note4x1->id));
        $this->assertTrue(note::can_rate($note5x1->id));

        $this->setUser($student2);
        $this->assertFalse(note::can_rate($note0x0->id));
        $this->assertTrue(note::can_rate($note1x0->id));
        $this->assertFalse(note::can_rate($note2x0->id));
        $this->assertTrue(note::can_rate($note3x0->id));
        $this->assertFalse(note::can_rate($note4x0->id));
        $this->assertFalse(note::can_rate($note4x0a->id));
        $this->assertTrue(note::can_rate($note4x0b->id));
        $this->assertFalse(note::can_rate($note5x0->id));
        $this->assertFalse(note::can_rate($note5x0a->id));
        $this->assertTrue(note::can_rate($note5x0b->id));
        $this->assertFalse(note::can_rate($note0x1->id));
        $this->assertTrue(note::can_rate($note1x1->id));
        $this->assertFalse(note::can_rate($note2x1->id));
        $this->assertTrue(note::can_rate($note3x1->id));
        $this->assertFalse(note::can_rate($note4x1->id));
        $this->assertFalse(note::can_rate($note5x1->id));

        $this->setUser($teacher0);
        $this->assertFalse(note::can_rate($note0x0->id));
        $this->assertTrue(note::can_rate($note1x0->id));
        $this->assertTrue(note::can_rate($note2x0->id));
        $this->assertTrue(note::can_rate($note3x0->id));
        $this->assertTrue(note::can_rate($note4x0->id));
        $this->assertTrue(note::can_rate($note4x0a->id));
        $this->assertTrue(note::can_rate($note4x0b->id));
        $this->assertTrue(note::can_rate($note5x0->id));
        $this->assertTrue(note::can_rate($note5x0a->id));
        $this->assertTrue(note::can_rate($note5x0b->id));
        $this->assertFalse(note::can_rate($note0x1->id));
        $this->assertTrue(note::can_rate($note1x1->id));
        $this->assertTrue(note::can_rate($note2x1->id));
        $this->assertTrue(note::can_rate($note3x1->id));
        $this->assertTrue(note::can_rate($note4x1->id));
        $this->assertTrue(note::can_rate($note5x1->id));

        $DB->set_field('board', 'addrating', board::RATINGBYSTUDENTS);

        $this->setUser($student1);
        $this->assertTrue(note::can_rate($note0x0->id));
        $this->assertTrue(note::can_rate($note1x0->id));
        $this->assertTrue(note::can_rate($note2x0->id));
        $this->assertTrue(note::can_rate($note3x0->id));
        $this->assertFalse(note::can_rate($note4x0->id));
        $this->assertTrue(note::can_rate($note4x0a->id));
        $this->assertFalse(note::can_rate($note4x0b->id));
        $this->assertFalse(note::can_rate($note5x0->id));
        $this->assertTrue(note::can_rate($note5x0a->id));
        $this->assertFalse(note::can_rate($note5x0b->id));
        $this->assertTrue(note::can_rate($note0x1->id));
        $this->assertTrue(note::can_rate($note1x1->id));
        $this->assertTrue(note::can_rate($note2x1->id));
        $this->assertTrue(note::can_rate($note3x1->id));
        $this->assertTrue(note::can_rate($note4x1->id));
        $this->assertTrue(note::can_rate($note5x1->id));

        $this->setUser($student2);
        $this->assertTrue(note::can_rate($note0x0->id));
        $this->assertTrue(note::can_rate($note1x0->id));
        $this->assertFalse(note::can_rate($note2x0->id));
        $this->assertTrue(note::can_rate($note3x0->id));
        $this->assertFalse(note::can_rate($note4x0->id));
        $this->assertFalse(note::can_rate($note4x0a->id));
        $this->assertTrue(note::can_rate($note4x0b->id));
        $this->assertFalse(note::can_rate($note5x0->id));
        $this->assertFalse(note::can_rate($note5x0a->id));
        $this->assertTrue(note::can_rate($note5x0b->id));
        $this->assertTrue(note::can_rate($note0x1->id));
        $this->assertTrue(note::can_rate($note1x1->id));
        $this->assertFalse(note::can_rate($note2x1->id));
        $this->assertTrue(note::can_rate($note3x1->id));
        $this->assertFalse(note::can_rate($note4x1->id));
        $this->assertFalse(note::can_rate($note5x1->id));

        $this->setUser($teacher0);
        $this->assertFalse(note::can_rate($note0x0->id));
        $this->assertFalse(note::can_rate($note1x0->id));
        $this->assertFalse(note::can_rate($note2x0->id));
        $this->assertFalse(note::can_rate($note3x0->id));
        $this->assertFalse(note::can_rate($note4x0->id));
        $this->assertFalse(note::can_rate($note4x0a->id));
        $this->assertFalse(note::can_rate($note4x0b->id));
        $this->assertFalse(note::can_rate($note5x0->id));
        $this->assertFalse(note::can_rate($note5x0a->id));
        $this->assertFalse(note::can_rate($note5x0b->id));
        $this->assertFalse(note::can_rate($note0x1->id));
        $this->assertFalse(note::can_rate($note1x1->id));
        $this->assertFalse(note::can_rate($note2x1->id));
        $this->assertFalse(note::can_rate($note3x1->id));
        $this->assertFalse(note::can_rate($note4x1->id));
        $this->assertFalse(note::can_rate($note5x1->id));

        $DB->set_field('board', 'addrating', board::RATINGBYTEACHERS);

        $this->setUser($student1);
        $this->assertFalse(note::can_rate($note0x0->id));
        $this->assertFalse(note::can_rate($note1x0->id));
        $this->assertFalse(note::can_rate($note2x0->id));
        $this->assertFalse(note::can_rate($note3x0->id));
        $this->assertFalse(note::can_rate($note4x0->id));
        $this->assertFalse(note::can_rate($note4x0a->id));
        $this->assertFalse(note::can_rate($note4x0b->id));
        $this->assertFalse(note::can_rate($note5x0->id));
        $this->assertFalse(note::can_rate($note5x0a->id));
        $this->assertFalse(note::can_rate($note5x0b->id));
        $this->assertFalse(note::can_rate($note0x1->id));
        $this->assertFalse(note::can_rate($note1x1->id));
        $this->assertFalse(note::can_rate($note2x1->id));
        $this->assertFalse(note::can_rate($note3x1->id));
        $this->assertFalse(note::can_rate($note4x1->id));
        $this->assertFalse(note::can_rate($note5x1->id));

        $this->setUser($student2);
        $this->assertFalse(note::can_rate($note0x0->id));
        $this->assertFalse(note::can_rate($note1x0->id));
        $this->assertFalse(note::can_rate($note2x0->id));
        $this->assertFalse(note::can_rate($note3x0->id));
        $this->assertFalse(note::can_rate($note4x0->id));
        $this->assertFalse(note::can_rate($note4x0a->id));
        $this->assertFalse(note::can_rate($note4x0b->id));
        $this->assertFalse(note::can_rate($note5x0->id));
        $this->assertFalse(note::can_rate($note5x0a->id));
        $this->assertFalse(note::can_rate($note5x0b->id));
        $this->assertFalse(note::can_rate($note0x1->id));
        $this->assertFalse(note::can_rate($note1x1->id));
        $this->assertFalse(note::can_rate($note2x1->id));
        $this->assertFalse(note::can_rate($note3x1->id));
        $this->assertFalse(note::can_rate($note4x1->id));
        $this->assertFalse(note::can_rate($note5x1->id));

        $this->setUser($teacher0);
        $this->assertTrue(note::can_rate($note0x0->id));
        $this->assertTrue(note::can_rate($note1x0->id));
        $this->assertTrue(note::can_rate($note2x0->id));
        $this->assertTrue(note::can_rate($note3x0->id));
        $this->assertTrue(note::can_rate($note4x0->id));
        $this->assertTrue(note::can_rate($note4x0a->id));
        $this->assertTrue(note::can_rate($note4x0b->id));
        $this->assertTrue(note::can_rate($note5x0->id));
        $this->assertTrue(note::can_rate($note5x0a->id));
        $this->assertTrue(note::can_rate($note5x0b->id));
        $this->assertTrue(note::can_rate($note0x1->id));
        $this->assertTrue(note::can_rate($note1x1->id));
        $this->assertTrue(note::can_rate($note2x1->id));
        $this->assertTrue(note::can_rate($note3x1->id));
        $this->assertTrue(note::can_rate($note4x1->id));
        $this->assertTrue(note::can_rate($note5x1->id));
    }

    public function test_rate(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $board1 = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
        ]);
        $board2 = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
        ]);

        list($column1, $column2, $column3)
            = array_values($DB->get_records('board_columns', ['boardid' => $board1->id], 'id ASC'));

        $this->setUser($user1);

        $note1 = note::create($column1->id, $user1->id, null, 'NH 1', 'NC 1', ['type' => 0, 'info' => '', 'url' => ''])['note'];
        $note2 = note::create($column1->id, $user1->id, null, 'NH 2', 'NC 2', ['type' => 0, 'info' => '', 'url' => ''])['note'];

        $this->setUser($user1);
        $result = note::rate($note1->id);
        $this->assertTrue($result['status']);
        $this->assertNotEmpty($result['historyid']);
        $this->assertSame(1, $result['rating']);

        $this->setUser($user2);
        $result = note::rate($note1->id);
        $this->assertTrue($result['status']);
        $this->assertNotEmpty($result['historyid']);
        $this->assertSame(2, $result['rating']);
        $result = note::rate($note1->id);
        $this->assertTrue($result['status']);
        $this->assertNotEmpty($result['historyid']);
        $this->assertSame(1, $result['rating']);
    }

    public function test_get_rating(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $board1 = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
        ]);
        $board2 = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
        ]);

        list($column1, $column2, $column3)
            = array_values($DB->get_records('board_columns', ['boardid' => $board1->id], 'id ASC'));

        $this->setUser($user1);

        $note1 = note::create($column1->id, $user1->id, null, 'NH 1', 'NC 1', ['type' => 0, 'info' => '', 'url' => ''])['note'];
        $note2 = note::create($column1->id, $user1->id, null, 'NH 2', 'NC 2', ['type' => 0, 'info' => '', 'url' => ''])['note'];

        $this->setUser($user1);
        $this->assertSame(0, note::get_rating($note1->id));
        note::rate($note1->id);
        $this->assertSame(1, note::get_rating($note1->id));

        $this->setUser($user2);
        note::rate($note1->id);
        $this->assertSame(2, note::get_rating($note1->id));
    }
}
