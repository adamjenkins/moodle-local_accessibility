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

use local_accessibility\colour\scheme;

/**
 * Tests for the preferences service.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\preferences
 */
final class preferences_test extends \advanced_testcase {
    /** @var array Saved cookie superglobal. */
    private array $savedcookie = [];

    /**
     * Set up: all features enabled.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->savedcookie = $_COOKIE;
        preferences::sync_features_table();
        \cache::make('local_accessibility', 'enabled')->purge();
        $_COOKIE = [];
    }

    /**
     * Restore the cookie superglobal.
     */
    protected function tearDown(): void {
        $_COOKIE = $this->savedcookie;
        parent::tearDown();
    }

    /**
     * A logged-in user's value round-trips through user preferences.
     */
    public function test_user_roundtrip(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame('100', preferences::get('size'));
        preferences::set('size', '150');
        $this->assertSame('150', preferences::get('size'));
        $this->assertSame('150', get_user_preferences('local_accessibility_size'));
        $attrs = array_intersect_key(preferences::html_attributes(), ['data-a11y-size' => 1]);
        $this->assertSame(['data-a11y-size' => '150'], $attrs);
    }

    /**
     * Invalid values are refused.
     */
    public function test_invalid_refused(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\invalid_parameter_exception::class);
        preferences::set('size', '50');
    }

    /**
     * Review focus 2: a locked feature uses the site value; the user's stored value survives.
     */
    public function test_locked_feature_uses_site_default(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        preferences::set('spacing', 'extra');
        set_config('default_spacing', 'wcag', 'local_accessibility');
        set_config('lock_spacing', 1, 'local_accessibility');
        $this->assertSame('wcag', preferences::get('spacing'));
        $this->assertSame('extra', get_user_preferences('local_accessibility_spacing'));
        set_config('lock_spacing', 0, 'local_accessibility');
        $this->assertSame('extra', preferences::get('spacing'));
    }

    /**
     * Saving a locked feature throws.
     */
    public function test_set_locked_throws(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('lock_size', 1, 'local_accessibility');
        $this->expectException(\moodle_exception::class);
        preferences::set('size', '125');
    }

    /**
     * Guests read the cookie.
     */
    public function test_guest_cookie(): void {
        $this->setGuestUser();
        $_COOKIE[preferences::COOKIE] = json_encode(['size' => '175', 'links' => 'on']);
        $this->assertTrue(preferences::uses_cookie());
        $this->assertSame('175', preferences::get('size'));
        $this->assertSame('on', preferences::get('links'));
    }

    /**
     * Review focus 1: tampered cookies give defaults, never errors.
     */
    public function test_guest_cookie_tampered(): void {
        $this->setGuestUser();
        $cases = [
            'not json',
            json_encode(['size' => '<script>', 'nosuch' => '1']),
            str_repeat('x', 10000),
            json_encode(['custom' => ['bg' => '#fff;}', 'text' => '#000', 'link' => '#00f', 'exact' => true]]),
            json_encode(['size' => ['nested' => 'array']]),
        ];
        foreach ($cases as $bad) {
            $_COOKIE[preferences::COOKIE] = $bad;
            $this->assertSame('100', preferences::get('size'));
            $this->assertNull(preferences::custom_scheme());
            $this->assertIsArray(preferences::html_attributes());
        }
    }

    /**
     * A disabled feature emits no attribute.
     */
    public function test_disabled_feature_ignored(): void {
        global $DB;
        $this->setUser($this->getDataGenerator()->create_user());
        preferences::set('links', 'on');
        $DB->set_field('local_accessibility_widgets', 'enabled', 0, ['name' => 'links']);
        \cache::make('local_accessibility', 'enabled')->purge();
        $this->assertArrayNotHasKey('data-a11y-links', preferences::html_attributes());
    }

    /**
     * A custom scheme emits mode, custom properties and data-bs-theme.
     */
    public function test_custom_scheme_attributes(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        preferences::set_custom_scheme(scheme::custom('#14202b', '#e8eef3', '#8cc8ff', false));
        $attrs = preferences::html_attributes();
        $this->assertSame('custom', $attrs['data-a11y-colour']);
        $this->assertSame('dark', $attrs['data-bs-theme']);
        $this->assertStringStartsWith('--a11y-page:#', $attrs['style']);
    }

    /**
     * Reset clears everything for the user.
     */
    public function test_reset(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        preferences::set('size', '200');
        preferences::reset();
        $this->assertSame('100', preferences::get('size'));
        $this->assertNull(get_user_preferences('local_accessibility_size'));
    }
}
