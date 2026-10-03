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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/legacy_configs.php');

/**
 * Tests for admin validation and widget subplugin cleanup.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\admin\setting_numericdefault
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
     * A numeric site default is empty or a value the feature accepts under the site's limits, checked by the feature.
     */
    public function test_numeric_default_validation(): void {
        $this->resetAfterTest();
        $ls = new admin\setting_numericdefault(feature\registry::get('letterspacing'));
        $size = new admin\setting_numericdefault(feature\registry::get('size'));
        foreach (['', '  ', '12', '-5', '-500', '500', 'default'] as $ok) {
            $this->assertTrue($ls->validate($ok), "'$ok'");
        }
        foreach (['501', '-501', '1e3', '12.5', '--1', 'x', '0.12', '012'] as $bad) {
            $this->assertIsString($ls->validate($bad), $bad);
        }
        $this->assertTrue($size->validate('175'));
        $this->assertTrue($size->validate('1'));
        $this->assertIsString($size->validate('0'));
        $this->assertIsString($size->validate('1001'));
        // Restricted to non-negative values, a negative default is refused.
        set_config('numericlimits', 'nonnegative', 'local_accessibility');
        $this->assertIsString($ls->validate('-5'));
        $this->assertTrue($ls->validate('0'));
        $this->assertIsString($size->validate('5'));
        // A stored non-numeric default shows as empty; a stored number shows as itself.
        set_config('default_letterspacing', 'default', 'local_accessibility');
        $this->assertSame('', $ls->get_setting());
        set_config('default_letterspacing', '12', 'local_accessibility');
        $this->assertSame('12', $ls->get_setting());
        $this->assertSame('', $ls->write_setting(''));
        $this->assertSame('', get_config('local_accessibility', 'default_letterspacing'));
        $this->assertNotSame('', $ls->write_setting('-5'));
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
            $this->assertContains(feature\registry::get($id)->lock_name(), $names);
        }
        // One lock per tile: the three spacing features share lock_spacing (spec §5).
        $this->assertContains('lock_spacing', $names);
        $this->assertSame(array_search('default_wordspacing', $names) + 1, array_search('lock_spacing', $names));
        $this->assertContains('lock_cursor', $names);
        foreach (['lineheight', 'letterspacing', 'wordspacing'] as $id) {
            $this->assertNotContains('lock_' . $id, $names);
        }
        $locks = array_values(array_filter($names, fn($n) => str_starts_with($n, 'lock_')));
        // In tile order; read keeps having neither a site default nor a lock.
        $tiles = array_values(array_diff(array_keys(feature\registry::tiles(array_keys(feature\registry::all()))), ['read']));
        $this->assertSame($tiles, array_map(fn($n) => substr($n, 5), $locks));
        $this->assertSame(
            get_string('lockfeature', 'local_accessibility', get_string('feature_spacing', 'local_accessibility')),
            (string) $settings['lock_spacing']->visiblename
        );
        // The numeric defaults take a number, empty for the feature's own default; the limits default to unlimited.
        foreach (['size', 'lineheight', 'letterspacing', 'wordspacing', 'narrow'] as $id) {
            $this->assertInstanceOf(admin\setting_numericdefault::class, $settings['default_' . $id], $id);
            $this->assertSame('', $settings['default_' . $id]->get_defaultsetting(), $id);
        }
        $this->assertSame('unlimited', $settings['numericlimits']->get_defaultsetting());
        $settings['numericlimits']->load_choices();
        $this->assertSame(['unlimited', 'nonnegative'], array_keys($settings['numericlimits']->choices));
        $this->assertSame('large', array_keys($settings['default_cursor']->choices)[1]);
        // Every built-in font is available by default; uploads go to the system fonts area (spec §4).
        $available = $settings['fonts_available'];
        $this->assertInstanceOf(\admin_setting_configmulticheckbox::class, $available);
        $available->load_choices();
        $builtin = array_merge(local\fonts::BUNDLED, local\fonts::DEVICE);
        $this->assertEqualsCanonicalizing($builtin, array_keys($available->choices));
        $this->assertEqualsCanonicalizing($builtin, array_keys(array_filter($available->get_defaultsetting())));
        $this->assertSame('Lexend', (string) $available->choices['lexend']);
        $uploaded = $settings['fonts_uploaded'];
        $this->assertInstanceOf(\admin_setting_configstoredfile::class, $uploaded);
        $this->assertSame('local_accessibility', $uploaded->plugin);
        $this->assertSame('fonts', (new \ReflectionProperty($uploaded, 'filearea'))->getValue($uploaded));
        $options = (new \ReflectionProperty($uploaded, 'options'))->getValue($uploaded);
        $this->assertSame(20, $options['maxfiles']);
        $this->assertSame(['.woff2', '.woff', '.ttf', '.otf'], $options['accepted_types']);
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
        // The 3.0 upgrade step uninstalls the subplugins while the 2.x table still exists (ruling R10).
        $legacy = local_accessibility_create_legacy_configs_table();
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
        $DB->get_manager()->drop_table($legacy);
        $this->assertFalse($cache->get('ids'));

        // A widget name that is also a 3.0 feature id leaves the feature's row alone.
        preferences::sync_features_table();
        $info->name = 'size';
        $info->uninstall_cleanup();
        $this->assertTrue($DB->record_exists('local_accessibility_widgets', ['name' => 'size']));
    }
}
