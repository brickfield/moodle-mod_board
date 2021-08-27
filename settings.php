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
 * The plugin settings.
 * @package     mod_board
 * @author      Karen Holland <karen@brickfieldlabs.ie>
 * @copyright   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_board\board;

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_description('mod_board/logo', '',
        html_writer::img($OUTPUT->image_url('brickfield-logo-small', 'mod_board'), 'logo',
            ['style' => 'display: block; margin: -50px auto -30px auto; float: right;'])));

    $settings->add(new admin_setting_configtext('mod_board/new_column_icon', get_string('new_column_icon', 'mod_board'),
                       get_string('new_column_icon_desc', 'mod_board'), 'fa-plus', PARAM_RAW_TRIMMED));

    $settings->add(new admin_setting_configtext('mod_board/new_note_icon', get_string('new_note_icon', 'mod_board'),
                       get_string('new_note_icon_desc', 'mod_board'), 'fa-plus', PARAM_RAW_TRIMMED));

    $options = array(
        1 => get_string('media_selection_buttons', 'mod_board'),
        2 => get_string('media_selection_dropdown', 'mod_board')
    );
    $settings->add(new admin_setting_configselect('mod_board/media_selection', get_string('media_selection', 'mod_board'),
                       get_string('media_selection_desc', 'mod_board'), 1, $options));

    $settings->add(new admin_setting_configtext('mod_board/post_max_length', get_string('post_max_length', 'mod_board'),
                       get_string('post_max_length_desc', 'mod_board'), 250, PARAM_INT));

    $settings->add(new admin_setting_configtext('mod_board/history_refresh', get_string('history_refresh', 'mod_board'),
                       get_string('history_refresh_desc', 'mod_board'), 60, PARAM_INT));

    $settings->add(new admin_setting_configtextarea(
        'mod_board/column_colours',
        get_string('column_colours', 'mod_board'),
        get_string('column_colours_desc', 'mod_board'),
        implode("\n", board::get_default_colours()),
        PARAM_TEXT
        )
    );

}
