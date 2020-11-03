<?php defined('MOODLE_INTERNAL') || die;

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

$string['new_column_icon'] = 'New column icon';
$string['new_column_icon_desc'] = 'Icon displayed on the new button for columns.';
$string['new_note_icon'] = 'New post icon';
$string['new_note_icon_desc'] = 'Icon displayed on the new button for posts.';

$string['export_csv'] = 'Export CSV';
$string['background_color'] = 'Background color';

$string['event_add_column'] = 'Column added';
$string['event_add_column_desc'] = 'The user with id \'{$a->userid}\' created board column with id \'{$a->objectid}\' and name \'{$a->name}\'.';
$string['event_update_column'] = 'Column updated';
$string['event_update_column_desc'] = 'The user with id \'{$a->userid}\' updated board column with id \'{$a->objectid}\' to \'{$a->name}\'.';
$string['event_delete_column'] = 'Column deleted';
$string['event_delete_column_desc'] = 'The user with id \'{$a->userid}\' deleted board column with id \'{$a->objectid}\'.';
$string['event_add_note'] = 'Post added';
$string['event_add_note_desc'] = 'The user with id \'{$a->userid}\' created board post with id \'{$a->objectid}\' and content \'{$a->content}\' on column id \'{$a->columnid}\'.';
$string['event_update_note'] = 'Post updated';
$string['event_update_note_desc'] = 'The user with id \'{$a->userid}\' updated board post with id \'{$a->objectid}\' to \'{$a->content}\' on column id \'{$a->columnid}\'.';
$string['event_delete_note'] = 'Post deleted';
$string['event_delete_note_desc'] = 'The user with id \'{$a->userid}\' deleted board post with id \'{$a->objectid}\' from column id \'{$a->columnid}\'.';