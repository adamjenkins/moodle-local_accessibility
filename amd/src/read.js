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
 * Read aloud with the browser's own on-device voices; nothing leaves the browser (spec §5).
 * Esc is deliberately not handled here: it closes the panel (spec §4), and Stop is a button.
 *
 * @module     local_accessibility/read
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

const CHUNK = 200;
let token = 0;
let chunks = [];
let index = 0;

/**
 * Split text into sentence-sized chunks of at most ~200 characters.
 *
 * @param {string} text
 * @returns {string[]}
 */
const split = (text) => {
    const sentences = text.replace(/\s+/g, ' ').match(/[^.!?。！？]+[.!?。！？]*\s*/g) || [];
    const out = [];
    sentences.forEach((s) => {
        while (s.length > CHUNK) {
            const cut = s.lastIndexOf(' ', CHUNK) > 0 ? s.lastIndexOf(' ', CHUNK) : CHUNK;
            out.push(s.slice(0, cut));
            s = s.slice(cut);
        }
        if (s.trim()) {
            out.push(s);
        }
    });
    return out;
};

/**
 * On-device voice for the page language.
 *
 * @returns {SpeechSynthesisVoice|null}
 */
const voice = () => {
    const lang = (document.documentElement.lang || 'en').toLowerCase();
    const voices = window.speechSynthesis.getVoices().filter((v) => v.localService);
    return voices.find((v) => v.lang.toLowerCase() === lang)
        || voices.find((v) => v.lang.toLowerCase().startsWith(lang.split('-')[0])) || null;
};

/**
 * Speak from the current index; a new token cancels older callbacks (cancel() fires onerror).
 *
 * @param {number} rate
 * @param {SpeechSynthesisVoice} chosen an on-device voice
 */
const speak = (rate, chosen) => {
    const mine = ++token;
    const next = () => {
        if (mine !== token || index >= chunks.length) {
            return;
        }
        const u = new SpeechSynthesisUtterance(chunks[index]);
        u.voice = chosen;
        u.lang = chosen.lang;
        u.rate = rate;
        u.onend = () => {
            if (mine === token) {
                index++;
                next();
            }
        };
        u.onerror = () => {
            if (mine === token) {
                index++;
                next();
            }
        };
        window.speechSynthesis.speak(u);
    };
    next();
};

/**
 * What to read: the selection, or the main region.
 *
 * @returns {string}
 */
const source = () => {
    const sel = window.getSelection().toString().trim();
    if (sel) {
        return sel;
    }
    const main = document.getElementById('region-main') || document.querySelector('main') || document.body;
    return main.innerText;
};

/**
 * Initialise.
 *
 * @param {HTMLElement} panel the dialog. The Read tile and drawer are looked up inside it only: admin/features.php
 *     also has a table row with data-feature="read", earlier in the page.
 */
export const init = (panel) => {
    const bar = document.querySelector('.la-readbar');
    const tile = panel.querySelector('.la-tile[data-tile="read"]');
    const drawer = panel.querySelector('#la-drawer-read');
    if (!('speechSynthesis' in window)) {
        tile?.remove();
        drawer?.remove();
        return;
    }
    const message = bar?.querySelector('[data-read="message"]');
    let on = document.documentElement.getAttribute('data-a11y-read') === 'on';
    // Not known until the voice list has loaded; until then a saved Read=on shows the bar.
    let novoice = false;
    const halt = () => {
        token++;
        window.speechSynthesis.cancel();
    };
    // Without an on-device voice the tile is hidden, so the bar is hidden too: a saved Read=on would otherwise leave
    // a bar on screen that nothing in the panel can turn off.
    const show = () => {
        const visible = on && !novoice;
        if (bar) {
            bar.hidden = !visible;
        }
        if (!visible) {
            halt();
        }
    };
    const check = () => {
        novoice = !voice();
        if (tile) {
            tile.hidden = novoice;
        }
        if (drawer && novoice && !drawer.hidden) {
            drawer.hidden = true;
            tile?.setAttribute('aria-expanded', 'false');
        }
        show();
    };
    window.speechSynthesis.addEventListener('voiceschanged', check);
    window.setTimeout(check, 1500);
    show();
    document.addEventListener('local_accessibility:changed', (e) => {
        if (e.detail.feature === 'read') {
            on = e.detail.value === 'on';
            show();
        }
    });
    window.addEventListener('pagehide', halt);
    bar?.addEventListener('click', async(e) => {
        const action = e.target.closest('[data-read]')?.dataset.read;
        if (!['play', 'stop', 'restart'].includes(action)) {
            return;
        }
        try {
            halt();
            message.textContent = '';
            if (action === 'stop') {
                return;
            }
            const chosen = voice();
            if (!chosen) {
                message.textContent = await getString('readnovoice', 'local_accessibility');
                return;
            }
            if (action === 'play') {
                chunks = split(source());
                index = 0;
            } else {
                index = 0;
            }
            speak(parseFloat(bar.querySelector('[data-read="rate"]').value), chosen);
        } catch (error) {
            Notification.exception(error);
        }
    });
};
