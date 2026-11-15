const { test } = require('@playwright/test');
const { sql, login } = require('./helpers');
test('shots', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1300, 950);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.click('#tab-live');
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  await a.screenshot({ path: '/tmp/claude-0/qa2/dbg/ask.png' });
  await a.click('#area-pick [data-my-area="1"]');
  await a.screenshot({ path: '/tmp/claude-0/qa2/dbg/mine.png' });
  await a.click('[data-start-run="1"]');
  await a.locator('#run-list').scrollIntoViewIfNeeded();
  await a.screenshot({ path: '/tmp/claude-0/qa2/dbg/list.png' });
  // kumite bout
  const rows = a.locator('#run-list .rl-row button');
  for (let i = 0; i < await rows.count(); i++) { if (/Kumite/.test(await rows.nth(i).innerText()) && !/Užbaigta/.test(await rows.nth(i).innerText())) { await rows.nth(i).click(); break; } }
  const b = a.locator('#draw-view [data-bout]').first();
  console.log('bouts', await b.count());
  if (await b.count()) { await b.click(); await a.waitForSelector('.bt-presets'); await a.screenshot({ path: '/tmp/claude-0/qa2/dbg/bout.png' }); await a.click('#bt-close'); }
  // awards
  sql(`INSERT IGNORE INTO tournament_awards (tournament_id, cat_id, status, sent_at) SELECT ${tid}, 'x', 'sent', NOW()`);
  const token = sql(`SELECT awards_token FROM tournaments WHERE id = ${tid}`);
  const p = await browser.newPage({ viewport: { width: 820, height: 1100 } });
  await p.goto(`varzybos-apdovanojimai.php?k=${token}`);
  const data = await (await p.request.get(`varzybos-apdovanojimai.php?k=${token}&json=1`)).json();
  const fin = data.cats.filter(c => c.finished);
  const g = fin[0].group;
  data.cats.filter(c => c.group === g).forEach(c => sql(`INSERT IGNORE INTO tournament_awards (tournament_id, cat_id, status, sent_at) VALUES (${tid}, '${c.id}', 'sent', NOW())`));
  if (fin[3]) sql(`INSERT IGNORE INTO tournament_awards (tournament_id, cat_id, status, sent_at) VALUES (${tid}, '${fin[3].id}', 'sent', NOW())`);
  await p.waitForSelector('.apd-g.ready', { timeout: 8000 });
  await p.screenshot({ path: '/tmp/claude-0/qa2/dbg/apd.png' });
  sql('DELETE FROM tournaments WHERE is_test = 1');
});
