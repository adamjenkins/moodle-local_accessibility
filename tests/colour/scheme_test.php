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
 * Tests for colour schemes.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\colour\scheme
 * @covers     \local_accessibility\colour\adjust
 */
final class scheme_test extends \advanced_testcase {
    /**
     * Every preset reaches 7:1 for text and links on every shade (spec §6.1).
     */
    public function test_presets_pass_on_every_shade(): void {
        foreach (scheme::presets() as $id => $s) {
            $this->assertGreaterThanOrEqual(contrast::AAA, $s->worst_text(), "$id text");
            $this->assertGreaterThanOrEqual(contrast::AAA, $s->worst_link(), "$id link");
            // On light pages field = page by design, so at least 4 distinct shades.
            $this->assertGreaterThanOrEqual(4, count(array_unique($s->ramp)), "$id ramp has distinct shades");
        }
    }

    /**
     * Every preset keeps its surface tiers visibly distinct, including pure black (spec §6.3).
     */
    public function test_preset_tiers_distinct(): void {
        foreach (scheme::presets() as $id => $s) {
            $r = $s->ramp;
            $this->assertCount(3, array_unique([$r['page'], $r['surface1'], $r['surface2']]), "$id tiers distinct");
            $this->assertGreaterThanOrEqual(1.15, contrast::ratio($r['page'], $r['surface2']), "$id page vs surface2");
        }
    }

    /**
     * Light pages step darker, dark pages step lighter (spec §6.3).
     */
    public function test_ramp_direction(): void {
        $light = adjust::ramp('#fbf3df');
        $this->assertLessThan(contrast::luminance('#fbf3df'), contrast::luminance($light['surface2']));
        $dark = adjust::ramp('#14202b');
        $this->assertGreaterThan(contrast::luminance('#14202b'), contrast::luminance($dark['surface2']));
        $this->assertSame('light', adjust::mode('#fbf3df'));
        $this->assertSame('dark', adjust::mode('#000000'));
    }

    /**
     * White text on a mid blue: the background darkens until 7:1 holds (spec §6.2 worked example).
     */
    public function test_auto_adjust_moves_background(): void {
        $s = scheme::custom('#3a6ea5', '#ffffff', '#ffe08a', false);
        $this->assertGreaterThanOrEqual(contrast::AAA, $s->worst_text());
        $this->assertGreaterThanOrEqual(contrast::AAA, $s->worst_link());
        $this->assertLessThan(contrast::luminance('#3a6ea5'), contrast::luminance($s->ramp['page']));
        $hue = fn(string $h): float => oklch::from_hex($h)[2];
        $this->assertEqualsWithDelta($hue('#3a6ea5'), $hue($s->ramp['page']), 6.0, 'hue kept');
        $this->assertFalse($s->exact);
    }

    /**
     * Dark text on a mid blue: the background lightens.
     */
    public function test_auto_adjust_dark_text(): void {
        $s = scheme::custom('#3a6ea5', '#1d2125', '#0b4f8a', false);
        $this->assertGreaterThanOrEqual(contrast::AAA, $s->worst_text());
        $this->assertGreaterThan(contrast::luminance('#3a6ea5'), contrast::luminance($s->ramp['page']));
    }

    /**
     * Exact colours are kept as given, even under 7:1.
     */
    public function test_exact_keeps_colours(): void {
        $s = scheme::custom('#3a6ea5', '#1d2125', '#ffe08a', true);
        $this->assertSame('#3a6ea5', $s->ramp['page']);
        $this->assertSame('#1d2125', $s->text);
        $this->assertTrue($s->exact);
        $this->assertLessThan(contrast::AAA, $s->worst_text());
    }

    /**
     * Below 1.5:1 is refused even when exact.
     */
    public function test_floor_refused(): void {
        $this->expectException(\invalid_parameter_exception::class);
        scheme::custom('#ffffff', '#fafafa', '#0000c0', true);
    }

    /**
     * Invalid hex is refused.
     */
    public function test_invalid_hex_refused(): void {
        $this->expectException(\invalid_parameter_exception::class);
        scheme::custom('#fff;}', '#000000', '#0000c0', false);
    }

    /**
     * JSON round trip, and tampered JSON is rejected.
     */
    public function test_json(): void {
        $s = scheme::custom('#fbf3df', '#1f1f1f', '#0b4f8a', true);
        $back = scheme::from_json($s->to_json());
        $this->assertSame($s->ramp, $back->ramp);
        $this->assertNull(scheme::from_json('{"v":1,"bg":"#fff;x","text":"#000000","link":"#0000c0","exact":true}'));
        $this->assertNull(scheme::from_json('not json'));
        $this->assertNull(scheme::from_json(str_repeat('a', 5000)));
        // Non-string colours must give null without a PHP warning.
        $this->assertNull(scheme::from_json('{"v":1,"bg":["x"],"text":"#000000","link":"#0000c0","exact":true}'));
        $this->assertNull(scheme::from_json('{"v":1,"bg":"#ffffff","text":{"a":1},"link":"#0000c0","exact":true}'));
        $this->assertNull(scheme::from_json('{"v":1,"bg":"#ffffff","text":"#000000","link":5,"exact":true}'));
        // Only a real boolean true means exact.
        $loose = scheme::from_json('{"v":1,"bg":"#3a6ea5","text":"#1d2125","link":"#ffe08a","exact":"false"}');
        $this->assertFalse($loose->exact);
        $this->assertGreaterThanOrEqual(contrast::AAA, $loose->worst_text());
    }

    /**
     * CSS output contains only custom properties with hex values.
     */
    public function test_css_properties_safe(): void {
        $css = scheme::presets()['cream']->css_properties();
        $this->assertMatchesRegularExpression('/^(--a11y-[a-z0-9-]+:#[0-9a-f]{6};)+$/', $css);
    }
}
