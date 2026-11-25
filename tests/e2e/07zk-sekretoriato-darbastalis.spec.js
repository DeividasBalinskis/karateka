// v2.01: sekretoriato darbastalis vienam tatamiui - kairėje meniu („📋 Mano tatamis“, „📹 Live kova“, „🏟 Visi tatamiai“) ir kas dabar / toliau,
// dešinėje visos to tatamio grupės su laikais; paspausta grupė išsiskleidžia čia pat (vardas - laimėjo, „✎ Keisti“ - sukeisti tempiant),
// grupių eilė - tempiant už ⠿; Live kovoje „🏅 Rezultatai“; sekretoriato nuoroda tatamiui (&t=N) atidaro jo darbastalį; telefone - be slinkimo į šoną.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, BASE_URL } = require('./helpers');

const blank = (r) => r.fulfill({ contentType: 'text/html', body: '<html><body style="background:#000"></body></html>' });
async function drag(p, from, to, dy = 0) {
  const f = await from.boundingBox(), t = await to.boundingBox();
  await p.mouse.move(f.x + f.width / 2, f.y + f.height / 2); await p.mouse.down();
  await p.mouse.move(f.x + f.width / 2, f.y + f.height / 2 + 12, { steps: 3 });
  await p.mouse.move(t.x + t.width / 2, t.y + t.height / 2 + dy, { steps: 10 }); await p.mouse.up();
}
const rowIds = (p) => p.$$eval('#desk .dk-row', rs => rs.map(r => r.dataset.dkRow));
const state = (tid) => JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
const saved = async (p) => { await p.waitForTimeout(1500); await p.waitForFunction(() => /Išsaugota/.test(document.getElementById('save-state').textContent), null, { timeout: 8000 }).catch(() => {}); };

