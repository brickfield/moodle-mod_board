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
 * Board generator tests.
 *
 * @package    mod_board
 * @copyright  2025 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_board_generator
 */
final class generator_test extends \advanced_testcase {
    public function test_plugin_generator(): void {
        /** @var \mod_board_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_board');
        $this->assertInstanceOf(\mod_board_generator::class, $generator);
        $this->assertTrue(method_exists($generator, 'create_instance'));
        $this->assertTrue(method_exists($generator, 'create_column'));
    }

    public function test_create_instance(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([]);

        $this->setCurrentTimeStart();
        $board = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
        ]);
        $this->assertSame($course->id, $board->course);
        $this->assertSame('Board 1', $board->name);
        $this->assertSame('0', $board->hidename);
        $this->assertTimeCurrent($board->timemodified);
        $this->assertSame('Test board 1', $board->intro);
        $this->assertSame(FORMAT_MOODLE, $board->introformat);
        $this->assertSame(null, $board->historyid);
        $this->assertSame('', $board->background_color);
        $this->assertSame('0', $board->addrating);
        $this->assertSame('0', $board->hideheaders);
        $this->assertSame((string)board::SORTBYNONE, $board->sortby);
        $this->assertSame('0', $board->postby);
        $this->assertSame('0', $board->userscanedit);
        $this->assertSame((string)board::SINGLEUSER_DISABLED, $board->singleusermode);
        $this->assertSame('0', $board->enableblanktarget);
        $this->assertSame('0', $board->completionnotes);
        $this->assertSame('0', $board->embed);

        $this->setCurrentTimeStart();
        $board = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
            'name' => 'Board X',
            'hidename' => 1,
            'intro' => 'Some intro',
            'introformat' => FORMAT_HTML,
            'background_color' => '#fff',
            'addrating' => 1,
            'hideheaders' => 1,
            'sortby' => board::SORTBYDATE,
            'postby' => 12345,
            'userscanedit' => 1,
            'singleusermode' => board::SINGLEUSER_PRIVATE,
            'enableblanktarget' => 1,
            'completionnotes' => 1,
        ]);
        $this->assertSame($course->id, $board->course);
        $this->assertSame('Board X', $board->name);
        $this->assertSame('1', $board->hidename);
        $this->assertTimeCurrent($board->timemodified);
        $this->assertSame('Some intro', $board->intro);
        $this->assertSame(FORMAT_HTML, $board->introformat);
        $this->assertSame(null, $board->historyid);
        $this->assertSame('#fff', $board->background_color);
        $this->assertSame('1', $board->addrating);
        $this->assertSame('1', $board->hideheaders);
        $this->assertSame((string)board::SORTBYDATE, $board->sortby);
        $this->assertSame('12345', $board->postby);
        $this->assertSame('1', $board->userscanedit);
        $this->assertSame((string)board::SINGLEUSER_PRIVATE, $board->singleusermode);
        $this->assertSame('1', $board->enableblanktarget);
        $this->assertSame('1', $board->completionnotes);
        $this->assertSame('0', $board->embed);
    }

    public function test_create_column(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([]);

        /** @var \mod_board_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_board');

        $board = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
        ]);

        $column4 = $generator->create_column(['boardid' => $board->id]);
        $this->assertSame($board->id, $column4->boardid);
        $this->assertSame('Column 4', $column4->name);
        $this->assertSame('0', $column4->locked);
        $this->assertSame('4', $column4->sortorder);

        $column5 = $generator->create_column(['boardid' => $board->id, 'name' => 'Col X']);
        $this->assertSame($board->id, $column5->boardid);
        $this->assertSame('Col X', $column5->name);
        $this->assertSame('0', $column5->locked);
        $this->assertSame('5', $column5->sortorder);
    }
}
