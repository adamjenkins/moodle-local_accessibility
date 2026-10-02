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
 * Tests for the fresh-install step.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_local_accessibility_install
 */
final class install_test extends \advanced_testcase {
    /**
     * A fresh install (the PHPUnit site is one) enables every feature, in registry order, with no admin visit.
     */
    public function test_fresh_install_enables_every_feature(): void {
        global $DB;
        $rows = $DB->get_records('local_accessibility_widgets', null, 'sequence', 'name, enabled, sequence');
        $this->assertSame(array_keys(feature\registry::all()), array_keys($rows));
        foreach ($rows as $row) {
            $this->assertEquals(1, $row->enabled);
        }
        $this->assertSame(array_keys(feature\registry::all()), preferences::enabled_ids());
    }

    /**
     * The install function adds missing rows and leaves existing ones (and their order) alone.
     */
    public function test_install_function_is_idempotent(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/accessibility/db/install.php');
        $DB->delete_records('local_accessibility_widgets');
        $DB->insert_record('local_accessibility_widgets', (object) ['name' => 'links', 'enabled' => 0, 'sequence' => 1]);
        xmldb_local_accessibility_install();
        xmldb_local_accessibility_install();
        $this->assertEquals(count(feature\registry::all()), $DB->count_records('local_accessibility_widgets'));
        $this->assertEquals(0, $DB->get_field('local_accessibility_widgets', 'enabled', ['name' => 'links']));
        $this->assertEquals(1, $DB->get_field('local_accessibility_widgets', 'sequence', ['name' => 'links']));
    }
}
