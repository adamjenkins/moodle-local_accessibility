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

namespace local_accessibility\external;

use core_external\external_api;

/**
 * Tests for the web services.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\external\save_preference
 * @covers     \local_accessibility\external\save_custom_scheme
 * @covers     \local_accessibility\external\reset_preferences
 */
final class services_test extends \advanced_testcase {
    /**
     * Setup.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \local_accessibility\preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * Valid saves persist.
     */
    public function test_save_preference(): void {
        $r = external_api::clean_returnvalue(save_preference::execute_returns(), save_preference::execute('size', '150'));
        $this->assertTrue($r['success']);
        $this->assertSame('150', get_user_preferences('local_accessibility_size'));
    }

    /**
     * Unknown values are refused server-side (spec §8).
     */
    public function test_save_preference_invalid(): void {
        $this->expectException(\invalid_parameter_exception::class);
        save_preference::execute('size', '5000');
    }

    /**
     * Locked features are refused.
     */
    public function test_save_preference_locked(): void {
        set_config('lock_size', 1, 'local_accessibility');
        $this->expectException(\moodle_exception::class);
        save_preference::execute('size', '150');
    }

    /**
     * Custom scheme: auto-adjust reports adjusted and passes 7:1.
     */
    public function test_custom_scheme_adjusted(): void {
        $r = external_api::clean_returnvalue(
            save_custom_scheme::execute_returns(),
            save_custom_scheme::execute('#3a6ea5', '#ffffff', '#ffe08a', false)
        );
        $this->assertTrue($r['adjusted']);
        $this->assertGreaterThanOrEqual(7, $r['textratio']);
        $this->assertSame('custom', get_user_preferences('local_accessibility_colour'));
    }

    /**
     * Custom scheme below the floor is refused even when exact.
     */
    public function test_custom_scheme_floor(): void {
        $this->expectException(\invalid_parameter_exception::class);
        save_custom_scheme::execute('#ffffff', '#fafafa', '#0000c0', true);
    }

    /**
     * Reset clears preferences.
     */
    public function test_reset(): void {
        save_preference::execute('size', '150');
        reset_preferences::execute();
        $this->assertNull(get_user_preferences('local_accessibility_size'));
    }

    /**
     * Guests cannot save server-side.
     */
    public function test_guest_refused(): void {
        $this->setGuestUser();
        $this->expectException(\moodle_exception::class);
        save_preference::execute('size', '150');
    }
}
