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
 * @covers     \local_accessibility\feature\font
 */
final class registry_test extends \advanced_testcase {
    /**
     * Store a file in the uploaded fonts area.
     *
     * @param string $filename
     * @return void
     */
    private static function store_font(string $filename): void {
        get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
            'component' => 'local_accessibility', 'filearea' => 'fonts', 'itemid' => 0, 'filepath' => '/',
            'filename' => $filename], 'font data');
    }

    /**
     * The 16 features in display order (spec §3).
     */
    public function test_order(): void {
        $this->assertSame(['size', 'font', 'lineheight', 'letterspacing', 'wordspacing', 'align', 'colour', 'narrow',
            'links', 'images', 'guide', 'motion', 'read', 'saturation', 'focus', 'cursor'], array_keys(registry::all()));
    }

    /**
     * Values match spec §3 exactly.
     */
    public function test_values(): void {
        $size = array_map('strval', array_merge(range(80, 120, 10), [125], range(130, 300, 10)));
        $this->assertSame($size, registry::get('size')->values());
        $this->assertSame(['default', 'sans', 'serif', 'mono', 'readable', 'lexend', 'dyslexic', 'comic', 'jagothic',
            'jamincho', 'jakyokasho'], registry::get('font')->values());
        $this->assertSame(['default', '120', '150', '180', '200', '250'], registry::get('lineheight')->values());
        $this->assertSame(['default', '5', '10', '12', '16', '20', '30'], registry::get('letterspacing')->values());
        $this->assertSame(['default', '10', '16', '24', '40', '60'], registry::get('wordspacing')->values());
        $this->assertSame(['default', 'left', 'center', 'right', 'justify'], registry::get('align')->values());
        $this->assertSame(['off', '90', '80', '70', '60', '50', '40'], registry::get('narrow')->values());
        $this->assertSame(['off', 'underline', 'outline', 'highlight'], registry::get('links')->values());
        $this->assertSame(['off', 'hide', 'dim'], registry::get('images')->values());
        $this->assertSame(['off', 'ruler', 'mask'], registry::get('guide')->values());
        $this->assertSame(['off', 'on'], registry::get('motion')->values());
        $this->assertSame(['off', 'on'], registry::get('read')->values());
        $this->assertSame(['off', 'low', 'grey', 'high'], registry::get('saturation')->values());
        $this->assertSame(['off', 'ring', 'thick'], registry::get('focus')->values());
        $this->assertSame(['off', 'large', 'xlarge'], registry::get('cursor')->values());
        $this->assertSame(
            ['default', 'highcontrast', 'yellowblack', 'blackwhite', 'cream', 'dark', 'custom'],
            registry::get('colour')->values()
        );
    }

    /**
     * Size defaults to 100 although its values ascend from 80 (D1); every default is one of its feature's values.
     */
    public function test_defaults(): void {
        $this->assertSame('100', registry::get('size')->default());
        foreach (registry::all() as $id => $f) {
            $this->assertTrue(in_array($f->default(), $f->values(), true), $id);
        }
    }

    /**
     * Every option shows an icon or a preview, and a real label (spec §2).
     */
    public function test_every_option_has_icon_or_preview(): void {
        foreach (registry::all() as $id => $f) {
            $options = $f->options();
            $this->assertSame($f->values(), array_column($options, 'value'), $id);
            foreach ($options as $o) {
                $this->assertTrue($o['icon'] !== null || $o['preview'] !== null, "$id {$o['value']}");
                $this->assertNotSame('', trim($o['label']), "$id {$o['value']}");
                $this->assertStringNotContainsString('[[', $o['label'], "$id {$o['value']}");
                $this->assertSame($f->css_properties($o['value']), $o['css'], "$id {$o['value']}");
                if ($o['icon'] !== null) {
                    $this->assertMatchesRegularExpression('/^fa-[a-z-]+$/', $o['icon'], "$id {$o['value']}");
                }
            }
        }
    }

    /**
     * Drawer and detail kinds follow spec §3 (A and B).
     */
    public function test_kinds(): void {
        $detail = ['size', 'font', 'lineheight', 'letterspacing', 'wordspacing', 'colour', 'narrow'];
        foreach (registry::all() as $id => $f) {
            $this->assertSame(in_array($id, $detail, true) ? 'detail' : 'drawer', $f->kind(), $id);
        }
    }

    /**
     * The three spacing features share one tile, which takes the place of the first of them.
     */
    public function test_tiles_group_spacing(): void {
        $tiles = registry::tiles(array_keys(registry::all()));
        $this->assertSame(['lineheight', 'letterspacing', 'wordspacing'], $tiles['spacing']);
        $this->assertArrayNotHasKey('lineheight', $tiles);
        $this->assertSame(['size', 'font', 'spacing', 'align', 'colour', 'narrow', 'links', 'images', 'guide', 'motion',
            'read', 'saturation', 'focus', 'cursor'], array_keys($tiles));
        $this->assertSame(['size'], $tiles['size']);
        // Only the enabled members, at the position of the first enabled one (D6).
        $expected = ['size' => ['size'], 'align' => ['align'], 'spacing' => ['wordspacing']];
        $this->assertSame($expected, registry::tiles(['size', 'align', 'wordspacing']));
    }

    /**
     * The spacing trio shares one lock; every other feature has its own.
     */
    public function test_lock_names(): void {
        foreach (['lineheight', 'letterspacing', 'wordspacing'] as $id) {
            $this->assertSame('lock_spacing', registry::get($id)->lock_name());
            $this->assertSame('spacing', registry::get($id)->tile());
        }
        $this->assertSame('lock_size', registry::get('size')->lock_name());
        $this->assertSame('lock_cursor', registry::get('cursor')->lock_name());
    }

    /**
     * CSS custom properties: only for validated, non-default values, formatted from integers or constants.
     *
     * @dataProvider css_provider
     * @param string $id
     * @param string $value
     * @param array $expected
     */
    public function test_css_properties(string $id, string $value, array $expected): void {
        $this->assertSame($expected, registry::get($id)->css_properties($value));
    }

    /**
     * Feature values and their CSS custom properties.
     *
     * @return array
     */
    public static function css_provider(): array {
        $serif = '"Iowan Old Style", "Palatino Linotype", Palatino, Georgia, "Noto Serif", "Liberation Serif", '
            . '"Times New Roman", serif';
        return [
            'size 150' => ['size', '150', ['--a11y-size' => '150']],
            'size 80' => ['size', '80', ['--a11y-size' => '80']],
            'size 100 default' => ['size', '100', []],
            'size injected' => ['size', '150;x', []],
            'size exponent' => ['size', '1e2', []],
            'size not offered' => ['size', '175', []],
            'lineheight 150' => ['lineheight', '150', ['--a11y-lh' => '1.5']],
            'lineheight 200' => ['lineheight', '200', ['--a11y-lh' => '2']],
            'lineheight 120' => ['lineheight', '120', ['--a11y-lh' => '1.2']],
            'lineheight default' => ['lineheight', 'default', []],
            'letterspacing 5' => ['letterspacing', '5', ['--a11y-ls' => '0.05em']],
            'letterspacing 30' => ['letterspacing', '30', ['--a11y-ls' => '0.3em']],
            'wordspacing 60' => ['wordspacing', '60', ['--a11y-ws' => '0.6em']],
            'wordspacing 16' => ['wordspacing', '16', ['--a11y-ws' => '0.16em']],
            'narrow 40' => ['narrow', '40', ['--a11y-measure' => '40ch']],
            'narrow off' => ['narrow', 'off', []],
            'narrow injected' => ['narrow', '40ch;x', []],
            'font serif' => ['font', 'serif', ['--a11y-font' => $serif]],
            'font readable' => ['font', 'readable', ['--a11y-font' => 'local_accessibility_readable, system-ui, sans-serif']],
            'font default' => ['font', 'default', []],
            'font injected' => ['font', 'x;y', []],
            'align center' => ['align', 'center', []],
            'links outline' => ['links', 'outline', []],
        ];
    }

    /**
     * An uploaded font's custom property names its own family first.
     */
    public function test_css_properties_uploaded_font(): void {
        $this->resetAfterTest();
        \local_accessibility\local\fonts::reset_cache();
        self::store_font('MyFont-Regular.woff2');
        \local_accessibility\local\fonts::reset_cache();
        $css = registry::get('font')->css_properties('up_myfont');
        $this->assertStringStartsWith('"local_accessibility_up_myfont"', $css['--a11y-font']);
        $this->assertSame([], registry::get('font')->css_properties('up_nosuch'));
        \local_accessibility\local\fonts::reset_cache();
    }

    /**
     * Every font id has a stack, and the stacks are spec §4's.
     */
    public function test_font_stacks(): void {
        foreach (registry::get('font')->values() as $id) {
            if ($id === 'default') {
                $this->assertNull(font::stack($id));
                continue;
            }
            $this->assertNotEmpty(font::stack($id), $id);
            $this->assertDoesNotMatchRegularExpression('/[;{}<>\\\\]/', font::stack($id), $id);
        }
        $mono = 'ui-monospace, "Cascadia Mono", Consolas, Menlo, "Liberation Mono", "Noto Sans Mono", monospace';
        $this->assertSame($mono, font::stack('mono'));
        $this->assertNull(font::stack('nosuch'));
    }

    /**
     * Validation rejects anything not in the list.
     */
    public function test_validate(): void {
        $this->resetAfterTest();
        $this->assertTrue(registry::get('size')->validate('150'));
        $this->assertTrue(registry::get('size')->validate('125'));
        $this->assertTrue(registry::get('size')->validate('80'));
        $this->assertTrue(registry::get('size')->validate('300'));
        $this->assertFalse(registry::get('size')->validate('175'));
        $this->assertFalse(registry::get('size')->validate('310'));
        $this->assertFalse(registry::get('size')->validate('50'));
        $this->assertFalse(registry::get('size')->validate('150" onload="x'));
        $this->assertFalse(registry::get('colour')->validate('site_99'));
        $this->assertNull(registry::get('nonexistent'));
        $this->assertNull(registry::get('spacing'));
    }

    /**
     * Attributes only for non-default values.
     */
    public function test_attributes(): void {
        $this->assertSame([], registry::get('size')->html_attributes('100'));
        $this->assertSame(['data-a11y-size' => '150'], registry::get('size')->html_attributes('150'));
        $this->assertSame(['data-a11y-size' => '80'], registry::get('size')->html_attributes('80'));
    }

    /**
     * Every feature and every value has a real label.
     */
    public function test_strings_exist(): void {
        $sm = get_string_manager();
        $this->assertTrue($sm->string_exists('feature_spacing', 'local_accessibility'));
        foreach (registry::all() as $id => $f) {
            $this->assertTrue($sm->string_exists("feature_$id", 'local_accessibility'), $id);
            foreach ($f->values() as $v) {
                $label = $f->value_label($v);
                $this->assertNotSame('', trim($label), "$id $v");
                $this->assertStringNotContainsString('[[', $label, "$id $v");
            }
        }
        $this->assertSame('150%', registry::get('size')->value_label('150'));
        $this->assertSame('40 characters', registry::get('narrow')->value_label('40'));
        $this->assertSame('Full width', registry::get('narrow')->value_label('off'));
        $this->assertSame('1.8', registry::get('lineheight')->value_label('180'));
        $this->assertSame('0.12', registry::get('letterspacing')->value_label('12'));
        $this->assertSame('Centre', registry::get('align')->value_label('center'));
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
