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

namespace local_accessibility\feature;

use local_accessibility\colour\scheme;

/**
 * Colour scheme (spec §6). Values are preset ids, 'custom', or site preset ids.
 *
 * @package    local_accessibility
 * @copyright  2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class colour extends base {
    /**
     * Stable id, stored in preferences.
     *
     * @return string
     */
    public function id(): string {
        return 'colour';
    }

    /**
     * Allowed values in cycle order; the first is off/default.
     *
     * @return string[]
     */
    public function values(): array {
        return array_merge(['default'], array_keys(scheme::presets()), ['custom']);
    }

    /**
     * Font Awesome icon name for the tile.
     *
     * @return string
     */
    public function icon(): string {
        return 'fa-palette';
    }

    /**
     * Also accepts site preset ids defined by the admin (Task 16).
     *
     * @param string $value
     * @return bool
     */
    public function validate(string $value): bool {
        return parent::validate($value) || array_key_exists($value, self::site_presets());
    }

    /**
     * Admin-defined presets, keyed site_<n>.
     *
     * @return array<string, scheme>
     */
    public static function site_presets(): array {
        $raw = (string) get_config('local_accessibility', 'sitepresets');
        $out = [];
        foreach (json_decode($raw, true) ?: [] as $i => $p) {
            try {
                $out['site_' . (int) $i] = scheme::custom(
                    (string) ($p['bg'] ?? ''),
                    (string) ($p['text'] ?? ''),
                    (string) ($p['link'] ?? ''),
                    true,
                    'site_' . (int) $i
                );
            } catch (\invalid_parameter_exception $e) {
                continue;
            }
        }
        return $out;
    }
}
