# Changelog

## [3.0.0] - 2026-10-03

Rebuilt as a single plugin: the 11 accessibility widgets are built in, and existing users' settings are carried over
on upgrade. Requires Moodle 4.5 or later.

### Added
- A new panel: each tile shows its current setting in words; every option has an icon or preview and a label, in a
  drawer under the tile or a detail view, by keyboard or pointer. Keyboard shortcut Alt+A and a user menu entry.
  The panel follows the user's own text size, font, spacing and colours.
- Colour schemes: presets plus the user's own background, text and link colours, adjusted to 7:1 contrast unless
  the user keeps their exact colours.
- − and + steppers with a typed field and a Site default button for text size, line height, letter spacing, word
  spacing and line width, with no fixed list of values; the admin setting Limits of number settings (unlimited or
  non-negative).
- Fonts: Atkinson Hyperlegible, Lexend and Comic Neue (SIL OFL), OpenDyslexic Alta (Bitstream Vera licence and
  CC BY 3.0), device sans-serif, serif and monospace, three Japanese UD font stacks, and admin-uploaded fonts.
- Alignment, reading guide, stop motion, read aloud (browser voices), saturation, focus ring, large cursor, and
  link and image options.
- Profiles, site defaults and locks, validated site colour schemes, and first-visit device settings (reduced
  motion, more contrast).

### Changed
- Text size scales the whole page in proportion instead of flattening headings.
- Line height, letter spacing and word spacing are separate settings (previously the lineheight, letterspacing
  and fontkerning widgets).

### Removed
- The accessibility_* widget subplugins: they are uninstalled on upgrade when their folders are gone.

### Known limitations
- Line height, letter spacing and word spacing do not yet apply to headings.
