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

namespace local_accessibility\local;

use local_accessibility\colour\adjust;
use local_accessibility\colour\contrast;
use local_accessibility\colour\scheme;

/**
 * Moves 2.x widget settings into 3.0 user preferences and removes the old widget subplugins (spec §9).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class migration {
    /** @var string Preference prefix. */
    private const P = 'local_accessibility_';
    /** @var string[] The 2.x widget subplugins (accessibility_*) that 3.0 replaces. */
    public const OLD_WIDGETS = ['backgroundcolour', 'fontface', 'fontkerning', 'fontsize', 'imagevisibility',
        'letterspacing', 'lineheight', 'linkhighlight', 'paragraphwidth', 'textalignment', 'textcolour'];
    /** @var string[] Boost's link colours on light and on dark pages: v2 had no link colour of its own. */
    private const LINKS = ['#0f6cbf', '#8cc8ff'];

    /**
     * Map one user's old settings straight to the 3.0 values (spec §6, plan D5).
     *
     * @param array $old widget => configvalue
     * @return array preference name => value
     */
    public static function map_user(array $old): array {
        $out = [];
        $size = self::number($old['fontsize'] ?? null, 100);
        if ($size !== null && $size > 0) {
            // The nearest 10-step in 80..300, half up; 125 is a step of its own. 100 is the default.
            $step = abs($size - 125) <= 0.5 ? 125 : (int) round($size / 10) * 10;
            $step = max(80, min(300, $step));
            if ($step !== 100) {
                $out[self::P . 'size'] = (string) $step;
            }
        }
        // Line height and letter spacing at or below normal were never increases: omitted.
        $lh = self::number($old['lineheight'] ?? null, 100);
        if ($lh !== null && $lh > 100) {
            $out[self::P . 'lineheight'] = self::nearest($lh, \local_accessibility\feature\registry::get('lineheight'));
        }
        $ls = self::number($old['letterspacing'] ?? null, 100);
        if ($ls !== null && $ls > 0) {
            $out[self::P . 'letterspacing'] = self::nearest($ls, \local_accessibility\feature\registry::get('letterspacing'));
        }
        if (!empty($old['fontkerning']) && !isset($out[self::P . 'letterspacing'])) {
            $out[self::P . 'letterspacing'] = '10';
        }
        $font = ['sansserif' => 'readable', 'dyslexic' => 'dyslexic'][(string) ($old['fontface'] ?? '')] ?? null;
        if ($font) {
            $out[self::P . 'font'] = $font;
        }
        if ((string) ($old['textalignment'] ?? '') === 'left') {
            $out[self::P . 'align'] = 'left';
        }
        $width = (string) ($old['paragraphwidth'] ?? '');
        if (in_array($width, ['25', '50', '75'], true)) {
            $out[self::P . 'narrow'] = ['25' => '50', '50' => '60', '75' => '70'][$width];
        }
        if (!empty($old['linkhighlight'])) {
            $out[self::P . 'links'] = 'outline';
        }
        if (!empty($old['imagevisibility'])) {
            $out[self::P . 'images'] = 'hide';
        }
        $bg = contrast::normalise((string) ($old['backgroundcolour'] ?? ''));
        $text = contrast::normalise((string) ($old['textcolour'] ?? ''));
        if ($bg || $text) {
            $bg ??= '#ffffff';
            $text ??= '#1d2125';
            // The user never chose a link colour, so it must not be what sinks their pair under the floor:
            // take whichever Boost link colour contrasts better with the page's surfaces.
            $ramp = adjust::ramp($bg);
            $link = contrast::worst(self::LINKS[0], $ramp) >= contrast::worst(self::LINKS[1], $ramp)
                ? self::LINKS[0] : self::LINKS[1];
            try {
                $s = scheme::custom($bg, $text, $link, true);
                $out[self::P . 'colour'] = 'custom';
                $out[self::P . 'colourcustom'] = $s->to_json();
            } catch (\invalid_parameter_exception $e) {
                // Below the floor: drop rather than make the site unreadable (spec §9).
                unset($e);
            }
        }
        return $out;
    }

    /**
     * A stored 2.x number scaled to the 3.0 integer units, or null when it is not a number.
     *
     * Rounded to 4 places so float noise cannot flip a half-way value (1.15 * 100 is 114.999...).
     *
     * @param mixed $value
     * @param int $scale e.g. 100 for a factor stored as 1.5 and meant as 150
     * @return float|null
     */
    private static function number($value, int $scale): ?float {
        if (!is_numeric($value)) {
            return null;
        }
        return round((float) $value * $scale, 4);
    }

    /**
     * The feature's numeric value nearest to a wanted one; a tie goes to the larger value.
     *
     * @param float $want
     * @param \local_accessibility\feature\base $feature a feature whose non-default values are digits
     * @return string
     */
    private static function nearest(float $want, \local_accessibility\feature\base $feature): string {
        $best = null;
        foreach ($feature->values() as $v) {
            if (!ctype_digit($v)) {
                continue;
            }
            if ($best === null || abs((int) $v - $want) <= abs((int) $best - $want)) {
                $best = $v;
            }
        }
        return (string) $best;
    }

    /**
     * Migrate every user's rows from local_accessibility_configs (called once from upgrade.php).
     *
     * Deleted users and the guest account are skipped. Re-running it sets the same values again, so an
     * interrupted upgrade can safely restart the step. Without the old table it does nothing.
     *
     * @return void
     */
    public static function run(): void {
        global $DB, $CFG;
        if (!$DB->get_manager()->table_exists('local_accessibility_configs')) {
            return;
        }
        $rs = $DB->get_recordset_sql(
            'SELECT c.id, c.userid, c.widget, c.configvalue
               FROM {local_accessibility_configs} c
               JOIN {user} u ON u.id = c.userid
              WHERE u.deleted = 0 AND u.id <> :guest
           ORDER BY c.userid, c.id',
            ['guest' => (int) $CFG->siteguest]
        );
        $user = null;
        $old = [];
        $flush = function () use (&$user, &$old): void {
            if ($user === null || !$old) {
                return;
            }
            // One user object per user, so core loads their preferences once rather than on every call.
            foreach (self::map_user($old) as $name => $value) {
                set_user_preference($name, $value, $user);
            }
            set_user_preference(self::P . 'initialised', 1, $user);
        };
        foreach ($rs as $r) {
            if ($user === null || (int) $r->userid !== (int) $user->id) {
                $flush();
                $user = (object) ['id' => (int) $r->userid];
                $old = [];
            }
            $old[$r->widget] = $r->configvalue;
        }
        $flush();
        $rs->close();
    }

    /**
     * Rename the 2.x widget rows in local_accessibility_widgets to the feature ids that replace them.
     *
     * A renamed row keeps the admin's enabled flag and order. Where two old widgets map to one feature, the
     * first in this list wins and the other row is deleted. Features with no old row are appended, enabled.
     *
     * @return void
     */
    public static function rename_widget_rows(): void {
        global $DB;
        $rename = ['fontsize' => 'size', 'fontface' => 'font', 'lineheight' => 'lineheight',
            'letterspacing' => 'letterspacing', 'fontkerning' => 'letterspacing', 'textalignment' => 'align',
            'textcolour' => 'colour', 'backgroundcolour' => 'colour', 'paragraphwidth' => 'narrow', 'linkhighlight' => 'links',
            'imagevisibility' => 'images'];
        foreach ($rename as $old => $new) {
            if (!$DB->record_exists('local_accessibility_widgets', ['name' => $new])) {
                $DB->set_field('local_accessibility_widgets', 'name', $new, ['name' => $old]);
            }
        }
        // Old widget names that are also feature ids (lineheight, letterspacing) are feature rows now: keep them.
        $gone = array_diff(self::OLD_WIDGETS, array_keys(\local_accessibility\feature\registry::all()));
        $DB->delete_records_list('local_accessibility_widgets', 'name', $gone);
        \local_accessibility\preferences::sync_features_table();
        \cache::make('local_accessibility', 'enabled')->purge();
    }

    /**
     * Uninstall the 2.x widget subplugins whose code has been removed (spike decision A).
     *
     * Only a subplugin core records as installed and whose files are gone (status "missing") is uninstalled. One
     * whose folder is still on disk would be installed again straight away, so it is left for the admin to remove
     * from the plugins overview. A failure is reported as a debugging message and never aborts the upgrade.
     *
     * @return string[] the components that were uninstalled
     */
    public static function uninstall_old_subplugins(): array {
        global $CFG;
        // The plugin manager calls the legacy uninstall_plugin(), which only adminlib.php defines.
        require_once($CFG->libdir . '/adminlib.php');
        $pm = \core_plugin_manager::instance();
        $done = [];
        foreach (self::OLD_WIDGETS as $w) {
            $component = 'accessibility_' . $w;
            $buffers = ob_get_level();
            try {
                $info = $pm->get_plugin_info($component);
                if ($info === null || $info->get_status() !== \core_plugin_manager::PLUGIN_STATUS_MISSING) {
                    continue;
                }
                if ($pm->uninstall_plugin($component, new \null_progress_trace())) {
                    $done[] = $component;
                }
            } catch (\Throwable $e) {
                // The plugin manager buffers the legacy uninstall's output; close a buffer an exception left open.
                while (ob_get_level() > $buffers) {
                    ob_end_clean();
                }
                debugging("local_accessibility: could not uninstall {$component}: " . $e->getMessage(), DEBUG_NORMAL);
            }
        }
        \core_plugin_manager::reset_caches();
        return $done;
    }
}
