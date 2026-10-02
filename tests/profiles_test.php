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
            ['font' => 'dyslexic', 'spacing' => 'extra', 'colour' => 'cream', 'guide' => 'ruler'],
            $p['dyslexia']['values']
        );
        $this->assertSame(get_string('profile_dyslexia', 'local_accessibility'), $p['dyslexia']['name']);
    }

    /**
     * Invalid admin profiles are dropped value by value.
     */
    public function test_invalid_values_dropped(): void {
        $this->resetAfterTest();
        set_config(
            'profiles',
            json_encode(['x' => ['name' => 'X', 'values' => ['size' => '999', 'links' => 'on', 'nosuch' => 'on']]]),
            'local_accessibility'
        );
        $this->assertSame(['links' => 'on'], profiles::all()['x']['values']);
    }

    /**
     * Template context carries the values as JSON.
     */
    public function test_for_template(): void {
        $this->resetAfterTest();
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
        $this->assertTrue($s->validate('{"a":{"name":"A","values":{"links":"on"}}}'));
        $this->assertNotTrue($s->validate('[]x'));
        $this->assertNotTrue($s->validate('{"a":"b"}'));
        $this->assertNotTrue($s->validate('{"a":{"name":"A"}}'));
        $this->assertNotTrue($s->validate('{"a":{"name":"","values":{}}}'));
    }
}
