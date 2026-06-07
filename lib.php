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
    require_once(__DIR__ . '/overridelib.php');
    $DB->delete_records('streamassign_submission', ['streamassignid' => $id]);
    streamassign_delete_all_overrides((int) $id);
    $DB->delete_records('streamassign', ['id' => $id]);
    streamassign_grade_item_update($instance, null, true);
    return true;
}

/**
 * File type groups and extensions selectable in activity/admin settings.
 *
 * @return string[]
 */
function streamassign_get_selectable_filetypes(): array {
    return ['video', 'audio', '.mkv', '.vob'];
}

/**
 * Default allowed file types (video + audio + formats not in Moodle groups).
 *
 * @return string
 */
function streamassign_get_default_filetypes(): string {
    $default = get_config('mod_streamassign', 'filetypes');
    if ($default === false || $default === '') {
        return 'video,audio,.mkv,.vob';
    }
    return (string) $default;
}

/**
 * All media extensions supported by this plugin and the Stream platform.
 *
 * @return string[]
 */
function streamassign_get_all_extensions(): array {
    return [
        'mp4', 'flv', 'webm', 'mkv', 'vob', 'ogv', 'ogg', 'avi', 'wmv', 'mov', 'mpeg', 'mpg',
        'mp3', 'wav', 'm4a', 'aac',
    ];
}

/**
 * Get configured file types string for an activity.
 *
 * @param stdClass $streamassign Activity record.
 * @return string
 */
function streamassign_get_configured_filetypes(\stdClass $streamassign): string {
    if (!empty($streamassign->filetypeslist)) {
        return (string) $streamassign->filetypeslist;
    }
    return streamassign_get_default_filetypes();
}

/**
 * Get allowed extensions for an activity after applying file type settings.
 *
 * @param stdClass|null $streamassign Activity record.
 * @return string[] Extensions without leading dot.
 */
function streamassign_get_allowed_extensions(?\stdClass $streamassign = null): array {
    $filetypes = $streamassign ? streamassign_get_configured_filetypes($streamassign) : streamassign_get_default_filetypes();
    $util = new \core_form\filetypes_util();
    $allowed = [];
    foreach (streamassign_get_all_extensions() as $ext) {
        if ($util->is_allowed_file_type('file.' . $ext, $filetypes)) {
            $allowed[] = $ext;
        }
    }
    return $allowed;
}

/**
 * Check whether a file extension is allowed for submission.
 *
 * @param string $extension Extension without leading dot.
 * @param stdClass|null $streamassign Activity record.
 * @return bool
 */
function streamassign_is_allowed_extension(string $extension, ?\stdClass $streamassign = null): bool {
    return in_array(strtolower($extension), streamassign_get_allowed_extensions($streamassign), true);
}

/**
 * Check whether a filename is allowed for submission.
 *
 * @param string $filename File name.
 * @param stdClass|null $streamassign Activity record.
 * @return bool
 */
function streamassign_is_allowed_filename(string $filename, ?\stdClass $streamassign = null): bool {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, streamassign_get_all_extensions(), true)) {
        return false;
    }
    $filetypes = $streamassign ? streamassign_get_configured_filetypes($streamassign) : streamassign_get_default_filetypes();
    $util = new \core_form\filetypes_util();
    return $util->is_allowed_file_type($filename, $filetypes);
}

/**
 * HTML accept attribute value for the media file input.
 *
 * @param stdClass|null $streamassign Activity record.
 * @return string
 */
function streamassign_get_accept_attribute(?\stdClass $streamassign = null): string {
    $filetypes = $streamassign ? streamassign_get_configured_filetypes($streamassign) : streamassign_get_default_filetypes();
    $util = new \core_form\filetypes_util();
    $normalized = $util->normalize_file_types($filetypes);
    $parts = [];
    if (in_array('video', $normalized, true)) {
        $parts[] = 'video/*';
    }
    if (in_array('audio', $normalized, true)) {
        $parts[] = 'audio/*';
    }
    foreach (streamassign_get_allowed_extensions($streamassign) as $ext) {
        $parts[] = '.' . $ext;
    }
    return implode(',', array_unique($parts));
}

/**
 * Human-readable description of allowed file types for an activity.
 *
 * @param stdClass|null $streamassign Activity record.
 * @return string
 */
