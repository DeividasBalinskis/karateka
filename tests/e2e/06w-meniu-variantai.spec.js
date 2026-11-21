// v1.45: valdymo meniu (#106): numatytas B+C - kompiuteryje šoninis, telefone viena eilutė su „Daugiau ▾“; galima B arba C atskirai
// v1.45 → B9 (v1.74): vienas valdymo išdėstymas - kompiuteryje šoninis meniu, telefone apatinė juosta; ne daugiau 2 lygių; „Žiūrėti kaip“ viename lange (#107)
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('menu (B9): one layout - side menu on computer, bottom bar on phone; variants gone; Renginiai and LTKF in the menu; editors keep the side menu', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/taskai.php');
  check('computer: side menu visible, phone row hidden', await a.locator('.adm-side').isVisible() && !(await a.locator('.am-line').isVisible()));
  check('side menu: groups, current section', await a.locator('.adm-side .am-group').count() === 3 && await a.locator('.adm-side a.active', { hasText: 'Aktyvumo taškai' }).count() === 1);
  check('no second row anywhere', await a.locator('.sub-nav-2').count() === 0);
  await a.goto('admin/nariai.php?meniu=eilute');
  check('old preview links do nothing: still the side menu', await a.locator('.adm-side').isVisible() && !(await a.locator('.am-line').isVisible()));
  await a.goto('admin/nustatymai.php');
  check('no menu variant choice in settings', await a.locator('#meniu, #tmeniu').count() === 0);
  check('Renginiai and LTKF in the side menu', await a.locator('.adm-side a[href$="admin/renginiai.php"]').count() === 1 && await a.locator('.adm-side a[href$="ltkf/"]').count() === 1);
  await a.click('.adm-side a[href$="admin/renginiai.php"]');
  await a.waitForSelector('.aev-list, .panel');
  check('events list inside Valdymas, menu item active', await a.locator('.adm-side a.active', { hasText: 'Renginiai' }).count() === 1 && await a.locator('h1', { hasText: 'Renginiai' }).count() === 1);
  await a.goto('admin/renginiai.php?edit=new&back=admin');
  check('event editor keeps the side menu', await a.locator('.adm-side').isVisible());
  await a.goto('admin/naujienos.php?edit=new');
  check('news editor keeps the side menu', await a.locator('.adm-side').isVisible());
  const p = await login(browser, 'info@karateka.lt', 390, 844);
  await p.goto('admin/taskai.php?tmeniu=dabar');
  check('phone: bottom bar, no row, no side menu (old preview ignored)', await p.locator('.m-tabbar').isVisible() && !(await p.locator('.am-line').isVisible()) && !(await p.locator('.adm-side').isVisible()));
  check('phone: no horizontal scroll', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
  // „Žiūrėti kaip“ - vienas langas su „Kaip narys“ viduje (#107)
  await a.goto('admin/');
  await a.click('.adm-li .view-switch > summary');
  check('view menu: one panel with the member button inside', await a.locator('.adm-li .vs-panel .vs-member-btn').count() === 1);
  noJsErrors(a, p);
});
