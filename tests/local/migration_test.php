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
 * Tests for the 2.x to 3.0 settings mapping (spec §9).
 *
 * @package    local_accessibility
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibility\local\migration
 */
final class migration_test extends \advanced_testcase {
    /**
     * Each row of the spec §9 table, with the values the 2.x widgets actually stored.
     *
     * @return array
     */
    public static function cases(): array {
        $p = 'local_accessibility_';
        return [
            'size 1.5' => [['fontsize' => '1.5'], [$p . 'size' => '150']],
            'size 1.6 nearest 10-step' => [['fontsize' => '1.6'], [$p . 'size' => '160']],
            'size 1.75 rounds half up' => [['fontsize' => '1.75'], [$p . 'size' => '180']],
            'size 1.25 kept' => [['fontsize' => '1.25'], [$p . 'size' => '125']],
            'size 1.15 float noise' => [['fontsize' => '1.15'], [$p . 'size' => '120']],
            'size 0.75 smallest' => [['fontsize' => '0.75'], [$p . 'size' => '80']],
            'size 1.0 default omitted' => [['fontsize' => '1.0'], []],
            'size 2' => [['fontsize' => '2'], [$p . 'size' => '200']],
            'size 3.5 capped' => [['fontsize' => '3.5'], [$p . 'size' => '300']],
            'size 0 dropped' => [['fontsize' => '0'], []],
            'size garbage dropped' => [['fontsize' => 'big'], []],
            'lineheight 1.5' => [['lineheight' => '1.5'], [$p . 'lineheight' => '150']],
            'lineheight 2.0' => [['lineheight' => '2.0'], [$p . 'lineheight' => '200']],
            'lineheight 1.7 nearest' => [['lineheight' => '1.7'], [$p . 'lineheight' => '180']],
            'lineheight 1.1 nearest' => [['lineheight' => '1.1'], [$p . 'lineheight' => '120']],
            'lineheight 3 capped' => [['lineheight' => '3'], [$p . 'lineheight' => '250']],
            'lineheight 1.0 omitted' => [['lineheight' => '1.0'], []],
            'lineheight low dropped' => [['lineheight' => '0.8'], []],
            'letterspacing 0.3' => [['letterspacing' => '0.3'], [$p . 'letterspacing' => '30']],
            'letterspacing 0.12' => [['letterspacing' => '0.12'], [$p . 'letterspacing' => '12']],
            'letterspacing 0.14 nearest' => [['letterspacing' => '0.14'], [$p . 'letterspacing' => '16']],
            'letterspacing 0 omitted' => [['letterspacing' => '0'], []],
            'letterspacing negative omitted' => [['letterspacing' => '-0.1'], []],
            'kerning' => [['fontkerning' => '1'], [$p . 'letterspacing' => '10']],
            'kerning keeps mapped letterspacing' => [['fontkerning' => '1', 'letterspacing' => '0.2'],
                [$p . 'letterspacing' => '20']],
            'kerning off' => [['fontkerning' => '0'], []],
            'lineheight and letterspacing' => [['lineheight' => '1.5', 'letterspacing' => '0.12'],
                [$p . 'lineheight' => '150', $p . 'letterspacing' => '12']],
            'font serif' => [['fontface' => 'serif'], []],
            'font sans' => [['fontface' => 'sansserif'], [$p . 'font' => 'readable']],
            'font dyslexic' => [['fontface' => 'dyslexic'], [$p . 'font' => 'dyslexic']],
            'align left' => [['textalignment' => 'left'], [$p . 'align' => 'left']],
            'align justify dropped' => [['textalignment' => 'justify'], []],
            'width 25' => [['paragraphwidth' => '25'], [$p . 'narrow' => '50']],
            'width 50' => [['paragraphwidth' => '50'], [$p . 'narrow' => '60']],
            'width 75' => [['paragraphwidth' => '75'], [$p . 'narrow' => '70']],
            'width 100 dropped' => [['paragraphwidth' => '100'], []],
            'links' => [['linkhighlight' => '1'], [$p . 'links' => 'outline']],
            'images' => [['imagevisibility' => '1'], [$p . 'images' => 'hide']],
            'unknown widget ignored' => [['nosuch' => 'x'], []],
        ];
    }

