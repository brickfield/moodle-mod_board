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

require_once($CFG->dirroot.'/course/moodleform_mod.php');

use mod_board\board;

/**
 * The mod form.
 * @package     mod_board
 * @author      Karen Holland <karen@brickfieldlabs.ie>
 * @copyright   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_board_mod_form extends moodleform_mod {
    /**
     * The definition function.
     */
    public function definition() {
        global $CFG, $DB;

        $mform = $this->_form;

        require_once('classes/board.php');

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), array('size' => '50'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'board', get_string('boardsettings', 'mod_board'));

        $mform->addElement('text', 'background_color', get_string('background_color', 'mod_board'), array('size' => '50'));
        $mform->setType('background_color', PARAM_TEXT);
        $mform->addRule('background_color', get_string('maximumchars', '', 9), 'maxlength', 9, 'client');
        $mform->addHelpButton('background_color', 'background_color', 'mod_board');

        $filemanageroptions = array();
        $filemanageroptions['accepted_types'] = array('.png', '.jpg', '.jpeg', '.bmp');
        $filemanageroptions['maxbytes'] = 0;
        $filemanageroptions['maxfiles'] = 1;
        $filemanageroptions['subdirs'] = 0;
        $mform->addElement('filemanager', 'background_image',
                get_string('background_image', 'mod_board'), null, $filemanageroptions);

        $mform->addElement('select', 'addrating', get_string('addrating', 'mod_board'),
           array(
                board::RATINGDISABLED => get_string('addrating_none', 'mod_board'),
                board::RATINGBYSTUDENTS => get_string('addrating_students', 'mod_board'),
                board::RATINGBYTEACHERS => get_string('addrating_teachers', 'mod_board'),
                board::RATINGBYALL => get_string('addrating_all', 'mod_board')
            )
        );
        $mform->setType('addrating', PARAM_INT);

        $mform->addElement('checkbox', 'hideheaders', get_string('hideheaders', 'mod_board'));
        $mform->setType('hideheaders', PARAM_INT);

        $mform->addElement('select', 'sortby', get_string('sortby', 'mod_board'),
           array(
                board::SORTBYNONE => get_string('sortbynone', 'mod_board'),
                board::SORTBYDATE => get_string('sortbydate', 'mod_board'),
                board::SORTBYRATING => get_string('sortbyrating', 'mod_board')
            )
        );
        $mform->setType('sortby', PARAM_INT);

        $mform->addElement('checkbox', 'postbyenabled', get_string('postbyenabled', 'mod_board'));
        $mform->addElement('date_time_selector', 'postby', get_string('postbydate', 'mod_board'));
        $mform->hideIf('postby', 'postbyenabled', 'notchecked');

        $mform->addElement('advcheckbox', 'userscanedit', get_string('userscanedit', 'mod_board'));

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();

        $mform->addElement('hidden', 'revision');
        $mform->setType('revision', PARAM_INT);
        $mform->setDefault('revision', 1);
    }

    /**
     * Preprocess the data.
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        $draftitemid = file_get_submitted_draft_itemid('background_image');
        file_prepare_draft_area($draftitemid, $this->context->id, 'mod_board', 'background', 0,
            array('subdirs' => 0, 'maxfiles' => 1));
        $defaultvalues['background_image'] = $draftitemid;

        $defaultvalues['postbyenabled'] = !empty($defaultvalues['postby']);
    }

    /**
     * Validate the data.
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['groupmode']) && empty($data['groupingid'])) {
            $errors['groupingid'] = get_string('groupingid_required', 'mod_board');
        }

        return $errors;
    }
}

