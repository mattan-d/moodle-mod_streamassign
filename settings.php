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
 * Stream assignment admin settings.
 * Displays connection status (local_stream streamurl + streamkey) and optional connectivity check.
 *
 * @package    mod_streamassign
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/stream_uploader.php');

if ($ADMIN->fulltree) {

    $streamurl = \mod_streamassign\stream_uploader::get_stream_base_url();
    $streamkey = \mod_streamassign\stream_uploader::get_stream_api_key();
    $configured = \mod_streamassign\stream_uploader::is_configured();

    $statuslines = [];
    $statuslines[] = get_string('connection_streamurl', 'streamassign') . ' '
        . ($streamurl ? get_string('connection_configured', 'streamassign') . ' (' . s($streamurl) . ')' : get_string('connection_notset', 'streamassign'));
    $statuslines[] = get_string('connection_apikey', 'streamassign') . ' '
        . ($streamkey ? get_string('connection_configured', 'streamassign') : get_string('connection_notset', 'streamassign'));

    $connectionok = false;
    if ($streamurl) {
        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 5, 'CURLOPT_CONNECTTIMEOUT' => 3]);
        $curl->get($streamurl);
        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        $connectionok = ($httpcode >= 200 && $httpcode < 500);
    }

    if ($connectionok) {
        $statuslines[] = get_string('connection_reach', 'streamassign') . ' ' . get_string('connection_ok', 'streamassign');
    } else if ($streamurl) {
        $statuslines[] = get_string('connection_reach', 'streamassign') . ' ' . get_string('connection_failed', 'streamassign');
    }

    if ($configured && $connectionok) {
        $notifyclass = 'notifysuccess';
        $summary = get_string('connection_ready', 'streamassign');
    } else if ($configured) {
        $notifyclass = 'notifywarning';
        $summary = get_string('connection_configured_not_reachable', 'streamassign');
    } else {
        $notifyclass = 'notifyproblem';
        $summary = get_string('connection_not_configured', 'streamassign');
    }

    $statushtml = $OUTPUT->notification(
        '<strong>' . $summary . '</strong><br><ul><li>' . implode('</li><li>', $statuslines) . '</li></ul>',
        $notifyclass
    );
    $settings->add(new admin_setting_heading(
        'streamassign/connectionstatus',
        get_string('connectionstatus', 'streamassign'),
        $statushtml
    ));

    $settings->add(new admin_setting_heading(
        'streamassign/connectioninfo',
        get_string('connectioninfo', 'streamassign'),
        get_string('connectioninfo_desc', 'streamassign')
    ));
}
