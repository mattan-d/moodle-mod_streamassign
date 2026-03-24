<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Stream assignment instance form.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_streamassign_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'timing', get_string('timing', 'form'));
        $mform->addElement('date_time_selector', 'timeopen', get_string('allowsubmissionsfromdate', 'assign'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'timeclose', get_string('duedate', 'assign'), ['optional' => true]);
        $mform->addElement('header', 'submissionsettings', get_string('submissionsettings', 'streamassign'));
        $mform->addElement('advcheckbox', 'preventlatesubmission', get_string('preventlatesubmission', 'streamassign'));
        $mform->setDefault('preventlatesubmission', 1);
        $mform->addElement('advcheckbox', 'allowresubmission', get_string('allowresubmission', 'streamassign'));
        $mform->setDefault('allowresubmission', 1);
        $mform->addElement('header', 'notificationsettings', get_string('notificationsettings', 'streamassign'));
        $mform->addElement('advcheckbox', 'emailalertstoteachers', get_string('notifygraderssubmission', 'streamassign'));
        $mform->setDefault('emailalertstoteachers', 1);
        $mform->addElement('advcheckbox', 'notifygraderslatesubmission', get_string('notifygraderslatesubmission', 'streamassign'));
        $mform->setDefault('notifygraderslatesubmission', 1);
        $mform->addElement('advcheckbox', 'notifystudentdefault', get_string('notifystudentdefault', 'streamassign'));
        $mform->setDefault('notifystudentdefault', 0);

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Set default values from existing instance.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        if (!empty($defaultvalues['timeopen'])) {
            $defaultvalues['timeopen'] = $defaultvalues['timeopen'];
        }
        if (!empty($defaultvalues['timeclose'])) {
            $defaultvalues['timeclose'] = $defaultvalues['timeclose'];
        }
    }
}
