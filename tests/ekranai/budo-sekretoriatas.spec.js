// Budo sekretoriatas: „📋 Mano tatamis“ (darbastalis), išskleista grupė (lentelė ir paprastas vaizdas), „🏟 Visi tatamiai“,
// jei yra - „⛶ Darbo režimas“. Nuotraukos: ekranai-out/<vardas>-<plotis>.png (375, 1280, 1440, 2560 px).
const { test } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const { sql, login, nav } = require('../e2e/helpers');

const OUT = path.join(__dirname, '..', 'ekranai-out');
fs.mkdirSync(OUT, { recursive: true });
const SIZES = [[375, 812], [1280, 800], [1440, 900], [2560, 1440]];
const saved = (p) => p.waitForFunction(() => /Išsaugota/.test((document.querySelector('.save-state') || {}).textContent || ''), null, { timeout: 15000 }).catch(() => {});
const shot = async (p, name, w, full) => { await p.waitForTimeout(500); await p.screenshot({ path: path.join(OUT, `${name}-${w}.png`), fullPage: !!full }); };

test('Budo secretariat screens', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await saved(a); await a.waitForTimeout(1500);
  const area1 = JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)).areas[0].id;

  for (const [w, h] of SIZES) {
    const p = await login(browser, 'info@karateka.lt', w, h);
    await p.goto(`admin/varzybu-sistema.php?id=${tid}`);
    await saved(p);
    await p.click('#tab-live');
    if (await p.locator('#run').isVisible()) await p.click('#run-back');
    if (await p.locator(`#area-pick.ask [data-my-area="${area1}"]`).count()) await p.click(`#area-pick.ask [data-my-area="${area1}"]`);
    else if (await p.locator(`#area-seg [data-my-area="${area1}"]`).isVisible()) await p.click(`#area-seg [data-my-area="${area1}"]`);
    await p.waitForSelector('#desk:not([hidden])', { timeout: 8000 }).catch(() => {});
    await shot(p, 'desk', w);
    // Išskleista grupė (ta, kuri vyksta dabar)
    const row = p.locator('.dk-row.st-now [data-dk-open]').first();
    await (await row.count() ? row : p.locator('.dk-row [data-dk-open]').first()).click();
    await p.waitForSelector('#draw-view .draw-head', { state: 'attached', timeout: 8000 }).catch(() => {});
    await shot(p, 'desk-open', w);
    await shot(p, 'desk-open-full', w, true);
    if (await p.locator('[data-runview="simple"]').first().isVisible()) {
      await p.locator('[data-runview="simple"]').first().click();
      await shot(p, 'desk-simple', w);
      await p.locator('[data-runview="lines"]').first().click();
    }
    // Darbo režimas (jei jau yra): tik šio tatamio grupės, lentelė telpa ekrane
    const focus = p.locator('[data-focus-toggle]').first();
    if (await focus.count() && await focus.isVisible()) {
      await focus.click(); await p.waitForTimeout(600);
      await shot(p, 'focus', w);
      if (await p.locator('[data-runview="simple"]').first().isVisible()) {
        await p.locator('[data-runview="simple"]').first().click();
        await shot(p, 'focus-simple', w);
        await p.locator('[data-runview="lines"]').first().click();
      }
      await focus.click().catch(() => {}); await p.waitForTimeout(300);
    }
    // „🏟 Visi tatamiai“
    const all = p.locator('[data-dk="all"]').first();
    if (await all.isVisible()) { await all.click(); await p.waitForTimeout(600); await shot(p, 'visi', w); }
    await p.context().close();
  }
});
