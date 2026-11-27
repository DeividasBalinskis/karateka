// v2.15 (Deividas ir atsiliepimas „tik varžybinis langas be kitų pasirinkimų“): sekretoriato „📋 Mano tatamis“ - baigtos grupės suskleistos,
// grupės nustatymai po „⚙ Daugiau“; „⛶ Darbo režimas“ - tik šio tatamio grupės per visą ekraną, lentelė telpa, lenta neslenka su puslapiu,
// telefone - „Paprastas“ vaizdas su dideliais „Laimėjo“. Dabartinė kova - mėlyna „Dabar“, kita - geltona „Ruošiasi“. Laikrodis be „10:60“.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

const state = (tid) => JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
const saved = async (p) => { await p.waitForTimeout(1200); await p.waitForFunction(() => /Išsaugota/.test(document.getElementById('save-state').textContent), null, { timeout: 8000 }).catch(() => {}); };
async function openDesk(p, tid) {
  await p.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await p.click('#tab-live');
  if (await p.locator('#run').isVisible()) await p.click('#run-back');
  if (await p.locator('#area-pick.ask [data-my-area="1"]').count()) await p.click('#area-pick.ask [data-my-area="1"]');
  else if (await p.locator('#area-seg [data-my-area="1"]').isVisible()) await p.click('#area-seg [data-my-area="1"]');
  await p.waitForSelector('#desk:not([hidden])');
}

