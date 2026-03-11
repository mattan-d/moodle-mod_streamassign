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

namespace mod_streamassign;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');
require_once($GLOBALS['CFG']->dirroot . '/repository/lib.php');

/**
 * Submission form: one video file + optional title.
 *
 * @package    mod_streamassign
 * @copyright  2025 mattandor <mattan@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        /** @var \stdClass $customdata */
        $customdata = $this->_customdata;
        $context = $customdata->context;
        $cmid = $customdata->cmid;

        $mform->addElement('hidden', 'id', $cmid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'videotitle', get_string('videotitle', 'streamassign'), ['size' => 64]);
        $mform->setType('videotitle', PARAM_TEXT);
        $mform->addHelpButton('videotitle', 'videotitle', 'streamassign');

        $options = [
            'maxfiles' => 1,
            'accepted_types' => ['video'],
            'return_types' => \FILE_INTERNAL,
        ];
        $mform->addElement('filemanager', 'video_file', get_string('uploadvideo', 'streamassign'), null, $options);
        $mform->addRule('video_file', get_string('required'), 'required', null, 'client');
        $mform->addElement('static', 'allowedformats', '', get_string('allowedformats', 'streamassign'));

        $this->add_action_buttons(true, get_string('submitvideo', 'streamassign'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $draftid = $data['video_file'] ?? 0;
        if ($draftid) {
            $usercontext = \context_user::instance($GLOBALS['USER']->id);
            $fs = get_file_storage();
            $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id', false);
            if (empty($draftfiles)) {
                $errors['video_file'] = get_string('uploaderror', 'streamassign');
            }
        }
        return $errors;
    }
}
