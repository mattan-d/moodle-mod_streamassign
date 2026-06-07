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
        global $CFG, $COURSE;
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
        $maxoptions = streamassign_get_maxvideos_form_options();
        $mform->addElement('select', 'maxvideos', get_string('maxvideos', 'streamassign'), $maxoptions);
        $mform->setDefault('maxvideos', 1);
        $mform->addHelpButton('maxvideos', 'maxvideos', 'streamassign');
        $defaultmaxbytes = (int) get_config('mod_streamassign', 'maxbytes');
        if ($defaultmaxbytes === 0) {
            $defaultmaxbytes = 2147483648;
        }
        $sizechoices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, get_config('mod_streamassign', 'maxbytes'));
        $mform->addElement('select', 'maxbytes', get_string('maximumsubmissionsize', 'streamassign'), $sizechoices);
        $mform->addHelpButton('maxbytes', 'maximumsubmissionsize', 'streamassign');
        $mform->setDefault('maxbytes', $defaultmaxbytes);
        $mform->addElement('filetypes', 'filetypeslist', get_string('allowedfiletypes', 'streamassign'), [
            'onlytypes' => streamassign_get_selectable_filetypes(),
            'allowall' => false,
        ]);
        $mform->addHelpButton('filetypeslist', 'allowedfiletypes', 'streamassign');
        $mform->setDefault('filetypeslist', streamassign_get_default_filetypes());

        $mform->addElement('header', 'groupsubmissionsettings', get_string('groupsubmissionsettings', 'assign'));
        $mform->addElement('selectyesno', 'teamsubmission', get_string('teamsubmission', 'assign'));
        $mform->addHelpButton('teamsubmission', 'teamsubmission', 'assign');
        $mform->addElement('selectyesno', 'preventsubmissionnotingroup', get_string('preventsubmissionnotingroup', 'assign'));
        $mform->addHelpButton('preventsubmissionnotingroup', 'preventsubmissionnotingroup', 'assign');
        $mform->hideIf('preventsubmissionnotingroup', 'teamsubmission', 'eq', 0);

        $groupings = groups_get_all_groupings($COURSE->id);
        $groupingoptions = [0 => get_string('none')];
        foreach ($groupings as $grouping) {
            $groupingoptions[$grouping->id] = $grouping->name;
        }
        $mform->addElement('select', 'teamsubmissiongroupingid', get_string('teamsubmissiongroupingid', 'assign'), $groupingoptions);
        $mform->addHelpButton('teamsubmissiongroupingid', 'teamsubmissiongroupingid', 'assign');
        $mform->hideIf('teamsubmissiongroupingid', 'teamsubmission', 'eq', 0);

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
        if (empty($defaultvalues['maxvideos'])) {
            $defaultvalues['maxvideos'] = 1;
        }
        if (empty($defaultvalues['maxbytes'])) {
            $defaultmaxbytes = (int) get_config('mod_streamassign', 'maxbytes');
            $defaultvalues['maxbytes'] = $defaultmaxbytes > 0 ? $defaultmaxbytes : 2147483648;
        }
        if (!isset($defaultvalues['filetypeslist']) || $defaultvalues['filetypeslist'] === '') {
            $defaultvalues['filetypeslist'] = streamassign_get_default_filetypes();
        }
        if (!empty($defaultvalues['timeopen'])) {
            $defaultvalues['timeopen'] = $defaultvalues['timeopen'];
        }
        if (!empty($defaultvalues['timeclose'])) {
            $defaultvalues['timeclose'] = $defaultvalues['timeclose'];
        }
    }

    /**
     * Validate form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty(trim($data['filetypeslist'] ?? ''))) {
            $errors['filetypeslist'] = get_string('required');
        }
        return $errors;
    }
}
