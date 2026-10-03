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

namespace local_accessibility\local;

/**
 * The fonts a user may choose: bundled, device stacks and admin uploads (spec §4).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fonts {
    /** @var string[] Fonts shipped in fonts/ with static @font-face rules in styles.css. */
    public const BUNDLED = ['readable', 'lexend', 'dyslexic', 'comic'];
    /** @var string[] Font stacks of fonts installed on the device (nothing is downloaded). */
    public const DEVICE = ['sans', 'serif', 'mono', 'jagothic', 'jamincho', 'jakyokasho'];
    /** @var string[] Display order of the built-in fonts. */
    private const ORDER = ['sans', 'serif', 'mono', 'readable', 'lexend', 'dyslexic', 'comic', 'jagothic', 'jamincho',
        'jakyokasho'];
    /** @var string File area of the uploaded fonts, in the system context. */
    public const FILEAREA = 'fonts';
    /** @var string Accepted upload file names (D8): nothing that could leave a CSS url() or an HTML attribute. */
    private const FILENAME = '/^[A-Za-z0-9 ._-]+\.(woff2|woff|ttf|otf)$/i';
    /** @var array<string, array{0: string, 1: string}> Extension => [CSS format(), MIME type]. */
    private const FORMATS = [
        'woff2' => ['woff2', 'font/woff2'],
        'woff' => ['woff', 'font/woff'],
        'ttf' => ['truetype', 'font/ttf'],
        'otf' => ['opentype', 'font/otf'],
    ];
    /** @var string[] Order of the formats in a src list: smallest first. */
    private const SRC_ORDER = ['woff2', 'woff', 'truetype', 'opentype'];

    /** @var array|null Uploaded fonts, memoised per request. */
    private static ?array $uploaded = null;

    /**
     * Built-in fonts the admin made available, in display order. The site default is not in the list: it is always
     * available.
     *
     * @return string[]
     */
    public static function available(): array {
        $config = get_config('local_accessibility', 'fonts_available');
        if ($config === false) {
            return self::ORDER;     // Never saved: every font.
        }
        $chosen = array_map('trim', explode(',', (string) $config));
        return array_values(array_intersect(self::ORDER, $chosen));
    }

    /**
     * Every built-in font, in display order.
     *
     * @return string[]
     */
    public static function builtin(): array {
        return self::ORDER;
    }

    /**
     * Drop the memoised uploaded fonts (after the setting changes, and between unit tests).
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$uploaded = null;
    }

    /**
     * Fonts uploaded by the admin, keyed by font id up_<slug>, sorted by file name.
     *
     * @return array<string, array{label: string, faces: array<int, array{url: string, weight: int, format: string}>}>
     */
    public static function uploaded(): array {
        if (self::$uploaded !== null) {
            return self::$uploaded;
        }
        $sys = \context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files($sys->id, 'local_accessibility', self::FILEAREA, 0, 'filename', false);
        $out = [];
        foreach ($files as $file) {
            if ($file->get_filepath() !== '/') {
                continue;
            }
            $parsed = self::parse_filename($file->get_filename());
            if ($parsed === null) {
                continue;
            }
            $id = 'up_' . $parsed['slug'];
            if (!isset($out[$id])) {
                $out[$id] = ['label' => $parsed['family'], 'faces' => []];
            } else if ($out[$id]['label'] !== $parsed['family']) {
                continue;       // A second family with the same slug: the first one wins.
            }
            $url = \moodle_url::make_pluginfile_url(
                $sys->id,
                'local_accessibility',
                self::FILEAREA,
                0,
                '/',
                $file->get_filename()
            );
            $out[$id]['faces'][] = [
                'url' => $url->out(false),
                'weight' => $parsed['weight'],
                'format' => $parsed['format'],
            ];
        }
        foreach ($out as &$font) {
            usort($font['faces'], fn($a, $b) => [$a['weight'], self::format_rank($a['format'])]
                <=> [$b['weight'], self::format_rank($b['format'])]);
        }
        unset($font);
        return self::$uploaded = $out;
    }

    /**
     * Parse an uploaded font's file name: Family-Bold.woff2 is family "Family", weight 700 (spec §4, D8).
     *
     * @param string $name
     * @return array{family: string, slug: string, weight: int, format: string}|null null when not accepted
     */
    public static function parse_filename(string $name): ?array {
        if (!preg_match(self::FILENAME, $name, $m)) {
            return null;
        }
        $basename = substr($name, 0, -strlen($m[1]) - 1);
        $cut = strcspn($basename, '-_');
        $family = trim(substr($basename, 0, $cut));
        $rest = substr($basename, $cut);
        $slug = clean_param(\core_text::strtolower($family), PARAM_ALPHANUMEXT);
        if ($family === '' || $slug === '') {
            return null;
        }
        return [
            'family' => $family,
            'slug' => $slug,
            'weight' => stripos($rest, 'bold') !== false ? 700 : 400,
            'format' => self::FORMATS[strtolower($m[1])][0],
        ];
    }

    /**
     * The font MIME type of a file name, by extension (D7: core's file types have none for fonts).
     *
     * @param string $filename
     * @return string|null null when not a font extension
     */
    public static function mimetype(string $filename): ?string {
        $dot = strrpos($filename, '.');
        if ($dot === false) {
            return null;
        }
        return self::FORMATS[strtolower(substr($filename, $dot + 1))][1] ?? null;
    }

    /**
     * Check a pluginfile request for an uploaded font.
     *
     * @param \context $context
     * @param string $filearea
     * @param array $args itemid and file name
     * @return array{file: \stored_file, mimetype: string}|null null when the request must be refused
     */
    public static function serve_check(\context $context, string $filearea, array $args): ?array {
        if ($context->contextlevel != CONTEXT_SYSTEM || $filearea !== self::FILEAREA || count($args) !== 2) {
            return null;
        }
        [$itemid, $filename] = array_map('strval', array_values($args));
        $mimetype = self::mimetype($filename);
        if ($itemid !== '0' || $mimetype === null || !preg_match(self::FILENAME, $filename)) {
            return null;
        }
        $file = get_file_storage()->get_file($context->id, 'local_accessibility', self::FILEAREA, 0, '/', $filename);
        if (!$file || $file->is_directory()) {
            return null;
        }
        return ['file' => $file, 'mimetype' => $mimetype];
    }

    /**
     * The @font-face rules of an uploaded font, one per weight; empty for any other id.
     *
     * @param string $id
     * @return string
     */
    public static function face_css(string $id): string {
        $font = self::uploaded()[$id] ?? null;
        if ($font === null) {
            return '';
        }
        $byweight = [];
        foreach ($font['faces'] as $face) {
            // Faces come sorted by weight, then format (smallest first).
            $byweight[$face['weight']][] = 'url("' . self::css_url($face['url']) . '") format("' . $face['format'] . '")';
        }
        $css = '';
        foreach ($byweight as $weight => $src) {
            // The id is up_ plus a PARAM_ALPHANUMEXT slug, the weight an int and the formats constants.
            $css .= '@font-face{font-family:"local_accessibility_' . $id . '";font-weight:' . (int) $weight
                . ';font-display:swap;src:' . implode(',', $src) . '}';
        }
        return $css;
    }

    /**
     * Position of a CSS font format in a src list: smallest first.
     *
     * @param string $format
     * @return int
     */
    private static function format_rank(string $format): int {
        return (int) array_search($format, self::SRC_ORDER, true);
    }

    /**
     * A URL made safe inside a double-quoted CSS url() within a style element: every character outside a
     * plain URL set is percent-encoded.
     *
     * @param string $url
     * @return string
     */
    private static function css_url(string $url): string {
        return preg_replace_callback('/[^A-Za-z0-9:\/._%~?=&-]/', fn($m) => rawurlencode($m[0]), $url);
    }
}
