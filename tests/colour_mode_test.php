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

    /**
     * Reset after the plugin's Dark was handed to core turns core's dark mode off again.
     */
    public function test_reset_clears_core_dark(): void {
        if (!class_exists(\theme_boost\colour_mode::class)) {
            $this->markTestSkipped('Core colour mode needs Moodle 5.3');
        }
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('enablecolourmodes', 1, 'theme_boost');
        preferences::set('colour', 'dark');
        $this->assertSame('dark', get_user_preferences(\theme_boost\colour_mode::PREFERENCE));
        preferences::reset();
        $this->assertNull(get_user_preferences(\theme_boost\colour_mode::PREFERENCE));
        $this->assertFalse(colour_mode::core_is_dark());
        $this->assertSame('default', preferences::get('colour'));
    }

    /**
     * Reset follows a dark site default, and leaves alone a core mode the user chose with core's own switcher.
     */
    public function test_reset_follows_site_default_and_keeps_core_choice(): void {
        if (!class_exists(\theme_boost\colour_mode::class)) {
            $this->markTestSkipped('Core colour mode needs Moodle 5.3');
        }
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('enablecolourmodes', 1, 'theme_boost');
        // Cream, then dark through core's own switcher: Reset does not undo core's choice.
        preferences::set('colour', 'cream');
        set_user_preference(\theme_boost\colour_mode::PREFERENCE, 'dark');
        preferences::reset();
        $this->assertSame('dark', get_user_preferences(\theme_boost\colour_mode::PREFERENCE));
        // A dark site default is handed to core on Reset, as choosing Dark would be.
        unset_user_preference(\theme_boost\colour_mode::PREFERENCE);
        set_config('default_colour', 'dark', 'local_accessibility');
        preferences::set('colour', 'cream');
        preferences::reset();
        $this->assertSame('dark', get_user_preferences(\theme_boost\colour_mode::PREFERENCE));
    }

    /**
     * Without core colour mode, Reset touches no core preference.
     */
    public function test_reset_without_core_colour_mode(): void {
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('enablecolourmodes', 0, 'theme_boost');
        set_user_preference('theme_boost_colourmode', 'dark');
        preferences::set('colour', 'dark');
        preferences::reset();
        $this->assertSame('default', preferences::get('colour'));
        $this->assertSame('dark', get_user_preferences('theme_boost_colourmode'));
    }

    /**
     * Dark chosen while core colour modes were off keeps the plugin's own Dark after an admin turns them on.
     */
    public function test_dark_chosen_before_core_enabled_is_kept(): void {
        if (!class_exists(\theme_boost\colour_mode::class)) {
            $this->markTestSkipped('Core colour mode needs Moodle 5.3');
        }
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('enablecolourmodes', 0, 'theme_boost');
        preferences::set('colour', 'dark');
        $this->assertNull(get_user_preferences(\theme_boost\colour_mode::PREFERENCE));
        set_config('enablecolourmodes', 1, 'theme_boost');
        $this->assertTrue(colour_mode::core_dark_available());
        $this->assertFalse(colour_mode::core_is_dark());
        $attrs = preferences::html_attributes();
        $this->assertSame('dark', $attrs['data-a11y-colour']);
        $this->assertSame('dark', $attrs['data-bs-theme']);
    }

    /**
     * A guest's Dark is handed to core only when core's own cookie says dark.
     */
    public function test_guest_dark_follows_core_cookie(): void {
        if (!class_exists(\theme_boost\colour_mode::class)) {
            $this->markTestSkipped('Core colour mode needs Moodle 5.3');
        }
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setGuestUser();
        set_config('enablecolourmodes', 1, 'theme_boost');
        $_COOKIE[preferences::COOKIE] = json_encode(['colour' => 'dark']);
        try {
            $this->assertArrayHasKey('data-a11y-colour', preferences::html_attributes());
            $_COOKIE[\theme_boost\colour_mode::PREFERENCE] = 'dark';
            $this->assertArrayNotHasKey('data-a11y-colour', preferences::html_attributes());
        } finally {
            unset($_COOKIE[preferences::COOKIE], $_COOKIE[\theme_boost\colour_mode::PREFERENCE]);
        }
    }

    /**
     * Boost and its children count as Boost-based; other themes do not.
     */
    public function test_is_boost_based(): void {
        $this->assertTrue(colour_mode::is_boost_based((object) ['name' => 'boost', 'parents' => []]));
        $this->assertTrue(colour_mode::is_boost_based((object) ['name' => 'child', 'parents' => ['boost']]));
        $this->assertFalse(colour_mode::is_boost_based((object) ['name' => 'other', 'parents' => ['base']]));
        $this->assertFalse(colour_mode::is_boost_based((object) ['name' => 'other']));
    }

    /**
     * The page's theme decides, not the site setting: a non-Boost page theme means no hand-over.
     */
    public function test_page_theme_decides(): void {
        if (!class_exists(\theme_boost\colour_mode::class)) {
            $this->markTestSkipped('Core colour mode needs Moodle 5.3');
        }
        global $PAGE;
        $this->resetAfterTest();
        set_config('enablecolourmodes', 1, 'theme_boost');
        $this->assertTrue(colour_mode::core_dark_available());
        $ref = new \ReflectionProperty(\moodle_page::class, '_theme');
        $ref->setValue($PAGE, (object) ['name' => 'other', 'parents' => []]);
        $this->assertFalse(colour_mode::core_dark_available());
    }
}
