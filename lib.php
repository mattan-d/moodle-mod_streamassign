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
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
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
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_ADVANCED_GRADING:
            return true;
        case FEATURE_PLAGIARISM:
            return true;
        case FEATURE_COMMENT:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
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
 * Get all submissions for an activity (for grading table).
 *
 * @param int $streamassignid
 * @param int $courseid
 * @return array indexed by userid, each element has submission + user + currentgrade
 */
function streamassign_get_submissions_for_grading(int $streamassignid, int $courseid): array {
    global $DB, $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $submissions = $DB->get_records('streamassign_submission', ['streamassignid' => $streamassignid], 'timemodified DESC');
    if (empty($submissions)) {
        return [];
    }
    $userids = array_unique(array_column($submissions, 'userid'));
    $users = $DB->get_records_list('user', 'id', $userids, '', 'id, firstname, lastname, email');
    $gradinginfo = grade_get_grades($courseid, 'mod', 'streamassign', $streamassignid, $userids);
    $gradesbyuser = [];
    if (!empty($gradinginfo->items) && isset($gradinginfo->items[0]->grades)) {
        foreach ($gradinginfo->items[0]->grades as $uid => $g) {
            $gradesbyuser[$uid] = (object) [
                'grade' => $g->grade,
                'feedback' => $g->feedback ?? '',
            ];
        }
    }
    $out = [];
    foreach ($submissions as $s) {
        $u = $users[$s->userid] ?? null;
        if (!$u) {
            continue;
        }
        $gi = $gradesbyuser[$s->userid] ?? null;
        $out[$s->userid] = (object) [
            'submission' => $s,
            'user' => $u,
            'fullname' => fullname($u),
            'currentgrade' => $gi ? $gi->grade : null,
            'currentfeedback' => $gi ? $gi->feedback : '',
        ];
    }
    return $out;
}

/**
 * Get grading summary counts for the activity (participants, submitted, need grading).
 * Used on the view page for users with grade capability.
 *
 * @param int $streamassignid
 * @param int $courseid
 * @param context_module $context
 * @return stdClass { participantcount, submittedcount, needgradingcount }
 */
function streamassign_get_grading_summary(int $streamassignid, int $courseid, context_module $context): \stdClass {
    global $DB, $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    list($esql, $params) = get_enrolled_sql($context, 'mod/streamassign:submit', 0, true);
    $params['streamassignid'] = $streamassignid;
    $participantcount = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT u.id) FROM {user} u INNER JOIN ($esql) je ON je.id = u.id",
        $params
    );

    $submittedcount = $DB->count_records('streamassign_submission', ['streamassignid' => $streamassignid]);

    $needgradingcount = 0;
    if ($submittedcount > 0) {
        $all = streamassign_get_submissions_for_grading($streamassignid, $courseid);
        foreach ($all as $row) {
            if ($row->currentgrade === null || $row->currentgrade === '') {
                $needgradingcount++;
            }
        }
    }

    return (object) [
        'participantcount' => (int) $participantcount,
        'submittedcount' => (int) $submittedcount,
        'needgradingcount' => (int) $needgradingcount,
    ];
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
    $record = $DB->get_record('streamassign_submission', [
        'streamassignid' => $streamassignid,
        'userid' => $userid,
    ]);
    return $record ?: null;
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

    $grade = isset($streamassign->grade) ? (int) $streamassign->grade : 0;
    if ($grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = $grade;
        $item['grademin'] = 0;
    } else if ($grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid'] = -$grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    return grade_update('mod/streamassign', $streamassign->course, 'mod', 'streamassign', $streamassign->id, 0, $grades, $item);
}

/**
 * Update grades for a user (e.g. when teacher sets grade and/or feedback).
 *
 * @param stdClass $streamassign
 * @param int $userid
 * @param float|null $rawgrade
 * @param string|null $feedback
 * @param int $feedbackformat FORMAT_PLAIN, FORMAT_HTML, etc.
 */
function streamassign_update_grades($streamassign, $userid, $rawgrade = null, $feedback = null, $feedbackformat = FORMAT_PLAIN) {
    $grades = new \stdClass();
    $grades->userid = $userid;
    $grades->rawgrade = $rawgrade;
    if ($feedback !== null) {
        $grades->feedback = $feedback;
        $grades->feedbackformat = $feedbackformat;
    }
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

/**
 * Extend activity settings navigation with grading shortcut.
 *
 * @param settings_navigation $settingsnav
 * @param navigation_node $streamassignnode
 * @return void
 */
function streamassign_extend_settings_navigation($settingsnav, $streamassignnode = null) {
    global $PAGE;

    if (empty($PAGE->cm) || $PAGE->cm->modname !== 'streamassign') {
        return;
    }

    $context = context_module::instance($PAGE->cm->id);
    if (!has_capability('mod/streamassign:grade', $context)) {
        return;
    }

    $gradingurl = new moodle_url('/mod/streamassign/grading.php', ['id' => $PAGE->cm->id]);
    $parentnode = $streamassignnode ?: $settingsnav;
    $node = $parentnode->add(
        get_string('viewgrading', 'streamassign'),
        $gradingurl,
        navigation_node::TYPE_SETTING,
        null,
        'streamassigngrading'
    );

    if ($PAGE->url->compare($gradingurl, URL_MATCH_BASE)) {
        $node->make_active();
    }
}
