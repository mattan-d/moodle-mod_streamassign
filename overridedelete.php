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
 * Delete a streamassign override.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/overridelib.php');

global $DB;

$overrideid = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', false, PARAM_BOOL);

$override = $DB->get_record('streamassign_overrides', ['id' => $overrideid], '*', MUST_EXIST);
list($course, $cm) = get_course_and_cm_from_instance($override->streamassignid, 'streamassign');
$context = context_module::instance($cm->id);
$streamassign = $DB->get_record('streamassign', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_capability('mod/streamassign:manageoverrides', $context);

if ($override->groupid) {
    if (!groups_group_visible($override->groupid, $course, $cm)) {
        throw new moodle_exception('invalidoverrideid', 'mod_assign');
    }
} else if (!groups_user_groups_visible($course, $override->userid, $cm)) {
    throw new moodle_exception('invalidoverrideid', 'mod_assign');
}

$url = new moodle_url('/mod/streamassign/overridedelete.php', ['id' => $override->id]);
$confirmurl = new moodle_url($url, ['id' => $override->id, 'confirm' => 1]);
$cancelurl = new moodle_url('/mod/streamassign/overrides.php', ['cmid' => $cm->id]);
if (!empty($override->userid)) {
    $cancelurl->param('mode', 'user');
}

if ($confirm) {
    require_sesskey();
    streamassign_delete_override((int) $override->id, (int) $streamassign->id);
    streamassign_reorder_group_overrides((int) $streamassign->id);
    redirect($cancelurl);
}

$stroverride = get_string('override', 'mod_assign');
$title = get_string('deletecheck', null, $stroverride);

$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('limitedwidth');
$PAGE->navbar->add($title);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->set_attrs([
    'title' => format_string($streamassign->name, true, ['context' => $context]),
    'description' => '',
    'hidecompletion' => true,
]);

echo $OUTPUT->header();

if ($override->groupid) {
    $group = $DB->get_record('groups', ['id' => $override->groupid], 'id, name', MUST_EXIST);
    $confirmstr = get_string('overridedeletegroupsure', 'mod_assign',
        format_string($group->name, true, ['context' => $context]));
} else {
    $userfieldsapi = \core_user\fields::for_name();
    $namefields = $userfieldsapi->get_sql('', false, '', '', false)->selects;
    $user = $DB->get_record('user', ['id' => $override->userid], 'id, ' . $namefields, MUST_EXIST);
    $confirmstr = get_string('overridedeleteusersure', 'mod_assign', fullname($user));
}

echo $OUTPUT->confirm($confirmstr, $confirmurl, $cancelurl);
echo $OUTPUT->footer();
