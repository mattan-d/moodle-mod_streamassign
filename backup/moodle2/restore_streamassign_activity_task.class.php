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
 * Defines the restore task for the Stream assignment activity.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/streamassign/backup/moodle2/restore_streamassign_stepslib.php');

/**
 * Stream assignment restore task that provides all the settings and steps to perform a complete restore.
 */
class restore_streamassign_activity_task extends restore_activity_task {

    /**
     * Define (add) particular steps that this activity can have.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_streamassign_activity_structure_step('streamassign_structure', 'streamassign.xml'));
    }

    /**
     * Define optional settings (none for streamassign).
     */
    protected function define_my_settings() {
    }

    /**
     * Define contents to decode (links to activity view).
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('streamassign', ['intro'], 'streamassign');
        return $contents;
    }

    /**
     * Define decoding rules for links to the activity.
     *
     * @return array
     */
    public static function define_decode_rules() {
        $rules = [];
        $rules[] = new restore_decode_rule('STREAMASSIGNVIEW', '/mod/streamassign/view.php?id=$1', 'course_module');
        return $rules;
    }
}
