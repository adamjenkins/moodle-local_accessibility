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
 * Conversion between sRGB hex and OKLCH, used to change lightness while keeping hue.
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class oklch {
    /**
     * Hex to [L, C, H].
     *
     * @param string $hex normalised colour
     * @return float[] L 0..1, C >= 0, H degrees 0..360
     */
    public static function from_hex(string $hex): array {
        $rgb = [];
        foreach ([1, 3, 5] as $i) {
            $c = hexdec(substr($hex, $i, 2)) / 255;
            $rgb[] = $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }
        [$r, $g, $b] = $rgb;
        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;
        [$l, $m, $s] = [self::cbrt($l), self::cbrt($m), self::cbrt($s)];
        $ll = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $aa = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $bb = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;
        $h = rad2deg(atan2($bb, $aa));
        return [$ll, sqrt($aa * $aa + $bb * $bb), $h < 0 ? $h + 360 : $h];
    }

    /**
     * [L, C, H] to hex, reducing chroma until the colour is inside sRGB.
     *
     * @param float[] $lch
     * @return string normalised colour
     */
    public static function to_hex(array $lch): string {
        [$ll, $c, $h] = $lch;
        $ll = max(0.0, min(1.0, $ll));
        for ($i = 0; $i < 50; $i++) {
            $rgb = self::lch_to_linear($ll, $c, $h);
            if (min($rgb) >= -0.0001 && max($rgb) <= 1.0001) {
                break;
            }
            $c *= 0.9;
        }
        $out = '#';
        foreach ($rgb as $v) {
            $v = max(0.0, min(1.0, $v));
            $v = $v <= 0.0031308 ? 12.92 * $v : 1.055 * ($v ** (1 / 2.4)) - 0.055;
            $out .= str_pad(dechex((int) round($v * 255)), 2, '0', STR_PAD_LEFT);
        }
        return $out;
    }

    /**
     * OKLCH to linear sRGB.
     *
     * @param float $ll
     * @param float $c
     * @param float $h
     * @return float[]
     */
    private static function lch_to_linear(float $ll, float $c, float $h): array {
        $aa = $c * cos(deg2rad($h));
        $bb = $c * sin(deg2rad($h));
        $l = ($ll + 0.3963377774 * $aa + 0.2158037573 * $bb) ** 3;
        $m = ($ll - 0.1055613458 * $aa - 0.0638541728 * $bb) ** 3;
        $s = ($ll - 0.0894841775 * $aa - 1.2914855480 * $bb) ** 3;
        return [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];
    }

    /**
     * Real cube root.
     *
     * @param float $x
     * @return float
     */
    private static function cbrt(float $x): float {
        return $x < 0 ? -((-$x) ** (1 / 3)) : $x ** (1 / 3);
    }
}
