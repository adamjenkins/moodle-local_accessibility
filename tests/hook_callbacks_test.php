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
 * Tests for the page hooks.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\hook_callbacks
 */
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Store a file in the uploaded fonts area.
     *
     * @param string $filename
     * @return void
     */
    private static function store_font(string $filename): void {
        get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
            'component' => 'local_accessibility', 'filearea' => 'fonts', 'itemid' => 0, 'filepath' => '/',
            'filename' => $filename], 'font data');
    }

    /**
     * The user menu hook's items, through the 5.3+ getter where it exists (MDL-88938 deprecates get_navitems()).
     *
     * @param \core_user\hook\extend_user_menu $hook
     * @return array
     */
    private static function menu_items(\core_user\hook\extend_user_menu $hook): array {
        return method_exists($hook, 'get_menu_items') ? $hook->get_menu_items() : $hook->get_navitems();
    }

    /**
     * Attributes reach the hook.
     */
    public function test_html_attributes(): void {
        global $PAGE;
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
        preferences::set('size', '180');
        $PAGE->set_url('/');
        $hook = new \core\hook\output\before_html_attributes($PAGE->get_renderer('core'), ['lang' => 'en']);
        hook_callbacks::html_attributes($hook);
        $this->assertSame('180', $hook->get_attributes()['data-a11y-size']);
        $this->assertStringContainsString('--a11y-size: 180', $hook->get_attributes()['style']);
        $this->assertSame('en', $hook->get_attributes()['lang']);
    }

    /**
     * Dispatch before_html_attributes through core's hook manager, so every listener runs in core's order.
     *
     * @return array<string, string> the resulting attributes
     */
    private function dispatch_html_attributes(): array {
        global $PAGE;
        $PAGE->set_url('/');
        $hook = new \core\hook\output\before_html_attributes($PAGE->get_renderer('core'), ['lang' => 'en']);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        return $hook->get_attributes();
    }

    /**
     * A plugin scheme's mode stands through the hook manager. Without core colour modes nothing else sets it.
     */
    public function test_scheme_mode_through_hook_manager(): void {
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('enablecolourmodes', 0, 'theme_boost');
        preferences::set('colour', 'yellowblack');
        $attrs = $this->dispatch_html_attributes();
        $this->assertSame('yellowblack', $attrs['data-a11y-colour']);
        $this->assertSame('dark', $attrs['data-bs-theme']);
        $this->assertSame('en', $attrs['lang']);
    }

    /**
     * On 5.3 with core colour modes on, the plugin's scheme mode wins over theme_boost's listener (which sets
     * data-bs-theme and data-colourmode on the same hook), and data-colourmode matches so core's auto script agrees.
     */
    public function test_scheme_mode_wins_over_core_colour_mode(): void {
        if (!class_exists(\theme_boost\colour_mode::class)) {
            $this->markTestSkipped('Core colour mode needs Moodle 5.3');
        }
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('enablecolourmodes', 1, 'theme_boost');
        set_config('defaultcolourmode', 'light', 'theme_boost');

        // Without a plugin scheme, core's listener is active and sets its own mode (proves the test is not vacuous).
        $attrs = $this->dispatch_html_attributes();
        $this->assertSame('light', $attrs['data-bs-theme']);
        $this->assertSame('light', $attrs['data-colourmode']);

        foreach (['yellowblack' => 'dark', 'highcontrast' => 'dark', 'cream' => 'light'] as $colour => $mode) {
            preferences::set('colour', $colour);
            $attrs = $this->dispatch_html_attributes();
            $this->assertSame($colour, $attrs['data-a11y-colour'], $colour);
            $this->assertSame($mode, $attrs['data-bs-theme'], $colour);
            $this->assertSame($mode, $attrs['data-colourmode'], $colour);
        }

        // A light scheme on a site whose default mode is dark (or auto) stays light.
        set_config('defaultcolourmode', 'auto', 'theme_boost');
        preferences::set('colour', 'cream');
        $attrs = $this->dispatch_html_attributes();
        $this->assertSame('light', $attrs['data-bs-theme']);
        $this->assertSame('light', $attrs['data-colourmode']);

        // Dark handed to core: core's own attributes stand.
        preferences::set('colour', 'dark');
        $attrs = $this->dispatch_html_attributes();
        $this->assertArrayNotHasKey('data-a11y-colour', $attrs);
        $this->assertSame('dark', $attrs['data-bs-theme']);
        $this->assertSame('dark', $attrs['data-colourmode']);
    }

    /**
     * Embedded and popup layouts get nothing (spec §4.1).
     */
    public function test_suppressed_layouts(): void {
        $this->resetAfterTest();
        $cases = ['embedded' => true, 'popup' => true, 'frametop' => true, 'standard' => false, 'secure' => false];
        foreach ($cases as $layout => $expect) {
            $page = new \moodle_page();
            $page->set_url('/');
            $page->set_pagelayout($layout);
            $this->assertSame($expect, hook_callbacks::is_suppressed($page), $layout);
        }
    }

    /**
     * The footer renders the dialog with its accessible structure.
     */
    public function test_footer_renders_dialog(): void {
        global $PAGE;
        $this->resetAfterTest();
        preferences::sync_features_table();
        $PAGE->set_url('/');
        $PAGE->set_pagelayout('standard');
        $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
        hook_callbacks::footer($hook);
        $html = $hook->get_output();
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('data-feature="size"', $html);
    }

    /**
     * The user menu entry follows the launcher setting.
     */
    public function test_user_menu(): void {
        global $PAGE;
        $this->resetAfterTest();
        preferences::sync_features_table();
        foreach (['both' => 1, 'menu' => 1, 'floating' => 0] as $mode => $expect) {
            set_config('launcher', $mode, 'local_accessibility');
            $hook = new \core_user\hook\extend_user_menu();
            hook_callbacks::user_menu($hook);
            if (method_exists($hook, 'get_menu_items')) {
                // Moodle 5.3+: menu item objects (MDL-88938).
                $items = $hook->get_menu_items();
                $this->assertCount($expect, $items, $mode);
                if ($expect) {
                    $this->assertInstanceOf(\core_user\output\user_action_menu\link::class, $items[0]);
                    $data = $items[0]->export_for_template($PAGE->get_renderer('core'));
                    $this->assertSame(get_string('accessibilitysettings', 'local_accessibility'), $data['title']);
                    $this->assertStringEndsWith('#local-accessibility-panel', $data['url']);
                }
                continue;
            }
            $items = $hook->get_navitems();
            $this->assertCount($expect, $items, $mode);
            if ($expect) {
                $this->assertSame('link', $items[0]->itemtype);
                $this->assertSame('accessibilitysettings,local_accessibility', $items[0]->titleidentifier);
                $this->assertStringEndsWith('#local-accessibility-panel', $items[0]->url->out(false));
            }
        }
    }

    /**
     * Guest cookie attributes follow the site cookie settings.
     *
     * @covers \local_accessibility\colour_mode::cookie_attributes
     */
    public function test_cookie_attributes(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->sessioncookiepath = '';
        $CFG->sessioncookiedomain = '';
        unset($CFG->cookiesecure);
        $CFG->wwwroot = 'http://example.com';
        $this->assertSame('; Path=/; SameSite=Lax; Max-Age=31536000', colour_mode::cookie_attributes());
        $CFG->wwwroot = 'http://example.com/lms';
        $this->assertStringStartsWith('; Path=/lms/;', colour_mode::cookie_attributes());
        $CFG->sessioncookiepath = '/moodle/';
        $CFG->sessioncookiedomain = 'example.com';
        $this->assertStringContainsString('; Path=/moodle/;', colour_mode::cookie_attributes());
        $this->assertStringContainsString('; Domain=example.com', colour_mode::cookie_attributes());
        $CFG->cookiesecure = true;
        $CFG->sslproxy = true;
        $this->assertStringEndsWith('; Secure', colour_mode::cookie_attributes());
    }

    /**
     * Code on disk but plugin not installed: no hook touches the plugin's tables.
     */
    public function test_not_installed_does_nothing(): void {
        global $PAGE;
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
        preferences::set('size', '180');
        unset_config('version', 'local_accessibility');
        $cache = \cache::make('local_accessibility', 'enabled');
        $cache->purge();
        $PAGE->set_url('/');
        $PAGE->set_pagelayout('standard');

        $attrs = new \core\hook\output\before_html_attributes($PAGE->get_renderer('core'), ['lang' => 'en']);
        hook_callbacks::html_attributes($attrs);
        $this->assertSame(['lang' => 'en'], $attrs->get_attributes());

        $footer = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
        hook_callbacks::footer($footer);
        $this->assertSame('', $footer->get_output());

        $menu = new \core_user\hook\extend_user_menu();
        hook_callbacks::user_menu($menu);
        $this->assertSame([], self::menu_items($menu));

        $this->assertTrue(hook_callbacks::is_suppressed($PAGE));
        // The enabled-features cache was never filled, so the features table was never queried.
        $this->assertFalse($cache->get('ids'));
    }

    /**
     * A running upgrade suppresses every page.
     */
    public function test_upgrade_running_suppresses(): void {
        global $CFG;
        $this->resetAfterTest();
        $page = new \moodle_page();
        $page->set_url('/');
        $page->set_pagelayout('standard');
        $this->assertFalse(hook_callbacks::is_suppressed($page));
        $CFG->upgraderunning = time();
        $this->assertTrue(hook_callbacks::is_suppressed($page));
    }

    /**
     * The escape hatch also removes the user menu entry.
     */
    public function test_user_menu_disabled(): void {
        global $CFG;
        $this->resetAfterTest();
        preferences::sync_features_table();
        $CFG->local_accessibility_disabled = true;
        $hook = new \core_user\hook\extend_user_menu();
        hook_callbacks::user_menu($hook);
        $this->assertSame([], self::menu_items($hook));
    }

    /**
     * In menu mode, users without a user menu still get the floating launcher (R16).
     *
     * @covers \local_accessibility\output\panel
     */
    public function test_menu_mode_floating_fallback(): void {
        global $PAGE;
        $this->resetAfterTest();
        preferences::sync_features_table();
        set_config('launcher', 'menu', 'local_accessibility');
        $PAGE->set_url('/');
        $PAGE->set_pagelayout('standard');
        $renderer = $PAGE->get_renderer('core');

        $this->setGuestUser();
        $this->assertTrue((new output\panel())->export_for_template($renderer)['floating']);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse((new output\panel())->export_for_template($renderer)['floating']);
        $this->setUser(0);
        $this->assertTrue((new output\panel())->export_for_template($renderer)['floating']);
    }

    /**
     * Logged-in users on a secure-layout page have no user menu either (R16).
     *
     * @covers \local_accessibility\output\panel
     */
    public function test_menu_mode_secure_layout(): void {
        global $PAGE;
        $this->resetAfterTest();
        preferences::sync_features_table();
        set_config('launcher', 'menu', 'local_accessibility');
        $this->setUser($this->getDataGenerator()->create_user());
        $PAGE->set_url('/');
        $PAGE->set_pagelayout('secure');
        $this->assertTrue((new output\panel())->export_for_template($PAGE->get_renderer('core'))['floating']);
    }

    /**
     * The colour tile's warning icon is shown only for exact custom colours under 7:1 (R18).
     *
     * @covers \local_accessibility\output\panel
     */
    public function test_panel_lowcontrast(): void {
        global $PAGE;
        $this->resetAfterTest();
        preferences::sync_features_table();
        $this->setUser($this->getDataGenerator()->create_user());
        $PAGE->set_url('/');
        $renderer = $PAGE->get_renderer('core');

        $this->assertFalse((new output\panel())->export_for_template($renderer)['lowcontrast']);
        preferences::set_custom_scheme(colour\scheme::custom('#3a6ea5', '#ffffff', '#ffe08a', true));
        $context = (new output\panel())->export_for_template($renderer);
        $this->assertTrue($context['lowcontrast']);
        $this->assertDoesNotMatchRegularExpression('/\shidden[\s>]/', $this->warn_icon($renderer, $context));
        preferences::set_custom_scheme(colour\scheme::custom('#3a6ea5', '#ffffff', '#ffe08a', false));
        $context = (new output\panel())->export_for_template($renderer);
        $this->assertFalse($context['lowcontrast']);
        $this->assertMatchesRegularExpression('/\shidden[\s>]/', $this->warn_icon($renderer, $context));
    }

    /**
     * The rendered warning icon tag from the panel.
     *
     * @param \renderer_base $renderer
     * @param array $context
     * @return string
     */
    private function warn_icon(\renderer_base $renderer, array $context): string {
        $html = $renderer->render_from_template('local_accessibility/panel', $context);
        $this->assertSame(1, preg_match('/<i [^>]*la-warn[^>]*>/', $html, $m));
        return $m[0];
    }

    /**
     * Site colour presets carry the admin's name into the tile's value label and each swatch's accessible name.
     */
    public function test_panel_site_preset_labels(): void {
        global $PAGE;
        $this->resetAfterTest();
        preferences::sync_features_table();
        set_config('sitepresets', json_encode([
            ['name' => 'Navy & <b>Gold</b>', 'bg' => '#0b1f3a', 'text' => '#ffffff', 'link' => '#ffe08a'],
            ['name' => 'Forest', 'bg' => '#0d2b1a', 'text' => '#ffffff', 'link' => '#ffe08a'],
            ['bg' => '#000000', 'text' => '#ffffff', 'link' => '#ffff00'],
        ]), 'local_accessibility');
        $this->setUser($this->getDataGenerator()->create_user());
        preferences::set('colour', 'site_1');
        $PAGE->set_url('/');
        $renderer = $PAGE->get_renderer('core');
        $context = (new output\panel())->export_for_template($renderer);

        $tile = array_values(array_filter($context['tiles'], fn($t) => $t['id'] === 'colour'))[0];
        $this->assertSame('Forest', $tile['valuelabel']);
        $labels = array_column($context['swatches'], 'label', 'id');
        $this->assertSame('Navy & Gold', $labels['site_0']);
        $this->assertSame('Forest', $labels['site_1']);
        $this->assertSame('Site scheme 3', $labels['site_2']);
        $this->assertCount(count($labels), array_unique($labels));

        $html = $renderer->render_from_template('local_accessibility/panel', $context);
        $this->assertStringNotContainsString('[[', $html);
        $this->assertMatchesRegularExpression('/la-colourlabel">\s*Forest\s*</', $html);
        $this->assertMatchesRegularExpression('/data-scheme="site_0"[^>]*aria-label="Navy &amp; Gold"/', $html);
        $this->assertDebuggingNotCalled();
    }

    /**
     * The head hook's output for the current user.
     *
     * @return string
     */
    private function head_output(): string {
        global $PAGE;
        $PAGE->set_url('/');
        $PAGE->set_pagelayout('standard');
        $hook = new \core\hook\output\before_standard_head_html_generation($PAGE->get_renderer('core'));
        hook_callbacks::head($hook);
        return $hook->get_output();
    }

    /**
     * The @font-face of an uploaded font is added to the head only while that font is selected (spec §4).
     *
     * @covers \local_accessibility\local\fonts::face_css
     */
    public function test_head_injects_uploaded_font(): void {
        $this->resetAfterTest();
        local\fonts::reset_cache();
        preferences::sync_features_table();
        self::store_font('MyFont-Regular.woff2');
        local\fonts::reset_cache();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertSame('', $this->head_output());
        preferences::set('font', 'up_myfont');
        $html = $this->head_output();
        $this->assertStringStartsWith('<style>@font-face{font-family:"local_accessibility_up_myfont"', $html);
        $this->assertStringEndsWith('</style>', $html);
        // The callback is registered in db/hooks.php: core's hook manager reaches it.
        global $PAGE;
        $hook = new \core\hook\output\before_standard_head_html_generation($PAGE->get_renderer('core'));
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $this->assertStringContainsString($html, $hook->get_output());
        preferences::set('font', 'serif');
        $this->assertSame('', $this->head_output());

        // A guest's cookie naming an unknown upload is ignored.
        $saved = $_COOKIE;
        $this->setUser(0);
        $_COOKIE[preferences::COOKIE] = json_encode(['font' => 'up_nosuch']);
        $this->assertSame('', $this->head_output());
        $_COOKIE[preferences::COOKIE] = json_encode(['font' => 'up_myfont']);
        $this->assertStringContainsString('local_accessibility_up_myfont', $this->head_output());
        $_COOKIE = $saved;

        // Nothing on suppressed pages or when the font feature is disabled.
        $this->setUser($this->getDataGenerator()->create_user());
        preferences::set('font', 'up_myfont');
        global $DB;
        $DB->set_field('local_accessibility_widgets', 'enabled', 0, ['name' => 'font']);
        \cache::make('local_accessibility', 'enabled')->purge();
        $this->assertSame('', $this->head_output());
        local\fonts::reset_cache();
    }

    /**
     * The shortcut is on unless explicitly turned off.
     */
    public function test_shortcut_enabled(): void {
        $this->resetAfterTest();
        unset_config('shortcut', 'local_accessibility');
        $this->assertTrue(hook_callbacks::shortcut_enabled());
        set_config('shortcut', '0', 'local_accessibility');
        $this->assertFalse(hook_callbacks::shortcut_enabled());
        set_config('shortcut', '1', 'local_accessibility');
        $this->assertTrue(hook_callbacks::shortcut_enabled());
    }
}
