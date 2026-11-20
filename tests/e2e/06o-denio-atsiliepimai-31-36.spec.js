// v1.32: Denio atsiliepimai #31-#36 - „+XP“ iškart reitinge, kovos laikmatis (pradinis 0:10, be 2:00, Enbu nuo 0),
// telefone kovos langas neperšoka į viršų ir nuslenka iki apačios, AKA eilutė visa raudona, katos finale pirmas - žemiausias balas.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

test('XP page: +XP right in the ranking list', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  // Bent vienas narys su šio sezono XP
  const mid = +sql("SELECT id FROM members WHERE status = 'active' ORDER BY id LIMIT 1");
  const cat = +sql("SELECT id FROM point_categories WHERE code = 'lead_training'");
  sql(`INSERT INTO point_awards (member_id, category_id, points, awarded_on, awarded_by) VALUES (${mid}, ${cat}, 1, CURDATE(), 1)`);
  await a.goto('admin/taskai.php?rt=klubas');
  const row = a.locator(`#rankAll li:has(.rank-xp[data-member="${mid}"])`);
  check('+XP button next to the name', await row.count() === 1);
  const before = +(await row.locator('.pts').textContent());
  const n0 = +sql(`SELECT COUNT(*) FROM point_awards WHERE member_id = ${mid}`);
  await row.locator('.rank-xp').click();
  await a.waitForSelector('#rankQuick:not([hidden])');
  await a.fill('#rqPts', '3');
  await a.fill('#rqNote', 'Iš reitingo');
  await a.click('#rqGive');
  await row.locator('.xp-line').waitFor();
  check('points in the row grew by 3', +(await row.locator('.pts').textContent()) === before + 3);
  check('award saved', +sql(`SELECT COUNT(*) FROM point_awards WHERE member_id = ${mid}`) === n0 + 1 && sql(`SELECT note FROM point_awards WHERE member_id = ${mid} ORDER BY id DESC LIMIT 1`) === 'Iš reitingo');
  noJsErrors(a);
});

test('Taika: timer 0:10 by default, no 2:00, Enbu counts up; phone keeps scroll and reaches the bottom; red AKA rows; kata final lowest first', async ({ browser }) => {
  sql('DELETE FROM tournaments WHERE is_test = 1');
  const a = await login(browser, 'info@karateka.lt', 1400, 1000);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.click('#tab-live');
  if (!(await a.locator('#run').isVisible())) await a.locator('#sched [data-open-cat]').first().click();
  const openFromList = async (re) => {
    const rows = a.locator('#run-list .rl-row button');
    const n = await rows.count();
    for (let i = 0; i < n; i++) { if (re.test(await rows.nth(i).innerText())) { await rows.nth(i).click(); return true; } }
    return false;
  };

  // #36 Katos finalas: pasirodo nuo žemiausio pusfinalio balo
  check('kata group on tatami', await openFromList(/Kata (M|F)/));
  await a.selectOption('#draw-system', 'score');
  await a.waitForSelector('.score-table');
  await a.selectOption('[data-cfg="rounds"]', '2');
  await a.waitForSelector('[data-cfg="finalists"]');
  await a.fill('[data-cfg="finalists"]', '3'); await a.press('[data-cfg="finalists"]', 'Tab');
  await a.click('#draw-sim');
  await a.waitForSelector('.score-table >> nth=1');
  const semi = await a.locator('.score-panel').nth(1).locator('tbody tr').evaluateAll(rows => rows.map(r => parseFloat(r.querySelector('td:nth-last-child(3)').textContent)));
  check('final order: lowest semi-final score first', semi.length >= 2 && semi.every((v, i) => !i || semi[i - 1] <= v), JSON.stringify(semi));
  check('hint explains the order', /nuo žemiausio pusfinalio balo/.test(await a.textContent('#draw-view')));

  // #35 Tinkluose AKA eilutė visa raudona, SHIRO - balta
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  if (await a.locator('#area-pick [data-my-area="all"]').count()) await a.click('#area-pick [data-my-area="all"]');
  // Kumite grupė, kurioje dar yra neįvykusi kova
  const kums = a.locator('#sched [data-open-cat]', { hasText: /Kumite/ });
  let found = false;
  for (let i = 0; i < Math.min(await kums.count(), 12) && !found; i++) {
    await kums.nth(i).click();
    if (await a.locator('[data-runview="lines"]').count()) await a.click('[data-runview="lines"]');
    const ok = await a.waitForSelector('#draw-view .slot.aka', { timeout: 4000 }).then(() => true, () => false);
    if (ok && await a.locator('#draw-view [data-bout]').count()) found = true;
    else if (await a.locator('#run-back').isVisible()) await a.click('#run-back');
  }
  check('kumite group with an open bout', found);
  const colors = await a.evaluate(() => ({ aka: getComputedStyle(document.querySelector('#draw-view .slot.aka:not(.empty)') || document.querySelector('#draw-view .slot.aka')).backgroundImage, shiro: getComputedStyle(document.querySelector('#draw-view .slot.shiro')).backgroundColor }));
  check('AKA row red, SHIRO stays white', /linear-gradient\(.*rgb\((215, 38, 61|184, 30, 51)/.test(colors.aka) && !/gradient/.test(colors.shiroImg || ''), JSON.stringify(colors));

  // #32 Laikmatis
  await a.locator('#draw-view [data-bout]').first().click();
  await a.waitForSelector('.bt-presets');
  check('starts at 0:10', (await a.textContent('#bt-timer')).trim() === '0:10');
  const presets = await a.locator('.bt-presets button').allInnerTexts();
  check('no 2:00 preset, Enbu present', !presets.some(t => /2:00/.test(t)) && presets.some(t => /Enbu/.test(t)), presets.join(' | '));
  await a.click('.bt-presets [data-up]');
  check('Enbu starts at 0:00', (await a.textContent('#bt-timer')).trim() === '0:00');
  await a.click('#bt-start');
  await a.waitForTimeout(2300);
  await a.click('#bt-start');
  const up = (await a.textContent('#bt-timer')).trim();
  check('Enbu counts up', /^0:0[2-5]$/.test(up), up);

  // #33 / #34 Telefone: +1 neperšoka į viršų, langą galima nuslinkti iki apačios
  await a.setViewportSize({ width: 390, height: 660 });
  await a.waitForTimeout(200);
  const card = a.locator('.bout-card');
  await card.evaluate(el => { el.scrollTop = 260; });
  const plus = a.locator('.bt-side.aka [data-pt="a|1"]');
  const y0 = await card.evaluate(el => el.scrollTop);
  await plus.evaluate(el => el.click());   // paspaudimas be Playwright'o automatinio slinkimo iki mygtuko
  await a.waitForTimeout(150);
  const y1 = await a.locator('.bout-card').evaluate(el => el.scrollTop);
  check('scroll kept after +1', y0 > 100 && Math.abs(y1 - y0) < 3, y0 + ' → ' + y1);
  check('score counted', (await a.locator('.bt-side.aka .bt-score').inputValue()) === '1');
  await a.locator('.bout-card').evaluate(el => { el.scrollTop = el.scrollHeight; });
  const fin = await a.evaluate(() => { const r = document.querySelector('[data-win="b"]').getBoundingClientRect(); return { bottom: r.bottom, vh: window.innerHeight }; });
  check('bottom buttons reachable above the screen edge', fin.bottom < fin.vh - 40, JSON.stringify(fin));
  await a.setViewportSize({ width: 1400, height: 1000 });
  await a.click('#bt-close');
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});
