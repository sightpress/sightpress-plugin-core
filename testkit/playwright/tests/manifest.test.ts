import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, it } from 'node:test';
import { formatViolations, LAUNCH_LOCALES, readPluginManifest, readRouteFixtures, routeUrls, RTL_TEST_LOCALES, WCAG_TAGS } from '../index.ts';

const fixture = (name: string) => new URL(`./fixtures/${name}`, import.meta.url);

function tempFile(content: string): string {
  const file = join(mkdtempSync(join(tmpdir(), 'sightpress-')), 'file.json');
  writeFileSync(file, content);
  return file;
}

describe('readPluginManifest', () => {
  it('reads routes, admin routes and locales', () => {
    assert.deepEqual(readPluginManifest(fixture('composer.json')), {
      id: 'acme.fixture',
      routes: ['/good', '/events/{slug}'],
      adminRoutes: ['/admin/good'],
      locales: ['en-GB'],
    });
  });

  it('treats missing lists as empty', () => {
    assert.deepEqual(readPluginManifest(tempFile('{"extra":{"sightpress":{"id":"x"}}}')), { id: 'x', routes: [], adminRoutes: [], locales: [] });
  });

  it('explains what is wrong', () => {
    assert.throws(() => readPluginManifest('/no/such/composer.json'), /Cannot read the plugin manifest/);
    assert.throws(() => readPluginManifest(tempFile('{}')), /extra\.sightpress\.id is missing/);
    assert.throws(() => readPluginManifest(tempFile('{"extra":{"sightpress":{"id":"x","routes":"/a"}}}')), /"routes" must be a list of strings/);
  });
});

describe('readRouteFixtures', () => {
  it('reads the fixtures file, and a missing file means none', () => {
    assert.deepEqual(readRouteFixtures(fixture('fixtures.json')), { '/events/{slug}': '/events/summer-fair' });
    assert.deepEqual(readRouteFixtures('/no/such/fixtures.json'), {});
  });

  it('refuses anything but a map of URLs', () => {
    assert.throws(() => readRouteFixtures(tempFile('["/a"]')), /must map route patterns to URLs/);
    assert.throws(() => readRouteFixtures(tempFile('{"/a/{b}": 1}')), /must map route patterns to URLs/);
  });
});

describe('routeUrls', () => {
  const manifest = { id: 'x', routes: ['/a', '/b/{id}', '/a'], adminRoutes: ['/admin/x/{id}'], locales: [] };

  it('fills parameters from fixtures and lists the routes it cannot test', () => {
    assert.deepEqual(routeUrls(manifest, { '/b/{id}': '/b/1' }), { urls: ['/a', '/b/1'], missing: ['/admin/x/{id}'] });
    assert.deepEqual(routeUrls(manifest), { urls: ['/a'], missing: ['/b/{id}', '/admin/x/{id}'] });
  });
});

describe('formatViolations', () => {
  it('describes each violation in words', () => {
    const text = formatViolations([
      { id: 'image-alt', impact: 'critical', help: 'Images must have alternative text', helpUrl: 'https://example.test/image-alt',
        nodes: [{ target: ['img.logo'], failureSummary: 'Fix any of the following:\n  Element has no alt attribute' }] },
      { id: 'label', help: 'Form elements must have labels', helpUrl: 'https://example.test/label', nodes: [{ target: ['#a'] }, { target: ['#b'] }] },
    ]);
    assert.match(text, /"image-alt" \(impact: critical\): Images must have alternative text\. 1 element:/);
    assert.match(text, /img\.logo: Fix any of the following: Element has no alt attribute/);
    assert.match(text, /"label" \(impact: unknown\): .* 2 elements:/);
  });
});

describe('constants', () => {
  it('match the project locales and WCAG 2.2 AA', () => {
    assert.equal(LAUNCH_LOCALES.length, 11);
    assert.deepEqual([...RTL_TEST_LOCALES], ['he-IL', 'ar-SA']);
    assert.ok(WCAG_TAGS.includes('wcag22aa'));
  });
});
