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

        $extensions = board::get_accepted_file_extensions();

        $extensions = array_map(function($extension) {
            return '.' . $extension;
        }, $extensions);

        $filemanageroptions = array();
        $filemanageroptions['accepted_types'] = $extensions;
        $filemanageroptions['maxbytes'] = 0;
        $filemanageroptions['maxfiles'] = 1;
        $filemanageroptions['subdirs'] = 0;
        $mform->addElement('filemanager', 'background_image',
                get_string('background_image', 'mod_board'), null, $filemanageroptions);

        $mform->addElement('advcheckbox', 'showauthorofnote', get_string('showauthorofnote', 'mod_board'));
        $mform->addHelpButton('showauthorofnote', 'showauthorofnote', 'mod_board');
        $mform->setDefault('showauthorofnote', 0);
        $mform->setType('showauthorofnote', PARAM_INT);

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

        $boardhasnotes = (!empty($this->_cm) && board::board_has_notes($this->_cm->instance));
        if ($boardhasnotes) {
            $mform->addElement('html', '<div class="alert alert-info">'.get_string('boardhasnotes', 'mod_board').'</div>');
        }
        list($allowprivate, $allowpublic) = str_split(get_config('mod_board', 'allowed_singleuser_modes'));
        $modesallow = [
            board::SINGLEUSER_PRIVATE => $allowprivate,
            board::SINGLEUSER_PUBLIC => $allowpublic,
            board::SINGLEUSER_DISABLED => "1"
        ];
        $allowedsumodes = array_filter([
            board::SINGLEUSER_DISABLED => get_string('singleusermodenone', 'mod_board'),
            board::SINGLEUSER_PRIVATE => get_string('singleusermodeprivate', 'mod_board'),
            board::SINGLEUSER_PUBLIC => get_string('singleusermodepublic', 'mod_board')
            ], function($mode) use ($modesallow) {
                return $modesallow[$mode];
            }, ARRAY_FILTER_USE_KEY
        );
        if (count($allowedsumodes) > 1) {
            $mform->addElement('select', 'singleusermode', get_string('singleusermode', 'mod_board'), $allowedsumodes);
        }
        $mform->setType('singleusermode', PARAM_INT);
        if ($boardhasnotes) {
            $mform->addElement('hidden', 'hasnotes', $boardhasnotes);
            $mform->setType('hasnotes', PARAM_BOOL);
            $mform->disabledIf('singleusermode', 'hasnotes', 'gt', 0);
        }

        $mform->addElement('checkbox', 'postbyenabled', get_string('postbyenabled', 'mod_board'));
        $mform->addElement('date_time_selector', 'postby', get_string('postbydate', 'mod_board'));
        $mform->hideIf('postby', 'postbyenabled', 'notchecked');

        $mform->addElement('advcheckbox', 'userscanedit', get_string('userscanedit', 'mod_board'));

        $mform->addElement('advcheckbox', 'enableblanktarget', get_string('enableblanktarget', 'mod_board'));
        $mform->addHelpButton('enableblanktarget', 'enableblanktarget', 'mod_board');
        // Embed board on the course, rather then give a link to it.
        $mform->addElement('advcheckbox', 'embed', get_string('embedboard', 'mod_board'));

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

        $defaultvalues['completionnotesenabled'] = !empty($defaultvalues['completionnotes']) ? 1 : 0;
    }

    /**
     * Validate the data.
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (($data['embed'] == 1) && ($data['singleusermode'] != board::SINGLEUSER_DISABLED)) {
            $errors['embed'] = get_string('singleusermodenotembed', 'mod_board');
        }

        return $errors;
    }

    /**
     * Add custom completion rules.
     *
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        $group = [];
        $group[] =& $mform->createElement('checkbox', 'completionnotesenabled', '', get_string('completionnotes', 'mod_board'));
        $group[] =& $mform->createElement('text', 'completionnotes', '', ['size' => 3]);
        $mform->setType('completionnotes', PARAM_INT);
        $mform->addGroup($group, 'completionnotesgroup', get_string('completionnotesgroup', 'mod_board'), [' '], false);
        $mform->disabledIf('completionnotes', 'completionnotesenabled', 'notchecked');

        return ['completionnotesgroup'];
    }

    /**
     * Determines if completion is enabled for this module.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return (!empty($data['completionnotesenabled']) && $data['completionnotes'] != 0);
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        // Turn off completion settings if the checkboxes aren't ticked.
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionnotesenabled) || !$autocompletion) {
                $data->completionnotes = 0;
            }
        }
    }
}
