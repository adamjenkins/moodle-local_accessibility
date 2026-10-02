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

namespace local_accessibility\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_accessibility\colour\contrast;
use local_accessibility\colour\scheme;
use local_accessibility\preferences;

/**
 * Save a custom colour scheme; auto-adjusted unless exact (spec §6.2).
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_custom_scheme extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'bg' => new external_value(PARAM_RAW_TRIMMED, 'Background #rrggbb'),
            'text' => new external_value(PARAM_RAW_TRIMMED, 'Text #rrggbb'),
            'link' => new external_value(PARAM_RAW_TRIMMED, 'Link #rrggbb'),
            'exact' => new external_value(PARAM_BOOL, 'Keep exact colours'),
        ]);
    }

    /**
     * Save and report what was stored.
     *
     * @param string $bg
     * @param string $text
     * @param string $link
     * @param bool $exact
     * @return array
     */
    public static function execute(string $bg, string $text, string $link, bool $exact): array {
        $p = self::validate_parameters(
            self::execute_parameters(),
            ['bg' => $bg, 'text' => $text, 'link' => $link, 'exact' => $exact]
        );
        self::validate_context(\context_system::instance());
        $s = scheme::custom($p['bg'], $p['text'], $p['link'], $p['exact']);
        preferences::set_custom_scheme($s);
        $input = [contrast::normalise($p['bg']), contrast::normalise($p['text']), contrast::normalise($p['link'])];
        return [
            'bg' => $s->ramp['page'], 'text' => $s->text, 'link' => $s->link, 'exact' => $s->exact,
            'textratio' => round($s->worst_text(), 2), 'linkratio' => round($s->worst_link(), 2),
            'adjusted' => !$p['exact'] && ([$s->ramp['page'], $s->text, $s->link] !== $input),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'bg' => new external_value(PARAM_RAW, 'Stored background'),
            'text' => new external_value(PARAM_RAW, 'Stored text'),
            'link' => new external_value(PARAM_RAW, 'Stored link'),
            'exact' => new external_value(PARAM_BOOL, 'Exact'),
            'textratio' => new external_value(PARAM_FLOAT, 'Worst text ratio'),
            'linkratio' => new external_value(PARAM_FLOAT, 'Worst link ratio'),
            'adjusted' => new external_value(PARAM_BOOL, 'Whether auto-adjust changed anything'),
        ]);
    }
}
