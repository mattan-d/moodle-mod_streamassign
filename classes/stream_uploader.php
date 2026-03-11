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

/**
 * Uploads a video file to the Stream platform via moodle-upload API.
 * Uses local_stream plugin config: streamurl and streamkey.
 *
 * @package    mod_streamassign
 * @copyright  2025 mattandor <mattan@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stream_uploader {

    /**
     * Get Stream base URL from local_stream config.
     *
     * @return string|null
     */
    public static function get_stream_base_url(): ?string {
        $url = get_config('local_stream', 'streamurl');
        return is_string($url) && $url !== '' ? rtrim($url, '/') : null;
    }

    /**
     * Get Stream API key from local_stream config.
     *
     * @return string|null
     */
    public static function get_stream_api_key(): ?string {
        $key = get_config('local_stream', 'streamkey');
        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Check if Stream is configured (URL + API key from local_stream).
     *
     * @return bool
     */
    public static function is_configured(): bool {
        return self::get_stream_base_url() !== null && self::get_stream_api_key() !== null;
    }

    /**
     * Build embed URL with JWT for viewing a video (including privacy "identifier").
     * Uses HS256 with Stream API key; payload: fullname, email, identifier (= videoid), nbf, exp.
     *
     * @param int $videoid Stream video ID
     * @param \stdClass $user Moodle user (firstname, lastname, email, idnumber)
     * @param int $validityseconds Token validity in seconds (default 2 hours)
     * @return string|null Full URL to embed with token, or null if not configured
     */
    public static function get_embed_url_with_jwt(int $videoid, \stdClass $user, int $validityseconds = 7200): ?string {
        $baseurl = self::get_stream_base_url();
        $apikey = self::get_stream_api_key();
        if (!$baseurl || !$apikey) {
            return null;
        }

        $fullname = trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? ''));
        if ($fullname === '') {
            $fullname = $user->username ?? 'User';
        }
        $email = strtolower(trim($user->email ?? ''));
        if ($email === '') {
            return null;
        }

        $now = time();
        $payload = [
            'fullname' => $fullname,
            'email' => $email,
            'identifier' => $videoid,
            'matricula' => $user->idnumber ?? '',
            'nbf' => $now,
            'exp' => $now + $validityseconds,
        ];

        try {
            $libjwt = $GLOBALS['CFG']->dirroot . '/lib/php-jwt/src/JWT.php';
            if (is_readable($libjwt)) {
                require_once($libjwt);
            }
            $jwt = \Firebase\JWT\JWT::encode($payload, $apikey, 'HS256');
        } catch (\Throwable $e) {
            return null;
        }

        return $baseurl . '/embed/' . $videoid . '?token=' . $jwt;
    }

    /**
     * Upload a video file to Stream via POST /webservice/api/moodle-upload.
     *
     * @param string $filepath Full path to the video file on disk.
     * @param string $filename Original filename (used as title if title not provided).
     * @param \stdClass $user Moodle user (email, firstname, lastname, username).
     * @param array $metadata Optional. e.g. ['courseid' => x, 'cmid' => y, 'activity' => 'streamassign'].
     * @param string|null $title Optional video title.
     * @return \stdClass { success: bool, streamid?: int, topic?: string, thumbnail?: string, message?: string }
     */
    public static function upload(
        string $filepath,
        string $filename,
        \stdClass $user,
        array $metadata = [],
        ?string $title = null
    ): \stdClass {
        $result = (object) ['success' => false, 'message' => '', 'debuginfo' => ''];

        $baseurl = self::get_stream_base_url();
        $apikey = self::get_stream_api_key();
        if (!$baseurl || !$apikey) {
            $result->message = get_string('streamurl_required', 'streamassign');
            $result->debuginfo = 'streamurl=' . ($baseurl ? 'set' : 'missing') . ', apikey=' . ($apikey ? 'set' : 'missing');
            return $result;
        }

        if (!is_readable($filepath)) {
            $result->message = get_string('uploaderror', 'streamassign');
            $result->debuginfo = 'file_not_readable: ' . $filepath;
            return $result;
        }

        $mimetype = 'video/mp4';
        if (preg_match('/\.(webm|mkv|avi|mov|mpeg|mpg|flv|wmv|ogv|ogg|vob)$/i', $filename, $m)) {
            $map = [
                'webm' => 'video/webm', 'mkv' => 'video/x-matroska', 'avi' => 'video/x-msvideo',
                'mov' => 'video/quicktime', 'mpeg' => 'video/mpeg', 'mpg' => 'video/mpeg',
                'flv' => 'video/x-flv', 'wmv' => 'video/x-ms-wmv', 'ogv' => 'video/ogg',
                'ogg' => 'video/ogg', 'vob' => 'video/dvd',
            ];
            $mimetype = $map[strtolower($m[1])] ?? 'video/mp4';
        }

        $postdata = [
            'file' => new \CURLFile($filepath, $mimetype, $filename),
            'email' => $user->email,
            'metadata' => json_encode($metadata),
        ];
        if ($title !== null && $title !== '') {
            $postdata['title'] = $title;
        }
        if (!empty($user->firstname)) {
            $postdata['firstname'] = $user->firstname;
        }
        if (!empty($user->lastname)) {
            $postdata['lastname'] = $user->lastname;
        }
        if (!empty($user->username)) {
            $postdata['username'] = $user->username;
        }

        $url = $baseurl . '/webservice/api/moodle-upload';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postdata,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apikey,
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        $httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerr = curl_error($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $debugparts = ['HTTP ' . $httpcode];
        if ($curlerr !== '') {
            $debugparts[] = 'cURL: ' . $curlerr;
        }
        if (is_string($response) && $response !== '') {
            $debugparts[] = 'response: ' . s(mb_substr($response, 0, 500));
        }

        if (!is_array($data)) {
            $result->message = get_string('uploaderror', 'streamassign');
            $result->debuginfo = implode('; ', $debugparts);
            return $result;
        }

        if (!empty($data['error'])) {
            $result->message = isset($data['message']) ? $data['message'] : get_string('uploaderror', 'streamassign');
            $result->debuginfo = implode('; ', $debugparts);
            return $result;
        }

        if (isset($data['streamid'])) {
            $result->success = true;
            $result->streamid = (int) $data['streamid'];
            $result->topic = $data['topic'] ?? '';
            $result->thumbnail = $data['thumbnail'] ?? '';
        } else {
            $result->message = isset($data['message']) ? $data['message'] : get_string('uploaderror', 'streamassign');
            $result->debuginfo = implode('; ', $debugparts);
        }

        return $result;
    }
}
