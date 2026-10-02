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
 * Stop motion: pause autoplaying video, freeze animated GIFs (spec §5; CSS handles animations).
 *
 * @module     local_accessibility/motion
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const frozen = new Map();

/**
 * Whether the image comes from this site; a canvas drawn from any other origin would taint, and we never
 * fetch cross-origin images.
 *
 * @param {HTMLImageElement} img
 * @returns {boolean}
 */
const isSameOrigin = (img) => {
    try {
        return new URL(img.currentSrc, window.location.href).origin === window.location.origin;
    } catch (e) {
        return false;
    }
};

/**
 * Whether an image is a GIF; pluginfile URLs have no suffix, so ask the server once (spec §5).
 *
 * @param {HTMLImageElement} img
 * @returns {Promise<boolean>}
 */
const isGif = async(img) => {
    if (!img.currentSrc || !isSameOrigin(img)) {
        return false;
    }
    if (/\.gif(\?|#|$)/i.test(img.currentSrc)) {
        return true;
    }
    try {
        const r = await fetch(img.currentSrc, {method: 'HEAD', credentials: 'same-origin'});
        return (r.headers.get('Content-Type') || '').startsWith('image/gif');
    } catch (e) {
        return false;
    }
};

/**
 * Replace a GIF with a canvas showing its first frame.
 *
 * @param {HTMLImageElement} img
 */
const freeze = (img) => {
    if (frozen.has(img) || !img.complete || !img.naturalWidth) {
        return;
    }
    const c = document.createElement('canvas');
    c.width = img.naturalWidth;
    c.height = img.naturalHeight;
    c.style.width = img.width + 'px';
    c.style.height = img.height + 'px';
    c.setAttribute('role', 'img');
    c.setAttribute('aria-label', img.alt || '');
    try {
        c.getContext('2d').drawImage(img, 0, 0);
    } catch (e) {
        return;
    }
    img.style.display = 'none';
    img.after(c);
    frozen.set(img, c);
};

/**
 * Whether Motion is currently on.
 *
 * @returns {boolean}
 */
const isOn = () => document.documentElement.getAttribute('data-a11y-motion') === 'on';

/**
 * Stop or restore motion.
 *
 * @param {boolean} stop
 */
const set = async(stop) => {
    document.querySelectorAll('video').forEach((v) => {
        if (stop && !v.paused) {
            v.pause();
            v.dataset.laPaused = '1';
        } else if (!stop && v.dataset.laPaused) {
            delete v.dataset.laPaused;
        }
        v.autoplay = !stop && v.hasAttribute('autoplay');
    });
    if (stop) {
        for (const img of document.querySelectorAll('#page img')) {
            // Motion may have been switched off while a HEAD request was in flight.
            if (await isGif(img) && isOn()) {
                freeze(img);
            }
        }
    } else {
        frozen.forEach((c, img) => {
            c.remove();
            img.style.display = '';
        });
        frozen.clear();
    }
};

/**
 * Initialise; the OS reduced-motion setting turns it on for users who never chose (spec §7.3).
 */
export const init = () => {
    if (isOn()) {
        set(true);
    }
    document.addEventListener('local_accessibility:changed', (e) => {
        if (e.detail.feature === 'motion') {
            set(e.detail.value === 'on');
        }
    });
};
