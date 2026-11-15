// v1.29: dalyvių vietų keitimas tempiant (pele ir pirštu, „⇄ Keisti vietas“), pasirodymo eilė balų sistemoje tempiant;
// kategorijos langelis prisegamas ekrane (📌 ar nutempus kortelę už tatamių), tempiamas, išlieka perkrovus; Taika telefone neišeina už ekrano.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

async function newTest(a) {
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('form button.vz-link')]);
  return +sql('SELECT MAX(id) FROM tournaments');
}
async function drag(a, from, to) {
  const f = await from.boundingBox(), t = await to.boundingBox();
  await a.mouse.move(f.x + f.width / 2, f.y + f.height / 2); await a.mouse.down();
  await a.mouse.move(f.x + f.width / 2 + 20, f.y + f.height / 2 + 20, { steps: 4 });
  await a.mouse.move(t.x + t.width / 2, t.y + t.height / 2, { steps: 8 }); await a.mouse.up();
}

test('swap names by dragging, reorder score order, pin a category window', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 1000);
  const tid = await newTest(a);
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.click('#tab-live');
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  if (await a.locator('#area-pick [data-my-area="all"]').count()) await a.click('#area-pick [data-my-area="all"]');
  // Nepradėta olimpinė kategorija: vardas nutempiamas ant kito - apsikeičia
  const card = a.locator('#sched .card.st-later [data-open-cat]').first();
  await card.click();
  await a.click('[data-runview="lines"]');
  const slots = a.locator('#draw-view [data-drag-slot]');
  if (await slots.count() >= 2) {
    const n0 = (await slots.nth(0).locator('b').innerText()).trim(), n1 = (await slots.nth(1).locator('b').innerText()).trim();
    await drag(a, slots.nth(0), slots.nth(1));
    await a.waitForTimeout(300);
    const m0 = (await a.locator('#draw-view [data-drag-slot]').nth(0).locator('b').innerText()).trim();
    check('names swapped by dragging', m0 === n1 && n0 !== n1, n0 + ' / ' + n1 + ' → ' + m0);
  }
  check('button renamed', /Keisti vietas/.test(await a.textContent('#draw-swap')));

  // Prisegti: mygtukas, langelis matosi kituose skirtukuose, tempiamas, išlieka perkrovus
  await a.click('#run-pin');
  await a.waitForSelector('.pin-win');
  await a.click('#tab-dalyviai');
  check('pin visible on another tab', await a.locator('.pin-win').isVisible());
  const head = a.locator('.pin-win .pin-head');
  const hb = await head.boundingBox();
  await a.mouse.move(hb.x + 40, hb.y + 10); await a.mouse.down(); await a.mouse.move(120, 600, { steps: 6 }); await a.mouse.up();
  const after = await a.locator('.pin-win').boundingBox();
  check('pin moved', Math.abs(after.y - 590) < 40 && after.x < 140, JSON.stringify(after));
  await a.reload();
  await a.waitForSelector('.pin-win');
  check('pin kept after reload', Math.abs((await a.locator('.pin-win').boundingBox()).y - after.y) < 4);
  await a.click('.pin-win [data-pin-min]');
  check('minimize', await a.locator('.pin-win .pin-body').count() === 0);
  await a.click('.pin-win [data-pin-x]');
  check('unpin', await a.locator('.pin-win').count() === 0);
  // Kortelė nutempta už tatamių - prisegama
  await a.click('#tab-live');
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  await a.locator('#sched .card').nth(2).dragTo(a.locator('#live-board .live-bar'), { targetPosition: { x: 40, y: 20 } });
  await a.waitForTimeout(300);
  check('card dragged out is pinned', await a.locator('.pin-win').count() === 1);
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});

test('Taika fits a phone screen; ⋯ opens inside the card', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 384, 760);
  const tid = await newTest(a);
  await a.goto('admin/varzybos.php');
  await a.locator('.varz-more summary').first().click();
  const card = await a.locator('.varz-card').first().boundingBox(), body = await a.locator('.varz-more-body').first().boundingBox();
  check('⋯ opens inside the card', body.y + body.height <= card.y + card.height + 1, JSON.stringify([card, body]));
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  for (const tab of ['klubai', 'komandos', 'dalyviai', 'kategorijos', 'live', 'rezultatai']) {
    await a.click('#tab-' + tab); await a.waitForTimeout(200);
    const w = await a.evaluate(() => Math.max(document.documentElement.scrollWidth, document.body.scrollWidth));
    check('no sideways scroll: ' + tab, w <= 386, String(w));
  }
  await a.click('#tab-dalyviai');
  check('participants as cards', await a.locator('.ppl-table thead').isHidden());
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});
