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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * List streamassign user/group overrides.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/overridelib.php');

global $DB, $OUTPUT;

$cmid = required_param('cmid', PARAM_INT);
$mode = optional_param('mode', '', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'streamassign');
$streamassign = $DB->get_record('streamassign', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/streamassign:manageoverrides', $context);

$accessallgroups = (groups_get_activity_groupmode($cm) == NOGROUPS) ||
    has_capability('moodle/site:accessallgroups', $context);
$groups = $accessallgroups ? groups_get_all_groups($cm->course) : groups_get_activity_allowed_groups($cm);

if ($mode !== 'user' && $mode !== 'group') {
    $mode = !empty($groups) ? 'group' : 'user';
}
$groupmode = ($mode === 'group');

$url = new moodle_url('/mod/streamassign/overrides.php', ['cmid' => $cm->id, 'mode' => $mode]);
$PAGE->set_url($url);
navigation_node::override_active_url(new moodle_url('/mod/streamassign/overrides.php', ['cmid' => $cmid]));

if ($action === 'movegroupoverride') {
    $id = required_param('id', PARAM_INT);
    $dir = required_param('dir', PARAM_ALPHA);
    if (confirm_sesskey()) {
        streamassign_move_group_override($id, $dir, (int) $streamassign->id);
    }
    redirect(new moodle_url('/mod/streamassign/overrides.php', ['cmid' => $cmid, 'mode' => 'group']));
}

$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_title(get_string('overrides', 'mod_assign'));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->set_attrs([
    'description' => '',
    'hidecompletion' => true,
    'title' => format_string($streamassign->name, true, ['context' => $context]),
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('overrides', 'mod_assign'), 2);

$overridemenu = new \mod_streamassign\output\override_actionmenu($url, $cm);
echo $OUTPUT->render_from_template('mod_streamassign/override_actionmenu',
    $overridemenu->export_for_template($OUTPUT));

$sql = 'SELECT o.id FROM {streamassign_overrides} o
         LEFT JOIN {groups} g ON o.groupid = g.id
        WHERE o.groupid IS NOT NULL AND g.id IS NULL AND o.streamassignid = ?';
$orphaned = $DB->get_records_sql($sql, [$streamassign->id]);
if ($orphaned) {
    $DB->delete_records_list('streamassign_overrides', 'id', array_keys($orphaned));
}

$overridecountgroup = $DB->count_records('streamassign_overrides', [
    'userid' => null,
    'streamassignid' => $streamassign->id,
]);
$overrides = [];

if ($groupmode && $groups) {
    $colname = get_string('group');
    list($insql, $inparams) = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED);
    $params = ['streamassignid' => $streamassign->id] + $inparams;
    $sql = "SELECT o.*, g.name FROM {streamassign_overrides} o
            JOIN {groups} g ON o.groupid = g.id
            WHERE o.streamassignid = :streamassignid AND g.id $insql
            ORDER BY o.sortorder";
    $overrides = $DB->get_records_sql($sql, $params);
} else if (!$groupmode) {
    $colname = get_string('user');
    list($sort, $params) = users_order_by_sql('u');
    $params['streamassignid'] = $streamassign->id;
    $userfieldsapi = \core_user\fields::for_name();
    $namesql = $userfieldsapi->get_sql('u', false, '', '', false)->selects;
    if ($accessallgroups) {
        $sql = "SELECT o.*, $namesql FROM {streamassign_overrides} o
                JOIN {user} u ON o.userid = u.id
                WHERE o.streamassignid = :streamassignid ORDER BY $sort";
        $overrides = $DB->get_records_sql($sql, $params);
    } else if ($groups) {
        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED);
        $params += $inparams;
        $sql = "SELECT o.*, $namesql FROM {streamassign_overrides} o
                JOIN {user} u ON o.userid = u.id
                JOIN {groups_members} gm ON u.id = gm.userid
                WHERE o.streamassignid = :streamassignid AND gm.groupid $insql
                ORDER BY $sort";
        $overrides = $DB->get_records_sql($sql, $params);
    }
} else {
    $colname = get_string('group');
}

$table = new html_table();
$table->headspan = [1, 2, 1];
$table->colclasses = ['colname', 'colsetting', 'colvalue', 'colaction'];
$table->head = [$colname, get_string('overrides', 'mod_assign'), get_string('action')];

