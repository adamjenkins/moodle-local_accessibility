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

/**
 * Fresh-install step: enable every feature.
 *
 * @package     local_accessibility
 * @category    upgrade
 * @copyright   2026 Adam Jenkins <adam@wisecat.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Give every feature an enabled row, in registry order, so a fresh site shows the panel without an admin visit.
 *
 * Rows are written directly rather than through preferences::sync_features_table(): that purges the 'enabled'
 * cache, whose definition is not registered yet while this plugin is being installed.
 *
 * @return bool
 */
function xmldb_local_accessibility_install() {
    global $DB;
    $existing = $DB->get_records_menu('local_accessibility_widgets', null, '', 'name, id');
    $seq = (int) $DB->get_field_sql('SELECT MAX(sequence) FROM {local_accessibility_widgets}');
    foreach (array_keys(\local_accessibility\feature\registry::all()) as $id) {
        if (!isset($existing[$id])) {
            $DB->insert_record('local_accessibility_widgets', (object) ['name' => $id, 'enabled' => 1,
                'sequence' => ++$seq]);
        }
    }
    return true;
}
