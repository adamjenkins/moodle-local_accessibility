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
import {init as initColour} from 'local_accessibility/colour';
import {init as initGuide} from 'local_accessibility/guide';
import {init as initImages} from 'local_accessibility/images';
import {init as initMotion} from 'local_accessibility/motion';
import {init as initRead} from 'local_accessibility/read';

// Everything natively tabbable (aria-disabled buttons stay tabbable, so the trap must include them).
const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), '
    + 'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
const EDITABLE = 'input, textarea, select, [contenteditable]:not([contenteditable="false"])';
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
 * Whether an element is visible (rendered), including position: fixed ones.
 *
 * @param {Element|null} el
 * @returns {boolean}
 */
const isVisible = (el) => !!el && document.contains(el) && el.getClientRects().length > 0;

/**
 * Where focus goes on close: the opener, or, when it has gone (a user-menu item whose dropdown closed),
 * the user-menu toggle or the floating launcher.
 *
 * @returns {HTMLElement|null}
 */
const returnTarget = () => [opener, document.getElementById('user-menu-toggle'),
    document.querySelector('.local-accessibility-launcher')].find(isVisible) || null;

/**
 * Whether a keydown is the Alt+A shortcut. Characters typed with Alt/Option (macOS å, Polish ą, AZERTY Alt+Q)
 * are never taken: the physical-key fallback only applies when the key produced no character.
 *
 * @param {KeyboardEvent} e
 * @returns {boolean}
 */
const isShortcut = (e) => {
    if (!e.altKey || e.ctrlKey || e.metaKey || e.repeat || typeof e.key !== 'string') {
        return false;
    }
    if (e.key === 'a' || e.key === 'A') {
        return true;
    }
    const editable = e.target instanceof Element && e.target.closest(EDITABLE) !== null;
    return e.code === 'KeyA' && e.key.length !== 1 && !editable;
};

/**
 * The first visible control in a container that the user can operate.
 *
 * @param {Element|null} root
 * @returns {HTMLElement|undefined}
 */
const firstOperable = (root) => root ? [...root.querySelectorAll(FOCUSABLE)].find((el) =>
    el.getAttribute('aria-disabled') !== 'true' && isVisible(el)) : undefined;

/**
 * Open the dialog. Focus goes to the first setting, not to 'Reset All' in the head, which would wipe every
 * setting on a repeated Enter.
 *
 * @param {HTMLElement|null} from the control that opened it
 */
const open = (from) => {
    opener = from || document.activeElement;
    panel.hidden = false;
    document.querySelectorAll(CONTROLS).forEach((b) => b.setAttribute('aria-expanded', 'true'));
    // First, so colour.js puts the grid back (closing a colour editor left open) before focus is placed.
    document.dispatchEvent(new CustomEvent('local_accessibility:open'));
    const first = firstOperable(panel.querySelector('.la-grid')) || firstOperable(panel);
    if (first) {
        first.focus();
    }
};

/**
 * Close the dialog.
 *
 * @param {boolean} returnFocus false when the user clicked elsewhere, so focus stays where they clicked
 */
