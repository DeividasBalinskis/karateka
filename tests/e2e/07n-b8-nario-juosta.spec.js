// B8 (sprendimas 6a): nariams, tėvams ir vaikams telefone - apatinė juosta (4 skirtukai + ⋯ Daugiau); 🏯 Dojo - kampe.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

async function closeCelebrate(p) {
  for (let i = 0; i < 4 && await p.locator('#celebrate').count(); i++) { await p.click('#celebrate button').catch(() => {}); await p.waitForTimeout(300); }
}

test('B8 member: bottom tab bar on a phone, none on a computer; Dojo in the corner when the game is open', async ({ browser }) => {
  sql("INSERT INTO site_settings (name, value) VALUES ('game_access', 'all') ON DUPLICATE KEY UPDATE value = 'all'");
  const p = await login(browser, 'tevai@karateka.test', 375, 812);
  await p.goto('paskyra.php'); await closeCelebrate(p);
  check('phone: tab bar with 4 tabs + Daugiau', await p.locator('.mt-member').isVisible() && await p.locator('.mt-member > a').count() === 4 && await p.locator('.mt-member .m-more').count() === 1);
  check('phone: no ☰ and no account tab row', !(await p.locator('.burger').isVisible()) && !(await p.locator('.site-header .sub-nav').isVisible()));
  check('phone: Apžvalga is active', /Apžvalga/.test(await p.locator('.mt-member a.active').textContent()));
  check('phone: 🏯 in the corner', await p.locator('.m-dojo').isVisible());
  await p.click('.mt-member .m-more > summary');
  check('Daugiau: settings, parents, logout', await p.locator('.m-sheet a[href*="skiltis=nustatymai"]').count() >= 1 && await p.locator('.m-sheet .am-logout').isVisible());
  await p.click('.mt-member .m-more > summary');
  await Promise.all([p.waitForNavigation(), p.click('.mt-member a[href*="uzduotys.php"]')]);
  check('Užduotys tab opens and is active', p.url().includes('uzduotys.php') && /Užduotys/.test(await p.locator('.mt-member a.active').textContent()));
  const sw = await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth);
  check('no sideways scroll', sw);

  const d = await login(browser, 'tevai@karateka.test', 1440, 900);
  await d.goto('paskyra.php'); await closeCelebrate(d);
  check('computer: no tab bar', !(await d.locator('.mt-member').isVisible()));
  check('computer: Dojo is a corner button, not in the page list', await d.locator('.nav-account .nav-dojo').isVisible() && await d.locator('.nav-pages .nav-game').count() === 0);

  const a = await login(browser, 'info@karateka.lt', 375, 812);
  await a.goto('admin/');
  check('staff: no member tab bar', await a.locator('.mt-member').count() === 0);
  noJsErrors(p, d, a);
});
