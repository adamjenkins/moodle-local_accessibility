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
 * Client mirror of classes/colour/* (contrast, oklch, adjust, scheme::custom) for the live preview.
 * The server re-validates every save (spec §8); keep every constant and step table identical to the PHP.
 *
 * Fixture (checked against the PHP): ratio('#ffffff', '#3a6ea5') -> 5.31;
 * custom('#3a6ea5', '#ffffff', '#ffe08a', false).textratio >= 7.
 *
 * @module     local_accessibility/contrast
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Enhanced contrast (WCAG 1.4.6, AAA): contrast::AAA. */
export const AAA = 7;
/** Below this a custom scheme is refused: contrast::FLOOR. */
export const FLOOR = 1.5;
/** Luminance above which a page counts as light: adjust::LIGHT_LUMINANCE. */
const LIGHT_LUMINANCE = 0.18;
/** OKLCH lightness steps per tier on a light page: adjust::LIGHT_STEPS. */
const LIGHT_STEPS = {surface1: -0.03, surface2: -0.06, field: 0, hover: -0.09};
/** Steps on a dark page: adjust::DARK_STEPS. */
const DARK_STEPS = {surface1: 0.07, surface2: 0.12, field: 0.10, hover: 0.16};
/** Minimum OKLCH lightness per tier on a dark page: adjust::DARK_FLOORS. */
const DARK_FLOORS = {surface1: 0.20, surface2: 0.28, field: 0.24, hover: 0.34};

/**
 * Normalise a hex colour to lowercase #rrggbb (contrast::normalise).
 *
 * @param {string} hex
 * @returns {string|null} null when not a 3- or 6-digit hex colour
 */
export const normalise = (hex) => {
    if (typeof hex !== 'string') {
        return null;
    }
    const m = /^#([0-9a-f]{3})$/i.exec(hex);
    if (m) {
        hex = '#' + m[1].split('').map((c) => c + c).join('');
    }
    return /^#[0-9a-f]{6}$/i.test(hex) ? hex.toLowerCase() : null;
};

/**
 * Linear sRGB channels of a normalised colour.
 *
 * @param {string} hex
 * @returns {number[]}
 */
const lin = (hex) => [1, 3, 5].map((i) => {
    const c = parseInt(hex.substr(i, 2), 16) / 255;
    return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
});

/**
 * WCAG 2 relative luminance (contrast::luminance).
 *
 * @param {string} hex normalised colour
 * @returns {number} 0..1
 */
