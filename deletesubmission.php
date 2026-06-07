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
 * Delete a student submission (teacher/admin).
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $OUTPUT;

$submissionid = required_param('id', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$submission = $DB->get_record('streamassign_submission', ['id' => $submissionid], '*', MUST_EXIST);
list($course, $cm) = get_course_and_cm_from_instance($submission->streamassignid, 'streamassign');
if ((int) $cm->id !== $cmid) {
    throw new moodle_exception('invalidcoursemodule');
}

$streamassign = $DB->get_record('streamassign', ['id' => $cm->instance], '*', MUST_EXIST);
$student = $DB->get_record('user', ['id' => $submission->userid], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/streamassign:grade', $context);

$returnurl = new moodle_url('/mod/streamassign/grading.php', ['id' => $cm->id]);
$confirmurl = new moodle_url('/mod/streamassign/deletesubmission.php', [
    'id' => $submissionid,
    'cmid' => $cmid,
    'confirm' => 1,
    'sesskey' => sesskey(),
]);

if ($confirm) {
    require_sesskey();
    if (!streamassign_delete_submission((int) $submissionid, (int) $streamassign->id)) {
        throw new moodle_exception('invalidrecord');
    }
    redirect($returnurl, get_string('submissiondeleted', 'streamassign'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$url = new moodle_url('/mod/streamassign/deletesubmission.php', ['id' => $submissionid, 'cmid' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_title(get_string('deletesubmission', 'streamassign'));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->set_attrs([
    'title' => format_string($streamassign->name, true, ['context' => $context]),
    'description' => '',
    'hidecompletion' => true,
]);

$confirmstr = get_string('deletesubmissionconfirm', 'streamassign', (object) [
    'student' => fullname($student),
    'title' => $submission->videotitle ?: get_string('watchvideo', 'streamassign'),
]);

echo $OUTPUT->header();
echo $OUTPUT->confirm($confirmstr, $confirmurl, $returnurl);
echo $OUTPUT->footer();
