// C4: vaizdinė apsauga - ~15 puslapių nuotraukos 375 / 768 / 1440 (Valdymas ir Budo - ir 1280 / 2560).
// Prieš CSS darbus:   bash tests/visual.sh update   (padaromos pradinės nuotraukos, į git nekeliamos)
// Po pakeitimų:       bash tests/visual.sh          (palyginama; skirtumai - tests/visual-results/, atidaryti HTML ataskaitą)
// Ta pati duomenų bazė ir ta pati diena - kitaip skiriasi datos ir skaičiai.
const { test, expect } = require('@playwright/test');
const { login } = require('../e2e/helpers');
// Kiekvienas testas - savo langas (helpers.login uždaro ankstesnius)

const { PAGES, WIDTHS, WIDE } = require('./pages');   // sąrašas - pages.js (tas pats ir styles.spec.js)

for (const [name, url, who, wide] of PAGES) {
  for (const w of WIDTHS.concat(wide ? WIDE : [])) {
    test(`${name} @${w}`, async ({ browser }) => {
      const p = await login(browser, who, w, 900);
      await p.goto(url);
      await p.waitForLoadState('networkidle').catch(() => {});
      await p.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}' });
      // C5: be atsitiktinių skirtumų - visi paveikslėliai įkelti (ir „lazy“ apačioje), šriftai paruošti
      await p.evaluate(() => {
        document.querySelectorAll('img[loading="lazy"]').forEach(i => { i.loading = 'eager'; });
        const imgs = [...document.images].map(i => (i.complete ? i.decode().catch(() => {}) : new Promise(r => { i.addEventListener('load', r); i.addEventListener('error', r); })));
        return Promise.race([Promise.all(imgs.concat(document.fonts.ready)), new Promise(r => setTimeout(r, 8000))]);
      });
      await expect(p).toHaveScreenshot(`${name}-${w}.png`, {
        fullPage: true,
        maxDiffPixelRatio: 0.002,
        timeout: 15000,
        // .leaflet-container - LTKF žemėlapis (plytelės iš interneto, kraunasi kiekvieną kartą kitaip)
        mask: [p.locator('.ver-tag, time, .count-badge, .ntf-pill, #chatFab, .chat-hint, .leaflet-container')],
      });
    });
  }
}
