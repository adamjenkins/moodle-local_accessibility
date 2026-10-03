# Accessibility #

Lets every user adjust how Moodle looks and reads for them: text size, spacing, fonts, colours, motion and more.
It is one plugin with all features built in.

## Features ##

- A panel with one tile per setting. Each tile shows its current setting in words, for example
  "Font, OpenDyslexic Alta". Open the panel with the floating button, the user menu entry or the keyboard
  shortcut Alt+A.
- Every option is shown and can be chosen directly, each with an icon or a preview and a label. Short lists
  open in a drawer under the tile; longer ones (text size, font, spacing, line width, colours) open a detail
  view. Arrow keys move between options and Enter or Space chooses one.
- Text size, line height, letter spacing, word spacing and line width have no fixed list of values. Each has
  − and + buttons (hold one to keep stepping), a field where you can type a number and press Enter, and a
  _Site default_ button. Changes show at once and are saved a moment after the last one.
- The panel follows your own text size, font, spacing and colours. Alignment, line width, link and image
  options and the reading guide are not applied to the panel itself.
- Profiles: one-click bundles of settings (Dyslexia, Focus, Low vision, Seizure-safe).
- Settings can start from the device's reduced-motion and contrast preferences.

| Setting | Choices |
|---|---|
| Text size | Any percentage up to 1000% (steps of 10; default 100%), with quick picks 100%, 125%, 150%, 200%, 250% and 300%. The whole page scales in proportion. |
| Font | Site default; the device's sans-serif, serif and monospace fonts; Atkinson Hyperlegible, Lexend, OpenDyslexic Alta and Comic Neue (included); Japanese UD Gothic, Japanese UD Mincho and Japanese textbook (device fonts); fonts uploaded by the administrator. Each option is shown in its own font. |
| Spacing: line height | Site default, or any value from 0 to 10 in steps of 0.1 (typed values to 0.01). Paragraph spacing follows the line height. |
| Spacing: letter spacing | Site default, or any value from −5 to 5 em in steps of 0.01 em |
| Spacing: word spacing | Site default, or any value from −10 to 10 em in steps of 0.02 em |
| Alignment | Site default, Left, Centre, Right, Justified |
| Colours | Site default, High contrast, Yellow on black, Black on white, Cream, Dark, the site's own schemes, and Custom (your own background, text and link colours, adjusted to a 7:1 contrast ratio unless you keep your exact colours) |
| Line width | Full width, or any width from 1 to 300 characters in steps of 5 |
| Links | Off, Underlined, Underline and outline, Highlighted |
| Images | Shown, Dimmed, Hidden (alt text shown) |
| Guide | Off, Reading ruler, Reading mask |
| Motion | On, Stopped |
| Read | Off, Read aloud bar shown (the browser's own voices, sentence by sentence; no visual highlight yet) |
| Saturation | Normal, Low, Greyscale, High |
| Focus ring | Standard, Strong, Extra thick |
| Cursor | Standard, Large, Extra large |

The ranges include the WCAG 1.4.12 text spacing values (line height 1.5, letter spacing 0.12, word spacing
0.16). The lower ends above (negative spacing, line heights under 1, text size and line width down to 1) apply
while the administrator's _Limits of number settings_ is _Unlimited_, the default.

### Included fonts ###

Atkinson Hyperlegible, Lexend and Comic Neue (regular and bold) are under the SIL Open Font License 1.1.
OpenDyslexic Alta (regular, bold and italic) is OpenDyslexic 2 by Abelardo Gonzalez, with a single-storey "a":
the original Bitstream glyphs are under the Bitstream Vera licence and the OpenDyslexic changes under CC BY 3.0.
Licence texts are in `fonts/`, sources in `thirdpartylibs.xml`.

The device fonts are font stacks: nothing is downloaded, and a device without any font in a stack shows its
own default font of that kind.

## Settings for administrators ##

Under _Site administration > Plugins > Local plugins > Accessibility_:

- Where users open the panel from (floating button, user menu, or both) and whether Alt+A is active.
- Site defaults for each setting, with a lock per tile so that everyone uses the default. Line height, letter
  spacing and word spacing have a default each and share one lock, _Lock Spacing_ (`lock_spacing`). The
  defaults of text size, line height, letter and word spacing and line width are whole numbers in the stored
  units (percent, hundredths, hundredths of an em, characters), or empty for the normal value.
- _Limits of number settings_ (`numericlimits`): _Unlimited_ (the default) lets users choose negative letter
  and word spacing, which squeezes text together, and a line height under 1.0, which makes lines overlap.
  _Non-negative_ keeps spacing at zero or more, line height at 1.0 or more, and text size and line width at 10
  or more. Changing it does not rewrite saved settings: a saved value outside the limits is ignored and the
  site default is used instead.
- _Fonts users may choose_ (`fonts_available`): which included and device fonts the Font view offers. All are
  offered by default; the site font always is.
- _Uploaded fonts_ (`fonts_uploaded`): up to 20 font files (.woff2, .woff, .ttf or .otf) offered as extra
  fonts. The file name sets the family and face: the part before the first hyphen or underscore is the family
  name, a rest containing "Bold" makes a bold face and one containing "Italic" or "Oblique" an italic face.
  For example `Family-Regular.woff2` and `Family-Bold.woff2` make one font, Family, with two faces. Names may
  use only letters, digits, spaces, dots, hyphens and underscores; other files are ignored. Check that each
  font's licence allows serving it on the web, and delete a file to stop offering it. Uploaded fonts are served
  without a login, so they also work on the login page, and only font files from this setting are served.
- Site colour schemes (each is checked for contrast) and your own profiles.
- _Features_ page: enable, disable and order the features.

## Guests ##

Visitors who are not logged in keep their settings in a cookie (`local_accessibility`) in their own browser.
Nothing is stored on the server for them. Logged-in users' settings are stored as user preferences.

## Known limitations ##

- Line height, letter spacing and word spacing do not yet apply to headings.
- Content inside iframes (including H5P) is not changed.
- The Moodle mobile app does not load the plugin.
- The colour contrast guarantee covers only the surfaces the plugin recolours. Content with its own
  colours, such as images or embedded media, is not guaranteed.
- At 200% text with extra spacing on a very narrow screen (320 px), some Moodle controls that never wrap
  (dropdown buttons, badges, Timeline event names on the Dashboard) can make the page scroll sideways.
- Read aloud uses the browser's own voices. In a browser with no voices installed the tile is hidden.

## Upgrading from 2.x ##

Existing users' settings are carried over. Moodle 4.5 or later is required. Text size, line height and letter
spacing keep their value, rounded to a whole percent or hundredth; paragraph widths of 25, 50 and 75 become line
widths of 50, 60 and 70 characters; highlighted links become _Underline and outline_ and hidden images _Hidden
(alt text shown)_.

The upgrade uninstalls an old `accessibility_*` widget plugin only when its folder is already gone from
`local/accessibility/widgets/`. If the old widget folders are still there after the upgrade (for example after
a git pull, or after unpacking 3.0 over the old folder), delete each `local/accessibility/widgets/<name>/`
folder, keeping `widgets/README.md`, then uninstall the leftover widgets under
_Site administration > Plugins > Plugins overview_.

## Installing ##

Install from a ZIP file via _Site administration > Plugins > Install plugins_, or put the contents of this
directory in `{your/moodle/dirroot}/local/accessibility` and run

    $ php admin/cli/upgrade.php

## Credits ##

- Adam Jenkins, maintainer and author of the 3.0 rebuild.
- Ponlawat Weerapanpisit, original author of the plugin and its widget framework.
- Bartlomiej Jencz, for the 2024 work on moving the plugin to Moodle's hooks API.

## License ##

2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
2026 Adam Jenkins <adam@wisecat.net>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
