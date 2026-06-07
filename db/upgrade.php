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

    if ($oldversion < 2026052800) {
        $table = new xmldb_table('streamassign');

        $field = new xmldb_field('maxvideos', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'allowresubmission');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $subtable = new xmldb_table('streamassign_submission');

        $oldunique = new xmldb_index('streamassign_user', XMLDB_INDEX_UNIQUE, ['streamassignid', 'userid']);
        if ($dbman->index_exists($subtable, $oldunique)) {
            $dbman->drop_index($subtable, $oldunique);
        }

        $newindex = new xmldb_index('streamassign_user', XMLDB_INDEX_NOTUNIQUE, ['streamassignid', 'userid']);
        if (!$dbman->index_exists($subtable, $newindex)) {
            $dbman->add_index($subtable, $newindex);
        }

        $uniqueindex = new xmldb_index('streamassign_user_stream', XMLDB_INDEX_UNIQUE, ['streamassignid', 'userid', 'streamid']);
        if (!$dbman->index_exists($subtable, $uniqueindex)) {
            $dbman->add_index($subtable, $uniqueindex);
        }

        upgrade_mod_savepoint(true, 2026052800, 'streamassign');
    }

    if ($oldversion < 2026052810) {
        $table = new xmldb_table('streamassign');

        $field = new xmldb_field('maxbytes', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '2147483648', 'maxvideos');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026052810, 'streamassign');
    }

    if ($oldversion < 2026052812) {
        $table = new xmldb_table('streamassign');

        $field = new xmldb_field('filetypeslist', XMLDB_TYPE_TEXT, null, null, null, null, null, 'maxbytes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026052812, 'streamassign');
    }

    if ($oldversion < 2026052813) {
        $table = new xmldb_table('streamassign_overrides');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('streamassignid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timeopen', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timeclose', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('streamassignid', XMLDB_KEY_FOREIGN, ['streamassignid'], 'streamassign', ['id']);
        $table->add_key('groupid', XMLDB_KEY_FOREIGN, ['groupid'], 'groups', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026052813, 'streamassign');
    }

    if ($oldversion < 2026052815) {
        $table = new xmldb_table('streamassign');

        $field = new xmldb_field('teamsubmission', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'filetypeslist');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('teamsubmissiongroupingid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'teamsubmission');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('preventsubmissionnotingroup', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'teamsubmissiongroupingid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $subtable = new xmldb_table('streamassign_submission');

        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'streamassignid');
        if (!$dbman->field_exists($subtable, $field)) {
            $dbman->add_field($subtable, $field);
        }

        $field = new xmldb_field('submittedby', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'userid');
        if (!$dbman->field_exists($subtable, $field)) {
            $dbman->add_field($subtable, $field);
        }

        // Backfill submittedby from userid for existing rows.
        $DB->execute("UPDATE {streamassign_submission} SET submittedby = userid WHERE submittedby = 0 AND userid > 0");

        $oldunique = new xmldb_index('streamassign_user_stream', XMLDB_INDEX_UNIQUE, ['streamassignid', 'userid', 'streamid']);
        if ($dbman->index_exists($subtable, $oldunique)) {
            $dbman->drop_index($subtable, $oldunique);
        }

        $groupindex = new xmldb_index('streamassign_group', XMLDB_INDEX_NOTUNIQUE, ['streamassignid', 'groupid']);
        if (!$dbman->index_exists($subtable, $groupindex)) {
            $dbman->add_index($subtable, $groupindex);
        }

        $newunique = new xmldb_index('streamassign_owner_stream', XMLDB_INDEX_UNIQUE, ['streamassignid', 'groupid', 'userid', 'streamid']);
        if (!$dbman->index_exists($subtable, $newunique)) {
            $dbman->add_index($subtable, $newunique);
        }

        upgrade_mod_savepoint(true, 2026052815, 'streamassign');
    }

    if ($oldversion < 2026052900) {
        upgrade_mod_savepoint(true, 2026052900, 'streamassign');
    }

    if ($oldversion < 2026060701) {
        require_once($CFG->dirroot . '/mod/streamassign/lib.php');

        $sitefiletypes = get_config('mod_streamassign', 'filetypes');
        if ($sitefiletypes !== false && $sitefiletypes !== '') {
            set_config('filetypes', streamassign_sanitize_filetypes_string((string) $sitefiletypes), 'mod_streamassign');
        }

        $instances = $DB->get_records('streamassign', null, '', 'id, filetypeslist');
        foreach ($instances as $instance) {
            if (empty($instance->filetypeslist)) {
                continue;
            }
            $clean = streamassign_sanitize_filetypes_string((string) $instance->filetypeslist);
            if ($clean !== $instance->filetypeslist) {
                $DB->set_field('streamassign', 'filetypeslist', $clean, ['id' => $instance->id]);
            }
        }

        upgrade_mod_savepoint(true, 2026060701, 'streamassign');
    }

    return true;
}
