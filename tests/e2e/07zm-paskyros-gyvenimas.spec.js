// C1: paskyros gyvenimas - registracija → el. pašto patvirtinimas → trenerio patvirtinimas → narys → rolė, testuotojas,
// pradžios redagavimas (pagal paskyrą, ne pagal vardą) → slaptažodžio pakeitimas ir atkūrimas atjungia kitus įrenginius → išjungimas.
// Kiekvienas žingsnis - account_log žurnale (kas, kada, sena → nauja), žurnalas - Paskyros ir rolės → „Pakeitimų žurnalas“.
const { test } = require('@playwright/test');
const crypto = require('crypto');
const { sql, login, check, noJsErrors, BASE_URL } = require('./helpers');

const EMAIL = 'e2e-c1-gyvenimas@karateka.test';
const FIRST = 'Rūta';
const LAST = 'Teisutė';
const PW1 = 'Pirmas-slapt-1';
const PW2 = 'Antras-slapt-2';
const PW3 = 'Trecias-slapt-3';

function cleanup() {
  const id = sql(`SELECT COALESCE(MAX(id), 0) FROM accounts WHERE email = '${EMAIL}'`);
  if (+id) {
    const mids = sql(`SELECT COALESCE(GROUP_CONCAT(member_id), '0') FROM account_members WHERE account_id = ${id}`);
    sql(`DELETE FROM account_log WHERE account_id = ${id}`);
    sql(`DELETE FROM accounts WHERE id = ${id}`);   // account_members, email_tokens - ON DELETE CASCADE
    sql(`DELETE FROM members WHERE id IN (${mids})`);
  }
  sql(`DELETE FROM login_attempts WHERE email = '${EMAIL}'`);
}
const accId = () => +sql(`SELECT id FROM accounts WHERE email = '${EMAIL}'`);
const col = (c) => sql(`SELECT ${c} FROM accounts WHERE email = '${EMAIL}'`);
const logHas = (field, oldV, newV) => +sql(`SELECT COUNT(*) FROM account_log WHERE account_id = ${accId()} AND field = '${field}'`
  + (oldV === undefined ? '' : (oldV === null ? ' AND old_value IS NULL' : ` AND old_value = '${oldV}'`))
  + (newV === undefined ? '' : ` AND new_value = '${newV}'`)) > 0;
/** Vienkartinis laiško žetonas (kaip app/tokens.php: duomenų bazėje - tik sha256) */
function mailToken(purpose) {
  const tok = crypto.randomBytes(32).toString('hex');
  sql(`INSERT INTO email_tokens (purpose, token_hash, email, account_id, expires_at) VALUES ('${purpose}', SHA2('${tok}', 256), '${EMAIL}', ${accId()}, NOW() + INTERVAL 1 HOUR)`);
  return tok;
}
async function csrfOf(page) {
  return page.locator('input[name=_csrf]').first().inputValue();
}
async function signedOut(page) {
  await page.goto('paskyra.php');
  return page.url().includes('prisijungti.php');
}

