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
 * Accessibility dialog: open/close, focus trap, tile cycling (spec §4).
 *
 * @module     local_accessibility/panel
 * @copyright  2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {configure, save, reset} from 'local_accessibility/store';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

const FOCUSABLE = 'button:not([disabled]):not([aria-disabled="true"]), [href], input, select, textarea, '
    + '[tabindex]:not([tabindex="-1"])';
const CONTROLS = '[aria-controls="local-accessibility-panel"]';

let panel;
let opener = null;

/**
 * Set a feature value on <html> and announce it.
 *
 * @param {string} feature
 * @param {string} value
 * @param {string} defaultValue
 */
export const apply = (feature, value, defaultValue) => {
    const attr = 'data-a11y-' + feature;
    if (value === defaultValue) {
        document.documentElement.removeAttribute(attr);
    } else {
        document.documentElement.setAttribute(attr, value);
    }
    document.dispatchEvent(new CustomEvent('local_accessibility:changed', {detail: {feature, value}}));
};

/**
 * Open the dialog.
 *
 * @param {HTMLElement|null} from the control that opened it
 */
const open = (from) => {
    opener = from || document.activeElement;
    panel.hidden = false;
    document.querySelectorAll(CONTROLS).forEach((b) => b.setAttribute('aria-expanded', 'true'));
    const first = panel.querySelector(FOCUSABLE);
    if (first) {
        first.focus();
    }
    document.dispatchEvent(new CustomEvent('local_accessibility:open'));
};

/**
 * Close the dialog and return focus.
 */
const close = () => {
    panel.hidden = true;
    document.querySelectorAll(CONTROLS).forEach((b) => b.setAttribute('aria-expanded', 'false'));
    if (opener && document.contains(opener)) {
        opener.focus();
    }
};

/**
 * Advance a tile to its next value.
 *
 * @param {HTMLElement} tile
 */
const cycle = async(tile) => {
    if (tile.getAttribute('aria-disabled') === 'true') {
        return;
    }
    const values = JSON.parse(tile.dataset.values);
    const labels = JSON.parse(tile.dataset.labels);
    const next = (values.indexOf(tile.dataset.value) + 1) % values.length;
    const value = values[next];
    tile.dataset.value = value;
    tile.setAttribute('aria-label', tile.dataset.label + ', ' + labels[next]);
    if (tile.hasAttribute('aria-pressed')) {
        tile.setAttribute('aria-pressed', next > 0 ? 'true' : 'false');
    }
    tile.querySelectorAll('.la-dots i').forEach((dot, i) => dot.classList.toggle('la-on', i < next));
    apply(tile.dataset.feature, value, values[0]);
    panel.querySelector('.la-live').textContent =
        await getString('settingchanged', 'local_accessibility', {feature: tile.dataset.label, value: labels[next]});
    await save(tile.dataset.feature, value);
};

/**
 * Initialise.
 *
 * @param {Object} config from hook_callbacks::before_http_headers
 */
export const init = (config) => {
    configure(config);
    panel = document.getElementById('local-accessibility-panel');
    if (!panel || window !== window.top) {
        return;
    }
    document.addEventListener('click', (e) => {
        const launcher = e.target.closest('.local-accessibility-launcher, a[href$="#local-accessibility-panel"]');
        if (launcher) {
            e.preventDefault();
            if (panel.hidden) {
                open(launcher);
            } else {
                close();
            }
            return;
        }
        if (!panel.hidden && !panel.contains(e.target)) {
            close();
        }
    });
    panel.addEventListener('click', async(e) => {
        try {
            const tile = e.target.closest('.la-tile[data-feature]');
            if (tile) {
                await cycle(tile);
                return;
            }
            if (e.target.closest('[data-action="close"]')) {
                close();
            } else if (e.target.closest('[data-action="reset"]')) {
                await reset();
                window.location.reload();
            }
        } catch (error) {
            Notification.exception(error);
        }
    });
    panel.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
            return;
        }
        if (e.key !== 'Tab') {
            return;
        }
        const items = [...panel.querySelectorAll(FOCUSABLE)].filter((el) => el.offsetParent !== null);
        const first = items[0];
        const last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });
    if (config.shortcut) {
        document.addEventListener('keydown', (e) => {
            if (e.altKey && !e.ctrlKey && !e.metaKey && (e.key === 'a' || e.key === 'A' || e.code === 'KeyA')) {
                e.preventDefault();
                if (panel.hidden) {
                    open(null);
                } else {
                    close();
                }
            }
        });
    }
    // Keep the floating launcher off the focused element (WCAG 2.4.11).
    const launcher = document.querySelector('.local-accessibility-launcher');
    if (launcher) {
        document.addEventListener('focusin', (e) => {
            const r = e.target.getBoundingClientRect();
            const l = launcher.getBoundingClientRect();
            const overlaps = r.right > l.left && r.left < l.right && r.bottom > l.top && r.top < l.bottom;
            launcher.classList.toggle('la-launcher-shifted', overlaps && !panel.contains(e.target) && e.target !== launcher);
        });
    }
};
