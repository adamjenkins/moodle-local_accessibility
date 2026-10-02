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
        preferences::set('size', '175');
        $PAGE->set_url('/');
        $hook = new \core\hook\output\before_html_attributes($PAGE->get_renderer('core'), ['lang' => 'en']);
        hook_callbacks::html_attributes($hook);
        $this->assertSame('175', $hook->get_attributes()['data-a11y-size']);
        $this->assertSame('en', $hook->get_attributes()['lang']);
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
        preferences::set('size', '175');
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