test('secretariat work mode: only my tatami full screen, bracket fits, current bout blue and next yellow, phone marks winners simply', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  sql(`UPDATE tournaments SET starts_on = CURDATE() WHERE id = ${tid}`);
  // Test varžybos sukuriamos atidarius - tada viena kumite grupė (4+ dalyviai) perkeliama į 1 tatamį ir jos rezultatai išvalomi
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await saved(a);
  const all = state(tid).categories, bi = all.findIndex((c) => c.discipline === 'kumite' && !c.team && c.competitors.length >= 4 && c.draw && c.draw.slots);
  check('a kumite group with 4+ athletes', bi >= 0);
  const big = all[bi];
  sql(`UPDATE tournaments SET state = JSON_SET(state, '$.categories[${bi}].tatami', 1, '$.categories[${bi}].draw.results', JSON_OBJECT(), '$.categories[${bi}].draw.scores', JSON_OBJECT(), '$.categories[${bi}].draw.locked', JSON_EXTRACT('false', '$')) WHERE id = ${tid}`);
  await openDesk(a, tid);
  await saved(a);
  const mine = state(tid).categories.filter((c) => c.tatami === 1);
  check('clock shows a real time', /^\d{2}:[0-5]\d$/.test((await a.textContent('#dk-clock')).trim()), await a.textContent('#dk-clock'));
  check('work mode button in the desk', await a.locator('#dk-focus').isVisible());

  // ⛶ Darbo režimas: viršus paslėptas, juosta su laiku ir šio tatamio grupėmis, atidaryta dabartinė grupė
  await a.click('#dk-focus');
  await a.waitForSelector('body.vz-focus #fx-bar:not([hidden])');
  await a.waitForSelector('#dk-run #run:not([hidden])');
  await saved(a);
  check('page header and other menus hidden', await a.locator('.vz-top').isHidden() && await a.locator('.desk-side').isHidden() && await a.locator('#dk-list').isHidden());
  check('a chip for every group of my tatami', await a.locator('#fx-groups .fx-chip').count() === mine.length, String(mine.length));
  check('one group open, marked in the strip', await a.locator('#fx-groups .fx-chip.on').count() === 1);
  check('saved state and clock in the bar', /Išsaugota/.test(await a.textContent('#fx-save')) && /^\d{2}:[0-5]\d$/.test((await a.textContent('.fx-clock')).trim()));
  check('board does not move with the page', await a.evaluate(() => getComputedStyle(document.body).overflow === 'hidden' && getComputedStyle(document.getElementById('dk-run')).position === 'fixed'));
  check('group settings folded', await a.locator('#run-tools').isHidden() && await a.locator('#run-undo').isVisible() && await a.locator('#run-more').isVisible());

  // Olimpinė grupė su keliomis kovomis: lentelė telpa, dabartinė - mėlyna, kita - geltona; pažymėjus nugalėtoją - pasislenka
  await a.click(`#fx-groups [data-fx-open="${big.id}"]`);
  await a.waitForTimeout(400);
  if (await a.locator('[data-runview="lines"]').isVisible()) await a.click('[data-runview="lines"]');
  await a.waitForSelector('#draw-view .bracket');
  check('strip follows the open group', (await a.getAttribute('#fx-groups .fx-chip.on', 'data-fx-open')) === big.id && (await a.textContent('#run-name')).includes(big.name));
  const fit = await a.evaluate(() => { const w = document.querySelector('#draw-view .bracket-wrap'), b = w.querySelector('.bracket'); return { bw: b.getBoundingClientRect().width, ww: w.clientWidth }; });
  check('bracket fits the width', fit.bw <= fit.ww + 2, JSON.stringify(fit));
  const cur = a.locator('#draw-view .match.is-cur');
  check('current bout blue „Dabar“', await cur.count() === 1 && /Dabar/.test(await cur.textContent()));
  check('next bout yellow „Ruošiasi“', await a.locator('#draw-view .match.is-prep').count() === 1 && /Ruošiasi/.test(await a.locator('#draw-view .match.is-prep').textContent()));
  check('colours', await a.evaluate(() => [getComputedStyle(document.querySelector('.match.is-cur')).borderTopColor, getComputedStyle(document.querySelector('.match.is-prep')).borderTopColor].join(' ')) === 'rgb(37, 99, 235) rgb(242, 183, 5)');
  const mid0 = await cur.locator('[data-bout]').getAttribute('data-bout');
  await cur.locator('.slot.aka button[data-m]').click();
  await a.waitForTimeout(400);
  check('after a winner the current bout moves on', (await a.locator('#draw-view .match.is-cur [data-bout]').first().getAttribute('data-bout').catch(() => null)) !== mid0);
  await saved(a);
  check('winner saved', !!JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)).categories.find((c) => c.id === big.id).draw.results[mid0]);
  await a.click('#run-more');
  check('⚙ opens the group settings', await a.locator('#run-tools').isVisible() && await a.locator('#draw-system').isVisible());
  await a.click('#run-more');

  // Esc - atgal į darbastalį
  await a.keyboard.press('Escape');
  await a.waitForTimeout(300);
  check('Esc leaves work mode', !(await a.evaluate(() => document.body.classList.contains('vz-focus'))) && await a.locator('.vz-top').isVisible() && await a.locator('#fx-bar').isHidden());

  // Telefonas: darbo režimas - „Paprastas“ vaizdas, dideli „Laimėjo“, be slinkimo į šoną
  const p = await login(browser, 'info@karateka.lt', 375, 812);
  await openDesk(p, tid);
  check('phone: finished groups folded, desk starts on the first screen', (await p.locator('#desk .dk-donesum, #desk .dk-row:not(.folded)').first().boundingBox()).y < 812);
  await p.click('#dk-focus');
  await p.waitForSelector('body.vz-focus #dk-run #run:not([hidden])');
  await p.click(`#fx-groups [data-fx-open="${big.id}"]`);
  await p.waitForTimeout(400);
  check('phone: simple view in work mode', await p.locator('[data-runview="simple"].on').count() === 1);
  const win = p.locator('#draw-view .sv-win').first();
  if (await win.count()) {
    const box = await win.boundingBox();
    check('phone: big winner button on screen', box && box.height >= 40 && box.y + box.height <= 812, JSON.stringify(box));
    await win.click();
    await saved(p);
  }
  check('phone: no sideways scroll', await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth && document.getElementById('dk-run').scrollWidth <= document.getElementById('dk-run').clientWidth + 1));
  check('phone: strip of groups fits', await p.evaluate(() => document.getElementById('fx-bar').getBoundingClientRect().height < 160));
  await p.click('.fx-exit');
  check('phone: ✕ leaves work mode', await p.locator('#fx-bar').isHidden() && await p.locator('#dk-head, .dk-head').first().isVisible());

  sql(`DELETE FROM tournaments WHERE id = ${tid}`);
  noJsErrors(a, p);
});
