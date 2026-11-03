# SightPress plugin core

`sightpress/plugin-core` is the shared contract and tooling for every
SightPress plugin. Plugins depend on it, `sightpress/core` implements it, and
it depends on no other SightPress package. Licensed GPL-2.0-or-later.

Its version is the Plugin API version: Plugin API 1.0 is
`sightpress/plugin-core` 1.0.

> **Status:** M0 scaffolding complete: Plugin API 1.0 surface, value types,
> manifest reader, settings schema builder, test kit and shared quality rules.

## What is a plugin?

A SightPress plugin is a Composer package (a Symfony bundle extending
`AbstractPlugin`) that adds features to the SightPress CMS through the typed
extension points of the Plugin API, without touching the core. Site owners
enable or disable plugins per site.

With plugins you can build, for example: new content types, fields and blocks;
linter rules and publish guards; admin sections and Twig slots; importers and
media sources; notification types and delivery channels; account, legal and
personal-data features; structured data; and adapters such as SMS providers.

## What it provides

- **Plugin API:** typed extension points (content types, linter rules, block
  types, publish guards, field types, content options, admin sections, Twig
  slots, account sections, personal-data providers, legal sections, structured
  data, notification types and delivery, importers, media sources, adapters
  such as SMS providers), with their attributes, DTOs, read models, ports and
  domain events.
  - `MediaLibraryPort` adds files to the media library with the same checks as
    an upload, and can fill in credit and licence.
  - `NotifierPort` lets the core and any plugin notify people without
    requiring the `notifications` plugin, which delivers by each user's
    preferences when enabled.
- **Public value types:** ids, `Locale`, `Slug`, `SiteOwnedInterface`.
- **Base classes:** `AbstractPlugin` (bundle base class and manifest reader)
  and the settings-schema builder.
- **Test kit:** `PluginTestCase` (site isolation, plugin-disabled checks,
  XLIFF coverage, round-trip, security and query-count checks) plus Playwright
  and axe helpers.
- **Shared quality rules:** PHPStan rules, Twig and stylelint checks, Deptrac
  layers, the PHP-CS-Fixer ruleset, and `vendor/bin/sightpress-check-coverage`,
  which fails QA below 95% line coverage (the threshold can't be lowered).

## Creating a plugin

1. Create a repository from `sightpress/plugin-template`, then rename the
   package, namespace and plugin id placeholders to your own.
2. Make sure `composer.json` requires the API and dev-requires the core:

   ```json
   "require": { "sightpress/plugin-core": "^1.0" },
   "require-dev": { "sightpress/core": "^1.0" }
   ```

3. Extend `AbstractPlugin` for your bundle class and fill in the plugin
   manifest it reads.
4. Implement extension points using the Plugin API attributes and interfaces.
   Depend only on `sightpress/plugin-core`, never on `sightpress/core`
   internals (Deptrac enforces this).
5. Install dependencies:

   ```sh
   composer install
   ```

## Testing a plugin

- Write tests by extending `PluginTestCase`. It gives you site isolation,
  plugin-disabled checks, XLIFF coverage, round-trip, security and
  query-count checks.
- Run the suite with the plugin template's scripts (see its `composer.json`),
  for example:

  ```sh
  composer test
  ```

- Run the shared quality rules (PHPStan, PHP-CS-Fixer, Deptrac, Twig and
  stylelint checks) via the template's QA script.
- Check coverage; QA fails below 95% line coverage:

  ```sh
  vendor/bin/sightpress-check-coverage
  ```

- For UI, use the Playwright + axe helpers to test browser behaviour and
  accessibility.

Exact script names come from `sightpress/plugin-template`; check its
`composer.json` if they differ from the examples above.

## CLI tools

Composer links these scripts into `vendor/bin/` of any project that requires
this package. Run them from the project root. Each exits `0` on success, `1`
when the check fails and `2` on a usage or setup error.

### `sightpress-check-coverage`

Fails unless line coverage is at least 95% (fixed, can't be lowered).

```sh
vendor/bin/phpunit --coverage-clover var/coverage/clover.xml
vendor/bin/sightpress-check-coverage [clover.xml]
```

The report path is optional and defaults to `var/coverage/clover.xml`.

### `sightpress-check-licenses`

Checks that runtime dependency licences are compatible with
GPL-2.0-or-later. Needs `composer install` first (reads
`vendor/composer/installed.json` and `composer.json`).

```sh
vendor/bin/sightpress-check-licenses [project-dir]
```

`project-dir` defaults to the current directory.

### `sightpress-check-translations`

Checks the XLIFF catalogues of one translation domain. By default it checks
the 11 launch locales (the bar for core and official plugins).

```sh
vendor/bin/sightpress-check-translations <translations-dir> <domain> [--manifest=composer.json] [--locales=en-GB,nl-NL] [--release]
```

- `<translations-dir>`: directory holding the XLIFF files.
- `<domain>`: the translation domain to check.
- `--manifest`: check the plugin's required locales from its manifest. An
  official plugin needs all 11 launch locales; a third-party plugin needs
  only the locales it lists, plus `en-GB`, and the launch locales it lacks are
  listed in words without failing the check. Plugins use this.
- `--locales`: check these BCP 47 locales (plus `en-GB`) instead.
- `--release`: also fails while any target is still waiting for review
  (`needs-review-translation`). Use it in release pipelines.

Without `--release`, unreviewed targets are only reported.

Example: `vendor/bin/sightpress-check-translations translations my_plugin --manifest=composer.json --release`

## Contributing to this package

Changes to the Plugin API follow semantic versioning: breaking changes need a
new major version, since that is a new Plugin API version.
