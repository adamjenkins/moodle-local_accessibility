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

/**
 * Tests for the feature registry.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\feature\registry
 * @covers     \local_accessibility\feature\base
 */
final class registry_test extends \advanced_testcase {
    /**
     * The 13 features in display order (spec §5).
     */
    public function test_order(): void {
        $this->assertSame(['size', 'font', 'spacing', 'align', 'colour', 'narrow', 'links', 'images',
            'guide', 'motion', 'read', 'saturation', 'focus'], array_keys(registry::all()));
    }

    /**
     * Levels match the spec.
     */
    public function test_values(): void {
        $this->assertSame(['100', '125', '150', '175', '200'], registry::get('size')->values());
        $this->assertSame(['default', 'readable', 'dyslexic'], registry::get('font')->values());
        $this->assertSame(['normal', 'wcag', 'extra'], registry::get('spacing')->values());
        $this->assertSame(['off', '70', '60'], registry::get('narrow')->values());
        $this->assertSame(['off', 'ruler', 'mask'], registry::get('guide')->values());
        $this->assertSame(['off', 'low', 'grey', 'high'], registry::get('saturation')->values());
        $this->assertSame(['off', 'ring', 'cursor'], registry::get('focus')->values());
        $this->assertSame(
            ['default', 'highcontrast', 'yellowblack', 'blackwhite', 'cream', 'dark', 'custom'],
            registry::get('colour')->values()
        );
    }

    /**
     * Validation rejects anything not in the list.
     */
    public function test_validate(): void {
        $this->resetAfterTest();
        $this->assertTrue(registry::get('size')->validate('150'));
        $this->assertFalse(registry::get('size')->validate('50'));
        $this->assertFalse(registry::get('size')->validate('150" onload="x'));
        $this->assertFalse(registry::get('colour')->validate('site_99'));
        $this->assertNull(registry::get('nonexistent'));
    }

    /**
     * Attributes only for non-default values.
     */
    public function test_attributes(): void {
        $this->assertSame([], registry::get('size')->html_attributes('100'));
        $this->assertSame(['data-a11y-size' => '150'], registry::get('size')->html_attributes('150'));
    }

    /**
     * Every feature and value has a lang string.
     */
    public function test_strings_exist(): void {
        $sm = get_string_manager();
        foreach (registry::all() as $id => $f) {
            $this->assertTrue($sm->string_exists("feature_$id", 'local_accessibility'), $id);
            foreach ($f->values() as $v) {
                $this->assertTrue($sm->string_exists("feature_{$id}_{$v}", 'local_accessibility'), "$id $v");
            }
        }
    }

    /**
     * Malformed admin config is skipped silently.
     *
     * @dataProvider malformed_provider
     * @param string $raw
     */
    public function test_malformed_site_presets(string $raw): void {
        $this->resetAfterTest();
        set_config('sitepresets', $raw, 'local_accessibility');
        $warnings = [];
        set_error_handler(function ($no, $str) use (&$warnings) {
            $warnings[] = $str;
            return true;
        });
        try {
            $valid = registry::get('colour')->validate('site_0');
            $presets = colour::site_presets();
        } finally {
            restore_error_handler();
        }
        $this->assertSame([], $warnings);
        $this->assertFalse($valid);
        $this->assertSame([], $presets);
    }

    /**
     * Malformed config shapes.
     *
     * @return array
     */
    public static function malformed_provider(): array {
        return [
            'scalar number' => ['5'],
            'scalar string' => ['"x"'],
            'bad elements' => ['[1,{"bg":["x"]}]'],
            'string keys' => ['{"a":{"bg":"#000000","text":"#ffffff","link":"#ffff00"}}'],
            'invalid json' => ['{'],
        ];
    }

    /**
     * A valid site preset validates and does not change values().
     */
    public function test_valid_site_preset(): void {
        $this->resetAfterTest();
        $json = json_encode([['bg' => '#000000', 'text' => '#ffffff', 'link' => '#ffff00']]);
        set_config('sitepresets', $json, 'local_accessibility');
        $colour = registry::get('colour');
        $this->assertTrue($colour->validate('site_0'));
        $this->assertFalse($colour->validate('site_1'));
        $expected = ['default', 'highcontrast', 'yellowblack', 'blackwhite', 'cream', 'dark', 'custom'];
        $this->assertSame($expected, $colour->values());
    }
}
