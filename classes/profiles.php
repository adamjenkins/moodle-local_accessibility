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

use local_accessibility\feature\registry;

/**
 * One-click bundles of feature values (spec §7.3).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profiles {
    /** @var array Shipped profiles (spec §7.3); each name is a lang string identifier. */
    public const DEFAULTS = [
        'dyslexia' => ['name' => 'profile_dyslexia',
            'values' => ['font' => 'dyslexic', 'lineheight' => '180', 'letterspacing' => '16', 'wordspacing' => '24',
                'colour' => 'cream', 'guide' => 'ruler']],
        'lowvision' => ['name' => 'profile_lowvision',
            'values' => ['size' => '180', 'colour' => 'highcontrast', 'links' => 'outline', 'focus' => 'ring']],
        'focus' => ['name' => 'profile_focus', 'values' => ['motion' => 'on', 'narrow' => '70']],
        'seizuresafe' => ['name' => 'profile_seizuresafe', 'values' => ['motion' => 'on', 'saturation' => 'low']],
    ];

    /**
     * Validated profiles: admin config if set, else the shipped defaults. Invalid values are dropped.
     *
     * @return array<string, array{name: string, values: array<string, string>}>
     */
    public static function all(): array {
        $raw = json_decode((string) get_config('local_accessibility', 'profiles'), true);
        $source = is_array($raw) && $raw ? $raw : self::DEFAULTS;
        $out = [];
        foreach ($source as $id => $p) {
            $id = clean_param((string) $id, PARAM_ALPHANUMEXT);
            if ($id === '' || !is_array($p) || !is_array($p['values'] ?? null)) {
                continue;
            }
            $values = [];
            foreach ($p['values'] as $fid => $v) {
                $f = registry::get((string) $fid);
                if ($f && is_string($v) && $f->validate($v)) {
                    $values[(string) $fid] = $v;
                }
            }
            $name = is_string($p['name'] ?? null) ? $p['name'] : $id;
            $isdefault = isset(self::DEFAULTS[$id]) && $name === self::DEFAULTS[$id]['name'];
            $out[$id] = [
                'name' => $isdefault ? get_string($name, 'local_accessibility') : clean_param($name, PARAM_TEXT),
                'values' => $values,
            ];
        }
        return $out;
    }

    /**
     * Template context: profiles reduced to the values the user can actually change (not locked, not disabled).
     * A profile left with nothing to apply is omitted.
     *
     * @return array
     */
    public static function for_template(): array {
        $out = [];
        foreach (self::all() as $id => $p) {
            // Applying a profile saves each value, and saving a locked or disabled feature fails.
            $values = array_filter(
                $p['values'],
                fn($v, $fid) => preferences::is_enabled((string) $fid) && !preferences::is_locked((string) $fid),
                ARRAY_FILTER_USE_BOTH
            );
            if ($values) {
                $out[] = ['id' => $id, 'name' => $p['name'], 'values' => json_encode($values)];
            }
        }
        return $out;
    }
}
