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

namespace local_accessibility\admin;

use local_accessibility\colour\adjust;
use local_accessibility\colour\contrast;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Site colour presets, each checked at 7:1 on every surface shade (spec §6.1).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_sitepresets extends \admin_setting_configtextarea {
    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            'local_accessibility/sitepresets',
            new \lang_string('sitepresets', 'local_accessibility'),
            new \lang_string('sitepresets_desc', 'local_accessibility'),
            '[]',
            PARAM_RAW,
            60,
            6
        );
    }

    /**
     * Validate: a JSON list of {name, bg, text, link}, text and link at 7:1 on every shade of the bg ramp.
     *
     * @param string $data
     * @return bool|string true, or an error naming the failing preset and shade
     */
    public function validate($data) {
        $list = json_decode((string) $data, true);
        if (!is_array($list) || !array_is_list($list)) {
            return get_string('sitepresets_badjson', 'local_accessibility');
        }
        foreach ($list as $p) {
            if (!is_array($p)) {
                return get_string('sitepresets_badjson', 'local_accessibility');
            }
            $fields = [];
            foreach (['name', 'bg', 'text', 'link'] as $key) {
                $fields[$key] = is_string($p[$key] ?? null) ? $p[$key] : '';
            }
            $name = trim(clean_param($fields['name'], PARAM_TEXT));
            $bg = contrast::normalise($fields['bg']);
            $text = contrast::normalise($fields['text']);
            $link = contrast::normalise($fields['link']);
            if ($name === '' || !$bg || !$text || !$link) {
                return get_string('sitepresets_badjson', 'local_accessibility');
            }
            foreach (adjust::ramp($bg) as $shade => $hex) {
                foreach (['text' => $text, 'link' => $link] as $what => $fg) {
                    $r = contrast::ratio($fg, $hex);
                    if ($r < contrast::AAA) {
                        return get_string('sitepresets_fails', 'local_accessibility', (object) [
                            'name' => $name,
                            'what' => $what,
                            'shade' => $shade,
                            'ratio' => round($r, 2),
                        ]);
                    }
                }
            }
        }
        return true;
    }
}
