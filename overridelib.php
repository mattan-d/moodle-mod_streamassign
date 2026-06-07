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
 * Override helpers for mod_streamassign.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Resolve override values for a user (group override + user override).
 *
 * @param int $streamassignid
 * @param int $courseid
 * @param int $userid
 * @return stdClass Object with timeopen/timeclose (null = inherit activity default).
 */
function streamassign_override_exists(int $streamassignid, int $courseid, int $userid): stdClass {
    global $DB;

    $getuseroverride = function(int $uid) use ($DB, $streamassignid): array {
        $useroverride = $DB->get_record('streamassign_overrides', [
            'streamassignid' => $streamassignid,
            'userid' => $uid,
        ]);
        return $useroverride ? get_object_vars($useroverride) : [];
    };

    $getgroupoverride = function(int $uid) use ($DB, $streamassignid, $courseid): array {
        $groupings = groups_get_user_groups($courseid, $uid);
        if (empty($groupings[0])) {
            return [];
        }
        list($extra, $params) = $DB->get_in_or_equal(array_values($groupings[0]), SQL_PARAMS_NAMED);
        $params['streamassignid'] = $streamassignid;
        $sql = "SELECT * FROM {streamassign_overrides}
                WHERE groupid $extra AND streamassignid = :streamassignid AND userid IS NULL
                ORDER BY sortorder ASC";
        $groupoverride = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
        return $groupoverride ? get_object_vars($groupoverride) : [];
    };

    return (object) array_merge(
        ['timeopen' => null, 'timeclose' => null],
        $getgroupoverride($userid),
        $getuseroverride($userid)
    );
}

/**
 * Apply user/group overrides to activity settings.
 *
 * @param stdClass $streamassign Activity record from DB.
 * @param int $userid
 * @return stdClass Clone of activity with effective timeopen/timeclose.
 */
function streamassign_get_effective_settings(stdClass $streamassign, int $userid): stdClass {
    $effective = clone $streamassign;
    $override = streamassign_override_exists((int) $streamassign->id, (int) $streamassign->course, $userid);
    foreach (['timeopen', 'timeclose'] as $key) {
        if ($override->{$key} !== null) {
            $effective->{$key} = $override->{$key};
        }
    }
    return $effective;
}

/**
 * Delete an override record.
 *
 * @param int $overrideid
 * @param int $streamassignid
 * @return bool
 */
function streamassign_delete_override(int $overrideid, int $streamassignid): bool {
    global $DB;
    return $DB->delete_records('streamassign_overrides', [
        'id' => $overrideid,
        'streamassignid' => $streamassignid,
    ]);
}

/**
 * Delete all overrides for an activity.
 *
 * @param int $streamassignid
 * @return void
 */
function streamassign_delete_all_overrides(int $streamassignid): void {
    global $DB;
    $DB->delete_records('streamassign_overrides', ['streamassignid' => $streamassignid]);
}

/**
 * Move a group override up or down in priority.
 *
 * @param int $id Override id.
 * @param string $move 'up' or 'down'.
 * @param int $streamassignid Activity id.
 * @return bool
 */
function streamassign_move_group_override(int $id, string $move, int $streamassignid): bool {
    global $DB;

    if (!$override = $DB->get_record('streamassign_overrides',
            ['id' => $id, 'streamassignid' => $streamassignid], 'id, sortorder, groupid')) {
        return false;
    }

    $overridecountgroup = $DB->count_records('streamassign_overrides', [
        'userid' => null,
        'streamassignid' => $streamassignid,
    ]);

    if ($move === 'up' && $override->sortorder > 1) {
        $neworder = $override->sortorder - 1;
    } else if ($move === 'down' && $override->sortorder < $overridecountgroup) {
        $neworder = $override->sortorder + 1;
    } else {
        return false;
    }

    $params = ['sortorder' => $neworder, 'streamassignid' => $streamassignid];
    if ($swapoverride = $DB->get_record('streamassign_overrides', $params, 'id, sortorder, groupid')) {
        $swapoverride->sortorder = $override->sortorder;
        $override->sortorder = $neworder;
        $DB->update_record('streamassign_overrides', $override);
        $DB->update_record('streamassign_overrides', $swapoverride);
    }

    streamassign_reorder_group_overrides($streamassignid);
    return true;
}

/**
 * Reorder group overrides sequentially from 1.
 *
 * @param int $streamassignid
 * @return void
 */
function streamassign_reorder_group_overrides(int $streamassignid): void {
    global $DB;

    $i = 1;
    $overrides = $DB->get_records('streamassign_overrides',
        ['userid' => null, 'streamassignid' => $streamassignid], 'sortorder ASC');
    foreach ($overrides as $override) {
        if ((int) $override->sortorder !== $i) {
            $DB->set_field('streamassign_overrides', 'sortorder', $i, ['id' => $override->id]);
        }
        $i++;
    }
}