test('account life cycle: register → verify → approve → role, tester, homepage editor → password change and reset sign out → disable; all in the log', async ({ browser }) => {
  test.setTimeout(180000);
  cleanup();
  sql("INSERT INTO site_settings (name, value) VALUES ('game_access', 'all') ON DUPLICATE KEY UPDATE value = 'all'");
  const editorsBefore = sql("SELECT COALESCE(MAX(value), '') FROM site_settings WHERE name = 'home_editors'");

  // 1. Registracija (suaugęs narys pats): paskyra laukia el. pašto patvirtinimo
  const g = await login(browser, null, 1280, 900);
  await g.goto('registracija.php?tipas=pats');
  const reg = await g.request.post('registracija.php?tipas=pats', { form: {
    _csrf: await csrfOf(g), tipas: 'pats', email: EMAIL, first_name: FIRST, last_name: LAST, phone: '',
    password: PW1, password2: PW1, data_consent: '1', birth_date: '1990-05-17', photo_consent: '0',
  } });
  check('registration accepted', reg.ok() && /registracija\.php\?ok=1/.test(reg.url()), `${reg.status()} ${reg.url()}`);
  check('account waits for e-mail', col('status') === 'pending_email', col('status'));
  check('log: account created', logHas('created', null, 'pending_email'));

  // 2. El. pašto nuoroda → laukia trenerio patvirtinimo (žurnale - be „kas pakeitė“: pats žmogus)
  await g.goto(`patvirtinti.php?t=${mailToken('verify_email')}`);
  check('account waits for approval', col('status') === 'pending_approval', col('status'));
  check('log: pending_email → pending_approval by the person', logHas('status', 'pending_email', 'pending_approval')
    && sql(`SELECT COUNT(*) FROM account_log WHERE account_id = ${accId()} AND field = 'status' AND changed_by IS NULL`) !== '0');

  // 3. Dar nepatvirtintas: prisijungia, bet Valdymo ir Dojo nemato
  const p = await login(browser, EMAIL, 1280, 900, PW1);
  check('pending account can log in', !p.url().includes('prisijungti.php'), p.url());
  check('pending account: no Valdymas', (await p.request.get('admin/', { maxRedirects: 0 })).status() === 403);
  await p.goto('dojo.php');
  check('pending account: no Dojo', !p.url().includes('dojo.php'), p.url());

  // 4. Treneris patvirtina (su grupe) → aktyvus narys
  const c = await login(browser, 'treneris@karateka.test', 1280, 900);
  await c.goto('admin/patvirtinimai.php');
  const mid = sql(`SELECT m.id FROM members m JOIN account_members am ON am.member_id = m.id WHERE am.account_id = ${accId()}`);
  const gid = sql('SELECT id FROM training_groups WHERE is_active = 1 ORDER BY id LIMIT 1');
  await c.request.post('admin/patvirtinimai.php', { form: { _csrf: await csrfOf(c), action: 'approve_account', account_id: String(accId()), [`group[${mid}]`]: gid } });
  check('approved by the coach', col('status') === 'active', col('status'));
  const coachId = sql("SELECT id FROM accounts WHERE email = 'treneris@karateka.test'");
  check('log: pending_approval → active by the coach', +sql(`SELECT COUNT(*) FROM account_log WHERE account_id = ${accId()} AND field = 'status'
    AND old_value = 'pending_approval' AND new_value = 'active' AND changed_by = ${coachId}`) === 1);
  check('a coach cannot open Paskyros ir rolės', (await c.request.get('admin/paskyros.php', { maxRedirects: 0 })).status() === 403);

  // 5. Narys: paskyra veikia, Valdymas - ne; Dojo - taip (žaidimas atidarytas visiems)
  await p.goto('paskyra.php');
  check('member sees own account', p.url().includes('paskyra.php'));
  check('member: no Valdymas', (await p.request.get('admin/', { maxRedirects: 0 })).status() === 403);
  await p.goto('dojo.php');
  check('member sees Dojo while the game is open to all', p.url().includes('dojo.php'), p.url());

  // 6. Administratorius: be CSRF žetono niekas nekeičiama; rolė → treneris (patvirtinimas vardija žmogų)
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  const noToken = await a.request.post('admin/paskyros.php', { form: { action: 'role', id: String(accId()), role: 'admin' } });
  check('POST without the CSRF token is refused', noToken.status() === 400 && col('role') === 'member', `${noToken.status()} ${col('role')}`);
  await a.goto(`admin/paskyros.php?q=${encodeURIComponent(LAST)}`);
  const row = () => a.locator('tr', { hasText: EMAIL });
  check('role confirm names the person', (await row().locator('select[name=role]').getAttribute('data-ask')).includes(`${FIRST} ${LAST}`));
  await Promise.all([a.waitForNavigation(), row().locator('select[name=role]').selectOption('coach')]);
  check('role changed to coach', col('role') === 'coach', col('role'));
  check('log: role member → coach by the admin', logHas('role', 'member', 'coach'));
  await p.goto('admin/');
  check('now opens Valdymas', p.url().includes('/admin/'), p.url());

  // 7. Testuotojo žymė: gali tiek, kiek treneris, nustatymus - tik skaityti, Dojo - ne
  await Promise.all([a.waitForNavigation(), row().locator('.tester-tg button').click()]);
  check('tester flag on', col('is_tester') === '1');
  check('log: tester 0 → 1', logHas('is_tester', '0', '1'));
  await p.goto('admin/nustatymai.php');
  check('tester: settings read only', await p.locator('.ro-note').isVisible());
  await p.goto('dojo.php');
  check('tester: no Dojo even when open to all', !p.url().includes('dojo.php'), p.url());
  await Promise.all([a.waitForNavigation(), row().locator('.tester-tg button').click()]);
  check('tester flag off, logged', col('is_tester') === '0' && logHas('is_tester', '1', '0'));

  // 8. Pagrindinio puslapio redagavimas - tik pažymėtai paskyrai (vardas instruktorių bloke teisės neduoda)
  check('not a homepage editor yet', (await p.request.get('admin/puslapis.php', { maxRedirects: 0 })).status() === 403);
  await Promise.all([a.waitForNavigation(), row().locator('.home-ed-tg button').click()]);
  check('homepage editor on', (await p.request.get('admin/puslapis.php', { maxRedirects: 0 })).status() === 302);
  check('log: homepage editor 0 → 1', logHas('home_editor', '0', '1'));
  await Promise.all([a.waitForNavigation(), row().locator('.home-ed-tg button').click()]);
  check('homepage editor off', (await p.request.get('admin/puslapis.php', { maxRedirects: 0 })).status() === 403 && logHas('home_editor', '1', '0'));

  // 9. Slaptažodžio pakeitimas: kitas įrenginys atjungiamas, šis lieka
  const other = await login(browser, EMAIL, 375, 812, PW1);
  check('second device logged in', !(await signedOut(other)));
  await p.goto('admin/nustatymai.php');
  await p.request.post('admin/nustatymai.php', { form: { _csrf: await csrfOf(p), action: 'password', current: PW1, password: PW2, password2: PW2 } });
  check('the device that changed it stays in', !(await signedOut(p)));
  check('the other device is signed out', await signedOut(other));
  check('log: password changed', logHas('password', null, 'changed'));

  // 10. Atkūrimas per nuorodą: atjungiami visi (ir tas, kuris buvo prisijungęs)
  const r = await login(browser, null, 375, 812);
  await r.goto(`slaptazodis.php?t=${mailToken('password_reset')}`);
  await r.fill('input[name=password]', PW3);
  await r.fill('input[name=password2]', PW3);
  await Promise.all([r.waitForNavigation(), r.click('form button[type=submit]')]);
  check('reset done, back to login', r.url().includes('prisijungti.php'), r.url());
  check('after the reset the logged-in device is signed out', await signedOut(p));
  check('log: password reset', logHas('password', null, 'reset'));
  const again = await login(browser, EMAIL, 1280, 900, PW3);
  check('new password works', !(await signedOut(again)));

  // 11. Išjungta paskyra neprisijungia; įjungus - vėl gali
  await a.goto(`admin/paskyros.php?q=${encodeURIComponent(LAST)}`);
  await Promise.all([a.waitForNavigation(), row().locator('button[value=disable]').click()]);
  check('account disabled', col('status') === 'disabled');
  check('log: active → disabled', logHas('status', 'active', 'disabled'));
  check('disabled: the open session is closed', await signedOut(again));
  const d = await login(browser, EMAIL, 1280, 900, PW3);
  check('disabled: cannot log in', d.url().includes('prisijungti.php'), d.url());
  await Promise.all([a.waitForNavigation(), row().locator('button[value=enable]').click()]);
  check('account enabled again, logged', col('status') === 'active' && logHas('status', 'disabled', 'active'));

  // 12. Žurnalas ekrane: kas, kada, sena → nauja
  await a.goto('admin/paskyros.php#zurnalas');
  const log = await a.locator('#zurnalas').innerText();
  check('log on screen: the person and the changes', log.includes(`${FIRST} ${LAST}`) && /Rolė/.test(log) && /Testuotojas/.test(log)
    && /Redaguoja pradžią/.test(log) && /Slaptažodis/.test(log) && /Denis Balinskis/.test(log), log.slice(0, 400));
  check('log on screen: old → new value', /Narys \/ tėvai → Treneris/.test(log), log.slice(0, 400));
  check('log rows for every step', +sql(`SELECT COUNT(*) FROM account_log WHERE account_id = ${accId()}`) >= 11);

  noJsErrors(a, p, c);
  cleanup();
  if (editorsBefore === '') sql("DELETE FROM site_settings WHERE name = 'home_editors'");
  else sql(`UPDATE site_settings SET value = '${editorsBefore}' WHERE name = 'home_editors'`);
});

