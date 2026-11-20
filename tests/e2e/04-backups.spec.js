// Atsarginės kopijos: adminas sukuria ir atsisiunčia, kopija pilna (atkuriama), treneriui puslapis uždraustas.
const { test, expect } = require('@playwright/test');
const zlib = require('zlib');
const fs = require('fs');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('admin creates and downloads a full backup', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt');
  await a.goto('admin/kopijos.php');
  await nav(a, () => a.click('button[value=create]'));
  check('created', /Kopija sukurta/.test(await a.locator('.flash').allTextContents().then(t => t.join(' '))));
  const [download] = await Promise.all([a.waitForEvent('download'), a.locator('.backup-list a').first().click()]);
  const file = await download.path();
  const dump = zlib.gunzipSync(fs.readFileSync(file)).toString();
  expect(dump).toContain('-- pabaiga');
  const tables = sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'");
  expect((dump.match(/^CREATE TABLE/gm) || []).length).toBe(+tables);
  // Skaičiuojami stulpeliai nerašomi (kitaip griežtame serveryje importas nepavyktų)
  expect(dump).toMatch(/INSERT INTO `account_members` \(`account_id`, `member_id`, `relation`, `created_at`\)/);
  check('system info', /Importuoti db failai/.test(await a.content()));
  const bad = await a.request.get('admin/kopijos.php?atsisiusti=../config.php');
  check('no path traversal', bad.status() === 404, bad.status());
  noJsErrors(a);
});

test('coach cannot open backups', async ({ browser }) => {
  const c = await login(browser, 'treneris@karateka.test');
  const r = await c.goto('admin/kopijos.php');
  expect(r.status()).toBe(403);
  const d = await c.request.get('admin/kopijos.php?atsisiusti=x');
  expect(d.status()).toBe(403);
});
