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
 * Chunked video upload endpoint for Stream assignment (allows large files up to 2GB).
 * Receives chunks via POST, assembles file, uploads to Stream, returns streamid.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/classes/stream_uploader.php');

global $CFG, $DB, $USER;

$id = required_param('id', PARAM_INT); // cmid
$sesskey = required_param('sesskey', PARAM_RAW);
$chunkindex = required_param('chunk_index', PARAM_INT);
$totalchunks = required_param('total_chunks', PARAM_INT);
$filename = required_param('filename', PARAM_FILE);
$uploadid = optional_param('upload_id', '', PARAM_ALPHANUMEXT);
$videotitle = optional_param('videotitle', '', PARAM_TEXT);

header('Content-Type: application/json; charset=utf-8');

if ($totalchunks < 1 || $chunkindex < 0 || $chunkindex >= $totalchunks) {
    echo json_encode(['success' => false, 'message' => 'Invalid chunk parameters']);
    exit;
}

$maxbytes = 2 * 1024 * 1024 * 1024; // 2GB
$maxchunkbytes = 5 * 1024 * 1024;    // 5MB per chunk

$cm = get_coursemodule_from_id('streamassign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$streamassign = $DB->get_record('streamassign', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey($sesskey);
$context = context_module::instance($cm->id);
require_capability('mod/streamassign:submit', $context);

$timenow = time();
if ($streamassign->timeopen > 0 && $timenow < $streamassign->timeopen) {
    echo json_encode(['success' => false, 'message' => get_string('activitynotavailableyet', 'streamassign', userdate($streamassign->timeopen))]);
    exit;
}
if (!empty($streamassign->preventlatesubmission) && $streamassign->timeclose > 0 && $timenow > $streamassign->timeclose) {
    echo json_encode(['success' => false, 'message' => get_string('activityclosed', 'streamassign', userdate($streamassign->timeclose))]);
    exit;
}
if (empty($streamassign->allowresubmission)) {
    $existing = $DB->record_exists('streamassign_submission', ['streamassignid' => $streamassign->id, 'userid' => $USER->id]);
    if ($existing) {
        echo json_encode(['success' => false, 'message' => get_string('resubmissionnotallowed', 'streamassign')]);
        exit;
    }
}

if (!isset($_FILES['chunk']) || !is_uploaded_file($_FILES['chunk']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => get_string('uploaderror', 'streamassign')]);
    exit;
}

$chunksize = $_FILES['chunk']['size'];
if ($chunksize > $maxchunkbytes) {
    echo json_encode(['success' => false, 'message' => 'Chunk too large']);
    exit;
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$allowed = ['mp4', 'flv', 'webm', 'mkv', 'vob', 'ogv', 'ogg', 'avi', 'wmv', 'mov', 'mpeg', 'mpg'];
if (!in_array($ext, $allowed)) {
    echo json_encode(['success' => false, 'message' => get_string('uploaderror', 'streamassign') . ' ' . get_string('allowedformats', 'streamassign')]);
    exit;
}

$tempbase = $CFG->tempdir . '/streamassign_upload';
if (!is_dir($tempbase)) {
    if (!mkdir($tempbase, $CFG->directorypermissions, true)) {
        echo json_encode(['success' => false, 'message' => get_string('uploaderror', 'streamassign')]);
        exit;
    }
}

if ($uploadid === '' || preg_match('/[^a-zA-Z0-9_-]/', $uploadid)) {
    $uploadid = 'u' . $USER->id . '_' . $cm->id . '_' . substr(md5($filename . $timenow), 0, 12);
}

$uploaddir = $tempbase . '/' . $uploadid;
if (!is_dir($uploaddir)) {
    mkdir($uploaddir, $CFG->directorypermissions, true);
}

$chunkpath = $uploaddir . '/chunk_' . $chunkindex;
if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkpath)) {
    echo json_encode(['success' => false, 'message' => get_string('uploaderror', 'streamassign')]);
    exit;
}

// Check we have all chunks.
$have = 0;
for ($i = 0; $i < $totalchunks; $i++) {
    if (file_exists($uploaddir . '/chunk_' . $i)) {
        $have++;
    }
}

if ($have !== $totalchunks) {
    echo json_encode([
        'success' => true,
        'chunk_accepted' => true,
        'upload_id' => $uploadid,
        'chunk_index' => $chunkindex,
    ]);
    exit;
}

// Assemble full file and check total size.
$fullpath = $uploaddir . '/' . $filename;
$out = fopen($fullpath, 'wb');
if (!$out) {
    echo json_encode(['success' => false, 'message' => get_string('uploaderror', 'streamassign')]);
    exit;
}
$totalsize = 0;
for ($i = 0; $i < $totalchunks; $i++) {
    $cp = $uploaddir . '/chunk_' . $i;
    $data = file_get_contents($cp);
    $totalsize += strlen($data);
    if ($totalsize > $maxbytes) {
        fclose($out);
        @unlink($fullpath);
        echo json_encode(['success' => false, 'message' => 'File exceeds maximum size (2GB)']);
        exit;
    }
    fwrite($out, $data);
    @unlink($cp);
}
fclose($out);

$metadata = [
    'courseid' => (int) $streamassign->course,
    'cmid' => (int) $cm->id,
    'activity' => 'streamassign',
];
$title = trim($videotitle) !== '' ? trim($videotitle) : null;
$upload = \mod_streamassign\stream_uploader::upload($fullpath, $filename, $USER, $metadata, $title);
@unlink($fullpath);
@rmdir($uploaddir);

if (!$upload->success) {
    echo json_encode(['success' => false, 'message' => $upload->message]);
    exit;
}

echo json_encode([
    'success' => true,
    'streamid' => (int) $upload->streamid,
    'topic' => $upload->topic ?? '',
]);
