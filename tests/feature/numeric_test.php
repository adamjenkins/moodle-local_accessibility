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

use local_accessibility\preferences;
use local_accessibility\profiles;

/**
 * Tests for the numeric features: any whole number in a range, bounded below by the site's numeric limits.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\feature\numeric
 * @covers     \local_accessibility\feature\size
 * @covers     \local_accessibility\feature\lineheight
 * @covers     \local_accessibility\feature\letterspacing
 * @covers     \local_accessibility\feature\wordspacing
 * @covers     \local_accessibility\feature\narrow
 */
final class numeric_test extends \advanced_testcase {
    /**
     * Range validation in both limit modes.
     *
     * @dataProvider range_provider
     * @param string $id
     * @param string $value
     * @param bool $unlimited valid while the site allows the full range
     * @param bool $nonnegative valid while the site keeps values non-negative
     */
    public function test_range(string $id, string $value, bool $unlimited, bool $nonnegative): void {
        $this->resetAfterTest();
        $f = registry::get($id);
        $this->assertInstanceOf(numeric::class, $f);
        $this->assertSame($unlimited, $f->validate($value), "unlimited $id '$value'");
        set_config('numericlimits', 'unlimited', 'local_accessibility');
        $this->assertSame($unlimited, $f->validate($value), "explicitly unlimited $id '$value'");
        set_config('numericlimits', 'nonnegative', 'local_accessibility');
        $this->assertSame($nonnegative, $f->validate($value), "nonnegative $id '$value'");
    }

    /**
     * Values, and whether each is valid when unlimited and when non-negative.
     *
     * @return array
     */
    public static function range_provider(): array {
        $cases = [
            // Bounds per feature: unlimited minimum, non-negative minimum, cap.
            ['size', '1', true, false], ['size', '10', true, true], ['size', '0', false, false],
            ['size', '9', true, false], ['size', '1000', true, true], ['size', '1001', false, false],
            ['size', '175', true, true], ['size', '-10', false, false], ['size', '100', true, true],
            ['lineheight', '0', true, false], ['lineheight', '99', true, false], ['lineheight', '100', true, true],
            ['lineheight', '1000', true, true], ['lineheight', '1001', false, false], ['lineheight', '-1', false, false],
            ['lineheight', 'default', true, true],
            ['letterspacing', '-500', true, false], ['letterspacing', '-501', false, false],
            ['letterspacing', '-5', true, false], ['letterspacing', '0', true, true], ['letterspacing', '500', true, true],
            ['letterspacing', '501', false, false], ['letterspacing', 'default', true, true],
            ['wordspacing', '-1000', true, false], ['wordspacing', '-1001', false, false],
            ['wordspacing', '-2', true, false], ['wordspacing', '0', true, true], ['wordspacing', '1000', true, true],
            ['wordspacing', '1001', false, false], ['wordspacing', 'default', true, true],
            ['narrow', '1', true, false], ['narrow', '10', true, true], ['narrow', '0', false, false],
            ['narrow', '300', true, true], ['narrow', '301', false, false], ['narrow', 'off', true, true],
            // Junk is never valid.
            ['letterspacing', '1e3', false, false], ['letterspacing', '12.5', false, false],
            ['letterspacing', '--1', false, false], ['letterspacing', '+5', false, false],
            ['letterspacing', '-0', false, false], ['letterspacing', '012', false, false],
            ['letterspacing', ' 12', false, false], ['letterspacing', "12\n", false, false],
            ['letterspacing', '12em', false, false], ['letterspacing', '', false, false],
            ['letterspacing', '12;x', false, false], ['letterspacing', 'off', false, false],
            ['size', '10000', false, false], ['size', '1e2', false, false], ['narrow', 'default', false, false],
            ['lineheight', '0x10', false, false], ['wordspacing', '٣', false, false],
        ];
        $out = [];
        foreach ($cases as [$id, $value, $u, $n]) {
            $out["$id '" . addcslashes($value, "\n") . "'"] = [$id, $value, $u, $n];
        }
        return $out;
    }

    /**
     * Only validated integers reach CSS, through the feature's own conversion, in either mode.
     */
    public function test_css_emission(): void {
        $this->resetAfterTest();
        $this->assertSame(['--a11y-ls' => '-0.05em'], registry::get('letterspacing')->css_properties('-5'));
        $this->assertSame(['--a11y-ws' => '-10em'], registry::get('wordspacing')->css_properties('-1000'));
        $this->assertSame(['--a11y-lh' => '0.5'], registry::get('lineheight')->css_properties('50'));
        $this->assertSame(['--a11y-size' => '1000'], registry::get('size')->css_properties('1000'));
        $this->assertSame(['--a11y-measure' => '300ch'], registry::get('narrow')->css_properties('300'));
        $this->assertSame([], registry::get('letterspacing')->css_properties('1e3'));
        $this->assertSame([], registry::get('size')->css_properties('100'));
        set_config('numericlimits', 'nonnegative', 'local_accessibility');
        $this->assertSame([], registry::get('letterspacing')->css_properties('-5'));
        $this->assertSame([], registry::get('lineheight')->css_properties('50'));
        $this->assertSame(['--a11y-ls' => '0.05em'], registry::get('letterspacing')->css_properties('5'));
    }

