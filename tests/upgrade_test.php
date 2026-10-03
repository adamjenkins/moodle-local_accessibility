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
 * @covers     \local_accessibility\local\legacy
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
        $this->assertSame('outline', get_user_preferences('local_accessibility_links', null, $u1->id));
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
        // 1.75 keeps its own value: text size takes any whole percentage.
        $this->assertSame('175', get_user_preferences('local_accessibility_size', null, $u1->id));
        $this->assertSame('custom', get_user_preferences('local_accessibility_colour', null, $u1->id));
        $s = colour\scheme::from_json(get_user_preferences('local_accessibility_colourcustom', null, $u1->id));
        $this->assertSame('#ffff00', $s->text);
        $this->assertSame('#000000', $s->ramp['page']);
        $this->assertFalse($dbman->table_exists('local_accessibility_configs'));

        // Widget rows: renamed with their enabled flag and order, the rest appended, no old names left.
        $rows = $DB->get_records('local_accessibility_widgets', null, 'sequence', 'name, enabled, sequence');
        $this->assertSame(['colour', 'size', 'letterspacing', 'lineheight'], array_slice(array_keys($rows), 0, 4));
        $this->assertArrayNotHasKey('spacing', $rows);
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
        $this->assertEquals(2026100600, get_config('local_accessibility', 'version'));
    }

    /**
     * The 2026100600 step on a 3.0 development site: feature rows, user preferences and admin config move to the
     * choices values (spec §6), profiles JSON is left alone (plan D12), and running the helpers again changes nothing.
     */
    public function test_upgrade_from_30dev(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('version', 2026100510, 'local_accessibility');

        // The 13 feature rows of 3.0-dev, the admin having disabled spacing.
        $DB->delete_records('local_accessibility_widgets');
        $old = ['size', 'font', 'spacing', 'align', 'colour', 'narrow', 'links', 'images', 'guide', 'motion', 'read',
            'saturation', 'focus'];
        foreach ($old as $i => $name) {
            $DB->insert_record('local_accessibility_widgets', (object) ['name' => $name,
                'enabled' => $name === 'spacing' ? 0 : 1, 'sequence' => $i + 1]);
        }
        $cache = \cache::make('local_accessibility', 'enabled');
        $cache->set('ids', ['stale']);

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();
        $prefs = [
            [$u1, ['spacing' => 'extra', 'align' => 'on', 'links' => 'on', 'images' => 'on', 'focus' => 'cursor',
                'size' => '175', 'font' => 'dyslexic']],
            [$u2, ['spacing' => 'normal']],
            [$u3, ['size' => '150']],
        ];
        foreach ($prefs as [$u, $values]) {
            foreach ($values as $id => $v) {
                set_user_preference('local_accessibility_' . $id, $v, $u);
            }
        }
        set_config('default_spacing', 'wcag', 'local_accessibility');
        set_config('lock_spacing', 1, 'local_accessibility');
        set_config('default_focus', 'cursor', 'local_accessibility');
        set_config('lock_focus', 1, 'local_accessibility');
        set_config('default_links', 'on', 'local_accessibility');
        set_config('default_size', '175', 'local_accessibility');
        // What saving the settings page stores for the new features: a default must not block the mapping.
        set_config('default_lineheight', 'default', 'local_accessibility');
        set_config('default_cursor', 'off', 'local_accessibility');
        set_config('lock_cursor', 0, 'local_accessibility');
        $profiles = json_encode(['p' => ['name' => 'P', 'values' => ['spacing' => 'wcag', 'links' => 'on']]]);
        set_config('profiles', $profiles, 'local_accessibility');

        $this->assertTrue(xmldb_local_accessibility_upgrade(2026100510));

        // Rows: spacing became lineheight in place, the other two follow it with its enabled flag, cursor follows focus.
        $rows = $DB->get_records('local_accessibility_widgets', null, 'sequence', 'name, enabled, sequence');
        $this->assertSame(['size', 'font', 'lineheight', 'letterspacing', 'wordspacing', 'align', 'colour', 'narrow',
            'links', 'images', 'guide', 'motion', 'read', 'saturation', 'focus', 'cursor'], array_keys($rows));
        foreach (['lineheight', 'letterspacing', 'wordspacing'] as $id) {
            $this->assertEquals(0, $rows[$id]->enabled, $id);
        }
        $this->assertEquals(1, $rows['cursor']->enabled);
        $this->assertEquals($rows['focus']->sequence + 1, $rows['cursor']->sequence);
        $this->assertFalse($cache->get('ids'));

        // Preferences.
        $this->assertSame(['align' => 'left', 'cursor' => 'large', 'focus' => 'ring', 'font' => 'dyslexic',
            'images' => 'hide', 'letterspacing' => '16', 'lineheight' => '180', 'links' => 'outline', 'size' => '175',
            'wordspacing' => '24'], $this->prefs($u1->id));
        $this->assertSame([], $this->prefs($u2->id));
        $this->assertSame(['size' => '150'], $this->prefs($u3->id));

        // Admin defaults and locks.
        $c = get_config('local_accessibility');
        $this->assertSame('150', $c->default_lineheight);
        $this->assertSame('12', $c->default_letterspacing);
        $this->assertSame('16', $c->default_wordspacing);
        $this->assertFalse(property_exists($c, 'default_spacing'));
        $this->assertSame('1', $c->lock_spacing);
        $this->assertSame('ring', $c->default_focus);
        $this->assertSame('large', $c->default_cursor);
        $this->assertSame('1', $c->lock_cursor);
        $this->assertSame('1', $c->lock_focus);
        $this->assertSame('outline', $c->default_links);
        // Text size takes any whole percentage, so the development value 175 is no longer renamed.
        $this->assertSame('175', $c->default_size);
        // Profiles are mapped when read, never rewritten (plan D12).
        $this->assertSame($profiles, $c->profiles);
        $this->assertEquals(2026100600, $c->version);

        // Re-running the step's helpers (an interrupted upgrade restarts the step) changes nothing.
        $snapshot = $this->snapshot();
        local\legacy::upgrade_feature_rows();
        local\legacy::upgrade_config();
        local\legacy::upgrade_user_preferences();
        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * The step also handles rows the runtime sync appended before the upgrade ran: an existing lineheight row takes
     * the place and flag of spacing, and the other two spacing rows move next to it.
     */
    public function test_upgrade_feature_rows_after_sync(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('local_accessibility_widgets');
        $order = ['size', 'spacing', 'focus', 'lineheight', 'letterspacing', 'wordspacing', 'cursor'];
        foreach ($order as $i => $name) {
            $DB->insert_record('local_accessibility_widgets', (object) ['name' => $name,
                'enabled' => $name === 'spacing' || $name === 'focus' ? 0 : 1, 'sequence' => $i + 1]);
        }

        local\legacy::upgrade_feature_rows();

        $rows = $DB->get_records('local_accessibility_widgets', null, 'sequence', 'name, enabled');
        $first = ['size', 'lineheight', 'letterspacing', 'wordspacing', 'focus', 'cursor'];
        $this->assertSame($first, array_slice(array_keys($rows), 0, 6));
        $this->assertArrayNotHasKey('spacing', $rows);
        foreach (['lineheight', 'letterspacing', 'wordspacing', 'focus'] as $id) {
            $this->assertEquals(0, $rows[$id]->enabled, $id);
        }
        $this->assertEquals(1, $rows['cursor']->enabled);
        $this->assertEqualsCanonicalizing(array_keys(feature\registry::all()), array_keys($rows));
    }

    /**
     * A user's local_accessibility preferences, without the prefix.
     *
     * @param int $userid
     * @return array<string, string> feature id => value, sorted by id
     */
    private function prefs(int $userid): array {
        global $DB;
        $like = 'userid = ? AND ' . $DB->sql_like('name', '?');
        $out = [];
        $records = $DB->get_records_select('user_preferences', $like, [$userid, 'local\\_accessibility\\_%'], '', 'name, value');
        foreach ($records as $r) {
            $out[substr($r->name, strlen('local_accessibility_'))] = $r->value;
        }
        ksort($out);
        return $out;
    }

    /**
     * Everything the step writes: feature rows, the plugin's config and the plugin's user preferences.
     *
     * @return array
     */
    private function snapshot(): array {
        global $DB;
        $like = $DB->sql_like('name', '?');
        $rows = [
            $DB->get_records('local_accessibility_widgets', null, 'id', 'id, name, enabled, sequence'),
            $DB->get_records('config_plugins', ['plugin' => 'local_accessibility'], 'id', 'id, name, value'),
            $DB->get_records_select('user_preferences', $like, ['local\\_accessibility\\_%'], 'id', 'id, userid, name, value'),
        ];
        // Plain arrays, so assertSame compares values rather than object identity.
        return array_map(fn($set) => array_map(fn($r) => (array) $r, $set), $rows);
    }
}
