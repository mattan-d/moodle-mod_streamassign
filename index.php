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
 * List all Stream assignment instances in a course.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $OUTPUT;

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$strplural = get_string('modulenameplural', 'streamassign');
$PAGE->set_url('/mod/streamassign/index.php', ['id' => $course->id]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title($course->shortname . ': ' . $strplural);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($strplural);

if (!$instances = get_all_instances_in_course('streamassign', $course)) {
    notice(get_string('nostreamassignments', 'streamassign'), new moodle_url('/course/view.php', ['id' => $course->id]));
    echo $OUTPUT->footer();
    exit;
}

$usesections = course_format_uses_sections($course->format);
$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsection = get_string('sectionname', 'format_' . $course->format);
    $table->head = [$strsection, get_string('name')];
    $table->align = ['center', 'left'];
} else {
    $table->head = [get_string('name')];
    $table->align = ['left'];
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($instances as $instance) {
    $cm = $modinfo->get_cm($instance->coursemodule);
    $row = [];
    if ($usesections) {
        if ($cm->sectionnum !== $currentsection) {
            if ($cm->sectionnum) {
                $row[] = get_section_name($course, $cm->sectionnum);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $cm->sectionnum;
        }
    }
    $class = $cm->visible ? null : ['class' => 'dimmed'];
    $row[] = html_writer::link(new moodle_url('/mod/streamassign/view.php', ['id' => $cm->id]),
        $cm->get_formatted_name(), $class);
    $table->data[] = $row;
}

echo html_writer::table($table);
echo $OUTPUT->footer();
