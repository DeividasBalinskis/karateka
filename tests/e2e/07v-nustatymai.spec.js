// v1.81 C2: Visi nustatymai - registras, žemėlapis, pakeitimų žurnalas su „Atkurti“.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('C2: settings map lists every switch; a change is logged and can be restored', async ({ browser }) => {
  sql("DELETE FROM site_settings WHERE name = 'ltkf_mode'"); sql("DELETE FROM settings_log WHERE name = 'ltkf_mode'");
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto('admin/nustatymai.php');
  check('Nustatymai links to Visi nustatymai', await a.locator('a[href$="admin/nustatymai-visi.php"]').count() >= 1);
  await a.goto('admin/ltkf-perziura.php');
  await a.check('#busena input[name=mode][value=public]');
  await Promise.all([a.waitForNavigation(), a.click('#busena button[type=submit]')]);
  await a.goto('admin/nustatymai-visi.php');
  check('map shows the LTKF state with a link', (await a.locator('.set-map li', { hasText: 'ltkf_mode' }).innerText()).includes('public'));
  check('server items shown read-only', (await a.locator('.panel', { hasText: 'config.php' }).innerText()).includes('env'));
  check('no unknown settings', await a.locator('text=Nežinomi nustatymai').count() === 0);
  const row = a.locator('#zurnalas li', { hasText: 'LTKF svetainės būsena' }).first();
  check('change logged with who', (await row.innerText()).includes('public'));
  await nav(a, () => row.locator('button').click());
  check('restored to default (removed)', sql("SELECT COUNT(*) FROM site_settings WHERE name = 'ltkf_mode'") === '0');
  check('restore logged too', +sql("SELECT COUNT(*) FROM settings_log WHERE name = 'ltkf_mode'") === 2);
  const s = await login(browser, 'info@karateka.lt', 375, 800);
  await s.goto('admin/nustatymai-visi.php');
  check('no sideways scroll at 375', await s.evaluate(() => document.documentElement.scrollWidth <= 375));
  noJsErrors(a, s);
});
