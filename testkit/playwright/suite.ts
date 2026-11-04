import type { Page, TestType } from '@playwright/test';
import { assertNoA11yViolations } from './axe.ts';
import { assertKeyboardJourney } from './keyboard.ts';
import { RTL_TEST_LOCALES } from './locales.ts';
import type { PluginManifest, RouteFixtures } from './manifest.ts';
import { readPluginManifest, readRouteFixtures, routeUrls } from './manifest.ts';
import { checkReflow } from './reflow.ts';
import { checkPageStructure } from './structure.ts';

// Accepts the plugin's own `test`, so the helpers never load a second copy of @playwright/test.
type AnyTest = TestType<any, any>;

export type RouteSuiteOptions = {
  /** The plugin's `test` from @playwright/test. */
  test: AnyTest;
  /** The manifest, or the path or URL of the plugin's composer.json. */
  manifest: PluginManifest | string | URL;
  /** Fixture URLs for routes with {parameters}, or the path or URL of e2e/fixtures.json. */
  fixtures?: RouteFixtures | string | URL;
  /** The site under test; defaults to SIGHTPRESS_BASE_URL. Without one, the suite skips and says why. */
  baseUrl?: string;
  /**
   * Turns a route URL into the same page in another locale (for example by
   * adding a /he-IL prefix). When set, every route also runs in he-IL and ar-SA
   * and must come back with dir="rtl".
   */
  localise?: (url: string, locale: string) => string;
};

async function checkRoute(page: Page, url: string, expected: { dir?: 'ltr' | 'rtl'; lang?: string } = {}): Promise<void> {
  const response = await page.goto(url);
  const status = response?.status() ?? 0;
  if (status >= 400 || status === 0) {
    throw new Error(`${url} answered with HTTP status ${status}; every manifest route must render on the fixture site.`);
  }

  const problems = [...await checkPageStructure(page, expected), ...await checkReflow(page)];
  if (problems.length > 0) {
    throw new Error(`${url} has problems:\n${problems.map((p) => `  - ${p}`).join('\n')}`);
  }
  await assertNoA11yViolations(page);
  await page.goto(url);
  await assertKeyboardJourney(page);
}

/**
 * Registers the browser checks for every route in a plugin's manifest:
 * WCAG 2.2 AA with axe, page structure, reflow at 320 pixels, and a clean
 * keyboard journey; with `localise`, also in the RTL test locales.
 *
 *   import { test } from '@playwright/test';
 *   import { defineRouteSuite } from '../vendor/sightpress/plugin-core/testkit/playwright/index.ts';
 *
 *   defineRouteSuite({ test, manifest: new URL('../composer.json', import.meta.url),
 *     fixtures: new URL('./fixtures.json', import.meta.url) });
 */
export function defineRouteSuite(options: RouteSuiteOptions): void {
  const { test } = options;
  const manifest = typeof options.manifest === 'object' && !(options.manifest instanceof URL)
    ? options.manifest
    : readPluginManifest(options.manifest);
  const fixtures = options.fixtures === undefined || (typeof options.fixtures === 'object' && !(options.fixtures instanceof URL))
    ? (options.fixtures ?? {})
    : readRouteFixtures(options.fixtures);
  const { urls, missing } = routeUrls(manifest, fixtures);
  const baseUrl = options.baseUrl ?? process.env.SIGHTPRESS_BASE_URL;

  test.describe(`${manifest.id}: every route is accessible`, () => {
    test('every route with {parameters} has a fixture URL', () => {
      if (missing.length > 0) {
        throw new Error(`These routes have no fixture URL, so they wouldn't be tested: ${missing.join(', ')}. Add them to e2e/fixtures.json as {"<route>": "<url>"}.`);
      }
    });

    test.describe('on the fixture site', () => {
      test.skip(!baseUrl, 'Set SIGHTPRESS_BASE_URL to a running site with this plugin enabled.');
      if (baseUrl) {
        test.use({ baseURL: baseUrl });
      }

      for (const url of urls) {
        test(`${url} passes the accessibility checks`, async ({ page }) => {
          await checkRoute(page, url, { dir: 'ltr' });
        });

        if (options.localise) {
          for (const locale of RTL_TEST_LOCALES) {
            const localised = options.localise(url, locale);
            test(`${localised} passes the accessibility checks in ${locale} (right to left)`, async ({ page }) => {
              await checkRoute(page, localised, { dir: 'rtl' });
            });
          }
        }
      }
    });
  });
}
