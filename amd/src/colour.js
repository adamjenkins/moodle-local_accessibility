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
 * Swatches and the custom colour editor (spec §6).
 *
 * @module     local_accessibility/colour
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {custom, normalise, ramp, worst, AAA} from 'local_accessibility/contrast';
import {save, saveCustom, getConfig} from 'local_accessibility/store';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

const PROPS = ['page', 'surface1', 'surface2', 'field', 'hover'];

/**
 * Whether a scheme is exact and under 7:1.
 *
 * @param {Object} s result of custom()
 * @returns {boolean}
 */
const isLow = (s) => s.exact && (s.textratio < AAA || s.linkratio < AAA);

/**
 * Put a custom scheme on <html> without a reload.
 *
 * @param {HTMLElement} panel
 * @param {Object} s result of custom() from contrast.js, including mode
 */
const applyScheme = (panel, s) => {
    const html = document.documentElement;
    PROPS.forEach((p) => html.style.setProperty('--a11y-' + p, s.ramp[p]));
    html.style.setProperty('--a11y-text', s.text);
    html.style.setProperty('--a11y-link', s.link);
    html.setAttribute('data-a11y-colour', 'custom');
    html.setAttribute('data-bs-theme', s.mode);
    html.toggleAttribute('data-a11y-lowcontrast', isLow(s));
    // Bootstrap's [hidden] rule is !important, so the warning icon is driven by the attribute (R18).
    panel.querySelectorAll('.la-warn').forEach((w) => {
        w.hidden = !isLow(s);
    });
    panel.querySelectorAll('.la-swatch[data-scheme]').forEach((b) => b.setAttribute('aria-pressed', 'false'));
    document.dispatchEvent(new CustomEvent('local_accessibility:changed', {detail: {feature: 'colour', value: 'custom'}}));
};

/**
 * Initialise the colour tile and editor.
 *
 * @param {HTMLElement} panel
 */
