// C4: vaizdinės apsaugos nustatymai (bash tests/visual.sh). Pradinės nuotraukos - tests/visual/__baseline__ (į git nekeliamos).
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: '.',
  testMatch: 'visual.spec.js',
  timeout: 60_000,
  workers: 1,
  retries: 0,
  outputDir: '../visual-results',
  snapshotPathTemplate: '{testDir}/__baseline__/{arg}{ext}',
  reporter: [['list'], ['html', { open: 'never', outputFolder: '../visual-report' }]],
  // PW_CHROMIUM - jei naršyklė įdiegta kitur (pvz. debesies sesijoje /opt/pw-browsers/chromium)
  use: { baseURL: process.env.BASE_URL || 'http://127.0.0.1:8088/', trace: 'off',
    launchOptions: process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {} },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});
