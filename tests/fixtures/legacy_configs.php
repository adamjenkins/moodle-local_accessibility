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
 * Test fixture: the 2.x settings table, which 3.0 no longer installs.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * (Re)create the 2.x local_accessibility_configs table, as db/install.xml declared it up to 2.4.1.
 *
 * A leftover from an aborted test run is dropped first, so the table always starts empty.
 * Callers drop it again at the end, because the PHPUnit reset only knows the installed tables.
 *
 * @return xmldb_table
 */
function local_accessibility_create_legacy_configs_table(): xmldb_table {
    global $DB;
    $dbman = $DB->get_manager();
    $table = new xmldb_table('local_accessibility_configs');
    if ($dbman->table_exists($table)) {
        $dbman->drop_table($table);
    }
    $table->add_field('id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
    $table->add_field('widget', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL);
    $table->add_field('configvalue', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
    $table->add_key('pk_id', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_index('idx_widget', XMLDB_INDEX_NOTUNIQUE, ['widget']);
    $table->add_index('idx_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
    $dbman->create_table($table);
    return $table;
}
