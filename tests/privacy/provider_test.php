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
        $choices = ['lineheight' => '180', 'letterspacing' => '12', 'wordspacing' => '16', 'cursor' => 'large'];
        foreach ($choices as $id => $value) {
            \local_accessibility\preferences::set($id, $value);
        }
        $scheme = \local_accessibility\colour\scheme::custom('#fbf3df', '#1f1f1f', '#0b4f8a', false);
        \local_accessibility\preferences::set_custom_scheme($scheme);
        provider::export_user_preferences($u->id);
        $prefs = writer::with_context(\context_system::instance())->get_user_preferences('local_accessibility');
        $this->assertSame('150', $prefs->local_accessibility_size->value);
        // The spacing features and the cursor added in 3.0 are exported like every other feature.
        foreach ($choices as $id => $value) {
            $this->assertSame($value, $prefs->{'local_accessibility_' . $id}->value, $id);
        }
        // Every declared preference that is set is exported.
        $this->assertObjectHasProperty('local_accessibility_colourcustom', $prefs);
        $this->assertObjectHasProperty('local_accessibility_initialised', $prefs);
    }

    /**
     * Metadata declares the preferences and the guest cookie.
     */
    public function test_metadata(): void {
        $c = provider::get_metadata(new \core_privacy\local\metadata\collection('local_accessibility'));
        $names = array_map(fn($i) => $i->get_name(), $c->get_collection());
        $this->assertContains('local_accessibility_size', $names);
        foreach (['lineheight', 'letterspacing', 'wordspacing', 'cursor'] as $id) {
            $this->assertContains('local_accessibility_' . $id, $names);
        }
        // The 3.0 development spacing preference is replaced by the three spacing features.
        $this->assertNotContains('local_accessibility_spacing', $names);
        $this->assertContains('local_accessibility_colourcustom', $names);
        $this->assertContains('local_accessibility_initialised', $names);
        $this->assertContains('local_accessibility', $names);
        // The 2.x table is dropped by the 3.0 upgrade step.
        $this->assertNotContains('local_accessibility_configs', $names);
    }
}
