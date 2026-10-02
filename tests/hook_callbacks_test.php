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
        $this->resetAfterTest();
        preferences::sync_features_table();
        foreach (['both' => 1, 'menu' => 1, 'floating' => 0] as $mode => $expect) {
            set_config('launcher', $mode, 'local_accessibility');
            $hook = new \core_user\hook\extend_user_menu();
            hook_callbacks::user_menu($hook);
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
        $this->assertSame('; Path=/; SameSite=Lax; Max-Age=31536000', colour_mode::cookie_attributes());
        $CFG->sessioncookiepath = '/moodle/';
        $CFG->sessioncookiedomain = 'example.com';
        $this->assertStringContainsString('; Path=/moodle/;', colour_mode::cookie_attributes());
        $this->assertStringContainsString('; Domain=example.com', colour_mode::cookie_attributes());
        $CFG->cookiesecure = true;
        $CFG->sslproxy = true;
        $this->assertStringEndsWith('; Secure', colour_mode::cookie_attributes());
    }
}
