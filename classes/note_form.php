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

use mod_board\local\note;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/formslib.php");

/**
 * The main board class functions.
 * @package     mod_board
 * @author      Eric Merrill <eric.a.merrill@gmail.com>
 * @copyright   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class note_form extends \moodleform {
    /**
     * Definition of the form elements.
     */
    public function definition() {
        $config = get_config('mod_board');

        $mform = $this->_form;

        $mform->addElement('hidden', 'noteid');
        $mform->setType('noteid', PARAM_INT);
        $mform->addElement('hidden', 'columnid');
        $mform->setType('columnid', PARAM_INT);
        $mform->addElement('hidden', 'ownerid');
        $mform->setType('ownerid', PARAM_INT);
        $mform->addElement('hidden', 'groupid');
        $mform->setType('groupid', PARAM_INT);

        $maxlenheading = board::LENGTH_HEADING;
        $mform->addElement(
            'text',
            'heading',
            get_string('form_title', 'mod_board'),
            ['maxlength' => $maxlenheading]
        );
        $mform->setType('heading', PARAM_TEXT);
        $mform->addRule(
            'heading',
            get_string('maximumchars', '', $maxlenheading),
            'maxlength',
            $maxlenheading,
            'client'
        );

        $maxlen = $config->post_max_length;
        $options = ['maxlength' => $maxlen, 'cols' => 30, 'rows' => 4];
        $mform->addElement('textarea', 'content', get_string('form_body', 'mod_board'), $options);
        $mform->setType('content', PARAM_RAW);
        // Unfortunately Moodle forms validation may count new-line characters
        // differently from text area maxlength attribute, for now let's allow some more characters
        // to prevent surprises/data loss caused by missing client and server side validation
        // in current JS dialog code for this form.
        $mform->addRule('content', get_string('maximumchars', '', $maxlen), 'maxlength', $maxlen + 30, 'client');

        $mform->addElement('checkbox', 'mardownhelpcheckbox', get_string('limited_markdown_checkbox', 'mod_board'));
        $markdown = '<div class="alert alert-info limited_markdown_examples">'
            . get_string('limited_markdown_examples', 'mod_board') . '</div>';
        $mform->addElement('static', 'mardownhelpstatic', '', $markdown);
        $mform->hideIf('mardownhelpstatic', 'mardownhelpcheckbox', 'notchecked');

        $generalpickeroptions = note::get_general_picker_options();

        $options = [
            board::MEDIATYPE_NONE => get_string('option_empty', 'mod_board'),
            board::MEDIATYPE_URL => get_string('option_link', 'mod_board'),
            board::MEDIATYPE_IMAGE => get_string('option_image', 'mod_board'),
            board::MEDIATYPE_FILE => get_string('option_file', 'mod_board'),
            board::MEDIATYPE_YOUTUBE => get_string('option_youtube', 'mod_board'),
        ];
        if (!$generalpickeroptions) {
            unset($options[board::MEDIATYPE_FILE]);
        }
        if (!$config->allowyoutube) {
            unset($options[board::MEDIATYPE_YOUTUBE]);
        }
        $attr = ['class' => 'mod_board_type'];
        $mform->addElement('select', 'mediatype', get_string('form_mediatype', 'mod_board'), $options, $attr);

        $html = '<div class="mod_board_note_buttons">
                    <div class="mod_board_attachment_button link_button fa fa-link" role="button" tabindex="0"></div>
                    <div class="mod_board_attachment_button image_button fa fa-picture-o" role="button" tabindex="0"></div>';
        if ($generalpickeroptions) {
            $html .= '<div class="mod_board_attachment_button file_button fa fa-file-text" role="button" tabindex="0"></div>';
        }
        if ($config->allowyoutube) {
            $html .= '<div class="mod_board_attachment_button youtube_button fa fa-youtube" role="button" tabindex="0"></div>';
        }
        $html .= '</div>';

        $mform->addElement('static', 'mediabuttons', get_string('form_mediatype', 'mod_board'), $html);

        // Link.
        $maxleninfo = board::LENGTH_INFO;
        $options = ['maxlength' => $maxleninfo, 'placeholder' => get_string('option_link_info', 'mod_board')];
        $mform->addElement('text', 'linktitle', get_string('option_link_info', 'mod_board'), $options);
        $mform->setType('linktitle', PARAM_TEXT);
        $mform->hideIf('linktitle', 'mediatype', 'neq', board::MEDIATYPE_URL);
        $mform->addRule('linktitle', get_string('maximumchars', '', $maxleninfo), 'maxlength', $maxleninfo, 'client');

        // URL.
        $maxlenurl = board::LENGTH_URL;
        $attr = ['maxlength' => $maxlenurl, 'placeholder' => get_string('option_link_url', 'mod_board'), 'size' => 80];
        $mform->addElement('url', 'linkurl', get_string('option_link_url', 'mod_board'), $attr, ['usefilepicker' => false]);
        $mform->setType('linkurl', PARAM_URL);
        $mform->hideIf('linkurl', 'mediatype', 'neq', board::MEDIATYPE_URL);
        $mform->addRule('linkurl', get_string('maximumchars', '', $maxlenurl), 'maxlength', $maxlenurl, 'client');

        // Image file.
        $options = ['maxlength' => $maxleninfo, 'placeholder' => get_string('option_image_info', 'mod_board')];
        $mform->addElement('text', 'imagetitle', get_string('option_image_info', 'mod_board'), $options);
        $mform->setType('imagetitle', PARAM_TEXT);
        $mform->hideIf('imagetitle', 'mediatype', 'neq', board::MEDIATYPE_IMAGE);
        $mform->addRule('imagetitle', get_string('maximumchars', '', $maxleninfo), 'maxlength', $maxleninfo, 'client');

        $imagepickeroptions = note::get_image_picker_options();
        $mform->addElement('filemanager', 'imagefile', get_string('form_image_file', 'mod_board'), null, $imagepickeroptions);
        $mform->hideIf('imagefile', 'mediatype', 'neq', board::MEDIATYPE_IMAGE);

        // General file.
        if ($generalpickeroptions) {
            $mform->addElement(
                'filemanager',
                'generalfile',
                get_string('form_general_file', 'mod_board'),
                null,
                $generalpickeroptions
            );
            $mform->hideIf('generalfile', 'mediatype', 'neq', board::MEDIATYPE_FILE);
        }

        if ($config->allowyoutube) {
            // YouTube video.
            $options = ['maxlength' => $maxleninfo, 'placeholder' => get_string('option_youtube_info', 'mod_board')];
            $mform->addElement('text', 'youtubetitle', get_string('option_youtube_info', 'mod_board'), $options);
            $mform->setType('youtubetitle', PARAM_TEXT);
            $mform->hideIf('youtubetitle', 'mediatype', 'neq', board::MEDIATYPE_YOUTUBE);
            $mform->addRule('youtubetitle', get_string('maximumchars', '', $maxleninfo), 'maxlength', $maxleninfo, 'client');

            $options = ['maxlength' => $maxlenurl, 'placeholder' => get_string('option_youtube_url', 'mod_board'), 'size' => 80];
            $mform->addElement('text', 'youtubeurl', get_string('option_youtube_url', 'mod_board'), $options);
            $mform->setType('youtubeurl', PARAM_URL);
            $mform->hideIf('youtubeurl', 'mediatype', 'neq', board::MEDIATYPE_YOUTUBE);
            $mform->addRule('youtubeurl', get_string('maximumchars', '', $maxlenurl), 'maxlength', $maxlenurl, 'client');
        }
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // NOTE: do not add validation here until board.js can reload the form.
        // phpcs:disable Squiz.PHP.CommentedOutCode.Found
        /*
        if ($data['mediatype'] == board::MEDIATYPE_NONE) {
            if (trim($data['heading']) === '' && trim($data['content']) === '') {
                $errors['heading'] = get_string('required');
            }
        }
        */

        return $errors;
    }
}
