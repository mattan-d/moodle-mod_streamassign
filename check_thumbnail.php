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

/**
 * AJAX endpoint: check if the current user's submission video has a thumbnail (ready to watch).
 * Returns JSON { ready: bool, embed_url?: string }.
 * Used by view page to poll every 30s until video is ready, then show embed.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER;

$id = required_param('id', PARAM_INT);
require_sesskey();

$cm = get_coursemodule_from_id('streamassign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$streamassign = $DB->get_record('streamassign', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/streamassign:view', $context);

header('Content-Type: application/json; charset=utf-8');

$submission = streamassign_get_submission((int) $streamassign->id, (int) $USER->id);
if (!$submission) {
    echo json_encode(['ready' => false]);
    exit;
}

$thumburl = \mod_streamassign\stream_uploader::get_video_thumbnail_url((int) $submission->streamid);
if ($thumburl === null) {
    echo json_encode(['ready' => false]);
    exit;
}

$embedurl = \mod_streamassign\stream_uploader::get_embed_url_with_jwt((int) $submission->streamid, $USER, 7200);
echo json_encode([
    'ready' => true,
    'embed_url' => $embedurl ?: '',
    'embed_width' => 640,
    'embed_height' => 360,
]);
