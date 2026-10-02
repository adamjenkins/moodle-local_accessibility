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
            'size 1.6 nearest' => [['fontsize' => '1.6'], [$p . 'size' => '150']],
            'size 0.75 dropped' => [['fontsize' => '0.75'], []],
            'size 2' => [['fontsize' => '2'], [$p . 'size' => '200']],
            'lineheight wcag' => [['lineheight' => '1.5'], [$p . 'spacing' => 'wcag']],
            'lineheight extra' => [['lineheight' => '2.0'], [$p . 'spacing' => 'extra']],
            'lineheight low dropped' => [['lineheight' => '0.8'], []],
            'letterspacing extra' => [['letterspacing' => '0.3'], [$p . 'spacing' => 'extra']],
            'kerning' => [['fontkerning' => '1'], [$p . 'spacing' => 'extra']],
            'max of spacing' => [['lineheight' => '1.5', 'fontkerning' => '1'], [$p . 'spacing' => 'extra']],
            'font serif' => [['fontface' => 'serif'], []],
            'font sans' => [['fontface' => 'sansserif'], [$p . 'font' => 'readable']],
            'font dyslexic' => [['fontface' => 'dyslexic'], [$p . 'font' => 'dyslexic']],
            'align left' => [['textalignment' => 'left'], [$p . 'align' => 'on']],
            'align justify dropped' => [['textalignment' => 'justify'], []],
            'width 25' => [['paragraphwidth' => '25'], [$p . 'narrow' => '60']],
            'width 75' => [['paragraphwidth' => '75'], [$p . 'narrow' => '70']],
            'width 100 dropped' => [['paragraphwidth' => '100'], []],
            'links' => [['linkhighlight' => '1'], [$p . 'links' => 'on']],
            'images' => [['imagevisibility' => '1'], [$p . 'images' => 'on']],
            'unknown widget ignored' => [['nosuch' => 'x'], []],
        ];
    }

    /**
     * Mapping.
     *
     * @dataProvider cases
     * @param array $old
     * @param array $expected
     */
    public function test_map(array $old, array $expected): void {
        $this->assertSame($expected, migration::map_user($old));
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
            ['local_accessibility_links' => 'on'],
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
