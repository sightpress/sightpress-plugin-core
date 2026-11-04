// Playwright + axe helpers for SightPress plugins (plugins.md §6).
//
// Import them from the plugin's e2e specs:
//   import { defineRouteSuite, assertNoA11yViolations, tabTo } from '../vendor/sightpress/plugin-core/testkit/playwright/index.ts';
//
// They need @playwright/test and @axe-core/playwright in the plugin's package.json.
// None of them can turn an axe rule off: accessibility can't be turned off.

export { analyseA11y, assertNoA11yViolations, formatViolations, WCAG_TAGS } from './axe.ts';
export type { A11yCheckOptions, A11yResult, AxeViolation } from './axe.ts';
export { assertKeyboardJourney, tabThrough, tabTo } from './keyboard.ts';
export type { KeyboardJourney, KeyboardJourneyOptions, KeyboardStop } from './keyboard.ts';
export { LAUNCH_LOCALES, REFLOW_WIDTH, RTL_TEST_LOCALES, TEXT_EXPANSION_LOCALE } from './locales.ts';
export { readPluginManifest, readRouteFixtures, routeUrls } from './manifest.ts';
export type { PluginManifest, RouteFixtures, RouteUrls } from './manifest.ts';
export { assertReflows, checkReflow } from './reflow.ts';
export { assertPageStructure, checkPageStructure } from './structure.ts';
export type { StructureOptions } from './structure.ts';
export { defineRouteSuite } from './suite.ts';
export type { RouteSuiteOptions } from './suite.ts';
