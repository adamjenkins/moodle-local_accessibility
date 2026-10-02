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

/**
 * Behat steps for local_accessibility.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Steps for the accessibility panel.
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_accessibility extends behat_base {
    /**
     * Check an attribute on <html>, waiting for it while the page settles (a profile or swatch saves, then reloads).
     *
     * @Then /^the page root should have attribute "(?P<attr>[^"]*)" with value "(?P<value>[^"]*)"$/
     * @param string $attr
     * @param string $value
     */
    public function the_page_root_should_have_attribute(string $attr, string $value): void {
        $this->spin(function () use ($attr, $value) {
            $actual = $this->getSession()->getPage()->find('css', 'html')->getAttribute($attr);
            if ($actual !== $value) {
                throw new ExpectationException(
                    "Expected $attr=\"$value\" on html, got " . var_export($actual, true),
                    $this->getSession()
                );
            }
            return true;
        });
    }

    /**
     * Check an attribute is absent on <html>.
     *
     * @Then /^the page root should not have attribute "(?P<attr>[^"]*)"$/
     * @param string $attr
     */
    public function the_page_root_should_not_have_attribute(string $attr): void {
        if ($this->getSession()->getPage()->find('css', 'html')->hasAttribute($attr)) {
            throw new ExpectationException("Unexpected $attr on html", $this->getSession());
        }
    }

    /**
     * Press Alt+A, dispatched with key 'a' (the shortcut's primary match).
     *
     * @When I press the accessibility shortcut
     */
    public function i_press_the_accessibility_shortcut(): void {
        $this->execute_script(
            "document.dispatchEvent(new KeyboardEvent('keydown', {key: 'a', code: 'KeyA', altKey: true, bubbles: true}));"
        );
    }

    /**
     * Set a colour input in the colour editor and fire input and change, as a picker does.
     *
     * In Chrome under Behat, the core field step left the editor's ratio unchanged (no input event reached it).
     *
     * @When /^I set the colour field "(?P<name>[^"]*)" to "(?P<hex>#[0-9a-fA-F]{6})"$/
     * @param string $name the visible label, for example "Background"
     * @param string $hex
     */
    public function i_set_the_colour_field_to(string $name, string $hex): void {
        $script = 'return (function(name, hex) {
            const labels = [...document.querySelectorAll("#local-accessibility-panel .la-editor label")];
            const label = labels.find((l) => l.textContent.trim() === name);
            const input = label ? label.querySelector("input[type=color]") : null;
            if (!input) {
                return false;
            }
            input.value = hex;
            input.dispatchEvent(new Event("input", {bubbles: true}));
            input.dispatchEvent(new Event("change", {bubbles: true}));
            return input.value === hex.toLowerCase();
        })(' . json_encode($name) . ', ' . json_encode($hex) . ');';
        if (!$this->evaluate_script($script)) {
            throw new ExpectationException("Colour field \"$name\" not found or not set to $hex", $this->getSession());
        }
    }

    /**
     * Press Escape on the focused element inside the accessibility dialog.
     *
     * In Chrome under Behat, the core Escape step did not close the colour editor while its colour field had focus
     * (the editor stayed open), so this dispatches the keydown on the focused element, as a browser would.
     *
     * @When I press escape in the accessibility dialog
     */
    public function i_press_escape_in_the_accessibility_dialog(): void {
        $script = 'return (function() {
            const el = document.activeElement;
            if (!el || !el.closest("#local-accessibility-panel")) {
                return false;
            }
            el.dispatchEvent(new KeyboardEvent("keydown", {key: "Escape", code: "Escape", bubbles: true, cancelable: true}));
            return true;
        })();';
        if (!$this->evaluate_script($script)) {
            throw new ExpectationException('Focus is not inside the accessibility dialog', $this->getSession());
        }
    }
}
