// v1.45: valdymo meniu variantai (#106): peržiūra ?meniu=..., „Visiems“ nustatymuose; ne daugiau 2 lygių; rodyklės ir „Žiūrėti kaip“ (#104, #107)
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('menu variants: preview, choose for everyone, max two levels', async ({ browser }) => {
  sql("DELETE FROM site_settings WHERE name = 'menu_variant'");
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/taskai.php');
  check('current: second row in settings', await a.locator('.sub-nav-2').count() === 1);
  // Viena eilutė + „Daugiau“
  await a.goto('admin/taskai.php?meniu=eilute');
  check('one row', await a.locator('.sub-nav').count() === 1 && await a.locator('.sub-nav-2').count() === 0);
  await a.click('.am-more > summary');
  check('more list has the rare sections', await a.locator('.am-more-panel a[href*="atsiliepimai.php"]').count() === 1 && await a.locator('.am-more > summary.active').count() === 1);
  // Šoninis meniu
  await a.goto('admin/nariai.php?meniu=sonas');
  check('side menu, no rows', await a.locator('.adm-side .am-row').count() >= 8 && await a.locator('.sub-nav').count() === 0 && await a.locator('.adm-side .am-row.active', { hasText: 'Nariai' }).count() === 1);
  // Didelis meniu
  await a.goto('admin/testai.php?meniu=didelis');
  check('no rows, crumb', await a.locator('.sub-nav').count() === 0 && /Testai/.test(await a.textContent('.am-crumb')));
  await a.click('.am-mega > summary');
  check('mega menu with groups', await a.locator('.am-mega-panel .am-group').count() === 3);
  await a.goto('naujienos.php');
  check('mega menu works on site pages too', await a.locator('.am-mega').count() === 1);
  // Viena juosta
  await a.goto('admin/?meniu=juosta');
  check('one bar: admin links in the top bar', await a.locator('.am-jlinks a').count() >= 4 && await a.locator('.am-site').count() === 1 && await a.locator('.sub-nav').count() === 0);
  // Visiems: nustatymuose
  await a.goto('admin/nustatymai.php?meniu=visiems');
  await nav(a, () => a.locator('#meniu .mp-row', { hasText: 'Šoninis meniu' }).locator('button').click());
  check('saved for everyone', sql("SELECT value FROM site_settings WHERE name = 'menu_variant'") === 'sonas');
  const c = await login(browser, 'info@karateka.lt', 1280, 800);
  await c.goto('admin/');
  check('other browser sees the chosen menu', await c.locator('.adm-side').count() === 1);
  // „Žiūrėti kaip“ - vienas langas su „Kaip narys“ viduje (#107)
  await c.click('.adm-li .view-switch > summary');
  check('view menu: one panel with the member button inside', await c.locator('.adm-li .vs-panel .vs-member-btn').count() === 1);
  sql("DELETE FROM site_settings WHERE name = 'menu_variant'");
  noJsErrors(a, c);
});
