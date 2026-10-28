// Naršyklės testai. Svetainė turi veikti BASE_URL adresu su švaria testų baze (npm run reset-db).
// Failai vykdomi iš eilės (01-, 02-, ...): pirmi tikrina scenarijus su pavyzdiniais duomenimis.
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './e2e',
  timeout: 120_000,
  expect: { timeout: 5_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:8088/',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});
