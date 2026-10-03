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
 * Line width: limits the length of content lines (spec §3).
 *
 * Characters per line; stepping down from full width starts at 90.
 *
 * @package    local_accessibility
 * @copyright  2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class narrow extends numeric {
    /** @var string Default: full width. */
    protected const DEFAULT = 'off';
    /** @var int One press of − or +. */
    protected const STEP = 5;
    /** @var int Largest value. */
    protected const MAX = 300;
    /** @var int Smallest value while the site allows the full range. */
    protected const MIN_UNLIMITED = 1;
    /** @var int Smallest value while the site keeps values non-negative. */
    protected const MIN_NONNEGATIVE = 10;
    /** @var int Stepping from the default starts here. */
    protected const START = 95;
    /** @var string CSS custom property. */
    protected const PROPERTY = '--a11y-measure';
    /** @var int Encoding scale. */
    protected const SCALE = 1;
    /** @var string CSS unit. */
    protected const CSSUNIT = 'ch';
    /** @var string|null Lang string that labels a value. */
    protected const LABELSTRING = 'characters';
    /** @var string|null Lang string of the unit beside the value field. */
    protected const UNITSTRING = 'unit_characters';
    /** @var bool Full width is wider than any number of characters, so + does nothing there. */
    protected const DEFAULT_IS_MAX = true;

    /**
     * Stable id, stored in preferences.
     *
     * @return string
     */
    public function id(): string {
        return 'narrow';
    }

    /**
     * Font Awesome icon name for the tile.
     *
     * @return string
     */
    public function icon(): string {
        return 'fa-arrows-left-right';
    }

    /**
     * Options are chosen in a detail view (spec §3 "B").
     *
     * @return string
     */
    public function kind(): string {
        return 'detail';
    }

    /**
     * Options preview the line width as a bar.
     *
     * @return string|null
     */
    public function preview(): ?string {
        return 'bar';
    }
}
