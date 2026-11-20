// v1.53: „Kas naujo“ (versijos numeris), kodas kitam įrenginiui (vienkartinis, atjungiamas), vaiko prisijungimas vardu ir PIN (B7).
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

async function closeCelebrate(p) {
  for (let i = 0; i < 3 && await p.locator('#celebrate').count(); i++) { await p.click('#celebrate button'); await p.waitForTimeout(500); }
}

test('Kas naujo: staff click the version number and see the 3 newest versions; members do not get the link', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 375, 812);
  await Promise.all([a.waitForNavigation(), a.click('.ver-tag')]);
  check('opens Kas naujo', a.url().includes('admin/kas-naujo.php'), a.url());
  check('3 versions with a date', await a.locator('.kn-ver').count() === 3 && /^v\d+\.\d+ · \d{4}-\d{2}-\d{2}$/.test((await a.locator('.kn-ver h2').first().textContent()).trim()));
  const m = await login(browser, 'tevai@karateka.test', 375, 812);
  check('member: version tag is not a link', await m.locator('.ver-tag[role=link]').count() === 0);
  await m.goto('admin/kas-naujo.php');
  check('member: page closed', !(await m.locator('.kn-ver').count()));
  noJsErrors(a, m);
});

test('code for another device: one-time, wrong code refused, used once, disconnect logs the device out, members cannot create', async ({ browser }) => {
  sql("DELETE FROM device_codes");
  sql("DELETE FROM login_attempts WHERE email = '#irenginys'");
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/nustatymai.php');
  await Promise.all([a.waitForNavigation(), a.click('a.settings-link:has-text("Kodas kitam įrenginiui")')]);
  await a.fill('input[name=label]', 'E2E planšetė');
  await Promise.all([a.waitForNavigation(), a.click('.dc-form button[type=submit]')]);
  const code = (await a.locator('#dcCode').textContent()).trim();
  check('code shown once, 8 signs', /^[A-Z2-9]{4}-[A-Z2-9]{4}$/.test(code));
  check('only the hash is stored', sql("SELECT COUNT(*) FROM device_codes WHERE code_hash = SHA2(CONCAT('device:', '" + code.replace('-', '') + "'), 256)") === '1');

  const d = await login(browser, null, 375, 812);
  await d.goto('prisijungti.php');
  await Promise.all([d.waitForNavigation(), d.click('text=Turiu kodą iš administratoriaus')]);
  await d.fill('input[name=code]', 'AAAA-2222');
  await Promise.all([d.waitForNavigation(), d.click('form button[type=submit]')]);
  check('wrong code refused', await d.locator('.flash-err').count() === 1);
  await d.fill('input[name=code]', code.toLowerCase());
  await Promise.all([d.waitForNavigation(), d.click('form button[type=submit]')]);
  check('device logged in as admin, lands on Valdymas', d.url().includes('/admin/') && await d.locator('.dc-bar').count() === 1, d.url());

  const r = await login(browser, null, 375, 812);
  await r.goto('irenginys.php');
  await r.fill('input[name=code]', code);
  await Promise.all([r.waitForNavigation(), r.click('form button[type=submit]')]);
  check('same code does not work twice', await r.locator('.flash-err').count() === 1);

  await a.goto('admin/kitas-irenginys.php');
  check('log shows the device', /E2E planšetė/.test(await a.locator('.dc-log').textContent()) && /Veikia iki/.test(await a.locator('.dc-log').textContent()));
  await Promise.all([a.waitForNavigation(), a.locator('.dc-row').first().locator('button:has-text("Atjungti")').click()]);
  await d.goto('admin/');
  check('disconnected device is logged out', d.url().includes('prisijungti.php'), d.url());

  const m = await login(browser, 'tevai@karateka.test', 375, 812);
  await m.goto('admin/kitas-irenginys.php');
  check('member cannot create codes', await m.locator('.dc-form').count() === 0);
  sql("DELETE FROM device_codes");
  noJsErrors(a, d, r, m);
});

