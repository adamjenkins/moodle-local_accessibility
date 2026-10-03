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
 * Accessibility dialog: open/close, focus trap, choosing values in drawers and detail views (choices spec §2), and the
 * −/+ steppers of the numeric settings (numeric steppers brief).
 *
 * The dialog's data-state (classes/output/panel.php) is the single source of every enabled feature's value, built-in
 * default, tile, kind, lock and options, with the CSS custom properties each option sets. A numeric feature also has a
 * stepper: its step, range, encoding scale, CSS property and units, from which any value in its range is labelled
 * and applied here as the server does (classes/feature/numeric.php).
 *
 * @module     local_accessibility/panel
 * @copyright  2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {configure, save, reset} from 'local_accessibility/store';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {check, closeDrawer, hideView, initKeys, isDisabled, loadFaces, replaceDrawer, showView, tileOf,
    toggleDrawer} from 'local_accessibility/choices';
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

// Spacing summary string per member, as SPACING_PARTS in classes/output/panel.php.
const SPACING_PARTS = {lineheight: 'spacing_line', letterspacing: 'spacing_letter', wordspacing: 'spacing_word'};

let panel;
let opener = null;
// Feature id => {value, default, tile, kind, locked, options: [{value, label, css}]}.
let state = {};
// Tile id => the number of its latest value-text update, so a slower earlier one cannot win.
const tileUpdates = {};
// Steppers save this long after the last change, so holding a button does not send a request per step.
const SAVE_DELAY = 500;
// Holding a stepper button repeats after this long, then at the second interval.
const REPEAT_DELAY = 400;
const REPEAT_INTERVAL = 80;
// Feature id => the value the server has, once the feature has been changed in this page.
const saved = {};
// Feature id => {id, resolve, pending} of a save waiting for SAVE_DELAY.
const timers = {};
// Feature id => the chain of its saves, so they reach the server in order.
const chains = {};
// The stepper button being held: {button, timer}.
let held = null;

/**
 * The option of a feature with a value.
 *
 * @param {string} feature
 * @param {string} value
 * @returns {Object|undefined} {value, label, css}
 */
const optionOf = (feature, value) => state[feature]?.options.find((o) => o.value === value);

/**
 * Whether a value is an integer string in a numeric feature's encoding.
 *
 * @param {string} value
 * @returns {boolean}
 */
const isNumber = (value) => /^-?\d+$/.test(value);

/**
 * Hundredths as a short decimal with '.' (CSS) or the user's decimal separator: 180 => 1.8, -5 => -0.05.
 *
 * @param {number} n
 * @param {string} sep
 * @returns {string}
 */
const hundredths = (n, sep = '.') => (n / 100).toFixed(2).replace(/\.?0+$/, '').replace('.', sep);

/**
 * A numeric value in the user's units, as typed in its field: "1.8", "0.12", "150", "60".
 *
 * @param {string} feature
 * @param {string|number} value an integer in the feature's encoding
 * @returns {string}
 */
const userNumber = (feature, value) => {
    const m = state[feature].stepper;
    return m.scale === 100 ? hundredths(Number(value), m.decsep) : String(Number(value));
};

/**
 * Whether a feature accepts a value: one of its options, or, for a numeric feature, an integer in its range
 * (as classes/feature/numeric.php validate(); the server checks again).
 *
 * @param {string} feature
 * @param {string} value
 * @returns {boolean}
 */
const isValid = (feature, value) => {
    const m = state[feature]?.stepper;
    if (!m || value === state[feature].default) {
        return !!optionOf(feature, value);
    }
    return /^-?\d{1,4}$/.test(value) && String(Number(value)) === value && Number(value) >= m.min && Number(value) <= m.max;
};

/**
 * The label of a value: its option's, or a numeric value in the user's units ("1.8", "150%", "60 characters").
 *
 * @param {string} feature
 * @param {string} value
 * @returns {Promise<string>}
 */
const labelOf = async(feature, value) => {
    const m = state[feature]?.stepper;
    if (!m || !isNumber(value)) {
        return optionOf(feature, value)?.label || value;
    }
    const n = userNumber(feature, value);
    return m.labelstring ? getString(m.labelstring, 'local_accessibility', n) : n;
};

