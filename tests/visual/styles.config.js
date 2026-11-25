// C5: stilių palyginimo nustatymai (styles.spec.js) - tie patys kaip vaizdinės apsaugos, tik kitas testų failas.
const base = require('./playwright.config.js');

module.exports = { ...base, testMatch: 'styles.spec.js', timeout: 300_000,
  reporter: [['list']], outputDir: '../visual-styles-results' };
