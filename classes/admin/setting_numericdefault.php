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
 * The site default of a numeric feature (size, line height, letter and word spacing, line width): empty for the
 * feature's own default, or a whole number the feature accepts, validated by the feature itself.
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_numericdefault extends \admin_setting_configtext {
    /** @var \local_accessibility\feature\numeric The feature. */
    private $feature;

    /**
     * Constructor.
     *
     * @param \local_accessibility\feature\numeric $feature
     */
    public function __construct(\local_accessibility\feature\numeric $feature) {
        $this->feature = $feature;
        $id = $feature->id();
        parent::__construct(
            'local_accessibility/default_' . $id,
            $feature->label(),
            new \lang_string('numericdefault_desc', 'local_accessibility', (object) [
                'unit' => get_string('numericunit_' . $id, 'local_accessibility'),
                'min' => $feature->min(),
                'max' => $feature->max(),
            ]),
            '',
            PARAM_RAW_TRIMMED,
            8
        );
    }

    /**
     * The stored value; a stored non-numeric default ('default', 'off') shows as empty.
     *
     * @return string|null
     */
    public function get_setting() {
        $v = parent::get_setting();
        return ($v !== null && $v === $this->feature->default() && !preg_match('/^-?\d+$/D', (string) $v)) ? '' : $v;
    }

    /**
     * Validate: empty, or a value the feature accepts under the site's numeric limits.
     *
     * @param string $data
     * @return bool|string true, or an error message
     */
    public function validate($data) {
        $data = trim((string) $data);
        if ($data === '' || $this->feature->validate($data)) {
            return true;
        }
        $a = (object) ['min' => $this->feature->min(), 'max' => $this->feature->max()];
        return get_string('numericdefault_invalid', 'local_accessibility', $a);
    }
}
