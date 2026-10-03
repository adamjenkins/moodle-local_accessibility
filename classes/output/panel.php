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

use local_accessibility\colour\scheme;
use local_accessibility\feature\base;
use local_accessibility\feature\colour;
use local_accessibility\feature\numeric;
use local_accessibility\feature\registry;
use local_accessibility\preferences;

/**
 * The launcher and the dialog: tiles that state their value, drawers and detail views (choices spec §2).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panel implements \renderable, \templatable {
    /** @var string[] Text size quick picks in the size view (spec §3). */
    private const QUICK_SIZES = ['100', '125', '150', '200', '250', '300'];

    /** @var array<string, string> Spacing summary string per member (spec §3 Spacing). */
    private const SPACING_PARTS = ['lineheight' => 'spacing_line', 'letterspacing' => 'spacing_letter',
        'wordspacing' => 'spacing_word'];

    /** @var array<string, string> Icons of tiles shared by several features; others use their feature's icon. */
    private const TILE_ICONS = ['spacing' => 'fa-arrows-up-down'];

    /**
     * The site's own font: Bootstrap 5's body font (Moodle 5.x), else Bootstrap 4's (Moodle 4.5). A constant, so it is
     * safe in a style attribute.
     */
    private const SITE_FONT = 'var(--bs-body-font-family, var(--font-family-sans-serif, sans-serif))';

    /**
     * The site's own colours for the "Site default" swatch: Boost's light body colours, as literals. Not
     * var(--bs-body-*): a chosen scheme repoints those on the html element (styles.css section 7), so the swatch would
     * show that scheme instead of the way back to the site's colours.
     */
    private const SITE_COLOURS = 'background: #fff; color: #1d2125';

    /**
     * Template context.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $PAGE;
        $enabled = preferences::enabled_ids();
        $values = [];
        foreach ($enabled as $id) {
            $values[$id] = preferences::get($id);
        }
        $tiles = [];
        $drawers = [];
        $views = [];
        foreach (registry::tiles($enabled) as $tileid => $members) {
            $first = registry::get($members[0]);
            $tile = self::tile($tileid, $members, $values);
            $tiles[] = $tile;
            if ($tile['isdrawer']) {
                $drawers[] = ['id' => $tileid, 'label' => $tile['label'], 'locked' => $tile['locked'],
                    'options' => self::radios($first, $values[$first->id()])];
            } else {
                $views[] = self::view($tileid, $members, $values, $tile);
            }
        }
        $custom = preferences::custom_scheme();
        $mode = get_config('local_accessibility', 'launcher') ?: 'both';
        return [
            'tiles' => $tiles,
            'drawers' => $drawers,
            'views' => $views,
            'state' => self::state($enabled, $values),
            'custom' => $custom ? ['bg' => $custom->ramp['page'], 'text' => $custom->text,
                'link' => $custom->link, 'exact' => $custom->exact] : null,
            // Guests and secure-layout pages have no user menu, so they always get the floating launcher (R16).
            'floating' => $mode !== 'menu' || preferences::uses_cookie() || $PAGE->pagelayout === 'secure',
            'profiles' => \local_accessibility\profiles::for_template(),
            // Exact custom colours under 7:1 (R18): the tile's warning icon is toggled by its hidden attribute.
            'lowcontrast' => isset(preferences::html_attributes()['data-a11y-lowcontrast']),
        ];
    }

    /**
     * One tile: its name, icon and current value in words (spec §2 "Tile face").
     *
     * @param string $tileid
     * @param string[] $members enabled feature ids shown by the tile
     * @param array $values feature id => current value of each enabled feature
     * @return array
     */
    private static function tile(string $tileid, array $members, array $values): array {
        $first = registry::get($members[0]);
        $shared = $tileid !== $first->id();
        $active = false;
        foreach ($members as $id) {
            $active = $active || $values[$id] !== registry::get($id)->default();
        }
        return [
            'id' => $tileid,
            'label' => $shared ? get_string('feature_' . $tileid, 'local_accessibility') : $first->label(),
            'icon' => self::TILE_ICONS[$tileid] ?? $first->icon(),
            'kind' => $first->kind(),
            'isdrawer' => $first->kind() === 'drawer',
            'iscolour' => $tileid === 'colour',
            'valuetext' => $tileid === 'spacing' ? self::spacing_summary($members, $values)
                : $first->value_label($values[$first->id()]),
            'active' => $active,
            // Features sharing a tile share its lock (spec §5).
            'locked' => preferences::is_locked($first->id()),
            'members' => $members,
        ];
    }

    /**
     * The spacing tile's value: its non-default members, e.g. "Line 1.8 · Letter 0.12", or "Site default".
     *
     * @param string[] $members
     * @param array $values feature id => current value of each enabled feature
     * @return string
     */
    private static function spacing_summary(array $members, array $values): string {
        $parts = [];
        foreach ($members as $id) {
            $f = registry::get($id);
            if ($values[$id] !== $f->default() && isset(self::SPACING_PARTS[$id])) {
                $parts[] = get_string(self::SPACING_PARTS[$id], 'local_accessibility', $f->value_label($values[$id]));
            }
        }
        return $parts ? implode(' · ', $parts) : get_string('sitedefault', 'local_accessibility');
    }

    /**
     * One detail view (spec §2 "Rich features").
     *
     * @param string $tileid
     * @param string[] $members
     * @param array $values feature id => current value of each enabled feature
     * @param array $tile the tile's context
     * @return array
     */
    private static function view(string $tileid, array $members, array $values, array $tile): array {
        $view = ['id' => $tileid, 'label' => $tile['label'], 'locked' => $tile['locked'], 'is' . $tileid => true];
        $f = registry::get($members[0]);
        switch ($tileid) {
            case 'size':
                $view['stepper'] = self::stepper($f, $values['size'], $tile['locked'], 'la-view-size-title');
                $view['options'] = self::radios($f, $values['size'], self::QUICK_SIZES);
                break;
            case 'spacing':
                $view['steppers'] = [];
                foreach ($members as $id) {
                    $view['steppers'][] = self::stepper(registry::get($id), $values[$id], $tile['locked']);
                }
                break;
            case 'narrow':
                $view['stepper'] = self::stepper($f, $values['narrow'], $tile['locked'], 'la-view-narrow-title');
                break;
            case 'colour':
                $view['options'] = self::swatches($values['colour'], $tile['locked']);
                break;
            default:
                $view['options'] = self::radios($f, $values[$f->id()]);
        }
        return $view;
    }

    /**
     * Radio options of one feature, each with an icon or a visual preview and its label (spec §2).
     *
     * @param base $f
     * @param string $current the current value
     * @param string[]|null $only values to offer, in this order (values the feature does not accept are skipped); null
     *     for all of the feature's listed values
     * @return array
     */
    private static function radios(base $f, string $current, ?array $only = null): array {
        $locked = preferences::is_locked($f->id());
        $options = $only === null ? $f->options()
            : array_map(fn($v) => $f->option($v), array_values(array_filter($only, fn($v) => $f->validate($v))));
        $out = [];
        foreach (array_values($options) as $o) {
            $radio = [
                'feature' => $f->id(),
                'value' => $o['value'],
                'label' => $o['label'],
                'icon' => $o['icon'],
                'checked' => $o['value'] === $current,
                'locked' => $locked,
                'sample' => null,
                'samplestyle' => null,
                'labelstyle' => null,
                'bar' => null,
                'faces' => null,
                'swatch' => false,
            ];
            $out[] = self::preview($radio, $o) + $radio;
        }
        return self::roving($out);
    }

    /**
     * The visual preview of one option (spec §3): text at its size, "Aa" and the label in its font, a bar as wide as
     * its line, or sample text with its spacing. Every style is built from validated values or constants.
     *
     * @param array $radio the radio being built
     * @param array $o the feature's option
     * @return array preview keys to set
     */
    private static function preview(array $radio, array $o): array {
        $sample = get_string('fontpreview', 'local_accessibility');
        switch ($o['preview']) {
            case 'size':
                // Three quarters of the chosen size, so 300% still fits a chip.
                return ['sample' => $sample, 'samplestyle' => 'font-size: ' . self::decimal((int) $o['value'] * 0.0075) . 'em'];
            case 'font':
                $style = 'font-family: ' . ($o['stack'] ?? self::SITE_FONT);
                return ['sample' => $sample, 'samplestyle' => $style, 'labelstyle' => $style,
                    'faces' => isset($o['faces']) ? json_encode($o['faces'], JSON_UNESCAPED_SLASHES) : null];
            case 'bar':
                return ['bar' => self::bar($o['value'])];
            case 'spacing':
                $property = ['lineheight' => 'line-height', 'letterspacing' => 'letter-spacing',
                    'wordspacing' => 'word-spacing'][$radio['feature']];
                $css = array_values($o['css']);
                return ['sample' => get_string('spacingpreview', 'local_accessibility'),
                    'samplestyle' => $property . ': ' . ($css[0] ?? 'normal')];
        }
        return [];
    }

    /**
     * One −/+ stepper of a numeric feature (numeric steppers brief): the value in the user's units, the buttons'
     * state and the feature's preview. Every style is built from validated values or constants.
     *
     * @param numeric $f
     * @param string $value its current, validated value
     * @param bool $locked
     * @param string|null $labelid id of the element that names the stepper; null to show the feature's name as its
     *     own heading
     * @return array
     */
    private static function stepper(numeric $f, string $value, bool $locked, ?string $labelid = null): array {
        $meta = $f->stepper();
        $isnumber = (bool) preg_match('/^-?\d+$/D', $value);
        $id = $f->id();
        $stepper = [
            'feature' => $id,
            'label' => $f->label(),
            'labelid' => $labelid ?? 'la-group-' . $id,
            'showlabel' => $labelid === null,
            'fieldvalue' => $f->field_value($value),
            'placeholder' => $isnumber ? '' : $f->value_label($value),
            'unit' => $meta['unit'],
            'locked' => $locked,
            'issize' => $id === 'size',
            'mindisabled' => $locked || ($meta['nonnegative'] && $isnumber && (int) $value <= $meta['min']),
            'maxdisabled' => $locked || ($isnumber ? (int) $value >= $meta['max'] : $meta['defaultismax']),
            'sample' => null,
            'samplestyle' => null,
            'bar' => null,
        ];
        if ($f->preview() === 'spacing') {
            $property = ['lineheight' => 'line-height', 'letterspacing' => 'letter-spacing',
                'wordspacing' => 'word-spacing'][$id];
            $css = array_values($f->css_properties($value));
            $stepper['sample'] = get_string('spacingpreview', 'local_accessibility');
            $stepper['samplestyle'] = $property . ': ' . ($css[0] ?? 'normal');
        } else if ($f->preview() === 'bar') {
            $stepper['bar'] = self::bar($value);
        }
        return $stepper;
    }

    /**
     * Width in percent of the line width preview bar: 90 characters or more, and full width, fill it.
     *
     * @param string $value
     * @return int
     */
    private static function bar(string $value): int {
        return preg_match('/^-?\d+$/D', $value) ? max(1, min(100, (int) round((int) $value / 90 * 100))) : 100;
    }

    /**
     * Colour scheme swatches: the site default, the built-in and site presets, and the user's custom scheme once
     * saved; each named and showing its colours (spec §3 colour).
     *
     * @param string $current
     * @param bool $locked
     * @return array
     */
    private static function swatches(string $current, bool $locked): array {
        $f = registry::get('colour');
        $schemes = scheme::presets() + colour::site_presets();
        $custom = preferences::custom_scheme();
        if ($custom !== null) {
            $schemes['custom'] = $custom;
        }
        $out = [self::swatch('default', $f->value_label('default'), self::SITE_COLOURS, $current, $locked)];
        foreach ($schemes as $id => $s) {
            // Validated #rrggbb values only (scheme::custom()), so they are safe in a style attribute.
            $style = 'background: ' . $s->ramp['page'] . '; color: ' . $s->text;
            $out[] = self::swatch($id, $f->value_label($id), $style, $current, $locked);
        }
        return self::roving($out);
    }

    /**
     * One colour swatch radio.
     *
     * @param string $id
     * @param string $label
     * @param string $style
     * @param string $current
     * @param bool $locked
     * @return array
     */
    private static function swatch(string $id, string $label, string $style, string $current, bool $locked): array {
        return ['feature' => 'colour', 'value' => $id, 'label' => $label, 'icon' => null, 'checked' => $id === $current,
            'locked' => $locked, 'sample' => get_string('fontpreview', 'local_accessibility'), 'samplestyle' => $style,
            'labelstyle' => null, 'bar' => null, 'faces' => null, 'swatch' => true];
    }

    /**
     * Give a radio group its one Tab stop: the checked option, else the first.
     *
     * @param array $options
     * @return array
     */
    private static function roving(array $options): array {
        $stop = array_search(true, array_column($options, 'checked'), true);
        foreach ($options as $i => &$o) {
            $o['focusable'] = $i === ($stop === false ? 0 : $stop);
        }
        return $options;
    }

    /**
     * The state of every enabled feature for panel.js (choices plan Task 5): value, built-in default, tile, kind,
     * lock and options with the CSS properties each one sets; numeric features add their stepper's range and units.
     *
     * @param string[] $enabled
     * @param array $values feature id => current value of each enabled feature
     * @return string JSON
     */
    private static function state(array $enabled, array $values): string {
        $state = [];
        foreach ($enabled as $id) {
            $f = registry::get($id);
            $options = [];
            if ($id === 'colour') {
                foreach (self::swatches($values[$id], false) as $s) {
                    $options[] = ['value' => $s['value'], 'label' => $s['label'], 'css' => (object) []];
                }
            } else {
                foreach ($f->options() as $o) {
                    $options[] = ['value' => $o['value'], 'label' => $o['label'], 'css' => (object) $o['css']];
                }
            }
            $state[$id] = ['value' => $values[$id], 'default' => $f->default(), 'tile' => $f->tile(),
                'kind' => $f->kind(), 'locked' => preferences::is_locked($id), 'options' => $options];
            if ($f instanceof numeric) {
                // Any number in the range is a value: panel.js steps, labels and applies it from this.
                $state[$id]['stepper'] = $f->stepper();
            }
        }
        return json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * A short decimal: 1.125, 0.75. %F, not %f: the CSS decimal point must not follow the locale.
     *
     * @param float $n
     * @return string
     */
    private static function decimal(float $n): string {
        return rtrim(rtrim(sprintf('%.3F', $n), '0'), '.');
    }
}
