# 3.0.3

- No change to the plugin's code or behaviour.
- Releases now also publish to the camp plugin registry: the repository carries the camp listing (`.camp/listing.yml`,
  with screenshots) and its release workflow. These files are not included in the plugin download.
- Release archives built from the git tag no longer include the development folders `tests/` and `.github/`
  (`.gitattributes` export-ignore).
