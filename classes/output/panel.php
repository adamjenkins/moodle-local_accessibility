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
use local_accessibility\feature\colour;
use local_accessibility\feature\registry;
use local_accessibility\preferences;

/**
 * The launcher and the dialog (spec §4).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panel implements \renderable, \templatable {
    /**
     * Template context.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $PAGE;
        $tiles = [];
        foreach (preferences::enabled_ids() as $id) {
            $f = registry::get($id);
            $value = preferences::get($id);
            $values = $f->values();
            $level = array_search($value, $values, true);
            $tiles[] = [
                'id' => $id,
                'label' => $f->label(),
                'icon' => $f->icon(),
                'value' => $value,
                'valuelabel' => $f->value_label($value),
                'values' => json_encode($values),
                'valuelabels' => json_encode(array_map([$f, 'value_label'], $values)),
                'iscolour' => $id === 'colour',
                'twostate' => count($values) === 2,
                'pressed' => $level > 0 ? 'true' : 'false',
                'dots' => count($values) > 2
                    ? array_map(fn($i) => ['filled' => $i <= $level], range(1, count($values) - 1))
                    : [],
                'locked' => preferences::is_locked($id),
            ];
        }
        $swatches = [['id' => 'default', 'label' => get_string('feature_colour_default', 'local_accessibility'),
            'selected' => preferences::get('colour') === 'default']];
        foreach (scheme::presets() + colour::site_presets() as $id => $s) {
            $swatches[] = ['id' => $id, 'bg' => $s->ramp['page'], 'text' => $s->text,
                'label' => str_starts_with($id, 'site_') ? get_string('sitepreset', 'local_accessibility')
                    : get_string('feature_colour_' . $id, 'local_accessibility'),
                'selected' => in_array('colour', preferences::enabled_ids(), true) && preferences::get('colour') === $id];
        }
        $custom = preferences::custom_scheme();
        $mode = get_config('local_accessibility', 'launcher') ?: 'both';
        return [
            'tiles' => $tiles,
            'swatches' => $swatches,
            'custom' => $custom ? ['bg' => $custom->ramp['page'], 'text' => $custom->text,
                'link' => $custom->link, 'exact' => $custom->exact] : null,
            // Guests and secure-layout pages have no user menu, so they always get the floating launcher (R16).
            'floating' => $mode !== 'menu' || preferences::uses_cookie() || $PAGE->pagelayout === 'secure',
            'profiles' => \local_accessibility\profiles::for_template(),
        ];
    }
}
