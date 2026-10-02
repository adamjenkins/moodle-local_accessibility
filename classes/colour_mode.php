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
 * Bridge to core's Boost colour mode (filled in by Task 12).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class colour_mode {
    /**
     * Whether core's dark colour mode can render the Dark preset.
     *
     * @return bool
     */
    public static function core_dark_available(): bool {
        return false;
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
