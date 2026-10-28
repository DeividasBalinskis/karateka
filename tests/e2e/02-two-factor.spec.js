// Prisijungimas su kodu el. paštu: įjungimas, patikimas įrenginys, naujas įrenginys, bandymų riba, išjungimas.
// Kompiuteryje (env = local) laiškai nesiunčiami - kodas rodomas puslapyje (.dev-code).
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

const EMAIL = 'info@karateka.lt';

async function loginForm(page, email) {
  await page.goto('prisijungti.php');
  await page.fill('input[name=email]', email);
  await page.fill('input[name=password]', 'testas123');
  await nav(page, () => page.click('button[type=submit]'));
}

test.beforeAll(() => {
  sql(`UPDATE accounts SET two_factor = 0 WHERE email = '${EMAIL}'`);
  sql('DELETE FROM login_codes');
  sql('DELETE FROM trusted_devices');
});
test.afterAll(() => {
  sql(`UPDATE accounts SET two_factor = 0 WHERE email = '${EMAIL}'`);
});

test('enable, trusted device, new device with code, limits, disable', async ({ browser }) => {
  const p = await login(browser, EMAIL);
  await p.goto('admin/#tfa');
  await p.click('#tfa summary');
  await p.fill('#tfa input[name=current]', 'blogas');
  await nav(p, () => p.click('#tfa button:has-text("Siųsti kodą")'));
  check('wrong password', /Neteisingas slaptažodis/.test(await p.content()));
  await p.fill('#tfa input[name=current]', 'testas123');
  await nav(p, () => p.click('#tfa button:has-text("Siųsti kodą")'));
  check('code sent', /Išsiuntėme kodą/.test(await p.content()));
  check('setting open after redirect', await p.locator('#tfa[open]').count() === 1);
  await p.fill('#tfa input[name=code]', '000000');
  await nav(p, () => p.click('#tfa button:has-text("Patvirtinti")'));
  check('wrong code counted', /Liko bandymų: 4/.test(await p.content()));
  const code = (await p.locator('#tfa .dev-code').textContent()).trim();
  await p.fill('#tfa input[name=code]', code.slice(0, 3) + ' ' + code.slice(3));
  await nav(p, () => p.click('#tfa button:has-text("Patvirtinti")'));
  check('enabled', sql(`SELECT two_factor FROM accounts WHERE email = '${EMAIL}'`) === '1');
  check('this device trusted', +sql('SELECT COUNT(*) FROM trusted_devices') === 1);
  check('device listed', await p.locator('#tfa .device-list li').count() === 1);

  // Tas pats įrenginys (slapukas lieka, sesija - nauja): kodo neprašoma
  const ctx = p.context();
  const keep = (await ctx.cookies()).filter(c => c.name !== 'karateka_sid');
  await ctx.clearCookies();
  await ctx.addCookies(keep);
  await loginForm(p, EMAIL);
  check('trusted device: no code', !p.url().includes('kodas.php'), p.url());

  // Naujas įrenginys: reikia kodo
  const q = await login(browser, null, 390, 844);
  await loginForm(q, EMAIL);
  check('new device: code page', q.url().includes('kodas.php'), q.url());
  check('no wait error right after enabling', !/po minutės/.test(await q.content()));
  check('not logged in before code', (await q.request.get('admin/')).url().includes('prisijungti'));
  for (let i = 0; i < 5; i++) {
    await q.fill('input[name=code]', '111111');
    await nav(q, () => q.click('button[type=submit]:has-text("Prisijungti")'));
  }
  check('5 wrong codes: expired', /nebegalioja/.test(await q.content()));
  await nav(q, () => q.click('button[value=resend]'));
  check('resend throttled', /po minutės/.test(await q.content()));
  sql('UPDATE login_codes SET created_at = created_at - INTERVAL 2 MINUTE');
  await nav(q, () => q.click('button[value=resend]'));
  check('resend ok', /Naują kodą išsiuntėme/.test(await q.content()));
  const code2 = (await q.locator('.dev-code').textContent()).trim();
  await q.uncheck('input[name=trust]');
  await q.fill('input[name=code]', code2);
  await nav(q, () => q.click('button[type=submit]:has-text("Prisijungti")'));
  check('logged in with code', /\/admin\/|paskyra/.test(q.url()), q.url());
  check('not trusted when unchecked', +sql('SELECT COUNT(*) FROM trusted_devices') === 1);
  check('code used once', +sql('SELECT COUNT(*) FROM login_codes WHERE used_at IS NULL AND expires_at > NOW()') === 0);

  // Išjungti
  await q.goto('admin/#tfa');
  await q.click('#tfa summary');
  await q.fill('#tfa input[name=current]', 'testas123');
  await nav(q, () => q.click('#tfa button:has-text("Išjungti")'));
  check('disabled, devices forgotten', sql(`SELECT two_factor FROM accounts WHERE email = '${EMAIL}'`) === '0' && +sql('SELECT COUNT(*) FROM trusted_devices') === 0);

  const m = await login(browser, 'tevai@karateka.test');
  await m.goto('paskyra.php?skiltis=nustatymai');
  check('members can enable it too', await m.locator('#tfa').count() === 1);
  const r = await login(browser, null);
  await r.goto('kodas.php');
  check('code page without password step -> login', r.url().includes('prisijungti'));
  noJsErrors(p, q, m, r);
});
