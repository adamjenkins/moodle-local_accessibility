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

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_html_attributes;
use core\hook\output\before_http_headers;
use core_user\hook\extend_user_menu;

/**
 * Hook callbacks (spec §7.1).
 *
 * @package    local_accessibility
 * @copyright  2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright  2024 Bartlomiej Jencz <bartlomiej.jencz@p.lodz.pl>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /** @var string[] Layouts that never get the panel (spec §4.1). */
    private const SUPPRESSED_LAYOUTS = ['embedded', 'popup', 'frametop'];

    /**
     * Whether this page gets nothing from the plugin.
     *
     * @param \moodle_page $page
     * @return bool
     */
    public static function is_suppressed(\moodle_page $page): bool {
        return self::inactive() || in_array($page->pagelayout, self::SUPPRESSED_LAYOUTS, true);
    }

    /**
     * Whether the plugin must do nothing on any page: escape hatch, install or upgrade running, or
     * the code is on disk but the plugin is not installed yet (core loads db/hooks.php regardless).
     *
     * @return bool
     */
    private static function inactive(): bool {
        global $CFG;
        return !empty($CFG->local_accessibility_disabled)
            || !empty($CFG->upgraderunning)
            || during_initial_install()
            || !get_config('local_accessibility', 'version');
    }

    /**
     * Whether the keyboard shortcut is on (default on when the setting is unset).
     *
     * @return bool
     */
    public static function shortcut_enabled(): bool {
        return get_config('local_accessibility', 'shortcut') !== '0';
    }

    /**
     * Add data-a11y-* attributes before the page renders.
     *
     * @param before_html_attributes $hook
     * @return void
     */
    public static function html_attributes(before_html_attributes $hook): void {
        global $PAGE;
        if (self::is_suppressed($PAGE)) {
            return;
        }
        foreach (preferences::html_attributes() as $name => $value) {
            $hook->add_attribute($name, $value);
        }
    }

    /**
     * Load the panel module (the stylesheet is the plugin's styles.css, aggregated by the theme).
     *
     * @param before_http_headers $hook
     * @return void
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $PAGE;
        if (self::is_suppressed($PAGE) || !preferences::enabled_ids()) {
            return;
        }
        $PAGE->requires->js_call_amd('local_accessibility/panel', 'init', [[
            'guest' => preferences::uses_cookie(),
            'cookie' => preferences::COOKIE,
            'cookieattributes' => colour_mode::cookie_attributes(),
            'shortcut' => self::shortcut_enabled(),
            'initialised' => !preferences::uses_cookie() && get_user_preferences('local_accessibility_initialised'),
        ]]);
    }

    /**
     * Render the launcher and the panel.
     *
     * @param before_footer_html_generation $hook
     * @return void
     */
    public static function footer(before_footer_html_generation $hook): void {
        global $PAGE, $OUTPUT;
        if (self::is_suppressed($PAGE) || !preferences::enabled_ids()) {
            return;
        }
        $hook->add_html($OUTPUT->render(new output\panel()));
    }

    /**
     * Add the user menu entry when the launcher mode includes it.
     *
     * @param extend_user_menu $hook
     * @return void
     */
    public static function user_menu(extend_user_menu $hook): void {
        if (self::inactive()) {
            return;
        }
        $mode = get_config('local_accessibility', 'launcher') ?: 'both';
        if ($mode === 'floating' || !preferences::enabled_ids()) {
            return;
        }
        $hook->add_navitem((object) [
            'itemtype' => 'link',
            'url' => new \moodle_url('#local-accessibility-panel'),
            'title' => get_string('accessibilitysettings', 'local_accessibility'),
            'titleidentifier' => 'accessibilitysettings,local_accessibility',
        ]);
    }
}
