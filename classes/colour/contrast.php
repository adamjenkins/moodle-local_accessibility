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

namespace local_accessibility\colour;

/**
 * WCAG 2 relative luminance and contrast ratio.
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class contrast {
    /** @var float Enhanced contrast (WCAG 1.4.6, AAA). */
    public const AAA = 7.0;
    /** @var float Below this a custom scheme is refused (spec §6.2). */
    public const FLOOR = 1.5;

    /**
     * Normalise a hex colour to lowercase #rrggbb.
     *
     * @param string $hex
     * @return string|null null when the input is not a 3- or 6-digit hex colour
     */
    public static function normalise(string $hex): ?string {
        if (preg_match('/^#([0-9a-f]{3})$/i', $hex, $m)) {
            $hex = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }
        return preg_match('/^#[0-9a-f]{6}$/i', $hex) ? strtolower($hex) : null;
    }

    /**
     * Relative luminance as defined by WCAG 2.
     *
     * @param string $hex normalised colour
     * @return float 0..1
     */
    public static function luminance(string $hex): float {
        $l = [];
        foreach ([1, 3, 5] as $i) {
            $c = hexdec(substr($hex, $i, 2)) / 255;
            $l[] = $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }
        return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
    }

    /**
     * Contrast ratio between two colours.
     *
     * @param string $a normalised colour
     * @param string $b normalised colour
     * @return float 1..21
     */
    public static function ratio(string $a, string $b): float {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Lowest ratio of a foreground against several backgrounds.
     *
     * @param string $fg normalised colour
     * @param string[] $backgrounds normalised colours
     * @return float
     */
    public static function worst(string $fg, array $backgrounds): float {
        return min(array_map(fn(string $bg): float => self::ratio($fg, $bg), $backgrounds));
    }
}
