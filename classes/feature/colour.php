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
 * Colour scheme (spec §3). Values are preset ids, 'custom', or site preset ids.
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
     * Allowed values in display order; the first is off/default.
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
     * Options are chosen in a detail view with named swatches and the custom editor (spec §3 "B").
     *
     * @return string
     */
    public function kind(): string {
        return 'detail';
    }

    /**
     * Options preview the scheme as a swatch.
     *
     * @return string|null
     */
    public function preview(): ?string {
        return 'swatch';
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
     * Label of one value: the admin's name for a site preset, else the built-in string.
     *
     * @param string $value
     * @return string
     */
    public function value_label(string $value): string {
        if (str_starts_with($value, 'site_')) {
            return self::parse_site_presets()['names'][$value]
                ?? get_string('sitepreset', 'local_accessibility', (int) substr($value, 5) + 1);
        }
        return parent::value_label($value);
    }

    /**
     * Admin-defined presets, keyed site_<n>.
     *
     * @return array<string, scheme>
     */
    public static function site_presets(): array {
        return self::parse_site_presets()['schemes'];
    }

    /**
     * Display names of the admin-defined presets, keyed like site_presets(): the admin's name, or "Site scheme <n>"
     * when a preset has none.
     *
     * @return array<string, string>
     */
    public static function site_preset_names(): array {
        return self::parse_site_presets()['names'];
    }

    /**
     * Parse the sitepresets setting: valid presets and their names, both keyed site_<n>. Memoised per setting value
     * for the request, as the panel asks once per swatch.
     *
     * @return array{schemes: array<string, scheme>, names: array<string, string>}
     */
    private static function parse_site_presets(): array {
        static $memo = [];
        $raw = (string) get_config('local_accessibility', 'sitepresets');
        if (!isset($memo[$raw])) {
            $memo = [$raw => self::parse_site_presets_json($raw)];
        }
        return $memo[$raw];
    }

    /**
     * Parse a sitepresets setting value.
     *
     * @param string $raw JSON list of {name, bg, text, link}
     * @return array{schemes: array<string, scheme>, names: array<string, string>}
     */
    private static function parse_site_presets_json(string $raw): array {
        $out = ['schemes' => [], 'names' => []];
        $list = json_decode($raw, true);
        if (!is_array($list)) {
            return $out;
        }
        foreach ($list as $i => $p) {
            if (!is_int($i) || !is_array($p)) {
                continue;
            }
            $bg = $p['bg'] ?? null;
            $text = $p['text'] ?? null;
            $link = $p['link'] ?? null;
            if (!is_string($bg) || !is_string($text) || !is_string($link)) {
                continue;
            }
            try {
                $out['schemes']['site_' . $i] = scheme::custom($bg, $text, $link, true, 'site_' . $i);
            } catch (\invalid_parameter_exception $e) {
                continue;
            }
            $name = is_string($p['name'] ?? null) ? trim(clean_param($p['name'], PARAM_TEXT)) : '';
            $out['names']['site_' . $i] = $name !== '' ? $name : get_string('sitepreset', 'local_accessibility', $i + 1);
        }
        return $out;
    }
}
