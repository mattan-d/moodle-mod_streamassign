<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Stream assignment library.
 *
 * @package    mod_streamassign
 * @copyright  2025 mattandor <mattan@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Supported features.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed true if supported, null if unknown
 */
function streamassign_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_ACTIVITY;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return false;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        default:
            return null;
    }
}

/**
 * Add a new streamassign instance.
 *
 * @param stdClass $data form data
 * @param mod_streamassign_mod_form $mform form
 * @return int new instance id
 */
function streamassign_add_instance(stdClass $data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('streamassign', $data);
    streamassign_grade_item_update($data);
    return $data->id;
}

/**
 * Update streamassign instance.
 *
 * @param stdClass $data form data
 * @param mod_streamassign_mod_form $mform form
 * @return bool
 */
function streamassign_update_instance(stdClass $data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    $result = $DB->update_record('streamassign', $data);
    streamassign_grade_item_update($data);
    return $result;
}

/**
 * Delete streamassign instance.
 *
 * @param int $id instance id
 * @return bool
 */
function streamassign_delete_instance($id) {
    global $DB;

    $instance = $DB->get_record('streamassign', ['id' => $id]);
    if (!$instance) {
        return false;
    }
    $DB->delete_records('streamassign_submission', ['streamassignid' => $id]);
    $DB->delete_records('streamassign', ['id' => $id]);
    streamassign_grade_item_update($instance, null, true);
    return true;
}

/**
 * Get submission for a user (latest).
 *
 * @param int $streamassignid
 * @param int $userid
 * @return stdClass|null
 */
function streamassign_get_submission(int $streamassignid, int $userid): ?\stdClass {
    global $DB;
    return $DB->get_record('streamassign_submission', [
        'streamassignid' => $streamassignid,
        'userid' => $userid,
    ]);
}

/**
 * Update grade item for streamassign.
 *
 * @param stdClass $streamassign
 * @param stdClass|null $grades optional grades (userid, rawgrade)
 * @param bool $delete
 * @return int
 */
function streamassign_grade_item_update($streamassign, $grades = null, $delete = false) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname' => $streamassign->name,
        'idnumber' => $streamassign->id,
    ];
    if (isset($streamassign->cmidnumber)) {
        $item['idnumber'] = $streamassign->cmidnumber;
    }

    if ($delete) {
        return grade_update(
            'mod/streamassign',
            $streamassign->course,
            'mod',
            'streamassign',
            $streamassign->id,
            0,
            null,
            $item,
            ['deleted' => 1]
        );
    }

    if (!empty($streamassign->grade)) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = $streamassign->grade;
        $item['grademin'] = 0;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    return grade_update('mod/streamassign', $streamassign->course, 'mod', 'streamassign', $streamassign->id, 0, $grades, $item);
}

/**
 * Update grades for a user (e.g. when teacher sets grade).
 *
 * @param stdClass $streamassign
 * @param int $userid
 * @param float|null $rawgrade
 */
function streamassign_update_grades($streamassign, $userid, $rawgrade = null) {
    if (empty($streamassign->grade)) {
        return;
    }
    $grades = new \stdClass();
    $grades->userid = $userid;
    $grades->rawgrade = $rawgrade;
    streamassign_grade_item_update($streamassign, $grades);
}

/**
 * Get coursemodule info for display in course.
 *
 * @param stdClass $cm
 * @return cached_cm_info|null
 */
function streamassign_get_coursemodule_info($cm) {
    global $DB;
    if (!$streamassign = $DB->get_record('streamassign', ['id' => $cm->instance], 'id, name, intro, introformat')) {
        return null;
    }
    $info = new \cached_cm_info();
    $info->name = $streamassign->name;
    $info->content = format_module_intro('streamassign', $streamassign, $cm->id, false);
    return $info;
}
