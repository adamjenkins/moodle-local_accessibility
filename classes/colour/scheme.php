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

use invalid_parameter_exception;

/**
 * A colour scheme: surface ramp, text and link colours (spec §6).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scheme {
    /**
     * Constructor; use custom() or presets().
     *
     * @param string $id preset id or 'custom'
     * @param string $mode 'light' or 'dark'
     * @param array $ramp page, surface1, surface2, field, hover
     * @param string $text
     * @param string $link
     * @param bool $exact true when the user chose exact colours over auto-adjust
     */
    private function __construct(
        /** @var string Preset id or "custom". */
        public readonly string $id,
        /** @var string "light" or "dark". */
        public readonly string $mode,
        /** @var array Surface ramp: page, surface1, surface2, field, hover. */
        public readonly array $ramp,
        /** @var string Text colour. */
        public readonly string $text,
        /** @var string Link colour. */
        public readonly string $link,
        /** @var bool True when exact colours were chosen. */
        public readonly bool $exact,
    ) {
    }

    /**
     * Build a custom scheme, auto-adjusting to 7:1 unless exact (spec §6.2).
     *
     * @param string $bg
     * @param string $text
     * @param string $link
     * @param bool $exact
     * @param string $id
     * @return self
     * @throws invalid_parameter_exception invalid hex, or below the floor
     */
    public static function custom(string $bg, string $text, string $link, bool $exact, string $id = 'custom'): self {
        $bg = contrast::normalise($bg);
        $text = contrast::normalise($text);
        $link = contrast::normalise($link);
        if ($bg === null || $text === null || $link === null) {
            throw new invalid_parameter_exception('Colours must be #rrggbb');
        }
        $ramp = adjust::ramp($bg);
        if ($exact) {
            $s = new self($id, adjust::mode($bg), $ramp, $text, $link, true);
            if ($s->worst_text() < contrast::FLOOR || $s->worst_link() < contrast::FLOOR) {
                throw new invalid_parameter_exception('Colours are below the 1.5:1 floor');
            }
            return $s;
        }
        $newtext = adjust::towards($text, $ramp, contrast::AAA);
        if ($newtext === null) {
            // Move the page away from the text until the text's hue can pass (spec §6.2 step 2).
            [$l, $c, $h] = oklch::from_hex($bg);
            $step = contrast::luminance($text) > contrast::luminance($bg) ? -0.01 : 0.01;
            for ($i = 1; $i <= 100 && $newtext === null; $i++) {
                $ramp = adjust::ramp(oklch::to_hex([$l + $step * $i, $c, $h]));
                $newtext = adjust::towards($text, $ramp, contrast::AAA);
            }
            $newtext ??= self::best_bw($ramp);
        }
        $newlink = adjust::towards($link, $ramp, contrast::AAA) ?? self::best_bw($ramp);
        return new self($id, adjust::mode($ramp['page']), $ramp, $newtext, $newlink, false);
    }

    /**
     * Black or white, whichever contrasts more with the ramp.
     *
     * @param array $ramp
     * @return string
     */
    private static function best_bw(array $ramp): string {
        return contrast::worst('#000000', $ramp) >= contrast::worst('#ffffff', $ramp) ? '#000000' : '#ffffff';
    }

    /**
     * Built-in presets, built through auto-adjust so they always pass (spec §6.1).
     *
     * @return array<string, self>
     */
    public static function presets(): array {
        static $cache = null;
        return $cache ??= [
            'highcontrast' => self::custom('#000000', '#ffffff', '#ffff00', false, 'highcontrast'),
            'yellowblack' => self::custom('#000000', '#ffff00', '#00ffff', false, 'yellowblack'),
            'blackwhite' => self::custom('#ffffff', '#000000', '#0000c0', false, 'blackwhite'),
            'cream' => self::custom('#fbf3df', '#1f1f1f', '#0b4f8a', false, 'cream'),
            'dark' => self::custom('#14202b', '#e8eef3', '#8cc8ff', false, 'dark'),
        ];
    }

    /**
     * Lowest text ratio over the ramp.
     *
     * @return float
     */
    public function worst_text(): float {
        return contrast::worst($this->text, $this->ramp);
    }

    /**
     * Lowest link ratio over the ramp.
     *
     * @return float
     */
    public function worst_link(): float {
        return contrast::worst($this->link, $this->ramp);
    }

    /**
     * Stored form of a custom scheme.
     *
     * @return string
     */
    public function to_json(): string {
        return json_encode(['v' => 1, 'bg' => $this->ramp['page'], 'text' => $this->text,
            'link' => $this->link, 'exact' => $this->exact]);
    }

    /**
     * Parse a stored custom scheme; anything invalid gives null.
     *
     * @param string $json
     * @return self|null
     */
    public static function from_json(string $json): ?self {
        if (strlen($json) > 500) {
            return null;
        }
        $d = json_decode($json, true);
        if (!is_array($d) || ($d['v'] ?? 0) !== 1 || !isset($d['bg'], $d['text'], $d['link'])) {
            return null;
        }
        try {
            return self::custom((string) $d['bg'], (string) $d['text'], (string) $d['link'], !empty($d['exact']));
        } catch (invalid_parameter_exception $e) {
            return null;
        }
    }

    /**
     * Custom properties for the html style attribute. Only validated hex values are emitted.
     *
     * @return string e.g. "--a11y-page:#fbf3df;..."
     */
    public function css_properties(): string {
        $props = $this->ramp + ['text' => $this->text, 'link' => $this->link];
        $out = '';
        foreach ($props as $name => $hex) {
            $out .= '--a11y-' . $name . ':' . $hex . ';';
        }
        return $out;
    }
}
