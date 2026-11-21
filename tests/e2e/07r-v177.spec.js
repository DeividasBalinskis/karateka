// v1.77 (#160): LTKF registrai - klubai, teisėjai, nariai (Valdymas → Federacijos registrai); LTKF svetainė rodo klubus ir teisėjus iš čia.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('#160: registers - add a club, a judge and a member; LTKF pages show the club and the judge', async ({ browser }) => {
  sql("DELETE FROM ltkf_judges WHERE name LIKE 'E2E%'");
  sql("DELETE FROM ltkf_clubs WHERE name LIKE 'E2E%'");
  sql("DELETE FROM federation_people WHERE name LIKE 'E2E%'");
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/');
  check('menu: Federacijos registrai', await a.locator('.adm-side a[href$="admin/federacija.php"]').count() === 1);
  await a.goto('admin/federacija.php?skiltis=klubai');
  check('clubs seeded from the LTKF draft', await a.locator('table.cards tbody tr').count() >= 5);
  await a.fill('#forma input[name=name]', 'E2E Kauno dojo'); await a.fill('#forma input[name=city]', 'Kaunas'); await a.fill('#forma input[name=coach]', 'E2E Treneris');
  await nav(a, () => a.click('#forma button[type=submit]'));
  check('club saved', sql("SELECT COUNT(*) FROM ltkf_clubs WHERE name = 'E2E Kauno dojo' AND city = 'Kaunas'") === '1');
  await a.goto('admin/federacija.php?skiltis=teisejai');
  await a.fill('#forma input[name=name]', 'E2E Jonas Teisėjauskas'); await a.fill('#forma input[name=club]', 'E2E Kauno dojo');
  await a.fill('#forma input[name=category]', 'nacionalinė'); await a.fill('#forma input[name=valid_until]', '2030-12-31');
  await nav(a, () => a.click('#forma button.btn-primary[type=submit]'));
  check('judge saved', sql("SELECT COUNT(*) FROM ltkf_judges WHERE name = 'E2E Jonas Teisėjauskas'") === '1');
  await a.goto('admin/federacija.php');
  await a.click('.fed-add > summary');
  await a.fill('.fed-add input[name=name]', 'E2E Narys Registre'); await a.fill('.fed-add input[name=club]', 'E2E Kauno dojo'); await a.fill('.fed-add input[name=grade]', '5 kyu');
  await nav(a, () => a.click('.fed-add button[type=submit]'));
  check('member added with a federation number', /^\d\d\.\d\d\.\d\d-\d+$/.test(sql("SELECT fed_no FROM federation_people WHERE name = 'E2E Narys Registre'")));
  const g = await login(browser, null, 1280, 900);
  await g.goto('ltkf/klubai.php');
  check('LTKF clubs page shows the new club', await g.locator('text=E2E Kauno dojo').count() >= 1);
  await g.goto('ltkf/teisejai.php');
  check('LTKF judges page shows the judge with category', await g.locator('table.judges tr', { hasText: 'E2E Jonas Teisėjauskas' }).count() === 1 && /nacionalinė/.test(await g.locator('table.judges tr', { hasText: 'E2E Jonas' }).textContent()));
  sql("DELETE FROM ltkf_judges WHERE name LIKE 'E2E%'");
  sql("DELETE FROM ltkf_clubs WHERE name LIKE 'E2E%'");
  sql("DELETE FROM federation_people WHERE name LIKE 'E2E%'");
  noJsErrors(a, g);
});
