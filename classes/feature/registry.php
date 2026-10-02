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
    /** @var string[] Display order (spec §5 and panel-final.html). */
    private const ORDER = ['size', 'font', 'spacing', 'align', 'colour', 'narrow', 'links', 'images',
        'guide', 'motion', 'read', 'saturation', 'focus'];

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
}
