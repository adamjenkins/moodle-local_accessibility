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
 * Tests for the uninstall step.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_local_accessibility_uninstall
 */
final class uninstall_test extends \advanced_testcase {
    /**
     * Uninstalling deletes every user's local_accessibility_* preferences and nothing else.
     */
    public function test_uninstall_deletes_user_preferences(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        preferences::sync_features_table();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $this->setUser($u1);
        preferences::set('size', '150');
        preferences::set_custom_scheme(colour\scheme::custom('#14202b', '#e8eef3', '#8cc8ff', false));
        $this->setUser($u2);
        preferences::set('links', 'outline');
        set_user_preference('local_accessibilityx', 'kept', $u2);
        set_user_preference('theme_boost_colourmode', 'dark', $u2);
        $like = $DB->sql_like('name', ':name');
        $params = ['name' => $DB->sql_like_escape('local_accessibility_') . '%'];
        $this->assertGreaterThanOrEqual(5, $DB->count_records_select('user_preferences', $like, $params));

        require_once($CFG->dirroot . '/local/accessibility/db/uninstall.php');
        $this->assertTrue(xmldb_local_accessibility_uninstall());

        $this->assertSame(0, $DB->count_records_select('user_preferences', $like, $params));
        $kept = $DB->get_records_menu('user_preferences', ['userid' => $u2->id], '', 'name, value');
        $this->assertSame('kept', $kept['local_accessibilityx']);
        $this->assertSame('dark', $kept['theme_boost_colourmode']);
    }
}
