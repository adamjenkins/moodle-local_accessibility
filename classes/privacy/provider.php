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
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_accessibility\feature\registry;

/**
 * Privacy provider: settings are user preferences; guests keep a browser cookie.
 *
 * The legacy local_accessibility_configs table is still declared, exported and deleted here until the
 * upgrade step that drops it; remove that handling together with the table.
 *
 * @package     local_accessibility
 * @copyright   2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright   2026 Adam Jenkins <adam@wisecat.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    // This plugin has some sitewide user preferences to export.
    \core_privacy\local\request\user_preference_provider,
    core_userlist_provider {
    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @throws dml_exception
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $systemcontext = \context_system::instance();
        $user = $contextlist->get_user();
        if (!$contextlist->valid() || $contextlist->current()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $rs = $DB->get_records_sql(
            <<<SQL
                SELECT c.widget, c.configvalue, c.userid
                FROM {local_accessibility_configs} c
                WHERE c.userid = :userid
                ORDER BY c.widget, c.configvalue, c.userid
            SQL,
            ['userid' => $user->id]
        );
        if (count($rs) == 0) {
            return;
        }
        foreach ($rs as $config) {
            $contextdata = (object) [
                'widget' => $config->widget,
                'configvalue' => $config->configvalue,
                'userid' => transform::user($config->userid),
            ];
            writer::with_context($systemcontext)->export_data(["local_accessibility_config"], $contextdata);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     * @throws dml_exception
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        // We just delete all data from the system context since we don't have other assignments?
        $DB->delete_records('local_accessibility_configs');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     * @throws dml_exception
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!$contextlist->valid() || $contextlist->current()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $DB->delete_records('local_accessibility_configs', ['userid' => $contextlist->get_user()->id]);
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!is_a($context, \context_system::class)) {
            return;
        }
        // Since we only have one system context all data lay here!
        $sql = "SELECT userid FROM {local_accessibility_configs}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $sql = "userid {$insql}";
        $DB->delete_records_select('local_accessibility_configs', $sql, $inparams);
    }

    /**
     * Returns metadata about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_accessibility_configs',
            [
                'widget' => 'privacy:metadata:configs:widget',
                'configvalue' => 'privacy:metadata:configs:configvalue',
                'userid' => 'privacy:metadata:configs:userid',
            ],
            'privacy:metadata:configs'
        );
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
        $names[] = 'local_accessibility_colourcustom';
        foreach ($names as $name) {
            $v = get_user_preferences($name, null, $userid);
            if ($v !== null) {
                writer::export_user_preference(
                    'local_accessibility',
                    $name,
                    $v,
                    get_string('privacy:metadata:preference', 'local_accessibility')
                );
            }
        }
    }
}
