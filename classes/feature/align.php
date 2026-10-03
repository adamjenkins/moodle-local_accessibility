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

namespace local_accessibility\feature;

/**
 * Text alignment in page content (spec §3).
 *
 * @package    local_accessibility
 * @copyright  2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class align extends base {
    /** @var array<string, string> Option icons; the site default shows "undo". */
    protected const ICONS = [
        'default' => 'fa-rotate-left',
        'left' => 'fa-align-left',
        'center' => 'fa-align-center',
        'right' => 'fa-align-right',
        'justify' => 'fa-align-justify',
    ];

    /**
     * Stable id, stored in preferences.
     *
     * @return string
     */
    public function id(): string {
        return 'align';
    }

    /**
     * Allowed values in display order; the first is off/default.
     *
     * @return string[]
     */
    public function values(): array {
        return ['default', 'left', 'center', 'right', 'justify'];
    }

    /**
     * Font Awesome icon name for the tile.
     *
     * @return string
     */
    public function icon(): string {
        return 'fa-align-left';
    }
}
