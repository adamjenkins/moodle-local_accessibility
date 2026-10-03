# 3.0.0

Rebuilt as a single plugin. The 11 accessibility widgets are now built in, and existing users' settings are
carried over on upgrade.

- New panel: each tile shows its current setting in words, and every option is shown with an icon or a
  preview and a label, in a drawer under the tile or a detail view, chosen by keyboard or pointer. Keyboard
  shortcut Alt+A, and an entry in the user menu. The panel follows the user's own text size, font, spacing
  and colours.
- Colour schemes: presets plus your own background, text and link colours. These are adjusted to a 7:1 contrast
  ratio unless you choose to keep your exact colours.
- Text size scales the whole page in proportion (no longer flattens headings). Line height, letter spacing and
  word spacing are set separately, and the WCAG 1.4.12 values are within reach.
- Text size, line height, letter spacing, word spacing and line width have no fixed list of values: − and +
  buttons that repeat while held, a field that takes a typed number, and a _Site default_ button. Text size
  keeps quick picks (100% to 300%). Values may go negative (spacing) or under 1.0 (line height) unless the
  administrator's new _Limits of number settings_ (`numericlimits`) is set to _Non-negative_; their site
  defaults are typed numbers.
- Fonts: Atkinson Hyperlegible, Lexend and Comic Neue (included, SIL Open Font License), OpenDyslexic Alta with a
  single-storey a (included; Bitstream Vera licence and CC BY 3.0), the device's sans-serif, serif and monospace
  fonts, three Japanese UD font stacks, and fonts the administrator uploads. Administrators choose which built-in
  fonts are offered.
- New: alignment, line width, reading guide, stop motion, read aloud (browser voices; sentence by sentence, no
  visual highlight yet), saturation, focus ring (strong or extra thick), large or extra large cursor, and link
  (underline, outline or highlight) and image (dimmed, or hidden with alt text shown) options.
- Profiles, site defaults and locks, validated site colour schemes, and starting from the device's
  reduced-motion and contrast settings.
- Requires Moodle 4.5 or later.

Known limitations: line height, letter spacing and word spacing do not yet apply to headings; content inside
iframes (including H5P) is not changed. See the README for the full list.
