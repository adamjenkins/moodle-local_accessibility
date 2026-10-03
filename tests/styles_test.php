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

namespace local_accessibility;

/**
 * Selector contract of styles.css: every feature value has a rule, and the panel follows the user (choices spec §1,
 * §3) except for alignment, line width, links, images and the reading guide.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class styles_test extends \advanced_testcase {
    /**
     * The stylesheet without comments.
     *
     * @return string
     */
    private static function css(): string {
        $css = file_get_contents(__DIR__ . '/../styles.css');
        return preg_replace('~/\*.*?\*/~s', '', $css);
    }

    /**
     * Innermost rule blocks as [selector, body] pairs (rules nested in @media are found too).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private static function rules(): array {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', self::css(), $m, PREG_SET_ORDER);
        return array_map(fn($r) => [trim($r[1]), $r[2]], $m);
    }

    /**
     * The rules whose selector contains a string.
     *
     * @param string $needle
     * @return array<int, array{0: string, 1: string}>
     */
    private static function rules_with(string $needle): array {
        return array_values(array_filter(self::rules(), fn($r) => str_contains($r[0], $needle)));
    }

    /**
     * The old fixed-value selectors are gone.
     */
    public function test_no_old_value_selectors(): void {
        $css = self::css();
        foreach (
            ['data-a11y-spacing', 'data-a11y-align="on"', 'data-a11y-links="on"', 'data-a11y-images="on"',
                'data-a11y-focus="cursor"', 'data-a11y-size="'] as $old
        ) {
            $this->assertStringNotContainsString($old, $css, $old);
        }
        // The phone size cap is dropped (D3): the user's size is applied as chosen.
        $this->assertStringNotContainsString('max-width: 480px', $css);
    }

    /**
     * Every new variable and value has a rule.
     */
    public function test_new_variables_and_values(): void {
        $css = self::css();
        foreach (
            ['var(--a11y-size)', 'var(--a11y-font)', 'var(--a11y-lh)', 'var(--a11y-ls)', 'var(--a11y-ws)',
                'var(--a11y-measure)', 'data-a11y-cursor="large"', 'data-a11y-cursor="xlarge"',
                'data-a11y-images="hide"', 'data-a11y-images="dim"', 'data-a11y-links="underline"',
                'data-a11y-links="outline"', 'data-a11y-links="highlight"', 'data-a11y-focus="ring"',
                'data-a11y-focus="thick"', 'data-a11y-align="left"', 'data-a11y-align="center"',
                'data-a11y-align="right"', 'data-a11y-align="justify"', 'pix/cursor.svg', 'pix/cursor-xlarge.svg',
                'data-a11y-lineheight', 'data-a11y-letterspacing', 'data-a11y-wordspacing', 'data-a11y-narrow'] as $new
        ) {
            $this->assertStringContainsString($new, $css, $new);
        }
        $size = self::rules_with('html[data-a11y-size]');
        $this->assertCount(1, $size);
        $this->assertStringContainsString('font-size: calc(var(--a11y-size) * 1%)', $size[0][1]);
        // Paragraph spacing follows line height (D4).
        $para = array_filter(self::rules_with('[data-a11y-lineheight]'), fn($r) => str_contains($r[1], 'margin-block-end'));
        $this->assertCount(1, $para);
        $this->assertStringContainsString('calc(var(--a11y-lh) * 1.33em)', reset($para)[1]);
    }

    /**
     * Feature ids whose rules keep the panel and the readbar out.
     *
     * @return array
     */
    public static function excluded_provider(): array {
        return ['align' => ['data-a11y-align'], 'narrow' => ['data-a11y-narrow'], 'links' => ['data-a11y-links'],
            'images' => ['data-a11y-images'], 'guide' => ['data-a11y-guide']];
    }

    /**
     * Alignment, line width, links and images never apply inside the panel or the readbar.
     *
     * @dataProvider excluded_provider
     * @param string $attr
     */
    public function test_panel_excluded(string $attr): void {
        $rules = self::rules_with($attr);
        if ($attr === 'data-a11y-guide') {
            // The guide is an overlay drawn by guide.js; it has no attribute rule to guard.
            $this->assertCount(0, $rules);
            return;
        }
        $this->assertNotEmpty($rules, $attr);
        foreach ($rules as [$selector]) {
            $this->assertStringContainsString('.local-accessibility-panel *', $selector, $selector);
            $this->assertStringContainsString('.la-readbar *', $selector, $selector);
        }
    }

    /**
     * Feature ids whose rules the panel and the readbar follow.
     *
     * @return array
     */
    public static function followed_provider(): array {
        $ids = ['font', 'lineheight', 'letterspacing', 'wordspacing', 'focus', 'cursor', 'motion', 'colour'];
        return array_combine($ids, array_map(fn($id) => ['data-a11y-' . $id], $ids));
    }

    /**
     * Font, spacing, focus, cursor, motion and colour apply inside the panel and the readbar (R19 replaced).
     *
     * @dataProvider followed_provider
     * @param string $attr
     */
    public function test_panel_follows(string $attr): void {
        $rules = self::rules_with($attr);
        $this->assertNotEmpty($rules, $attr);
        $excluded = array_column(self::excluded_provider(), 0);
        foreach ($rules as [$selector]) {
            foreach ($excluded as $other) {
                if (str_contains($selector, $other)) {
                    // A combined rule (e.g. scheme plus link highlight) belongs to the excluded feature.
                    continue 2;
                }
            }
            $this->assertStringNotContainsString('.local-accessibility-panel *', $selector, $selector);
            $this->assertStringNotContainsString('.la-readbar *', $selector, $selector);
        }
    }

    /**
     * The panel no longer re-points the scheme's tokens to Boost's values.
     */
    public function test_no_panel_token_repointing(): void {
        foreach (self::rules_with('.local-accessibility-panel') as [$selector, $body]) {
            // A declaration of a Bootstrap token, not a var() reading one.
            $this->assertDoesNotMatchRegularExpression('/--bs-[a-z-]+\s*:/', $body, $selector);
        }
    }

    /**
     * The panel is sized in rem/em, compact on wide screens and a bottom sheet on narrow ones (spec §1, D10).
     */
    public function test_panel_sizing(): void {
        $panel = array_values(array_filter(self::rules(), fn($r) => $r[0] === '.local-accessibility-panel'));
        $this->assertNotEmpty($panel);
        $body = implode("\n", array_column($panel, 1));
        $this->assertStringContainsString('clamp(380px, 50vw, 760px)', $body);
        $this->assertStringContainsString('min(80vh, calc(100vh - 120px))', $body);
        $this->assertStringContainsString('font-size: 1rem', $body);
        $this->assertStringContainsString('var(--a11y-page, var(--bs-body-bg, #fff))', $body);
        $this->assertStringContainsString('var(--a11y-text, var(--bs-body-color, #1d2125))', $body);
        $head = array_values(array_filter(self::rules(), fn($r) => $r[0] === '.local-accessibility-panel .la-head'));
        $this->assertNotEmpty($head);
        $this->assertStringContainsString('position: sticky', $head[0][1]);
        // The bottom sheet spans the viewport between its insets, not 100vw, which would include a classic scrollbar
        // and put the panel's edge (and the Close button's padding) under it.
        $this->assertSame(
            1,
            preg_match('/@media \(max-width: 767\.98px\)\s*\{\s*\.local-accessibility-panel\s*\{([^}]*)\}/', self::css(), $sheet)
        );
        $this->assertStringContainsString('inset-inline: 0;', $sheet[1]);
        $this->assertStringContainsString('inline-size: auto;', $sheet[1]);
        $this->assertStringNotContainsString('100vw', $sheet[1]);
        // The header wraps instead of pushing Close off a narrow screen, and the title's push is logical (RTL).
        $this->assertStringContainsString('flex-wrap: wrap', $head[0][1]);
        $title = array_values(array_filter(self::rules(), fn($r) => $r[0] === '.local-accessibility-panel .la-title'));
        $this->assertNotEmpty($title);
        $this->assertStringContainsString('margin-inline-end: auto', $title[0][1]);
        $this->assertDoesNotMatchRegularExpression('/margin:\s*0 auto 0 0/', $title[0][1]);
        foreach (array_merge(self::rules_with('.local-accessibility-panel'), self::rules_with('.la-readbar')) as $rule) {
            [$selector, $declarations] = $rule;
            $this->assertDoesNotMatchRegularExpression('/font-size:\s*\d+(\.\d+)?px/', $declarations, $selector);
        }
    }

    /**
     * Icons in the plugin's UI grow with the text: Boost caps every .icon at 30px wide and 24px tall
     * (theme/boost/scss/moodle/icons.scss), which would let a rem-sized glyph overflow its box from 150% up.
     */
    public function test_ui_icons_uncapped(): void {
        foreach (['.local-accessibility-panel .icon', '.la-readbar .icon'] as $needle) {
            $uncapped = array_filter(
                self::rules(),
                fn($r) => in_array($needle, array_map('trim', explode(',', $r[0])), true)
                    && str_contains($r[1], 'max-inline-size: none') && str_contains($r[1], 'max-block-size: none')
            );
            $this->assertNotEmpty($uncapped, $needle);
        }
    }

    /**
     * The panel and the readbar take the user's whole spacing: buttons, headings and form controls, which the
     * browser and Bootstrap reset, inherit letter and word spacing, and fixed line heights are only fallbacks for
     * when no line height is chosen. The colour swatches are fixed-size previews and keep theirs.
     */
    public function test_ui_follows_spacing(): void {
        foreach (['.local-accessibility-panel', '.la-readbar'] as $root) {
            $inherit = array_filter(
                self::rules(),
                fn($r) => str_contains($r[0], $root . ' :where(button')
                    && str_contains($r[1], 'letter-spacing: inherit') && str_contains($r[1], 'word-spacing: inherit')
            );
            $this->assertNotEmpty($inherit, $root);
        }
        foreach (array_merge(self::rules_with('.local-accessibility-panel'), self::rules_with('.la-readbar')) as $rule) {
            [$selector, $declarations] = $rule;
            if (str_contains($selector, '.la-swatch')) {
                continue;
            }
            if (preg_match_all('/(?<![-\w])line-height:\s*([^;]+);/', $declarations, $m)) {
                foreach ($m[1] as $value) {
                    $this->assertStringStartsWith('var(--a11y-lh,', trim($value), $selector);
                }
            }
        }
    }

    /**
     * Dimmed images fade once: a <picture> is not dimmed as well as its <img>, which would multiply to 0.16.
     */
    public function test_dim_not_doubled(): void {
        $dim = array_filter(self::rules_with('data-a11y-images="dim"'), fn($r) => str_contains($r[1], 'opacity: 0.4'));
        $this->assertCount(1, $dim);
        // The element, not .userpicture.
        $this->assertDoesNotMatchRegularExpression('/(?<![\w.-])picture\b/', reset($dim)[0]);
    }

    /**
     * The extra-large cursor exists at 96px, and images.js adds alt text for the "hide" value.
     */
    public function test_cursor_and_images_script(): void {
        $svg = file_get_contents(__DIR__ . '/../pix/cursor-xlarge.svg');
        $this->assertNotFalse($svg);
        $this->assertStringContainsString('width="96" height="96"', $svg);
        $js = file_get_contents(__DIR__ . '/../amd/src/images.js');
        $this->assertStringContainsString("'hide'", $js);
        $this->assertStringNotContainsString("=== 'on'", $js);
    }

    /**
     * A font option's label keeps its own font (.la-ownfont) but, not being a preview (.la-sample), follows the user's
     * line, letter and word spacing like every other label in the panel (choices spec §1).
     */
    public function test_font_labels_follow_spacing(): void {
        $font = self::rules_with('html[data-a11y-font]');
        $this->assertNotEmpty($font);
        foreach ($font as [$selector]) {
            $this->assertStringContainsString('.la-ownfont, .la-ownfont *', $selector);
        }
        foreach (['data-a11y-lineheight', 'data-a11y-letterspacing', 'data-a11y-wordspacing'] as $attr) {
            $rules = self::rules_with('html[' . $attr . ']');
            $this->assertNotEmpty($rules, $attr);
            foreach ($rules as [$selector]) {
                $this->assertStringNotContainsString('la-ownfont', $selector, $attr);
            }
        }
    }
}
