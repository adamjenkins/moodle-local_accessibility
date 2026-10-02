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
 * Uninstall step.
 *
 * @package     local_accessibility
 * @category    upgrade
 * @copyright   2026 Adam Jenkins <adam@wisecat.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Delete every user's local_accessibility_* preferences (feature values, custom scheme, first-visit marker).
 *
 * Users' settings live in user_preferences, which core's plugin uninstall leaves alone. Core's own
 * theme_boost_colourmode preference, which the Dark scheme may have set, belongs to core and is kept.
 *
 * @return bool
 */
function xmldb_local_accessibility_uninstall() {
    global $DB;
    $like = $DB->sql_like('name', ':name');
    $DB->delete_records_select('user_preferences', $like, ['name' => $DB->sql_like_escape('local_accessibility_') . '%']);
    return true;
}
