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
 * Defines the restore steps for the Stream assignment activity.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one streamassign activity.
 */
class restore_streamassign_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the structure of the restore (paths and process methods).
     *
     * @return array of restore_path_element
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('streamassign', '/activity/streamassign');
        $paths[] = new restore_path_element('streamassign_override', '/activity/streamassign/overrides/override');
        $paths[] = new restore_path_element('submission', '/activity/streamassign/submissions/submission');
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the streamassign element (activity instance).
     *
     * @param array $data Parsed element data
     */
    protected function process_streamassign($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('streamassign', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process one override element.
     *
     * @param array $data Parsed element data
     */
    protected function process_streamassign_override($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $userinfo = $this->get_setting_value('userinfo');
        if (!$userinfo && !is_null($data->userid)) {
            return;
        }

        $groupinfo = $this->get_setting_value('groups');
        if (!$groupinfo && !is_null($data->groupid)) {
            return;
        }

        $data->streamassignid = $this->get_new_parentid('streamassign');

        if (!is_null($data->userid)) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }
        if (!is_null($data->groupid)) {
            $data->groupid = $this->get_mappingid('group', $data->groupid);
        }

        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);

        $newitemid = $DB->insert_record('streamassign_overrides', $data);
        $this->set_mapping('streamassign_override', $oldid, $newitemid);
    }

    /**
     * Process one submission element.
     *
     * @param array $data Parsed element data
     */
    protected function process_submission($data) {
        global $DB;

        $data = (object) $data;
        $data->streamassignid = $this->task->get_activityid();
        $data->groupid = isset($data->groupid) ? (int) $data->groupid : 0;

        if (!empty($data->groupid)) {
            $data->groupid = $this->get_mappingid('group', $data->groupid);
            if ($data->groupid === false) {
                return;
            }
            $data->userid = 0;
        } else {
            $data->userid = $this->get_mappingid('user', $data->userid);
            if ($data->userid === false) {
                return;
            }
        }

        if (!empty($data->submittedby)) {
            $data->submittedby = $this->get_mappingid('user', $data->submittedby);
            if ($data->submittedby === false) {
                $data->submittedby = 0;
            }
        } else {
            $data->submittedby = $data->userid ?: 0;
        }

        $DB->insert_record('streamassign_submission', $data);
    }

    /**
     * After execute: ensure grade item is created for the restored activity.
     */
    protected function after_execute() {
        $this->add_related_files('mod_streamassign', 'intro', null);
    }
}
