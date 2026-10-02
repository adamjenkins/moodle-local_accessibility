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

use local_accessibility\colour\contrast;
use local_accessibility\colour\scheme;
use local_accessibility\feature\registry;
use moodle_exception;
use invalid_parameter_exception;

/**
 * Reads and writes the current user's feature values (spec §7.2, §7.3).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preferences {
    /** @var string Guest cookie name. */
    public const COOKIE = 'local_accessibility';
    /** @var string Preference prefix. */
    public const PREFIX = 'local_accessibility_';
    /** @var int Largest cookie accepted. */
    private const COOKIE_MAX = 2000;

    /**
     * Whether the current user stores settings in the cookie rather than preferences.
     *
     * @return bool
     */
    public static function uses_cookie(): bool {
        return !isloggedin() || isguestuser();
    }

    /**
     * Ensure every registry feature has a row in local_accessibility_widgets (enabled, in order).
     *
     * @return void
     */
    public static function sync_features_table(): void {
        global $DB;
        $existing = $DB->get_records_menu('local_accessibility_widgets', null, '', 'name, id');
        $seq = (int) $DB->get_field_sql('SELECT MAX(sequence) FROM {local_accessibility_widgets}');
        foreach (array_keys(registry::all()) as $id) {
            if (!isset($existing[$id])) {
                $DB->insert_record('local_accessibility_widgets', (object) ['name' => $id, 'enabled' => 1,
                    'sequence' => ++$seq]);
            }
        }
    }

    /**
     * Enabled feature ids in admin order.
     *
     * @return string[]
     */
    public static function enabled_ids(): array {
        global $DB;
        $cache = \cache::make('local_accessibility', 'enabled');
        $ids = $cache->get('ids');
        if ($ids === false) {
            $ids = array_values(array_intersect(
                $DB->get_fieldset_select('local_accessibility_widgets', 'name', 'enabled = 1 ORDER BY sequence'),
                array_keys(registry::all())
            ));
            $cache->set('ids', $ids);
        }
        return $ids;
    }

    /**
     * Whether a feature is enabled.
     *
     * @param string $id
     * @return bool
     */
    public static function is_enabled(string $id): bool {
        return in_array($id, self::enabled_ids(), true);
    }

    /**
     * Whether the admin locked a feature.
     *
     * @param string $id
     * @return bool
     */
    public static function is_locked(string $id): bool {
        return (bool) get_config('local_accessibility', 'lock_' . $id);
    }

    /**
     * Site default for a feature, validated.
     *
     * @param string $id
     * @return string
     */
    public static function site_default(string $id): string {
        $f = registry::get($id);
        $v = (string) get_config('local_accessibility', 'default_' . $id);
        return ($v !== '' && $f->validate($v)) ? $v : $f->default();
    }

    /**
     * The guest cookie, decoded, or an empty array.
     *
     * @return array
     */
    private static function cookie(): array {
        $raw = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($raw) || $raw === '' || strlen($raw) > self::COOKIE_MAX) {
            return [];
        }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }

    /**
     * Effective value of a feature for the current user.
     *
     * @param string $id
     * @return string
     */
    public static function get(string $id): string {
        $f = registry::get($id);
        if ($f === null) {
            throw new invalid_parameter_exception("Unknown feature $id");
        }
        if (self::is_locked($id)) {
            return self::site_default($id);
        }
        $v = self::uses_cookie() ? (self::cookie()[$id] ?? null) : get_user_preferences(self::PREFIX . $id);
        return (is_string($v) && $f->validate($v)) ? $v : self::site_default($id);
    }

    /**
     * Effective values of every enabled feature.
     *
     * @return array<string, string>
     */
    public static function all(): array {
        $out = [];
        foreach (self::enabled_ids() as $id) {
            $out[$id] = self::get($id);
        }
        return $out;
    }

    /**
     * Save a value for the logged-in user.
     *
     * @param string $id
     * @param string $value
     * @return void
     * @throws moodle_exception when locked, invalid parameter when the value is not allowed
     */
    public static function set(string $id, string $value): void {
        $f = registry::get($id);
        if ($f === null || !$f->validate($value)) {
            throw new invalid_parameter_exception("Invalid value for $id");
        }
        if (self::is_locked($id)) {
            throw new moodle_exception('featurelocked', 'local_accessibility');
        }
        if (self::uses_cookie()) {
            throw new moodle_exception('guestsusecookie', 'local_accessibility');
        }
        if ($value === $f->default()) {
            unset_user_preference(self::PREFIX . $id);
        } else {
            set_user_preference(self::PREFIX . $id, $value);
        }
        set_user_preference(self::PREFIX . 'initialised', 1);
    }

    /**
     * The current user's custom scheme, if valid.
     *
     * @return scheme|null
     */
    public static function custom_scheme(): ?scheme {
        if (self::uses_cookie()) {
            $c = self::cookie()['custom'] ?? null;
            return is_array($c) ? scheme::from_json((string) json_encode(['v' => 1] + $c)) : null;
        }
        $json = get_user_preferences(self::PREFIX . 'colourcustom');
        return is_string($json) ? scheme::from_json($json) : null;
    }

    /**
     * Save the custom scheme and select it.
     *
     * @param scheme $s
     * @return void
     */
    public static function set_custom_scheme(scheme $s): void {
        if (self::uses_cookie()) {
            throw new moodle_exception('guestsusecookie', 'local_accessibility');
        }
        set_user_preference(self::PREFIX . 'colourcustom', $s->to_json());
        self::set('colour', 'custom');
    }

    /**
     * Clear all of the user's settings.
     *
     * @return void
     */
    public static function reset(): void {
        if (self::uses_cookie()) {
            return;
        }
        foreach (array_keys(registry::all()) as $id) {
            unset_user_preference(self::PREFIX . $id);
        }
        unset_user_preference(self::PREFIX . 'colourcustom');
    }

    /**
     * Attributes for the html tag (spec §6.5, §7.1).
     *
     * @return array<string, string>
     */
    public static function html_attributes(): array {
        $attrs = [];
        foreach (self::all() as $id => $value) {
            $attrs += registry::get($id)->html_attributes($value);
        }
        $colour = $attrs['data-a11y-colour'] ?? null;
        if ($colour !== null) {
            $s = match (true) {
                $colour === 'custom' => self::custom_scheme(),
                default => scheme::presets()[$colour]
                    ?? (\local_accessibility\feature\colour::site_presets()[$colour] ?? null),
            };
            if ($colour === 'dark' && colour_mode::core_dark_available()) {
                unset($attrs['data-a11y-colour']);      // Core renders dark (Task 12).
            } else if ($s === null) {
                unset($attrs['data-a11y-colour']);
            } else {
                $attrs['data-bs-theme'] = $s->mode;
                $attrs['style'] = $s->css_properties();
                if ($s->exact && ($s->worst_text() < contrast::AAA || $s->worst_link() < contrast::AAA)) {
                    $attrs['data-a11y-lowcontrast'] = '1';
                }
            }
        }
        return $attrs;
    }
}
