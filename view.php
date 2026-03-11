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
 * Stream assignment view and submission.
 *
 * @package    mod_streamassign
 * @copyright  2025 mattandor <mattan@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

global $DB, $USER, $PAGE, $OUTPUT;

$id = optional_param('id', 0, PARAM_INT);
$n  = optional_param('n', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('streamassign', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $streamassign = $DB->get_record('streamassign', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($n) {
    $streamassign = $DB->get_record('streamassign', ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $streamassign->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('streamassign', $streamassign->id, $course->id, false, MUST_EXIST);
} else {
    throw new \moodle_exception('invalidcoursemodule');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/streamassign:view', $context);

$PAGE->set_url('/mod/streamassign/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($streamassign->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/mod/streamassign/styles.css');

$timenow = time();
$canedit = has_capability('mod/streamassign:addinstance', $context);
$opens = $streamassign->timeopen > 0 && $timenow < $streamassign->timeopen;
$closed = $streamassign->timeclose > 0 && $timenow > $streamassign->timeclose;
$cansubmit = has_capability('mod/streamassign:submit', $context) && !$opens && !$closed;

$submission = streamassign_get_submission((int) $streamassign->id, (int) $USER->id);
$streamurl = \mod_streamassign\stream_uploader::get_stream_base_url();
$streamconfigured = \mod_streamassign\stream_uploader::is_configured();

// Build submission form once so validation errors are shown on same form.
$submissionform = null;
if ($cansubmit && $streamconfigured) {
    $uservideos = [];
    $userlist = \mod_streamassign\stream_uploader::get_user_videos($USER->email, 100, 0);
    if (!$userlist->error && !empty($userlist->videos)) {
        $uservideos = $userlist->videos;
    }
    $customdata = (object) ['context' => $context, 'cmid' => $cm->id, 'uservideos' => $uservideos];
    $submissionform = new \mod_streamassign\submission_form($PAGE->url, $customdata);
    if (!empty($uservideos)) {
        $PAGE->requires->js_call_amd('mod_streamassign/videopicker', 'init', []);
    }
    if ($submissionform->is_cancelled()) {
        redirect($PAGE->url);
    }
    if ($fromform = $submissionform->get_data()) {
        $submissiontype = $fromform->submission_type_group ?? $fromform->submission_type ?? 'upload';
        if ($submissiontype === 'existing') {
            $existingid = (int) ($fromform->existing_video_id ?? 0);
            if ($existingid > 0) {
                $videotitle = '';
                foreach ($userlist->videos as $v) {
                    if ((int) ($v['id'] ?? 0) === $existingid) {
                        $videotitle = $v['title'] ?? '';
                        break;
                    }
                }
                $saveresult = streamassign_handle_existing_video($streamassign, $cm, $existingid, $videotitle);
                if ($saveresult->success) {
                    redirect($PAGE->url, get_string('uploadsuccess', 'streamassign'), null, \core\output\notification::NOTIFY_SUCCESS);
                } else {
                    redirect($PAGE->url, $saveresult->message ?: get_string('uploaderror', 'streamassign'), null, \core\output\notification::NOTIFY_ERROR);
                }
            }
        } else {
            $draftid = $fromform->video_file ?? 0;
            $videotitle = $fromform->videotitle ?? '';
            if ($draftid) {
                $uploadresult = streamassign_handle_upload($context, $streamassign, $cm, $draftid, $videotitle);
                if ($uploadresult->success) {
                    redirect($PAGE->url, get_string('uploadsuccess', 'streamassign'), null, \core\output\notification::NOTIFY_SUCCESS);
                } else {
                    $errmsg = get_string('uploaderror', 'streamassign');
                    if ($uploadresult->message !== '') {
                        $errmsg .= ': ' . $uploadresult->message;
                    }
                    if (!empty($uploadresult->debuginfo) && debugging('', DEBUG_DEVELOPER)) {
                        $errmsg .= ' [' . s($uploadresult->debuginfo) . ']';
                    }
                    redirect($PAGE->url, $errmsg, null, \core\output\notification::NOTIFY_ERROR);
                }
            }
        }
    }
}

$cangrade = has_capability('mod/streamassign:grade', $context);

// Submission summary for graders (like mod_assign).
$gradingsummary = null;
if ($cangrade) {
    $gradingsummary = streamassign_get_grading_summary((int) $streamassign->id, (int) $course->id, $context);
}

echo $OUTPUT->header();

if ($cangrade) {
    if ($gradingsummary) {
        $notsubmitted = $gradingsummary->participantcount - $gradingsummary->submittedcount;
        $summarytable = new html_table();
        $summarytable->attributes['class'] = 'generaltable streamassign-submissionsummary';
        $summarytable->head = [get_string('submissionsummary', 'streamassign'), ''];
        $gradingurl = new moodle_url('/mod/streamassign/grading.php', ['id' => $cm->id]);
        $needgradingcell = $gradingsummary->needgradingcount;
        if ($gradingsummary->needgradingcount > 0) {
            $needgradingcell = html_writer::link($gradingurl, $gradingsummary->needgradingcount);
        }
        $summarytable->data = [
            [get_string('numberofparticipants', 'streamassign'), $gradingsummary->participantcount],
            [get_string('numberofsubmitted', 'streamassign'), $gradingsummary->submittedcount],
            [get_string('numberofneedgrading', 'streamassign'), $needgradingcell],
            [get_string('numberofnotsubmitted', 'streamassign'), $notsubmitted],
        ];
        echo $OUTPUT->box(html_writer::table($summarytable), 'generalbox streamassign-summarybox');
    }
    echo $OUTPUT->single_button(
        new moodle_url('/mod/streamassign/grading.php', ['id' => $cm->id]),
        get_string('viewgrading', 'streamassign'),
        'get',
        ['primary' => false]
    );
}

if (trim(strip_tags($streamassign->intro))) {
    echo $OUTPUT->box(format_module_intro('streamassign', $streamassign, $cm->id), 'generalbox', 'intro');
}

if ($opens) {
    echo $OUTPUT->notification(get_string('activitynotavailableyet', 'streamassign', userdate($streamassign->timeopen)), 'notifyinfo');
}
if ($closed) {
    echo $OUTPUT->notification(get_string('activityclosed', 'streamassign', userdate($streamassign->timeclose)), 'notifyinfo');
}

if (!$streamconfigured) {
    echo $OUTPUT->notification(get_string('streamurl_required', 'streamassign'), 'notifyproblem');
}

if ($submission) {
    echo $OUTPUT->heading(get_string('yoursubmission', 'streamassign'), 3);
    $hasthumb = \mod_streamassign\stream_uploader::get_video_thumbnail_url((int) $submission->streamid) !== null;
    $embedurl = $hasthumb ? \mod_streamassign\stream_uploader::get_embed_url_with_jwt((int) $submission->streamid, $USER, 7200) : null;
    $watchurl = $streamurl ? $streamurl . '/watch/' . $submission->streamid : '';
    $submissioninfo = [
        'submittedon' => get_string('submittedon', 'streamassign') . ' ' . userdate($submission->timemodified),
        'videotitle' => get_string('videotitle', 'streamassign') . ': ' . s($submission->videotitle ?: '-'),
        'videoready' => $hasthumb,
    ];
    if ($hasthumb && $embedurl) {
        $submissioninfo['embedurl'] = $embedurl;
        $submissioninfo['embedwidth'] = 640;
        $submissioninfo['embedheight'] = 360;
        $submissioninfo['embedtitle'] = get_string('watchvideo', 'streamassign');
    }
    if (!$hasthumb) {
        $checkurl = new moodle_url('/mod/streamassign/check_thumbnail.php', ['id' => $cm->id, 'sesskey' => sesskey()]);
        $submissioninfo['checkurl'] = $checkurl->out(false);
        $submissioninfo['processingmessage'] = get_string('videoprocessing', 'streamassign');
        $submissioninfo['nextcheckseconds'] = 30;
        $submissioninfo['embedtitle'] = get_string('watchvideo', 'streamassign');
    }
    if ($watchurl) {
        $submissioninfo['watchurl'] = $watchurl;
        $submissioninfo['watchlabel'] = get_string('watchvideo', 'streamassign');
    }
    echo $OUTPUT->render_from_template('mod_streamassign/submission_info', $submissioninfo);
}

if ($cansubmit && $streamconfigured && $submissionform) {
    if (!$submission) {
        echo $OUTPUT->heading(get_string('submitvideo', 'streamassign'), 3);
    } else if (!$canedit) {
        echo $OUTPUT->notification(get_string('allowedformats', 'streamassign'), 'notifyinfo');
    }
    $submissionform->display();
}

echo $OUTPUT->footer();
