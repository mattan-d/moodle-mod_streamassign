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
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/overridelib.php');

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

$streamassign = streamassign_get_effective_settings($streamassign, (int) $USER->id);

$PAGE->set_url('/mod/streamassign/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($streamassign->name));
$PAGE->set_heading(format_string($course->fullname));

$timenow = time();
$canedit = has_capability('mod/streamassign:addinstance', $context);
$opens = $streamassign->timeopen > 0 && $timenow < $streamassign->timeopen;
$closed = !empty($streamassign->preventlatesubmission) && $streamassign->timeclose > 0 && $timenow > $streamassign->timeclose;
$cansubmit = has_capability('mod/streamassign:submit', $context) && !$opens && !$closed;
$usersubmissions = streamassign_get_user_submissions((int) $streamassign->id, (int) $USER->id, $streamassign, (int) $course->id);
$submissiongroup = streamassign_is_team_submission($streamassign)
    ? streamassign_get_submission_group($streamassign, (int) $course->id, (int) $USER->id)
    : null;
if (!streamassign_user_can_submit($streamassign, (int) $USER->id, (int) $course->id)) {
    $cansubmit = false;
}
$streamurl = \mod_streamassign\stream_uploader::get_stream_base_url();
$streamconfigured = \mod_streamassign\stream_uploader::is_configured();

// Build submission form once so validation errors are shown on same form.
$submissionform = null;
if ($cansubmit && $streamconfigured) {
    $uservideos = [];
    $userlist = \mod_streamassign\stream_uploader::get_user_videos($USER->email, 100, 0);
    if (!$userlist->error && !empty($userlist->videos)) {
        $submittedstreamids = array_map(function($s) {
            return (int) $s->streamid;
        }, $usersubmissions);
        foreach ($userlist->videos as $v) {
            $vid = (int) ($v['id'] ?? 0);
            if ($vid > 0 && !in_array($vid, $submittedstreamids, true)) {
                $uservideos[] = $v;
            }
        }
    }
    $uploadurl = (new moodle_url('/mod/streamassign/upload_video.php'))->out(false);
    $maxvideos = streamassign_get_maxvideos($streamassign);
    $submissioncount = count($usersubmissions);
    $maxbytes = streamassign_get_maxbytes($streamassign, $course);
    $customdata = (object) [
        'context' => $context,
        'cmid' => $cm->id,
        'uservideos' => $uservideos,
        'uploadurl' => $uploadurl,
        'maxvideos' => $maxvideos,
        'submissioncount' => $submissioncount,
        'maxbytes' => $maxbytes,
        'maxbytesdisplay' => display_size($maxbytes),
        'streamassign' => $streamassign,
        'allowedextensions' => streamassign_get_allowed_extensions($streamassign),
        'allowedformatsdescription' => streamassign_get_allowedformats_description($streamassign),
    ];
    $submissionform = new \mod_streamassign\submission_form($PAGE->url, $customdata);
    if (!empty($uservideos)) {
        $PAGE->requires->js_call_amd('mod_streamassign/videopicker', 'init', []);
    }
    $PAGE->requires->js_call_amd('mod_streamassign/uploader', 'init', []);
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
            $videotitle = $fromform->videotitle ?? '';
            $newuploadid = isset($fromform->new_upload_stream_id) ? (int) $fromform->new_upload_stream_id : 0;
            if ($newuploadid > 0) {
                $saveresult = streamassign_handle_existing_video($streamassign, $cm, $newuploadid, $videotitle);
                if ($saveresult->success) {
                    redirect($PAGE->url, get_string('uploadsuccess', 'streamassign'), null, \core\output\notification::NOTIFY_SUCCESS);
                } else {
                    redirect($PAGE->url, $saveresult->message ?: get_string('uploaderror', 'streamassign'), null, \core\output\notification::NOTIFY_ERROR);
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
if (streamassign_is_team_submission($streamassign) && has_capability('mod/streamassign:submit', $context)) {
    if ($submissiongroup) {
        echo $OUTPUT->notification(get_string('yoursubmissiongroup', 'streamassign', format_string($submissiongroup->name)), 'notifyinfo');
    } else if (!empty($streamassign->preventsubmissionnotingroup)) {
        echo $OUTPUT->notification(get_string('notingroup', 'assign'), 'notifywarning');
    }
}

if (!$streamconfigured) {
    echo $OUTPUT->notification(get_string('streamurl_required', 'streamassign'), 'notifyproblem');
}

if (!empty($usersubmissions)) {
    $heading = count($usersubmissions) > 1 ? get_string('yoursubmissions', 'streamassign') : get_string('yoursubmission', 'streamassign');
    if (streamassign_is_team_submission($streamassign) && $submissiongroup) {
        $heading = get_string('groupsubmissions', 'streamassign', format_string($submissiongroup->name));
    }
    echo $OUTPUT->heading($heading, 3);
    $maxvideos = streamassign_get_maxvideos($streamassign);
    if ($maxvideos > 1) {
        echo $OUTPUT->notification(get_string('submissioncount', 'streamassign', (object) [
            'count' => count($usersubmissions),
            'max' => $maxvideos,
        ]), 'notifyinfo');
    }
    foreach ($usersubmissions as $submission) {
        echo html_writer::start_tag('div', ['class' => 'streamassign-submission-item mb-4']);
        $hasthumb = \mod_streamassign\stream_uploader::get_video_thumbnail_url((int) $submission->streamid) !== null;
        $embedurl = $hasthumb ? \mod_streamassign\stream_uploader::get_embed_url_with_jwt((int) $submission->streamid, $USER, 7200) : null;
        $watchurl = $streamurl ? $streamurl . '/watch/' . $submission->streamid : '';
        $submissioninfo = [
            'submittedon' => get_string('submittedon', 'streamassign') . ' ' . userdate($submission->timemodified),
            'videotitle' => get_string('videotitle', 'streamassign') . ': ' . s($submission->videotitle ?: '-'),
            'videoready' => $hasthumb,
        ];
        if (streamassign_is_team_submission($streamassign) && !empty($submission->submittedby)) {
            $submitter = $DB->get_record('user', ['id' => $submission->submittedby], '*', IGNORE_MISSING);
            if ($submitter) {
                if ((int) $submission->submittedby === (int) $USER->id) {
                    $submissioninfo['submittedby'] = get_string('submittedbyyou', 'streamassign');
                } else {
                    $submissioninfo['submittedby'] = get_string('submittedby', 'streamassign', fullname($submitter));
                }
            }
        }
        if ($hasthumb && $embedurl) {
            $submissioninfo['embedurl'] = $embedurl;
            $submissioninfo['embedwidth'] = 640;
            $submissioninfo['embedheight'] = 360;
            $submissioninfo['embedtitle'] = get_string('watchvideo', 'streamassign');
        }
        if (!$hasthumb) {
            $checkurl = new moodle_url('/mod/streamassign/check_thumbnail.php', [
                'id' => $cm->id,
                'submissionid' => $submission->id,
                'sesskey' => sesskey(),
            ]);
            $submissioninfo['checkurl'] = $checkurl->out(false);
            $submissioninfo['processingmessage'] = get_string('videoprocessing', 'streamassign');
            $submissioninfo['nextcheckseconds'] = 30;
            $submissioninfo['embedtitle'] = get_string('watchvideo', 'streamassign');
            $submissioninfo['placeholderid'] = 'streamassign-embed-placeholder-' . (int) $submission->id;
        }
        if ($watchurl) {
            $submissioninfo['watchurl'] = $watchurl;
            $submissioninfo['watchlabel'] = get_string('watchvideo', 'streamassign');
        }
        echo $OUTPUT->render_from_template('mod_streamassign/submission_info', $submissioninfo);
        echo html_writer::end_tag('div');
    }
}

if ($cansubmit && $streamconfigured && $submissionform) {
    if (empty($usersubmissions)) {
        echo $OUTPUT->heading(get_string('submitvideo', 'streamassign'), 3);
    } else if (!$canedit) {
        echo $OUTPUT->notification(streamassign_get_allowedformats_description($streamassign), 'notifyinfo');
    }
    $submissionform->display();
}

echo $OUTPUT->footer();
