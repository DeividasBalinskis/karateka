// A3: egzamino nuorodos - svetimas negauna vaiko puslapio: vien vardo neužtenka, įrašytas treneriui žmogus - be nuorodos,
// „Jau užsiregistravai?“ ribojama pagal IP, ↻ nauja nuoroda, atsiliepimuose nesaugomi slapti adreso parametrai.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, openFeedback } = require('./helpers');

test('A3: exam links - no name-only match, coach-added person gets no link, find is rate-limited, ↻ new link, feedback scrubs secrets', async ({ browser }) => {
  sql("DELETE FROM exam_protocol_rows WHERE number LIKE 'E2E-90%'");
  sql("DELETE FROM events WHERE title = 'E2E A3 egzaminas'");
  sql("DELETE FROM login_attempts WHERE email = '#exam-find'");
  sql("INSERT INTO events (type, title, starts_on, start_time) VALUES ('exam', 'E2E A3 egzaminas', DATE_ADD(CURDATE(), INTERVAL 10 DAY), '11:00')");
  const eid = +sql("SELECT id FROM events WHERE title = 'E2E A3 egzaminas'");
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto(`admin/egzamino-protokolas.php?id=${eid}`);
  // Treneris įrašo vaiką su gimimo data; ir vaiką be gimimo datos
  sql(`INSERT INTO exam_protocol_rows (event_id, number, num_date, seq, name, birth_date, phone) VALUES (${eid}, 'E2E-901', CURDATE(), 901, 'Rūta Tikroji', '2014-06-01', '+370 600 11111')`);
  sql(`INSERT INTO exam_protocol_rows (event_id, number, num_date, seq, name) VALUES (${eid}, 'E2E-902', CURDATE(), 902, 'Be Datos')`);
  sql(`INSERT IGNORE INTO exam_signup_links (event_id, token, is_open) VALUES (${eid}, LOWER(LEFT(REPLACE(UUID(), '-', ''), 24)), 1)`);
  const token = sql(`SELECT token FROM exam_signup_links WHERE event_id = ${eid}`);
  check('sign-up link exists', /^[a-f0-9]{24}$/.test(token), token);

  const v = await login(browser, null, 375, 812);
  const signup = async (first, last, birth, phone) => {
    await v.goto(`egzamino-registracija.php?k=${token}`);
    await v.fill('input[name=first_name]', first); await v.fill('input[name=last_name]', last);
    await v.fill('input[name=birth_date]', birth); await v.fill('input[name=phone]', phone);
    await nav(v, () => v.click('button:has-text("Registruotis į egzaminą")'));
  };
  // Svetimas žino vaiko vardą ir gimimo datą: gauna „jau sąraše“, bet ne nuorodą; telefonas neperrašomas
  await signup('Rūta', 'Tikroji', '2014-06-01', '+370 699 99999');
  check('coach-added child: „already registered“, no link', /jau užregistruotas/.test(await v.textContent('.exam-su-ok')) && await v.locator('.exam-su-ok a[href*="mano-egzaminas"], .exam-su-ok .su-url').count() === 0);
  check('phone not overwritten', sql(`SELECT phone FROM exam_protocol_rows WHERE event_id = ${eid} AND number = 'E2E-901'`) === '+370 600 11111');
  // Vien vardo neužtenka: žmogus be gimimo datos protokole - registracija sukuria naują eilutę, ne prisijungia prie svetimos
  await signup('Be', 'Datos', '2013-01-01', '+370 699 88888');
  check('no match on name alone', +sql(`SELECT COUNT(*) FROM exam_protocol_rows WHERE event_id = ${eid} AND name = 'Be Datos' AND deleted_at IS NULL`) === 2);
  // Ištrinta eilutė neatgaivinama
  sql(`UPDATE exam_protocol_rows SET deleted_at = NOW() WHERE event_id = ${eid} AND number = 'E2E-901'`);
  await signup('Rūta', 'Tikroji', '2014-06-01', '+370 699 99999');
  check('deleted row stays deleted', sql(`SELECT deleted_at IS NOT NULL FROM exam_protocol_rows WHERE event_id = ${eid} AND number = 'E2E-901'`) === '1');

  // „Jau užsiregistravai?“: po 10 nesėkmių iš to paties IP - užrakinama (11-as bandymas - „Per daug bandymų“)
  for (let i = 0; i < 11; i++) {
    await v.goto(`egzamino-registracija.php?k=${token}`);
    await v.locator('#rasti > summary').click();
    await v.fill('#rasti input[name=name]', 'Niekas ' + i); await v.fill('#rasti input[name=birth_date]', '2012-01-01');
    await nav(v, () => v.click('#rasti button[type=submit]'));
  }
  check('find is limited per IP in the database', +sql("SELECT COUNT(*) FROM login_attempts WHERE email = '#exam-find' AND success = 0") >= 10 && /Per daug bandymų/.test(await v.textContent('#rasti')));

  // ↻ Nauja nuoroda: senoji nebeveikia
  const row = sql(`SELECT id FROM exam_protocol_rows WHERE event_id = ${eid} AND name = 'Be Datos' AND birth_date = '2013-01-01'`);
  const oldTok = sql(`SELECT token FROM exam_protocol_rows WHERE id = ${row}`);
  await a.goto(`admin/egzamino-protokolas.php?id=${eid}`);
  await nav(a, () => a.locator(`tr[data-row="${row}"] .pr-newlink`).click());
  const newTok = sql(`SELECT token FROM exam_protocol_rows WHERE id = ${row}`);
  check('↻ gives a new link', /^[a-f0-9]{20}$/.test(newTok) && newTok !== oldTok);
  const r = await v.request.get(`mano-egzaminas.php?t=${oldTok}`);
  check('old link stops working', r.status() !== 200 || !/Labas/.test(await r.text()), String(r.status()));

  // Atsiliepimas iš puslapio su slaptu raktu - raktas nesaugomas
  sql("DELETE FROM feedback WHERE body LIKE 'E2E A3%'");
  await a.goto(`egzamino-registracija.php?k=${token}`);
  await openFeedback(a);
  await a.fill('#fbText', 'E2E A3 testas');
  await a.waitForFunction(() => /Išsaugota/.test(document.getElementById('fbState').textContent), null, { timeout: 8000 });
  const page = sql("SELECT page FROM feedback WHERE body LIKE 'E2E A3%' ORDER BY id DESC LIMIT 1");
  check('feedback does not store the secret key', page.includes('egzamino-registracija.php') && !page.includes(token), page);
  sql("DELETE FROM feedback WHERE body LIKE 'E2E A3%'");
  sql("DELETE FROM login_attempts WHERE email = '#exam-find'");   // kad kiti testai iš to paties IP nebūtų užrakinti
  sql(`DELETE FROM exam_protocol_rows WHERE event_id = ${eid}`);
  sql(`DELETE FROM events WHERE id = ${eid}`);
  noJsErrors(a, v);
});
