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
 * Hook callbacks.
 *
 * @package    local_accessibility
 * @copyright  2024 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_html_attributes::class,
        'callback' => [\local_accessibility\hook_callbacks::class, 'html_attributes'],
        // Below the default 100 so it runs after theme_boost's listener (higher priorities run first): on 5.3 that
        // listener sets data-bs-theme and data-colourmode, and the plugin's scheme must be the last to set them.
        'priority' => 50,
    ],
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => [\local_accessibility\hook_callbacks::class, 'before_http_headers'],
    ],
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => [\local_accessibility\hook_callbacks::class, 'footer'],
    ],
    [
        'hook' => \core_user\hook\extend_user_menu::class,
        'callback' => [\local_accessibility\hook_callbacks::class, 'user_menu'],
    ],
];
