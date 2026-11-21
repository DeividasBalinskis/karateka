// C4: vaizdinė apsauga - ~15 puslapių nuotraukos 375 / 768 / 1440 (Valdymas ir Budo - ir 1280 / 2560).
// Prieš CSS darbus:   bash tests/visual.sh update   (padaromos pradinės nuotraukos, į git nekeliamos)
// Po pakeitimų:       bash tests/visual.sh          (palyginama; skirtumai - tests/visual-results/, atidaryti HTML ataskaitą)
// Ta pati duomenų bazė ir ta pati diena - kitaip skiriasi datos ir skaičiai.
const { test, expect } = require('@playwright/test');
const { login } = require('../e2e/helpers');
// Kiekvienas testas - savo langas (helpers.login uždaro ankstesnius)

const PAGES = [
  // [pavadinimas, adresas, kas prisijungęs (null - svečias), ar Valdymas / Budo (papildomi pločiai)]
  ['pagrindinis', 'index.php', null],
  ['tvarkarastis', 'tvarkarastis.php', null],
  ['kontaktai', 'kontaktai.php', null],
  ['prisijungti', 'prisijungti.php', null],
  ['naujienos', 'naujienos.php', null],
  ['privatumas', 'privatumas.php', null],
  ['paskyra-tevai', 'paskyra.php', 'tevai@karateka.test'],
  ['paskyra-narys', 'paskyra.php', 'narys@karateka.test'],
  ['valdymas', 'admin/', 'info@karateka.lt', true],
  ['valdymas-nariai', 'admin/nariai.php', 'info@karateka.lt', true],
  ['valdymas-nustatymai', 'admin/nustatymai.php', 'info@karateka.lt', true],
  ['egzaminai', 'admin/egzaminai.php', 'info@karateka.lt', true],
  ['budo', 'admin/varzybos.php', 'info@karateka.lt', true],
  ['ltkf', 'ltkf/index.php', null],
  ['ltkf-klubai', 'ltkf/klubai.php', null],
];
const WIDTHS = [375, 768, 1440];
const WIDE = [1280, 2560];

for (const [name, url, who, wide] of PAGES) {
  for (const w of WIDTHS.concat(wide ? WIDE : [])) {
    test(`${name} @${w}`, async ({ browser }) => {
      const p = await login(browser, who, w, 900);
      await p.goto(url);
      await p.waitForLoadState('networkidle').catch(() => {});
      await p.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}' });
      await expect(p).toHaveScreenshot(`${name}-${w}.png`, {
        fullPage: true,
        maxDiffPixelRatio: 0.002,
        timeout: 15000,
        mask: [p.locator('.ver-tag, time, .count-badge, .ntf-pill, #chatFab, .chat-hint')],
      });
    });
  }
}