test('B7: parents create a username + PIN for a child (7+), the child logs in, PIN change and turn off work', async ({ browser }) => {
  sql("DELETE FROM accounts WHERE email LIKE '%@vaikas.invalid'");
  sql("DELETE FROM login_attempts WHERE email LIKE '%@vaikas.invalid'");
  const mid = sql("SELECT m.id FROM members m JOIN account_members am ON am.member_id = m.id JOIN accounts a ON a.id = am.account_id WHERE a.email = 'tevai@karateka.test' AND am.relation = 'parent' AND TIMESTAMPDIFF(YEAR, m.birth_date, CURDATE()) BETWEEN 7 AND 10 LIMIT 1");
  const p = await login(browser, 'tevai@karateka.test', 375, 812);
  await p.goto(`paskyra.php?skiltis=nustatymai&m=${mid}`);
  await closeCelebrate(p);
  await p.click('#vaiko-prisijungimas > summary');
  check('username suggested from the first name', /^[a-z][a-z0-9.]{2,}$/.test(await p.inputValue('#vaiko-prisijungimas input[name=username]')));
  await p.fill('#vaiko-prisijungimas input[name=username]', 'e2e.vaikas');
  await p.fill('#vaiko-prisijungimas input[name=pin]', '4821');
  await Promise.all([p.waitForNavigation(), p.click('#vaiko-prisijungimas button[type=submit]')]);
  check('created, active without coach approval', sql("SELECT status FROM accounts WHERE email = 'e2e.vaikas@vaikas.invalid'") === 'active');
  check('parent sees the username', (await p.locator('#vaiko-prisijungimas .kl-user').textContent()).trim() === 'e2e.vaikas');

  const k = await login(browser, 'E2E.Vaikas', 375, 812, '4821');
  await closeCelebrate(k);
  check('child logs in with the username', k.url().includes('paskyra.php'), k.url());
  check('child words: Mano diena', /Mano diena/.test(await k.locator('.eyebrow').first().textContent()));
  await k.goto('paskyra.php?skiltis=nustatymai');
  check('child cannot change e-mail or password', await k.locator('input[name=email], input[name=password]').count() === 0 && await k.locator('text=Mano prisijungimas').count() === 1);
  const w = await login(browser, 'e2e.vaikas', 375, 812, '0000');
  check('wrong PIN refused', await w.locator('.flash-err').count() === 1);

  await p.fill('#vaiko-prisijungimas input[name=pin]', '7391');
  await Promise.all([p.waitForNavigation(), p.click('#vaiko-prisijungimas .kl-form button')]);
  const k2 = await login(browser, 'e2e.vaikas', 375, 812, '7391');
  check('new PIN works', !k2.url().includes('prisijungti.php'), k2.url());
  await Promise.all([p.waitForNavigation(), p.click('#vaiko-prisijungimas .kl-toggle button')]);
  const k3 = await login(browser, 'e2e.vaikas', 375, 812, '7391');
  check('turned off: child is told to ask parents', /paprašyk tėvų/.test(await k3.locator('.flash-err').textContent()));
  await k2.reload();
  check('turned off: open session ends', k2.url().includes('prisijungti.php'), k2.url());
  sql("DELETE FROM accounts WHERE email LIKE '%@vaikas.invalid'");
  noJsErrors(p, k, w);
});

