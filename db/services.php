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
 * Web services.
 *
 * @package     local_accessibility
 * @copyright   2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright   2026 Adam Jenkins <adam@wisecat.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_accessibility_save_preference' => [
        'classname' => \local_accessibility\external\save_preference::class,
        'description' => 'Save one accessibility preference for the current user.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_accessibility_save_custom_scheme' => [
        'classname' => \local_accessibility\external\save_custom_scheme::class,
        'description' => 'Save a custom colour scheme for the current user.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_accessibility_reset_preferences' => [
        'classname' => \local_accessibility\external\reset_preferences::class,
        'description' => 'Clear all accessibility preferences for the current user.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
