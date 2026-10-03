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

namespace local_accessibility\feature;

/**
 * The list of built-in features, in display order.
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /** @var string[] Display order (spec §3). */
    private const ORDER = ['size', 'font', 'lineheight', 'letterspacing', 'wordspacing', 'align', 'colour', 'narrow',
        'links', 'images', 'guide', 'motion', 'read', 'saturation', 'focus', 'cursor'];

    /**
     * All features.
     *
     * @return array<string, base>
     */
    public static function all(): array {
        static $all = null;
        if ($all === null) {
            $all = [];
            foreach (self::ORDER as $id) {
                $class = __NAMESPACE__ . '\\' . $id;
                $all[$id] = new $class();
            }
        }
        return $all;
    }

    /**
     * One feature by id.
     *
     * @param string $id
     * @return base|null
     */
    public static function get(string $id): ?base {
        return self::all()[$id] ?? null;
    }

    /**
     * Group feature ids into panel tiles. A tile takes the position of its first member in the given order and
     * lists only the given members (plan D6).
     *
     * @param string[] $ids feature ids, e.g. the enabled ids in admin order; unknown ids are skipped
     * @return array<string, string[]> tile id => member feature ids
     */
    public static function tiles(array $ids): array {
        $tiles = [];
        foreach ($ids as $id) {
            $f = self::get((string) $id);
            if ($f !== null) {
                $tiles[$f->tile()][] = $f->id();
            }
        }
        return $tiles;
    }
}
