# 3.0.2

- The full GPL-3.0 licence text is now included as `LICENSE` in the plugin root. The plugin's licence is unchanged
  (GPL-3.0-or-later).
- Installing with Composer no longer caps the Moodle version: `composer.json` now requires `moodle/moodle`
  `^4.5 || ^5.0` (was `>=4.5 <5.4`).
- Continuous integration now tests against the released Moodle 5.3 (`MOODLE_503_STABLE`) instead of Moodle's
  development branch.
