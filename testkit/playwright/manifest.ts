import { readFileSync } from 'node:fs';

/** The parts of a plugin manifest (composer.json extra.sightpress) the browser tests use. */
export type PluginManifest = {
  id: string;
  routes: string[];
  adminRoutes: string[];
  locales: string[];
};

/** Route patterns mapped to a URL that fills their {parameters} on the fixture site. */
export type RouteFixtures = Record<string, string>;

export type RouteUrls = {
  /** URLs to test: plain routes plus the fixtures for routes with {parameters}. */
  urls: string[];
  /** Route patterns with {parameters} and no fixture. A suite fails on these: every route is tested. */
  missing: string[];
};

function stringList(value: unknown, field: string): string[] {
  if (value === undefined) {
    return [];
  }
  if (!Array.isArray(value) || !value.every((item) => typeof item === 'string')) {
    throw new Error(`The plugin manifest field "${field}" must be a list of strings.`);
  }

  return value;
}

/** Reads extra.sightpress from a plugin's composer.json. */
export function readPluginManifest(composerJsonPath: string | URL): PluginManifest {
  let composer: unknown;
  try {
    composer = JSON.parse(readFileSync(composerJsonPath, 'utf8'));
  } catch (error) {
    throw new Error(`Cannot read the plugin manifest from "${String(composerJsonPath)}": ${(error as Error).message}`);
  }
  const extra = (composer as { extra?: { sightpress?: Record<string, unknown> } }).extra?.sightpress;
  if (!extra || typeof extra.id !== 'string' || extra.id === '') {
    throw new Error(`"${String(composerJsonPath)}" has no plugin manifest: extra.sightpress.id is missing.`);
  }

  return {
    id: extra.id,
    routes: stringList(extra.routes, 'routes'),
    adminRoutes: stringList(extra.admin_routes, 'admin_routes'),
    locales: stringList(extra.locales, 'locales'),
  };
}

/** Reads e2e/fixtures.json ({"<route pattern>": "<url>"}); a missing file means no fixtures. */
export function readRouteFixtures(path: string | URL): RouteFixtures {
  let raw: string;
  try {
    raw = readFileSync(path, 'utf8');
  } catch {
    return {};
  }
  const parsed: unknown = JSON.parse(raw);
  if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed) ||
    !Object.values(parsed).every((url) => typeof url === 'string')) {
    throw new Error(`"${String(path)}" must map route patterns to URLs, for example {"/events/{slug}": "/events/summer-fair"}.`);
  }

  return parsed as RouteFixtures;
}

/** Turns the manifest's public and admin routes into URLs to test. */
export function routeUrls(manifest: PluginManifest, fixtures: RouteFixtures = {}): RouteUrls {
  const urls: string[] = [];
  const missing: string[] = [];
  for (const pattern of [...manifest.routes, ...manifest.adminRoutes]) {
    if (!pattern.includes('{')) {
      urls.push(pattern);
    } else if (fixtures[pattern]) {
      urls.push(fixtures[pattern]);
    } else {
      missing.push(pattern);
    }
  }

  return { urls: [...new Set(urls)], missing };
}
