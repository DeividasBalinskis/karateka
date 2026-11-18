// v1.41: registracija į egzaminą per nuorodą (be paskyros) ir kvietimai, „Egzaminavimas“ treneriams, „Registruotis“ / „Registracija uždaryta“
// prie renginių, mokestis pagal lankomumą, testas iš visų klausimų, prisijungimo tekstas; Budo: „Rask save“ ir YouTube transliacija žiūrovams.
const { test } = require('@playwright/test');
const { sql, day, login, nav, check, noJsErrors } = require('./helpers');

test('exam signup link for people without an account, invitations, exam list for staff, register buttons', async ({ browser }) => {
  sql("DELETE FROM events WHERE title LIKE 'E2E egzaminas%' OR title = 'E2E uždaryta registracija'");
  sql(`INSERT INTO events (type, title, starts_on, members_only, registration) VALUES ('exam', 'E2E egzaminas', '${day(12)}', 0, 0)`);
  sql(`INSERT INTO events (type, title, starts_on, members_only, registration) VALUES ('seminar', 'E2E uždaryta registracija', '${day(14)}', 0, 0)`);
  const eid = +sql("SELECT id FROM events WHERE title = 'E2E egzaminas'");
  const a = await login(browser, 'info@karateka.lt');

  // „Egzaminai“ - trenerio skirtukų juostoje (šalia „Varžybos“, „Testai“)
  await a.goto('admin/');
  check('coach tabs have Egzaminai', await a.locator('.sub-nav a[href*="admin/egzaminai.php"]').count() === 1);
  await a.goto('admin/egzaminai.php');
  const item = a.locator('.exam-item', { hasText: 'E2E egzaminas' });
  check('exam listed', await item.count() === 1);
  await nav(a, () => item.locator('a', { hasText: 'Protokolas' }).click());

  // Protokole: sukurti registracijos nuorodą
  if (!(await a.locator('#registracija button:has-text("Sukurti registracijos nuorodą")').isVisible())) await a.locator('#registracija > summary').click();
  await nav(a, () => a.click('button:has-text("Sukurti registracijos nuorodą")'));
  const token = sql(`SELECT token FROM exam_signup_links WHERE event_id = ${eid}`);
  check('link created', /^[a-f0-9]{24}$/.test(token), token);
  check('link shown with copy', (await a.inputValue('#registracija .su-url')).includes('egzamino-registracija.php?k=' + token));

  // Kvietimai el. paštu: eilutė be el. pašto praleidžiama
  if (!(await a.locator('.su-invite').isVisible())) await a.locator('#registracija > summary').click();
  await a.locator('.su-invite > summary').click();
  await a.fill('.su-invite textarea[name=people]', 'Petras Kviestinis, petras.e2e@example.com\nbe pasto');
  await nav(a, () => a.click('.su-invite button[type=submit]'));
  check('invite result reported', /Kvietimų išsiųsta: \d/.test(await a.locator('.flash').allTextContents().then(t => t.join(' '))));
  check('line without e-mail skipped', /be pasto/.test(await a.locator('.flash').allTextContents().then(t => t.join(' '))));

  // Žmogus be paskyros: renginio puslapyje - „Registruotis į egzaminą“, forma - vardas, pavardė, el. paštas
  const v = await login(browser, null, 390, 844);
  await v.goto(`renginys.php?id=${eid}`);
  check('event page: register for the exam', await v.locator('.ep-reg a', { hasText: 'Registruotis į egzaminą' }).count() === 1);
  await nav(v, () => v.locator('.ep-reg a', { hasText: 'Registruotis į egzaminą' }).click());
  await nav(v, () => v.click('button:has-text("Registruotis į egzaminą")'));
  check('required fields checked', await v.locator('.flash-err').count() === 1);
  await v.fill('input[name=first_name]', 'Ona');
  await v.fill('input[name=last_name]', 'Svečiūtė');
  await v.fill('input[name=email]', 'ona.e2e@example.com');
  await v.locator('.exam-su-more > summary').click();
  await v.fill('input[name=club]', 'Kitas klubas');
  await v.selectOption('select[name=kyu]', '8 kyu');
  await v.check('input[name=consent]');
  await nav(v, () => v.click('button:has-text("Registruotis į egzaminą")'));
  check('signed up', await v.locator('.exam-su-ok').count() === 1);
  const row = sql(`SELECT CONCAT(name, '|', club, '|', kyu) FROM exam_protocol_rows WHERE event_id = ${eid} AND deleted_at IS NULL`);
  check('in the protocol at once', row === 'Ona Svečiūtė|Kitas klubas|8 kyu', row);
  check('signup stored', +sql(`SELECT COUNT(*) FROM exam_signups WHERE event_id = ${eid} AND email = 'ona.e2e@example.com' AND signed_at IS NOT NULL`) === 1);
  check('staff notified', +sql("SELECT COUNT(*) FROM notifications WHERE type = 'exam_registration' AND title LIKE '%Ona Svečiūtė%'") >= 1);
  // Tas pats el. paštas dar kartą - atnaujina, ne dubliuoja
  await v.goto(`egzamino-registracija.php?k=${token}`);
  await v.fill('input[name=first_name]', 'Ona');
  await v.fill('input[name=last_name]', 'Svečiūtė-Kitokia');
  await v.fill('input[name=email]', 'ona.e2e@example.com');
  await v.check('input[name=consent]');
  await nav(v, () => v.click('button:has-text("Registruotis į egzaminą")'));
  check('same e-mail updates, no duplicate', +sql(`SELECT COUNT(*) FROM exam_protocol_rows WHERE event_id = ${eid} AND deleted_at IS NULL`) === 1
    && sql(`SELECT name FROM exam_protocol_rows WHERE event_id = ${eid}`) === 'Ona Svečiūtė-Kitokia');

  // Protokole - kas užsiregistravo; uždarius - „Registracija uždaryta“
  await a.goto(`admin/egzamino-protokolas.php?id=${eid}`);
  check('protocol lists signups', await a.locator('.su-table tr', { hasText: 'ona.e2e@example.com' }).count() === 1);
  if (!(await a.locator('#registracija button:has-text("Uždaryti registraciją")').isVisible())) await a.locator('#registracija > summary').click();
  await nav(a, () => a.click('#registracija button:has-text("Uždaryti registraciją")'));
  await v.goto(`egzamino-registracija.php?k=${token}`);
  check('closed link says so', /Registracija uždaryta/.test(await v.textContent('.exam-su')));

  // Renginių sąraše: kiekvienas būsimas renginys - „Registruotis“ arba „Registracija uždaryta“
  await v.goto('renginiai.php');
  const closed = v.locator('.event', { hasText: 'E2E uždaryta registracija' });
  check('closed registration shown', await closed.locator('.reg-closed', { hasText: 'Registracija uždaryta' }).count() === 1);
  sql("UPDATE events SET registration = 1, registration_until = NULL WHERE title = 'E2E uždaryta registracija'");
  await v.goto('renginiai.php');
  check('open registration: Registruotis', await v.locator('.event', { hasText: 'E2E uždaryta registracija' }).locator('a.event-reg', { hasText: 'Registruotis' }).count() === 1);

  // Prisijungimo puslapyje - ką rasite prisijungę
  await v.goto('prisijungti.php');
  check('login page explains what is inside', /renginius, registracijas, sąskaitas, dokumentus/.test(await v.textContent('.login-intro')));

  sql("DELETE FROM events WHERE title LIKE 'E2E egzaminas%' OR title = 'E2E uždaryta registracija'");
  noJsErrors(a, v);
});

