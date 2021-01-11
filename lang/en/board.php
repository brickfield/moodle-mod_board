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

$string['pluginname'] = 'Board';
$string['modulename'] = 'Board';
$string['modulename_help'] = 'This is a new activity for Moodle that enables a teacher to create a new “postit” board.';
$string['modulenameplural'] = 'Boards';
$string['board:addinstance'] = 'Add a new board resource';
$string['board:view'] = 'View board content and manage own posts.';
$string['board:manageboard'] = 'Manage columns and manage all posts.';
$string['pluginadministration'] = 'Board module administration';

$string['default_column_heading'] = 'Heading';
$string['post_button_text'] = 'Post';
$string['cancel_button_text'] = 'Cancel';
$string['remove_note_text'] = "This will affect all other users as well.\nAre you sure you want to delete this post?";
$string['remove_column_text'] = 'All the posts for this column will be deleted along with the column itself. Are you sure you want to continue?';
$string['note_changed_text'] = "The post you are editing has changed.\nPress 'OK' to see the updated post or 'Cancel' to continue editing ?";
$string['note_deleted_text'] = 'The post you were editing was deleted.';
$string['Ok'] = 'Ok';
$string['Cancel'] = 'Cancel';
$string['warning'] = 'Notification';
$string['option_youtube'] = 'Video (YouTube)';
$string['option_youtube_info'] = 'Video Title';
$string['option_youtube_url'] = 'YouTube URL';
$string['option_image'] = 'Image';
$string['option_image_info'] = 'Image Title';
$string['option_image_url'] = 'Image URL';
$string['option_link'] = 'Link';
$string['option_link_info'] = 'Link Title';
$string['option_link_url'] = 'Link URL';
$string['option_empty'] = 'None';

$string['new_column_icon'] = 'New column icon';
$string['new_column_icon_desc'] = 'Icon displayed on the new button for columns.';
$string['new_note_icon'] = 'New post icon';
$string['new_note_icon_desc'] = 'Icon displayed on the new button for posts.';
$string['media_selection_buttons'] = 'Buttons';
$string['media_selection_dropdown'] = 'Dropdown';
$string['media_selection'] = 'Media selection';
$string['media_selection_desc'] = 'Configure how the media selection for posts will be displayed as.';
$string['post_max_length'] = 'Post maximum length';
$string['post_max_length_desc'] = 'The maximum allowed content length. Anything over this length will be trimmed.';

$string['export_board'] = 'Export CSV';
$string['export_submissions'] = 'Export Submissions';
$string['export_firstname'] = 'Firstname';
$string['export_lastname'] = 'Lastname';
$string['export_email'] = 'Email';
$string['export_heading'] = 'Post Heading';
$string['export_content'] = 'Post Text';
$string['export_info'] = 'Post Title';
$string['export_url'] = 'Post URL';
$string['export_timecreated'] = 'Date created';
$string['background_color'] = 'Background color';

$string['event_add_column'] = 'Column added';
$string['event_add_column_desc'] = 'The user with id \'{$a->userid}\' created board column with id \'{$a->objectid}\' and name \'{$a->name}\'.';
$string['event_update_column'] = 'Column updated';
$string['event_update_column_desc'] = 'The user with id \'{$a->userid}\' updated board column with id \'{$a->objectid}\' to \'{$a->name}\'.';
$string['event_delete_column'] = 'Column deleted';
$string['event_delete_column_desc'] = 'The user with id \'{$a->userid}\' deleted board column with id \'{$a->objectid}\'.';
$string['event_add_note'] = 'Post added';
$string['event_add_note_desc'] = 'The user with id \'{$a->userid}\' created board post with id \'{$a->objectid}\', heading \'{$a->heading}\', content \'{$a->content}\', media \'{$a->media}\' on column id \'{$a->columnid}\', group id \'{$a->groupid}\'.';
$string['event_update_note'] = 'Post updated';
$string['event_update_note_desc'] = 'The user with id \'{$a->userid}\' updated board post with id \'{$a->objectid}\' to heading \'{$a->heading}\', content \'{$a->content}\', media \'{$a->media}\' on column id \'{$a->columnid}\'.';
$string['event_delete_note'] = 'Post deleted';
$string['event_delete_note_desc'] = 'The user with id \'{$a->userid}\' deleted board post with id \'{$a->objectid}\' from column id \'{$a->columnid}\'.';

$string['groupingid_required'] = 'A course grouping must be selected for this group mode.';

$string['aria_newcolumn'] = 'Add new column';
$string['aria_newpost'] = 'Add new post to column {column}';
$string['aria_deletecolumn'] = 'Delete column {column}';
$string['aria_deletepost'] = 'Delete post {post} from column {column}';
$string['aria_addmedia'] = 'Add {type} for post {post} from column {column}';
$string['aria_addmedianew'] = 'Add {type} for new post from column {column}';
$string['aria_deleteattachment'] = 'Delete attachment for post {post} from column {column}';
$string['aria_postedit'] = 'Save post edit for post {post} from column {column}';
$string['aria_canceledit'] = 'Cancel post edit for post {post} from column {column}';
$string['aria_postnew'] = 'Save new post for column {column}';
$string['aria_cancelnew'] = 'Cancel new post for column {column}';
