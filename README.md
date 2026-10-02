# Accessibility #

Lets every user adjust how Moodle looks and reads for them: text size, spacing, fonts, colours, motion and more.
It is one plugin with all features built in.

## Features ##

- A compact panel with one tile per setting. Open it with the floating button, the user menu entry or the
  keyboard shortcut Alt+A.
- Colour schemes: presets, or your own background, text and link colours. Custom colours are adjusted to a 7:1
  contrast ratio unless you choose to keep your exact colours.
- Text size scales the whole page in proportion. Spacing follows the WCAG 1.4.12 values.
- Fonts: Readable (Atkinson Hyperlegible) and OpenDyslexic.
- Reading guide, stop motion, saturation, strong focus ring, large cursor, link and image options.
- Read aloud using the browser's own voices, sentence by sentence (no visual highlight yet).
- Profiles: one-click bundles of settings (Dyslexia, Focus, Low vision, Seizure-safe).
- Settings can start from the device's reduced-motion and contrast preferences.

## Settings for administrators ##

Under _Site administration > Plugins > Local plugins > Accessibility_:

- Where users open the panel from (floating button, user menu, or both) and whether Alt+A is active.
- Site defaults for each setting, with a lock per setting so that everyone uses the default.
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

Existing users' settings are carried over, and the old widget plugins are removed. Moodle 4.5 or later is
required.

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
