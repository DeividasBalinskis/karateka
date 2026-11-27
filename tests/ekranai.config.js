// Ekrano nuotraukos (.github/workflows/ekranai.yml): ne testai - tik nuotraukos į ekranai-out/ (375 ir 1440 px, kai reikia - 1280 / 2560).
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './ekranai',
  timeout: 180_000,
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: 'list',
  use: { baseURL: process.env.BASE_URL || 'http://127.0.0.1:8088/' },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});
