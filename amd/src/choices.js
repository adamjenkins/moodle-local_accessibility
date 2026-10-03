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
 * Drawers, radio groups and detail views of the accessibility dialog (choices spec §2).
 *
 * Arrow keys, Home and End move focus between a group's options only; Enter, Space or a click chooses (choices plan
 * D11). Choosing itself is local_accessibility/panel's job: Enter and Space click the focused option, so a pointer
 * and a key take the same path.
 *
 * @module     local_accessibility/choices
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const OPTION = '[role="radio"]';
const GROUP = '[role="radiogroup"]';

/**
 * The options of a radio group, in order.
 *
 * @param {Element} group
 * @returns {HTMLElement[]}
 */
const optionsOf = (group) => [...group.querySelectorAll(OPTION)];

/**
 * Make one option its group's single Tab stop.
 *
 * @param {Element} group
 * @param {Element|undefined} target the option to reach with Tab; the first option when undefined
 */
export const rove = (group, target) => {
    const all = optionsOf(group);
    const stop = target && all.includes(target) ? target : all[0];
    all.forEach((o) => o.setAttribute('tabindex', o === stop ? '0' : '-1'));
};

/**
 * Show which value of a feature is chosen in every group that offers it, and move each group's Tab stop to it.
 * A group that does not offer the value (a size off the quick picks) has nothing checked and its first option as
 * its Tab stop.
 *
 * @param {Element} root
 * @param {string} feature
 * @param {string} value
 */
export const check = (root, feature, value) => {
    const groups = new Set();
    root.querySelectorAll(OPTION).forEach((o) => {
        if (o.dataset.feature !== feature) {
            return;
        }
        o.setAttribute('aria-checked', o.dataset.value === value ? 'true' : 'false');
        const group = o.closest(GROUP);
        if (group) {
            groups.add(group);
        }
    });
    groups.forEach((g) => rove(g, g.querySelector(OPTION + '[aria-checked="true"]') || undefined));
};

/**
 * Whether an option may not be chosen: it, or its group, is aria-disabled (an admin lock, or forced colours).
 *
 * @param {Element} option
 * @returns {boolean}
 */
export const isDisabled = (option) => option.getAttribute('aria-disabled') === 'true'
    || option.closest(GROUP)?.getAttribute('aria-disabled') === 'true';

/**
 * Move focus inside radio groups with the arrow keys, Home and End, and choose with Enter or Space.
 *
 * @param {HTMLElement} panel
 */
export const initKeys = (panel) => {
    panel.addEventListener('keydown', (e) => {
        if (e.altKey || e.ctrlKey || e.metaKey || !(e.target instanceof Element)) {
            return;
        }
        const option = e.target.closest(OPTION);
        const group = option?.closest(GROUP);
        if (!group) {
            return;
        }
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            option.click();
            return;
        }
        const all = optionsOf(group).filter((o) => o.getClientRects().length > 0);
        const at = all.indexOf(option);
        // Left and right follow the reading direction.
        const rtl = getComputedStyle(group).direction === 'rtl';
        const step = {ArrowDown: 1, ArrowUp: -1, ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1}[e.key];
        let next;
        if (step) {
            next = all[(at + step + all.length) % all.length];
        } else if (e.key === 'Home') {
            next = all[0];
        } else if (e.key === 'End') {
            next = all[all.length - 1];
        }
        if (!next) {
            return;
        }
        e.preventDefault();
        rove(group, next);
        next.focus();
    });
};

/**
 * The tile of a drawer or a view.
 *
 * @param {HTMLElement} panel
 * @param {string} id tile id
 * @returns {HTMLElement|null}
 */
export const tileOf = (panel, id) => panel.querySelector('.la-tile[data-tile="' + id + '"]');

/**
 * The open drawer, if any.
 *
 * @param {HTMLElement} panel
 * @returns {HTMLElement|null}
 */
const openDrawer = (panel) => panel.querySelector('.la-drawer:not([hidden])');

/**
 * Close the open drawer.
 *
 * @param {HTMLElement} panel
 * @param {boolean} focusTile whether focus returns to the drawer's tile
 * @returns {boolean} whether a drawer was open
 */
export const closeDrawer = (panel, focusTile) => {
    const drawer = openDrawer(panel);
    if (!drawer) {
        return false;
    }
    drawer.hidden = true;
    const tile = tileOf(panel, drawer.dataset.drawer);
    if (tile) {
        tile.setAttribute('aria-expanded', 'false');
        if (focusTile) {
            tile.focus();
        }
    }
    return true;
};

/**
 * The last tile of a tile's grid row, counted from the grid's columns rather than measured, so an open drawer (which
 * ends a row early) does not change the answer.
 *
 * @param {HTMLElement} panel
 * @param {HTMLElement} tile
 * @returns {HTMLElement}
 */
