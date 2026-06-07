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
 * Defines the backup steps for the Stream assignment activity.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to backup one streamassign activity.
 */
class backup_streamassign_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure of the backup for the streamassign activity.
     *
     * @return backup_nested_element Root element of the backup structure
     */
    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        $streamassign = new backup_nested_element('streamassign', ['id'], [
            'course', 'name', 'intro', 'introformat',
            'timeopen', 'timeclose', 'preventlatesubmission', 'allowresubmission', 'maxvideos', 'maxbytes', 'filetypeslist',
            'teamsubmission', 'teamsubmissiongroupingid', 'preventsubmissionnotingroup',
            'emailalertstoteachers', 'notifygraderslatesubmission', 'notifystudentdefault', 'grade',
            'timecreated', 'timemodified',
        ]);

        $overrides = new backup_nested_element('overrides');
        $override = new backup_nested_element('override', ['id'], [
            'groupid', 'userid', 'sortorder', 'timeopen', 'timeclose',
        ]);

        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element('submission', ['id'], [
            'groupid', 'userid', 'submittedby', 'streamid', 'videotitle',
            'timecreated', 'timemodified',
        ]);

        $streamassign->add_child($overrides);
        $overrides->add_child($override);
        $streamassign->add_child($submissions);
        $submissions->add_child($submission);

        $streamassign->set_source_table('streamassign', ['id' => backup::VAR_ACTIVITYID]);
        $streamassign->annotate_files('mod_streamassign', 'intro', null);

        $overrideparams = ['streamassignid' => backup::VAR_PARENTID];
        $groupinfo = $this->get_setting_value('groups');
        if (!$userinfo) {
            $overrideparams['userid'] = backup_helper::is_sqlparam(null);
        }
        if (!$groupinfo) {
            $overrideparams['groupid'] = backup_helper::is_sqlparam(0);
        }
        $override->set_source_table('streamassign_overrides', $overrideparams);
        $override->annotate_ids('user', 'userid');
        $override->annotate_ids('group', 'groupid');

        if ($userinfo) {
            $submission->set_source_table('streamassign_submission', ['streamassignid' => backup::VAR_PARENTID]);
        }

        $submission->annotate_ids('user', 'userid');
        $submission->annotate_ids('user', 'submittedby');
        $submission->annotate_ids('group', 'groupid');

        return $this->prepare_activity_structure($streamassign);
    }
}
