// #165 (Denis): viena registracijos nuoroda visiems egzaminams → „⏳ Laukiantieji“; egzamino dienai - keli protokolai (diržams);
// pažymėtus įtraukus į protokolą - eilutė su 6.4, laiškas su data, vieta ir kaina pagal diržą.
const { test } = require('@playwright/test');
const { sql, day, login, nav, check, noJsErrors } = require('./helpers');

test('exam waiting list: one link, separate protocols per belt, add to protocol with an e-mail', async ({ browser }) => {
  sql("DELETE FROM exam_waitlist");
  sql("DELETE FROM site_settings WHERE name IN ('exam_waitlist_token', 'exam_waitlist_open')");
  sql(`REPLACE INTO site_settings (name, value) VALUES ('exam_prices', '{"1": 25}')`);
  sql(`INSERT INTO events (type, title, starts_on, start_time, location) VALUES ('exam', 'Kyu egzaminas (laukiantieji)', '${day(12)}', '10:00', 'Sporto centras')`);
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/egzaminai.php');
  await nav(a, () => a.click('#laukiantieji button:has-text("Sukurti registracijos nuorodą")'));
  const url = await a.getAttribute('#laukiantieji [data-copy]', 'data-copy');
  check('one registration link', /egzamino-registracija\.php\?k=[a-f0-9]{24}$/.test(url), url);
  // Ta pati diena - kitas protokolas kitiems diržams
  await a.locator('.exam-copy input[name=label]').last().fill('9–7 kyu');
  await nav(a, () => a.locator('.exam-copy button').last().click());
  check('second protocol for the same date', sql(`SELECT COUNT(*) FROM events WHERE type = 'exam' AND starts_on = '${day(12)}' AND title LIKE '%· 9–7 kyu'`) === '1');
  const ev2 = sql(`SELECT id FROM events WHERE type = 'exam' AND title LIKE '%· 9–7 kyu' ORDER BY id DESC LIMIT 1`);

  // Žmogus be paskyros registruojasi
  const v = await login(browser, null, 390, 844);
  await v.goto(url.replace(/^https?:\/\/[^/]+\//, ''));
  await v.fill('input[name=first_name]', 'Laukiantis'); await v.fill('input[name=last_name]', 'Testinis');
  await v.fill('input[name=birth_date]', '2014-05-06'); await v.selectOption('select[name=kyu]', '9 kyu');
  await v.fill('input[name=email]', 'laukiantis@example.test'); await v.fill('input[name=phone]', '+37060000000'); await v.fill('input[name=club]', 'Sanrei');
  await nav(v, () => v.click('button[type=submit]'));
  check('registered to the waiting list', /laukiančiųjų sąraše/.test(await v.locator('.exam-su-ok').innerText()) && sql("SELECT COUNT(*) FROM exam_waitlist WHERE last_name = 'Testinis' AND event_id IS NULL") === '1');
  await v.goto(url.replace(/^https?:\/\/[^/]+\//, ''));
  await v.fill('input[name=first_name]', 'Laukiantis'); await v.fill('input[name=last_name]', 'Testinis');
  await v.fill('input[name=birth_date]', '2014-05-06'); await v.selectOption('select[name=kyu]', '9 kyu');
  await v.fill('input[name=email]', 'laukiantis@example.test'); await v.fill('input[name=phone]', '+37060000000');
  await nav(v, () => v.click('button[type=submit]'));
  check('same person not twice', /jau sąraše/.test(await v.locator('.exam-su-ok').innerText()) && sql("SELECT COUNT(*) FROM exam_waitlist WHERE last_name = 'Testinis'") === '1');

  // Treneris: laukiantieji su kaina pagal diržą → įtraukti į antrą protokolą
  await a.goto('admin/egzaminai.php');
  const row = a.locator('.exam-wl-table tbody tr', { hasText: 'Laukiantis Testinis' });
  check('waiting list shows the person with the belt price', await row.count() === 1 && /25,00 €/.test(await row.innerText()) && /9 kyu/.test(await row.innerText()));
  await row.locator('input[type=checkbox]').check();
  await a.selectOption('.exam-wl-go select[name=event_id]', ev2);
  await nav(a, () => a.click('.exam-wl-go button[type=submit]'));
  check('in the protocol with 6.4 and the belt', sql(`SELECT CONCAT(kyu, '|', kihon, '|', club) FROM exam_protocol_rows WHERE event_id = ${ev2} AND name = 'Laukiantis Testinis'`) === '9 kyu|6.4|Sanrei');
  check('left the waiting list, e-mail sent', sql("SELECT CONCAT(event_id IS NOT NULL, mailed_at IS NOT NULL) FROM exam_waitlist WHERE last_name = 'Testinis'") === '11'
    && await a.locator('.exam-wl-table tbody tr', { hasText: 'Laukiantis Testinis' }).count() === 0);
  // Uždarius - registracija nevyksta
  await nav(a, () => a.click('#laukiantieji button:has-text("Uždaryti registraciją")'));
  await v.goto(url.replace(/^https?:\/\/[^/]+\//, ''));
  check('closed link', /Registracija uždaryta/.test(await v.locator('main, body').first().innerText()));
  sql(`DELETE FROM events WHERE title LIKE 'Kyu egzaminas (laukiantieji)%'`);
  sql("DELETE FROM exam_waitlist");
  noJsErrors(a, v);
});
