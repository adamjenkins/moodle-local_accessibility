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

use local_accessibility\local\fonts;

/**
 * Font face: the site font, a device stack, a bundled font or an admin-uploaded font (spec §3, §4).
 *
 * @package    local_accessibility
 * @copyright  2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class font extends base {
    /** @var array<string, string> Family stacks (spec §4); constants only, as they are written into a style attribute. */
    private const STACKS = [
        'sans' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans", "Liberation Sans", Arial, sans-serif',
        'serif' => '"Iowan Old Style", "Palatino Linotype", Palatino, Georgia, "Noto Serif", "Liberation Serif", '
            . '"Times New Roman", serif',
        'mono' => 'ui-monospace, "Cascadia Mono", Consolas, Menlo, "Liberation Mono", "Noto Sans Mono", monospace',
        'readable' => 'local_accessibility_readable, system-ui, sans-serif',
        'lexend' => 'local_accessibility_lexend, system-ui, sans-serif',
        'dyslexic' => 'local_accessibility_dyslexic, system-ui, sans-serif',
        'comic' => 'local_accessibility_comic, system-ui, sans-serif',
        'jagothic' => '"BIZ UDPGothic", "BIZ UDGothic", "Hiragino Sans", "Hiragino Kaku Gothic ProN", "Noto Sans CJK JP", '
            . '"Noto Sans JP", "Yu Gothic UI", "Meiryo", sans-serif',
        'jamincho' => '"BIZ UDPMincho", "BIZ UDMincho", "Hiragino Mincho ProN", "Noto Serif CJK JP", "Noto Serif JP", '
            . '"Yu Mincho", serif',
        'jakyokasho' => '"UD Digi Kyokasho NK-R", "UD Digi Kyokasho N-R", "UD デジタル 教科書体 NK-R", '
            . '"UD デジタル 教科書体 N-R", "Klee", "Klee One", sans-serif',
    ];

    /**
     * Stable id, stored in preferences.
     *
     * @return string
     */
    public function id(): string {
        return 'font';
    }

    /**
     * Allowed values: the site font, the built-in fonts the admin made available, then uploaded fonts (spec §3, §4).
     *
     * @return string[]
     */
    public function values(): array {
        return array_merge(['default'], fonts::available(), array_keys(fonts::uploaded()));
    }

    /**
     * Label of one value: an uploaded font's family name from its file name. Raw text, like every label: the
     * outputs (panel template, admin select) escape it.
     *
     * @param string $value
     * @return string
     */
    public function value_label(string $value): string {
        if (str_starts_with($value, 'up_')) {
            return fonts::uploaded()[$value]['label'] ?? $value;
        }
        return parent::value_label($value);
    }

    /**
     * Every option with its family stack, and for an uploaded font its faces, so a preview can load it.
     *
     * @return array
     */
    public function options(): array {
        $uploaded = fonts::uploaded();
        $out = [];
        foreach (parent::options() as $option) {
            $option['stack'] = self::stack($option['value']);
            if (isset($uploaded[$option['value']])) {
                $option['faces'] = $uploaded[$option['value']]['faces'];
            }
            $out[] = $option;
        }
        return $out;
    }

    /**
     * Font Awesome icon name for the tile.
     *
     * @return string
     */
    public function icon(): string {
        return 'fa-font';
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
     * Options preview "Aa" in their own font.
     *
     * @return string|null
     */
    public function preview(): ?string {
        return 'font';
    }

    /**
     * The CSS font-family stack of a font id.
     *
     * @param string $id
     * @return string|null null for the site default or an unknown id
     */
    public static function stack(string $id): ?string {
        if (isset(self::STACKS[$id])) {
            return self::STACKS[$id];
        }
        // An uploaded font's id is up_ plus a PARAM_ALPHANUMEXT slug, so it is safe in the style attribute.
        return isset(fonts::uploaded()[$id]) ? '"local_accessibility_' . $id . '", system-ui, sans-serif' : null;
    }

    /**
     * The font family stack.
     *
     * @param string $value
     * @return array<string, string>
     */
    public function css_properties(string $value): array {
        $stack = $this->validate($value) ? self::stack($value) : null;
        return $stack === null ? [] : ['--a11y-font' => $stack];
    }
}
