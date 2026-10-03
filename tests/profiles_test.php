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

namespace local_accessibility;

/**
 * Tests for profiles.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\profiles
 */
final class profiles_test extends \advanced_testcase {
    /**
     * Shipped defaults are valid and match the spec.
     */
    public function test_defaults(): void {
        $this->resetAfterTest();
        $p = profiles::all();
        $this->assertSame(['dyslexia', 'lowvision', 'focus', 'seizuresafe'], array_keys($p));
        $this->assertSame(
            ['font' => 'dyslexic', 'lineheight' => '180', 'letterspacing' => '16', 'wordspacing' => '24',
                'colour' => 'cream', 'guide' => 'ruler'],
            $p['dyslexia']['values']
        );
        $lowvision = ['size' => '180', 'colour' => 'highcontrast', 'links' => 'outline', 'focus' => 'ring'];
        $this->assertSame($lowvision, $p['lowvision']['values']);
        // Every shipped value survives validation.
        foreach (profiles::DEFAULTS as $id => $d) {
            $this->assertSame($d['values'], $p[$id]['values'], $id);
        }
        $this->assertSame(get_string('profile_dyslexia', 'local_accessibility'), $p['dyslexia']['name']);
    }

    /**
     * Invalid admin profiles are dropped value by value.
     */
    public function test_invalid_values_dropped(): void {
        $this->resetAfterTest();
        set_config(
            'profiles',
            json_encode(['x' => ['name' => 'X', 'values' => ['size' => '999', 'links' => 'outline', 'nosuch' => 'on']]]),
            'local_accessibility'
        );
        $this->assertSame(['links' => 'outline'], profiles::all()['x']['values']);
    }

    /**
     * Plan D12: profile JSON saved with 3.0 development values is mapped when read, not rewritten.
     */
    public function test_legacy_profile_json_mapped(): void {
        $this->resetAfterTest();
        $json = json_encode(['p' => ['name' => 'P', 'values' => ['spacing' => 'wcag', 'links' => 'on', 'size' => '175']]]);
        set_config('profiles', $json, 'local_accessibility');
        $this->assertEqualsCanonicalizing(
            ['lineheight' => '150', 'letterspacing' => '12', 'wordspacing' => '16', 'links' => 'outline', 'size' => '180'],
            profiles::all()['p']['values']
        );
        $this->assertSame($json, get_config('local_accessibility', 'profiles'));
        // Focus "cursor" gains the large cursor, and a value still invalid after mapping is dropped.
        $json = json_encode(['q' => ['name' => 'Q', 'values' => ['focus' => 'cursor', 'images' => 'x']]]);
        set_config('profiles', $json, 'local_accessibility');
        $this->assertEqualsCanonicalizing(['focus' => 'ring', 'cursor' => 'large'], profiles::all()['q']['values']);
    }

    /**
     * Template context carries the values as JSON.
     */
    public function test_for_template(): void {
        $this->resetAfterTest();
        preferences::sync_features_table();
        $t = profiles::for_template();
        $this->assertSame('dyslexia', $t[0]['id']);
        $this->assertSame(profiles::all()['dyslexia']['values'], json_decode($t[0]['values'], true));
    }

    /**
     * The admin setting accepts only a JSON object of profiles, or empty for the defaults.
     */
    public function test_setting_validation(): void {
        $this->resetAfterTest();
        $s = new admin\setting_profiles();
        $this->assertTrue($s->validate(''));
        $this->assertTrue($s->validate('{"a":{"name":"A","values":{"links":"outline"}}}'));
        $this->assertNotTrue($s->validate('[]x'));
        $this->assertNotTrue($s->validate('{"a":"b"}'));
        $this->assertNotTrue($s->validate('{"a":{"name":"A"}}'));
        $this->assertNotTrue($s->validate('{"a":{"name":"","values":{}}}'));
    }

    /**
     * Locked and disabled features are left out of the buttons' values, and an emptied profile is omitted.
     */
    public function test_for_template_skips_locked_and_disabled(): void {
        global $DB;
        $this->resetAfterTest();
        preferences::sync_features_table();
        set_config('lock_font', 1, 'local_accessibility');
        $DB->set_field('local_accessibility_widgets', 'enabled', 0, ['name' => 'colour']);
        \cache::make('local_accessibility', 'enabled')->purge();
        $t = array_column(profiles::for_template(), null, 'id');
        $dyslexia = ['lineheight' => '180', 'letterspacing' => '16', 'wordspacing' => '24', 'guide' => 'ruler'];
        $this->assertSame($dyslexia, json_decode($t['dyslexia']['values'], true));
        $lowvision = ['size' => '180', 'links' => 'outline', 'focus' => 'ring'];
        $this->assertSame($lowvision, json_decode($t['lowvision']['values'], true));
        // One lock covers the three spacing features.
        set_config('lock_spacing', 1, 'local_accessibility');
        $t = array_column(profiles::for_template(), null, 'id');
        $this->assertSame(['guide' => 'ruler'], json_decode($t['dyslexia']['values'], true));
        set_config(
            'profiles',
            json_encode(['only' => ['name' => 'Only', 'values' => ['font' => 'dyslexic']]]),
            'local_accessibility'
        );
        $this->assertSame([], profiles::for_template());
    }
}
