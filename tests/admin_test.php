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
 * Tests for admin validation and widget subplugin cleanup.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\admin\setting_sitepresets
 * @covers     \local_accessibility\plugininfo\accessibility
 */
final class admin_test extends \advanced_testcase {
    /**
     * A passing preset is accepted.
     */
    public function test_site_preset_ok(): void {
        $s = new admin\setting_sitepresets();
        $this->assertTrue($s->validate(json_encode([
            ['name' => 'Ink', 'bg' => '#ffffff', 'text' => '#000000', 'link' => '#0000c0'],
        ])));
    }

    /**
     * Review focus 5: a preset passing on the page colour but failing on a darker shade is rejected, naming the shade.
     */
    public function test_site_preset_rejected_on_shade(): void {
        $s = new admin\setting_sitepresets();
        // Computed once with the committed colour\adjust (Ruling R7): #0b4f8a is 7.60:1 on the cream page
        // but 6.94:1 on surface1 and 5.75:1 on hover, so it passes on the page and fails on a derived shade.
        $page = '#fbf3df';
        $link = '#0b4f8a';
        $ramp = colour\adjust::ramp($page);
        $this->assertGreaterThanOrEqual(colour\contrast::AAA, colour\contrast::ratio($link, $page));
        $this->assertLessThan(colour\contrast::AAA, colour\contrast::worst($link, $ramp));
        $result = $s->validate(json_encode([['name' => 'Tight', 'bg' => $page, 'text' => '#1f1f1f', 'link' => $link]]));
        $this->assertIsString($result);
        $this->assertStringContainsString('Tight', $result);
        $this->assertStringContainsString('link', $result);
        $this->assertStringContainsString('surface1', $result);
    }

    /**
     * Garbage JSON is rejected.
     */
    public function test_site_preset_garbage(): void {
        $s = new admin\setting_sitepresets();
        $this->assertIsString($s->validate('[{"bg":"red"}]'));
        $this->assertIsString($s->validate('not json'));
        $this->assertIsString($s->validate('["#ffffff"]'));
        $this->assertTrue($s->validate('[]'));
    }

    /**
     * The settings tree builds with every expected setting and the features page.
     */
    public function test_settings_tree(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $root = admin_get_root(true, true);
        $page = $root->locate('local_accessibility');
        $this->assertInstanceOf(\admin_settingpage::class, $page);
        $this->assertInstanceOf(\admin_externalpage::class, $root->locate('local_accessibility_features'));
        $settings = [];
        foreach ((array) $page->settings as $setting) {
            $settings[$setting->name] = $setting;
        }
        $names = array_keys($settings);
        $this->assertContains('launcher', $names);
        $this->assertContains('shortcut', $names);
        $this->assertContains('sitepresets', $names);
        foreach (array_keys(feature\registry::all()) as $id) {
            if ($id === 'read') {
                $this->assertNotContains('default_read', $names);
                continue;
            }
            $this->assertContains('default_' . $id, $names);
            $this->assertContains('lock_' . $id, $names);
        }
        $this->assertSame('1', (string) $settings['shortcut']->get_defaultsetting());
        $this->assertSame('both', $settings['launcher']->get_defaultsetting());
    }

    /**
     * Ruling R10: uninstalling a legacy widget subplugin never deletes users' settings, and purges the enabled cache.
     */
    public function test_widget_uninstall_keeps_user_settings(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->insert_record('local_accessibility_widgets', (object) ['name' => 'fontsize', 'enabled' => 1, 'sequence' => 99]);
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_accessibility_configs', (object) ['userid' => $user->id, 'widget' => 'fontsize',
            'configvalue' => '150']);
        $cache = \cache::make('local_accessibility', 'enabled');
        $cache->set('ids', ['stale']);

        $info = (new \ReflectionClass(plugininfo\accessibility::class))->newInstanceWithoutConstructor();
        $info->type = 'accessibility';
        $info->name = 'fontsize';
        $info->uninstall_cleanup();

        $this->assertFalse($DB->record_exists('local_accessibility_widgets', ['name' => 'fontsize']));
        $this->assertEquals(1, $DB->count_records('local_accessibility_configs', ['widget' => 'fontsize']));
        $this->assertFalse($cache->get('ids'));

        // A widget name that is also a 3.0 feature id leaves the feature's row alone.
        preferences::sync_features_table();
        $info->name = 'size';
        $info->uninstall_cleanup();
        $this->assertTrue($DB->record_exists('local_accessibility_widgets', ['name' => 'size']));
    }
}
