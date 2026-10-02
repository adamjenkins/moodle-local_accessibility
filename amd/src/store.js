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
 * Saves preferences: web service for users, cookie for guests (spec §7.2).
 *
 * @module     local_accessibility/store
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {call} from 'core/ajax';

let config = {guest: false, cookie: 'local_accessibility', cookieattributes: ''};

/**
 * Set the runtime configuration.
 *
 * @param {Object} c
 */
export const configure = (c) => {
    config = Object.assign(config, c);
};

/**
 * The runtime configuration.
 *
 * @returns {Object}
 */
export const getConfig = () => config;

/**
 * Read the guest cookie.
 *
 * @returns {Object}
 */
const readCookie = () => {
    const raw = document.cookie.split('; ').find((p) => p.startsWith(config.cookie + '='));
    try {
        return raw ? JSON.parse(decodeURIComponent(raw.slice(config.cookie.length + 1))) : {};
    } catch (e) {
        return {};
    }
};

/**
 * Write the guest cookie.
 *
 * @param {Object} data
 */
const writeCookie = (data) => {
    document.cookie = config.cookie + '=' + encodeURIComponent(JSON.stringify(data)) + config.cookieattributes;
};

/**
 * Save one feature value.
 *
 * @param {string} feature
 * @param {string} value
 * @returns {Promise}
 */
export const save = (feature, value) => {
    if (config.guest) {
        const data = readCookie();
        data[feature] = value;
        writeCookie(data);
        return Promise.resolve();
    }
    return call([{methodname: 'local_accessibility_save_preference', args: {feature, value}}])[0];
};

/**
 * Save a custom scheme. Guests store the raw colours; the server validates them on the next page load.
 *
 * @param {Object} scheme {bg, text, link, exact}
 * @returns {Promise<Object>}
 */
export const saveCustom = (scheme) => {
    if (config.guest) {
        const data = readCookie();
        data.custom = scheme;
        data.colour = 'custom';
        writeCookie(data);
        return Promise.resolve(scheme);
    }
    return call([{methodname: 'local_accessibility_save_custom_scheme', args: scheme}])[0];
};

/**
 * Reset everything.
 *
 * @returns {Promise}
 */
export const reset = () => {
    if (config.guest) {
        const wasdark = readCookie().colour === 'dark';
        writeCookie({});
        if (wasdark && config.coredark) {
            // The plugin's Dark wrote core's cookie (colour.js): set it back to light, as a non-dark swatch does,
            // so core's dark mode does not outlive the reset.
            document.cookie = 'theme_boost_colourmode=light' + config.cookieattributes;
        }
        return Promise.resolve();
    }
    return call([{methodname: 'local_accessibility_reset_preferences', args: {}}])[0];
};