const rowEnd = (panel, tile) => {
    const grid = panel.querySelector('.la-grid');
    const tiles = [...grid.querySelectorAll(':scope > .la-tile')].filter((t) => !t.hidden && t.getClientRects().length > 0);
    const columns = Math.max(getComputedStyle(grid).gridTemplateColumns.split(' ').filter((c) => c !== '').length, 1);
    const at = tiles.indexOf(tile);
    if (at < 0) {
        return tile;
    }
    return tiles[Math.min((Math.floor(at / columns) + 1) * columns, tiles.length) - 1];
};

/**
 * Put a drawer straight after the last tile of its tile's grid row, so it spans the grid under that row. A drawer
 * already there is not moved (moving a node takes focus from inside it); one that moves keeps the focus it had.
 *
 * @param {HTMLElement} panel
 * @param {HTMLElement} drawer
 * @param {HTMLElement} tile
 */
const place = (panel, drawer, tile) => {
    const end = rowEnd(panel, tile);
    if (end.nextElementSibling === drawer) {
        return;
    }
    const focused = drawer.contains(document.activeElement) ? document.activeElement : null;
    end.after(drawer);
    if (focused) {
        focused.focus();
    }
};

/**
 * Open a drawer tile's drawer, or close it when it is open. Only one drawer is open at a time.
 *
 * @param {HTMLElement} panel
 * @param {HTMLElement} tile
 */
export const toggleDrawer = (panel, tile) => {
    const drawer = panel.querySelector('#la-drawer-' + tile.dataset.tile);
    if (!drawer) {
        return;
    }
    const wasOpen = !drawer.hidden;
    closeDrawer(panel, false);
    if (wasOpen) {
        tile.focus();
        return;
    }
    place(panel, drawer, tile);
    drawer.hidden = false;
    tile.setAttribute('aria-expanded', 'true');
    const target = drawer.querySelector(OPTION + '[tabindex="0"]') || drawer.querySelector(OPTION);
    if (target) {
        target.focus();
    }
};

/**
 * Keep an open drawer under its tile's row when the grid reflows (a resize or a new text size).
 *
 * @param {HTMLElement} panel
 */
export const replaceDrawer = (panel) => {
    const drawer = openDrawer(panel);
    const tile = drawer ? tileOf(panel, drawer.dataset.drawer) : null;
    if (drawer && tile) {
        place(panel, drawer, tile);
    }
};

/**
 * The open detail view, if any.
 *
 * @param {HTMLElement} panel
 * @returns {HTMLElement|null}
 */
export const openView = (panel) => panel.querySelector('.la-view:not([hidden])');

/**
 * Show or hide the grid and what sits with it (profiles, the device note).
 *
 * @param {HTMLElement} panel
 * @param {boolean} hidden
 */
const setGridHidden = (panel, hidden) => {
    panel.querySelectorAll('.la-grid, .la-profiles, .la-devicenote').forEach((el) => {
        el.hidden = hidden;
    });
};

/**
 * Replace the grid with a detail tile's view and put focus on the view's heading.
 *
 * @param {HTMLElement} panel
 * @param {HTMLElement} tile
 * @returns {HTMLElement|null} the view
 */
export const showView = (panel, tile) => {
    const view = panel.querySelector('.la-view[data-view="' + tile.dataset.tile + '"]');
    if (!view) {
        return null;
    }
    closeDrawer(panel, false);
    setGridHidden(panel, true);
    view.hidden = false;
    view.querySelector('.la-viewtitle')?.focus();
    return view;
};

/**
 * Go back from the open view to the grid, with focus on the view's tile.
 *
 * @param {HTMLElement} panel
 * @param {boolean} focusTile whether focus returns to the tile
 * @returns {boolean} whether a view was open
 */
export const hideView = (panel, focusTile) => {
    const view = openView(panel);
    if (!view) {
        return false;
    }
    view.hidden = true;
    setGridHidden(panel, false);
    if (focusTile) {
        tileOf(panel, view.dataset.view)?.focus();
    }
    return true;
};

/**
 * Load an uploaded font's faces for its preview in the font view, once. Failures are ignored: the preview then shows
 * the fallback font.
 *
 * @param {HTMLElement} view
 */
export const loadFaces = (view) => {
    if (view.dataset.facesLoaded || !('FontFace' in window) || !document.fonts) {
        return;
    }
    view.dataset.facesLoaded = '1';
    view.querySelectorAll('[data-faces]').forEach((option) => {
        let faces = [];
        try {
            faces = JSON.parse(option.dataset.faces);
        } catch (e) {
            return;
        }
        faces.forEach((f) => {
            try {
                const face = new FontFace('local_accessibility_' + option.dataset.value,
                    'url(' + JSON.stringify(String(f.url)) + ') format(' + JSON.stringify(String(f.format)) + ')',
                    {weight: String(f.weight), style: f.style === 'italic' ? 'italic' : 'normal'});
                document.fonts.add(face);
                face.load().catch(() => null);
            } catch (e) {
                // A face the browser refuses leaves the fallback font in the preview.
                return;
            }
        });
    });
};
