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
 * Stream assignment module upgrade code.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the mod_streamassign database.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool true on success.
 */
function xmldb_streamassign_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026032000) {
        require_once($CFG->dirroot . '/mod/streamassign/lib.php');

        $instances = $DB->get_records('streamassign');
        foreach ($instances as $instance) {
            // Rebuild gradebook item type (value/scale/none) for existing activities.
            streamassign_grade_item_update($instance);
        }

        upgrade_mod_savepoint(true, 2026032000, 'streamassign');
    }

    if ($oldversion < 2026032400) {
        $table = new xmldb_table('streamassign');

        $field = new xmldb_field('preventlatesubmission', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'timeclose');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('allowresubmission', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'preventlatesubmission');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('emailalertstoteachers', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'allowresubmission');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('streamassign');

        $field = new xmldb_field('notifygraderslatesubmission', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'emailalertstoteachers');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('notifystudentdefault', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'notifygraderslatesubmission');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026032400, 'streamassign');
    }

    return true;
}