test('secretariat desk: my tatami, groups open in place, winner by name, swap and reorder by dragging, all tatamis, Live results', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.route('https://vdo.ninja/**', blank);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  sql(`UPDATE tournaments SET starts_on = CURDATE() WHERE id = ${tid}`);
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.click('#tab-live');
  if (await a.locator('#run').isVisible()) await a.click('#run-back');

  // „▶ Start“: pasirinkus tatamį - jo darbastalis: meniu kairėje, tik to tatamio grupės, laikai, kas dabar
  await a.waitForSelector('#area-pick.ask');
  await a.click('#area-pick [data-my-area="1"]');
  await a.waitForSelector('#desk:not([hidden])');
  await saved(a);
  const mine = state(tid).categories.filter(c => c.tatami === 1).map(c => c.id);
  check('desk instead of the board', await a.locator('#live-board').isHidden() && await a.locator('.desk-side [data-dk="list"]').isVisible() && await a.locator('.desk-side [data-dk="all"]').isVisible());
  check('only my tatami groups, in schedule order', JSON.stringify(await rowIds(a)) === JSON.stringify(mine), JSON.stringify([await rowIds(a), mine]));
  check('times and now / next shown', await a.locator('#desk .dk-row .card-time-in').count() === mine.length && /Dabar|Visos grupės baigtos/.test(await a.textContent('#dk-now')));

  // Paspausta grupė išsiskleidžia čia pat su visa lentele; paspaudus vardą - laimėjo
  const now = a.locator('#desk .dk-row.st-now .dk-open');
  const openBtn = (await now.count()) ? now.first() : a.locator('#desk .dk-row .dk-open').first();
  await openBtn.click();
  await a.waitForSelector('#dk-run #run:not([hidden])');
  check('group opens under its row', await a.locator('#desk .dk-row.open').count() === 1 && await a.locator('#run.in-desk #draw-view').isVisible() && await a.locator('#run-list').isHidden());
  const free = a.locator('#draw-view .match:not(.is-done):not(.is-bye):not(.ghost) .slot button[data-m]:not([disabled])').first();
  if (await free.count()) {
    const wins = await a.locator('#draw-view .slot.win').count();
    await free.click();
    check('name click marks the winner', await a.locator('#draw-view .slot.win').count() === wins + 1);
  }
  await a.locator('#desk .dk-row.open .dk-open').click();
  check('second click folds the group', await a.locator('#desk .dk-row.open').count() === 0 && await a.locator('#run').isHidden());

  // „✎ Keisti“: nepradėtoje grupėje vardas nutempiamas ant kito - jie iškart susikeičia
  const later = a.locator('#desk .dk-row.st-later .dk-open, #desk .dk-row.st-next .dk-open');
  if (await later.count()) {
    await later.last().click();
    await a.waitForSelector('#dk-run #run:not([hidden])');
    await a.click('[data-runview="lines"]');
    if (await a.locator('#draw-swap').isVisible()) {
      await a.click('#draw-swap');
      check('edit button', /Baigti keitimą/.test(await a.textContent('#draw-swap')));
      const slots = a.locator('#draw-view [data-drag-slot]');
      if (await slots.count() >= 2) {
        const n0 = (await slots.nth(0).locator('b').innerText()).trim(), n1 = (await slots.nth(1).locator('b').innerText()).trim();
        await drag(a, slots.nth(0), slots.nth(1));
        await a.waitForTimeout(300);
        check('dragged names swapped at once', (await a.locator('#draw-view [data-drag-slot]').nth(0).locator('b').innerText()).trim() === n1 && n0 !== n1, n0 + ' / ' + n1);
      }
      await a.click('#draw-swap');
      check('edit ends', /✎ Keisti/.test(await a.textContent('#draw-swap')));
    }
  }

  // Grupių eilė: antra grupė nutempiama už ⠿ virš pirmos - laikai persiskaičiuoja, išlieka perkrovus
  if (await a.locator('#desk .dk-row.open').count()) await a.locator('#desk .dk-row.open .dk-open').click();
  await a.evaluate(() => window.scrollTo(0, 0));
  const before = await rowIds(a);
  if (before.length >= 2) {
    const rows = a.locator('#desk .dk-row');
    const top = await rows.first().boundingBox();
    await drag(a, rows.nth(1).locator('[data-dk-grip]'), rows.first(), -(top.height / 2 - 6));
    await a.waitForTimeout(300);
    const after = await rowIds(a);
    check('second group moved to the top', after[0] === before[1] && after[1] === before[0] && after.length === before.length, JSON.stringify([before, after]));
    await saved(a);
    check('new order saved', JSON.stringify(state(tid).categories.filter(c => c.tatami === 1).map(c => c.id)) === JSON.stringify(after));
  }
  await a.reload();
  check('desk remembered on this device', await a.locator('#desk').isVisible() && await a.locator('#area-pick').isHidden());

  // „🏟 Visi tatamiai“ - bendras tvarkaraštis; „📋 Mano tatamis“ - atgal
  await a.click('.desk-side [data-dk="all"]');
  check('all tatamis board', await a.locator('#live-board').isVisible() && await a.locator('#sched .area').count() === state(tid).areas.length && await a.locator('#desk').isHidden());
  await a.click('#desk-back');
  check('back to my desk', await a.locator('#desk').isVisible() && /1 TATAMIS/.test(await a.textContent('#dk-title')));

  // 📹 Live kova iš meniu: uždarius - atgal į darbastalį; „🏅 Rezultatai“ - grupės kovos šalia vaizdo
  if (await a.locator('.desk-side [data-dk="live"]').isVisible()) {
    await a.click('.desk-side [data-dk="live"]');
    await a.waitForSelector('#live-win:not([hidden])');
    check('Live close goes back to my tatami', /Mano tatamis/.test(await a.textContent('#live-win [data-lv="close"]')));
    await a.click('#live-win [data-lv="side"]');
    check('results next to the video', await a.locator('#live-win .lv-side').isVisible());
    await a.click('#live-win [data-lv="side"]');
    await a.click('#live-win [data-lv="close"]');
    check('desk again after Live', await a.locator('#live-win').isHidden() && await a.locator('#desk').isVisible());
  }

  // Adminas: „🧑‍💼 Sekretoriatas“ → darbastalis kitam tatamiui ir nuorodos tatamiams
  await a.click('#sekr-toggle');
  await nav(a, () => a.click('#sekr-panel [data-lk="new"]'));
  await a.waitForSelector('#sekr-panel:not([hidden]) #sekr-links [data-copy]');   // po sukūrimo skydelis atidarytas (&sekr=1)
  const copies = await a.$$eval('#sekr-links [data-copy]', bs => bs.map(b => b.dataset.copy));
  check('a secretariat link per tatami', copies.length === state(tid).areas.length && copies.every((u, i) => /varzybos-sekretoriatas\.php\?k=[0-9a-f]+&t=\d+$/.test(u)), JSON.stringify(copies));
  await a.click('#sekr-desk [data-desk-open="2"]');
  check('admin opens the desk of tatami 2', await a.locator('#desk').isVisible() && (await a.inputValue('#dk-area')) === '2');

  // Sekretorius su nuoroda 2 tatamiui: po vardo - iškart to tatamio darbastalis; telefone - be slinkimo į šoną
  const link = copies.find(u => u.endsWith('&t=2'));
  const ctx = await browser.newContext({ baseURL: BASE_URL, viewport: { width: 375, height: 812 } });
  const g = await ctx.newPage();
  g.jsErrors = []; g.on('pageerror', e => g.jsErrors.push(e.message)); g.on('dialog', d => d.accept());
  await g.goto(link);
  check('entry page names the tatami', /2 TATAMIS/.test(await g.textContent('main')));
  await g.fill('input[name="name"]', 'Tomas');
  await nav(g, () => g.click('button[type="submit"]'));
  await g.waitForSelector('#desk:not([hidden])');
  check('secretariat lands on the tatami 2 desk', (await g.inputValue('#dk-area')) === '2' && !/tatamis=/.test(g.url()), g.url());
  check('phone: no sideways scroll', await g.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
  check('phone: groups start on the first screen', (await g.locator('#desk .dk-row').first().boundingBox()).y < 812);
  await ctx.close();

  sql(`DELETE FROM tournaments WHERE id = ${tid}`);
  noJsErrors(a, g);
});
