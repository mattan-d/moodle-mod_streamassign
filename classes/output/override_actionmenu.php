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

namespace mod_streamassign\output;

use core_availability\info_module;
use moodle_url;
use renderable;
use single_button;
use templatable;
use url_select;

/**
 * Override action bar (user/group tabs + add button).
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class override_actionmenu implements templatable, renderable {

    /** @var moodle_url */
    protected $currenturl;

    /** @var \cm_info */
    protected $cm;

    /** @var bool */
    protected $canaccessallgroups;

    /** @var array */
    protected $groups;

    /**
     * @param moodle_url $currenturl
     * @param \cm_info $cm
     */
    public function __construct(moodle_url $currenturl, \cm_info $cm) {
        $this->currenturl = $currenturl;
        $this->cm = $cm;
        $groupmode = groups_get_activity_groupmode($this->cm);
        $this->canaccessallgroups = ($groupmode === NOGROUPS) ||
            has_capability('moodle/site:accessallgroups', $this->cm->context);
        $this->groups = $this->canaccessallgroups ? groups_get_all_groups($this->cm->course) :
            groups_get_activity_allowed_groups($this->cm);
    }

    /**
     * @return url_select
     */
    protected function get_select_menu(): url_select {
        $userlink = new moodle_url('/mod/streamassign/overrides.php', ['cmid' => $this->cm->id, 'mode' => 'user']);
        $grouplink = new moodle_url('/mod/streamassign/overrides.php', ['cmid' => $this->cm->id, 'mode' => 'group']);
        $menu = [
            $userlink->out(false) => get_string('useroverrides', 'mod_assign'),
            $grouplink->out(false) => get_string('groupoverrides', 'mod_assign'),
        ];
        return new url_select($menu, $this->currenturl->out(false), null, 'mod_streamassign_override_select');
    }

    /**
     * @return bool
     */
    protected function show_groups(): bool {
        return !empty($this->groups);
    }

    /**
     * @return bool
     */
    protected function show_useroverride(): bool {
        global $DB;
        $context = $this->cm->context;
        if ($this->canaccessallgroups) {
            $users = get_enrolled_users($context, '', 0, 'u.id');
        } else if ($this->groups) {
            $enrolledjoin = get_enrolled_join($context, 'u.id');
            list($ingroupsql, $ingroupparams) = $DB->get_in_or_equal(array_keys($this->groups), SQL_PARAMS_NAMED);
            $params = $enrolledjoin->params + $ingroupparams;
            $sql = "SELECT u.id
                      FROM {user} u
                      JOIN {groups_members} gm ON gm.userid = u.id
                           {$enrolledjoin->joins}
                     WHERE gm.groupid $ingroupsql
                       AND {$enrolledjoin->wheres}";
            $users = $DB->get_records_sql($sql, $params);
        } else {
            $users = [];
        }
        $info = new info_module($this->cm);
        $users = $info->filter_user_list($users);
        return !empty($users);
    }

    /**
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $type = $this->currenturl->get_param('mode');
        if ($type === 'user') {
            $text = get_string('addnewuseroverride', 'mod_assign');
        } else {
            $text = get_string('addnewgroupoverride', 'mod_assign');
        }
        $action = ($type === 'user') ? 'adduser' : 'addgroup';
        $url = new moodle_url('/mod/streamassign/overrideedit.php', [
            'cmid' => $this->currenturl->get_param('cmid'),
            'action' => $action,
        ]);
        $options = [];
        if ($action === 'addgroup' && !$this->show_groups()) {
            $options = ['disabled' => 'true'];
        } else if ($action === 'adduser' && !$this->show_useroverride()) {
            $options = ['disabled' => 'true'];
        }
        $overridebutton = new single_button($url, $text, 'post', single_button::BUTTON_PRIMARY, $options);
        return [
            'addoverride' => $overridebutton->export_for_template($output),
            'urlselect' => $this->get_select_menu()->export_for_template($output),
        ];
    }
}
