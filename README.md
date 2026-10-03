# Accessibility #

Lets every user adjust how Moodle looks and reads for them: text size, spacing, fonts, colours, motion and more.
It is one plugin with all features built in.

## Features ##

- A panel with one tile per setting. Each tile shows its current setting in words, for example
  "Font, OpenDyslexic". Open the panel with the floating button, the user menu entry or the keyboard
  shortcut Alt+A.
- Every option is shown and can be chosen directly, each with an icon or a preview and a label. Short lists
  open in a drawer under the tile; longer ones (text size, font, spacing, line width, colours) open a detail
  view. Arrow keys move between options and Enter or Space chooses one.
- The panel follows your own text size, font, spacing and colours. Alignment, line width, link and image
  options and the reading guide are not applied to the panel itself.
- Profiles: one-click bundles of settings (Dyslexia, Focus, Low vision, Seizure-safe).
- Settings can start from the device's reduced-motion and contrast preferences.

| Setting | Choices |
|---|---|
| Text size | 80% to 300% in steps of 10, plus 125% (default 100%). The whole page scales in proportion. |
| Font | Site default; the device's sans-serif, serif and monospace fonts; Atkinson Hyperlegible, Lexend, OpenDyslexic and Comic Neue (included); Japanese UD Gothic, Japanese UD Mincho and Japanese textbook (device fonts); fonts uploaded by the administrator. Each option is shown in its own font. |
| Spacing: line height | Site default, 1.2, 1.5, 1.8, 2.0, 2.5. Paragraph spacing follows the line height. |
| Spacing: letter spacing | Site default, 0.05, 0.10, 0.12, 0.16, 0.20, 0.30 (em) |
| Spacing: word spacing | Site default, 0.10, 0.16, 0.24, 0.40, 0.60 (em) |
| Alignment | Site default, Left, Centre, Right, Justified |
| Colours | Site default, High contrast, Yellow on black, Black on white, Cream, Dark, the site's own schemes, and Custom (your own background, text and link colours, adjusted to a 7:1 contrast ratio unless you keep your exact colours) |
| Line width | Full width, 90, 80, 70, 60, 50 or 40 characters |
| Links | Off, Underlined, Underline and outline, Highlighted |
| Images | Shown, Dimmed, Hidden (alt text shown) |
| Guide | Off, Reading ruler, Reading mask |
| Motion | On, Stopped |
| Read | Off, Read aloud bar shown (the browser's own voices, sentence by sentence; no visual highlight yet) |
| Saturation | Normal, Low, Greyscale, High |
| Focus ring | Standard, Strong, Extra thick |
| Cursor | Standard, Large, Extra large |

The spacing values include the WCAG 1.4.12 text spacing values (line height 1.5, letter spacing 0.12, word
spacing 0.16).

### Included fonts ###

All four are under the SIL Open Font License 1.1 (licence texts in `fonts/`, sources in `thirdpartylibs.xml`):
Atkinson Hyperlegible, Lexend, OpenDyslexic and Comic Neue, each in regular and bold.

The device fonts are font stacks: nothing is downloaded, and a device without any font in a stack shows its
own default font of that kind.

## Settings for administrators ##

Under _Site administration > Plugins > Local plugins > Accessibility_:

- Where users open the panel from (floating button, user menu, or both) and whether Alt+A is active.
- Site defaults for each setting, with a lock per tile so that everyone uses the default. Line height, letter
  spacing and word spacing have a default each and share one lock, _Lock Spacing_ (`lock_spacing`).
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

- Content inside iframes (including H5P) is not changed.
- The Moodle mobile app does not load the plugin.
- The colour contrast guarantee covers only the surfaces the plugin recolours. Content with its own
  colours, such as images or embedded media, is not guaranteed.
- At 200% text with extra spacing on a very narrow screen (320 px), some Moodle controls that never wrap
  (dropdown buttons, badges, Timeline event names on the Dashboard) can make the page scroll sideways.
- Read aloud uses the browser's own voices. In a browser with no voices installed the tile is hidden.

## Upgrading from 2.x ##

Existing users' settings are carried over. Moodle 4.5 or later is required. Text size, line height and letter
spacing move to the nearest new value; paragraph widths of 25, 50 and 75 become line widths of 50, 60 and 70
characters; highlighted links become _Underline and outline_ and hidden images _Hidden (alt text shown)_.

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

- Ponlawat Weerapanpisit, original author of the plugin and its widget framework.
- Bartlomiej Jencz, for the 2024 work on moving the plugin to Moodle's hooks API.

## License ##

2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
