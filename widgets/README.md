# widgets/ (kept on purpose)

From version 3.0 every accessibility feature is built into local_accessibility, and this plugin no longer uses
widget subplugins. Do not put widgets in this folder.

The folder and `db/subplugins.json` stay so that Moodle still knows the `accessibility` plugin type. That lets the
upgrade from 2.x uninstall the old `accessibility_*` widgets cleanly. Moodle ignores a plugin type whose folder is
missing, so do not delete this file.

The 2.x widget framework was written by Ponlawat Weerapanpisit.
