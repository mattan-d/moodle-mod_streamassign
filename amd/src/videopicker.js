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
 * Video picker: card selection, search filter, and ensure selected value is submitted.
 *
 * @module     mod_streamassign/videopicker
 * @copyright  2025 mattandor <mattan@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Find the hidden input for existing_video_id in the form (Moodle may add prefix to name).
     * @param {HTMLFormElement} form
     * @returns {HTMLInputElement|null}
     */
    function findHiddenInput(form) {
        if (!form) {
            return null;
        }
        var inputs = form.querySelectorAll('input[type="hidden"]');
        for (var i = 0; i < inputs.length; i++) {
            var name = inputs[i].getAttribute('name') || '';
            if (name === 'existing_video_id' || name.indexOf('existing_video_id') !== -1) {
                return inputs[i];
            }
        }
        return null;
    }

    /**
     * Find radio inputs for submission_type (Moodle may add prefix to name).
     * @param {HTMLFormElement} form
     * @returns {Array}
     */
    function findTypeRadios(form) {
        if (!form) {
            return [];
        }
        var all = form.querySelectorAll('input[type="radio"]');
        var out = [];
        for (var n = 0; n < all.length; n++) {
            var name = all[n].getAttribute('name') || '';
            if (name.indexOf('submission_type') !== -1) {
                out.push(all[n]);
            }
        }
        return out;
    }

    /**
     * Initialise the video picker: card selection, search, submit fix, show/hide list by method.
     */
    function init() {
        var section = document.querySelector('.streamassign-myvideos-section');
        if (!section) {
            return;
        }
        var list = section.querySelector('.streamassign-existing-videos-list');
        if (!list) {
            return;
        }
        var form = section.closest('form');
        if (!form) {
            return;
        }
        var hidden = findHiddenInput(form);
        if (!hidden) {
            return;
        }

        var typeRadios = findTypeRadios(form);

        function updateSectionVisibility() {
            var isExisting = false;
            for (var r = 0; r < typeRadios.length; r++) {
                if (typeRadios[r].checked && typeRadios[r].value === 'existing') {
                    isExisting = true;
                    break;
                }
            }
            section.style.display = isExisting ? '' : 'none';
        }

        // Ensure "Upload a new video" is selected by default if nothing is checked (e.g. Moodle prefix).
        var noneChecked = true;
        for (var r = 0; r < typeRadios.length; r++) {
            if (typeRadios[r].checked) {
                noneChecked = false;
                break;
            }
        }
        if (noneChecked) {
            for (var r = 0; r < typeRadios.length; r++) {
                if (typeRadios[r].value === 'upload') {
                    typeRadios[r].checked = true;
                    break;
                }
            }
        }

        updateSectionVisibility();
        for (var r = 0; r < typeRadios.length; r++) {
            typeRadios[r].addEventListener('change', updateSectionVisibility);
        }

        var searchInput = section.querySelector('.streamassign-video-search');

        function filterCards() {
            var q = (searchInput && searchInput.value) ? searchInput.value.trim().toLowerCase() : '';
            var cards = list.querySelectorAll('.streamassign-video-card');
            for (var j = 0; j < cards.length; j++) {
                var card = cards[j];
                var title = (card.getAttribute('data-search-title') || '');
                card.style.display = (q === '' || title.indexOf(q) !== -1) ? '' : 'none';
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterCards);
            searchInput.addEventListener('keyup', filterCards);
        }

        function selectCard(card) {
            var id = card.getAttribute('data-video-id');
            if (!id) {
                return;
            }
            hidden.value = id;
            hidden.removeAttribute('disabled');
            var cards = list.querySelectorAll('.streamassign-video-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            }
            card.classList.add('selected');
            var radio = card.querySelector('.streamassign-video-radio');
            if (radio) {
                radio.checked = true;
            }
        }

        var cardsList = list.querySelectorAll('.streamassign-video-card');
        for (var k = 0; k < cardsList.length; k++) {
            (function(card) {
                card.addEventListener('click', function(e) {
                    e.preventDefault();
                    selectCard(card);
                });
            })(cardsList[k]);
        }

        form.addEventListener('submit', function() {
            var sel = list.querySelector('.streamassign-video-card.selected');
            if (sel) {
                hidden.removeAttribute('disabled');
                hidden.value = sel.getAttribute('data-video-id');
            }
        });
    }

    return {
        init: init
    };
});
