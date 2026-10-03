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

namespace local_accessibility\output;

use local_accessibility\local\fonts;
use local_accessibility\preferences;

/**
 * Tests for the panel's template context: tiles with value text, drawers and detail views (choices spec §2).
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\output\panel
 */
final class panel_test extends \advanced_testcase {
    /**
     * Uploaded fonts are memoised per request; every test starts without the memo.
     */
    protected function setUp(): void {
        parent::setUp();
        fonts::reset_cache();
    }

    /**
     * Drop the memo even when a test fails.
     */
    protected function tearDown(): void {
        fonts::reset_cache();
        parent::tearDown();
    }

    /**
     * A logged-in user with every feature enabled.
     *
     * @return void
     */
    private function setup_user(): void {
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * The panel's template context for the current user.
     *
     * @return array
     */
    private static function context(): array {
        global $PAGE;
        $PAGE->set_url('/');
        $PAGE->set_pagelayout('standard');
        return (new panel())->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Disable features in the admin features table.
     *
     * @param string[] $ids
     * @return void
     */
    private static function disable(array $ids): void {
        global $DB;
        foreach ($ids as $id) {
            $DB->set_field('local_accessibility_widgets', 'enabled', 0, ['name' => $id]);
        }
        \cache::make('local_accessibility', 'enabled')->purge();
    }

    /**
     * One tile by id.
     *
     * @param array $context
     * @param string $id
     * @return array|null
     */
    private static function tile(array $context, string $id): ?array {
        return array_column($context['tiles'], null, 'id')[$id] ?? null;
    }

    /**
     * One detail view by id.
     *
     * @param array $context
     * @param string $id
     * @return array|null
     */
    private static function view(array $context, string $id): ?array {
        return array_column($context['views'], null, 'id')[$id] ?? null;
    }

    /**
     * Every radio group in the context: drawers, the size quick picks, the font, line width and colour views, and
     * each spacing group.
     *
     * @param array $context
     * @return array<string, array> group name => options
     */
    private static function radiogroups(array $context): array {
        $groups = [];
        foreach ($context['drawers'] as $drawer) {
            $groups['drawer ' . $drawer['id']] = $drawer['options'];
        }
        foreach ($context['views'] as $view) {
            if (!empty($view['groups'])) {
                foreach ($view['groups'] as $group) {
                    $groups['spacing ' . $group['id']] = $group['options'];
                }
            } else {
                $groups['view ' . $view['id']] = $view['options'];
            }
        }
        return $groups;
    }

    /**
     * A fresh user sees every tile in admin order, each stating its value in words.
     */
    public function test_every_tile_has_value_text(): void {
        $this->setup_user();
        $context = self::context();
        $this->assertSame(['size', 'font', 'spacing', 'align', 'colour', 'narrow', 'links', 'images', 'guide', 'motion',
            'read', 'saturation', 'focus', 'cursor'], array_column($context['tiles'], 'id'));
        foreach ($context['tiles'] as $tile) {
            $this->assertNotSame('', trim($tile['valuetext']), $tile['id']);
            $this->assertNotSame('', trim($tile['label']), $tile['id']);
            $this->assertStringStartsWith('fa-', $tile['icon'], $tile['id']);
            $this->assertFalse($tile['active'], $tile['id']);
            $this->assertFalse($tile['locked'], $tile['id']);
        }
        $this->assertSame('100%', self::tile($context, 'size')['valuetext']);
        $this->assertSame('Site default', self::tile($context, 'spacing')['valuetext']);
        $this->assertSame('Site default', self::tile($context, 'font')['valuetext']);
        $this->assertSame('Site default', self::tile($context, 'align')['valuetext']);
        $this->assertSame('Spacing', self::tile($context, 'spacing')['label']);

        preferences::set('align', 'center');
        preferences::set('size', '150');
        preferences::set('font', 'dyslexic');
        $context = self::context();
        $this->assertSame('Centre', self::tile($context, 'align')['valuetext']);
        $this->assertTrue(self::tile($context, 'align')['active']);
        $this->assertSame('150%', self::tile($context, 'size')['valuetext']);
        $this->assertSame('OpenDyslexic', self::tile($context, 'font')['valuetext']);
        $this->assertFalse(self::tile($context, 'links')['active']);
    }

    /**
     * The spacing tile summarises its non-default members.
     */
    public function test_spacing_summary(): void {
        $this->setup_user();
        preferences::set('lineheight', '180');
        preferences::set('letterspacing', '12');
        $tile = self::tile(self::context(), 'spacing');
        $this->assertSame('Line 1.8 · Letter 0.12', $tile['valuetext']);
        $this->assertTrue($tile['active']);
        preferences::set('wordspacing', '16');
        $this->assertSame('Line 1.8 · Letter 0.12 · Word 0.16', self::tile(self::context(), 'spacing')['valuetext']);
        preferences::set('lineheight', 'default');
        preferences::set('letterspacing', 'default');
        $tile = self::tile(self::context(), 'spacing');
        $this->assertSame('Word 0.16', $tile['valuetext']);
        $this->assertSame('Spacing, Word 0.16', $tile['label'] . ', ' . $tile['valuetext']);
    }

    /**
     * The spacing tile takes the place of its first enabled member and lists only the enabled ones (plan D6).
     */
    public function test_spacing_tile_position_and_members(): void {
        $this->setup_user();
        $context = self::context();
        $this->assertSame(['lineheight', 'letterspacing', 'wordspacing'], self::tile($context, 'spacing')['members']);

        self::disable(['lineheight']);
        $context = self::context();
        $tiles = array_column($context['tiles'], 'id');
        $this->assertSame(2, array_search('spacing', $tiles, true));
        $this->assertSame(['letterspacing', 'wordspacing'], self::tile($context, 'spacing')['members']);
        $this->assertSame(['letterspacing', 'wordspacing'], array_column(self::view($context, 'spacing')['groups'], 'id'));
        $this->assertArrayNotHasKey('lineheight', json_decode($context['state'], true));

        self::disable(['letterspacing', 'wordspacing']);
        $context = self::context();
        $this->assertNull(self::tile($context, 'spacing'));
        $this->assertNull(self::view($context, 'spacing'));
    }

    /**
     * Short lists open a drawer under the tile; rich features open a detail view.
     */
    public function test_kinds(): void {
        $this->setup_user();
        $context = self::context();
        $drawers = ['align', 'links', 'images', 'guide', 'motion', 'read', 'saturation', 'focus', 'cursor'];
        $this->assertSame($drawers, array_column($context['drawers'], 'id'));
        $this->assertSame(['size', 'font', 'spacing', 'colour', 'narrow'], array_column($context['views'], 'id'));
        foreach ($context['tiles'] as $tile) {
            $drawer = in_array($tile['id'], array_column($context['drawers'], 'id'), true);
            $this->assertSame($drawer ? 'drawer' : 'detail', $tile['kind'], $tile['id']);
            $this->assertSame($drawer, $tile['isdrawer'], $tile['id']);
        }
        foreach (['size', 'font', 'spacing', 'colour', 'narrow'] as $id) {
            $view = self::view($context, $id);
            $this->assertTrue($view['is' . $id], $id);
            $this->assertNotSame('', $view['label'], $id);
        }
        $size = self::view($context, 'size');
        $this->assertSame(['100', '125', '150', '200', '250', '300'], array_column($size['options'], 'value'));
        $this->assertSame([80, 300, 10], [$size['min'], $size['max'], $size['step']]);
        $this->assertSame('100', $size['value']);
        $this->assertSame('100%', $size['valuetext']);
        $narrow = array_column(self::view($context, 'narrow')['options'], 'bar', 'value');
        $this->assertSame(['off' => 100, '90' => 100, '80' => 89, '70' => 78, '60' => 67, '50' => 56, '40' => 44], $narrow);
    }

    /**
     * Every option shows an icon or a visual preview with its text, and each group has exactly one checked option,
     * which is the one reachable by Tab.
     */
    public function test_options_have_icon_or_preview_and_checked(): void {
        $this->setup_user();
        preferences::set('links', 'highlight');
        preferences::set('colour', 'cream');
        $groups = self::radiogroups(self::context());
        $this->assertCount(9 + 4 + 3, $groups);
        foreach ($groups as $name => $options) {
            $this->assertNotEmpty($options, $name);
            foreach ($options as $o) {
                $this->assertNotSame('', trim($o['label']), $name);
                $this->assertTrue(!empty($o['icon']) || !empty($o['sample']) || !empty($o['bar']), $name . ' ' . $o['value']);
            }
            $this->assertCount(1, array_filter(array_column($options, 'checked')), $name);
            $this->assertCount(1, array_filter(array_column($options, 'focusable')), $name);
        }
        $this->assertSame(['highlight'], array_keys(array_filter(array_column($groups['drawer links'], 'checked', 'value'))));
        $this->assertSame(['cream'], array_keys(array_filter(array_column($groups['view colour'], 'checked', 'value'))));
        // A size with no quick pick: no pick is checked, and the first one takes the Tab stop.
        preferences::set('size', '110');
        $picks = self::radiogroups(self::context())['view size'];
        $this->assertSame([], array_filter(array_column($picks, 'checked')));
        $this->assertTrue($picks[0]['focusable']);
    }

    /**
     * The state JSON describes every enabled feature for panel.js, with locks.
     */
    public function test_state_json(): void {
        $this->setup_user();
        $state = json_decode(self::context()['state'], true);
        $this->assertCount(16, $state);
        $this->assertSame('100', $state['size']['default']);
        $this->assertSame('100', $state['size']['value']);
        $this->assertSame('detail', $state['size']['kind']);
        $this->assertSame('spacing', $state['wordspacing']['tile']);
        $this->assertSame('drawer', $state['align']['kind']);
        $option = ['value' => '150', 'label' => '150%', 'css' => ['--a11y-size' => '150']];
        $this->assertContains($option, $state['size']['options']);
        $this->assertFalse($state['lineheight']['locked']);

        set_config('lock_spacing', 1, 'local_accessibility');
        $context = self::context();
        $state = json_decode($context['state'], true);
        foreach (['lineheight', 'letterspacing', 'wordspacing'] as $id) {
            $this->assertTrue($state[$id]['locked'], $id);
        }
        $this->assertFalse($state['size']['locked']);
        $this->assertTrue(self::tile($context, 'spacing')['locked']);
        // Empty CSS is an object, so panel.js can always iterate it.
        $this->assertStringContainsString('"css":{}', $context['state']);
    }

    /**
     * Font options show "Aa" and their label in their own font, and uploaded fonts carry their faces.
     */
    public function test_font_options_render_own_font(): void {
        global $OUTPUT;
        $this->setup_user();
        get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
            'component' => 'local_accessibility', 'filearea' => 'fonts', 'itemid' => 0, 'filepath' => '/',
            'filename' => 'Andika-Regular.woff2'], 'font data');
        fonts::reset_cache();
        $context = self::context();
        $options = array_column(self::view($context, 'font')['options'], null, 'value');
        $this->assertSame('Japanese UD Gothic', $options['jagothic']['label']);
        $this->assertStringStartsWith('font-family: "BIZ UDPGothic"', $options['jagothic']['samplestyle']);
        $this->assertSame($options['jagothic']['samplestyle'], $options['jagothic']['labelstyle']);
        $faces = json_decode($options['up_andika']['faces'], true);
        $this->assertSame(400, $faces[0]['weight']);
        $this->assertSame('woff2', $faces[0]['format']);

        $html = $OUTPUT->render_from_template('local_accessibility/panel', $context);
        $this->assertMatchesRegularExpression(
            '/<span class="la-sample"[^>]*style="font-family: &quot;BIZ UDPGothic&quot;/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/class="la-optlabel la-sample" style="font-family: &quot;BIZ UDPGothic&quot;[^"]*">\s*Japanese UD Gothic\s*</',
            $html
        );
        $this->assertStringContainsString('data-faces="', $html);
        $this->assertStringNotContainsString('[[', $html);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Drawer tiles control their drawer; the rendered radios have one Tab stop per group.
     */
    public function test_rendered_drawer(): void {
        global $OUTPUT;
        $this->setup_user();
        preferences::set('align', 'center');
        $html = $OUTPUT->render_from_template('local_accessibility/panel', self::context());
        $this->assertMatchesRegularExpression(
            '/data-tile="align"[^>]*aria-expanded="false"[^>]*aria-controls="la-drawer-align"/',
            $html
        );
        $this->assertMatchesRegularExpression('/<div class="la-drawer" id="la-drawer-align"[^>]*hidden/', $html);
        $this->assertMatchesRegularExpression(
            '/role="radio"[^>]*tabindex="0"[^>]*aria-checked="true"[^>]*data-feature="align"[^>]*data-value="center"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression('/data-tile="size"[^>]*aria-expanded/', $html);
        $this->assertMatchesRegularExpression('/data-tile="align"[^>]*data-active/', $html);
        $this->assertStringContainsString('<span class="la-value" aria-hidden="true">Centre</span>', $html);
    }
}
