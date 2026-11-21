// v1.54 (B3): Budo telefone varžybų dieną - kovos langas telpa ekrane, plaukiojantys mygtukai paslėpti, patvirtinimai, TV be tatamio numerio.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors, BASE_URL } = require('./helpers');

test('B3: phone bout - pair and score buttons fit on 375×812, time section folds, floats hidden; confirmations; TV tatami picker', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 375, 812);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  check('creation confirmed on the competition page', /sukurtos/.test((await a.locator('.vz-flash').allTextContents()).join(' ')));
  await a.waitForTimeout(1500);
  check('header is low on a phone', await a.evaluate(() => document.querySelector('.vz-top').getBoundingClientRect().height) <= 160);
  const st = JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
  const kumite = st.categories.find(c => c.discipline === 'kumite' && !c.team && c.competitors.length >= 4);
  await a.click('#tab-live');
  await a.locator(`[data-open-cat="${kumite.id}"]`).first().click();
  await a.waitForTimeout(500);
  const bout = a.locator('#draw-view [data-bout]').first();
  await bout.scrollIntoViewIfNeeded(); await bout.click(); await a.waitForTimeout(500);
  const g = await a.evaluate(() => {
    const vh = innerHeight, r = s => [...document.querySelectorAll(s)].filter(x => x.offsetParent).map(x => x.getBoundingClientRect());
    const btns = r('.bout [data-pt]'), names = r('.bout .bt-name');
    return { vh, maxBtn: Math.max(...btns.map(b => b.bottom)), minSize: Math.min(...btns.map(b => Math.min(b.width, b.height))), names: names.length, nameBottom: Math.max(...names.map(n => n.bottom)),
      more: !!document.querySelector('.bt-more:not([open])'), fb: [...document.querySelectorAll('.fb, .chat-fab')].filter(x => getComputedStyle(x).display !== 'none').length,
      overflow: document.documentElement.scrollWidth - innerWidth };
  });
  check('pair and all score buttons visible without scrolling', g.names === 2 && g.maxBtn <= g.vh && g.nameBottom <= g.vh, JSON.stringify(g));
  check('score buttons at least 44 px', g.minSize >= 44, JSON.stringify(g));
  check('time presets folded on a phone', g.more, JSON.stringify(g));
  check('💡 / 💬 hidden during the bout', g.fb === 0, JSON.stringify(g));
  await a.click('.bt-more > summary');
  check('presets open on tap', await a.locator('.bt-more[open] [data-preset]').count() >= 2);
  await a.locator('.bt-side.aka [data-pt$="|2"]').click();
  check('folded state remembered after a score', await a.locator('.bt-more[open]').count() === 1);
  await a.click('#bt-close');

  const tok = sql(`SELECT share_token FROM tournaments WHERE id = ${tid}`);
  const v = await login(browser, null, 1440, 900);
  await v.goto(`varzybos-gyvai.php?k=${tok}&ekranas=1`);
  await v.waitForTimeout(2000);
  check('TV without a tatami number: big tatami buttons', await v.locator('.scr-pickt-btn').count() >= 2);
  await Promise.all([v.waitForNavigation(), v.locator('.scr-pickt-btn').first().click()]);
  check('picked tatami opens its screen', /tatami=/.test(v.url()) && await v.locator('.scr-stage').count() === 1);
  sql(`DELETE FROM tournaments WHERE id = ${tid}`);
  noJsErrors(a, v);
});