export const init = (panel) => {
    const editor = panel.querySelector('.la-editor');
    const grid = panel.querySelector('.la-grid');
    if (!editor || !grid) {
        return;
    }
    const field = (n) => editor.querySelector(`[name="${n}"]`);
    const ratioEl = editor.querySelector('.la-ratio');
    const protest = editor.querySelector('.la-protest');
    const announcer = editor.querySelector('.la-announce');
    const applyButton = editor.querySelector('[data-action="applycolours"]');
    const previewEl = editor.querySelector('.la-preview');
    const ANNOUNCE_DELAY = 500;
    let sequence = 0;
    let previewed = null;
    let lastPreview = Promise.resolve(null);
    let announceTimer = null;

    /**
     * The editor's current inputs.
     *
     * @returns {Object} {bg, text, link, exact}
     */
    const inputs = () => ({bg: field('bg').value, text: field('text').value, link: field('link').value,
        exact: field('exact').checked});

    // The last applied colours: server-rendered at first, then whatever applyScheme last put on the page.
    let applied = inputs();

    /**
     * Put the inputs back to the last applied colours (abandoned edits are discarded).
     */
    const restoreInputs = () => {
        field('bg').value = applied.bg;
        field('text').value = applied.text;
        field('link').value = applied.link;
        field('exact').checked = applied.exact;
        previewed = null;
    };

    /**
     * The work of preview(): compute the scheme and update the visible state.
     *
     * @returns {Promise<Object|null>}
     */
    const render = async() => {
        const mine = ++sequence;
        const v = inputs();
        previewed = JSON.stringify(v);
        let s = null;
        let refused = null;
        try {
            s = custom(v.bg, v.text, v.link, v.exact);
        } catch (e) {
            const bg = normalise(v.bg);
            const r = bg ? ramp(bg) : null;
            const text = normalise(v.text);
            const link = normalise(v.link);
            refused = r && text && link ? Math.min(worst(text, r), worst(link, r)).toFixed(2) : '?';
        }
        const nums = s ? {text: s.textratio.toFixed(2), link: s.linkratio.toFixed(2)} : null;
        const [ratioText, warning, adjusted] = await Promise.all([
            s ? getString('contrastok', 'local_accessibility', nums)
                : getString('contrastrefused', 'local_accessibility', refused),
            s && isLow(s) ? getString('contrastwarning', 'local_accessibility', nums) : Promise.resolve(''),
            s && s.adjusted ? getString('contrastadjusted', 'local_accessibility', [s.ramp.page, s.text, s.link].join(' / '))
                : Promise.resolve(''),
        ]);
        if (mine !== sequence) {
            // A newer input event has already taken over.
            return s;
        }
        applyButton.disabled = s === null;
        protest.hidden = warning === '';
        protest.textContent = warning;
        ratioEl.textContent = ratioText + (adjusted ? ' · ' + adjusted : '');
        if (s) {
            previewEl.style.background = s.ramp.page;
            previewEl.style.color = s.text;
            previewEl.querySelector('u').style.color = s.link;
        }
        return s;
    };

    /**
     * Recompute the scheme from the inputs and update the VISIBLE preview, ratio, warning and Apply state.
     * Nothing here is announced: the ratio text is not a live region (see announce()).
     *
     * @returns {Promise<Object|null>} the scheme, or null when refused
     */
    const preview = () => {
        lastPreview = render();
        return lastPreview;
    };

    /**
     * Copy the current ratio (and warning) into the polite live region, once the user has settled.
     *
     * @param {number} delay ms; 0 announces now
     */
    const announce = (delay) => {
        clearTimeout(announceTimer);
        announceTimer = setTimeout(() => {
            if (!editor.hidden) {
                announcer.textContent = ratioEl.textContent + (protest.hidden ? '' : ' ' + protest.textContent);
            }
        }, delay);
    };

    /**
     * Back to the grid, focus on the pen button. Unapplied edits are discarded.
     */
    const closeEditor = () => {
        clearTimeout(announceTimer);
        editor.hidden = true;
        grid.hidden = false;
        restoreInputs();
        const pen = panel.querySelector('[data-action="customcolours"]');
        if (pen) {
            pen.focus();
        }
    };

    /**
     * Save the editor's colours (after confirmation when under 7:1) and apply them live.
     */
    const applyColours = async() => {
        const s = await preview();
        if (!s) {
            return;
        }
        if (isLow(s)) {
            const warning = await getString('contrastwarning', 'local_accessibility',
                {text: s.textratio.toFixed(2), link: s.linkratio.toFixed(2)});
            // The spec's confirmation step: a native, keyboard- and screen-reader-accessible modal.
            // eslint-disable-next-line no-alert
            if (!window.confirm(warning)) {
                return;
            }
        }
        const saved = await saveCustom({bg: field('bg').value, text: field('text').value,
            link: field('link').value, exact: field('exact').checked});
        let final = s;
        // A server result (it carries ratios) is authoritative; a guest's cookie save echoes the input.
        if (saved && saved.bg && typeof saved.textratio !== 'undefined') {
            try {
                final = Object.assign(custom(saved.bg, saved.text, saved.link, true), {exact: !!saved.exact});
            } catch (e) {
                final = s;
            }
        }
        const config = getConfig();
        if (config.guest && config.coredark) {
            // As the swatches do: guests have no preference to sync, so core's colour-mode cookie follows the
            // scheme's own mode. A dark cookie left from an earlier Dark would otherwise make core's head script
            // render a light custom scheme with data-bs-theme=dark.
            document.cookie = 'theme_boost_colourmode=' + (final.mode === 'dark' ? 'dark' : 'light')
                + config.cookieattributes;
        }
        applyScheme(panel, final);
        // Matches what the server renders into the inputs on the next page load.
        applied = {bg: final.ramp.page, text: final.text, link: final.link, exact: final.exact};
        const label = panel.querySelector('.la-colourlabel');
        if (label) {
            label.textContent = await getString('feature_colour_custom', 'local_accessibility');
        }
        closeEditor();
    };

    // Visual preview on every input (continuous while a picker is dragged); the announcement waits for a pause.
    editor.addEventListener('input', () => {
        preview().then(() => announce(ANNOUNCE_DELAY)).catch(Notification.exception);
    });
    // A committed change: preview only if no input event already covered these values, then announce.
    editor.addEventListener('change', () => {
        const pending = previewed === JSON.stringify(inputs()) ? lastPreview : preview();
        pending.then(() => announce(0)).catch(Notification.exception);
    });

    // Esc anywhere in the dialog (head and profiles included) closes the editor first, not the dialog.
    // Capture phase on the panel runs before panel.js's bubbling Esc handler, and stopPropagation keeps it from it.
    panel.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !editor.hidden) {
            e.preventDefault();
            e.stopPropagation();
            closeEditor();
        }
    }, true);

    // Each time the dialog opens it starts on the grid, with the applied colours.
    document.addEventListener('local_accessibility:open', () => {
        clearTimeout(announceTimer);
        editor.hidden = true;
        grid.hidden = false;
        restoreInputs();
    });

    panel.addEventListener('click', async(e) => {
        try {
            const swatch = e.target.closest('.la-swatch[data-scheme]');
            if (swatch) {
                if (swatch.getAttribute('aria-disabled') === 'true') {
                    return;
                }
                const pressed = [...panel.querySelectorAll('.la-swatch[data-scheme]')].map((b) => {
                    const was = b.getAttribute('aria-pressed');
                    b.setAttribute('aria-pressed', b === swatch ? 'true' : 'false');
                    return [b, was];
                });
                try {
                    await save('colour', swatch.dataset.scheme);
                } catch (error) {
                    // Put the pressed swatch back and say so in the dialog's live region (an error modal would
                    // open behind the dialog).
                    pressed.forEach(([b, was]) => b.setAttribute('aria-pressed', was));
                    const live = panel.querySelector('.la-live');
                    if (live) {
                        live.textContent = await getString('savefailed', 'local_accessibility',
                            swatch.closest('.la-colour')?.getAttribute('aria-label') || '');
                    }
                    return;
                }
                const config = getConfig();
                if (config.guest && config.coredark) {
                    // Guests have no preference to sync: write core's cookie so core's dark mode follows.
                    // A non-dark choice writes 'light', which undoes an earlier dark.
                    document.cookie = 'theme_boost_colourmode=' + (swatch.dataset.scheme === 'dark' ? 'dark' : 'light')
                        + config.cookieattributes;
                }
                window.location.reload();
                return;
            }
            const pen = e.target.closest('[data-action="customcolours"]');
            if (pen) {
                if (pen.getAttribute('aria-disabled') === 'true') {
                    return;
                }
                restoreInputs();
                grid.hidden = true;
                editor.hidden = false;
                field('bg').focus();
                await preview();
                announce(0);
            } else if (e.target.closest('[data-action="cancelcolours"]')) {
                closeEditor();
            } else if (e.target.closest('[data-action="applycolours"]')) {
                await applyColours();
            }
        } catch (error) {
            Notification.exception(error);
        }
    });
};
