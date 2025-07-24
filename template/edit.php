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
 * Create or update a template.
 *
 * @package    mod_board
 * @copyright  2025 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_board\local\template;

require('../../../config.php');
require_once("$CFG->libdir/filelib.php");

$id = required_param('id', PARAM_INT);

$syscontext = context_system::instance();

require_login();
require_capability('mod/board:managetemplates', $syscontext);

$pageurl = new moodle_url('/mod/board/template/edit.php', ['id' => $id]);
$returnurl = new moodle_url('/mod/board/template/index.php');
if ($id) {
    $title = get_string('template_update', 'mod_board');
} else {
    $title = get_string('template_create', 'mod_board');
}
template::setup_management_page($pageurl, $title);

if ($id) {
    $template = $DB->get_record('board_templates', ['id' => $id], '*', MUST_EXIST);
    $settings = template::get_settings($template->jsonsettings);
    $template = (object)((array)$template + $settings);
    file_prepare_standard_editor($template, 'description', []);
} else {
    $template = (object)[
        'id' => '0',
        'name' => '',
        'columns' => '',
        'contextid' => $syscontext->id,
    ];
}

$form = new \mod_board\local\form\template_edit(null, ['id' => $id, 'contextid' => $template->contextid]);
$form->set_data($template);

if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    if ($data->id) {
        template::update($data);
    } else {
        template::create($data);
    }
    redirect($returnurl);
}

echo $OUTPUT->header();

$form->display();

echo $OUTPUT->footer();
