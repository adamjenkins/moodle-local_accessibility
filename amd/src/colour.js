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
import {save, saveCustom} from 'local_accessibility/store';
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
    const applyButton = editor.querySelector('[data-action="applycolours"]');
    const previewEl = editor.querySelector('.la-preview');
    let sequence = 0;

    /**
     * Recompute the scheme from the inputs and update the preview, ratio, warning and Apply state.
     *
     * @returns {Promise<Object|null>} the scheme, or null when refused
     */
    const preview = async() => {
        const mine = ++sequence;
        const exact = field('exact').checked;
        const values = [field('bg').value, field('text').value, field('link').value];
        let s = null;
        let refused = null;
        try {
            s = custom(...values, exact);
        } catch (e) {
            const bg = normalise(values[0]);
            const r = bg ? ramp(bg) : null;
            const text = normalise(values[1]);
            const link = normalise(values[2]);
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
     * Back to the grid, focus on the pen button.
     */
    const closeEditor = () => {
        editor.hidden = true;
        grid.hidden = false;
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
        // The spec's confirmation step: a native, keyboard- and screen-reader-accessible modal.
        // eslint-disable-next-line no-alert
        if (!protest.hidden && !window.confirm(protest.textContent)) {
            return;
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
        applyScheme(panel, final);
        const label = panel.querySelector('.la-colourlabel');
        if (label) {
            label.textContent = await getString('feature_colour_custom', 'local_accessibility');
        }
        closeEditor();
    };

    const onInput = () => preview().catch(Notification.exception);
    editor.addEventListener('input', onInput);
    editor.addEventListener('change', onInput);

    // Esc in the editor goes back to the grid instead of closing the dialog.
    editor.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !editor.hidden) {
            e.preventDefault();
            e.stopPropagation();
            closeEditor();
        }
    });

    // Each time the dialog opens it starts on the grid.
    document.addEventListener('local_accessibility:open', () => {
        editor.hidden = true;
        grid.hidden = false;
    });

    panel.addEventListener('click', async(e) => {
        try {
            const swatch = e.target.closest('.la-swatch[data-scheme]');
            if (swatch) {
                if (swatch.getAttribute('aria-disabled') === 'true') {
                    return;
                }
                panel.querySelectorAll('.la-swatch[data-scheme]').forEach((b) => b.setAttribute('aria-pressed', 'false'));
                swatch.setAttribute('aria-pressed', 'true');
                await save('colour', swatch.dataset.scheme);
                window.location.reload();
                return;
            }
            const pen = e.target.closest('[data-action="customcolours"]');
            if (pen) {
                if (pen.getAttribute('aria-disabled') === 'true') {
                    return;
                }
                grid.hidden = true;
                editor.hidden = false;
                field('bg').focus();
                await preview();
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
