// v1.86 (Denis): LTKF teisėjų registras - lankomumas (seminarai, teisėjavimas, egzaminai) ir diplomai (tik personalui).
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('Denis: judge register - attendance and diplomas; diplomas only for staff', async ({ browser }) => {
  sql("DELETE FROM ltkf_judges WHERE name LIKE 'E2E%'");
  sql("INSERT INTO ltkf_judges (name, club, category, license_no, valid_until) VALUES ('E2E Teisėjas Lankomumas', 'Karateka', 'nacionalinė', 'LT-77', '2030-01-01')");
  const jid = sql("SELECT id FROM ltkf_judges WHERE name = 'E2E Teisėjas Lankomumas'");
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto(`admin/federacija.php?skiltis=teisejai&j=${jid}`);
  check('attendance and diplomas sections', await a.locator('#lankomumas').isVisible() && await a.locator('#diplomai').isVisible());
  await a.fill('#lankomumas input[name=on_date]', '2026-10-12');
  await a.selectOption('#lankomumas select[name=kind]', 'seminar');
  await a.fill('#lankomumas input[name=title]', 'E2E teisėjų seminaras');
  await nav(a, () => a.click('#lankomumas .jd-add button[type=submit]'));
  check('attendance saved', sql(`SELECT CONCAT(on_date, '|', kind, '|', title) FROM ltkf_judge_events WHERE judge_id = ${jid}`) === '2026-10-12|seminar|E2E teisėjų seminaras');
  await a.fill('#diplomai input[name=title]', 'E2E diplomas');
  await a.setInputFiles('#diplomai input[name=file]', { name: 'diplomas.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 e2e diplomas') });
  await nav(a, () => a.click('#diplomai .jd-add button[type=submit]'));
  check('diploma listed', (await a.locator('#diplomai .jd-list').innerText()).includes('E2E diplomas'));
  const href = await a.locator('#diplomai .jd-list a').first().getAttribute('href');
  const r = await a.request.get('admin/federacija.php' + href);
  check('staff can download it', r.ok() && (await r.body()).toString().startsWith('%PDF'));
  const file = sql(`SELECT file FROM ltkf_judge_files WHERE judge_id = ${jid}`);
  const g = await login(browser, null, 1280, 900);
  const direct = await g.request.get('uploads/teisejai/' + file);
  check('file folder is locked for the browser (.htaccess on the real server)', require('fs').existsSync(require('path').join(__dirname, '../../public_html/uploads/teisejai/.htaccess')));
  const viaPage = await g.request.get('admin/federacija.php' + href, { maxRedirects: 0 });
  check('guest cannot download through the page', !viaPage.ok() || !(await viaPage.body()).toString().startsWith('%PDF'));
  await a.goto('admin/federacija.php?skiltis=teisejai');
  check('list shows attendance and diploma counts', /1/.test(await a.locator('tr', { hasText: 'E2E Teisėjas Lankomumas' }).innerText()));
  sql("DELETE FROM ltkf_judges WHERE name LIKE 'E2E%'");
  check('deleting the judge removes the records', sql(`SELECT COUNT(*) FROM ltkf_judge_events WHERE judge_id = ${jid}`) === '0');
  noJsErrors(a);
});