function streamassign_get_allowedformats_description(?\stdClass $streamassign = null): string {
    $filetypes = $streamassign ? streamassign_get_configured_filetypes($streamassign) : streamassign_get_default_filetypes();
    $util = new \core_form\filetypes_util();
    $desc = $util->describe_file_types($filetypes);
    if (empty($desc->hasdescriptions)) {
        return get_string('allowedformats', 'streamassign');
    }
    $parts = [];
    foreach ($desc->descriptions as $item) {
        $line = $item->description;
        if (!empty($item->extensions)) {
            $line .= ' (' . $item->extensions . ')';
        }
        $parts[] = $line;
    }
    return get_string('allowedformatstypes', 'streamassign') . ' ' . implode('; ', $parts);
}

/**
 * Get the configured maximum number of videos per student for an activity.
 *
 * @param stdClass $streamassign Activity record.
 * @return int
 */
function streamassign_get_maxvideos(\stdClass $streamassign): int {
    $max = isset($streamassign->maxvideos) ? (int) $streamassign->maxvideos : 1;
    return max(1, $max);
}

/**
 * Whether team (group) submission mode is enabled.
 *
 * @param stdClass $streamassign
 * @return bool
 */
function streamassign_is_team_submission(\stdClass $streamassign): bool {
    return !empty($streamassign->teamsubmission);
}

/**
 * Get the submission group for a user (exactly one group in grouping, or false).
 *
 * @param stdClass $streamassign
 * @param int $courseid
 * @param int $userid
 * @return stdClass|false|null Group record, false if zero/multiple groups, null if not team mode.
 */
function streamassign_get_submission_group(\stdClass $streamassign, int $courseid, int $userid) {
    if (!streamassign_is_team_submission($streamassign)) {
        return null;
    }
    $groupingid = (int) ($streamassign->teamsubmissiongroupingid ?? 0);
    $groups = groups_get_all_groups($courseid, $userid, $groupingid, 'g.*', false, true);
    if (count($groups) !== 1) {
        return false;
    }
    return reset($groups);
}

/**
 * Resolve submission ownership keys for insert/update.
 *
 * @param stdClass $streamassign
 * @param int $courseid
 * @param int $userid Current user submitting.
 * @return stdClass {groupid, userid, submittedby, group?} or {error}
 */
function streamassign_get_submission_target(\stdClass $streamassign, int $courseid, int $userid): \stdClass {
    if (!streamassign_is_team_submission($streamassign)) {
        return (object) [
            'groupid' => 0,
            'userid' => $userid,
            'submittedby' => $userid,
        ];
    }

    $group = streamassign_get_submission_group($streamassign, $courseid, $userid);
    if (!empty($streamassign->preventsubmissionnotingroup) && !$group) {
        return (object) ['error' => get_string('notingroup', 'streamassign')];
    }
    if ($group) {
        return (object) [
            'groupid' => (int) $group->id,
            'userid' => 0,
            'submittedby' => $userid,
            'group' => $group,
        ];
    }

    return (object) [
        'groupid' => 0,
        'userid' => $userid,
        'submittedby' => $userid,
    ];
}

/**
 * Get active group members enrolled with submit capability.
 *
 * @param int $groupid
 * @param context_module $context
 * @return stdClass[] indexed by userid
 */
function streamassign_get_group_members(int $groupid, context_module $context): array {
    $members = groups_get_members($groupid, 'u.*', 'u.lastname ASC, u.firstname ASC');
    foreach ($members as $id => $member) {
        if (!is_enrolled($context, $member, 'mod/streamassign:submit')) {
            unset($members[$id]);
        }
    }
    return $members;
}

/**
 * Count submissions for a user or their team.
 *
 * @param stdClass $streamassign
 * @param int $courseid
 * @param int $userid
 * @return int
 */
function streamassign_count_submissions(\stdClass $streamassign, int $courseid, int $userid): int {
    global $DB;

    if (streamassign_is_team_submission($streamassign)) {
        $group = streamassign_get_submission_group($streamassign, $courseid, $userid);
        if ($group) {
            return $DB->count_records('streamassign_submission', [
                'streamassignid' => $streamassign->id,
                'groupid' => $group->id,
            ]);
        }
    }

    return $DB->count_records('streamassign_submission', [
        'streamassignid' => $streamassign->id,
        'groupid' => 0,
        'userid' => $userid,
    ]);
}

/**
 * Count how many videos an individual user owns (non-team submissions only).
 *
 * @param int $streamassignid
 * @param int $userid
 * @return int
 */
