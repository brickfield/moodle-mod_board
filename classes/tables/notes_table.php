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
 * Table sql definition for exporting the board notes.
 * @package     mod_board
 * @author      Bas Brands <bas@sonsbeekmedia.nl>
 * @copyright   2023 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_board\tables;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/tablelib.php');

use table_sql;
use moodle_url;

/**
 * Define notes table class.
 */
class notes_table extends table_sql {

    /**
     * Constructor
     * @param int $cmid The course module id.
     * @param int $boardid The board id.
     * @param int $groupid The group id.
     * @param int $ownerid The owner id.
     * @param int $includedeleted Include deleted notes.
     */
    public function __construct($cmid, $boardid, $groupid, $ownerid, $includedeleted) {
        parent::__construct('mod_board_notes_table');
        $context = \context_module::instance($cmid);
        // Get the construct parameters and add them to the export url.
        $exportparams = [
            'id' => $cmid,
            'group' => $groupid,
            'tabletype' => 'notes',
            'ownerid' => $ownerid,
            'includedeleted' => $includedeleted
        ];
        $exporturl = new moodle_url('/mod/board/export.php', $exportparams);
        $this->define_baseurl($exporturl);

        // Define the list of columns to show.
        $module = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $courseid = $module->course;
        $fields = self::get_user_profile_fields($courseid, false);
        $columns = [];
        $userfields = '';
        foreach ($fields as $field) {
            // Needed headers in the table.
            $columns[] = $field->shortname;
            // Needed userdata to be included in the sql-statement.
            $userfields .= ' u.' . $field->shortname . ', ';
        }
        $columns = array_merge($columns,  array('heading', 'content', 'info', 'url', 'timecreated', 'deleted'));

        $this->define_columns($columns);

        // Define the titles of columns to show in header.
        $headers = array_map(function($column) {
            if ($column == 'heading') {
                // The word 'heading' is not included in core langfile, so we use langfile included in board plugin.
                return get_string('export_' . $column, 'board');
            } else {
                // Read the string from the moodle core langfile if possible.
                return new \lang_string($column);
            }
        }, $columns);
        $this->define_headers($headers);

        // Define the SQL used to get the data.
        $this->sql = (object)[];
        $this->sql->fields = 'bn.id, ' . $userfields . ' bn.heading, bn.content, bn.info, bn.url, bn.timecreated, bn.deleted';

        $this->sql->from = '{board_columns} bc
        JOIN {board_notes} bn ON bn.columnid = bc.id JOIN {user} u ON u.id = bn.ownerid';
        $this->sql->where = 'bc.boardid = :boardid';
        $this->sql->params = ['boardid' => $boardid];
        if ($groupid > 0) {
            $this->sql->where .= ' AND bn.groupid = :groupid';
            $this->sql->params['groupid'] = $groupid;
        }
        if ($ownerid > 0) {
            $this->sql->where .= ' AND bn.ownerid = :ownerid';
            $this->sql->params['ownerid'] = $ownerid;
        }
        if (!$includedeleted) {
            $this->sql->where .= ' AND bn.deleted = 0';
        }
    }

    /**
     * This code is copied from the grading and has been adapted.
     * Returns an array of user profile fields to be included in export
     *
     * @param int $courseid
     * @param bool $includecustomfields
     * @return array An array of stdClass instances with customid, shortname, datatype, default and fullname fields
     */
    public static function get_user_profile_fields($courseid, $includecustomfields = false) {
        global $CFG, $DB;

        // Gets the fields that have to be hidden.
        $hiddenfields = array_map('trim', explode(',', $CFG->hiddenuserfields));
        $context = \context_course::instance($courseid);
        $canseehiddenfields = has_capability('moodle/course:viewhiddenuserfields', $context);
        if ($canseehiddenfields) {
            $hiddenfields = array();
        }
        $fields = array();
        require_once($CFG->dirroot.'/user/lib.php');                // Loads user_get_default_fields()
        require_once($CFG->dirroot.'/user/profile/lib.php');        // Loads constants, such as PROFILE_VISIBLE_ALL
        $userdefaultfields = self::user_get_default_fields();
        // Sets the list of profile fields.
        $userprofilefields = array_map('trim', explode(',', get_config('mod_board', 'export_userprofilefields')));
        if (!empty($userprofilefields)) {
            foreach ($userprofilefields as $field) {
                $field = trim($field);
                if (in_array($field, $hiddenfields) || !in_array($field, $userdefaultfields)) {
                    continue;
                }
                $obj = new \stdClass();
                $obj->customid  = 0;
                $obj->shortname = $field;
                $obj->fullname  = get_string($field);
                $fields[] = $obj;
            }
        }

        // Sets the list of custom profile fields.
        $customprofilefields = array_map('trim', explode(',', get_config('mod_board', 'export_customprofilefields')));
        if ($includecustomfields && !empty($customprofilefields)) {
            $customfields = profile_get_user_fields_with_data(0);

            foreach ($customfields as $fieldobj) {
                $field = (object)$fieldobj->get_field_config_for_external();
                // Make sure we can display this custom field
                if (!in_array($field->shortname, $customprofilefields)) {
                    continue;
                } else if (in_array($field->shortname, $hiddenfields)) {
                    continue;
                } else if ($field->visible != PROFILE_VISIBLE_ALL && !$canseehiddenfields) {
                    continue;
                }

                $obj = new \stdClass();
                $obj->customid  = $field->id;
                $obj->shortname = $field->shortname;
                $obj->fullname  = format_string($field->name);
                $obj->datatype  = $field->datatype;
                $obj->default   = $field->defaultdata;
                $fields[] = $obj;
            }
        }

        return $fields;
    }

    /**
     * This code is copied from the grading.
     * Returns the list of default 'displayable' fields
     *
     * Contains database field names but also names used to generate information, such as enrolledcourses
     *
     * @return array of user fields
     */
    public static function user_get_default_fields() {
        return array( 'id', 'username', 'fullname', 'firstname', 'lastname', 'email',
                'address', 'phone1', 'phone2', 'department',
                'institution', 'interests', 'firstaccess', 'lastaccess', 'auth', 'confirmed',
                'idnumber', 'lang', 'theme', 'timezone', 'mailformat', 'description', 'descriptionformat',
                'city', 'country', 'profileimageurlsmall', 'profileimageurl', 'customfields',
                'groups', 'roles', 'preferences', 'enrolledcourses', 'suspended', 'lastcourseaccess'
        );
    }

    /**
     * Displays deleted in readable format.
     *
     * @param object $value The value of the column.
     * @return string returns deleted.
     */
    public function col_deleted($value) {
        return ($value->deleted) ? get_string('yes') : get_string('no');
    }

    /**
     * This function is called for each data row to allow processing of
     * columns which do not have a *_cols function.
     *
     * @param string $colname The name of the column.
     * @param object $value The value of the column.
     * @return string return processed value. Return NULL if no change has
     *     been made.
     */
    public function other_cols($colname, $value) {
        if ($colname == 'timecreated') {
            return userdate($value->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        }
    }

    /**
     * Displays the table.
     */
    public function display() {
        $this->out(10, true);
    }
}