test('B7: a parent adds themselves as a trainee; an adult member adds a child; parent switches the child chat off', async ({ browser }) => {
  sql("DELETE m FROM members m JOIN account_members am ON am.member_id = m.id JOIN accounts a ON a.id = am.account_id WHERE a.email = 'tevai@karateka.test' AND am.relation = 'self'");
  sql("DELETE FROM members WHERE first_name = 'E2E' AND last_name = 'Vaikas'");
  sql("DELETE FROM accounts WHERE email LIKE '%@vaikas.invalid'");
  const p = await login(browser, 'tevai@karateka.test', 375, 812);
  await p.goto('paskyra.php?skiltis=nustatymai');
  await closeCelebrate(p);
  check('parent sees „Treniruojuosi ir aš“', await p.locator('#as-pats').count() === 1);
  await p.click('#as-pats > summary');
  await p.selectOption('#as-pats select[name="birth_date[y]"]', '1985');
  await p.selectOption('#as-pats select[name="birth_date[m]"]', '3');
  await p.selectOption('#as-pats select[name="birth_date[d]"]', '14');
  await p.check('#as-pats input[name=photo_consent][value="0"]', { force: true });
  await Promise.all([p.waitForNavigation(), p.click('#as-pats button[type=submit]')]);
  check('parent is now a trainee too', sql("SELECT COUNT(*) FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE a.email = 'tevai@karateka.test' AND am.relation = 'self'") === '1');
  check('children stay', +sql("SELECT COUNT(*) FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE a.email = 'tevai@karateka.test' AND am.relation = 'parent'") >= 2);

  const n = await login(browser, 'narys@karateka.test', 375, 812);
  await n.goto('paskyra.php?skiltis=nustatymai');
  await closeCelebrate(n);
  check('adult member can add a child', await n.locator('#vaikai').count() === 1);
  await n.click('#vaikai > summary');
  await n.fill('#vaikai input[name=first_name]', 'E2E'); await n.fill('#vaikai input[name=last_name]', 'Vaikas');
  await n.selectOption('#vaikai select[name="birth_date[y]"]', '2016');
  await n.selectOption('#vaikai select[name="birth_date[m]"]', '5');
  await n.selectOption('#vaikai select[name="birth_date[d]"]', '2');
  await n.check('#vaikai input[name=photo_consent][value="0"]', { force: true });
  await Promise.all([n.waitForNavigation(), n.click('#vaikai button[type=submit]')]);
  check('child added to the adult member', sql("SELECT COUNT(*) FROM account_members am JOIN accounts a ON a.id = am.account_id JOIN members m ON m.id = am.member_id WHERE a.email = 'narys@karateka.test' AND am.relation = 'parent' AND m.last_name = 'Vaikas'") === '1');

  const mid = sql("SELECT id FROM members WHERE first_name = 'E2E' AND last_name = 'Vaikas'");
  await n.goto(`paskyra.php?skiltis=nustatymai&m=${mid}`);
  await n.click('#vaiko-prisijungimas > summary');
  await n.fill('#vaiko-prisijungimas input[name=username]', 'e2e.mazas');
  await n.fill('#vaiko-prisijungimas input[name=pin]', '5150');
  await Promise.all([n.waitForNavigation(), n.click('#vaiko-prisijungimas button[type=submit]')]);
  check('chat on by default', sql("SELECT chat_off FROM accounts WHERE email = 'e2e.mazas@vaikas.invalid'") === '0');
  await Promise.all([n.waitForNavigation(), n.locator('.kl-chat input[type=checkbox]').uncheck({ force: true })]);
  check('parent switched chat off', sql("SELECT chat_off FROM accounts WHERE email = 'e2e.mazas@vaikas.invalid'") === '1');
  const k = await login(browser, 'e2e.mazas', 375, 812, '5150');
  await closeCelebrate(k);
  check('child without chat sees no chat button', await k.locator('.chat-fab').count() === 0);
  sql("DELETE FROM accounts WHERE email LIKE '%@vaikas.invalid'");
  sql("DELETE FROM members WHERE first_name = 'E2E' AND last_name = 'Vaikas'");
  sql("DELETE m FROM members m JOIN account_members am ON am.member_id = m.id JOIN accounts a ON a.id = am.account_id WHERE a.email = 'tevai@karateka.test' AND am.relation = 'self'");
  noJsErrors(p, n, k);
});
