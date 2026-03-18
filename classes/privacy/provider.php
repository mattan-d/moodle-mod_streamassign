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

namespace mod_streamassign\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for mod_streamassign.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns metadata about this plugin's storage of user data.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('streamassign_submission', [
            'userid' => 'privacy:metadata:streamassign_submission:userid',
            'streamid' => 'privacy:metadata:streamassign_submission:streamid',
            'videotitle' => 'privacy:metadata:streamassign_submission:videotitle',
            'timecreated' => 'privacy:metadata:streamassign_submission:timecreated',
            'timemodified' => 'privacy:metadata:streamassign_submission:timemodified',
        ], 'privacy:metadata:streamassign_submission');

        $collection->add_subsystem_link('core_grades', [], 'privacy:metadata:core_grades');

        $collection->add_external_location_link('streamplatform', [
            'userid' => 'privacy:metadata:streamplatform:userid',
            'videotitle' => 'privacy:metadata:streamplatform:videotitle',
        ], 'privacy:metadata:streamplatform');

        $collection->add_external_location_link('streamplatform_reachability', [], 'privacy:metadata:streamplatform_reachability');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT c.id
                  FROM {context} c
                  INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  INNER JOIN {streamassign} sa ON sa.id = cm.instance
                  INNER JOIN {streamassign_submission} ss ON ss.streamassignid = sa.id AND ss.userid = :userid
                 WHERE 1 = 1";
        $params = [
            'modname' => 'streamassign',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('streamassign', $context->instanceid);
        if (!$cm) {
            return;
        }
        $sql = "SELECT ss.userid
                  FROM {streamassign_submission} ss
                 WHERE ss.streamassignid = :streamassignid";
        $params = ['streamassignid' => $cm->instance];
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        $userid = $user->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('streamassign', $context->instanceid);
            if (!$cm || $cm->modname !== 'streamassign') {
                continue;
            }
            $streamassign = $DB->get_record('streamassign', ['id' => $cm->instance]);
            if (!$streamassign) {
                continue;
            }

            $submissions = $DB->get_records('streamassign_submission', [
                'streamassignid' => $streamassign->id,
                'userid' => $userid,
            ], 'timemodified DESC');

            if (empty($submissions)) {
                continue;
            }

            foreach ($submissions as $sub) {
                $subcontext = [
                    get_string('submission', 'streamassign'),
                    $sub->id,
                ];
                $data = (object) [
                    'streamid' => $sub->streamid,
                    'videotitle' => $sub->videotitle,
                    'timecreated' => userdate($sub->timecreated),
                    'timemodified' => userdate($sub->timemodified),
                ];
                writer::with_context($context)->export_data($subcontext, $data);
            }

            // Grades and feedback are stored in core_grades; they are exported by the grade subsystem.
        }
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('streamassign', $context->instanceid);
        if (!$cm || $cm->modname !== 'streamassign') {
            return;
        }
        $streamassign = $DB->get_record('streamassign', ['id' => $cm->instance]);
        if (!$streamassign) {
            return;
        }

        $DB->delete_records('streamassign_submission', ['streamassignid' => $streamassign->id]);

        // Grades for this activity are handled by core_grades when the context is deleted/expired.
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if ($contextlist->count() === 0) {
            return;
        }
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('streamassign', $context->instanceid);
            if (!$cm || $cm->modname !== 'streamassign') {
                continue;
            }
            $streamassign = $DB->get_record('streamassign', ['id' => $cm->instance]);
            if (!$streamassign) {
                continue;
            }

            $DB->delete_records('streamassign_submission', [
                'streamassignid' => $streamassign->id,
                'userid' => $userid,
            ]);

            // Clear this user's grade and feedback for this activity.
            require_once($GLOBALS['CFG']->dirroot . '/mod/streamassign/lib.php');
            streamassign_update_grades($streamassign, $userid, null, null);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('streamassign', $context->instanceid);
        if (!$cm || $cm->modname !== 'streamassign') {
            return;
        }
        $streamassign = $DB->get_record('streamassign', ['id' => $cm->instance]);
        if (!$streamassign) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['streamassignid' => $streamassign->id], $userparams);
        $DB->delete_records_select(
            'streamassign_submission',
            "streamassignid = :streamassignid AND userid $usersql",
            $params
        );

        require_once($GLOBALS['CFG']->dirroot . '/mod/streamassign/lib.php');
        foreach ($userlist->get_userids() as $userid) {
            streamassign_update_grades($streamassign, $userid, null, null);
        }
    }
}