    /**
     * A stored value is used while it is in range; once the site restricts the limits it falls back to the site
     * default when read, and is not rewritten.
     */
    public function test_stored_value_falls_back_when_restricted(): void {
        $this->resetAfterTest();
        preferences::sync_features_table();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        preferences::set('letterspacing', '-5');
        $this->assertSame('-5', preferences::get('letterspacing'));
        $this->assertStringContainsString('--a11y-ls: -0.05em', preferences::html_attributes()['style']);
        $this->assertSame('-5', preferences::html_attributes()['data-a11y-letterspacing']);

        set_config('numericlimits', 'nonnegative', 'local_accessibility');
        set_config('default_letterspacing', '12', 'local_accessibility');
        $this->assertSame('12', preferences::get('letterspacing'));
        $this->assertSame('-5', get_user_preferences('local_accessibility_letterspacing'));
        // Saving a negative value is refused.
        $this->expectException(\invalid_parameter_exception::class);
        preferences::set('letterspacing', '-6');
    }

    /**
     * Admin defaults outside the range fall back to the feature's default.
     */
    public function test_site_default_validated(): void {
        $this->resetAfterTest();
        set_config('default_lineheight', '80', 'local_accessibility');
        $this->assertSame('80', preferences::site_default('lineheight'));
        set_config('numericlimits', 'nonnegative', 'local_accessibility');
        $this->assertSame('default', preferences::site_default('lineheight'));
        set_config('default_size', '', 'local_accessibility');
        $this->assertSame('100', preferences::site_default('size'));
        set_config('default_size', '1e3', 'local_accessibility');
        $this->assertSame('100', preferences::site_default('size'));
    }

    /**
     * The guest cookie's numeric values are validated like saved ones.
     */
    public function test_guest_cookie(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $_COOKIE[preferences::COOKIE] = json_encode(['letterspacing' => '-12', 'size' => '175', 'narrow' => '1e3',
            'wordspacing' => '12.5']);
        try {
            $this->assertSame('-12', preferences::get('letterspacing'));
            $this->assertSame('175', preferences::get('size'));
            $this->assertSame('off', preferences::get('narrow'));
            $this->assertSame('default', preferences::get('wordspacing'));
            set_config('numericlimits', 'nonnegative', 'local_accessibility');
            $this->assertSame('default', preferences::get('letterspacing'));
        } finally {
            unset($_COOKIE[preferences::COOKIE]);
        }
    }

    /**
     * Profile values are validated with the same rules: out-of-range and junk numbers are dropped.
     */
    public function test_profiles(): void {
        $this->resetAfterTest();
        $json = json_encode(['p' => ['name' => 'P', 'values' => ['letterspacing' => '-5', 'size' => '175',
            'lineheight' => '12.5', 'narrow' => '400', 'wordspacing' => '-1000']]]);
        set_config('profiles', $json, 'local_accessibility');
        $expected = ['letterspacing' => '-5', 'size' => '175', 'wordspacing' => '-1000'];
        $this->assertEqualsCanonicalizing($expected, profiles::all()['p']['values']);
        set_config('numericlimits', 'nonnegative', 'local_accessibility');
        $this->assertSame(['size' => '175'], profiles::all()['p']['values']);
        // The shipped profiles stay valid when restricted.
        set_config('profiles', '', 'local_accessibility');
        foreach (profiles::DEFAULTS as $id => $p) {
            $this->assertSame(count($p['values']), count(profiles::all()[$id]['values']), $id);
        }
    }

    /**
     * Labels in the user's units, and the stepper's metadata.
     */
    public function test_labels_and_stepper(): void {
        $this->resetAfterTest();
        $this->assertSame('1.8', registry::get('lineheight')->value_label('180'));
        $this->assertSame('-0.05', registry::get('letterspacing')->value_label('-5'));
        $this->assertSame('0.16', registry::get('wordspacing')->value_label('16'));
        $this->assertSame('150%', registry::get('size')->value_label('150'));
        $this->assertSame('100%', registry::get('size')->value_label('100'));
        $this->assertSame('60 characters', registry::get('narrow')->value_label('60'));
        $this->assertSame('Full width', registry::get('narrow')->value_label('off'));
        $this->assertSame('Site default', registry::get('letterspacing')->value_label('default'));
        $this->assertSame('', registry::get('letterspacing')->field_value('default'));
        $this->assertSame('-0.05', registry::get('letterspacing')->field_value('-5'));

        $expected = ['lineheight' => [10, 0, 1000], 'letterspacing' => [1, -500, 500], 'wordspacing' => [2, -1000, 1000],
            'size' => [10, 1, 1000], 'narrow' => [5, 1, 300]];
        foreach ($expected as $id => [$step, $min, $max]) {
            $m = registry::get($id)->stepper();
            $this->assertSame([$step, $min, $max], [$m['step'], $m['min'], $m['max']], $id);
            $this->assertFalse($m['nonnegative'], $id);
        }
        $keys = array_flip(['property', 'cssunit', 'scale', 'unit']);
        $this->assertSame([100, '--a11y-ls', 'em', 'em'], array_values(array_intersect_key(
            registry::get('letterspacing')->stepper(),
            $keys
        )));
        $this->assertTrue(registry::get('narrow')->stepper()['defaultismax']);
        set_config('numericlimits', 'nonnegative', 'local_accessibility');
        $expected = ['lineheight' => 100, 'letterspacing' => 0, 'wordspacing' => 0, 'size' => 10, 'narrow' => 10];
        foreach ($expected as $id => $min) {
            $this->assertSame($min, registry::get($id)->stepper()['min'], $id);
            $this->assertTrue(registry::get($id)->stepper()['nonnegative'], $id);
        }
    }
}
