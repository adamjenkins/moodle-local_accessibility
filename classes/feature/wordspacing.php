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
 * Word spacing: extra space between words (spec §3).
 *
 * Hundredths of an em; negative values squeeze words together.
 *
 * @package    local_accessibility
 * @copyright  2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wordspacing extends numeric {
    /** @var int One press of − or +. */
    protected const STEP = 2;
    /** @var int Largest value. */
    protected const MAX = 1000;
    /** @var int Smallest value while the site allows the full range. */
    protected const MIN_UNLIMITED = -1000;
    /** @var int Smallest value while the site keeps values non-negative. */
    protected const MIN_NONNEGATIVE = 0;
    /** @var int Stepping from the default starts here. */
    protected const START = 0;
    /** @var string CSS custom property. */
    protected const PROPERTY = '--a11y-ws';
    /** @var int Encoding scale. */
    protected const SCALE = 100;
    /** @var string CSS unit. */
    protected const CSSUNIT = 'em';
    /** @var string|null Lang string that labels a value. */
    protected const LABELSTRING = null;
    /** @var string|null Lang string of the unit beside the value field. */
    protected const UNITSTRING = 'unit_em';

    /**
     * Stable id, stored in preferences.
     *
     * @return string
     */
    public function id(): string {
        return 'wordspacing';
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
     * Shown on the Spacing tile with the other two spacing features (spec §3).
     *
     * @return string
     */
    public function tile(): string {
        return 'spacing';
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
     * Options preview sample text with the spacing applied.
     *
     * @return string|null
     */
    public function preview(): ?string {
        return 'spacing';
    }
}
