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
 * Let authored text with a fixed inline font size follow the user's text size (issue #2).
 *
 * The text size scales the root font size (styles.css section 2), which moves rem, em and % sizes but not absolute
 * units or keywords. An inline font-size in pt, px, pc, in, cm, mm or Q (TinyMCE and tiny_fontsize write pt) becomes
 * calc(<value> * var(--a11y-size, 100) / 100): unchanged at 100%, and it follows later size changes without another
 * pass. A keyword (small, x-large...) becomes its rem equivalent. Nothing runs until a size other than the default
 * is chosen; from then on, content added to the page later is handled too. Editable regions are left alone so the
 * rewritten value can never be saved into content.
 *
 * @module     local_accessibility/inlinesize
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const SELECTOR = '[style*="font-size" i]';
const SKIP = '[contenteditable], .local-accessibility-panel, .local-accessibility-launcher, .la-readbar';
const ABSOLUTE = /^(\d*\.?\d+)(px|pt|pc|in|cm|mm|q)$/i;
/** @type {Object<string, string>} The browser's keyword sizes against its 16px medium, in rem. */
const KEYWORDS = {'xx-small': '0.5625rem', 'x-small': '0.625rem', 'small': '0.8125rem', 'medium': '1rem',
    'large': '1.125rem', 'x-large': '1.5rem', 'xx-large': '2rem', 'xxx-large': '3rem'};

let started = false;

/**
 * Rewrite one element's inline font size, if it is fixed.
 *
 * @param {HTMLElement} el
 */
const rewrite = (el) => {
    const value = el.style.fontSize.trim().toLowerCase();
    let scaled = KEYWORDS[value];
    if (!scaled && ABSOLUTE.test(value)) {
        scaled = 'calc(' + value + ' * var(--a11y-size, 100) / 100)';
    }
    if (scaled && !el.closest(SKIP)) {
        el.style.setProperty('font-size', scaled, el.style.getPropertyPriority('font-size'));
    }
};

/**
 * Rewrite an element and its descendants.
 *
 * @param {Element} root
 */
const scan = (root) => {
    if (root.matches(SELECTOR)) {
        rewrite(root);
    }
    root.querySelectorAll(SELECTOR).forEach(rewrite);
};

/**
 * Rewrite the page, then everything added to it later.
 */
const start = () => {
    if (started || !document.documentElement.hasAttribute('data-a11y-size')) {
        return;
    }
    started = true;
    scan(document.body);
    new MutationObserver((records) => records.forEach((r) => r.addedNodes.forEach((n) => {
        if (n.nodeType === Node.ELEMENT_NODE) {
            scan(n);
        }
    }))).observe(document.body, {childList: true, subtree: true});
};

/**
 * Initialise.
 */
export const init = () => {
    start();
    document.addEventListener('local_accessibility:changed', (e) => {
        if (e.detail.feature === 'size') {
            start();
        }
    });
};