const close = (returnFocus = true) => {
    panel.hidden = true;
    document.querySelectorAll(CONTROLS).forEach((b) => b.setAttribute('aria-expanded', 'false'));
    const target = returnFocus ? returnTarget() : null;
    if (target) {
        target.focus();
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
 * Disable or re-enable controls for forced colours, touching only what this function disabled itself.
 * Controls the server rendered as aria-disabled (admin locks) are never changed.
 *
 * @param {Iterable<Element>} els
 * @param {boolean} on whether forced colours are active
 */
const setForced = (els, on) => {
    els.forEach((el) => {
        if (on) {
            if (el.getAttribute('aria-disabled') !== 'true') {
                el.setAttribute('aria-disabled', 'true');
                el.setAttribute('data-la-forced', '1');
            }
        } else if (el.hasAttribute('data-la-forced')) {
            el.setAttribute('aria-disabled', 'false');
            el.removeAttribute('data-la-forced');
        }
    });
};

/**
 * Mark the colour, saturation and links controls as controlled by the device while forced colours are active,
 * and show "Controlled by your device" as the colour tile's label.
 */
const initForcedColours = async() => {
    const forced = window.matchMedia('(forced-colors: active)');
    const label = panel.querySelector('.la-colourlabel');
    let original = '';
    let showing = false;
    const message = await getString('controlledbydevice', 'local_accessibility');
    const mark = () => {
        setForced(panel.querySelectorAll('.la-colour .la-swatch, [data-feature="saturation"], [data-feature="links"]'),
            forced.matches);
        if (label && forced.matches && !showing) {
            original = label.textContent;
            label.textContent = message;
            showing = true;
        } else if (label && !forced.matches && showing) {
            label.textContent = original;
            showing = false;
        }
    };
    mark();
    forced.addEventListener('change', mark);
};

/**
 * Whether the server left a control operable: it did not render it as locked. Forced-colours disabling, set by this
 * script, is not a lock.
 *
 * @param {Element} el
 * @returns {boolean}
 */
const unlocked = (el) => el.getAttribute('aria-disabled') !== 'true' || el.hasAttribute('data-la-forced');

/**
 * The value a tile shows, and its first (off/default) value. The colour tile is a group of swatches with no cycle:
 * its value is the pressed swatch's scheme, or 'custom' when none is pressed.
 *
 * @param {HTMLElement} tile
 * @returns {{value: string, first: string}}
 */
const tileState = (tile) => {
    if (tile.classList.contains('la-colour')) {
        const pressed = tile.querySelector('.la-swatch[data-scheme][aria-pressed="true"]');
        return {value: pressed ? pressed.dataset.scheme : 'custom', first: 'default'};
    }
    return {value: tile.dataset.value, first: JSON.parse(tile.dataset.values)[0]};
};

/**
 * Whether the panel shows a feature as one the user may change: it has a tile (so it is enabled) and the server
 * did not render it as locked. The colour tile is changeable while any of its scheme swatches is.
 *
 * @param {string} feature
 * @returns {boolean}
 */
const isChangeable = (feature) => {
    const tile = panel.querySelector('.la-tile[data-feature="' + feature + '"]');
    if (!tile) {
        return false;
    }
    if (tile.classList.contains('la-colour')) {
        return [...tile.querySelectorAll('.la-swatch[data-scheme]')].some(unlocked);
    }
    return unlocked(tile);
};

/**
 * Apply a profile: confirm if the user has changed anything they could change, save every value, then reload.
 *
 * @param {HTMLElement} profile the profile button
 */
const applyProfile = async(profile) => {
    const changed = [...panel.querySelectorAll('.la-tile[data-feature]')].some((t) => {
        const state = tileState(t);
        return isChangeable(t.dataset.feature) && state.value !== state.first;
    });
    // A native confirm is deliberate: a Moodle modal would sit outside this dialog's focus trap.
    // eslint-disable-next-line no-alert
    if (changed && !window.confirm(await getString('profileoverwrite', 'local_accessibility'))) {
        return;
    }
    const values = JSON.parse(profile.dataset.values);
    let saved = 0;
    let failure = null;
    for (const [feature, value] of Object.entries(values)) {
        if (!isChangeable(feature)) {
            continue;
        }
        try {
            await save(feature, value);
            saved++;
        } catch (error) {
            // One refused value (for example a lock set after the page loaded) must not abort the rest.
            failure = failure || error;
        }
    }
    if (saved) {
        window.location.reload();
    } else if (failure) {
        Notification.exception(failure);
    }
};

/**
 * Read or write a sessionStorage flag; storage may be unavailable (privacy modes), which reads as "no".
 *
 * @param {string} key
 * @param {string|null} value a string to set, null to remove, undefined to read
 * @returns {string|null|boolean} the stored value when reading; whether the write worked otherwise
 */
const flag = (key, value) => {
    try {
        if (value === undefined) {
            return window.sessionStorage.getItem(key);
        }
        if (value === null) {
            window.sessionStorage.removeItem(key);
        } else {
            window.sessionStorage.setItem(key, value);
        }
        return true;
    } catch (e) {
        return value === undefined ? null : false;
    }
};

/**
 * On a first visit, adopt the device's reduced-motion and more-contrast settings (spec §7.3), reloading once.
 * A sessionStorage guard is set before saving, so a failing save, or storage that cannot hold the guard,
 * never produces a reload loop.
 *
 * @param {Object} config
 */
const initDeviceSettings = async(config) => {
    const firstVisit = config.guest ? !document.cookie.includes(config.cookie + '=') : !config.initialised;
    if (firstVisit && !flag('local_accessibility_devicetried')) {
        const wants = {};
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            wants.motion = 'on';
        }
        if (window.matchMedia('(prefers-contrast: more)').matches) {
            wants.colour = 'highcontrast';
        }
        // Without a working guard a reload could repeat, so do nothing then.
        if (Object.keys(wants).length && flag('local_accessibility_devicetried', '1')) {
            let saved = 0;
            for (const [feature, value] of Object.entries(wants)) {
                if (!isChangeable(feature)) {
                    continue;
                }
                try {
                    await save(feature, value);
                    saved++;
                } catch (error) {
                    // Silent on purpose: the visitor did nothing, so a locked or disabled feature is not an error.
                    continue;
                }
            }
            if (saved && flag('local_accessibility_fromdevice', '1')) {
                window.location.reload();
                return;
            }
        }
    }
    if (flag('local_accessibility_fromdevice')) {
        flag('local_accessibility_fromdevice', null);
        const note = document.createElement('p');
        note.className = 'la-devicenote';
        note.textContent = await getString('fromdevice', 'local_accessibility');
        const undo = document.createElement('button');
        undo.type = 'button';
        undo.className = 'btn btn-link btn-sm';
        undo.textContent = await getString('undo', 'local_accessibility');
        undo.addEventListener('click', async() => {
            try {
                // Reset keeps the initialised marker, so this does not re-apply the device settings.
                await reset();
                window.location.reload();
            } catch (error) {
                Notification.exception(error);
            }
        });
        note.append(' ', undo);
        panel.querySelector('.la-grid').before(note);
    }
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
            close(false);
        }
    });
    panel.addEventListener('click', async(e) => {
        try {
            const profile = e.target.closest('.la-profile');
            if (profile) {
                await applyProfile(profile);
                return;
            }
            // The colour tile is a group of swatches (colour.js), not a cycling tile.
            const tile = e.target.closest('.la-tile[data-feature]:not(.la-colour)');
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
            if (isShortcut(e)) {
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
    initColour(panel);
    initForcedColours().catch(Notification.exception);
    initGuide();
    initImages();
    initMotion();
    initRead(panel);
    // Last, so every listener above is attached before the first await.
    initDeviceSettings(config).catch(Notification.exception);
};
