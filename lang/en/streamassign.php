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
 * English strings for mod_streamassign.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['modulename'] = 'Stream assignment';
$string['modulenameplural'] = 'Stream assignments';
$string['modulename_help'] = 'Students submit a video file that is uploaded to the Stream platform.';
$string['pluginname'] = 'Stream assignment';
$string['pluginadministration'] = 'Stream assignment administration';
$string['streamassign:view'] = 'View Stream assignment';
$string['streamassign:submit'] = 'Submit video to Stream assignment';
$string['streamassign:addinstance'] = 'Add a new Stream assignment';
$string['streamassign:grade'] = 'Grade Stream assignment';

$string['streamassignsettings'] = 'Stream assignment settings';
$string['streamurl_required'] = 'Stream URL and API key must be configured in the local_stream plugin (Site administration → Plugins → Local plugins → Stream).';
$string['submitvideo'] = 'Submit video';
$string['submission'] = 'Submission';
$string['nosubmission'] = 'No submission yet';
$string['yoursubmission'] = 'Your submission';
$string['submittedon'] = 'Submitted on';
$string['watchvideo'] = 'Watch video';
$string['uploadvideo'] = 'Upload video';
$string['uploadzonetext'] = 'You can drag and drop files here to add them.';
$string['uploadzonehint'] = 'Maximum file size: 2GB. Accepted formats: MP4, WebM, MOV, AVI, and other common video formats.';
$string['uploadinprogress'] = 'Uploading…';
$string['uploadtoolarge'] = 'File exceeds the maximum size of 2GB.';
$string['videotitle'] = 'Video title (optional)';
$string['videotitle_help'] = 'Title for the video on the Stream platform. If empty, the file name will be used.';
$string['allowedformats'] = 'Allowed formats: MP4, WebM, MKV, AVI, MOV, and other common video formats.';
$string['uploadsuccess'] = 'Video uploaded successfully to Stream.';
$string['uploaderror'] = 'Upload failed';
$string['activitynotavailableyet'] = 'This activity is not available until {$a}.';
$string['activityclosed'] = 'This activity closed on {$a}.';
$string['nostreamassignments'] = 'No Stream assignments';
$string['privacy:metadata:streamassign_submission'] = 'Stores the Stream video ID and submission time for each user.';
$string['privacy:metadata:streamplatform'] = 'Video files are uploaded to an external Stream platform; user email and name may be sent for ownership.';

// Settings page - connection status
$string['connectionstatus'] = 'Stream connection status';
$string['connection_streamurl'] = 'Stream URL (local_stream):';
$string['connection_apikey'] = 'API key (local_stream):';
$string['connection_configured'] = 'Configured';
$string['connection_notset'] = 'Not set';
$string['connection_reach'] = 'Reachability:';
$string['connection_ok'] = 'OK';
$string['connection_failed'] = 'Could not reach server (check URL or network)';
$string['connection_ready'] = 'Stream is configured and reachable. The plugin is ready to use.';
$string['connection_configured_not_reachable'] = 'Stream URL and API key are set, but the Stream server could not be reached.';
$string['connection_not_configured'] = 'Stream is not configured. Configure Stream URL and API key in the local_stream plugin (Site administration → Plugins → Local plugins → Stream).';
$string['connectioninfo'] = 'About Stream settings';
$string['connectioninfo_desc'] = 'This activity uses the Stream URL and API key from the local_stream plugin. To change them, go to Site administration → Plugins → Local plugins → Stream.';

// Grading
$string['grading'] = 'Grading submissions';
$string['viewgrading'] = 'Grade submissions';
$string['savegrades'] = 'Save grades';
$string['gradesupdated'] = 'Grades saved.';
$string['nosubmissionsgrading'] = 'No submissions to grade.';
$string['backtoactivity'] = 'Back to activity';
$string['feedback'] = 'Feedback';
$string['clearfilter'] = 'Show all';

// Submission summary (view page, for graders)
$string['submissionsummary'] = 'Submission summary';
$string['numberofparticipants'] = 'Participants';
$string['numberofsubmitted'] = 'Submitted';
$string['numberofneedgrading'] = 'Needs grading';
$string['numberofnotsubmitted'] = 'Not submitted';

$string['thumbnail'] = 'Thumbnail';
$string['nothumbnail'] = 'No thumbnail';

// Submit: choose existing video or upload new
$string['choosemethod'] = 'How do you want to submit?';
$string['selectexisting'] = 'Choose from my existing videos';
$string['uploadnew'] = 'Upload a new video';
$string['myvideos'] = 'My videos';
$string['myvideos_help'] = 'Videos you have already uploaded to the Stream platform. Select one to submit for this activity.';
$string['selectvideo'] = 'Choose a video...';
$string['pleaseselectvideo'] = 'Please select a video.';
$string['noexistingvideos'] = 'You have no videos on Stream yet. Upload a new video below.';
$string['searchmyvideos'] = 'Search videos...';
$string['selected'] = 'Selected';

$string['videoprocessing'] = 'Your video is still being processed. The player will appear here when it is ready (we check every 30 seconds).';
