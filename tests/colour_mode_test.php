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
 * Tests for the core colour mode bridge.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\colour_mode
 */
final class colour_mode_test extends \advanced_testcase {
    /**
     * Without core colour mode, Dark is the plugin's own scheme and nothing fails.
     */
    public function test_dark_without_core_colour_mode(): void {
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('enablecolourmodes', 0, 'theme_boost');
        preferences::set('colour', 'dark');
        $this->assertFalse(colour_mode::core_dark_available());
        $attrs = preferences::html_attributes();
        $this->assertSame('dark', $attrs['data-a11y-colour']);
        $this->assertSame('dark', $attrs['data-bs-theme']);
    }

    /**
     * With core colour mode on (5.3+), Dark is handed to core.
     */
    public function test_dark_handed_to_core(): void {
        if (!class_exists(\theme_boost\colour_mode::class)) {
            $this->markTestSkipped('Core colour mode needs Moodle 5.3');
        }
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('enablecolourmodes', 1, 'theme_boost');
        $this->assertTrue(colour_mode::core_dark_available());
        preferences::set('colour', 'dark');
        $this->assertSame('dark', get_user_preferences(\theme_boost\colour_mode::PREFERENCE));
        $this->assertArrayNotHasKey('data-a11y-colour', preferences::html_attributes());
        preferences::set('colour', 'cream');
        $this->assertNull(get_user_preferences(\theme_boost\colour_mode::PREFERENCE));
    }
}