function streamassign_count_user_submissions(int $streamassignid, int $userid): int {
    global $DB;
    return $DB->count_records('streamassign_submission', [
        'streamassignid' => $streamassignid,
        'groupid' => 0,
        'userid' => $userid,
    ]);
}

/**
 * Whether a user may submit another video for this activity.
 *
 * @param stdClass $streamassign Activity record.
 * @param int $userid
 * @param int $courseid
 * @return bool
 */
function streamassign_user_can_submit(\stdClass $streamassign, int $userid, int $courseid = 0): bool {
    if (streamassign_is_team_submission($streamassign) && $courseid) {
        if (!empty($streamassign->preventsubmissionnotingroup) && !streamassign_get_submission_group($streamassign, $courseid, $userid)) {
            return false;
        }
    }

    $max = streamassign_get_maxvideos($streamassign);
    $count = $courseid ? streamassign_count_submissions($streamassign, $courseid, $userid)
        : streamassign_count_user_submissions((int) $streamassign->id, $userid);

    if ($count >= 1 && empty($streamassign->allowresubmission) && $max <= 1) {
        return false;
    }
    if ($count >= $max) {
        return ($max === 1 && !empty($streamassign->allowresubmission));
    }
    return true;
}

/**
 * Get all submissions visible to a user (individual or team), newest first.
 *
 * @param int $streamassignid
 * @param int $userid
 * @param stdClass|null $streamassign
 * @param int $courseid
 * @return stdClass[]
 */
function streamassign_get_user_submissions(int $streamassignid, int $userid, ?\stdClass $streamassign = null, int $courseid = 0): array {
    global $DB;

    if ($streamassign && streamassign_is_team_submission($streamassign) && $courseid) {
        $group = streamassign_get_submission_group($streamassign, $courseid, $userid);
        if ($group) {
            return $DB->get_records('streamassign_submission', [
                'streamassignid' => $streamassignid,
                'groupid' => $group->id,
            ], 'timemodified DESC');
        }
        return [];
    }

    return $DB->get_records('streamassign_submission', [
        'streamassignid' => $streamassignid,
        'groupid' => 0,
        'userid' => $userid,
    ], 'timemodified DESC');
}

/**
 * Get latest submission for replace/resubmit logic.
 *
 * @param stdClass $streamassign
 * @param int $courseid
 * @param int $userid
 * @return stdClass|null
 */
