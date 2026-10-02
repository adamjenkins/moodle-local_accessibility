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
 * Plugin upgrade steps are defined here.
 *
 * @package     local_accessibility
 * @category    upgrade
 * @copyright   2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute local_accessibility upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_accessibility_upgrade($oldversion) {
    global $DB, $CFG;
    /** @var \moodle_database $DB */ $DB;

    $dbman = $DB->get_manager();

    $dbxmlpath = $CFG->dirroot . '/local/accessibility/db/install.xml';

    if ($oldversion < 2023050600) {
        $dbman->install_from_xmldb_file($dbxmlpath);
        upgrade_plugin_savepoint(true, 2023050600, 'local', 'accessibility');
    }
    if ($oldversion < 2023051302) {
        $DB->delete_records('accessibility_userconfigs', []);
        if (!$dbman->table_exists('accessibility_enabledoptions')) {
            $dbman->install_one_table_from_xmldb_file($dbxmlpath, 'accessibility_enabledoptions');
        }
        upgrade_plugin_savepoint(true, 2023051302, 'local', 'accessibility');
    }
    if ($oldversion < 2023071300) {
        if ($dbman->table_exists('accessibility_enabledoptions')) {
            $dbman->drop_table(new xmldb_table('accessibility_enabledoptions'));
        }
        if ($dbman->table_exists('accessibility_userconfigs')) {
            $dbman->drop_table(new xmldb_table('accessibility_userconfigs'));
        }
        $dbman->install_from_xmldb_file($dbxmlpath);
        upgrade_plugin_savepoint(true, 2023071300, 'local', 'accessibility');
    }
    if ($oldversion < 2023103002) {
        $oldtable = new xmldb_table('accessibility_enabledwidgets');
        $newtable = new xmldb_table('accessibility_widgets');
        if ($dbman->table_exists($oldtable)) {
            $dbman->rename_table($oldtable, $newtable->getName());
        }
        if (!$dbman->field_exists($newtable, 'enabled')) {
            $field = new xmldb_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1, 'name');
            $dbman->add_field($newtable, $field);
        }
        // The 2.x lib.php helper that registered installed widgets here is gone: preferences::sync_features_table()
        // adds the built-in features' rows at runtime, and the 3.0 step below renames the old widget names.
        upgrade_plugin_savepoint(true, 2023103002, 'local', 'accessibility');
    }
    if ($oldversion < 2023110101) {
        $oldtable = new xmldb_table('accessibility_widgets');
        $newtable = new xmldb_table('local_accessibility_widgets');
        if ($dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
            $dbman->rename_table($oldtable, $newtable->getName());
        }
        $oldtable1 = new xmldb_table('accessibility_userconfigs');
        $oldtable2 = new xmldb_table('local_accessibility_userconfigs');
        $newtable = new xmldb_table('local_accessibility_configs');
        if ($dbman->table_exists($oldtable1) && !$dbman->table_exists($newtable)) {
            $dbman->rename_table($oldtable1, $newtable->getName());
        }
        if ($dbman->table_exists($oldtable2) && !$dbman->table_exists($newtable)) {
            $dbman->rename_table($oldtable2, $newtable->getName());
        }
        upgrade_plugin_savepoint(true, 2023110101, 'local', 'accessibility');
    }
    if ($oldversion < 2026080200) {
        // Configurations of the guest account are now kept in the session,
        // remove the records shared between all guest visitors left by the previous versions.
        if ($dbman->table_exists('local_accessibility_configs')) {
            $DB->delete_records('local_accessibility_configs', ['userid' => $CFG->siteguest]);
        }
        upgrade_plugin_savepoint(true, 2026080200, 'local', 'accessibility');
    }
    if ($oldversion < 2026100510) {
        // 3.0: the order matters (spec §9).
        // 1. Move the 2.x widget settings to user preferences, while the old table still exists.
        \local_accessibility\local\migration::run();

        // 2. Feature ids replace widget names in the enabled/order table, before the subplugins go:
        // their uninstall_cleanup() deletes rows still carrying an old widget name.
        \local_accessibility\local\migration::rename_widget_rows();

        // 3. Remove the 11 old subplugins whose code is gone (Task 1 spike: decision A, the type stays declared).
        \local_accessibility\local\migration::uninstall_old_subplugins();

        // 4. Drop the old table last.
        $table = new xmldb_table('local_accessibility_configs');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100510, 'local', 'accessibility');
    }

    return true;
}
