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

namespace local_accessibility\local;

/**
 * Maps values used on 3.0 development sites to the 3.0 values (spec §6, plan D2).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacy {
    /** @var array<string, array<string, string>> Old spacing value => the three spacing features (spec §6). */
    private const SPACING = [
        'wcag' => ['lineheight' => '150', 'letterspacing' => '12', 'wordspacing' => '16'],
        'extra' => ['lineheight' => '180', 'letterspacing' => '16', 'wordspacing' => '24'],
    ];

    /** @var array<string, array<string, string>> Feature id => old value => new value (spec §6, D2). */
    private const RENAMED = [
        'align' => ['on' => 'left'],
        'links' => ['on' => 'outline'],
        'images' => ['on' => 'hide'],
        'focus' => ['cursor' => 'ring'],
        'size' => ['175' => '180'],
    ];

    /**
     * Map feature values (feature id => value) to the current values.
     *
     * The old 'spacing' key never survives: 'wcag' and 'extra' become the three spacing features (a value already
     * given for one of them wins), anything else is dropped. 'focus=cursor' becomes the strong ring plus the large
     * cursor unless a cursor value is given. Unknown keys, non-string values and current values pass through, and
     * mapping twice gives the same result as mapping once.
     *
     * @param array $values feature id => value, e.g. a decoded guest cookie or a profile's values
     * @return array
     */
    public static function map_values(array $values): array {
        $out = [];
        foreach ($values as $id => $value) {
            if ($id === 'spacing') {
                foreach (self::SPACING[is_string($value) ? $value : ''] ?? [] as $fid => $v) {
                    if (!array_key_exists($fid, $values)) {
                        $out[$fid] = $v;
                    }
                }
                continue;
            }
            if (is_string($value) && isset(self::RENAMED[$id][$value])) {
                $out[$id] = self::RENAMED[$id][$value];
                if ($id === 'focus' && !array_key_exists('cursor', $values)) {
                    $out['cursor'] = 'large';
                }
                continue;
            }
            $out[$id] = $value;
        }
        return $out;
    }
}
