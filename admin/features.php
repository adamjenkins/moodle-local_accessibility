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

/**
 * Admin page to enable, disable and order the built-in features.
 *
 * Derived from Ponlawat Weerapanpisit's manageenabledwidgets.php and the enable/disable/move functions in lib.php,
 * keyed by feature id instead of widget subplugin.
 *
 * @package     local_accessibility
 * @copyright   2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @copyright   2026 Adam Jenkins <adam@wisecat.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_accessibility\feature\registry;
use local_accessibility\preferences;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_accessibility_features');

$url = new moodle_url('/local/accessibility/admin/features.php');
$features = registry::all();

// Every feature gets a row before anything is read or changed.
preferences::sync_features_table();

// Feature rows (registry ids only) in admin order, keyed by feature id.
$loadrows = function (array $features): array {
    global $DB;
    [$insql, $params] = $DB->get_in_or_equal(array_keys($features));
    $rows = [];
    foreach ($DB->get_records_select('local_accessibility_widgets', "name $insql", $params, 'sequence ASC, id ASC') as $r) {
        $rows[$r->name] = $r;
    }
    return $rows;
};

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    $id = required_param('feature', PARAM_ALPHANUMEXT);
    if (!isset($features[$id]) || !in_array($action, ['enable', 'disable', 'up', 'down'], true)) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    $rows = $loadrows($features);
    if ($action === 'enable' || $action === 'disable') {
        $DB->set_field('local_accessibility_widgets', 'enabled', $action === 'enable' ? 1 : 0, ['id' => $rows[$id]->id]);
    } else {
        $order = array_keys($rows);
        $pos = array_search($id, $order, true);
        $swap = $action === 'up' ? $pos - 1 : $pos + 1;
        if ($swap >= 0 && $swap < count($order)) {
            [$order[$pos], $order[$swap]] = [$order[$swap], $order[$pos]];
            // Renumber 1..n, so rows sharing a sequence (or left at -1 by 2.x) still move.
            foreach ($order as $i => $name) {
                if ((int) $rows[$name]->sequence !== $i + 1) {
                    $DB->set_field('local_accessibility_widgets', 'sequence', $i + 1, ['id' => $rows[$name]->id]);
                }
            }
        }
    }
    \cache::make('local_accessibility', 'enabled')->purge();
    redirect($url);
}

$rows = array_values($loadrows($features));
$context = ['rows' => []];
foreach ($rows as $i => $r) {
    $actionurl = fn(string $a): string => (new moodle_url($url, ['action' => $a, 'feature' => $r->name,
        'sesskey' => sesskey()]))->out(false);
    $enabled = (bool) $r->enabled;
    $context['rows'][] = [
        'id' => $r->name,
        'label' => $features[$r->name]->label(),
        'enabled' => $enabled,
        'canup' => $i > 0,
        'candown' => $i < count($rows) - 1,
        'actionurls' => [
            'toggle' => $actionurl($enabled ? 'disable' : 'enable'),
            'up' => $actionurl('up'),
            'down' => $actionurl('down'),
        ],
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managefeatures', 'local_accessibility'));
echo $OUTPUT->render_from_template('local_accessibility/admin/features', $context);
echo $OUTPUT->footer();
