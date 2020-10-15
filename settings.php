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

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('new_column_icon', get_string('new_column_icon', 'mod_board'),
                       get_string('new_column_icon_desc', 'mod_board'), 'fa-plus', PARAM_RAW));

    $settings->add(new admin_setting_configtext('new_note_icon', get_string('new_note_icon', 'mod_board'),
                       get_string('new_note_icon_desc', 'mod_board'), 'fa-plus', PARAM_RAW));
}
