// v1.79 C10: Duomenų priežiūra (kas trinama automatiškai; ką galima ištrinti - pirmiau sąrašas), nuotraukų .zip kopija, slapukų sąrašas.
const { test } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('C10: data care lists before deleting; former member deleted only after confirm; orphan file listed; photo zip; cookie list', async ({ browser }) => {
  sql("DELETE FROM members WHERE last_name = 'Buvęs-E2E'");
  sql("INSERT INTO members (first_name, last_name, birth_date, status, created_at) VALUES ('Senas', 'Buvęs-E2E', '2010-01-01', 'inactive', NOW() - INTERVAL 2 YEAR)");
  const orphan = path.join(__dirname, '../../public_html/uploads/news/e2e-orphan-test.jpg');
  fs.writeFileSync(orphan, 'x');
  const old = new Date(Date.now() - 3 * 86400000); fs.utimesSync(orphan, old, old);

  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto('admin/nustatymai.php');
  check('Nustatymai links to Duomenų priežiūra', await a.locator('a[href$="admin/duomenys.php"]').count() >= 1);
  await a.goto('admin/duomenys.php');
  check('former member is listed', (await a.locator('#former_members').innerText()).includes('Senas Buvęs-E2E') || await a.locator('#former_members details').count() === 1);
  await a.locator('#former_members summary').click();
  check('list shows the name before deleting', await a.locator('#former_members .care-names', { hasText: 'Buvęs-E2E' }).count() === 1);
  check('still in the database until pressed', sql("SELECT COUNT(*) FROM members WHERE last_name = 'Buvęs-E2E'") === '1');
  await a.locator('#orphan_files summary').click();
  check('orphan file listed', (await a.locator('#orphan_files').innerText()).includes('e2e-orphan-test.jpg') || +(await a.locator('#orphan_files .badge').innerText()) > 60);
  check('automatic rules shown', await a.locator('.care-auto li').count() >= 6);
  await nav(a, () => a.click('#former_members button.btn-danger'));   // patvirtinimas - dialog.accept()
  check('former member deleted after confirm', sql("SELECT COUNT(*) FROM members WHERE last_name = 'Buvęs-E2E'") === '0');
  check('orphan file not touched', fs.existsSync(orphan));
  fs.unlinkSync(orphan);

  await a.goto('admin/kopijos.php');
  check('photo backup offered', await a.locator('a[href$="admin/kopijos.php?failai=1"]').count() === 1);
  const r = await a.request.get('admin/kopijos.php?failai=1');
  check('photo zip downloads', r.ok() && (r.headers()['content-type'] || '').includes('zip') && (await r.body()).length > 100);

  const g = await login(browser, null, 375, 800);
  await g.goto('privatumas.php');
  check('privacy page lists cookies', await g.locator('.cookie-list li').count() === 5);
  check('no sideways scroll at 375', await g.evaluate(() => document.documentElement.scrollWidth <= 375));
  noJsErrors(a, g);
});
