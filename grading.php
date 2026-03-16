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
 * Grading page: list submissions and save grades (like mod/assign grading).
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

global $DB, $USER, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('streamassign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$streamassign = $DB->get_record('streamassign', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/streamassign:grade', $context);

$tifirst = optional_param('tifirst', '', PARAM_ALPHAEXT);
$tilast = optional_param('tilast', '', PARAM_ALPHAEXT);
if ($tifirst !== '') {
    $tifirst = core_text::strtoupper(core_text::substr($tifirst, 0, 1));
}
if ($tilast !== '') {
    $tilast = core_text::strtoupper(core_text::substr($tilast, 0, 1));
}

$perpage = 30;
$page = optional_param('page', 0, PARAM_INT);
if ($page < 0) {
    $page = 0;
}

$urlparams = ['id' => $cm->id];
if ($tifirst !== '') {
    $urlparams['tifirst'] = $tifirst;
}
if ($tilast !== '') {
    $urlparams['tilast'] = $tilast;
}
if ($page > 0) {
    $urlparams['page'] = $page;
}
$PAGE->set_url('/mod/streamassign/grading.php', $urlparams);
$PAGE->set_title(format_string($streamassign->name) . ' - ' . get_string('grading', 'streamassign'));
$PAGE->set_heading(format_string($course->fullname));

$streamurl = \mod_streamassign\stream_uploader::get_stream_base_url();
$grademax = (int) $streamassign->grade;

