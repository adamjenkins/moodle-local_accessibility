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

use local_accessibility\feature\font;
use local_accessibility\feature\registry;

/**
 * Tests for the font list, uploaded fonts and their serving.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\local\fonts
 */
final class fonts_test extends \advanced_testcase {
    /**
     * Uploaded fonts are memoised per request; every test starts and ends without the memo.
     */
    protected function setUp(): void {
        parent::setUp();
        fonts::reset_cache();
    }

    /**
     * Drop the memo so later tests do not see this test's uploads.
     */
    protected function tearDown(): void {
        fonts::reset_cache();
        parent::tearDown();
    }

    /**
     * Store a file in the uploaded fonts area.
     *
     * @param string $filename
     * @param string $area
     * @return \stored_file
     */
    private static function store(string $filename, string $area = 'fonts'): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_accessibility',
            'filearea' => $area,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], 'font data ' . $filename);
    }

    /**
     * File names parse to family, slug, weight, style, preference rank and format (spec §4, D8).
     *
     * @dataProvider filename_provider
     * @param string $name
     * @param array|null $expected
     */
    public function test_parse_filename(string $name, ?array $expected): void {
        $this->assertSame($expected, fonts::parse_filename($name));
    }

    /**
     * File names and their parse. Rank 0 is the face's own name (Regular, Bold, Italic, BoldItalic); a higher rank is
     * used only when no better file of the same weight, style and format exists.
     *
     * @return array
     */
    public static function filename_provider(): array {
        $f = fn($family, $slug, $weight, $style, $rank, $format) =>
            compact('family', 'slug', 'weight', 'style', 'rank', 'format');
        return [
            'regular woff2' => ['MyFont-Regular.woff2', $f('MyFont', 'myfont', 400, 'normal', 0, 'woff2')],
            'underscore bold ttf' => ['MyFont_Bold.ttf', $f('MyFont', 'myfont', 700, 'normal', 0, 'truetype')],
            'space, bold italic' => ['Open Sans-BoldItalic.woff', $f('Open Sans', 'opensans', 700, 'italic', 0, 'woff')],
            'no suffix otf' => ['Plain.otf', $f('Plain', 'plain', 400, 'normal', 0, 'opentype')],
            'upper-case extension' => ['Plain-BOLD.WOFF2', $f('Plain', 'plain', 700, 'normal', 0, 'woff2')],
            'italic' => ['Roboto-Italic.ttf', $f('Roboto', 'roboto', 400, 'italic', 0, 'truetype')],
            'oblique' => ['Inter-Oblique.woff2', $f('Inter', 'inter', 400, 'italic', 0, 'woff2')],
            'regular italic' => ['Inter-Regular Italic.woff2', $f('Inter', 'inter', 400, 'italic', 0, 'woff2')],
            'book' => ['Gotham-Book.woff', $f('Gotham', 'gotham', 400, 'normal', 1, 'woff')],
            'light' => ['Roboto-Light.ttf', $f('Roboto', 'roboto', 400, 'normal', 2, 'truetype')],
            'black italic' => ['Roboto-BlackItalic.ttf', $f('Roboto', 'roboto', 400, 'italic', 2, 'truetype')],
            'extra bold' => ['Roboto-ExtraBold.ttf', $f('Roboto', 'roboto', 700, 'normal', 1, 'truetype')],
            'condensed' => ['Roboto_Condensed-Regular.ttf', $f('Roboto', 'roboto', 400, 'normal', 2, 'truetype')],
            'not a font' => ['bad.exe', null],
            'quote' => ['x".woff2', null],
            'parenthesis' => ['a(b).woff2', null],
            'no basename' => ['.woff2', null],
            'svg' => ['evil.svg', null],
            'empty family' => ['-Bold.woff2', null],
            'semicolon' => ['a;b.woff2', null],
        ];
    }

    /**
     * fonts_available: unset means all built-ins, '' means none, a list is filtered to known ids.
     */
    public function test_available(): void {
        $this->resetAfterTest();
        unset_config('fonts_available', 'local_accessibility');
        $all = ['sans', 'serif', 'mono', 'readable', 'lexend', 'dyslexic', 'comic', 'jagothic', 'jamincho', 'jakyokasho'];
        $this->assertSame($all, fonts::available());
        $this->assertSame(array_merge(['default'], $all), registry::get('font')->values());

        set_config('fonts_available', 'sans,lexend,nosuch', 'local_accessibility');
        $this->assertSame(['sans', 'lexend'], fonts::available());
        $font = registry::get('font');
        $this->assertSame(['default', 'sans', 'lexend'], $font->values());
        $this->assertFalse($font->validate('dyslexic'));
        $this->assertTrue($font->validate('lexend'));
        $this->assertTrue($font->validate('default'));

        // Display order is the built-in order, not the stored order.
        set_config('fonts_available', 'comic,serif', 'local_accessibility');
        $this->assertSame(['serif', 'comic'], fonts::available());

        set_config('fonts_available', '', 'local_accessibility');
        $this->assertSame([], fonts::available());
        $this->assertSame(['default'], registry::get('font')->values());
        $this->assertTrue(registry::get('font')->validate('default'));
    }

    /**
     * Uploaded fonts are listed by family, with each weight's face, and are always available.
     */
    public function test_uploaded_listed_and_validated(): void {
        $this->resetAfterTest();
        $this->assertSame([], fonts::uploaded());
        $regular = self::store('MyFont-Regular.woff2');
        $bold = self::store('MyFont-Bold.woff2');
        self::store('a(b).woff2');
        self::store('notes.txt');
        fonts::reset_cache();

        $up = fonts::uploaded();
        $this->assertSame(['up_myfont'], array_keys($up));
        $this->assertSame('MyFont', $up['up_myfont']['label']);
        $faces = $up['up_myfont']['faces'];
        $this->assertSame([400, 700], array_values(array_unique(array_column($faces, 'weight'))));
        $this->assertSame(['woff2'], array_values(array_unique(array_column($faces, 'format'))));
        $this->assertSame(['normal'], array_values(array_unique(array_column($faces, 'style'))));
        $sys = \context_system::instance()->id;
        // Faces come by weight. The item id in the URL is a content revision, so a replaced file gets a new URL.
        $rev = fn($f) => substr($f->get_contenthash(), 0, 8);
        $this->assertStringEndsWith(
            "/pluginfile.php/$sys/local_accessibility/fonts/{$rev($regular)}/MyFont-Regular.woff2",
            $faces[0]['url']
        );
        $this->assertStringEndsWith(
            "/pluginfile.php/$sys/local_accessibility/fonts/{$rev($bold)}/MyFont-Bold.woff2",
            $faces[1]['url']
        );

        set_config('fonts_available', '', 'local_accessibility');
        $font = registry::get('font');
        $this->assertSame(['default', 'up_myfont'], $font->values());
        $this->assertTrue($font->validate('up_myfont'));
        $this->assertFalse($font->validate('up_other'));
        $this->assertSame('MyFont', $font->value_label('up_myfont'));
        // Labels are raw text: the panel template and the admin select escape them once.
        $this->assertSame('up_a&b', $font->value_label('up_a&b'));
        $this->assertSame('"local_accessibility_up_myfont", system-ui, sans-serif', font::stack('up_myfont'));
        $this->assertNull(font::stack('up_other'));

        $options = array_column($font->options(), null, 'value');
        $this->assertSame('"local_accessibility_up_myfont", system-ui, sans-serif', $options['up_myfont']['stack']);
        $this->assertCount(2, $options['up_myfont']['faces']);
        $this->assertSame('font', $options['up_myfont']['preview']);
        $this->assertArrayNotHasKey('faces', $options['default']);
    }

    /**
     * The first file per slug names the family; a second family with the same slug is not listed twice.
     */
    public function test_uploaded_slug_collision(): void {
        $this->resetAfterTest();
        self::store('My Font-Regular.woff2');
        self::store('MyFont-Bold.woff2');
        fonts::reset_cache();
        $up = fonts::uploaded();
        $this->assertSame(['up_myfont'], array_keys($up));
        $this->assertSame('My Font', $up['up_myfont']['label']);
    }

    /**
     * Files are taken in byte order of their names, whatever the database's collation: "first family wins" is the
     * same on every database. Stored out of order, and differing only in case where a case-insensitive collation
     * would put the other one first.
     */
    public function test_uploaded_byte_order(): void {
        $this->resetAfterTest();
        self::store('myFont-Bold.woff2');
        self::store('Myfont-Regular.woff2');
        fonts::reset_cache();
        $up = fonts::uploaded();
        $this->assertSame(['up_myfont'], array_keys($up));
        $this->assertSame('Myfont', $up['up_myfont']['label']);
        $this->assertCount(1, $up['up_myfont']['faces']);
        $this->assertStringEndsWith('/Myfont-Regular.woff2', $up['up_myfont']['faces'][0]['url']);
    }

    /**
     * A full static family (as downloaded from a font site) gives one face per weight and style: Regular, Italic,
     * Bold and Bold Italic, never Black or Light in place of Regular.
     */
    public function test_uploaded_full_family(): void {
        $this->resetAfterTest();
        $suffixes = ['Black', 'BlackItalic', 'Bold', 'BoldItalic', 'ExtraBold', 'Italic', 'Light', 'Medium', 'Regular', 'Thin'];
        foreach ($suffixes as $suffix) {
            self::store("Roboto-$suffix.ttf");
        }
        fonts::reset_cache();
        $faces = fonts::uploaded()['up_roboto']['faces'];
        $byface = [];
        foreach ($faces as $face) {
            $byface[$face['weight'] . ' ' . $face['style']][] = basename($face['url']);
        }
        $this->assertSame([
            '400 normal' => ['Roboto-Regular.ttf'],
            '400 italic' => ['Roboto-Italic.ttf'],
            '700 normal' => ['Roboto-Bold.ttf'],
            '700 italic' => ['Roboto-BoldItalic.ttf'],
        ], $byface);

        $css = fonts::face_css('up_roboto');
        $this->assertSame(4, substr_count($css, '@font-face{'));
        $this->assertMatchesRegularExpression(
            '/font-weight:400;font-style:normal;font-display:swap;src:url\("[^"]*\/Roboto-Regular\.ttf"\) format\("truetype"\)\}/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/font-weight:700;font-style:italic;font-display:swap;src:url\("[^"]*\/Roboto-BoldItalic\.ttf"\) '
            . 'format\("truetype"\)\}/',
            $css
        );
        foreach (['Black', 'ExtraBold', 'Light', 'Medium', 'Thin'] as $unused) {
            $this->assertStringNotContainsString("Roboto-$unused.ttf", $css);
        }
    }

    /**
     * Without a Regular file, the best remaining file of the weight stands in: the family is still offered.
     */
    public function test_uploaded_fallback_face(): void {
        $this->resetAfterTest();
        self::store('Thin-Light.woff2');
        self::store('Thin-Medium.woff2');
        self::store('Thin-SemiBold.woff2');
        fonts::reset_cache();
        $faces = fonts::uploaded()['up_thin']['faces'];
        $this->assertSame(['Thin-Light.woff2', 'Thin-SemiBold.woff2'], array_map(fn($f) => basename($f['url']), $faces));
        $this->assertSame([400, 700], array_column($faces, 'weight'));
    }

    /**
     * Replacing a file's content changes its URL, so browsers do not keep the old file under immutable caching.
     */
    public function test_uploaded_url_changes_with_content(): void {
        $this->resetAfterTest();
        $file = self::store('MyFont-Regular.woff2');
        fonts::reset_cache();
        $before = fonts::uploaded()['up_myfont']['faces'][0]['url'];
        $file->delete();
        get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
            'component' => 'local_accessibility', 'filearea' => 'fonts', 'itemid' => 0, 'filepath' => '/',
            'filename' => 'MyFont-Regular.woff2'], 'corrected font data');
        fonts::reset_cache();
        $after = fonts::uploaded()['up_myfont']['faces'][0]['url'];
        $this->assertNotSame($before, $after);
        $this->assertStringEndsWith('/MyFont-Regular.woff2', $after);
    }

    /**
     * Serving: only the system context, the fonts area and font files, each with its font MIME type (D7).
     */
    public function test_serve_check(): void {
        $this->resetAfterTest();
        self::store('MyFont-Regular.woff2');
        self::store('MyFont-Bold.ttf');
        self::store('MyFont-Regular.woff2', 'other');
        self::store('evil.svg');
        self::store('a(b).woff2');
        $sys = \context_system::instance();

        $r = fonts::serve_check($sys, 'fonts', ['0', 'MyFont-Regular.woff2']);
        $this->assertSame('font/woff2', $r['mimetype']);
        $this->assertSame('MyFont-Regular.woff2', $r['file']->get_filename());
        $this->assertSame('font/ttf', fonts::serve_check($sys, 'fonts', ['0', 'MyFont-Bold.ttf'])['mimetype']);

        $course = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $this->assertNull(fonts::serve_check($course, 'fonts', ['0', 'MyFont-Regular.woff2']));
        $this->assertNull(fonts::serve_check($sys, 'other', ['0', 'MyFont-Regular.woff2']));
        $this->assertNull(fonts::serve_check($sys, 'fonts', ['0', 'Missing-Regular.woff2']));
        $this->assertNull(fonts::serve_check($sys, 'fonts', ['0', 'evil.svg']));
        $this->assertNull(fonts::serve_check($sys, 'fonts', ['0', 'a(b).woff2']));
        // The revision is a cache key only: any hex revision serves the current file; anything else is refused.
        $this->assertSame(
            'MyFont-Regular.woff2',
            fonts::serve_check($sys, 'fonts', ['0a1b2c3d', 'MyFont-Regular.woff2'])['file']->get_filename()
        );
        $this->assertNull(fonts::serve_check($sys, 'fonts', ['x', 'MyFont-Regular.woff2']));
        $this->assertNull(fonts::serve_check($sys, 'fonts', ['', 'MyFont-Regular.woff2']));
        $this->assertNull(fonts::serve_check($sys, 'fonts', ['-1', 'MyFont-Regular.woff2']));
        $this->assertNull(fonts::serve_check($sys, 'fonts', [str_repeat('a', 41), 'MyFont-Regular.woff2']));
        $this->assertNull(fonts::serve_check($sys, 'fonts', ['0', 'sub', 'MyFont-Regular.woff2']));
        $this->assertNull(fonts::serve_check($sys, 'fonts', []));
        $this->assertNull(fonts::serve_check($sys, 'fonts', ['0', '.']));
    }

    /**
     * The pluginfile callback refuses (returns false, sends nothing) whatever serve_check() refuses.
     *
     * @covers ::local_accessibility_pluginfile
     */
    public function test_pluginfile_refusals(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/accessibility/lib.php');
        $this->resetAfterTest();
        self::store('evil.svg');
        $sys = \context_system::instance();
        $course = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $this->assertFalse(local_accessibility_pluginfile(null, null, $sys, 'fonts', ['0', 'evil.svg'], false));
        $this->assertFalse(local_accessibility_pluginfile(null, null, $sys, 'fonts', ['0', 'Missing.woff2'], false));
        $this->assertFalse(local_accessibility_pluginfile(null, null, $course, 'fonts', ['0', 'A.woff2'], false));
        $this->assertFalse(local_accessibility_pluginfile(null, null, $sys, 'other', ['0', 'A.woff2'], false));
    }

    /**
     * Font MIME types by extension.
     */
    public function test_mimetype(): void {
        $this->assertSame('font/woff2', fonts::mimetype('a.woff2'));
        $this->assertSame('font/woff', fonts::mimetype('a.WOFF'));
        $this->assertSame('font/ttf', fonts::mimetype('a.ttf'));
        $this->assertSame('font/otf', fonts::mimetype('a.otf'));
        $this->assertNull(fonts::mimetype('a.svg'));
        $this->assertNull(fonts::mimetype('woff2'));
    }

    /**
     * The @font-face rule names the uploaded family, every weight and only safe URLs.
     */
    public function test_face_css(): void {
        $this->resetAfterTest();
        self::store('MyFont-Regular.woff');
        self::store('MyFont-Regular.woff2');
        self::store('MyFont-Bold.woff2');
        self::store('MyFont-x);}body{color:red.woff2');
        fonts::reset_cache();
        $css = fonts::face_css('up_myfont');
        $this->assertStringContainsString('font-family:"local_accessibility_up_myfont"', $css);
        $this->assertStringContainsString('font-weight:400', $css);
        $this->assertStringContainsString('font-weight:700', $css);
        $this->assertStringContainsString('format("woff2")', $css);
        $this->assertMatchesRegularExpression('#local_accessibility/fonts/[0-9a-f]{8}/MyFont-Bold\.woff2#', $css);
        $this->assertStringContainsString('font-style:normal', $css);
        $this->assertStringNotContainsString('color:red', $css);
        // Two rules; woff2 is listed before woff in the regular face.
        $this->assertSame(2, substr_count($css, '@font-face{'));
        $woff2first = '/MyFont-Regular\.woff2"\) format\("woff2"\),url\("[^"]*MyFont-Regular\.woff"\)/';
        $this->assertMatchesRegularExpression($woff2first, $css);
        // Every URL is plain; outside the URLs only the fixed rule text remains (no quote, bracket or tag).
        $this->assertSame(3, preg_match_all('/url\("([^"]*)"\)/', $css, $m));
        foreach ($m[1] as $url) {
            $this->assertMatchesRegularExpression('#^[A-Za-z0-9:/._%~?=&-]+$#', $url);
        }
        $outside = preg_replace(['/url\("[^"]*"\)/', '/format\("(woff2|woff|truetype|opentype)"\)/'], ['URL', 'FMT'], $css);
        $outside = str_replace('font-family:"local_accessibility_up_myfont"', '', $outside);
        $this->assertDoesNotMatchRegularExpression('/["\'()<>\\\\]/', $outside);
        $this->assertSame('', fonts::face_css('lexend'));
        $this->assertSame('', fonts::face_css('up_nosuch'));
        $this->assertSame('', fonts::face_css('x";}'));
    }
}
