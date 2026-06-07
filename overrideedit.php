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
 * Create or edit streamassign overrides.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/overridelib.php');
require_once(__DIR__ . '/override_form.php');

global $DB;

$cmid = optional_param('cmid', 0, PARAM_INT);
$overrideid = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', null, PARAM_ALPHA);
$reset = optional_param('reset', false, PARAM_BOOL);

$pagetitle = get_string('editoverride', 'mod_assign');
$override = null;

if ($overrideid) {
    $override = $DB->get_record('streamassign_overrides', ['id' => $overrideid], '*', MUST_EXIST);
    list($course, $cm) = get_course_and_cm_from_instance($override->streamassignid, 'streamassign');
} else if ($cmid) {
    list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'streamassign');
} else {
    throw new moodle_exception('invalidcoursemodule');
}

$streamassign = $DB->get_record('streamassign', ['id' => $cm->instance], '*', MUST_EXIST);

$url = new moodle_url('/mod/streamassign/overrideedit.php');
if ($action) {
    $url->param('action', $action);
}
if ($overrideid) {
    $url->param('id', $overrideid);
} else {
    $url->param('cmid', $cmid);
}

$PAGE->set_url($url);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/streamassign:manageoverrides', $context);

if ($overrideid) {
    $data = clone $override;
    if ($override->groupid) {
        if (!groups_group_visible($override->groupid, $course, $cm)) {
            throw new moodle_exception('invalidoverrideid', 'mod_assign');
        }
    } else if (!groups_user_groups_visible($course, $override->userid, $cm)) {
        throw new moodle_exception('invalidoverrideid', 'mod_assign');
    }
} else {
    $data = new stdClass();
}

$keys = ['timeopen', 'timeclose'];
foreach ($keys as $key) {
    if (!isset($data->{$key}) || $reset) {
        $data->{$key} = $streamassign->{$key};
    }
}

$groupmode = !empty($data->groupid) || ($action === 'addgroup' && empty($overrideid));

if ($action === 'duplicate') {
    $override->id = $data->id = null;
    $override->userid = $data->userid = null;
    $override->groupid = $data->groupid = null;
    $pagetitle = get_string('duplicateoverride', 'mod_assign');
}

$overridelisturl = new moodle_url('/mod/streamassign/overrides.php', ['cmid' => $cm->id]);
if (!$groupmode) {
    $overridelisturl->param('mode', 'user');
}

$mform = new streamassign_override_form($url, $cm, $streamassign, $context, $groupmode, $override);
$mform->set_data($data);

if ($mform->is_cancelled()) {
    redirect($overridelisturl);
} else if ($reset) {
    $url->param('reset', true);
    redirect($url);
} else if ($fromform = $mform->get_data()) {
    $fromform->streamassignid = $streamassign->id;

    foreach ($keys as $key) {
        if (!isset($fromform->{$key}) || (int) $fromform->{$key} === (int) $streamassign->{$key}) {
            $fromform->{$key} = null;
        }
    }

    $userorgroupchanged = empty($override) || empty($override->id);
    if (!$userorgroupchanged && $override) {
        if (!empty($fromform->userid)) {
            $userorgroupchanged = (int) $fromform->userid !== (int) $override->userid;
        } else {
            $userorgroupchanged = (int) $fromform->groupid !== (int) $override->groupid;
        }
    }

    if ($userorgroupchanged) {
        $conditions = [
            'streamassignid' => $streamassign->id,
            'userid' => empty($fromform->userid) ? null : $fromform->userid,
            'groupid' => empty($fromform->groupid) ? null : $fromform->groupid,
        ];
        if ($oldoverride = $DB->get_record('streamassign_overrides', $conditions)) {
            foreach ($keys as $key) {
                if (is_null($fromform->{$key})) {
                    $fromform->{$key} = $oldoverride->{$key};
                }
            }
            streamassign_delete_override((int) $oldoverride->id, (int) $streamassign->id);
        }
    }

    if ($override && !empty($override->id)) {
        $fromform->id = $override->id;
        $DB->update_record('streamassign_overrides', $fromform);
    } else {
        unset($fromform->id);
        $fromform->id = $DB->insert_record('streamassign_overrides', $fromform);
        if ($groupmode) {
            $overridecountgroup = $DB->count_records('streamassign_overrides', [
                'userid' => null,
                'streamassignid' => $streamassign->id,
            ]);
            $fromform->sortorder = $overridecountgroup;
            $DB->update_record('streamassign_overrides', $fromform);
            streamassign_reorder_group_overrides((int) $streamassign->id);
        }
    }

    if (!empty($fromform->submitbutton)) {
        redirect($overridelisturl);
    }

    $url->remove_params('cmid');
    $url->param('action', 'duplicate');
    $url->param('id', $fromform->id);
    redirect($url);
}

$PAGE->navbar->add($pagetitle);
$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->set_attrs([
    'description' => '',
    'hidecompletion' => true,
    'title' => format_string($streamassign->name, true, ['context' => $context]),
]);

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
