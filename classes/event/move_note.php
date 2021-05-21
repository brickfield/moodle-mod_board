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

namespace mod_board\event;
defined('MOODLE_INTERNAL') || die();

class move_note extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'u'; // C(reate), r(ead), u(pdate), d(elete).
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'board';
    }

    public static function get_name() {
        return get_string('event_move_note', 'mod_board');
    }

    public function get_description() {
        $obj = new \stdClass;
        $obj->userid = $this->userid;
        $obj->objectid = $this->objectid;
        $obj->columnid = $this->other['columnid'];
        return get_string('event_move_note_desc', 'mod_board', $obj);
    }

    public function get_legacy_logdata() {
        return array($this->courseid, 'mod_board', 'move_note', null, $this->objectid);
    }
}