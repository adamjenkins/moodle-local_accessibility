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
 * Tests for the legacy-value map (spec §6, plan D2).
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\local\legacy
 */
final class legacy_test extends \basic_testcase {
    /**
     * Each 3.0-dev value maps to its new value(s).
     *
     * @dataProvider map_provider
     * @param array $in
     * @param array $expected
     */
    public function test_map_values(array $in, array $expected): void {
        $this->assertSame($expected, legacy::map_values($in));
    }

    /**
     * Mapping twice gives the same result as mapping once.
     *
     * @dataProvider map_provider
     * @param array $in
     */
    public function test_idempotent(array $in): void {
        $once = legacy::map_values($in);
        $this->assertSame($once, legacy::map_values($once));
    }

    /**
     * Old values and what they become.
     *
     * @return array
     */
    public static function map_provider(): array {
        return [
            'align on' => [['align' => 'on'], ['align' => 'left']],
            'align off' => [['align' => 'off'], ['align' => 'off']],
            'spacing wcag' => [['spacing' => 'wcag'], ['lineheight' => '150', 'letterspacing' => '12', 'wordspacing' => '16']],
            'spacing extra' => [['spacing' => 'extra'], ['lineheight' => '180', 'letterspacing' => '16', 'wordspacing' => '24']],
            'spacing normal' => [['spacing' => 'normal', 'size' => '150'], ['size' => '150']],
            'spacing junk' => [['spacing' => ['x'], 'size' => '150'], ['size' => '150']],
            'spacing does not overwrite' => [['lineheight' => '250', 'spacing' => 'wcag'],
                ['lineheight' => '250', 'letterspacing' => '12', 'wordspacing' => '16']],
            'links on' => [['links' => 'on'], ['links' => 'outline']],
            'images on' => [['images' => 'on'], ['images' => 'hide']],
            'focus cursor' => [['focus' => 'cursor'], ['focus' => 'ring', 'cursor' => 'large']],
            'focus cursor keeps cursor' => [['focus' => 'cursor', 'cursor' => 'xlarge'], ['focus' => 'ring', 'cursor' => 'xlarge']],
            'focus ring' => [['focus' => 'ring'], ['focus' => 'ring']],
            'size 175 is a value of its own' => [['size' => '175'], ['size' => '175']],
            'size 150' => [['size' => '150'], ['size' => '150']],
            'new values' => [['links' => 'underline', 'images' => 'dim', 'align' => 'center'],
                ['links' => 'underline', 'images' => 'dim', 'align' => 'center']],
            'unknown and non-string' => [['nosuch' => 'on', 'custom' => ['bg' => '#000000'], 'links' => ['on']],
                ['nosuch' => 'on', 'custom' => ['bg' => '#000000'], 'links' => ['on']]],
            'empty' => [[], []],
            'everything' => [
                ['size' => '175', 'spacing' => 'extra', 'align' => 'on', 'links' => 'on', 'images' => 'on', 'focus' => 'cursor'],
                ['size' => '175', 'lineheight' => '180', 'letterspacing' => '16', 'wordspacing' => '24', 'align' => 'left',
                    'links' => 'outline', 'images' => 'hide', 'focus' => 'ring', 'cursor' => 'large'],
            ],
        ];
    }
}
