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
 * Stream assignment local helpers (upload handling, form).
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/stream_uploader.php');

/**
 * Notify graders that a student submitted a video.
 *
 * @param stdClass $streamassign Activity record.
 * @param stdClass $cm Course module record.
 * @param stdClass $student User record of submitter.
 * @param string $videotitle Submitted video title.
 * @param bool $islate Whether this is a late submission.
 * @return void
 */
function streamassign_notify_graders_submission(
    \stdClass $streamassign,
    \stdClass $cm,
    \stdClass $student,
    string $videotitle,
    bool $islate = false
): void {
    global $DB;

    if (empty($streamassign->emailalertstoteachers)) {
        return;
    }
    if ($islate && empty($streamassign->notifygraderslatesubmission)) {
        return;
    }

    $context = \context_module::instance((int) $cm->id);
    $course = $DB->get_record('course', ['id' => $streamassign->course], 'id,fullname', MUST_EXIST);

    $graders = get_enrolled_users(
        $context,
        'mod/streamassign:grade',
        0,
        'u.id,u.email,u.deleted,u.suspended,u.firstname,u.lastname'
    );
    if (empty($graders)) {
        return;
    }

    $studentname = fullname($student);
    $activityname = format_string($streamassign->name, true, ['context' => $context]);
    $submittedtitle = trim($videotitle) !== '' ? $videotitle : get_string('videotitle', 'streamassign');
    $subject = get_string('messageprovider:submission', 'streamassign') . ': ' . $activityname;
    $smallmessage = get_string('notificationnewsubmission', 'streamassign', (object) [
        'student' => $studentname,
        'activity' => $activityname,
    ]);
    if ($islate) {
        $smallmessage = get_string('notificationlatesubmission', 'streamassign', (object) [
            'student' => $studentname,
            'activity' => $activityname,
        ]);
    }
    $bodystring = $islate ? 'notificationlatesubmissionbody' : 'notificationnewsubmissionbody';
    $messagebody = get_string($bodystring, 'streamassign', (object) [
        'student' => $studentname,
        'activity' => $activityname,
        'course' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
        'videotitle' => $submittedtitle,
    ]);
    $url = new \moodle_url('/mod/streamassign/grading.php', ['id' => $cm->id]);

    foreach ($graders as $grader) {
        if ((int) $grader->id === (int) $student->id || !empty($grader->deleted) || !empty($grader->suspended)) {
            continue;
        }
        $eventdata = new \core\message\message();
        $eventdata->component = 'mod_streamassign';
        $eventdata->name = 'submission';
        $eventdata->userfrom = \core_user::get_noreply_user();
        $eventdata->userto = $grader;
        $eventdata->subject = $subject;
        $eventdata->fullmessage = $messagebody;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = '';
        $eventdata->smallmessage = $smallmessage;
        $eventdata->contexturl = $url->out(false);
        $eventdata->contexturlname = $activityname;
        $eventdata->courseid = (int) $streamassign->course;
        message_send($eventdata);
    }
}

/**
 * Handle video upload: get file from draft, send to Stream API, save submission.
 *
 * @param context_module $context
 * @param stdClass $streamassign
 * @param stdClass $cm
 * @param int $draftid draft file area itemid
 * @param string $videotitle optional title
 * @return stdClass { success: bool, message?: string }
 */
