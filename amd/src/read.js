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
 */
export const init = () => {
    const bar = document.querySelector('.la-readbar');
    const tile = document.querySelector('[data-feature="read"]');
    if (!('speechSynthesis' in window)) {
        tile?.remove();
        return;
    }
    const message = bar?.querySelector('[data-read="message"]');
    const check = () => {
        if (tile) {
            tile.hidden = !voice();
        }
    };
    window.speechSynthesis.addEventListener('voiceschanged', check);
    window.setTimeout(check, 1500);
    const halt = () => {
        token++;
        window.speechSynthesis.cancel();
    };
    const show = (on) => {
        if (bar) {
            bar.hidden = !on;
        }
        if (!on) {
            halt();
        }
    };
    show(document.documentElement.getAttribute('data-a11y-read') === 'on');
    document.addEventListener('local_accessibility:changed', (e) => {
        if (e.detail.feature === 'read') {
            show(e.detail.value === 'on');
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
