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

/**
 * Maps values used on 3.0 development sites to the 3.0 values (spec §6, plan D2), at read time and in the upgrade.
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacy {
    /** @var array<string, array<string, string>> Old spacing value => the three spacing features (spec §6). */
    private const SPACING = [
        'wcag' => ['lineheight' => '150', 'letterspacing' => '12', 'wordspacing' => '16'],
        'extra' => ['lineheight' => '180', 'letterspacing' => '16', 'wordspacing' => '24'],
    ];

    /**
     * @var array<string, array<string, string>> Feature id => old value => new value (spec §6, D2). The 3.0
     * development size 175 is no longer renamed: text size takes any whole percentage, so 175 is a value of its own.
     */
    private const RENAMED = [
        'align' => ['on' => 'left'],
        'links' => ['on' => 'outline'],
        'images' => ['on' => 'hide'],
        'focus' => ['cursor' => 'ring'],
    ];

    /** @var string[] Feature ids whose stored values the upgrade reads: the legacy ones and the ones they map to. */
    private const UPGRADED = ['spacing', 'lineheight', 'letterspacing', 'wordspacing', 'align', 'links', 'images',
        'focus', 'cursor'];

    /** @var string The features' enabled/order table. */
    private const TABLE = 'local_accessibility_widgets';

    /**
     * Map feature values (feature id => value) to the current values.
     *
     * The old 'spacing' key never survives: 'wcag' and 'extra' become the three spacing features (a value already
     * given for one of them wins), anything else is dropped. 'focus=cursor' becomes the strong ring plus the large
     * cursor unless a cursor value is given. Unknown keys, non-string values and current values pass through, and
     * mapping twice gives the same result as mapping once.
     *
     * @param array $values feature id => value, e.g. a decoded guest cookie or a profile's values
     * @return array
     */
    public static function map_values(array $values): array {
        $out = [];
        foreach ($values as $id => $value) {
            if ($id === 'spacing') {
                foreach (self::SPACING[is_string($value) ? $value : ''] ?? [] as $fid => $v) {
                    if (!array_key_exists($fid, $values)) {
                        $out[$fid] = $v;
                    }
                }
                continue;
            }
            if (is_string($value) && isset(self::RENAMED[$id][$value])) {
                $out[$id] = self::RENAMED[$id][$value];
                if ($id === 'focus' && !array_key_exists('cursor', $values)) {
                    $out['cursor'] = 'large';
                }
                continue;
            }
            $out[$id] = $value;
        }
        return $out;
    }

    /**
     * Drop the new spacing and cursor features' values that are their default, so a legacy value still maps onto them.
     *
     * Saving the admin settings page stores 'default' and 'off' for features the site never had, and a fresh install
     * stores '' (empty means the site default) for the numeric defaults; neither is a choice that should outweigh the
     * admin's or the user's old spacing or focus value.
     *
     * @param array $values feature id => stored value
     * @return array
     */
    private static function without_new_defaults(array $values): array {
        foreach (['lineheight', 'letterspacing', 'wordspacing', 'cursor'] as $id) {
            $default = \local_accessibility\feature\registry::get($id)->default();
            if (isset($values[$id]) && ($values[$id] === '' || $values[$id] === $default)) {
                unset($values[$id]);
            }
        }
        return $values;
    }

    /**
     * Upgrade every user's stored preferences with map_values() (upgrade step 2026100600).
     *
     * Only users with a legacy value are written to, one user object per user so core loads their preferences once.
     * The old spacing preference is removed. Running it again finds nothing to change.
     *
     * @return void
     */
    public static function upgrade_user_preferences(): void {
        global $DB;
        $prefix = 'local_accessibility_';
        [$in, $params] = $DB->get_in_or_equal(array_map(fn($id) => $prefix . $id, self::UPGRADED));
        $rs = $DB->get_recordset_select('user_preferences', "name $in", $params, 'userid, id', 'id, userid, name, value');
        $userid = null;
        $values = [];
        $flush = function () use (&$userid, &$values, $prefix): void {
            if ($userid === null) {
                return;
            }
            $user = null;
            foreach (self::map_values(self::without_new_defaults($values)) as $id => $value) {
                if (($values[$id] ?? null) !== $value) {
                    $user ??= (object) ['id' => $userid];
                    set_user_preference($prefix . $id, $value, $user);
                }
            }
            if (array_key_exists('spacing', $values)) {
                unset_user_preference($prefix . 'spacing', $user ?? (object) ['id' => $userid]);
            }
        };
        foreach ($rs as $r) {
            if ((int) $r->userid !== $userid) {
                $flush();
                $userid = (int) $r->userid;
                $values = [];
            }
            $values[substr($r->name, strlen($prefix))] = (string) $r->value;
        }
        $flush();
        $rs->close();
    }

    /**
     * Upgrade the admin defaults and locks with map_values() (upgrade step 2026100600).
     *
     * default_spacing becomes the three spacing defaults ('normal' becomes none) and is removed; lock_spacing keeps
     * its name, now covering the three. A focus default of 'cursor' becomes the strong ring plus the large cursor, and
     * a lock on it locks the cursor too. Running it again finds nothing to change.
     *
     * @return void
     */
    public static function upgrade_config(): void {
        $config = (array) get_config('local_accessibility');
        $values = [];
        foreach (self::UPGRADED as $id) {
            if (isset($config['default_' . $id])) {
                $values[$id] = (string) $config['default_' . $id];
            }
        }
        foreach (self::map_values(self::without_new_defaults($values)) as $id => $value) {
            if (($values[$id] ?? null) !== $value) {
                set_config('default_' . $id, $value, 'local_accessibility');
            }
        }
        if (array_key_exists('spacing', $values)) {
            unset_config('default_spacing', 'local_accessibility');
        }
        if (($values['focus'] ?? null) === 'cursor' && !empty($config['lock_focus']) && empty($config['lock_cursor'])) {
            set_config('lock_cursor', 1, 'local_accessibility');
        }
    }

    /**
     * Upgrade the features' enabled/order rows (upgrade step 2026100600, spec §6).
     *
     * The spacing row becomes the lineheight row, keeping the admin's enabled flag and position (if the runtime sync
     * already added a lineheight row, that row takes spacing's flag and place). While migrating spacing, and whenever
     * they have no row, letterspacing and wordspacing are placed right after lineheight with its enabled flag. A
     * missing cursor row is placed right after focus, enabled. Rows are then renumbered in order, any feature still
     * missing is appended, and the enabled-ids cache is purged. Running it again changes nothing.
     *
     * @return void
     */
    public static function upgrade_feature_rows(): void {
        global $DB;
        $rows = [];
        foreach ($DB->get_records(self::TABLE, null, 'sequence, id', 'id, name, enabled, sequence') as $r) {
            $rows[$r->name] = $r;
        }
        $order = array_keys($rows);
        $migrating = isset($rows['spacing']);
        if ($migrating) {
            $spacing = $rows['spacing'];
            if (isset($rows['lineheight'])) {
                $lh = $rows['lineheight'];
                $DB->set_field(self::TABLE, 'enabled', $spacing->enabled, ['id' => $lh->id]);
                $DB->delete_records(self::TABLE, ['id' => $spacing->id]);
                $lh->enabled = $spacing->enabled;
                $order = array_values(array_diff($order, ['lineheight']));
            } else {
                $DB->set_field(self::TABLE, 'name', 'lineheight', ['id' => $spacing->id]);
                $lh = $spacing;
                $lh->name = 'lineheight';
            }
            $rows['lineheight'] = $lh;
            unset($rows['spacing']);
            $order[array_search('spacing', $order, true)] = 'lineheight';
        }
        // Place a feature right after another one, inserting its row if it has none.
        $place = function (string $id, string $after, int $enabled) use (&$rows, &$order, $DB): void {
            if (isset($rows[$id])) {
                if ((int) $rows[$id]->enabled !== $enabled) {
                    $DB->set_field(self::TABLE, 'enabled', $enabled, ['id' => $rows[$id]->id]);
                    $rows[$id]->enabled = $enabled;
                }
                $order = array_values(array_diff($order, [$id]));
            } else {
                $row = (object) ['name' => $id, 'enabled' => $enabled, 'sequence' => 0];
                $row->id = $DB->insert_record(self::TABLE, $row);
                $rows[$id] = $row;
            }
            array_splice($order, array_search($after, $order, true) + 1, 0, [$id]);
        };
        if (isset($rows['lineheight'])) {
            $after = 'lineheight';
            foreach (['letterspacing', 'wordspacing'] as $id) {
                if ($migrating || !isset($rows[$id])) {
                    $place($id, $after, (int) $rows['lineheight']->enabled);
                }
                $after = $id;
            }
        }
        if (!isset($rows['cursor']) && isset($rows['focus'])) {
            $place('cursor', 'focus', 1);
        }
        foreach ($order as $i => $name) {
            if ((int) $rows[$name]->sequence !== $i + 1) {
                $DB->set_field(self::TABLE, 'sequence', $i + 1, ['id' => $rows[$name]->id]);
            }
        }
        \local_accessibility\preferences::sync_features_table();
        \cache::make('local_accessibility', 'enabled')->purge();
    }
}