function streamassign_get_latest_submission(\stdClass $streamassign, int $courseid, int $userid): ?\stdClass {
    global $DB;

    if (streamassign_is_team_submission($streamassign)) {
        $group = streamassign_get_submission_group($streamassign, $courseid, $userid);
        if (!$group) {
            return null;
        }
        $records = $DB->get_records('streamassign_submission', [
            'streamassignid' => $streamassign->id,
            'groupid' => $group->id,
        ], 'timemodified DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    return streamassign_get_submission((int) $streamassign->id, $userid);
}

/**
 * Delete a single submission (teacher/admin action).
 *
 * Clears grades when no submissions remain for the owner (user or group).
 *
 * @param int $submissionid Submission record id.
 * @param int $streamassignid Activity id (must match the submission).
 * @return bool True if a record was deleted.
 */
function streamassign_delete_submission(int $submissionid, int $streamassignid): bool {
    global $DB;

    $submission = $DB->get_record('streamassign_submission', [
        'id' => $submissionid,
        'streamassignid' => $streamassignid,
    ]);
    if (!$submission) {
        return false;
    }

    $groupid = (int) $submission->groupid;
    $userid = (int) $submission->userid;
    $DB->delete_records('streamassign_submission', ['id' => $submissionid]);

    $streamassign = $DB->get_record('streamassign', ['id' => $streamassignid]);
    if (!$streamassign) {
        return true;
    }

    if ($groupid > 0) {
        if (!$DB->record_exists('streamassign_submission', [
            'streamassignid' => $streamassignid,
            'groupid' => $groupid,
        ])) {
            $cm = get_coursemodule_from_instance('streamassign', $streamassignid, $streamassign->course, false, IGNORE_MISSING);
            if ($cm) {
                $context = context_module::instance($cm->id);
                streamassign_update_group_grades($streamassign, $groupid, $context, null, null);
            }
        }
    } else if ($userid > 0 && !streamassign_count_user_submissions($streamassignid, $userid)) {
        streamassign_update_grades($streamassign, $userid, null, null);
    }

    return true;
}

/**
 * Get all submissions for an activity (for grading table).
 *
 * @param int $streamassignid
 * @param int $courseid
 * @param context_module|null $context
 * @return array keyed by userid or g{groupid}
 */
function streamassign_get_submissions_for_grading(int $streamassignid, int $courseid, ?context_module $context = null): array {
    global $DB, $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $streamassign = $DB->get_record('streamassign', ['id' => $streamassignid], '*', MUST_EXIST);
    $submissions = $DB->get_records('streamassign_submission', ['streamassignid' => $streamassignid], 'timemodified DESC');
    if (empty($submissions)) {
        return [];
    }

    $userids = [];
    $groupids = [];
    foreach ($submissions as $s) {
        if ((int) $s->groupid > 0) {
            $groupids[(int) $s->groupid] = true;
        } else if ((int) $s->userid > 0) {
            $userids[(int) $s->userid] = true;
        }
        if ((int) $s->submittedby > 0) {
            $userids[(int) $s->submittedby] = true;
        }
    }

    $userfields = implode(',', \core_user\fields::for_userpic()->get_required_fields());
    $users = $userids ? $DB->get_records_list('user', 'id', array_keys($userids), '', $userfields) : [];
    $groups = $groupids ? $DB->get_records_list('groups', 'id', array_keys($groupids), '', 'id,name') : [];

    $allgradeuserids = array_keys($userids);
    if (streamassign_is_team_submission($streamassign) && $context && $groupids) {
        foreach (array_keys($groupids) as $groupid) {
            foreach (streamassign_get_group_members($groupid, $context) as $member) {
                $allgradeuserids[(int) $member->id] = (int) $member->id;
            }
        }
    }
    $allgradeuserids = array_values(array_unique($allgradeuserids));

    $gradinginfo = $allgradeuserids ? grade_get_grades($courseid, 'mod', 'streamassign', $streamassignid, $allgradeuserids) : null;
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
        if ((int) $s->groupid > 0) {
            $key = 'g' . $s->groupid;
            if (!isset($out[$key])) {
                $group = $groups[$s->groupid] ?? null;
                $members = ($context && $group) ? streamassign_get_group_members((int) $s->groupid, $context) : [];
                $memberids = array_keys($members);
                $grade = null;
                $feedback = '';
                foreach ($memberids as $memberid) {
                    if (isset($gradesbyuser[$memberid])) {
                        $grade = $gradesbyuser[$memberid]->grade;
                        $feedback = $gradesbyuser[$memberid]->feedback;
                        break;
                    }
                }
                $submitter = !empty($s->submittedby) && isset($users[$s->submittedby]) ? $users[$s->submittedby] : null;
                $out[$key] = (object) [
                    'isteam' => true,
                    'groupid' => (int) $s->groupid,
                    'groupname' => $group ? $group->name : '',
                    'members' => $members,
                    'submitter' => $submitter,
                    'submissions' => [],
                    'user' => $submitter,
                    'fullname' => $group ? format_string($group->name) : '',
                    'currentgrade' => $grade,
                    'currentfeedback' => $feedback,
                    'gradekey' => $key,
                ];
            }
            $out[$key]->submissions[] = $s;
            if (!empty($s->submittedby) && isset($users[$s->submittedby])) {
                $out[$key]->submitter = $users[$s->submittedby];
                $out[$key]->user = $users[$s->submittedby];
            }
        } else {
            $u = $users[$s->userid] ?? null;
            if (!$u) {
                continue;
            }
            if (!isset($out[$s->userid])) {
                $gi = $gradesbyuser[$s->userid] ?? null;
                $out[$s->userid] = (object) [
                    'isteam' => false,
                    'groupid' => 0,
                    'groupname' => '',
                    'members' => [],
                    'submitter' => $u,
                    'submissions' => [],
                    'user' => $u,
                    'fullname' => fullname($u),
                    'currentgrade' => $gi ? $gi->grade : null,
                    'currentfeedback' => $gi ? $gi->feedback : '',
                    'gradekey' => (string) $s->userid,
                ];
            }
            $out[$s->userid]->submissions[] = $s;
        }
    }
    return $out;
}

/**
 * Get grading summary counts for the activity (participants, submitted, need grading).
 *
 * @param int $streamassignid
 * @param int $courseid
 * @param context_module $context
 * @return stdClass { participantcount, submittedcount, needgradingcount }
 */
function streamassign_get_grading_summary(int $streamassignid, int $courseid, context_module $context): \stdClass {
    global $DB;

    list($esql, $params) = get_enrolled_sql($context, 'mod/streamassign:submit', 0, true);
    $participantcount = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT u.id) FROM {user} u INNER JOIN ($esql) je ON je.id = u.id",
        $params
    );

    $streamassign = $DB->get_record('streamassign', ['id' => $streamassignid], '*', MUST_EXIST);
    if (streamassign_is_team_submission($streamassign)) {
        $submittedcount = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT CASE WHEN groupid > 0 THEN groupid ELSE userid END)
               FROM {streamassign_submission}
              WHERE streamassignid = :streamassignid",
            ['streamassignid' => $streamassignid]
        );
    } else {
        $submittedcount = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid) FROM {streamassign_submission}
              WHERE streamassignid = :streamassignid AND groupid = 0",
            ['streamassignid' => $streamassignid]
        );
    }

    $needgradingcount = 0;
    if ($submittedcount > 0) {
        $all = streamassign_get_submissions_for_grading($streamassignid, $courseid, $context);
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
 * Get submission for a user (latest individual submission).
 *
 * @param int $streamassignid
 * @param int $userid
 * @return stdClass|null
 */
function streamassign_get_submission(int $streamassignid, int $userid): ?\stdClass {
    global $DB;
    $records = $DB->get_records('streamassign_submission', [
        'streamassignid' => $streamassignid,
        'groupid' => 0,
        'userid' => $userid,
    ], 'timemodified DESC', '*', 0, 1);
    return $records ? reset($records) : null;
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
 * Apply the same grade and feedback to all members of a group.
 *
 * @param stdClass $streamassign
 * @param int $groupid
 * @param context_module $context
 * @param float|int|null $rawgrade
 * @param string|null $feedback
 * @param int $feedbackformat
 * @return void
 */
function streamassign_update_group_grades(
    $streamassign,
    int $groupid,
    context_module $context,
    $rawgrade = null,
    $feedback = null,
    int $feedbackformat = FORMAT_PLAIN
): void {
    foreach (streamassign_get_group_members($groupid, $context) as $member) {
        streamassign_update_grades($streamassign, (int) $member->id, $rawgrade, $feedback, $feedbackformat);
    }
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

    if ($PAGE->cm->modname !== 'streamassign') {
        return;
    }

    $context = context_module::instance($PAGE->cm->id);
    $parentnode = $streamassignnode ?: $settingsnav;

    $keys = $parentnode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability('mod/streamassign:manageoverrides', $context)) {
        $overrideurl = new moodle_url('/mod/streamassign/overrides.php', [
            'cmid' => $PAGE->cm->id,
            'mode' => 'user',
        ]);
        $node = navigation_node::create(
            get_string('overrides', 'mod_assign'),
            $overrideurl,
            navigation_node::TYPE_SETTING,
            null,
            'mod_streamassign_overrides'
        );
        $parentnode->add_node($node, $beforekey);
        if ($PAGE->url->compare($overrideurl, URL_MATCH_BASE)) {
            $node->make_active();
        }
    }

    if (has_capability('mod/streamassign:grade', $context)) {
        $gradingurl = new moodle_url('/mod/streamassign/grading.php', ['id' => $PAGE->cm->id]);
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
}

/**
 * Options for the maxvideos activity setting select (1..site limit).
 *
 * @return array
 */
function streamassign_get_maxvideos_form_options(): array {
    $limit = (int) get_config('mod_streamassign', 'maxvideos');
    if ($limit < 1) {
        $limit = 20;
    }
    $options = [];
    for ($i = 1; $i <= $limit; $i++) {
        $options[$i] = $i;
    }
    return $options;
}

/**
 * Resolve the effective maximum upload size for an activity (bytes).
 *
 * @param stdClass $streamassign Activity record (may include maxbytes).
 * @param stdClass $course Course record (maxbytes).
 * @return int Effective limit in bytes.
 */
function streamassign_get_maxbytes(\stdClass $streamassign, \stdClass $course): int {
    global $CFG;

    $modulebytes = isset($streamassign->maxbytes) ? (int) $streamassign->maxbytes : 0;
    if ($modulebytes === 0) {
        $modulebytes = (int) get_config('mod_streamassign', 'maxbytes');
    }
    if ($modulebytes === 0) {
        $modulebytes = 2147483648;
    }

    return get_max_upload_file_size($CFG->maxbytes, $course->maxbytes, $modulebytes);
}
