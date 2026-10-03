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
 * Font face: the site font, a device stack or a bundled font (spec §3, §4).
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
     * Allowed values: the site font, device stacks, bundled fonts and Japanese stacks (spec §3).
     *
     * @return string[]
     */
    public function values(): array {
        return ['default', 'sans', 'serif', 'mono', 'readable', 'lexend', 'dyslexic', 'comic', 'jagothic', 'jamincho',
            'jakyokasho'];
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
        return self::STACKS[$id] ?? null;
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
