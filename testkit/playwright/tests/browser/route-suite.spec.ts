import { test } from '@playwright/test';
import { defineRouteSuite } from '../../index.ts';

// Runs the suite a plugin would run, against the fixture server (tests/server.mjs).
defineRouteSuite({
  test,
  manifest: new URL('../fixtures/composer.json', import.meta.url),
  fixtures: new URL('../fixtures/fixtures.json', import.meta.url),
  baseUrl: `http://localhost:${process.env.PORT ?? 4177}`,
  localise: (url, locale) => `/${locale}${url}`,
});
