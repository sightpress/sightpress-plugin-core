# SightPress plugin core

`sightpress/plugin-core`: the code every SightPress plugin shares. Licensed
GPL-2.0-or-later.

> **Status:** pre-M0.

It contains:

- **The Plugin API:** the typed extension points (content types, linter rules,
  block types, publish guards, field types, content options, admin sections,
  Twig slots, account sections, personal-data providers, legal sections,
  structured data, notification types and delivery, importers, media sources,
  adapters such as SMS providers), with their attributes, DTOs, read models, ports
  (including `MediaLibraryPort`, which adds files to the media library with
  the same checks as an upload and can fill in their credit and licence,
  and `NotifierPort`, so the core and any plugin can notify people
  without requiring the `notifications` plugin, which delivers them by each
  user's preferences when it's enabled) and domain events.
- **Public value types** plugins use: ids, `Locale`, `Slug`,
  `SiteOwnedInterface`.
- **Base classes:** `AbstractPlugin` (the bundle base class and manifest
  reader) and the settings-schema builder.
- **The test kit:** `PluginTestCase` (site isolation, plugin-disabled checks,
  XLIFF coverage, round-trip, security and query-count checks) and Playwright +
  axe helpers.
- **Shared quality rules:** PHPStan rules, Twig and stylelint checks, Deptrac
  layers and the PHP-CS-Fixer ruleset.

Its version is the Plugin API version: `Plugin API 1.0` is
`sightpress/plugin-core` 1.0. It depends on no other SightPress package.
`sightpress/core` implements it, and plugins require it:

```json
"require": { "sightpress/plugin-core": "^1.0" },
"require-dev": { "sightpress/core": "^1.0" }
```

To start a new plugin, use `sightpress/plugin-template`. The full
specification is in `plugins.md`.
