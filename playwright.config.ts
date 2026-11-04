import { defineConfig, devices } from '@playwright/test';

// Tests the shared Playwright + axe helpers against fixture pages.
const port = Number(process.env.PORT ?? 4177);

export default defineConfig({
  testDir: './testkit/playwright/tests/browser',
  forbidOnly: !!process.env.CI,
  reporter: process.env.CI ? [['github'], ['list']] : 'list',
  use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } },
  webServer: {
    command: 'node testkit/playwright/tests/server.mjs',
    url: `http://localhost:${port}/good`,
    reuseExistingServer: !process.env.CI,
    env: { PORT: String(port) },
  },
});
