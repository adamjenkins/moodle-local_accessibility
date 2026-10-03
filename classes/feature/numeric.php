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
 * A feature whose value is any integer in a range, changed with − and + steppers rather than chosen from a list.
 *
 * Its stored value is its default (off) value or an integer string in the feature's own encoding (hundredths, percent,
 * characters). The lower bound follows the admin setting local_accessibility/numericlimits: 'unlimited' (the default)
 * allows negative spacing and line heights under 1.0; 'nonnegative' does not. Values outside the bounds are invalid, so
 * a stored one falls back to the site default when read.
 *
 * @package    local_accessibility
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class numeric extends base {
    /** @var string Admin setting value that keeps every numeric feature at or above its non-negative bound. */
    public const NONNEGATIVE = 'nonnegative';
    /** @var string Admin setting value (the default) that allows the widest range. */
    public const UNLIMITED = 'unlimited';

    /** @var string The default (off) value, kept in the encoding saved values already use. */
    protected const DEFAULT = 'default';
    /** @var int Amount one − or + press changes the value by, in the encoding. */
    protected const STEP = 1;
    /** @var int Largest value. */
    protected const MAX = 0;
    /** @var int Smallest value while the site allows the full range. */
    protected const MIN_UNLIMITED = 0;
    /** @var int Smallest value while the site keeps values non-negative. */
    protected const MIN_NONNEGATIVE = 0;
    /** @var int The value stepping starts from when the feature is at its default. */
    protected const START = 0;
    /** @var string CSS custom property set on the html element. */
    protected const PROPERTY = '';
    /** @var int 100 when the value is hundredths of the CSS number, 1 when it is the number itself. */
    protected const SCALE = 1;
    /** @var string CSS unit after the number. */
    protected const CSSUNIT = '';
    /** @var string|null Lang string that labels a value (with the number as {$a}); null for the bare number. */
    protected const LABELSTRING = null;
    /** @var string|null Lang string of the unit shown beside the value field; null for none. */
    protected const UNITSTRING = null;
    /** @var bool Whether the default is beyond the largest value (full width), so + is unavailable there. */
    protected const DEFAULT_IS_MAX = false;

    /**
     * Default value (site default / off).
     *
     * @return string
     */
    public function default(): string {
        return static::DEFAULT;
    }

    /**
     * The only listed value is the default: every other value is a number in the range.
     *
     * @return string[]
     */
    public function values(): array {
        return [$this->default()];
    }

    /**
     * Whether the site keeps numeric values non-negative.
     *
     * @return bool
     */
    public static function nonnegative(): bool {
        return get_config('local_accessibility', 'numericlimits') === self::NONNEGATIVE;
    }

    /**
     * Smallest allowed value under the site's limits.
     *
     * @return int
     */
    public function min(): int {
        return self::nonnegative() ? static::MIN_NONNEGATIVE : static::MIN_UNLIMITED;
    }

    /**
     * Largest allowed value.
     *
     * @return int
     */
    public function max(): int {
        return static::MAX;
    }

    /**
     * Amount one − or + press changes the value by.
     *
     * @return int
     */
    public function step(): int {
        return static::STEP;
    }

    /**
     * Whether a value is allowed: the default, or an integer string (^-?\d{1,4}$, without leading zeros or "-0")
     * between min() and max().
     *
     * @param string $value
     * @return bool
     */
    public function validate(string $value): bool {
        if ($value === $this->default()) {
            return true;
        }
        if (!preg_match('/^-?\d{1,4}$/D', $value) || (string) (int) $value !== $value) {
            return false;
        }
        return (int) $value >= $this->min() && (int) $value <= $this->max();
    }

    /**
     * The CSS number of a value: hundredths as a short decimal, or the integer itself.
     *
     * @param int $value
     * @return string
     */
    protected static function css_number(int $value): string {
        return static::SCALE === 100 ? self::hundredths($value) : (string) $value;
    }

    /**
     * The value's custom property, for a validated non-default value only. Built from an integer, so it is safe in a
     * style attribute.
     *
     * @param string $value
     * @return array<string, string>
     */
    public function css_properties(string $value): array {
        if ($value === $this->default() || !$this->validate($value)) {
            return [];
        }
        return [static::PROPERTY => static::css_number((int) $value) . static::CSSUNIT];
    }

    /**
     * Label of one value: the default's own label, else the number in the user's units ("1.8", "0.12", "150%").
     *
     * @param string $value
     * @return string
     */
    public function value_label(string $value): string {
        if ($value === $this->default() && !preg_match('/^-?\d+$/D', $value)) {
            return parent::value_label($value);
        }
        $n = static::SCALE === 100 ? format_float((int) $value / 100, 2, true, true) : (string) (int) $value;
        return static::LABELSTRING === null ? $n : get_string(static::LABELSTRING, 'local_accessibility', $n);
    }

    /**
     * What the stepper needs to step, show and apply any value in the browser (local_accessibility/panel).
     *
     * @return array
     */
    public function stepper(): array {
        return [
            'step' => $this->step(),
            'min' => $this->min(),
            'max' => $this->max(),
            'start' => static::START,
            'defaultismax' => static::DEFAULT_IS_MAX,
            'scale' => static::SCALE,
            'property' => static::PROPERTY,
            'cssunit' => static::CSSUNIT,
            'labelstring' => static::LABELSTRING,
            'unit' => static::UNITSTRING === null ? '' : get_string(static::UNITSTRING, 'local_accessibility'),
            'nonnegative' => self::nonnegative(),
            'decsep' => get_string('decsep', 'langconfig'),
        ];
    }

    /**
     * The value shown in the stepper's field: the number in the user's units, or empty at a non-numeric default.
     *
     * @param string $value a validated value
     * @return string
     */
    public function field_value(string $value): string {
        if (!preg_match('/^-?\d+$/D', $value)) {
            return '';
        }
        return static::SCALE === 100 ? format_float((int) $value / 100, 2, true, true) : (string) (int) $value;
    }
}