test('permission table: one can() for pages - guests, members, coaches, testers and admins get the pages of their role', async ({ browser }) => {
  // Treneris: trenerio puslapiai atsidaro, tik administratoriaus - ne (403); Budo atsidaro
  const c = await login(browser, 'treneris@karateka.test', 1280, 900);
  for (const pg of ['admin/', 'admin/treniruote.php', 'admin/nariai.php', 'admin/egzaminai.php', 'admin/varzybos.php', 'admin/testai.php']) {
    check(`coach opens ${pg}`, (await c.request.get(pg, { maxRedirects: 0 })).status() === 200);
  }
  for (const pg of ['admin/paskyros.php', 'admin/kopijos.php', 'admin/sms.php', 'admin/pranesimai.php', 'admin/atsiliepimai.php', 'admin/perziura.php',
    'admin/nustatymai-visi.php', 'admin/kitas-irenginys.php', 'admin/testavimas.php', 'admin/pateisinimai.php', 'admin/duomenys.php', 'admin/ltkf-perziura.php']) {
    check(`coach: no ${pg}`, (await c.request.get(pg, { maxRedirects: 0 })).status() === 403);
  }
  // Narys: jokio Valdymo puslapio
  const m = await login(browser, 'narys@karateka.test', 1280, 900);
  for (const pg of ['admin/', 'admin/nariai.php', 'admin/varzybos.php', 'admin/paskyros.php']) {
    check(`member: no ${pg}`, (await m.request.get(pg, { maxRedirects: 0 })).status() === 403);
  }
  // Svečias: į prisijungimą
  const g = await login(browser, null, 1280, 900);
  const r = await g.request.get('admin/nariai.php', { maxRedirects: 0 });
  check('guest: to the login page', r.status() === 302 && /prisijungti\.php/.test(r.headers().location || ''), `${r.status()} ${r.headers().location}`);
  // Administratorius: viskas
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  for (const pg of ['admin/paskyros.php', 'admin/kopijos.php', 'admin/nustatymai-visi.php', 'admin/testavimas.php']) {
    check(`admin opens ${pg}`, (await a.request.get(pg, { maxRedirects: 0 })).status() === 200);
  }
  noJsErrors(c, m, a);
});
