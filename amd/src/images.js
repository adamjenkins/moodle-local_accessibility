// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Show alt text in place of hidden images (spec §5).
 *
 * @module     local_accessibility/images
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const SELECTOR = '#page img:not(.icon, .activityicon, .userpicture)';

/**
 * Add one span with the alt text after each content image that has alt text.
 */
const addAlts = () => {
    document.querySelectorAll(SELECTOR).forEach((img) => {
        if (img.dataset.laAlt || !img.alt) {
            return;
        }
        const span = document.createElement('span');
        span.className = 'la-alt';
        span.setAttribute('aria-hidden', 'true');
        span.textContent = img.alt;
        img.after(span);
        img.dataset.laAlt = '1';
    });
};

/**
 * Initialise.
 */
export const init = () => {
    if (document.documentElement.getAttribute('data-a11y-images') === 'on') {
        addAlts();
    }
    document.addEventListener('local_accessibility:changed', (e) => {
        if (e.detail.feature === 'images' && e.detail.value === 'on') {
            addAlts();
        }
    });
};
