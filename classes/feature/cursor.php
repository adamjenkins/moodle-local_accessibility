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
 * Mouse pointer size: standard, large (64px) or extra large (96px) (spec §3).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cursor extends base {
    /** @var array<string, string> Option icons. */
    protected const ICONS = [
        'off' => 'fa-arrow-pointer',
        'large' => 'fa-computer-mouse',
        'xlarge' => 'fa-crosshairs',
    ];

    /**
     * Stable id, stored in preferences.
     *
     * @return string
     */
    public function id(): string {
        return 'cursor';
    }

    /**
     * Allowed values in display order; the first is off/default.
     *
     * @return string[]
     */
    public function values(): array {
        return ['off', 'large', 'xlarge'];
    }

    /**
     * Font Awesome icon name for the tile.
     *
     * @return string
     */
    public function icon(): string {
        return 'fa-arrow-pointer';
    }
}
