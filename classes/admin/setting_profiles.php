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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin-defined profiles as JSON (spec §7.3). Empty means the shipped profiles.
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_profiles extends \admin_setting_configtextarea {
    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            'local_accessibility/profiles',
            new \lang_string('profiles', 'local_accessibility'),
            new \lang_string('profiles_desc', 'local_accessibility'),
            '',
            PARAM_RAW,
            60,
            6
        );
    }

    /**
     * Validate: empty, or a JSON object of {id: {name, values}}.
     *
     * @param string $data
     * @return bool|string true, or an error message
     */
    public function validate($data) {
        if (trim((string) $data) === '') {
            return true;
        }
        $list = json_decode((string) $data, true);
        $bad = get_string('profiles_badjson', 'local_accessibility');
        if (!is_array($list) || !$list || array_is_list($list)) {
            return $bad;
        }
        foreach ($list as $p) {
            if (!is_array($p) || !is_string($p['name'] ?? null) || trim(clean_param($p['name'], PARAM_TEXT)) === '') {
                return $bad;
            }
            if (!is_array($p['values'] ?? null) || ($p['values'] && array_is_list($p['values']))) {
                return $bad;
            }
        }
        return true;
    }
}
