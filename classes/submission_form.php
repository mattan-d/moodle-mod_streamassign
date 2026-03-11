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
        $uservideos = $customdata->uservideos ?? [];

        $mform->addElement('hidden', 'id', $cmid);
        $mform->setType('id', PARAM_INT);

        $hasexisting = !empty($uservideos);
        if (!$hasexisting) {
            $mform->addElement('hidden', 'submission_type', 'upload');
            $mform->setType('submission_type', PARAM_ALPHA);
        }

        if ($hasexisting) {
            $mform->addElement('header', 'submissionmethod', get_string('choosemethod', 'streamassign'));
            $radios = [];
            $radios[] = $mform->createElement('radio', 'submission_type', '', get_string('selectexisting', 'streamassign'), 'existing');
            $radios[] = $mform->createElement('radio', 'submission_type', '', get_string('uploadnew', 'streamassign'), 'upload');
            $mform->addGroup($radios, 'submission_type_group', '', ['<br>'], false);
            $mform->setDefault('submission_type_group', 'upload');

            $mform->addElement('hidden', 'existing_video_id', 0);
            $mform->setType('existing_video_id', PARAM_INT);

            $searchplaceholder = get_string('searchmyvideos', 'streamassign');
            $selectedlabel = get_string('selected', 'streamassign');
            $listhtml = '<div class="streamassign-myvideos-section" id="streamassign-myvideos-section" style="display:none;">';
            $listhtml .= '<p class="streamassign-myvideos-label"><strong>' . get_string('myvideos', 'streamassign') . '</strong></p>';
            $listhtml .= '<input type="text" class="streamassign-video-search form-control" id="streamassign-video-search" placeholder="' . s($searchplaceholder) . '" autocomplete="off">';
            $listhtml .= '<div class="streamassign-existing-videos-list" id="streamassign-existing-videos-list">';
            foreach ($uservideos as $v) {
                $id = isset($v['id']) ? (int) $v['id'] : 0;
                if ($id <= 0) {
                    continue;
                }
                $title = isset($v['title']) ? $v['title'] : ('Video ' . $id);
                $duration = isset($v['duration']) ? $v['duration'] : '';
                $searchtitle = \core_text::strtolower($title);
                $thumburl = !empty($v['thumbnail']) ? $v['thumbnail'] : stream_uploader::get_video_thumbnail_url($id);
                $thumbhtml = '<span class="streamassign-video-thumb-wrap">';
                if ($thumburl) {
                    $thumbhtml .= '<img src="' . s($thumburl) . '" alt="" class="streamassign-video-thumb" width="160" height="90">';
                    if ($duration !== '') {
                        $thumbhtml .= '<span class="streamassign-video-duration">' . s($duration) . '</span>';
                    }
                } else {
                    $thumbhtml .= '<span class="streamassign-no-thumb">' . get_string('nothumbnail', 'streamassign') . '</span>';
                    if ($duration !== '') {
                        $thumbhtml .= '<span class="streamassign-video-duration">' . s($duration) . '</span>';
                    }
                }
                $thumbhtml .= '</span>';
                $listhtml .= '<label class="streamassign-video-card" data-video-id="' . $id . '" data-search-title="' . s($searchtitle) . '">';
                $listhtml .= '<input type="radio" name="existing_video_id_sel" value="' . $id . '" class="streamassign-video-radio">';
                $listhtml .= '<span class="streamassign-video-selected-badge" aria-label="' . s($selectedlabel) . '"></span>';
                $listhtml .= '<span class="streamassign-video-card-inner">' . $thumbhtml . '<span class="streamassign-video-title">' . s($title) . '</span></span>';
                $listhtml .= '</label>';
            }
            $listhtml .= '</div></div></div>';
            $mform->addElement('html', $listhtml);
            $mform->disabledIf('existing_video_id', 'submission_type_group', 'neq', 'existing');
        } else {
            $mform->addElement('static', 'noexisting', '', get_string('noexistingvideos', 'streamassign'));
        }

        $mform->addElement('header', 'uploadsection', get_string('uploadnew', 'streamassign'));
        $mform->addElement('text', 'videotitle', get_string('videotitle', 'streamassign'), ['size' => 64]);
        $mform->setType('videotitle', PARAM_TEXT);
        $mform->addHelpButton('videotitle', 'videotitle', 'streamassign');

        $options = [
            'maxfiles' => 1,
            'accepted_types' => ['video'],
            'return_types' => \FILE_INTERNAL,
        ];
        $mform->addElement('filemanager', 'video_file', get_string('uploadvideo', 'streamassign'), null, $options);
        $mform->addElement('static', 'allowedformats', '', get_string('allowedformats', 'streamassign'));

        if ($hasexisting) {
            $mform->disabledIf('videotitle', 'submission_type_group', 'neq', 'upload');
            $mform->disabledIf('video_file', 'submission_type_group', 'neq', 'upload');
            $mform->disabledIf('allowedformats', 'submission_type_group', 'neq', 'upload');
        }

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
        $type = isset($data['submission_type_group']) ? $data['submission_type_group'] : (isset($data['submission_type']) ? $data['submission_type'] : 'upload');

        if ($type === 'existing') {
            $existingid = isset($data['existing_video_id']) ? (int) $data['existing_video_id'] : 0;
            if ($existingid <= 0) {
                $errors['existing_video_id'] = get_string('pleaseselectvideo', 'streamassign');
            }
        } else {
            $draftid = $data['video_file'] ?? 0;
            if (empty($draftid)) {
                $errors['video_file'] = get_string('required');
            } else {
                $usercontext = \context_user::instance($GLOBALS['USER']->id);
                $fs = get_file_storage();
                $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id', false);
                if (empty($draftfiles)) {
                    $errors['video_file'] = get_string('uploaderror', 'streamassign');
                }
            }
        }
        return $errors;
    }
}
