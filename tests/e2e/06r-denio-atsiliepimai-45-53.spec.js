// v1.37: Denio atsiliepimai #45-#53 - instruktoriai redaguoja pagrindinį puslapį, „+ Nauja grupė“, atsiliepimų atsakymai suskleisti,
// „Admin ▾“ su „Žiūrėti kaip“, egzamino protokolas: kablelis, skaičiai apačioje, stulpeliai, „Klubas“, Federacijos registras su numeriais.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('home page: instructors listed on the page can edit it, others cannot', async ({ browser }) => {
  sql("UPDATE accounts SET first_name = 'Karolis', last_name = 'Kiela' WHERE email = 'treneris@karateka.test'");
  const c = await login(browser, 'treneris@karateka.test', 1280, 900);
  await c.goto('index.php');
  check('listed instructor sees “Edit page”', await c.locator('.hb-fab').count() === 1);
  await c.goto('index.php?redaguoti=1');
  check('edit mode opens', await c.evaluate(() => document.body.classList.contains('home-edit')));
  sql("UPDATE accounts SET first_name = 'Lukas', last_name = 'Treneris' WHERE email = 'treneris@karateka.test'");
  await c.goto('index.php');
  check('not listed - no editing', await c.locator('.hb-fab').count() === 0);
  noJsErrors(c);
});

test('admin: new group from Training, feedback replies folded, one Admin control, language only the chosen one', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  const aid = +sql("SELECT id FROM accounts WHERE email = 'info@karateka.lt'");
  for (let i = 1; i <= 4; i++) sql(`INSERT INTO notifications (account_id, type, title, body, important) VALUES (${aid}, 'feedback', 'Jūsų atsiliepimas: pataisyta ${i}', 'Ats ${i}', 1)`);
  await a.goto('admin/');
  check('feedback replies: 2 shown, the rest folded', await a.locator('.fb-replies > .notif-list .notif').count() === 2 && await a.locator('.fb-replies details.fb-more .notif').count() >= 2);
  await nav(a, () => a.click('.fb-replies button:has-text("Supratau visus")'));
  check('all marked read', +sql(`SELECT COUNT(*) FROM notifications WHERE account_id = ${aid} AND type = 'feedback' AND done_at IS NULL`) === 0);
  check('one admin control with “view as” (incl. as a member)', await a.locator('.adm-li .view-switch').count() === 1 && await a.locator('.adm-li .vs-member-btn').count() === 1 && await a.locator('.vs-li').count() === 0);
  check('language: only the chosen one, on the left', /LT/.test(await a.locator('.nav-prefs .lang-dd > summary').innerText()));

  await a.goto('admin/treniruote.php');
  await nav(a, () => a.click('a:has-text("+ Nauja grupė")'));
  await a.fill('#nauja-grupe input[name="name"]', 'Teisėjams');
  await a.selectOption('#nauja-grupe select[name="category"]', 'suauge');
  await nav(a, () => a.click('#nauja-grupe button[type="submit"]'));
  check('group for referees created', +sql("SELECT COUNT(*) FROM training_groups WHERE name = 'Teisėjams'") === 1);
  sql("DELETE FROM training_groups WHERE name = 'Teisėjams'");
  noJsErrors(a);
});

test('exam protocol: decimal, live counts, editable columns, club, send to the Federation with permanent numbers', async ({ browser }) => {
  const eid = +sql("SELECT id FROM events WHERE type = 'exam' ORDER BY id LIMIT 1");
  sql(`DELETE FROM exam_protocol_rows WHERE event_id = ${eid}`);
  sql('DELETE FROM federation_exams'); sql('DELETE FROM federation_people');
  sql(`UPDATE exam_protocols SET cols = NULL, org_name = 'LIETUVOS TRADICINIO KARATE DO FEDERACIJA' WHERE event_id = ${eid}`);
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto(`admin/egzamino-protokolas.php?id=${eid}`);
  check('federation title', /LIETUVOS TRADICINIO KARATE DO FEDERACIJA/.test(await a.textContent('.prot-org')));
  await a.click('.prot-add summary');
  await a.fill('.prot-add input[name="name"]', 'Ona Federacinė');
  await a.fill('.prot-add input[name="club"]', 'Sanrei');
  await nav(a, () => a.click('.prot-add button[type="submit"]'));
  const kihon = a.locator('#protTable input[data-f="kihon"]').first();
  await kihon.click(); await a.keyboard.type('71');
  check('decimal after the first digit', (await kihon.inputValue()) === '7.1');
  await a.keyboard.press('Tab'); await a.waitForTimeout(400);
  check('club column saved', (await a.inputValue('#protTable input[data-f="club"]')) === 'Sanrei');
  await a.click('#protTable [data-res="passed"]');
  check('counts update right away', (await a.textContent('#sumPassed')).trim() === '1');

  // Stulpeliai: pridėti „Bunkai“, išimti „Testas“
  await a.click('.prot-cols summary');
  await a.fill('.prot-cols input[name="col_new"]', 'Bunkai');
  const labels = await a.locator('.prot-cols input[name^="col_label"]').evaluateAll(els => els.map(e => e.value));
  await a.check(`.prot-cols input[name="col_del[${labels.indexOf('Testas')}]"]`);
  await nav(a, () => a.click('.prot-cols button[type="submit"]'));
  const heads = await a.locator('#protTable thead tr').nth(1).locator('th').allInnerTexts();
  check('columns edited', heads.includes('Bunkai') && !heads.includes('Testas'), heads.join(' | '));
  const bunkai = a.locator('#protTable input[aria-label="Bunkai"]').first();
  await bunkai.fill('6.5'); await bunkai.press('Tab'); await a.waitForTimeout(400);

  // Federacijai: numeris 26.11.15-1, antrą kartą - tas pats
  await nav(a, () => a.click('.prot-fed button[type="submit"]'));
  const no = (await a.textContent('#protTable .prot-nr b')).trim();
  check('federation number given', /^\d\d\.\d\d\.\d\d-\d+$/.test(no), no);
  await nav(a, () => a.click('.prot-fed button[type="submit"]'));
  check('same person - same number, no duplicates', (await a.textContent('#protTable .prot-nr b')).trim() === no && +sql('SELECT COUNT(*) FROM federation_people') === 1);
  check('scores and club in the register', /Bunkai/.test(sql('SELECT scores FROM federation_exams LIMIT 1')) && sql('SELECT club FROM federation_people LIMIT 1') === 'Sanrei');
  await a.goto('admin/federacija.php');
  check('register lists the person', new RegExp(no.replace(/\./g, '\\.')).test(await a.textContent('main')) && /Ona Federacinė/.test(await a.textContent('main')));
  sql(`DELETE FROM exam_protocol_rows WHERE event_id = ${eid}`); sql('DELETE FROM federation_exams'); sql('DELETE FROM federation_people');
  noJsErrors(a);
});
