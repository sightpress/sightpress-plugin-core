# SightPress plugin core

`sightpress/plugin-core` is the shared contract and tooling for every
SightPress plugin. Plugins depend on it, `sightpress/core` implements it, and
it depends on no other SightPress package. Licensed GPL-2.0-or-later.

Its version is the Plugin API version: Plugin API 1.0 is
`sightpress/plugin-core` 1.0.

> **Status:** M0 scaffolding complete: Plugin API 1.0 surface, value types,
> manifest reader, settings schema builder, test kit (with Playwright + axe
> helpers) and shared quality rules (Twig lint, stylelint rules, Deptrac layers).

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
    requiring the `notifications-helper` plugin, which delivers by each user's
    preferences when enabled.
- **Public value types:** ids, `Locale`, `Slug`, `SiteOwnedInterface`.
- **Base classes:** `AbstractPlugin` (bundle base class and manifest reader)
  and the settings-schema builder.
- **Test kit:** `PluginTestCase` (site isolation, plugin-disabled checks,
  XLIFF coverage, round-trip, security and query-count checks),
  `AccessibleHtmlAssertions` (page structure, labels, the error summary,
  `aria-describedby` and focus targets in any `WebTestCase`) plus Playwright
  and axe helpers (`testkit/playwright/`).
- **Shared quality rules:** PHPStan rules, the Twig lint
  (`vendor/bin/sightpress-lint-twig`), stylelint rules
  (`quality/stylelint/`), Deptrac layers (`quality/deptrac/plugin.yaml`), the
  PHP-CS-Fixer ruleset, and `vendor/bin/sightpress-check-coverage`, which fails
  QA below 95% line coverage (the threshold can't be lowered).

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

### `sightpress-translations-csv`

Exports the XLIFF catalogues of one translation domain to CSV, one file per
locale, for translators who work in a spreadsheet, and imports the CSV files
back into the catalogues. The `en-GB` source catalogue isn't exported or
imported: edit its XLIFF file directly.

```sh
vendor/bin/sightpress-translations-csv export <translations-dir> <domain> [--locales=de-DE,fr-FR | --manifest=composer.json] [--output=dir]
vendor/bin/sightpress-translations-csv import <translations-dir> <domain> <file.csv> [<file.csv> ...] [--locale=de-DE] [--dry-run]
```

**Export** writes `<domain>.<locale>.csv` (for example `messages.pt-BR.csv`)
into `--output` (default: the current directory). By default it exports the
10 launch locales other than `en-GB`; `--manifest` exports the plugin's
required locales and `--locales` the ones you list. Files are UTF-8 with a
byte-order mark, so spreadsheets open them with the right characters. The
columns are:

| Column | Contents |
|---|---|
| `key` | The message key. Don't change it. |
| `source` | The English (United Kingdom) text. |
| `fallback` | For a regional override such as `pt-BR` or `de-CH`, the base catalogue's text it falls back to; empty otherwise. |
| `target` | The translation. Empty when there is none yet (for an override: when it doesn't differ from the fallback). |
| `state` | The XLIFF state, such as `needs-review-translation` or `translated`. |
| `note` | The source catalogue's note, such as `neutral`. |

**Import** reads the locale from the file name, or from `--locale` for a
single file. Only `key` and `target` are needed, in any order; `state` is
optional and the other columns are ignored. Comma, semicolon and tab
separators are all accepted. For each row:

- A changed target is stored as `needs-review-translation`, unless the row
  also changes the state: that is taken as the reviewer's decision. Machine
  and AI drafts therefore stay unreviewed until someone signs them off.
- An unchanged target with a different state takes the new state (how a
  reviewer signs a target off: set the state to `translated`).
- An empty target leaves the unit as it is. Import never deletes anything.
- A key missing from the catalogue is added (with its English source); a
  missing catalogue file is created.

A key that isn't in the source catalogue, a repeated key, an unknown state,
text that isn't UTF-8 or a control character is reported with its row
number, and that file's catalogue is not written at all. Fix the file and
import it again. `--dry-run` reports what would change and writes nothing.
Units that are left alone keep their exact bytes, so the catalogue diff shows
only what was imported. Run `sightpress-check-translations` afterwards.

Example: `vendor/bin/sightpress-translations-csv export translations messages --output=var/csv`, then
`vendor/bin/sightpress-translations-csv import translations messages var/csv/*.csv`

### `sightpress-lint-twig`

Lints every `*.twig` file under the given directories (default `templates`).
Each problem is printed as `file:line: [rule] what is wrong and what to do`.

```sh
vendor/bin/sightpress-lint-twig [templates-dir ...]
```

- `raw-filter`: `|raw`, `{% apply raw %}` and `{% autoescape false %}`. Only
  the core's sanitised `body_html` may be printed raw.
- `hard-coded-string`: text, and `alt`, `title`, `aria-label`, `placeholder`
  and other attributes people read or hear, written into the template instead
  of coming from translations.
- `inline-script`: inline `<script>`, `on*` attributes and `javascript:` URLs,
  which the strict content security policy refuses.
- `third-party-resource`: scripts, stylesheets and fonts from another host.
- `positive-tabindex`: `tabindex` above 0, which breaks the focus order.

No rule can be turned off inside a template.

## Playwright + axe helpers

TypeScript helpers in `testkit/playwright/`, for the plugin's browser tests.
They need `@playwright/test` and `@axe-core/playwright` in the plugin's
`package.json`, and never load their own copy of `@playwright/test`: pass in
your `test`. No helper can turn an axe rule off.

```ts
import { test } from '@playwright/test';
import { defineRouteSuite } from '../vendor/sightpress/plugin-core/testkit/playwright/index.ts';

defineRouteSuite({
  test,
  manifest: new URL('../composer.json', import.meta.url),
  fixtures: new URL('./fixtures.json', import.meta.url),
  // Optional: also run every route in he-IL and ar-SA, which must be dir="rtl".
  localise: (url, locale) => `/${locale}${url}`,
});
```

`defineRouteSuite` tests every public and admin route in the manifest on the
site at `SIGHTPRESS_BASE_URL` (it skips with a reason when that isn't set):
WCAG 2.2 AA with axe, page structure, reflow at 320 CSS pixels and a clean
keyboard journey. Routes with `{parameters}` need a URL in `e2e/fixtures.json`;
a route without one fails the suite instead of going untested.

For your own specs:

- `assertNoA11yViolations(page, { include? })`: axe with the WCAG 2.2 AA tags;
  fails with every violation described in words.
- `assertPageStructure(page, { lang?, dir? })`: `lang`/`dir`, a title, one
  `h1`, heading order, the banner, navigation, main and footer landmarks, and a
  skip link first.
- `assertReflows(page)`: no sideways scrolling at 320 CSS pixels.
- `assertKeyboardJourney(page)` / `tabThrough(page)`: presses Tab through the
  page and reports invisible focus, focus off screen and keyboard traps; the
  stops make a tab-order snapshot
  (`expect(journey.stops.map((s) => s.name)).toMatchSnapshot()`).
- `tabTo(page, 'button "Save"')`: moves focus with Tab only, for keyboard-only
  journeys.
- `LAUNCH_LOCALES`, `RTL_TEST_LOCALES`, `TEXT_EXPANSION_LOCALE`.

## Stylelint rules

`quality/stylelint/config.mjs` holds the shared stylelint configuration:
logical properties only, colours and fonts from theme tokens only, no
third-party URLs, motion only without a reduced-motion preference, and no
removing the focus outline. See
[`quality/stylelint/README.md`](quality/stylelint/README.md).

## Deptrac layers

`quality/deptrac/plugin.yaml` holds the layers and rules every plugin shares.
A plugin's `deptrac.yaml` imports it and names its namespace:

```yaml
imports:
  - vendor/sightpress/plugin-core/quality/deptrac/plugin.yaml
parameters:
  sightpress.plugin_namespace: 'SightPress\\Plugin\\Events'
  # Only with `requires`: the Contract\ namespaces the plugin may use, as one regex.
  sightpress.required_contracts: '^SightPress\\Plugin\\AiHelper\\Contract\\.*'
```

Plugin code may use the Plugin API (not the dev-only `TestKit` and `Quality`
namespaces) and the `Contract\` namespace of required plugins, never
`SightPress\Core\` or another plugin's internals. Inside the plugin, `Domain`
uses only `Contract`, and `Application`, `Infrastructure` and `UI` build on
`Domain`.

## Developing this package

PHP checks run with `composer qa`. The JavaScript parts are tested with Node
22.18 or later:

```sh
npm ci
npx playwright install chromium
npm test        # unit tests, type check, browser tests
```

## Contributing to this package

Changes to the Plugin API follow semantic versioning: breaking changes need a
new major version, since that is a new Plugin API version.
