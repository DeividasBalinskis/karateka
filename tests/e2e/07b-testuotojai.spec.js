// v1.49 (planas A4): testuotojai. Tikri administratoriai - Denis ir Deividas; kiti administratoriai - testuotojai (db/044):
// ženkliukas „🧪 Testuotojas“, gali tiek, kiek treneris, nustatymus mato tik skaityti, Dojo ir žaidimo nemato.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

const EMAIL = 'testuotojas@karateka.test';
function makeTester(on = 1) {
  sql(`DELETE FROM accounts WHERE email = '${EMAIL}'`);
  sql(`INSERT INTO accounts (email, password_hash, first_name, last_name, role, status, email_verified_at, approved_at, is_tester)
       SELECT '${EMAIL}', password_hash, 'Kostas', 'Testuotojas', 'admin', 'active', NOW(), NOW(), ${on} FROM accounts WHERE email = 'info@karateka.lt'`);
  return +sql(`SELECT id FROM accounts WHERE email = '${EMAIL}'`);
}

test('tester: badge, coach powers only, read-only settings, no Dojo even when the game is open to all', async ({ browser }) => {
  makeTester(1);
  sql("INSERT INTO site_settings (name, value) VALUES ('game_access', 'all') ON DUPLICATE KEY UPDATE value = 'all'");
  const t = await login(browser, EMAIL, 1440, 900);
  await t.goto('admin/');
  check('badge next to the name', /Testuotojas/.test(await t.textContent('h1')));
  check('no Dojo in the menu', await t.locator('.nav-game').count() === 0);
  await t.goto('dojo.php');
  check('Dojo page closed', !t.url().includes('dojo.php'), t.url());
  const api = await t.request.get('zaidimas.php?invites=1');
  check('game API closed', api.status() === 403);

  for (const p of ['admin/paskyros.php', 'admin/kopijos.php', 'admin/pranesimai.php', 'admin/atsiliepimai.php']) {
    const r = await t.request.get(p, { maxRedirects: 0 });
    check(`no access: ${p}`, r.status() === 403 || r.status() === 302, String(r.status()));
  }
  await t.goto('admin/treniruote.php');
  check('coach pages open', t.url().includes('admin/treniruote.php'));

  await t.goto('admin/nustatymai.php');
  check('settings: read-only note', await t.locator('.ro-note').isVisible());
  check('settings: switches disabled', await t.locator('#registracija input[type=checkbox]').first().isDisabled());
  check('settings: game section hidden', await t.locator('#zaidimas').count() === 0);
  // Tiesioginis POST - nepakeičia
  const before = sql("SELECT COALESCE(MAX(value), '') FROM site_settings WHERE name = 'reg_off'");
  await t.evaluate(async () => {
    const f = new FormData(); f.append('_csrf', document.querySelector('input[name=_csrf]').value); f.append('action', 'reg_toggles');
    await fetch(location.href, { method: 'POST', body: f, credentials: 'same-origin' });
  });
  check('settings: a tester cannot change them', sql("SELECT COALESCE(MAX(value), '') FROM site_settings WHERE name = 'reg_off'") === before);
  noJsErrors(t);
});

test('admin: tester switch in Paskyros; a real admin sees the game even when it is hidden', async ({ browser }) => {
  const id = makeTester(0);
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto(`admin/paskyros.php?q=${encodeURIComponent('Kostas')}`);
  const row = a.locator('tr', { hasText: EMAIL });
  await Promise.all([a.waitForNavigation(), row.locator('.tester-tg button').click()]);
  check('switched on', sql(`SELECT is_tester FROM accounts WHERE id = ${id}`) === '1');
  check('badge shown in the list', /Testuotojas ✓/.test(await a.locator('tr', { hasText: EMAIL }).textContent()));
  await Promise.all([a.waitForNavigation(), a.locator('tr', { hasText: EMAIL }).locator('.tester-tg button').click()]);
  check('switched off', sql(`SELECT is_tester FROM accounts WHERE id = ${id}`) === '0');

  // Žaidimas paslėptas (sąraše - niekas): tikras administratorius vis tiek mato
  sql("INSERT INTO site_settings (name, value) VALUES ('game_access', '[]') ON DUPLICATE KEY UPDATE value = '[]'");
  await a.goto('dojo.php');
  check('real admin sees the game', a.url().includes('dojo.php'), a.url());
  const c = await login(browser, 'treneris@karateka.test', 1280, 900);
  await c.goto('dojo.php');
  check('coach does not', !c.url().includes('dojo.php'), c.url());
  sql("UPDATE site_settings SET value = 'all' WHERE name = 'game_access'");
  sql(`DELETE FROM accounts WHERE id = ${id}`);
  noJsErrors(a, c);
});
