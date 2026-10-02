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

global $CFG;
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/local/accessibility/db/upgrade.php');
require_once(__DIR__ . '/fixtures/legacy_configs.php');

/**
 * End-to-end check of the 3.0 migration against seeded 2.x data.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\local\migration
 * @covers     ::xmldb_local_accessibility_upgrade
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Drop the fixture table if a test left it behind.
     */
    protected function tearDown(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_accessibility_configs');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        parent::tearDown();
    }

    /**
     * Two users' old rows become preferences; deleted users and the guest are skipped.
     */
    public function test_run(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        local_accessibility_create_legacy_configs_table();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();
        $u4 = $this->getDataGenerator()->create_user();
        $rows = [
            [$u1->id, 'fontsize', '1.5'], [$u2->id, 'fontface', 'dyslexic'], [$u1->id, 'linkhighlight', '1'],
            [$u3->id, 'fontsize', '2'], [$CFG->siteguest, 'fontsize', '2'], [$u4->id, 'textalignment', 'justify'],
        ];
        foreach ($rows as [$u, $w, $v]) {
            $DB->insert_record('local_accessibility_configs', (object) ['userid' => $u, 'widget' => $w, 'configvalue' => $v]);
        }
        delete_user($u3);

        local\migration::run();

        $this->assertSame('150', get_user_preferences('local_accessibility_size', null, $u1->id));
        $this->assertSame('on', get_user_preferences('local_accessibility_links', null, $u1->id));
        $this->assertSame('1', get_user_preferences('local_accessibility_initialised', null, $u1->id));
        $this->assertSame('dyslexic', get_user_preferences('local_accessibility_font', null, $u2->id));
        $this->assertNull(get_user_preferences('local_accessibility_size', null, $u2->id));
        $this->assertFalse($DB->record_exists('user_preferences', ['userid' => $u3->id, 'name' => 'local_accessibility_size']));
        $this->assertFalse($DB->record_exists('user_preferences', ['userid' => $CFG->siteguest,
            'name' => 'local_accessibility_size']));
        // Nothing mappable, but the user had saved settings: no preference except the initialised marker.
        $like = 'userid = ? AND ' . $DB->sql_like('name', '?');
        $names = $DB->get_fieldset_select('user_preferences', 'name', $like, [$u4->id, 'local_accessibility_%']);
        $this->assertSame(['local_accessibility_initialised'], $names);

        // Running it again (an interrupted upgrade restarts the step) changes nothing.
        local\migration::run();
        $this->assertSame('150', get_user_preferences('local_accessibility_size', null, $u1->id));
        $this->assertSame(1, $DB->count_records('user_preferences', ['userid' => $u1->id,
            'name' => 'local_accessibility_size']));
    }

    /**
     * Without the old table (fresh 3.0 install, or step re-run after the drop) the migration does nothing.
     */
    public function test_run_without_table(): void {
        $this->resetAfterTest();
        local\migration::run();
        $this->assertTrue(true);
    }

    /**
     * The real upgrade step: settings become preferences BEFORE the old table is dropped, widget rows are renamed
     * to feature ids, missing subplugins are uninstalled and nothing aborts the upgrade.
     */
    public function test_upgrade_step(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();
        set_config('version', 2026100502, 'local_accessibility');

        // A 2.x site: settings in the old table, widget rows under the old names.
        local_accessibility_create_legacy_configs_table();
        $u1 = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_accessibility_configs', (object) ['userid' => $u1->id, 'widget' => 'fontsize',
            'configvalue' => '1.75']);
        $DB->insert_record('local_accessibility_configs', (object) ['userid' => $u1->id, 'widget' => 'backgroundcolour',
            'configvalue' => '#000000']);
        $DB->insert_record('local_accessibility_configs', (object) ['userid' => $u1->id, 'widget' => 'textcolour',
            'configvalue' => '#ffff00']);
        $DB->delete_records('local_accessibility_widgets');
        $seq = 0;
        $old = ['textcolour' => 1, 'fontsize' => 0, 'backgroundcolour' => 1, 'letterspacing' => 1, 'lineheight' => 1];
        foreach ($old as $name => $enabled) {
            $DB->insert_record('local_accessibility_widgets', (object) ['name' => $name, 'enabled' => $enabled,
                'sequence' => ++$seq]);
        }
        // A subplugin whose files are gone (the normal case after replacing the plugin folder).
        set_config('version', 2025021800, 'accessibility_letterspacing');
        set_config('someconfig', 'x', 'accessibility_letterspacing');
        \core_plugin_manager::reset_caches();
        $missing = \core_plugin_manager::instance()->get_plugin_info('accessibility_letterspacing');
        $this->assertSame(\core_plugin_manager::PLUGIN_STATUS_MISSING, $missing->get_status());
        $cache = \cache::make('local_accessibility', 'enabled');
        $cache->set('ids', ['stale']);

        $this->assertTrue(xmldb_local_accessibility_upgrade(2026100502));

        // Preferences exist and the table is gone: the migration read the table before the drop.
        $this->assertSame('175', get_user_preferences('local_accessibility_size', null, $u1->id));
        $this->assertSame('custom', get_user_preferences('local_accessibility_colour', null, $u1->id));
        $s = colour\scheme::from_json(get_user_preferences('local_accessibility_colourcustom', null, $u1->id));
        $this->assertSame('#ffff00', $s->text);
        $this->assertSame('#000000', $s->ramp['page']);
        $this->assertFalse($dbman->table_exists('local_accessibility_configs'));

        // Widget rows: renamed with their enabled flag and order, the rest appended, no old names left.
        $rows = $DB->get_records('local_accessibility_widgets', null, 'sequence', 'name, enabled, sequence');
        $this->assertSame(['colour', 'size', 'spacing'], array_slice(array_keys($rows), 0, 3));
        $this->assertEquals(0, $rows['size']->enabled);
        $this->assertEqualsCanonicalizing(array_keys(feature\registry::all()), array_keys($rows));
        $this->assertFalse($cache->get('ids'));

        // The missing subplugin is uninstalled. Ones whose files are still present stay installed for the admin.
        $this->assertFalse($DB->record_exists('config_plugins', ['plugin' => 'accessibility_letterspacing']));
        foreach (local\migration::OLD_WIDGETS as $w) {
            $info = \core_plugin_manager::instance()->get_plugin_info('accessibility_' . $w);
            if ($info !== null && $info->versiondisk !== null) {
                $this->assertNotFalse(get_config('accessibility_' . $w, 'version'), $w);
            } else {
                $this->assertFalse(get_config('accessibility_' . $w, 'version'), $w);
            }
        }
        $this->assertEquals(2026100510, get_config('local_accessibility', 'version'));
    }
}
