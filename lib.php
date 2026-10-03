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
 * Library callbacks.
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve an admin-uploaded font (spec §4). No login is required: fonts load on the login page for guests, and they
 * are site assets, not user data. Only font files in the system context's fonts area are served, with their font
 * MIME type (D7: core has no file type for fonts, so a stored file's own MIME would be document/unknown).
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false when the file is not served
 */
function local_accessibility_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    $r = \local_accessibility\local\fonts::serve_check($context, (string) $filearea, $args);
    if ($r === null) {
        return false;
    }
    $file = $r['file'];
    $options += ['immutable' => true];
    send_file($file, $file->get_filename(), YEARSECS, 0, false, false, $r['mimetype'], false, $options);
}