/**
 * The CSS custom properties a value sets: its option's, or a numeric feature's property built from the integer.
 *
 * @param {string} feature
 * @param {string} value
 * @returns {Object} property => value
 */
const cssOf = (feature, value) => {
    const m = state[feature]?.stepper;
    if (!m) {
        return optionOf(feature, value)?.css || {};
    }
    if (value === state[feature].default || !isValid(feature, value)) {
        return {};
    }
    const n = Number(value);
    return {[m.property]: (m.scale === 100 ? hundredths(n) : String(n)) + m.cssunit};
};

/**
 * Put a feature value on the page: the data-a11y-* attribute on <html> (absent for the default) and the CSS custom
 * properties of its option (those of every other option removed), then tell the other modules.
 *
 * @param {string} feature
 * @param {string} value
 */
const apply = (feature, value) => {
    const s = state[feature];
    const html = document.documentElement;
    if (value === s.default) {
        html.removeAttribute('data-a11y-' + feature);
    } else {
        html.setAttribute('data-a11y-' + feature, value);
    }
    s.options.forEach((o) => Object.keys(o.css || {}).forEach((p) => html.style.removeProperty(p)));
    if (s.stepper) {
        html.style.removeProperty(s.stepper.property);
    }
    Object.entries(cssOf(feature, value)).forEach(([p, v]) => html.style.setProperty(p, v));
    document.dispatchEvent(new CustomEvent('local_accessibility:changed', {detail: {feature, value}}));
    // A new text size or spacing reflows the grid.
    replaceDrawer(panel);
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
    // Each opening starts on the grid: a view or drawer left open is closed, and colour.js closes its editor, before
    // focus is placed.
    hideView(panel, false);
    closeDrawer(panel, false);
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
 * Write a message into the dialog's polite live region.
 *
 * @param {string} message
 */
const announce = (message) => {
    panel.querySelector('.la-live').textContent = message;
};

/**
 * A feature's name: its tile's, or its group's in a shared view (Line height in the Spacing view).
 *
 * @param {string} feature
 * @returns {string}
 */
const featureLabel = (feature) => {
    const group = panel.querySelector('#la-group-' + feature);
    if (group) {
        return group.textContent.trim();
    }
    return tileOf(panel, state[feature].tile)?.querySelector('.la-label')?.textContent.trim() || feature;
};

/**
 * Show a tile's current value in words, its accessible name and whether it differs from the default. The spacing
 * tile summarises its non-default members as the server does (classes/output/panel.php spacing_summary()).
 *
 * @param {string} tileid
 */
const updateTile = async(tileid) => {
    const tile = tileOf(panel, tileid);
    if (!tile) {
        return;
    }
    const mine = (tileUpdates[tileid] || 0) + 1;
    tileUpdates[tileid] = mine;
    const members = Object.keys(state).filter((f) => state[f].tile === tileid);
    const changed = members.filter((f) => state[f].value !== state[f].default);
    let text;
    if (tileid === 'spacing') {
        const parts = await Promise.all(changed.filter((f) => SPACING_PARTS[f]).map(async(f) =>
            getString(SPACING_PARTS[f], 'local_accessibility', await labelOf(f, state[f].value))));
        text = parts.length ? parts.join(' · ') : await getString('sitedefault', 'local_accessibility');
    } else {
        text = await labelOf(members[0], state[members[0]].value);
    }
    if (mine !== tileUpdates[tileid]) {
        return;
    }
    tile.querySelector('.la-value').textContent = text;
    tile.setAttribute('aria-label', tile.querySelector('.la-label').textContent.trim() + ', ' + text);
    if (changed.length) {
        tile.setAttribute('data-active', 'true');
    } else {
        tile.removeAttribute('data-active');
    }
};

/**
 * Show a numeric feature's value in its stepper: the field (empty, with the default's label, at a non-numeric
 * default), which buttons can act, and the preview.
 *
 * @param {string} feature
 * @param {string} value
 */
const showStepper = async(feature, value) => {
    const s = state[feature];
    const stepper = panel.querySelector('.la-stepper[data-feature="' + feature + '"]');
    if (!s.stepper || !stepper) {
        return;
    }
    const m = s.stepper;
    const number = isNumber(value);
    const field = stepper.querySelector('.la-stepvalue');
    field.value = number ? userNumber(feature, value) : '';
    field.placeholder = number ? '' : await labelOf(feature, value);
    const down = stepper.querySelector('[data-action="stepdown"]');
    const up = stepper.querySelector('[data-action="stepup"]');
    if (!s.locked) {
        // Unlimited, the minus button is never disabled: pressing it at the minimum keeps the minimum.
        down.setAttribute('aria-disabled', m.nonnegative && number && Number(value) <= m.min ? 'true' : 'false');
        up.setAttribute('aria-disabled', (number ? Number(value) >= m.max : m.defaultismax) ? 'true' : 'false');
    }
    const sample = stepper.querySelector('.la-stepsample');
    if (sample) {
        const property = {lineheight: 'line-height', letterspacing: 'letter-spacing', wordspacing: 'word-spacing'}[feature];
        sample.style.setProperty(property, Object.values(cssOf(feature, value))[0] || 'normal');
    }
    const fill = stepper.querySelector('.la-barfill');
    if (fill) {
        fill.style.inlineSize = (number ? Math.max(1, Math.min(100, Math.round(Number(value) / 90 * 100))) : 100) + '%';
    }
};

/**
 * Make a value current: the page, the options that offer it, its stepper and the tile.
 *
 * @param {string} feature
 * @param {string} value
 */
const setValue = (feature, value) => {
    state[feature].value = value;
    apply(feature, value);
    check(panel, feature, value);
    showStepper(feature, value).catch(Notification.exception);
    updateTile(state[feature].tile).catch(Notification.exception);
};

/**
 * Save a feature's current value unless the server has it already, and announce it. When the save fails (offline,
 * a lock set after the page loaded) the value the server has is put back and announced as not saved. The live region
 * is used rather than an error modal, which would open behind this dialog.
 *
 * @param {string} feature
 */
const commit = async(feature) => {
    const value = state[feature].value;
    if (value !== saved[feature]) {
        try {
            await save(feature, value);
            saved[feature] = value;
        } catch (error) {
            if (state[feature].value === value) {
                setValue(feature, saved[feature]);
            }
            announce(await getString('savefailed', 'local_accessibility', featureLabel(feature)));
            return;
        }
    }
    let label = await labelOf(feature, value);
    const unit = state[feature].stepper?.unit;
    if (unit && isNumber(value) && !state[feature].stepper.labelstring) {
        label += ' ' + unit;
    }
    announce(await getString('settingchanged', 'local_accessibility', {feature: featureLabel(feature), value: label}));
};

/**
 * Choose a value. The change shows at once and is saved, after a delay for the steppers so that a held button saves
 * once; it is announced once saved. A locked feature, or a value the feature does not accept, does nothing.
 *
 * @param {string} feature
 * @param {string} value
 * @param {number} delay milliseconds to wait for further changes before saving
 * @returns {Promise} resolved once this change is saved, or replaced by a later one
 */
const choose = (feature, value, delay = 0) => {
    const s = state[feature];
    if (!s || s.locked || !isValid(feature, value)) {
        return Promise.resolve();
    }
    if (!(feature in saved)) {
        saved[feature] = s.value;
    }
    setValue(feature, value);
    const previous = timers[feature];
    if (previous) {
        clearTimeout(previous.id);
        previous.resolve();
    }
    // Behat and other waiters see the change as pending until it is saved.
    const pending = previous ? previous.pending : new Pending('local_accessibility/panel:choose');
    return new Promise((resolve) => {
        timers[feature] = {pending, resolve, id: setTimeout(() => {
            delete timers[feature];
            chains[feature] = (chains[feature] || Promise.resolve()).then(() => commit(feature))
                .catch(Notification.exception)
                .finally(() => {
                    pending.resolve();
                    resolve();
                });
        }, delay)};
    });
};

/**
 * The value one press of a stepper button gives: a step from the current number, or from the feature's starting
 * number at a non-numeric default, kept inside the range. Null when + is pressed at a default that is already the
 * widest (full width).
 *
 * @param {string} feature
 * @param {number} direction 1 or -1
 * @returns {string|null}
 */
const stepped = (feature, direction) => {
    const s = state[feature];
    const m = s.stepper;
    const number = isNumber(s.value);
    if (!number && direction > 0 && m.defaultismax) {
        return null;
    }
    const from = number ? Number(s.value) : m.start;
    return String(Math.min(m.max, Math.max(m.min, from + direction * m.step)));
};

/**
 * Press a stepper button once: step, or put the default back.
 *
 * @param {HTMLElement} button
 * @returns {boolean} whether the button could act
 */
const pressStep = (button) => {
    const feature = button.dataset.feature;
    if (!state[feature]?.stepper || isDisabled(button) || state[feature].locked) {
        return false;
    }
    if (button.dataset.action === 'stepdefault') {
        choose(feature, state[feature].default).catch(Notification.exception);
        return true;
    }
    const value = stepped(feature, button.dataset.action === 'stepup' ? 1 : -1);
    if (value === null) {
        return false;
    }
    choose(feature, value, SAVE_DELAY).catch(Notification.exception);
    return true;
};

/**
 * Stop repeating a held stepper button.
 */
const release = () => {
    if (held) {
        clearTimeout(held.timer);
        held = null;
    }
};

/**
 * Apply the number typed in a stepper's field: rounded to the feature's encoding, or the default when the field is
 * empty. An entry that is not a number in the range is put back and announced.
 *
 * @param {HTMLInputElement} field
 */
const applyField = async(field) => {
    const feature = field.dataset.feature;
    const s = state[feature];
    if (!s?.stepper || s.locked) {
        return;
    }
    const m = s.stepper;
    const typed = field.value.trim();
    // Accept a typographic minus, the user's decimal separator or a point, and a trailing unit such as % or em.
    const text = typed.replace(/\u2212/g, '-').replace(/\s+/g, '').split(m.decsep).join('.')
        .replace(/(\d)[^\d.]+$/, '$1');
    let value = null;
    if (typed === '') {
        value = s.default;
    } else if (/^-?(\d+\.?\d*|\.\d+)$/.test(text)) {
        value = String(Math.round(Number(text) * m.scale) || 0);
    }
    if (value === s.value) {
        await showStepper(feature, value);
        return;
    }
    if (value === null || !isValid(feature, value)) {
        await showStepper(feature, s.value);
        announce(await getString('stepperinvalid', 'local_accessibility', {value: typed, feature: featureLabel(feature),
            min: userNumber(feature, m.min), max: userNumber(feature, m.max)}));
        return;
    }
    await choose(feature, value);
};

/**
 * The steppers: buttons step on a click or a key and repeat while held; the field applies a typed number on Enter or
 * when it loses focus.
 */
const initSteppers = () => {
    panel.addEventListener('pointerdown', (e) => {
        const button = e.target.closest('.la-step[data-action]');
        if (!button || e.button !== 0) {
            return;
        }
        release();
        // The click that follows this press is not a second step.
        button.dataset.pressed = '1';
        if (!pressStep(button)) {
            return;
        }
        const repeat = (wait) => {
            held = {button, timer: setTimeout(() => {
                if (pressStep(button)) {
                    repeat(REPEAT_INTERVAL);
                } else {
                    release();
                }
            }, wait)};
        };
        repeat(REPEAT_DELAY);
    });
    document.addEventListener('pointerup', release);
    document.addEventListener('pointercancel', release);
    panel.addEventListener('pointerout', (e) => {
        if (held && e.target.closest('.la-step') === held.button && !held.button.contains(e.relatedTarget)) {
            release();
        }
    });
    panel.addEventListener('keydown', (e) => {
        const step = e.target.closest('.la-step');
        if (step) {
            // A key press is never the click of an earlier pointer press.
            delete step.dataset.pressed;
        }
        const field = e.target.closest('.la-stepvalue');
        if (field && e.key === 'Enter') {
            e.preventDefault();
            applyField(field).catch(Notification.exception);
        }
    });
    panel.addEventListener('change', (e) => {
        const field = e.target.closest('.la-stepvalue');
        if (field) {
            applyField(field).catch(Notification.exception);
        }
    });
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
 * Mark the colour, saturation and links controls as controlled by the device while forced colours are active, and
 * show "Controlled by your device" as the colour tile's value.
 */
const initForcedColours = async() => {
    const forced = window.matchMedia('(forced-colors: active)');
    const tile = tileOf(panel, 'colour');
    const value = tile?.querySelector('.la-value');
    let original = '';
    let showing = false;
    const message = await getString('controlledbydevice', 'local_accessibility');
    const mark = () => {
        setForced(panel.querySelectorAll('.la-swatch, [data-action="customcolours"], .la-tile[data-tile="colour"], '
            + '.la-tile[data-tile="saturation"], .la-tile[data-tile="links"], .la-option[data-feature="saturation"], '
            + '.la-option[data-feature="links"]'), forced.matches);
        if (value && forced.matches && !showing) {
            original = value.textContent;
            value.textContent = message;
            showing = true;
        } else if (value && !forced.matches && showing) {
            value.textContent = original;
            showing = false;
        }
        if (value) {
            tile.setAttribute('aria-label', tile.querySelector('.la-label').textContent.trim() + ', ' + value.textContent);
        }
    };
    mark();
    forced.addEventListener('change', mark);
};

/**
 * Whether the user may change a feature from the panel: it is enabled (it has state) and not locked.
 *
 * @param {string} feature
 * @returns {boolean}
 */
const isChangeable = (feature) => !!state[feature] && !state[feature].locked;

/**
 * Apply a profile: confirm if the user has changed anything they could change, save every value, then reload.
 *
 * @param {HTMLElement} profile the profile button
 */
const applyProfile = async(profile) => {
    const changed = Object.keys(state).some((f) => isChangeable(f) && state[f].value !== state[f].default);
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
    try {
        state = JSON.parse(panel.dataset.state || '{}');
    } catch (e) {
        state = {};
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
            // A locked tile still opens its drawer or view, which show the options and their lock.
            const tile = e.target.closest('.la-tile[data-tile]');
            if (tile) {
                if (tile.dataset.kind === 'drawer') {
                    toggleDrawer(panel, tile);
                } else {
                    const view = showView(panel, tile);
                    if (view && tile.dataset.tile === 'font') {
                        loadFaces(view);
                    }
                }
                return;
            }
            const step = e.target.closest('.la-step[data-action], .la-stepdefault');
            if (step) {
                if (step.dataset.pressed) {
                    // Already stepped on pointerdown.
                    delete step.dataset.pressed;
                } else {
                    pressStep(step);
                }
                return;
            }
            // Colour swatches are local_accessibility/colour's: choosing a scheme reloads the page.
            const option = e.target.closest('.la-option[data-feature]:not(.la-swatch)');
            if (option) {
                if (!isDisabled(option)) {
                    await choose(option.dataset.feature, option.dataset.value);
                }
                return;
            }
            if (e.target.closest('[data-action="back"]')) {
                hideView(panel, true);
            } else if (e.target.closest('[data-action="close"]')) {
                close();
            } else if (e.target.closest('[data-action="reset"]')) {
                await reset();
                window.location.reload();
            }
        } catch (error) {
            Notification.exception(error);
        }
    });
    initKeys(panel);
    panel.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            // One step back at a time: a view to the grid, an open drawer to its tile, then the dialog closes.
            e.preventDefault();
            if (!hideView(panel, true) && !closeDrawer(panel, true)) {
                close();
            }
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
    window.addEventListener('resize', () => replaceDrawer(panel));
    initSteppers();
    initColour(panel);
    initForcedColours().catch(Notification.exception);
    initGuide();
    initImages();
    initMotion();
    initRead(panel);
    // Last, so every listener above is attached before the first await.
    initDeviceSettings(config).catch(Notification.exception);
};
