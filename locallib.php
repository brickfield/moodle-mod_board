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

function coursemodule_for_board($board) {
    return get_coursemodule_from_instance('board', $board->id, $board->course, false, MUST_EXIST);
}

function get_board($id) {
    global $DB;
    return $DB->get_record('board', array('id' => $id));
}

function get_column($id) {
    global $DB;
    return $DB->get_record('board_columns', array('id' => $id));
}

function get_note($id) {
    global $DB;
    return $DB->get_record('board_notes', array('id' => $id));
}

function context_for_board($id) {
    if (!$board = get_board($id)) {
        return null;
    }

    $cm = coursemodule_for_board($board);
    return context_module::instance($cm->id);
}

function context_for_column($id) {
    if (!$column = get_column($id)) {
        return null;
    }

    return context_for_board($column->boardid);
}

function require_capability_for_board_view($id) {
    $context = context_for_board($id);
    if ($context) {
        require_capability('mod/board:view', $context);
    }
}

function require_capability_for_board($id) {
    $context = context_for_board($id);
    if ($context) {
        require_capability('mod/board:manageboard', $context);
    }
}

function require_capability_for_column($id) {
    $context = context_for_column($id);
    if ($context) {
        require_capability('mod/board:manageboard', $context);
    }
}

function require_access_for_group($groupid, $boardid) {
    $cm = coursemodule_for_board(get_board($boardid));
    $context = context_module::instance($cm->id);

    if (has_capability('mod/board:manageboard', $context)) {
        return true;
    }

    $groupmode = groups_get_activity_groupmode($cm);
    if (!in_array($groupmode, [VISIBLEGROUPS, SEPARATEGROUPS])) {
        return true;
    }

    if (!can_access_group($groupid, $context)) {
        throw new Exception('Invalid group');
    }
}

function clear_history() {
    global $DB;

    return $DB->delete_records_select('board_history', 'timecreated < :timecreated',
        array('timecreated' => time() - 60)); // 1 minute history.
}

function board_get($boardid) {
    global $DB;

    require_capability_for_board_view($boardid);

    if (!$board = $DB->get_record('board', array('id' => $boardid))) {
        return [];
    }

    $groupid = groups_get_activity_group(coursemodule_for_board(get_board($boardid)), true) ?: null;

    $columns = $DB->get_records('board_columns', array('boardid' => $boardid), 'id', 'id, name');
    foreach ($columns as $columnid => $column) {
        $params = array('columnid' => $columnid);
        if (!empty($groupid)) {
            $params['groupid'] = $groupid;
        }
        $column->notes = $DB->get_records('board_notes', $params, 'id',
            'id, userid, heading, content, type, info, url, timecreated');
    }

    clear_history();
    return $columns;
};

function board_history($boardid, $since) {
    global $DB;

    require_capability_for_board_view($boardid);

    if (!$board = $DB->get_record('board', array('id' => $boardid))) {
        return [];
    }

    $groupid = groups_get_activity_group(coursemodule_for_board(get_board($boardid)), true) ?: null;

    clear_history();

    $condition = "boardid=:boardid AND id > :since";
    $params = array('boardid' => $boardid, 'since' => $since);
    if (!empty($groupid)) {
        $condition .= " AND groupid=:groupid";
        $params['groupid'] = $groupid;
    }

    return $DB->get_records_select('board_history', $condition, $params);
};

function board_add_column($boardid, $name) {
    global $DB, $USER;

    $name = substr($name, 0, 100);

    require_capability_for_board($boardid);

    $transaction = $DB->start_delegated_transaction();

    $columnid = $DB->insert_record('board_columns', array('boardid' => $boardid, 'name' => $name));
    $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'action' => 'add_column',
        'userid' => $USER->id, 'content' => json_encode(array('id' => $columnid, 'name' => $name)), 'timecreated' => time()));
    $DB->update_record('board', array('id' => $boardid, 'historyid' => $historyid));
    $transaction->allow_commit();

    board_add_column_log($boardid, $name, $columnid);

    clear_history();
    return array('id' => $columnid, 'historyid' => $historyid);
}

function board_add_column_log($boardid, $name, $columnid) {
    $event = \mod_board\event\add_column::create(array(
        'objectid' => $columnid,
        'context' => context_module::instance(coursemodule_for_board(get_board($boardid))->id),
        'other' => array('name' => $name)
    ));
    $event->trigger();
}

