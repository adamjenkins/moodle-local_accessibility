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

namespace local_accessibility\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy tests.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Preferences are exported.
     */
    public function test_export_user_preferences(): void {
        $this->resetAfterTest();
        \local_accessibility\preferences::sync_features_table();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);
        \local_accessibility\preferences::set('size', '150');
        provider::export_user_preferences($u->id);
        $prefs = writer::with_context(\context_system::instance())->get_user_preferences('local_accessibility');
        $this->assertSame('150', $prefs->local_accessibility_size->value);
    }

    /**
     * Metadata declares the preferences and the guest cookie.
     */
    public function test_metadata(): void {
        $c = provider::get_metadata(new \core_privacy\local\metadata\collection('local_accessibility'));
        $names = array_map(fn($i) => $i->get_name(), $c->get_collection());
        $this->assertContains('local_accessibility_size', $names);
        $this->assertContains('local_accessibility_colourcustom', $names);
        $this->assertContains('local_accessibility_initialised', $names);
        $this->assertContains('local_accessibility', $names);
    }

    /**
     * The legacy widget config table (dropped later) is still exported, listed and deleted.
     */
    public function test_legacy_configs_table(): void {
        global $DB;
        $this->resetAfterTest();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $sys = \context_system::instance();
        foreach ([$u1, $u2] as $u) {
            $DB->insert_record('local_accessibility_configs', ['widget' => 'x', 'configvalue' => '1', 'userid' => $u->id]);
        }
        $this->assertContainsEquals($sys->id, provider::get_contexts_for_userid($u1->id)->get_contextids());

        $list = new userlist($sys, 'local_accessibility');
        provider::get_users_in_context($list);
        $this->assertEqualsCanonicalizing([$u1->id, $u2->id], $list->get_userids());

        $this->export_context_data_for_user($u1->id, $sys, 'local_accessibility');
        $this->assertTrue(writer::with_context($sys)->has_any_data());

        provider::delete_data_for_user(new approved_contextlist($u1, 'local_accessibility', [$sys->id]));
        $this->assertFalse($DB->record_exists('local_accessibility_configs', ['userid' => $u1->id]));
        $this->assertTrue($DB->record_exists('local_accessibility_configs', ['userid' => $u2->id]));

        provider::delete_data_for_users(new approved_userlist($sys, 'local_accessibility', [$u2->id]));
        $this->assertSame(0, $DB->count_records('local_accessibility_configs'));
    }
}
