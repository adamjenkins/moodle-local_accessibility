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
 * Reading ruler and mask that follow the pointer (spec §5).
 *
 * @module     local_accessibility/guide
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
let el = null;
let y = 0;
let queued = false;

/**
 * Draw at the last pointer position.
 */
const draw = () => {
    queued = false;
    if (el) {
        el.style.setProperty('--la-guide-y', y + 'px');
    }
};

/**
 * Show, change or remove the guide.
 *
 * @param {string} value off, ruler or mask
 */
const set = (value) => {
    if (value === 'off') {
        el?.remove();
        el = null;
        return;
    }
    if (!el) {
        el = document.createElement('div');
        el.setAttribute('aria-hidden', 'true');
        document.body.appendChild(el);
    }
    el.className = 'la-guide la-guide-' + value;
    draw();
};

/**
 * Initialise from the current attribute and follow changes.
 */
export const init = () => {
    set(document.documentElement.getAttribute('data-a11y-guide') || 'off');
    document.addEventListener('pointermove', (e) => {
        y = e.clientY;
        if (!queued) {
            queued = true;
            window.requestAnimationFrame(draw);
        }
    }, {passive: true});
    document.addEventListener('local_accessibility:changed', (e) => {
        if (e.detail.feature === 'guide') {
            set(e.detail.value);
        }
    });
};
