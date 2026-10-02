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
 * Lightness adjustments that keep hue: surface ramps and contrast targeting.
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class adjust {
    /** @var float Luminance above which a page counts as light. */
    private const LIGHT_LUMINANCE = 0.18;

    /** @var array<string, float> OKLCH lightness steps per tier on a light page (negative = darker). */
    private const LIGHT_STEPS = ['surface1' => -0.03, 'surface2' => -0.06, 'field' => 0.0, 'hover' => -0.09];

    /** @var array<string, float> Steps on a dark page (positive = lighter), visible even on pure black. */
    private const DARK_STEPS = ['surface1' => 0.07, 'surface2' => 0.12, 'field' => 0.10, 'hover' => 0.16];

    /** @var array<string, float> Minimum OKLCH lightness per tier on a dark page, so near-black pages still get visible tiers. */
    private const DARK_FLOORS = ['surface1' => 0.20, 'surface2' => 0.28, 'field' => 0.24, 'hover' => 0.34];

    /**
     * Light or dark, from the page colour.
     *
     * @param string $page normalised colour
     * @return string 'light' or 'dark'
     */
    public static function mode(string $page): string {
        return contrast::luminance($page) > self::LIGHT_LUMINANCE ? 'light' : 'dark';
    }

    /**
     * Derive the surface ramp from a page colour, keeping hue (spec §6.3).
     *
     * @param string $page normalised colour
     * @return array<string, string> page, surface1, surface2, field, hover
     */
    public static function ramp(string $page): array {
        [$l, $c, $h] = oklch::from_hex($page);
        $light = self::mode($page) === 'light';
        $steps = $light ? self::LIGHT_STEPS : self::DARK_STEPS;
        $ramp = ['page' => $page];
        foreach ($steps as $tier => $step) {
            $target = $l + $step;
            if (!$light) {
                $target = max($target, self::DARK_FLOORS[$tier]);
            }
            $ramp[$tier] = $step == 0.0 ? $page : oklch::to_hex([$target, $c, $h]);
        }
        return $ramp;
    }

    /**
     * Smallest lightness change that lets a foreground reach a target on every ramp shade.
     *
     * @param string $fg normalised colour
     * @param array $ramp from ramp()
     * @param float $target contrast ratio
     * @return string|null adjusted colour, or null when no lightness of this hue reaches the target
     */
    public static function towards(string $fg, array $ramp, float $target): ?string {
        if (contrast::worst($fg, $ramp) >= $target) {
            return $fg;
        }
        [$l, $c, $h] = oklch::from_hex($fg);
        $darker = contrast::luminance($fg) < contrast::luminance($ramp['page']);
        $end = $darker ? 0.0 : 1.0;
        if (contrast::worst(oklch::to_hex([$end, $c, $h]), $ramp) < $target) {
            return null;
        }
        $lo = $l;
        $hi = $end;
        for ($i = 0; $i < 30; $i++) {
            $mid = ($lo + $hi) / 2;
            if (contrast::worst(oklch::to_hex([$mid, $c, $h]), $ramp) >= $target) {
                $hi = $mid;
            } else {
                $lo = $mid;
            }
        }
        return oklch::to_hex([$hi, $c, $h]);
    }
}
