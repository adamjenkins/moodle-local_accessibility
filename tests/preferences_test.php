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
        preferences::set('size', '1001');
    }

    /**
     * Review focus 2: a locked feature uses the site value; the user's stored value survives.
     */
    public function test_locked_feature_uses_site_default(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        preferences::set('lineheight', '250');
        preferences::set('letterspacing', '30');
        set_config('default_lineheight', '150', 'local_accessibility');
        set_config('lock_spacing', 1, 'local_accessibility');
        $this->assertTrue(preferences::is_locked('lineheight'));
        $this->assertTrue(preferences::is_locked('letterspacing'));
        $this->assertTrue(preferences::is_locked('wordspacing'));
        $this->assertSame('150', preferences::get('lineheight'));
        $this->assertSame('default', preferences::get('letterspacing'));
        $this->assertSame('250', get_user_preferences('local_accessibility_lineheight'));
        set_config('lock_spacing', 0, 'local_accessibility');
        $this->assertSame('250', preferences::get('lineheight'));
        $this->assertSame('30', preferences::get('letterspacing'));
        // A per-feature lock name that is not the tile's has no effect.
        set_config('lock_lineheight', 1, 'local_accessibility');
        $this->assertFalse(preferences::is_locked('lineheight'));
    }

    /**
     * A user can choose the feature's built-in default when the admin's unlocked site default differs.
     */
    public function test_choose_builtin_default_over_site_default(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('default_size', '125', 'local_accessibility');
        $this->assertSame('125', preferences::get('size'));
        preferences::set('size', '100');
        $this->assertSame('100', preferences::get('size'));
        // Choosing the site default itself stores nothing, so the user follows later changes to it.
        preferences::set('size', '125');
        $this->assertNull(get_user_preferences('local_accessibility_size'));
        $this->assertSame('125', preferences::get('size'));
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
        $_COOKIE[preferences::COOKIE] = json_encode(['size' => '180', 'links' => 'outline']);
        $this->assertTrue(preferences::uses_cookie());
        $this->assertSame('180', preferences::get('size'));
        $this->assertSame('outline', preferences::get('links'));
    }

    /**
     * A guest cookie written by a 3.0 development site is read through the legacy-value map (spec §6).
     */
    public function test_guest_cookie_legacy_values(): void {
        $this->setGuestUser();
        $_COOKIE[preferences::COOKIE] = json_encode(['spacing' => 'extra', 'links' => 'on', 'align' => 'on',
            'size' => '175', 'focus' => 'cursor']);
        $this->assertSame('180', preferences::get('lineheight'));
        $this->assertSame('16', preferences::get('letterspacing'));
        $this->assertSame('24', preferences::get('wordspacing'));
        $this->assertSame('outline', preferences::get('links'));
        $this->assertSame('left', preferences::get('align'));
        // Text size takes any whole percentage, so the development value 175 is kept as it is.
        $this->assertSame('175', preferences::get('size'));
        $this->assertSame('ring', preferences::get('focus'));
        $this->assertSame('large', preferences::get('cursor'));
    }

    /**
     * A tampered cookie value never reaches a CSS custom property or an attribute.
     */
    public function test_tampered_cookie_emits_no_variable(): void {
        $this->setGuestUser();
        $_COOKIE[preferences::COOKIE] = json_encode(['size' => '150;background:url(x)', 'font' => 'x;y',
            'lineheight' => '1e2']);
        $attrs = preferences::html_attributes();
        $this->assertArrayNotHasKey('style', $attrs);
        $this->assertArrayNotHasKey('data-a11y-size', $attrs);
        $this->assertArrayNotHasKey('data-a11y-font', $attrs);
        $this->assertArrayNotHasKey('data-a11y-lineheight', $attrs);
    }

    /**
     * Feature variables and the colour scheme's properties share one style attribute (scheme first).
     */
    public function test_style_combines_scheme_and_variables(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        preferences::set_custom_scheme(scheme::custom('#14202b', '#e8eef3', '#8cc8ff', false));
        preferences::set('size', '150');
        preferences::set('lineheight', '180');
        preferences::set('font', 'mono');
        $attrs = preferences::html_attributes();
        $this->assertCount(1, array_filter(array_keys($attrs), fn($k) => $k === 'style'));
        $style = $attrs['style'];
        $this->assertStringStartsWith('--a11y-page:#', $style);
        $this->assertStringContainsString('--a11y-size: 150', $style);
        $this->assertStringContainsString('--a11y-lh: 1.8', $style);
        $this->assertStringContainsString('--a11y-font: ui-monospace, ', $style);
        $this->assertLessThan(strpos($style, '--a11y-lh'), strpos($style, '--a11y-size'));
        $this->assertStringNotContainsString(';;', $style);
        $this->assertSame('150', $attrs['data-a11y-size']);
        $this->assertSame('180', $attrs['data-a11y-lineheight']);
    }

    /**
     * Without a colour scheme, the style attribute carries only the feature variables.
     */
    public function test_style_without_scheme(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertArrayNotHasKey('style', preferences::html_attributes());
        preferences::set('narrow', '50');
        preferences::set('wordspacing', '40');
        $this->assertSame('--a11y-ws: 0.4em; --a11y-measure: 50ch', preferences::html_attributes()['style']);
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
        preferences::set('links', 'outline');
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
     * Adding features purges the cached enabled list, even when an empty list was cached.
     */
    public function test_sync_purges_enabled_cache(): void {
        global $DB;
        $DB->delete_records('local_accessibility_widgets');
        \cache::make('local_accessibility', 'enabled')->purge();
        $this->assertSame([], preferences::enabled_ids());
        preferences::sync_features_table();
        $this->assertSame(array_keys(\local_accessibility\feature\registry::all()), preferences::enabled_ids());
        $this->assertCount(16, preferences::enabled_ids());
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