// Process save grades and feedback.
if (data_submitted() && confirm_sesskey()) {
    $grades = optional_param_array('grade', [], PARAM_FLOAT);
    $feedbacks = optional_param_array('feedback', [], PARAM_TEXT);
    // Only update users that appear in the form (current page); keys are user ids.
    $useridsonsubmit = array_unique(array_merge(array_keys($grades), array_keys($feedbacks)));
    $allsubmissions = streamassign_get_submissions_for_grading((int) $streamassign->id, (int) $course->id);
    $updated = 0;
    foreach ($useridsonsubmit as $userid) {
        $userid = (int) $userid;
        if ($userid <= 0 || !isset($allsubmissions[$userid])) {
            continue;
        }
        $grade = null;
        if ($grademax > 0 && isset($grades[$userid])) {
            $grade = $grades[$userid] === '' || $grades[$userid] === null ? null : (float) $grades[$userid];
            if ($grade !== null) {
                // Clamp to valid range and remove digits after decimal.
                if ($grade < 0) {
                    $grade = 0;
                } else if ($grade > $grademax) {
                    $grade = $grademax;
                }
                $grade = (int) round($grade);
            }
        }
        $feedback = isset($feedbacks[$userid]) ? trim($feedbacks[$userid]) : null;
        streamassign_update_grades($streamassign, $userid, $grade, $feedback);
        $updated++;
    }
    if ($updated > 0) {
        redirect($PAGE->url, get_string('gradesupdated', 'streamassign'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$submissions = streamassign_get_submissions_for_grading((int) $streamassign->id, (int) $course->id);

// Filter by first/last initial (like mod_assign).
if ($tifirst !== '' || $tilast !== '') {
    foreach ($submissions as $userid => $row) {
        $u = $row->user;
        $firstchar = core_text::substr(trim($u->firstname ?? ''), 0, 1);
        $lastchar = core_text::substr(trim($u->lastname ?? ''), 0, 1);
        if ($tifirst !== '' && core_text::strtoupper($firstchar) !== $tifirst) {
            unset($submissions[$userid]);
            continue;
        }
        if ($tilast !== '' && core_text::strtoupper($lastchar) !== $tilast) {
            unset($submissions[$userid]);
        }
    }
}

$totalcount = count($submissions);
$baseurl = new moodle_url('/mod/streamassign/grading.php', ['id' => $cm->id]);
if ($tifirst !== '') {
    $baseurl->param('tifirst', $tifirst);
}
if ($tilast !== '') {
    $baseurl->param('tilast', $tilast);
}

$maxpage = ($perpage > 0 && $totalcount > 0) ? max(0, (int) ceil($totalcount / $perpage) - 1) : 0;
if ($page > $maxpage) {
    $page = $maxpage;
    if ($page > 0) {
        $urlparams['page'] = $page;
        $PAGE->set_url('/mod/streamassign/grading.php', $urlparams);
    }
}

// Pagination: show only current page.
if ($totalcount > $perpage) {
    $submissions = array_slice($submissions, $page * $perpage, $perpage, true);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('grading', 'streamassign'), 2);
echo html_writer::start_tag('div', ['class' => 'streamassign-grading-back-wrap mt-3 mb-4']);
echo $OUTPUT->single_button(
    new moodle_url('/mod/streamassign/view.php', ['id' => $cm->id]),
    get_string('backtoactivity', 'streamassign'),
    'get'
);
echo html_writer::end_tag('div');

// Initials bar: filter by first letter of firstname / lastname (like Moodle core assign).
$urlfirst = clone $baseurl;
if ($tilast !== '') {
    $urlfirst->param('tilast', $tilast);
}
$urllast = clone $baseurl;
if ($tifirst !== '') {
    $urllast->param('tifirst', $tifirst);
}
echo html_writer::start_tag('div', ['class' => 'streamassign-initials mb-3']);
echo $OUTPUT->initials_bar($tifirst ?: 'all', 'firstinitial', get_string('firstname'), 'tifirst', $urlfirst);
echo $OUTPUT->initials_bar($tilast ?: 'all', 'lastinitial', get_string('lastname'), 'tilast', $urllast);
if ($tifirst !== '' || $tilast !== '') {
    echo ' ' . html_writer::link($baseurl, get_string('clearfilter', 'streamassign'), ['class' => 'btn btn-secondary btn-sm']);
}
echo html_writer::end_tag('div');

if ($totalcount > $perpage) {
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $baseurl, 'page');
}

if (empty($submissions)) {
    echo $OUTPUT->notification(get_string('nosubmissionsgrading', 'streamassign'), 'notifyinfo');
    echo $OUTPUT->footer();
    exit;
}

$showgrades = ($grademax > 0);
$showform = true;
if ($showform) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'id' => 'streamassign-grading-form']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
}

$table = new html_table();
$table->head = [
    get_string('thumbnail', 'streamassign'),
    get_string('fullname'),
    get_string('submittedon', 'streamassign'),
    get_string('videotitle', 'streamassign'),
    get_string('watchvideo', 'streamassign'),
];
if ($showgrades) {
    $table->head[] = get_string('grade') . ' / ' . $grademax;
    $table->head[] = get_string('feedback', 'streamassign');
}
$table->attributes['class'] = 'generaltable streamassign-grading-table';

foreach ($submissions as $row) {
    $user = $row->user;
    $sub = $row->submission;
    $thumburl = \mod_streamassign\stream_uploader::get_video_thumbnail_url((int) $sub->streamid);
    $thumbcell = '';
    if ($thumburl) {
        $thumbcell = html_writer::empty_tag('img', [
            'src' => $thumburl,
            'alt' => s($sub->videotitle ?: get_string('watchvideo', 'streamassign')),
            'class' => 'streamassign-video-thumb',
            'width' => 160,
            'height' => 90,
        ]);
    } else {
        $thumbcell = html_writer::span(get_string('nothumbnail', 'streamassign'), 'streamassign-no-thumb');
    }
    $watchlink = '';
    if ($streamurl) {
        $embedurl = \mod_streamassign\stream_uploader::get_embed_url_with_jwt((int) $sub->streamid, $user, 7200);
        if ($embedurl) {
            $watchlink = html_writer::link($embedurl, get_string('watchvideo', 'streamassign'), ['target' => '_blank', 'rel' => 'noopener']);
        } else {
            $watchlink = html_writer::link($streamurl . '/watch/' . $sub->streamid, get_string('watchvideo', 'streamassign'), ['target' => '_blank', 'rel' => 'noopener']);
        }
    }
    $tablerow = [
        $thumbcell,
        $OUTPUT->user_picture($user, ['size' => 35, 'courseid' => $course->id]) . ' ' . fullname($user),
        userdate($sub->timemodified),
        s($sub->videotitle ?: '-'),
        $watchlink,
    ];
    if ($showgrades) {
        $tablerow[] = html_writer::empty_tag('input', [
            'type' => 'number',
            'name' => 'grade[' . $user->id . ']',
            'value' => $row->currentgrade !== null ? (string) (int) round($row->currentgrade) : '',
            'min' => 0,
            'max' => $grademax,
            'step' => 1,
            'size' => 5,
        ]);
        $tablerow[] = html_writer::tag('textarea', s($row->currentfeedback), [
            'name' => 'feedback[' . $user->id . ']',
            'rows' => 2,
            'cols' => 40,
            'class' => 'streamassign-feedback-input',
        ]);
    }
    $table->data[] = $tablerow;
}

echo html_writer::table($table);

if ($showform) {
    echo html_writer::tag('div', html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('savegrades', 'streamassign'),
        'class' => 'btn btn-primary',
    ]), ['class' => 'streamassign-grading-submit']);
    echo html_writer::end_tag('form');
}

if ($totalcount > $perpage) {
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $baseurl, 'page');
}

echo $OUTPUT->footer();