function board_update_column($id, $name) {
    global $DB, $USER;

    $name = substr($name, 0, 100);

    require_capability_for_column($id);

    $boardid = $DB->get_field('board_columns', 'boardid', array('id' => $id));
    if ($boardid) {
        $transaction = $DB->start_delegated_transaction();
        $update = $DB->update_record('board_columns', array('id' => $id, 'name' => $name));
        $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'action' => 'update_column',
            'userid' => $USER->id, 'content' => json_encode(array('id' => $id, 'name' => $name)), 'timecreated' => time()));
        $DB->update_record('board', array('id' => $id, 'historyid' => $historyid));
        $transaction->allow_commit();

        board_update_column_log($boardid, $name, $id);
    } else {
        $update = false;
        $historyid = 0;
    }

    clear_history();
    return array('status' => $update, 'historyid' => $historyid);
}

function board_update_column_log($boardid, $name, $columnid) {
    $event = \mod_board\event\update_column::create(array(
        'objectid' => $columnid,
        'context' => context_module::instance(coursemodule_for_board(get_board($boardid))->id),
        'other' => array('name' => $name)
    ));
    $event->trigger();
}

function board_delete_column($id) {
    global $DB, $USER;

    require_capability_for_column($id);

    $boardid = $DB->get_field('board_columns', 'boardid', array('id' => $id));
    if ($boardid) {
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('board_notes', array('columnid' => $id));
        $delete = $DB->delete_records('board_columns', array('id' => $id));
        $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'action' => 'delete_column',
            'content' => json_encode(array('id' => $id)), 'userid' => $USER->id, 'timecreated' => time()));
        $DB->update_record('board', array('id' => $boardid, 'historyid' => $historyid));
        $transaction->allow_commit();

        board_delete_column_log($boardid, $id);
    } else {
        $delete = false;
        $historyid = 0;
    }

    clear_history();
    return array('status' => $delete, 'historyid' => $historyid);
}

function board_delete_column_log($boardid, $columnid) {
    $event = \mod_board\event\delete_column::create(array(
        'objectid' => $columnid,
        'context' => context_module::instance(coursemodule_for_board(get_board($boardid))->id)
    ));
    $event->trigger();
}

function require_capability_for_note($id) {
    global $DB, $USER;

    if (!$note = $DB->get_record('board_notes', array('id' => $id))) {
        return false;
    }

    $context = context_for_column($note->columnid);
    if ($context) {
        require_capability('mod/board:view', $context);

        if ($USER->id != $note->userid) {
            require_capability('mod/board:manageboard', $context);
        }
    }
}

function board_add_note($columnid, $heading, $content, $attachment) {
    global $DB, $USER, $CFG;

    $context = context_for_column($columnid);
    if ($context) {
        require_capability('mod/board:view', $context);
    }

    $heading = empty($heading) ? null : substr($heading, 0, 100);
    $content = empty($content) ? "" : substr($content, 0, $CFG->post_max_length);

    $boardid = $DB->get_field('board_columns', 'boardid', array('id' => $columnid));

    if ($boardid) {
        $cm = coursemodule_for_board(get_board($boardid));
        $groupid = groups_get_activity_group($cm, true) ?: null;
        require_access_for_group($groupid, $boardid);

        $transaction = $DB->start_delegated_transaction();
        $type = !empty($attachment['type']) ? $attachment['type'] : 0;
        $info = !empty($type) ? substr($attachment['info'], 0, 100) : null;
        $url = !empty($type) ? substr($attachment['url'], 0, 200) : null;
        $noteid = $DB->insert_record('board_notes', array('groupid' => $groupid, 'columnid' => $columnid,
            'heading' => $heading, 'content' => $content, 'type' => $type, 'info' => $info, 'url' => $url,
            'userid' => $USER->id, 'timecreated' => time()));
        $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'groupid' => $groupid,
            'action' => 'add_note', 'userid' => $USER->id, 'content' => json_encode(array('id' => $noteid,
            'columnid' => $columnid, 'heading' => $heading, 'content' => $content,
            'attachment' => array('type' => $type, 'info' => $info, 'url' => $url))), 'timecreated' => time()));
        $DB->update_record('board', array('id' => $boardid, 'historyid' => $historyid));
        $transaction->allow_commit();

        board_add_note_log($boardid, $groupid, $heading, $content, $attachment, $columnid, $noteid);
    } else {
        $noteid = 0;
        $historyid = 0;
    }

    clear_history();
    return array('id' => $noteid, 'historyid' => $historyid);
}

