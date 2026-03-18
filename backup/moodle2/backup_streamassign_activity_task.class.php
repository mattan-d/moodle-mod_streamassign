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
 * Defines the backup task for the Stream assignment activity.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/streamassign/backup/moodle2/backup_streamassign_stepslib.php');

/**
 * Stream assignment backup task that provides all the settings and steps to perform a complete backup.
 */
class backup_streamassign_activity_task extends backup_activity_task {

    /**
     * Define (add) particular steps that this activity can have.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_streamassign_activity_structure_step('streamassign_structure', 'streamassign.xml'));
    }

    /**
     * Define optional settings (none for streamassign).
     */
    protected function define_my_settings() {
    }

    /**
     * Encode content links to the activity view page.
     *
     * @param string $content Content that may contain URLs
     * @return string Encoded content
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot . '/mod/streamassign/', '/');

        $pattern = '/' . $base . 'view\.php\?id=(\d+)/';
        $replacement = '$@STREAMASSIGNVIEW*$1@$';
        $content = preg_replace($pattern, $replacement, $content);

        return $content;
    }
}
