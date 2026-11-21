// v1.78: C2 kalba paskyroje (prisijungus kitur - ta pati, laiškai ta kalba); C8 laiškų šablonai ir siuntimo eilė (Pranešimas nariams → Laiškų siuntimas).
// Laiškai lokaliai keliauja į SMTP 127.0.0.1:1025 (Mailpit ar testinis priėmėjas); be jo laiškas pažymimas „Nepavyko“ - testas tikrina abu atvejus.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, PASSWORD } = require('./helpers');

test('C2: chosen language is saved on the account and used after the next login', async ({ browser }) => {
  sql("UPDATE accounts SET lang = NULL WHERE email = 'narys@karateka.test'");
  const m = await login(browser, 'narys@karateka.test', 1280, 900);
  check('first login saves the current language', sql("SELECT lang FROM accounts WHERE email = 'narys@karateka.test'") === 'lt');
  await m.goto('kalba.php?l=en&b=/paskyra.php');
  check('language switch saved on the account', sql("SELECT lang FROM accounts WHERE email = 'narys@karateka.test'") === 'en');
  // Kitas „įrenginys“ - be slapuko; prisijungiama ranka (helpers.login kalbą paskyroje nustato iš naujo)
  const m2 = await login(browser, null, 1280, 900);
  await m2.goto('prisijungti.php');
  await m2.fill('input[name=email]', 'narys@karateka.test');
  await m2.fill('input[name=password]', PASSWORD);
  await Promise.all([m2.waitForNavigation(), m2.click('button[type=submit]')]);
  check('next login opens in the saved language', await m2.evaluate(() => document.documentElement.lang) === 'en');
  await m2.goto('kalba.php?l=lt&b=/paskyra.php');
  check('back to Lithuanian', sql("SELECT lang FROM accounts WHERE email = 'narys@karateka.test'") === 'lt');
  noJsErrors(m, m2);
});

test('C8: broadcast e-mail goes through the outbox, each in the reader language, result per person', async ({ browser }) => {
  sql("UPDATE accounts SET lang = 'en' WHERE email = 'narys@karateka.test'");
  sql("DELETE FROM mail_outbox WHERE title LIKE 'E2E%'");
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto('admin/pranesimai.php');
  await a.fill('input[name=title]', 'E2E Laiškų eilė');
  await a.fill('textarea[name=body]', 'Šeštadienį treniruotė vyks salėje B.');
  await a.uncheck('input[name="channels[]"][value=site]');
  await a.check('input[name="channels[]"][value=email]');
  await nav(a, () => a.click('form button.btn-primary[type=submit]'));
  check('opens the sending page', a.url().includes('admin/siuntimas.php?b='));
  await a.waitForFunction(() => document.getElementById('ob') && +document.getElementById('ob').dataset.left === 0, null, { timeout: 30000 });
  const left = sql("SELECT COUNT(*) FROM mail_outbox WHERE title = 'E2E Laiškų eilė' AND status IN ('queued', 'sending')");
  check('nothing left in the queue', left === '0', left);
  check('result per person shown', await a.locator('.ob-list li').count() === +sql("SELECT COUNT(*) FROM mail_outbox WHERE title = 'E2E Laiškų eilė'"));
  check('member with English gets English', /^Hello/.test(sql("SELECT body FROM mail_outbox WHERE title = 'E2E Laiškų eilė' AND to_email = 'narys@karateka.test'")));
  check('others get Lithuanian', /^Sveiki/.test(sql("SELECT body FROM mail_outbox WHERE title = 'E2E Laiškų eilė' AND to_email <> 'narys@karateka.test' LIMIT 1")));
  check('one footer', /VšĮ Karate Ateitis · karateka.lt$/.test(sql("SELECT body FROM mail_outbox WHERE title = 'E2E Laiškų eilė' LIMIT 1")));
  await a.goto('admin/pranesimai.php');
  check('broadcast page lists the e-mail batch', (await a.locator('#laiskai').innerText()).includes('E2E Laiškų eilė'));
  sql("UPDATE accounts SET lang = 'lt' WHERE email = 'narys@karateka.test'");
  noJsErrors(a);
});

test('C8: "coach replied" e-mail link opens the chat for the member', async ({ browser }) => {
  const mid = sql("SELECT am.member_id FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE a.email = 'narys@karateka.test' ORDER BY am.relation = 'self' DESC LIMIT 1");
  const m = await login(browser, 'narys@karateka.test', 390, 844);
  await m.goto(`paskyra.php?pokalbis=${mid}`);
  await m.waitForTimeout(600);
  check('chat opens from the e-mail link', await m.locator('#chatPanel').isVisible());
  noJsErrors(m);
});
