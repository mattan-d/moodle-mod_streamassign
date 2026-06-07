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
 * Override form for mod_streamassign.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for editing streamassign overrides.
 */
class streamassign_override_form extends moodleform {

    /** @var object */
    protected $cm;

    /** @var object */
    protected $streamassign;

    /** @var context */
    protected $context;

    /** @var bool */
    protected $groupmode;

    /** @var int */
    protected $groupid;

    /** @var int */
    protected $userid;

    /** @var int|null */
    protected $sortorder;

    /**
     * @param moodle_url $submiturl
     * @param object $cm
     * @param object $streamassign
     * @param context $context
     * @param bool $groupmode
     * @param object|null $override
     */
    public function __construct($submiturl, $cm, $streamassign, $context, $groupmode, $override = null) {
        $this->cm = $cm;
        $this->streamassign = $streamassign;
        $this->context = $context;
        $this->groupmode = $groupmode;
        $this->groupid = ($override && !empty($override->groupid)) ? (int) $override->groupid : 0;
        $this->userid = ($override && !empty($override->userid)) ? (int) $override->userid : 0;
        $this->sortorder = ($override && !empty($override->sortorder)) ? (int) $override->sortorder : null;
        parent::__construct($submiturl, null, 'post');
    }

    /**
     * Form definition.
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;
        $assigngroupmode = groups_get_activity_groupmode($this->cm);
        $accessallgroups = ($assigngroupmode == NOGROUPS) ||
            has_capability('moodle/site:accessallgroups', $this->context);

        if ($this->groupmode) {
            $mform->addElement('header', 'override', get_string('groupoverrides', 'mod_assign'));
            if ($this->groupid) {
                $groupchoices = [
                    $this->groupid => format_string(groups_get_group_name($this->groupid), true, ['context' => $this->context]),
                ];
                $mform->addElement('select', 'groupid', get_string('overridegroup', 'mod_assign'), $groupchoices);
                $mform->freeze('groupid');
                $mform->addElement('hidden', 'sortorder', $this->sortorder);
                $mform->setType('sortorder', PARAM_INT);
                $mform->freeze('sortorder');
            } else {
                $groups = $accessallgroups ? groups_get_all_groups($this->cm->course) :
                    groups_get_activity_allowed_groups($this->cm);
                if (empty($groups)) {
                    throw new moodle_exception('groupsnone', 'mod_assign',
                        new moodle_url('/mod/streamassign/overrides.php', ['cmid' => $this->cm->id]));
                }
                $groupchoices = [];
                foreach ($groups as $group) {
                    if ($group->visibility != GROUPS_VISIBILITY_NONE) {
                        $groupchoices[$group->id] = format_string($group->name, true, ['context' => $this->context]);
                    }
                }
                if (count($groupchoices) === 0) {
                    $groupchoices[0] = get_string('none');
                }
                $mform->addElement('select', 'groupid', get_string('overridegroup', 'mod_assign'), $groupchoices);
                $mform->addRule('groupid', get_string('required'), 'required', null, 'client');
            }
        } else {
            $mform->addElement('header', 'override', get_string('useroverrides', 'mod_assign'));
            if ($this->userid) {
                $user = $DB->get_record('user', ['id' => $this->userid], '*', MUST_EXIST);
                $userchoices = [$this->userid => fullname($user)];
                $mform->addElement('select', 'userid', get_string('overrideuser', 'mod_assign'), $userchoices);
                $mform->freeze('userid');
            } else {
                list($sort) = users_order_by_sql('u');
                $userfieldsapi = \core_user\fields::for_name();
                $userfields = 'u.id, u.email, ' . $userfieldsapi->get_sql('u', false, '', '', false)->selects;
                $groupids = 0;
                if (!$accessallgroups) {
                    $groups = groups_get_activity_allowed_groups($this->cm);
                    $groupids = array_keys($groups);
                }
                $users = get_enrolled_users($this->context, '', $groupids, $userfields, $sort);
                $info = new \core_availability\info_module($this->cm);
                $users = $info->filter_user_list($users);
                if (empty($users)) {
                    throw new moodle_exception('usersnone', 'mod_assign',
                        new moodle_url('/mod/streamassign/overrides.php', ['cmid' => $this->cm->id, 'mode' => 'user']));
                }
                $canviewemail = in_array('email', \core_user\fields::get_identity_fields($this->context, false));
                $userchoices = [];
                foreach ($users as $id => $user) {
                    $userchoices[$id] = $canviewemail ? fullname($user) . ', ' . $user->email : fullname($user);
                }
                $mform->addElement('searchableselector', 'userid', get_string('overrideuser', 'mod_assign'), $userchoices);
                $mform->addRule('userid', get_string('required'), 'required', null, 'client');
            }
        }

        $mform->addElement('date_time_selector', 'timeopen',
            get_string('allowsubmissionsfromdate', 'assign'), ['optional' => true]);
        $mform->setDefault('timeopen', $this->streamassign->timeopen);

        $mform->addElement('date_time_selector', 'timeclose',
            get_string('duedate', 'assign'), ['optional' => true]);
        $mform->setDefault('timeclose', $this->streamassign->timeclose);

        $mform->addElement('submit', 'resetbutton', get_string('reverttodefaults', 'mod_assign'));

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('save', 'mod_assign'));
        $buttonarray[] = $mform->createElement('submit', 'againbutton', get_string('saveoverrideandstay', 'mod_assign'));
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonbar', '', [' '], false);
        $mform->closeHeaderBefore('buttonbar');
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $mform =& $this->_form;

        if ($mform->elementExists('userid') && empty($data['userid'])) {
            $errors['userid'] = get_string('required');
        }
        if ($mform->elementExists('groupid') && empty($data['groupid'])) {
            $errors['groupid'] = get_string('required');
        }
        if (!empty($data['timeopen']) && !empty($data['timeclose']) && $data['timeclose'] <= $data['timeopen']) {
            $errors['timeclose'] = get_string('duedateaftersubmissionvalidation', 'mod_assign');
        }

        $changed = false;
        foreach (['timeopen', 'timeclose'] as $key) {
            if (isset($data[$key]) && (int) $data[$key] != (int) $this->streamassign->{$key}) {
                $changed = true;
                break;
            }
        }
        if (!$changed) {
            $errors['timeopen'] = get_string('nooverridedata', 'mod_assign');
        }

        return $errors;
    }
}
