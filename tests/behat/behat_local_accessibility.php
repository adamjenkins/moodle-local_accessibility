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

    /**
     * Make the page see no on-device voice, as in a browser without local speech, then tell it the voice list changed.
     *
     * Headless browsers differ in the voices they report, so the page's own list is replaced rather than relied on.
     *
     * @Given the browser has no on-device voices
     */
    public function the_browser_has_no_on_device_voices(): void {
        $this->execute_script('if ("speechSynthesis" in window) {
            window.speechSynthesis.getVoices = () => [];
            window.speechSynthesis.dispatchEvent(new Event("voiceschanged"));
        }');
    }

    /**
     * Check that each content image's alt text reaches assistive technology exactly once.
     *
     * It reaches it from the image itself while the image is shown, or from the plugin's replacement text while Images
     * hides it. An element counts when no ancestor is aria-hidden and it is rendered: display:none and visibility:hidden
     * both take an element out of the accessibility tree.
     *
     * @Then the alt text of each content image should reach assistive technology exactly once
     */
    public function the_alt_text_should_reach_assistive_technology_once(): void {
        $script = 'return (function() {
            const exposed = (el) => !el.closest("[aria-hidden=\'true\']") && el.checkVisibility({visibilityProperty: true});
            const imgs = [...document.querySelectorAll("#region-main img")].filter((i) => i.alt);
            if (!imgs.length) {
                return "No content image with alt text on this page";
            }
            for (const img of imgs) {
                const next = img.nextElementSibling;
                const alt = next && next.classList.contains("la-alt") && next.textContent === img.alt ? next : null;
                const count = (exposed(img) ? 1 : 0) + (alt && exposed(alt) ? 1 : 0);
                if (count !== 1) {
                    return "\"" + img.alt + "\" reaches assistive technology " + count + " times";
                }
            }
            return "";
        })();';
        $error = $this->evaluate_script($script);
        if ($error !== '') {
            throw new ExpectationException($error, $this->getSession());
        }
    }

    /** @var bool whether this scenario switched on forced-colours emulation */
    private bool $forcedcolours = false;

    /**
     * Colour helpers shared by the style steps: parse a computed colour, find the colour behind an element and
     * compute the WCAG contrast ratio.
     *
     * @return string JavaScript declaring parse(), behind() and ratio()
     */
    private function colour_helpers(): string {
        return 'const parse = (c) => {
                const m = String(c).match(/rgba?\(([^)]+)\)/);
                if (!m) {
                    return null;
                }
                const p = m[1].split(/[\s,\/]+/).filter((v) => v !== "").map(Number);
                return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1];
            };
            const behind = (el) => {
                const layers = [];
                for (let e = el; e; e = e.parentElement) {
                    const c = parse(getComputedStyle(e).backgroundColor);
                    if (c && c[3] > 0) {
                        layers.push(c);
                        if (c[3] >= 1) {
                            break;
                        }
                    }
                }
                let out = [255, 255, 255];
                for (let i = layers.length - 1; i >= 0; i--) {
                    const a = layers[i][3];
                    out = out.map((v, k) => layers[i][k] * a + v * (1 - a));
                }
                return out;
            };
            const lum = (rgb) => {
                const f = (v) => {
                    v /= 255;
                    return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
                };
                return 0.2126 * f(rgb[0]) + 0.7152 * f(rgb[1]) + 0.0722 * f(rgb[2]);
            };
            const ratio = (a, b) => {
                const x = lum(a);
                const y = lum(b);
                return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
            };
            const over = (c, back) => c.slice(0, 3).map((v, k) => v * c[3] + back[k] * (1 - c[3]));';
    }

    /**
     * Run a script that returns "" on success or an error message, and fail with that message.
     *
     * @param string $body JavaScript statements; the colour helpers are in scope
     */
    private function check_script(string $body): void {
        $error = $this->evaluate_script('return (function() {' . $this->colour_helpers() . $body . '})();');
        if ($error !== '') {
            throw new ExpectationException((string) $error, $this->getSession());
        }
    }

    /**
     * Check a computed style property of an element, or of its pseudo-element (selector ending ::before or ::after).
     *
     * @Then /^the computed "(?P<property>[^"]*)" of "(?P<css>[^"]*)" should (?P<not>not )?be "(?P<value>[^"]*)"$/
     * @param string $property
     * @param string $css
     * @param string $not "not " to check that the value differs
     * @param string $value
     */
    public function the_computed_style_should_be(string $property, string $css, string $not, string $value): void {
        $this->check_script('const parts = ' . json_encode($css) . '.split("::");
            const el = document.querySelector(parts[0]);
            if (!el) {
                return "No element matches " + parts[0];
            }
            const actual = getComputedStyle(el, parts[1] ? "::" + parts[1] : null).getPropertyValue(' . json_encode($property) . ');
            const same = actual === ' . json_encode($value) . ';
            return same === ' . ($not ? 'false' : 'true') . ' ? "" : ' . json_encode("$property of $css: ") . ' + actual;');
    }

    /**
     * Compare one computed style property of two elements.
     *
     * @Then /^the computed "(?P<property>[^"]*)" of "(?P<css>[^"]*)" should differ from that of "(?P<other>[^"]*)"$/
     * @param string $property
     * @param string $css
     * @param string $other
     */
    public function the_computed_style_should_differ(string $property, string $css, string $other): void {
        $this->check_script('const a = document.querySelector(' . json_encode($css) . ');
            const b = document.querySelector(' . json_encode($other) . ');
            if (!a || !b) {
                return "Missing element: " + (a ? ' . json_encode($other) . ' : ' . json_encode($css) . ');
            }
            const p = ' . json_encode($property) . ';
            const va = getComputedStyle(a).getPropertyValue(p);
            const vb = getComputedStyle(b).getPropertyValue(p);
            return va === vb ? "Both have " + p + ": " + va : "";');
    }

    /**
     * Check that two elements differ visibly: colours, borders, outline, shadow or background image.
     *
     * @Then /^the "(?P<css>[^"]*)" element should look different from the "(?P<other>[^"]*)" element$/
     * @param string $css
     * @param string $other
     */
    public function the_element_should_look_different_from(string $css, string $other): void {
        $this->check_script('const props = ["background-color", "background-image", "border-top-color", "border-top-width",
                "border-top-style", "outline-style", "outline-width", "outline-color", "box-shadow"];
            const a = document.querySelector(' . json_encode($css) . ');
            const b = document.querySelector(' . json_encode($other) . ');
            if (!a || !b) {
                return "Missing element: " + (a ? ' . json_encode($other) . ' : ' . json_encode($css) . ');
            }
            const look = (el) => props.map((p) => p + ": " + getComputedStyle(el).getPropertyValue(p)).join("; ");
            return look(a) === look(b) ? "Both look the same: " + look(a) : "";');
    }

    /**
     * Check the contrast of an element's text colour against what is behind it.
     *
     * @Then /^the "(?P<css>[^"]*)" element should have a text contrast of at least (?P<min>[0-9.]+):1$/
     * @param string $css
     * @param string $min
     */
    public function the_element_should_have_text_contrast(string $css, string $min): void {
        $this->check_script('const el = document.querySelector(' . json_encode($css) . ');
            if (!el) {
                return "No element matches " + ' . json_encode($css) . ';
            }
            const back = behind(el);
            const fg = parse(getComputedStyle(el).color);
            if (!fg) {
                return "Unreadable colour " + getComputedStyle(el).color;
            }
            const r = ratio(over(fg, back), back);
            return r >= ' . (float) $min . ' ? "" : "Contrast " + r.toFixed(2) + ":1 (text " + getComputedStyle(el).color +
                " on rgb(" + back.map(Math.round).join(", ") + "))";');
    }

    /**
     * Check that an element stands out from what is behind it (non-text contrast 3:1), by its fill or its border.
     *
     * @Then /^the "(?P<css>[^"]*)" element should stand out from its background$/
     * @param string $css
     */
    public function the_element_should_stand_out(string $css): void {
        $this->check_script('const el = document.querySelector(' . json_encode($css) . ');
            if (!el) {
                return "No element matches " + ' . json_encode($css) . ';
            }
            const s = getComputedStyle(el);
            const back = behind(el.parentElement);
            const fill = parse(s.backgroundColor);
            const edge = parse(s.borderTopColor);
            const best = Math.max(fill && fill[3] > 0 ? ratio(over(fill, back), back) : 1,
                edge && parseFloat(s.borderTopWidth) > 0 && s.borderTopStyle !== "none" ? ratio(over(edge, back), back) : 1);
            return best >= 3 ? "" : "Contrast " + best.toFixed(2) + ":1 (fill " + s.backgroundColor + ", border " +
                s.borderTopWidth + " " + s.borderTopColor + " on rgb(" + back.map(Math.round).join(", ") + "))";');
    }

    /**
     * Give an element keyboard focus (it must match :focus-visible) and check its focus indicator.
     *
     * The indicator must change the element's look, and must be an outline of at least 2px with 3:1 contrast against
     * what is behind the element.
     *
     * @Then /^the "(?P<css>[^"]*)" element should show a keyboard focus indicator$/
     * @param string $css
     */
    public function the_element_should_show_a_focus_indicator(string $css): void {
        $this->check_script('const el = document.querySelector(' . json_encode($css) . ');
            if (!el) {
                return "No element matches " + ' . json_encode($css) . ';
            }
            const props = ["outline-style", "outline-width", "outline-color", "outline-offset", "box-shadow"];
            const look = () => props.map((p) => p + ": " + getComputedStyle(el).getPropertyValue(p)).join("; ");
            el.blur();
            const before = look();
            el.focus();
            if (!el.matches(":focus-visible")) {
                return "Could not give " + ' . json_encode($css) . ' + " keyboard focus (:focus-visible)";
            }
            const after = look();
            if (before === after) {
                return "Same look with and without focus: " + after;
            }
            const s = getComputedStyle(el);
            const colour = parse(s.outlineColor);
            if (s.outlineStyle === "none" || parseFloat(s.outlineWidth) < 2 || !colour) {
                return "No focus outline of at least 2px: " + after;
            }
            const back = behind(el.parentElement);
            const r = ratio(over(colour, back), back);
            return r >= 3 ? "" : "Focus outline contrast " + r.toFixed(2) + ":1 (" + s.outlineColor + " on rgb(" +
                back.map(Math.round).join(", ") + "))";');
    }

    /**
     * Make every Bootstrap 5 custom property (--bs-*) undefined on the page, as on Moodle 4.5 (Bootstrap 4).
     *
     * Each one is set to the CSS-wide keyword initial on <html>, which makes it the guaranteed-invalid value, so
     * var(--bs-x) behaves as it does where --bs-x was never declared.
     *
     * @Given the page has no Bootstrap 5 custom properties
     */
    public function the_page_has_no_bootstrap5_properties(): void {
        $this->check_script('const names = new Set();
            const walk = (list) => {
                for (const rule of list) {
                    if (rule.cssRules) {
                        walk(rule.cssRules);
                    }
                    if (rule.style) {
                        for (const p of rule.style) {
                            if (p.startsWith("--bs-")) {
                                names.add(p);
                            }
                        }
                    }
                }
            };
            for (const sheet of document.styleSheets) {
                try {
                    walk(sheet.cssRules);
                } catch (e) {
                    continue;
                }
            }
            names.forEach((n) => document.documentElement.style.setProperty(n, "initial"));
            const probe = getComputedStyle(document.documentElement).getPropertyValue("--bs-primary");
            if (!names.has("--bs-primary") || probe !== "") {
                return "Could not undefine the Bootstrap 5 properties (found " + names.size + ", --bs-primary: " + probe + ")";
            }
            return "";');
    }

    /**
     * Add core-style controls to the main region: a link, a moodleform submit input, checkboxes, radios, a text field,
     * pagination with a current page and a menu with an active item, as core's templates render them.
     *
     * @Given the main region contains core controls
     */
    public function the_main_region_contains_core_controls(): void {
        $html = '<div id="la-test-controls">
            <p><a id="la-test-link" href="#">A test link</a></p>
            <input type="submit" class="btn btn-primary" id="la-test-submit" value="Save changes">
            <input type="checkbox" class="form-check-input" id="la-test-checked" checked>
            <input type="checkbox" class="form-check-input" id="la-test-unchecked">
            <input type="radio" class="form-check-input" name="la-test-r" id="la-test-radio" checked>
            <input type="radio" class="form-check-input" name="la-test-r2" id="la-test-radio-off">
            <input type="text" class="form-control" id="la-test-text" value="Typed text">
            <nav><ul class="pagination">
                <li class="page-item"><a href="#" class="page-link">1</a></li>
                <li class="page-item active"><a href="#" class="page-link" id="la-test-page" aria-current="page">2</a></li>
            </ul></nav>
            <div class="dropdown-menu show" style="position: static">
                <a class="dropdown-item" href="#">One</a>
                <a class="dropdown-item active" id="la-test-menuitem" href="#" aria-current="true">Two</a>
            </div>
            <span class="MathJax"><span id="la-test-maths">x</span></span>
        </div>';
        $this->check_script('const main = document.querySelector("#region-main");
            if (!main) {
                return "No #region-main on this page";
            }
            main.insertAdjacentHTML("afterbegin", ' . json_encode($html) . ');
            return "";');
    }

    /**
     * Emulate forced colours (Windows High Contrast) in Chrome through the DevTools protocol.
     *
     * The step checks the browser really forces colours: an author background colour must be replaced.
     *
     * @Given the browser emulates forced colours
     */
    public function the_browser_emulates_forced_colours(): void {
        $this->set_forced_colours('active');
        $this->forcedcolours = true;
        $this->check_script('if (!window.matchMedia("(forced-colors: active)").matches) {
                return "forced-colors: active does not match";
            }
            const probe = document.createElement("div");
            probe.style.backgroundColor = "rgb(1, 2, 3)";
            document.body.appendChild(probe);
            const forced = getComputedStyle(probe).backgroundColor;
            probe.remove();
            return forced === "rgb(1, 2, 3)" ? "The browser matches forced-colors but does not force colours" : "";');
    }

    /**
     * Stop forced-colours emulation after a scenario that started it.
     *
     * @AfterScenario
     */
    public function stop_emulating_forced_colours(): void {
        if ($this->forcedcolours) {
            $this->forcedcolours = false;
            $this->set_forced_colours('');
        }
    }

    /**
     * Set the emulated forced-colors media feature ('' resets it).
     *
     * @param string $value active, none or ''
     */
    private function set_forced_colours(string $value): void {
        $cdp = new \Facebook\WebDriver\Chrome\ChromeDevToolsDriver($this->getSession()->getDriver()->getWebDriver());
        $cdp->execute('Emulation.setEmulatedMedia', ['features' => [['name' => 'forced-colors', 'value' => $value]]]);
    }

    /**
     * Add a style sheet to the page, for example to stand in for a theme rule that is off on Behat sites.
     *
     * @Given /^the page has the extra style "(?P<css>[^"]*)"$/
     * @param string $css
     */
    public function the_page_has_the_extra_style(string $css): void {
        $this->execute_script('const s = document.createElement("style"); s.textContent = ' . json_encode($css) .
            '; document.head.appendChild(s);');
    }
}