export const luminance = (hex) => {
    const [r, g, b] = lin(hex);
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

/**
 * Contrast ratio between two colours (contrast::ratio).
 *
 * @param {string} a normalised colour
 * @param {string} b normalised colour
 * @returns {number} 1..21
 */
export const ratio = (a, b) => {
    const la = luminance(a);
    const lb = luminance(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
};

/**
 * Lowest ratio of a foreground against several backgrounds (contrast::worst).
 *
 * @param {string} fg
 * @param {string[]|Object} bgs an array, or a ramp object (its values are used)
 * @returns {number}
 */
export const worst = (fg, bgs) => Math.min(...(Array.isArray(bgs) ? bgs : Object.values(bgs)).map((bg) => ratio(fg, bg)));

/**
 * Real cube root (oklch::cbrt).
 *
 * @param {number} x
 * @returns {number}
 */
const cbrt = (x) => (x < 0 ? -Math.pow(-x, 1 / 3) : Math.pow(x, 1 / 3));

/**
 * Hex to [L, C, H] (oklch::from_hex).
 *
 * @param {string} hex normalised colour
 * @returns {number[]}
 */
export const fromHex = (hex) => {
    const [r, g, b] = lin(hex);
    const l = cbrt(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b);
    const m = cbrt(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b);
    const s = cbrt(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b);
    const L = 0.2104542553 * l + 0.7936177850 * m - 0.0040720468 * s;
    const A = 1.9779984951 * l - 2.4285922050 * m + 0.4505937099 * s;
    const B = 0.0259040371 * l + 0.7827717662 * m - 0.8086757660 * s;
    const h = Math.atan2(B, A) * 180 / Math.PI;
    return [L, Math.sqrt(A * A + B * B), h < 0 ? h + 360 : h];
};

/**
 * OKLCH to linear sRGB (oklch::lch_to_linear).
 *
 * @param {number} L
 * @param {number} C
 * @param {number} H
 * @returns {number[]}
 */
const lchToLinear = (L, C, H) => {
    const a = C * Math.cos(H * Math.PI / 180);
    const b = C * Math.sin(H * Math.PI / 180);
    const l = Math.pow(L + 0.3963377774 * a + 0.2158037573 * b, 3);
    const m = Math.pow(L - 0.1055613458 * a - 0.0638541728 * b, 3);
    const s = Math.pow(L - 0.0894841775 * a - 1.2914855480 * b, 3);
    return [
        4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
        -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
        -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s,
    ];
};

/**
 * [L, C, H] to hex, reducing chroma until inside sRGB (oklch::to_hex).
 *
 * @param {number[]} lch
 * @returns {string} normalised colour
 */
export const toHex = ([L, C, H]) => {
    L = Math.max(0, Math.min(1, L));
    let rgb;
    for (let i = 0; i < 50; i++) {
        rgb = lchToLinear(L, C, H);
        if (Math.min(...rgb) >= -0.0001 && Math.max(...rgb) <= 1.0001) {
            break;
        }
        C *= 0.9;
    }
    return '#' + rgb.map((v) => {
        v = Math.max(0, Math.min(1, v));
        v = v <= 0.0031308 ? 12.92 * v : 1.055 * Math.pow(v, 1 / 2.4) - 0.055;
        return Math.round(v * 255).toString(16).padStart(2, '0');
    }).join('');
};

/**
 * Light or dark, from the page colour (adjust::mode).
 *
 * @param {string} page
 * @returns {string} 'light' or 'dark'
 */
export const mode = (page) => (luminance(page) > LIGHT_LUMINANCE ? 'light' : 'dark');

/**
 * Surface ramp from a page colour, keeping hue (adjust::ramp).
 *
 * @param {string} page normalised colour
 * @returns {Object} page, surface1, surface2, field, hover
 */
export const ramp = (page) => {
    const [L, C, H] = fromHex(page);
    const light = mode(page) === 'light';
    const steps = light ? LIGHT_STEPS : DARK_STEPS;
    const out = {page};
    Object.entries(steps).forEach(([tier, step]) => {
        let target = L + step;
        if (!light) {
            target = Math.max(target, DARK_FLOORS[tier]);
        }
        out[tier] = step === 0 ? page : toHex([target, C, H]);
    });
    return out;
};

/**
 * Smallest lightness change that lets a foreground reach a target on every shade (adjust::towards).
 *
 * @param {string} fg
 * @param {Object} r ramp
 * @param {number} target
 * @returns {string|null}
 */
export const towards = (fg, r, target) => {
    if (worst(fg, r) >= target) {
        return fg;
    }
    const [L, C, H] = fromHex(fg);
    const end = luminance(fg) < luminance(r.page) ? 0 : 1;
    if (worst(toHex([end, C, H]), r) < target) {
        return null;
    }
    let lo = L;
    let hi = end;
    for (let i = 0; i < 30; i++) {
        const mid = (lo + hi) / 2;
        if (worst(toHex([mid, C, H]), r) >= target) {
            hi = mid;
        } else {
            lo = mid;
        }
    }
    return toHex([hi, C, H]);
};

/**
 * Black or white, whichever contrasts more with the ramp (scheme::best_bw).
 *
 * @param {Object} r
 * @returns {string}
 */
const bestBw = (r) => (worst('#000000', r) >= worst('#ffffff', r) ? '#000000' : '#ffffff');

/**
 * Build a custom scheme, auto-adjusting to 7:1 unless exact (scheme::custom).
 *
 * @param {string} bg
 * @param {string} text
 * @param {string} link
 * @param {boolean} exact
 * @returns {Object} {ramp, mode, text, link, exact, textratio, linkratio, adjusted}
 * @throws {Error} 'hex' for an invalid colour, 'floor' for exact colours below 1.5:1
 */
export const custom = (bg, text, link, exact) => {
    const input = [normalise(bg), normalise(text), normalise(link)];
    [bg, text, link] = input;
    if (bg === null || text === null || link === null) {
        throw new Error('hex');
    }
    let r = ramp(bg);
    if (exact) {
        const tr = worst(text, r);
        const lr = worst(link, r);
        if (tr < FLOOR || lr < FLOOR) {
            throw new Error('floor');
        }
        return {ramp: r, mode: mode(bg), text, link, exact: true, textratio: tr, linkratio: lr, adjusted: false};
    }
    let t = towards(text, r, AAA);
    if (t === null) {
        // Move the page away from the text until the text's hue can pass (spec §6.2 step 2).
        const [L, C, H] = fromHex(bg);
        const step = luminance(text) > luminance(bg) ? -0.01 : 0.01;
        for (let i = 1; i <= 100 && t === null; i++) {
            r = ramp(toHex([L + step * i, C, H]));
            t = towards(text, r, AAA);
        }
        if (t === null) {
            t = bestBw(r);
        }
    }
    let l = towards(link, r, AAA);
    if (l === null) {
        l = bestBw(r);
    }
    return {ramp: r, mode: mode(r.page), text: t, link: l, exact: false, textratio: worst(t, r),
        linkratio: worst(l, r), adjusted: r.page !== bg || t !== text || l !== link};
};
