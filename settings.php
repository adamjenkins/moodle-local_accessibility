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
 * Admin settings: launcher, shortcut, site defaults, locks, site colour schemes and the features page.
 *
 * @package     local_accessibility
 * @category    admin
 * @copyright   2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright   2026 Adam Jenkins <adam@wisecat.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    /** @var admin_root $ADMIN */
    $ADMIN->add('localplugins', new admin_category(
        'local_accessibility_cat',
        new lang_string('pluginname', 'local_accessibility')
    ));
    $page = new admin_settingpage('local_accessibility', new lang_string('settings'));
    if ($ADMIN->fulltree) {
        $page->add(new admin_setting_configselect(
            'local_accessibility/launcher',
            new lang_string('launcher', 'local_accessibility'),
            new lang_string('launcher_desc', 'local_accessibility'),
            'both',
            [
                'both' => new lang_string('launcher_both', 'local_accessibility'),
                'floating' => new lang_string('launcher_floating', 'local_accessibility'),
                'menu' => new lang_string('launcher_menu', 'local_accessibility'),
            ]
        ));
        $page->add(new admin_setting_configcheckbox(
            'local_accessibility/shortcut',
            new lang_string('shortcut', 'local_accessibility'),
            new lang_string('shortcut_desc', 'local_accessibility'),
            1
        ));
        $page->add(new admin_setting_heading(
            'local_accessibility/defaultsheading',
            new lang_string('defaults', 'local_accessibility'),
            new lang_string('defaults_desc', 'local_accessibility')
        ));
        foreach (\local_accessibility\feature\registry::all() as $id => $f) {
            if ($id === 'read') {
                continue;
            }
            $choices = [];
            foreach ($f->values() as $v) {
                if ($v !== 'custom') {
                    $choices[$v] = $f->value_label($v);
                }
            }
            $page->add(new admin_setting_configselect(
                "local_accessibility/default_$id",
                $f->label(),
                '',
                $f->default(),
                $choices
            ));
            $page->add(new admin_setting_configcheckbox(
                "local_accessibility/lock_$id",
                new lang_string('lockfeature', 'local_accessibility', $f->label()),
                '',
                0
            ));
        }
        $page->add(new \local_accessibility\admin\setting_sitepresets());
        // Task 17 restores this line when setting_profiles exists.
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found,moodle.Commenting.InlineComment.NotCapital
        // $page->add(new \local_accessibility\admin\setting_profiles());
    }
    $ADMIN->add('local_accessibility_cat', $page);
    $ADMIN->add('local_accessibility_cat', new admin_externalpage(
        'local_accessibility_features',
        new lang_string('managefeatures', 'local_accessibility'),
        new moodle_url('/local/accessibility/admin/features.php')
    ));
}
