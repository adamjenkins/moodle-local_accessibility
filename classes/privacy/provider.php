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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use local_accessibility\feature\registry;

/**
 * Privacy provider: settings are user preferences; guests keep a browser cookie.
 *
 * The 2.x local_accessibility_configs table is migrated to preferences and dropped by the 3.0 upgrade step,
 * so the plugin stores no user data of its own beyond those preferences.
 *
 * @package     local_accessibility
 * @copyright   2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright   2026 Adam Jenkins <adam@wisecat.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    // This plugin has some sitewide user preferences to export.
    \core_privacy\local\request\user_preference_provider {
    /**
     * Returns metadata about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        foreach (array_keys(registry::all()) as $id) {
            $collection->add_user_preference('local_accessibility_' . $id, 'privacy:metadata:preference');
        }
        $collection->add_user_preference('local_accessibility_colourcustom', 'privacy:metadata:colourcustom');
        $collection->add_user_preference('local_accessibility_initialised', 'privacy:metadata:initialised');
        // Core 4.5 has no cookie-specific metadata type; an external location link is the closest.
        $collection->add_external_location_link(
            'local_accessibility',
            ['settings' => 'privacy:metadata:cookie:settings'],
            'privacy:metadata:cookie'
        );
        return $collection;
    }

    /**
     * Export the user's accessibility preferences.
     *
     * @param int $userid
     * @return void
     */
    public static function export_user_preferences(int $userid) {
        $names = array_map(fn($id) => 'local_accessibility_' . $id, array_keys(registry::all()));
        $descriptions = array_fill_keys($names, 'privacy:metadata:preference');
        $descriptions['local_accessibility_colourcustom'] = 'privacy:metadata:colourcustom';
        $descriptions['local_accessibility_initialised'] = 'privacy:metadata:initialised';
        foreach ($descriptions as $name => $description) {
            $v = get_user_preferences($name, null, $userid);
            if ($v !== null) {
                writer::export_user_preference(
                    'local_accessibility',
                    $name,
                    $v,
                    get_string($description, 'local_accessibility')
                );
            }
        }
    }
}
