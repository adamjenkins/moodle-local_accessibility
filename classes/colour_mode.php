<?php
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

namespace local_accessibility;

/**
 * Bridge to core's Boost colour mode.
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class colour_mode {
    /**
     * Whether core's dark colour mode can render the Dark preset (spec section 6.5).
     *
     * True only when core has the feature (Moodle 5.3+), the site has enabled it and the theme rendering the page
     * is Boost or a Boost child.
     *
     * @return bool
     */
    public static function core_dark_available(): bool {
        if (!class_exists(\theme_boost\colour_mode::class) || !\theme_boost\colour_mode::is_enabled()) {
            return false;
        }
        return self::rendering_theme_is_boost_based();
    }

    /**
     * Whether the theme that renders (or, in a web service, would render) the page is Boost-based.
     *
     * A page has $PAGE->theme (it honours course, category, user and device themes). A web service runs without a
     * page theme, and initialising one there is not safe, so it uses the user's effective theme: their own theme when
     * the site allows user themes, else the site theme. Course and category themes cannot be known in a service.
     * Memoised per theme name for the request.
     *
     * @return bool
     */
    private static function rendering_theme_is_boost_based(): bool {
        global $CFG, $PAGE, $USER;
        if (!(defined('AJAX_SCRIPT') && AJAX_SCRIPT) && !(defined('WS_SERVER') && WS_SERVER)) {
            return self::is_boost_based($PAGE->theme);
        }
        $name = !empty($CFG->allowuserthemes) && !empty($USER->theme) ? $USER->theme : ($CFG->theme ?? '');
        static $memo = [];
        if (!isset($memo[$name])) {
            $memo[$name] = $name !== '' && self::is_boost_based(\theme_config::load($name));
        }
        return $memo[$name];
    }

    /**
     * Whether a theme is Boost or declares Boost as a parent.
     *
     * @param object $theme a theme_config (anything with name and parents)
     * @return bool
     */
    public static function is_boost_based(object $theme): bool {
        return $theme->name === 'boost' || in_array('boost', $theme->parents ?? [], true);
    }

    /**
     * Whether core is already rendering dark for this browser or user, so the plugin's own Dark scheme can stand down.
     *
     * Logged-in users: core's preference is 'dark'. Guests: core's cookie is 'dark'. A user who chose Dark while core
     * colour modes were off has neither, and keeps the plugin's own Dark until they choose again.
     *
     * @return bool
     */
    public static function core_is_dark(): bool {
        if (!self::core_dark_available()) {
            return false;
        }
        $mode = preferences::uses_cookie()
            ? ($_COOKIE[\theme_boost\colour_mode::PREFERENCE] ?? null)
            : get_user_preferences(\theme_boost\colour_mode::PREFERENCE);
        return $mode === 'dark';
    }

    /**
     * Mirror the colour choice into core's preference: dark when the value is dark, otherwise unset so the
     * site default applies. A no-op when core dark is unavailable.
     *
     * @param string $colourvalue the colour feature value just saved
     * @return void
     */
    public static function sync(string $colourvalue): void {
        if (!self::core_dark_available()) {
            return;
        }
        if ($colourvalue === 'dark') {
            set_user_preference(\theme_boost\colour_mode::PREFERENCE, 'dark');
        } else {
            unset_user_preference(\theme_boost\colour_mode::PREFERENCE);
        }
    }

    /**
     * Cookie attributes matching the site's cookie path, domain and secure settings.
     *
     * @return string beginning with ';'
     */
    public static function cookie_attributes(): string {
        global $CFG;
        // Same fallback as core\session\manager when sessioncookiepath is empty: the wwwroot path.
        $path = ($CFG->sessioncookiepath ?? '') ?: (parse_url($CFG->wwwroot, PHP_URL_PATH) ?: '') . '/';
        $a = ['Path=' . $path, 'SameSite=Lax', 'Max-Age=31536000'];
        if (!empty($CFG->sessioncookiedomain)) {
            $a[] = 'Domain=' . $CFG->sessioncookiedomain;
        }
        if (is_moodle_cookie_secure()) {
            $a[] = 'Secure';
        }
        return '; ' . implode('; ', $a);
    }
}
