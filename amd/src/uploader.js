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
 * Chunked video upload (up to 2GB) with drag-and-drop zone.
 *
 * @module     mod_streamassign/uploader
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    if (window.console && console.log) {
        console.log('[streamassign] uploader AMD module loaded');
    }

    var CHUNK_SIZE = 5 * 1024 * 1024; // 5MB
    var MAX_SIZE = 2 * 1024 * 1024 * 1024; // 2GB

    /**
     * Find hidden input by name (with or without Moodle prefix).
     * @param {HTMLFormElement} form
     * @param {string} name
     * @returns {HTMLInputElement|null}
     */
    function findHidden(form, name) {
        if (!form) {
            return null;
        }
        var inputs = form.querySelectorAll('input[type="hidden"]');
        for (var i = 0; i < inputs.length; i++) {
            var n = inputs[i].getAttribute('name') || '';
            if (n === name || n.indexOf(name) !== -1) {
                return inputs[i];
            }
        }
        return null;
    }

    /**
     * Initialize the custom upload drop zone and chunked upload.
     * @param {string} baseUrl Optional base URL for upload (default: relative /mod/streamassign/upload_video.php)
     */
    function init(baseUrl) {
        if (window.console && console.log) {
            console.log('[streamassign] uploader init() called');
        }
        var wrapper = document.querySelector('.streamassign-upload-zone-wrapper');
        if (!wrapper) {
            if (window.console && console.warn) {
                console.warn('[streamassign] uploader init exit: no .streamassign-upload-zone-wrapper');
            }
            return;
        }
        var zone = wrapper.querySelector('.streamassign-upload-zone') || document.getElementById('streamassign-upload-zone');
        var fileInput = wrapper.querySelector('.streamassign-upload-file-input') || document.getElementById('streamassign-upload-file-input');
        var progressWrapper = wrapper.querySelector('#streamassign-upload-progress-wrapper');
        var progressBar = wrapper.querySelector('#streamassign-upload-progress-bar');
        var progressText = wrapper.querySelector('#streamassign-upload-progress-text');
        var doneEl = wrapper.querySelector('#streamassign-upload-done');
        var form = wrapper.closest('form');
        if (!form) {
            form = document.querySelector('form.streamassign-submission-form');
        }
        if (!form) {
            var el = wrapper.parentElement;
            while (el && el !== document.body) {
                if (el.tagName && el.tagName.toLowerCase() === 'form') {
                    form = el;
                    break;
                }
                el = el.parentElement;
            }
        }
        if (!form) {
            form = document.querySelector('form[id^="mform"]');
        }
        if (!zone || !fileInput || !form) {
            if (window.console && console.warn) {
                console.warn('[streamassign] uploader init exit: missing zone/fileInput/form', {zone: !!zone, fileInput: !!fileInput, form: !!form});
            }
            return;
        }

        var cmid = wrapper.getAttribute('data-cmid');
        var sesskey = wrapper.getAttribute('data-sesskey');
        if (!cmid || !sesskey) {
            if (window.console && console.warn) {
                console.warn('[streamassign] uploader init exit: missing cmid/sesskey', {cmid: cmid, sesskey: !!sesskey});
            }
            return;
        }

        if (window.console && console.log) {
            console.log('[streamassign] uploader init OK', {cmid: cmid});
        }

        var uploadUrl = wrapper.getAttribute('data-upload-url') || (window.M && M.cfg && M.cfg.wwwroot ? M.cfg.wwwroot + '/mod/streamassign/upload_video.php' : '/mod/streamassign/upload_video.php');
        var hiddenStreamId = findHidden(form, 'new_upload_stream_id');
        var videotitleInput = form.querySelector('input[type="text"][name*="videotitle"], input[type="text"][id*="videotitle"]');
        var filenameEl = wrapper.querySelector('#streamassign-upload-filename');

        /** Resolve string from M.util.get_string (may be sync or Promise depending on Moodle version). */
        function getStr(key, fallback, callback) {
            if (!window.M || !M.util || !M.util.get_string) {
                callback(fallback);
                return;
            }
            var result = M.util.get_string(key, 'mod_streamassign');
            if (result && typeof result.then === 'function') {
                result.then(function(s) { callback(s || fallback); }).catch(function() { callback(fallback); });
            } else {
                callback(typeof result === 'string' ? result : fallback);
            }
        }

        function getVideotitle() {
            if (videotitleInput) {
                return (videotitleInput.value || '').trim();
            }
            return '';
        }

        function setStreamId(id) {
            if (hiddenStreamId) {
                hiddenStreamId.value = id;
                hiddenStreamId.removeAttribute('disabled');
            }
        }

        function setState(idle, uploading, done) {
            var inner = zone.querySelector('.streamassign-upload-zone-inner');
            if (inner) {
                inner.style.display = uploading || done ? 'none' : '';
            }
            if (progressWrapper) {
                progressWrapper.style.display = uploading ? 'block' : 'none';
            }
            if (doneEl) {
                doneEl.style.display = done ? 'block' : 'none';
            }
            zone.classList.toggle('streamassign-upload-uploading', uploading);
            zone.classList.toggle('streamassign-upload-done', done);
        }

        function showError(msg) {
            setState(false, false, false);
            if (doneEl) {
                doneEl.textContent = msg;
                doneEl.className = 'streamassign-upload-done streamassign-upload-error';
                doneEl.style.display = 'block';
            }
        }

        function uploadFile(file) {
            if (window.console && console.log) {
                console.log('mod_streamassign/uploader uploadFile', {name: file && file.name, size: file && file.size});
            }
            if (file.size > MAX_SIZE) {
                getStr('uploadtoolarge', 'File exceeds 2GB.', showError);
                return;
            }

            var totalChunks = Math.ceil(file.size / CHUNK_SIZE);
            var uploadId = '';
            var title = getVideotitle();
            setState(false, true, false);
            if (progressBar) {
                progressBar.style.width = '0%';
            }
            if (progressText) {
                progressText.textContent = '0%';
            }

            function sendChunk(index) {
                var start = index * CHUNK_SIZE;
                var end = Math.min(start + CHUNK_SIZE, file.size);
                var chunk = file.slice(start, end);

                if (window.console && console.log) {
                    console.log('mod_streamassign/uploader sendChunk', {index: index, start: start, end: end, totalChunks: totalChunks});
                }

                var formData = new FormData();
                formData.append('id', cmid);
                formData.append('sesskey', sesskey);
                formData.append('chunk_index', index);
                formData.append('total_chunks', totalChunks);
                formData.append('filename', file.name);
                formData.append('chunk', chunk);
                if (uploadId) {
                    formData.append('upload_id', uploadId);
                }
                if (title) {
                    formData.append('videotitle', title);
                }

                return fetch(uploadUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function(response) {
                    return response.text().then(function(text) {
                        if (window.console && console.log) {
                            console.log('mod_streamassign/uploader response', {status: response.status, text: text});
                        }
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('Invalid JSON from upload endpoint');
                        }
                    });
                }).then(function(data) {
                    if (data.upload_id) {
                        uploadId = data.upload_id;
                    }
                    if (data.success && data.streamid) {
                        setStreamId(data.streamid);
                        setState(false, false, true);
                        if (doneEl) {
                            doneEl.className = 'streamassign-upload-done streamassign-upload-success';
                            getStr('uploadsuccess', 'Upload complete.', function(s) {
                                doneEl.textContent = s;
                            });
                        }
                        return;
                    }
                    if (data.success && data.chunk_accepted) {
                        var pct = Math.round(((index + 1) / totalChunks) * 100);
                        if (progressBar) {
                            progressBar.style.width = pct + '%';
                        }
                        if (progressText) {
                            progressText.textContent = pct + '%';
                        }
                        if (index + 1 < totalChunks) {
                            return sendChunk(index + 1);
                        }
                        return Promise.reject(new Error('Chunk accepted but no streamid'));
                    }
                    return Promise.reject(new Error(data.message || 'Upload failed'));
                });
            }

            sendChunk(0).catch(function(err) {
                if (window.console && console.error) {
                    console.error('mod_streamassign/uploader error', err);
                }
                var fallback = err.message || 'Upload failed';
                getStr('uploaderror', 'Upload failed', function(s) {
                    showError(err.message || s || fallback);
                });
            });
        }

        function handleFile(file) {
            if (!file) {
                return;
            }

            // Some browsers may not set file.type for videos; rely on server-side validation for safety.
            var name = file.name || '';
            if (filenameEl) {
                filenameEl.textContent = name;
                filenameEl.style.display = 'block';
            }

            if (window.console && console.log) {
                console.log('mod_streamassign/uploader handleFile', {name: name, type: file.type});
            }

            // Basic client-side check: allow if MIME starts with video/ or extension is common video type.
            var lower = name.toLowerCase();
            var looksVideo = (file.type && file.type.indexOf('video/') === 0) ||
                lower.match(/\.(mp4|webm|mkv|avi|mov|mpeg|mpg|flv|wmv|ogv|ogg|vob)$/);
            if (!looksVideo) {
                getStr('uploaderror', 'Please select a video file.', showError);
                return;
            }
            uploadFile(file);
        }

        zone.addEventListener('click', function(e) {
            if (zone.classList.contains('streamassign-upload-uploading') || zone.classList.contains('streamassign-upload-done')) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            /* Do not preventDefault/stopPropagation: let the label's native behavior open the file input.
               Programmatic fileInput.click() is blocked in many browsers after preventDefault. */
        }, true);

        zone.addEventListener('keydown', function(e) {
            if ((e.key === 'Enter' || e.key === ' ') && !zone.classList.contains('streamassign-upload-uploading')) {
                e.preventDefault();
                fileInput.removeAttribute('disabled');
                fileInput.click();
            }
        });

        fileInput.addEventListener('change', function() {
            var file = fileInput.files && fileInput.files[0];
            if (file) {
                handleFile(file);
            }
            fileInput.value = '';
        });

        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.add('streamassign-upload-dragover');
        });

        zone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('streamassign-upload-dragover');
        });

        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('streamassign-upload-dragover');
            var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (file) {
                handleFile(file);
            }
        });
    }

    return {
        init: init
    };
});