function streamassign_handle_upload($context, $streamassign, $cm, $draftid, $videotitle = '') {
    global $USER, $DB;

    $result = (object) ['success' => false, 'message' => '', 'debuginfo' => ''];

    $fs = get_file_storage();
    $usercontext = context_user::instance($USER->id);
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id', false);
    if (empty($files)) {
        $result->message = get_string('uploaderror', 'streamassign');
        $result->debuginfo = 'no_file_in_draft';
        return $result;
    }
    $file = reset($files);

    $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
    $allowed = ['mp4', 'flv', 'webm', 'mkv', 'vob', 'ogv', 'ogg', 'avi', 'wmv', 'mov', 'mpeg', 'mpg'];
    if (!in_array($ext, $allowed)) {
        $result->message = get_string('uploaderror', 'streamassign') . ' ' . get_string('allowedformats', 'streamassign');
        $result->debuginfo = 'invalid_extension: ' . $ext;
        return $result;
    }

    $temp = $file->copy_content_to_temp();
    if ($temp === false) {
        $result->message = get_string('uploaderror', 'streamassign');
        $result->debuginfo = 'copy_content_to_temp_failed';
        return $result;
    }

    $metadata = [
        'courseid' => (int) $streamassign->course,
        'cmid' => (int) $cm->id,
        'activity' => 'streamassign',
    ];
    $upload = \mod_streamassign\stream_uploader::upload(
        $temp,
        $file->get_filename(),
        $USER,
        $metadata,
        $videotitle !== '' ? $videotitle : null
    );
    @unlink($temp);

    if (!$upload->success) {
        $result->message = $upload->message;
        $result->debuginfo = isset($upload->debuginfo) ? $upload->debuginfo : '';
        return $result;
    }

    $now = time();
    $submission = $DB->get_record('streamassign_submission', [
        'streamassignid' => $streamassign->id,
        'userid' => $USER->id,
    ]);
    $title = $videotitle !== '' ? $videotitle : ($upload->topic ?? $file->get_filename());

    if ($submission) {
        $submission->streamid = $upload->streamid;
        $submission->videotitle = $title;
        $submission->timemodified = $now;
        $DB->update_record('streamassign_submission', $submission);
    } else {
        $DB->insert_record('streamassign_submission', (object) [
            'streamassignid' => $streamassign->id,
            'userid' => $USER->id,
            'streamid' => $upload->streamid,
            'videotitle' => $title,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    $islate = ($streamassign->timeclose > 0 && $now > (int) $streamassign->timeclose);
    streamassign_notify_graders_submission($streamassign, $cm, $USER, $title, $islate);
    $result->success = true;
    return $result;
}

/**
 * Save submission by selecting an existing video from the user's Stream library (no upload).
 *
 * @param stdClass $streamassign
 * @param stdClass $cm
 * @param int $streamid Video id on Stream platform
 * @param string $videotitle Title to store (e.g. from API video title)
 * @return stdClass { success: bool, message?: string }
 */
function streamassign_handle_existing_video($streamassign, $cm, $streamid, $videotitle = '') {
    global $USER, $DB;

    $result = (object) ['success' => false, 'message' => ''];
    $streamid = (int) $streamid;
    if ($streamid <= 0) {
        $result->message = get_string('uploaderror', 'streamassign');
        return $result;
    }

    $now = time();
    $submission = $DB->get_record('streamassign_submission', [
        'streamassignid' => $streamassign->id,
        'userid' => $USER->id,
    ]);
    $title = trim($videotitle) !== '' ? trim($videotitle) : get_string('videotitle', 'streamassign');

    if ($submission) {
        $submission->streamid = $streamid;
        $submission->videotitle = $title;
        $submission->timemodified = $now;
        $DB->update_record('streamassign_submission', $submission);
    } else {
        $DB->insert_record('streamassign_submission', (object) [
            'streamassignid' => $streamassign->id,
            'userid' => $USER->id,
            'streamid' => $streamid,
            'videotitle' => $title,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    $islate = ($streamassign->timeclose > 0 && $now > (int) $streamassign->timeclose);
    streamassign_notify_graders_submission($streamassign, $cm, $USER, $title, $islate);
    $result->success = true;
    return $result;
}

/**
 * Notify a student that grade/feedback was updated.
 *
 * @param stdClass $streamassign Activity record.
 * @param stdClass $cm Course module record.
 * @param stdClass $student Student user record.
 * @return void
 */
function streamassign_notify_student_grade_updated(\stdClass $streamassign, \stdClass $cm, \stdClass $student): void {
    global $DB;

    $course = $DB->get_record('course', ['id' => $streamassign->course], 'id,fullname', MUST_EXIST);
    $context = \context_module::instance((int) $cm->id);
    $activityname = format_string($streamassign->name, true, ['context' => $context]);
    $coursename = format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);
    $subject = get_string('messageprovider:gradeupdated', 'streamassign') . ': ' . $activityname;
    $smallmessage = get_string('notificationgradeupdated', 'streamassign', (object) [
        'activity' => $activityname,
    ]);
    $messagebody = get_string('notificationgradeupdatedbody', 'streamassign', (object) [
        'course' => $coursename,
        'activity' => $activityname,
    ]);
    $url = new \moodle_url('/mod/streamassign/view.php', ['id' => $cm->id]);

    $eventdata = new \core\message\message();
    $eventdata->component = 'mod_streamassign';
    $eventdata->name = 'gradeupdated';
    $eventdata->userfrom = \core_user::get_noreply_user();
    $eventdata->userto = $student;
    $eventdata->subject = $subject;
    $eventdata->fullmessage = $messagebody;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml = '';
    $eventdata->smallmessage = $smallmessage;
    $eventdata->contexturl = $url->out(false);
    $eventdata->contexturlname = $activityname;
    $eventdata->courseid = (int) $streamassign->course;
    message_send($eventdata);
}

/**
 * Render the submission form (uses submission_form moodleform).
 *
 * @param context_module $context
 * @param int $cmid
 * @return string HTML
 */
function streamassign_render_submission_form($context, $cmid) {
    $customdata = (object) ['context' => $context, 'cmid' => $cmid];
    $form = new \mod_streamassign\submission_form(new moodle_url('/mod/streamassign/view.php', ['id' => $cmid]), $customdata);
    $form->set_data(['id' => $cmid]);
    return $form->render();
}
