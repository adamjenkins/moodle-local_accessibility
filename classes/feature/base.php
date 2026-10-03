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
    /** @var array<string, string> Font Awesome icon for each value's option; empty when options show a preview. */
    protected const ICONS = [];

    /**
     * Stable id, stored in preferences.
     *
     * @return string
     */
    abstract public function id(): string;

    /**
     * Allowed values in display order. The first is off/default unless default() says otherwise.
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
     * Default value (site default / off): the first value unless a feature overrides it.
     *
     * @return string
     */
    public function default(): string {
        return $this->values()[0];
    }

    /**
     * Id of the panel tile that shows this feature. Features sharing a tile share its lock (spec §3 Spacing).
     *
     * @return string
     */
    public function tile(): string {
        return $this->id();
    }

    /**
     * How the options are chosen (spec §2): 'drawer' for a short list under the tile, 'detail' for a view that
     * replaces the grid.
     *
     * @return string
     */
    public function kind(): string {
        return 'drawer';
    }

    /**
     * Name of the admin lock setting for this feature: one per tile.
     *
     * @return string
     */
    public function lock_name(): string {
        return 'lock_' . $this->tile();
    }

    /**
     * Font Awesome icon for one value's option.
     *
     * @param string $value
     * @return string|null null when the feature's options show a preview instead
     */
    public function option_icon(string $value): ?string {
        return static::ICONS[$value] ?? null;
    }

    /**
     * Kind of visual preview the options show instead of an icon: 'size', 'font', 'bar', 'swatch' or 'spacing'.
     *
     * @return string|null null when the options show icons
     */
    public function preview(): ?string {
        return null;
    }

    /**
     * Every option, in values() order, with what the panel shows for it.
     *
     * @return array<int, array{value: string, label: string, icon: ?string, preview: ?string,
     *     css: array<string, string>}>
     */
    public function options(): array {
        return array_map(fn($value) => $this->option($value), $this->values());
    }

    /**
     * One option: what the panel shows for a value.
     *
     * @param string $value
     * @return array{value: string, label: string, icon: ?string, preview: ?string, css: array<string, string>}
     */
    public function option(string $value): array {
        return [
            'value' => $value,
            'label' => $this->value_label($value),
            'icon' => $this->option_icon($value),
            'preview' => $this->preview(),
            'css' => $this->css_properties($value),
        ];
    }

    /**
     * CSS custom properties for the html tag's style attribute; none for the default value or an invalid one.
     *
     * @param string $value
     * @return array<string, string> property name => value
     */
    public function css_properties(string $value): array {
        return [];
    }

    /**
     * A hundredths value as a short decimal: 150 => '1.5', 200 => '2', 5 => '0.05'.
     * %F, not %f: the CSS decimal point must not follow the locale.
     *
     * @param int $hundredths
     * @return string
     */
    protected static function hundredths(int $hundredths): string {
        return rtrim(rtrim(sprintf('%.2F', $hundredths / 100), '0'), '.');
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
