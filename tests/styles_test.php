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
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 767\.98px\)\s*\{\s*\.local-accessibility-panel\s*\{[^}]*inline-size: 100vw/',
            self::css()
        );
        foreach (array_merge(self::rules_with('.local-accessibility-panel'), self::rules_with('.la-readbar')) as $rule) {
            [$selector, $declarations] = $rule;
            $this->assertDoesNotMatchRegularExpression('/font-size:\s*\d+(\.\d+)?px/', $declarations, $selector);
        }
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
}
