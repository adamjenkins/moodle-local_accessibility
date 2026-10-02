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
 * Tests for the WCAG contrast and OKLCH helpers.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\colour\contrast
 * @covers     \local_accessibility\colour\oklch
 */
final class contrast_test extends \advanced_testcase {
    /**
     * Known WCAG pairs, values computed 2026-10-02 (spec §6.1).
     *
     * @return array
     */
    public static function pairs(): array {
        return [
            'black on white' => ['#000000', '#ffffff', 21.0],
            'yellow on black' => ['#ffff00', '#000000', 19.56],
            'cream text' => ['#1f1f1f', '#fbf3df', 14.90],
            'cream link' => ['#0b4f8a', '#fbf3df', 7.60],
            'dark text' => ['#e8eef3', '#14202b', 14.12],
            'boost link' => ['#0f6cbf', '#ffffff', 5.36],
            'mid blue white' => ['#ffffff', '#3a6ea5', 5.31],
        ];
    }

    /**
     * Ratios match the WCAG formula to two decimals.
     *
     * @dataProvider pairs
     * @param string $fg
     * @param string $bg
     * @param float $expected
     */
    public function test_ratio(string $fg, string $bg, float $expected): void {
        $this->assertEqualsWithDelta($expected, contrast::ratio($fg, $bg), 0.01);
        $this->assertEqualsWithDelta($expected, contrast::ratio($bg, $fg), 0.01);
    }

    /**
     * Invalid input never reaches the maths.
     */
    public function test_normalise(): void {
        $this->assertSame('#aabbcc', contrast::normalise('#ABC'));
        $this->assertSame('#0b4f8a', contrast::normalise('#0B4F8A'));
        $this->assertNull(contrast::normalise('red'));
        $this->assertNull(contrast::normalise('#12345'));
        $this->assertNull(contrast::normalise('#12345g'));
        $this->assertNull(contrast::normalise('#123456;color:red'));
        // PCRE's $ also matches before a final newline; a stored value must not carry one into the style attribute.
        $this->assertNull(contrast::normalise("#123456\n"));
        $this->assertNull(contrast::normalise("#abc\n"));
    }

    /**
     * The worst ratio is the minimum over the backgrounds.
     */
    public function test_worst(): void {
        $this->assertEqualsWithDelta(3.92, contrast::worst('#0f6cbf', ['#ffffff', '#000000']), 0.01);
    }

    /**
     * OKLCH round-trips within one step per channel.
     */
    public function test_oklch_roundtrip(): void {
        foreach (['#3a6ea5', '#fbf3df', '#14202b', '#ffff00', '#000000', '#ffffff'] as $hex) {
            $back = oklch::to_hex(oklch::from_hex($hex));
            foreach ([1, 3, 5] as $i) {
                $this->assertLessThanOrEqual(1, abs(hexdec(substr($hex, $i, 2)) - hexdec(substr($back, $i, 2))), $hex);
            }
        }
    }
}