function board_add_note_log($boardid, $groupid, $heading, $content, $attachment, $columnid, $noteid) {
    $event = \mod_board\event\add_note::create(array(
        'objectid' => $noteid,
        'context' => context_module::instance(coursemodule_for_board(get_board($boardid))->id),
        'other' => array('groupid' => $groupid, 'columnid' => $columnid, 'heading' => $heading,
        'content' => $content, 'attachment' => $attachment)
    ));
    $event->trigger();
}

function board_update_note($id, $heading, $content, $attachment) {
    global $DB, $USER, $CFG;

    require_capability_for_note($id);

    $heading = empty($heading) ? null : substr($heading, 0, 100);
    $content = empty($content) ? "" : substr($content, 0, $CFG->post_max_length);

    $columnid = $DB->get_field('board_notes', 'columnid', array('id' => $id));
    $boardid = $DB->get_field('board_columns', 'boardid', array('id' => $columnid));

    $note = get_note($id);
    if (!empty($note->groupid)) {
        require_access_for_group($note->groupid, $boardid);
    }

    if ($columnid && $boardid) {
        $transaction = $DB->start_delegated_transaction();
        $type = !empty($attachment['type']) ? $attachment['type'] : 0;
        $info = !empty($type) ? substr($attachment['info'], 0, 100) : null;
        $url = !empty($type) ? substr($attachment['url'], 0, 200) : null;
        $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'action' => 'update_note',
            'userid' => $USER->id, 'content' => json_encode(array('id' => $id, 'columnid' => $columnid,
            'heading' => $heading, 'content' => $content, 'attachment' =>
            array('type' => $type, 'info' => $info, 'url' => $url))), 'timecreated' => time()));
        $update = $DB->update_record('board_notes', array('id' => $id, 'heading' => $heading, 'content' => $content,
            'type' => $type, 'info' => $info, 'url' => $url));
        $DB->update_record('board', array('id' => $boardid, 'historyid' => $historyid));
        $transaction->allow_commit();

        board_update_note_log($boardid, $heading, $content, $attachment, $columnid, $id);
    } else {
        $update = false;
        $historyid = 0;
    }

    clear_history();
    return array('status' => $update, 'historyid' => $historyid);
}

function board_update_note_log($boardid, $heading, $content, $attachment, $columnid, $noteid) {
    $event = \mod_board\event\update_note::create(array(
        'objectid' => $noteid,
        'context' => context_module::instance(coursemodule_for_board(get_board($boardid))->id),
        'other' => array('columnid' => $columnid, 'heading' => $heading, 'content' => $content, 'attachment' => $attachment)
    ));
    $event->trigger();
}

function board_delete_note($id) {
    global $DB, $USER;

    require_capability_for_note($id);

    $columnid = $DB->get_field('board_notes', 'columnid', array('id' => $id));
    $boardid = $DB->get_field('board_columns', 'boardid', array('id' => $columnid));

    $note = get_note($id);
    if (!empty($note->groupid)) {
        require_access_for_group($note->groupid, $boardid);
    }

    if ($columnid && $boardid) {
        $transaction = $DB->start_delegated_transaction();
        $delete = $DB->delete_records('board_notes', array('id' => $id));
        $historyid = $DB->insert_record('board_history', array('boardid' => $boardid, 'action' => 'delete_note',
            'content' => json_encode(array('id' => $id, 'columnid' => $columnid)), 'userid' => $USER->id, 'timecreated' => time()));
        $DB->update_record('board', array('id' => $boardid, 'historyid' => $historyid));
        $transaction->allow_commit();

        board_delete_note_log($boardid, $columnid, $id);
    } else {
        $delete = false;
        $historyid = 0;
    }
    clear_history();
    return array('status' => $delete, 'historyid' => $historyid);
}

function board_delete_note_log($boardid, $columnid, $noteid) {
    $event = \mod_board\event\delete_note::create(array(
        'objectid' => $noteid,
        'context' => context_module::instance(coursemodule_for_board(get_board($boardid))->id),
        'other' => array('columnid' => $columnid)
    ));
    $event->trigger();
}

function can_access_all_groups($context) {
    return has_capability('moodle/site:accessallgroups', $context);
}

function can_access_group($groupid, $context) {
    global $USER;

    if (can_access_all_groups($context)) {
        return true;
    }

    return groups_is_member($groupid);
}
