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
        var wrapper = document.querySelector('.streamassign-upload-zone-wrapper');
        if (!wrapper) {
            return;
        }
        var zone = document.getElementById('streamassign-upload-zone');
        var fileInput = document.getElementById('streamassign-upload-file-input');
        var progressWrapper = document.getElementById('streamassign-upload-progress-wrapper');
        var progressBar = document.getElementById('streamassign-upload-progress-bar');
        var progressText = document.getElementById('streamassign-upload-progress-text');
        var doneEl = document.getElementById('streamassign-upload-done');
        var form = wrapper.closest('form');
        if (!zone || !fileInput || !form) {
            return;
        }

        var cmid = wrapper.getAttribute('data-cmid');
        var sesskey = wrapper.getAttribute('data-sesskey');
        if (!cmid || !sesskey) {
            return;
        }

        var uploadUrl = wrapper.getAttribute('data-upload-url') || (window.M && M.cfg && M.cfg.wwwroot ? M.cfg.wwwroot + '/mod/streamassign/upload_video.php' : '');
        if (!uploadUrl) {
            return;
        }
        var hiddenStreamId = findHidden(form, 'new_upload_stream_id');
        var videotitleInput = form.querySelector('input[type="text"][name*="videotitle"], input[type="text"][id*="videotitle"]');

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
            if (file.size > MAX_SIZE) {
                showError(window.M && M.util && M.util.get_string ? M.util.get_string('uploadtoolarge', 'streamassign') : 'File exceeds 2GB.');
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
                    return response.json();
                }).then(function(data) {
                    if (data.upload_id) {
                        uploadId = data.upload_id;
                    }
                    if (data.success && data.streamid) {
                        setStreamId(data.streamid);
                        setState(false, false, true);
                        if (doneEl) {
                            doneEl.textContent = (window.M && M.util && M.util.get_string ? M.util.get_string('uploadsuccess', 'streamassign') : 'Upload complete.');
                            doneEl.className = 'streamassign-upload-done streamassign-upload-success';
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
                showError(err.message || (window.M && M.util && M.util.get_string ? M.util.get_string('uploaderror', 'streamassign') : 'Upload failed'));
            });
        }

        function handleFile(file) {
            if (!file || !file.type.match(/^video\//)) {
                showError(window.M && M.util && M.util.get_string ? M.util.get_string('uploaderror', 'streamassign') : 'Please select a video file.');
                return;
            }
            uploadFile(file);
        }

        zone.addEventListener('click', function(e) {
            if (!zone.classList.contains('streamassign-upload-uploading') && !zone.classList.contains('streamassign-upload-done')) {
                fileInput.click();
            }
        });

        zone.addEventListener('keydown', function(e) {
            if ((e.key === 'Enter' || e.key === ' ') && !zone.classList.contains('streamassign-upload-uploading')) {
                e.preventDefault();
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