test('visit price per training with a monthly cap; quiz from all questions', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt');
  const gid = +sql("SELECT g.id FROM training_groups g WHERE g.is_active = 1 AND (SELECT COUNT(*) FROM members m WHERE m.group_id = g.id AND m.status = 'active') >= 1 ORDER BY g.id LIMIT 1");
  const old = sql(`SELECT CONCAT(COALESCE(price_visit, ''), '|', COALESCE(price_cap, '')) FROM training_groups WHERE id = ${gid}`);
  await a.goto(`admin/grupes.php?id=${gid}`);
  await a.fill('input[name=price_visit]', '6');
  await a.fill('input[name=price_cap]', '35');
  await nav(a, () => a.locator('form:has(input[name=price_visit]) button[type=submit]').first().click());
  check('price saved', sql(`SELECT CONCAT(price_visit, '|', price_cap) FROM training_groups WHERE id = ${gid}`) === '6.00|35.00');

  const d = day(-1);
  sql(`DELETE FROM attendance WHERE group_id = ${gid} AND training_date BETWEEN DATE_FORMAT('${d}', '%Y-%m-01') AND LAST_DAY('${d}')`);
  await a.goto(`admin/treniruote.php?g=${gid}&d=${d}`);
  check('rule shown', /6 € už treniruotę, ne daugiau kaip 35 € per mėn\./.test(await a.textContent('.att-bar')));
  const box = a.locator('.att').first(), mid = await box.getAttribute('data-member');
  await box.locator('.att-btn.yes').click();
  await a.waitForFunction(m => /1 × 6 € = 6 €/.test(document.querySelector('[data-fee="' + m + '"]').textContent), mid);
  check('sum grows with attendance', true);
  // 7 apsilankymai = 42 €, bet riba - 35 €
  for (let i = 2; i <= 7; i++) {
    const dd = d.slice(0, 8) + String(i).padStart(2, '0');
    if (dd !== d) sql(`INSERT IGNORE INTO attendance (member_id, training_date, group_id, present) VALUES (${mid}, '${dd}', ${gid}, 1)`);
  }
  await a.reload();
  check('capped at 35 €', /= 35 € \(riba\)/.test(await a.textContent(`[data-fee="${mid}"]`)), await a.textContent(`[data-fee="${mid}"]`));
  sql(`DELETE FROM attendance WHERE group_id = ${gid} AND member_id = ${mid} AND training_date BETWEEN DATE_FORMAT('${d}', '%Y-%m-01') AND LAST_DAY('${d}')`);
  const [pv, pc] = old.split('|');
  sql(`UPDATE training_groups SET price_visit = ${pv || 'NULL'}, price_cap = ${pc || 'NULL'} WHERE id = ${gid}`);

  // Testas iš visų banko klausimų
  sql("DELETE FROM quizzes WHERE title = 'Visų klausimų testas (e2e)'");
  sql("INSERT INTO quizzes (title, audience, question_count, pass_percent, is_published) VALUES ('Visų klausimų testas (e2e)', 'karateka', 5, 50, 1)");
  const qid = +sql("SELECT id FROM quizzes WHERE title = 'Visų klausimų testas (e2e)'");
  await a.goto(`admin/testai.php?id=${qid}`);
  await a.check('#qAll');
  check('count field disabled when all', await a.locator('#qCount').isDisabled());
  await nav(a, () => a.locator('form:has(#qAll) button[type=submit]').first().click());
  check('saved as all questions', +sql(`SELECT question_count FROM quizzes WHERE id = ${qid}`) === 0);
  sql(`DELETE FROM quizzes WHERE id = ${qid}`);
  noJsErrors(a);
});