    /**
     * Mapping: every value produced is valid for its feature.
     *
     * @dataProvider cases
     * @param array $old
     * @param array $expected
     */
    public function test_map(array $old, array $expected): void {
        $this->assertSame($expected, migration::map_user($old));
        foreach ($expected as $name => $value) {
            $f = \local_accessibility\feature\registry::get(substr($name, strlen('local_accessibility_')));
            $this->assertTrue($f->validate($value), "$name $value");
        }
    }

    /**
     * Renaming the 2.x widget rows keeps the rows of the features that took an old widget's name (lineheight,
     * letterspacing) and deletes the other old names.
     */
    public function test_rename_keeps_new_feature_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('local_accessibility_widgets');
        $seq = 0;
        foreach (['lineheight', 'letterspacing', 'fontkerning', 'fontsize'] as $name) {
            $DB->insert_record('local_accessibility_widgets', (object) ['name' => $name, 'enabled' => 1,
                'sequence' => ++$seq]);
        }

        migration::rename_widget_rows();

        $rows = $DB->get_records_menu('local_accessibility_widgets', null, 'sequence', 'name, sequence');
        $this->assertSame(['lineheight', 'letterspacing', 'size'], array_slice(array_keys($rows), 0, 3));
        $this->assertArrayNotHasKey('fontkerning', $rows);
        $this->assertArrayNotHasKey('fontsize', $rows);
        $this->assertEqualsCanonicalizing(array_keys(\local_accessibility\feature\registry::all()), array_keys($rows));
    }

    /**
     * Colours become an exact custom scheme.
     */
    public function test_map_colours_exact(): void {
        $r = migration::map_user(['textcolour' => '#ffffff', 'backgroundcolour' => '#3a6ea5']);
        $this->assertSame('custom', $r['local_accessibility_colour']);
        $s = \local_accessibility\colour\scheme::from_json($r['local_accessibility_colourcustom']);
        $this->assertTrue($s->exact);
        $this->assertSame('#3a6ea5', $s->ramp['page']);
        $this->assertSame('#ffffff', $s->text);
    }

    /**
     * Review focus 3: a pair under the floor is dropped.
     */
    public function test_map_colours_below_floor_dropped(): void {
        $this->assertSame([], migration::map_user(['textcolour' => '#ffffff', 'backgroundcolour' => '#fefefe']));
        // Text alone on Boost's white page: white on white is dropped too.
        $this->assertSame([], migration::map_user(['textcolour' => '#ffffff']));
        // Other settings survive a dropped colour pair.
        $this->assertSame(
            ['local_accessibility_links' => 'outline'],
            migration::map_user(['linkhighlight' => '1', 'textcolour' => '#ffffff', 'backgroundcolour' => '#fefefe'])
        );
    }

    /**
     * Only one colour set: the other comes from Boost's defaults.
     */
    public function test_map_one_colour(): void {
        $r = migration::map_user(['backgroundcolour' => '#fbf3df']);
        $s = \local_accessibility\colour\scheme::from_json($r['local_accessibility_colourcustom']);
        $this->assertSame('#1d2125', $s->text);
    }

    /**
     * A mid-tone page that passes for text keeps the pair: the link default is the better of Boost's two.
     */
    public function test_map_colours_link_never_drops_a_valid_pair(): void {
        foreach (['#777777', '#808080', '#5a8f5a', '#3a6ea5', '#b0b0b0'] as $bg) {
            $r = migration::map_user(['textcolour' => '#000000', 'backgroundcolour' => $bg]);
            $this->assertSame('custom', $r['local_accessibility_colour'] ?? null, $bg);
        }
    }
}
