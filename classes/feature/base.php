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
 * One adjustable display preference (spec §5).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * Stable id, stored in preferences.
     *
     * @return string
     */
    abstract public function id(): string;

    /**
     * Allowed values in cycle order; the first is off/default.
     *
     * @return string[]
     */
    abstract public function values(): array;

    /**
     * Font Awesome icon name for the tile.
     *
     * @return string
     */
    abstract public function icon(): string;

    /**
     * Default value.
     *
     * @return string
     */
    public function default(): string {
        return $this->values()[0];
    }

    /**
     * Whether a value is allowed.
     *
     * @param string $value
     * @return bool
     */
    public function validate(string $value): bool {
        return in_array($value, $this->values(), true);
    }

    /**
     * Attributes for the html tag; none for the default value.
     *
     * @param string $value a validated value
     * @return array<string, string>
     */
    public function html_attributes(string $value): array {
        return $value === $this->default() ? [] : ['data-a11y-' . $this->id() => $value];
    }

    /**
     * Tile label.
     *
     * @return string
     */
    public function label(): string {
        return get_string('feature_' . $this->id(), 'local_accessibility');
    }

    /**
     * Label of one value.
     *
     * @param string $value
     * @return string
     */
    public function value_label(string $value): string {
        return get_string('feature_' . $this->id() . '_' . $value, 'local_accessibility');
    }
}