test('Budo spectators: find yourself in the schedule, YouTube stream with start date', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1300, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.waitForTimeout(1500);   // santrauka išsaugoma serveryje

  // Transliacija: YouTube nuoroda ir data
  await a.click('#share-toggle');
  await a.fill('#stream-url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
  await a.fill('#stream-at', '2027-02-06T10:00');
  await a.click('#stream-save');
  await a.waitForFunction(() => /Transliacija išsaugota/.test(document.getElementById('save-state').textContent));
  check('stream saved', sql(`SELECT CONCAT(stream_url, '|', stream_at) FROM tournaments WHERE id = ${tid}`) === 'https://www.youtube.com/watch?v=dQw4w9WgXcQ|2027-02-06 10:00:00');

  const share = sql(`SELECT share_token FROM tournaments WHERE id = ${tid}`);
  const v = await login(browser, null, 390, 844);
  await v.goto(`varzybos-gyvai.php?k=${share}`);
  check('stream block with start date', /prasidės/.test(await v.textContent('#gv-stream')) && await v.locator('#gv-stream iframe[src*="youtube-nocookie.com/embed/dQw4w9WgXcQ"]').count() === 1);
  await v.waitForSelector('.gv-area');
  // Vardas iš tinklų: pirmas dalyvis bet kurioje grupėje
  const person = JSON.parse(sql(`SELECT results FROM tournaments WHERE id = ${tid}`));
  let who = '';
  for (const b of Object.values(person.brackets || {})) {
    const g = (b.groups || []).find(x => (x.matches || []).length && x.matches[0].a);
    if (g) { who = g.matches[0].a; break; }
    if ((b.people || []).length) { who = b.people[0].n; break; }
  }
  check('found a participant name to search', who.length > 2, who);
  await v.fill('#gv-q', who.split(' ').pop());
  await v.waitForSelector('#gv-found .gv-found li, #gv-found p');
  check('search finds the participant with category and tatami', await v.locator('#gv-found li', { hasText: who }).count() >= 1
    && await v.locator('#gv-found .gf-cat').count() >= 1);
  await v.locator('#gv-found .gf-cat').first().click();
  await v.waitForSelector('.gv-sheet:not([hidden])');
  check('opens the category', await v.locator('.gv-sheet:not([hidden])').count() === 1);

  // Tatamio ekrane: be kairiojo sąrašo, „Ruošiasi“ langeliuose
  const scr = await login(browser, null, 1280, 720);
  await scr.goto(`varzybos-gyvai.php?k=${share}&ekranas=1&tatami=1`);
  await scr.waitForSelector('.scr-stage');
  check('screen: no groups list', await scr.locator('.scr-groups').count() === 0);
  check('screen: who is preparing', await scr.locator('.scr-prep').count() >= 1);
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a, v, scr);
});
