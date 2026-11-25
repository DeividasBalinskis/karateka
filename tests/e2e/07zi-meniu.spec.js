// v2.02: Valdymo meniu - naujos grupės (Kasdien / Darbai / Nustatymai) ir administratoriaus „✎“:
// skyrius galima tempti į kitą grupę, pervadinti, kurti ir trinti grupes, grąžinti numatytą meniu. Treneris to nemato.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

const groupsOf = (p) => p.$$eval('.adm-side .am-group', gs => gs.map(g => ({ id: g.dataset.g, name: g.querySelector('.am-gh').textContent, items: [...g.querySelectorAll('.am-row')].map(r => r.dataset.k) })));

test('Valdymas menu: new groups, admin rearranges, renames, adds and deletes groups, resets', async ({ browser }) => {
  sql("DELETE FROM site_settings WHERE name = 'admin_menu'");
  const a = await login(browser, 'info@karateka.lt', 1440, 1400);
  // Peržiūros auksinės žymos (v2.02) šiame teste nereikalingos - paslepiamos, kad neuždengtų mygtukų
  await a.addInitScript(() => document.addEventListener('DOMContentLoaded', () => {
    const s = document.createElement('style'); s.textContent = '.pzr-ctl,.pzr-bar,.pzr-box{display:none !important}'; document.head.appendChild(s);
  }));
  await a.goto('admin/');
  let g = await groupsOf(a);
  check('default groups: Kasdien, Darbai, Nustatymai', g.map(x => x.id).join() === 'kasdien,darbai,nustatymai', JSON.stringify(g));
  check('daily work on top', g[0].items.slice(0, 5).join() === 'siandien,treniruote,egzaminai,renginiai,nariai', g[0].items.join());
  check('approvals and feedback in Darbai', g[1].items.includes('patvirtinimai') && g[1].items.includes('atsiliepimai'));
  check('activity points and settings at the bottom', g[2].items.includes('xp') && g[2].items[g[2].items.length - 1] === 'nustatymai');

  // ✎ → tempti „Aktyvumo taškai“ į Darbai, pervadinti grupę ir skyrių, nauja grupė su „Testai“
  await a.click('#am-edit');
  await a.waitForSelector('.am-ed');
  await a.locator('.am-ei[data-k="xp"] .am-handle').dragTo(a.locator('.am-eg[data-id="darbai"] .am-ei').first(), { targetPosition: { x: 12, y: 3 } });
  await a.fill('.am-eg[data-id="darbai"] .am-gin', 'Mano darbai');
  await a.fill('.am-ei[data-k="testai"] .am-in', 'Teorija');
  await a.click('.am-add');
  await a.fill('.am-eg:last-child .am-gin', 'Retai');
  await a.locator('.am-ei[data-k="testai"] .am-handle').dragTo(a.locator('.am-eg:last-child .am-el'));
  await Promise.all([a.waitForNavigation(), a.click('#am-save')]);
  g = await groupsOf(a);
  check('item dragged to another group', g[1].items[0] === 'xp', JSON.stringify(g[1]));
  check('group renamed', g[1].name === 'Mano darbai');
  check('new group with the dragged item', g.length === 4 && g[3].name === 'Retai' && g[3].items.join() === 'testai', JSON.stringify(g[3]));
  check('item renamed', (await a.textContent('.adm-side .am-row[data-k="testai"] .am-t')) === 'Teorija');

  // ↑ perkelia į ankstesnę grupę; ištrinta grupė - jos skyriai pereina į pirmą
  await a.click('#am-edit');
  await a.click('.am-ei[data-k="nariai"] .am-eb >> nth=1');   // ↓ - paskutinis Kasdien → pirmas Mano darbai
  await a.click('.am-eg:last-child .am-del');
  await Promise.all([a.waitForNavigation(), a.click('#am-save')]);
  g = await groupsOf(a);
  check('down arrow moves to the next group', g[1].items[0] === 'nariai', JSON.stringify(g[1]));
  check('deleted group: items go to the first group', g.length === 3 && g[0].items.includes('testai'), JSON.stringify(g));

  // Treneris: meniu toks pat, bet „✎“ nėra ir išsaugoti negali
  const c = await login(browser, 'treneris@karateka.test', 1440, 900);
  await c.goto('admin/');
  check('coach sees the arranged menu', (await groupsOf(c))[1].name === 'Mano darbai');
  check('coach has no edit button', await c.locator('#am-edit').count() === 0);
  const r = await c.request.post('admin/meniu.php', { data: { reset: true } });
  check('coach cannot save the menu', r.status() === 403, String(r.status()));

  // Numatytas meniu
  await a.click('#am-edit');
  await Promise.all([a.waitForNavigation(), a.click('#am-reset')]);
  g = await groupsOf(a);
  check('reset to default', g.map(x => x.id).join() === 'kasdien,darbai,nustatymai' && g[2].items.includes('xp') && !(await a.locator('.am-row[data-k="testai"]').textContent()).includes('Teorija'));
  check('setting cleared', sql("SELECT IFNULL(MAX(value), '') FROM site_settings WHERE name = 'admin_menu'") === '');
  noJsErrors(a, c);
});