$userurl = new moodle_url('/user/view.php');
$groupurl = new moodle_url('/group/overview.php', ['id' => $cm->course]);
$overridedeleteurl = new moodle_url('/mod/streamassign/overridedelete.php');
$overrideediturl = new moodle_url('/mod/streamassign/overrideedit.php');
$hasinactive = false;

foreach ($overrides as $override) {
    $fields = [];
    $values = [];
    $active = true;

    if (!$groupmode) {
        if (!is_enrolled($context, $override->userid) ||
                !\core_availability\info_module::is_user_visible($cm, $override->userid)) {
            $active = false;
        }
    }

    if ($override->timeopen !== null) {
        $fields[] = get_string('open', 'mod_assign');
        $values[] = $override->timeopen > 0 ? userdate($override->timeopen) : get_string('noopen', 'mod_assign');
    }
    if ($override->timeclose !== null) {
        $fields[] = get_string('duedate', 'mod_assign');
        $values[] = $override->timeclose > 0 ? userdate($override->timeclose) : get_string('noclose', 'mod_assign');
    }

    if (empty($fields)) {
        continue;
    }

    $iconstr = html_writer::link($overrideediturl->out(false, ['id' => $override->id]),
        $OUTPUT->pix_icon('t/edit', get_string('edit')));
    $iconstr .= ' ' . html_writer::link($overrideediturl->out(false, ['id' => $override->id, 'action' => 'duplicate']),
        $OUTPUT->pix_icon('t/copy', get_string('copy')));
    $iconstr .= ' ' . html_writer::link($overridedeleteurl->out(false, ['id' => $override->id, 'sesskey' => sesskey()]),
        $OUTPUT->pix_icon('t/delete', get_string('delete')));

    if ($groupmode) {
        $usergroupstr = html_writer::link($groupurl->out(false, ['group' => $override->groupid]),
            format_string($override->name, true, ['context' => $context]));
        if ($override->sortorder > 1) {
            $iconstr .= ' ' . html_writer::link(new moodle_url('/mod/streamassign/overrides.php', [
                'cmid' => $cmid, 'id' => $override->id, 'action' => 'movegroupoverride', 'dir' => 'up', 'sesskey' => sesskey(),
            ]), $OUTPUT->pix_icon('t/up', get_string('moveup')));
        }
        if ($override->sortorder < $overridecountgroup) {
            $iconstr .= ' ' . html_writer::link(new moodle_url('/mod/streamassign/overrides.php', [
                'cmid' => $cmid, 'id' => $override->id, 'action' => 'movegroupoverride', 'dir' => 'down', 'sesskey' => sesskey(),
            ]), $OUTPUT->pix_icon('t/down', get_string('movedown')));
        }
    } else {
        $usergroupstr = html_writer::link($userurl->out(false, ['id' => $override->userid, 'course' => $course->id]),
            fullname($override));
    }

    $class = $active ? '' : 'dimmed_text';
    if (!$active) {
        $usergroupstr .= '*';
        $hasinactive = true;
    }

    $usergroupcell = new html_table_cell();
    $usergroupcell->rowspan = count($fields);
    $usergroupcell->text = $usergroupstr;
    $actioncell = new html_table_cell();
    $actioncell->rowspan = count($fields);
    $actioncell->text = $iconstr;

    for ($i = 0; $i < count($fields); $i++) {
        $row = new html_table_row();
        $row->attributes['class'] = $class;
        if ($i === 0) {
            $row->cells[] = $usergroupcell;
        }
        $cell1 = new html_table_cell();
        $cell1->text = $fields[$i];
        $row->cells[] = $cell1;
        $cell2 = new html_table_cell();
        $cell2->text = $values[$i];
        $row->cells[] = $cell2;
        if ($i === 0) {
            $row->cells[] = $actioncell;
        }
        $table->data[] = $row;
    }
}

echo html_writer::start_tag('div', ['id' => 'streamassignoverrides']);
if ($table->data) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification($groupmode ? get_string('nogroupoverrides', 'mod_assign') :
        get_string('nouseroverrides', 'mod_assign'), 'info');
}
if ($hasinactive) {
    echo $OUTPUT->notification(get_string('inactiveoverridehelp', 'mod_assign'), 'dimmed_text');
}
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
