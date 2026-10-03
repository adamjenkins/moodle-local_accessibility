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
 * Line height: multiples of the font size (spec §3).
 *
 * @package    local_accessibility
 * @copyright  2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lineheight extends base {
    /**
     * Stable id, stored in preferences.
     *
     * @return string
     */
    public function id(): string {
        return 'lineheight';
    }

    /**
     * Allowed values: the site default, then hundredths (spec §3).
     *
     * @return string[]
     */
    public function values(): array {
        return ['default', '120', '150', '180', '200', '250'];
    }

    /**
     * Font Awesome icon name for the tile.
     *
     * @return string
     */
    public function icon(): string {
        return 'fa-arrows-up-down';
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

    /**
     * Label of one value: "Site default" or the value as a decimal.
     *
     * @param string $value
     * @return string
     */
    public function value_label(string $value): string {
        return $value === 'default' ? parent::value_label($value) : format_float((int) $value / 100, 1);
    }

    /**
     * The line height custom property.
     *
     * @param string $value
     * @return array<string, string>
     */
    public function css_properties(string $value): array {
        return $this->is_numeric_choice($value) ? ['--a11y-lh' => self::hundredths((int) $value)] : [];
    }
}
