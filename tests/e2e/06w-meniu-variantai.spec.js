// v1.45: valdymo meniu (#106): numatytas B+C - kompiuteryje šoninis, telefone viena eilutė su „Daugiau ▾“; galima B arba C atskirai
// (peržiūra ?meniu=..., „Visiems“ nustatymuose); ne daugiau 2 lygių; „Žiūrėti kaip“ viename lange (#107)
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('menu: side menu on computer, one row with More on phone, B or C for everyone', async ({ browser }) => {
  sql("DELETE FROM site_settings WHERE name = 'menu_variant'");
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/taskai.php');
  check('computer: side menu visible, phone row hidden', await a.locator('.adm-side').isVisible() && !(await a.locator('.am-line').isVisible()));
  check('side menu: groups, current section', await a.locator('.adm-side .am-group').count() === 3 && await a.locator('.adm-side a.active', { hasText: 'Aktyvumo taškai' }).count() === 1);
  check('no second row anywhere', await a.locator('.sub-nav-2').count() === 0);
  const p = await login(browser, 'info@karateka.lt', 390, 844);
  await p.goto('admin/taskai.php');
  check('phone: one row, no side menu', await p.locator('.am-line').isVisible() && !(await p.locator('.adm-side').isVisible()));
  await p.click('.am-line .am-more > summary');
  check('phone: More list with rare sections, current shown', await p.locator('.am-more-panel a[href*="testai.php"]').isVisible() && await p.locator('.am-more > summary.active').count() === 1);
  check('phone: no horizontal scroll', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
  // Tik B (eilutė) - ir kompiuteryje
  await a.goto('admin/nariai.php?meniu=eilute');
  check('B: row on computer, no side menu', await a.locator('.am-line').isVisible() && await a.locator('.adm-side').count() === 0);
  // Tik C (šoninis) - ir telefone (slenkama juostelė)
  await p.goto('admin/nariai.php?meniu=sonas');
  check('C: side menu also on phone', await p.locator('.adm-side').isVisible() && await p.locator('.am-line').count() === 0);
  // Visiems: nustatymuose
  await a.goto('admin/nustatymai.php?meniu=visiems');
  check('three choices', await a.locator('#meniu .mp-row').count() === 3);
  await nav(a, () => a.locator('#meniu .mp-row', { hasText: 'Viena eilutė' }).locator('button').click());
  check('saved for everyone', sql("SELECT value FROM site_settings WHERE name = 'menu_variant'") === 'eilute');
  const c = await login(browser, 'info@karateka.lt', 1280, 800);
  await c.goto('admin/');
  check('other browser sees the chosen menu', await c.locator('.am-line').isVisible() && await c.locator('.adm-side').count() === 0);
  // „Žiūrėti kaip“ - vienas langas su „Kaip narys“ viduje (#107)
  await c.click('.adm-li .view-switch > summary');
  check('view menu: one panel with the member button inside', await c.locator('.adm-li .vs-panel .vs-member-btn').count() === 1);
  sql("DELETE FROM site_settings WHERE name = 'menu_variant'");
  await p.goto('admin/?meniu=visiems');
  noJsErrors(a, p, c);
});
