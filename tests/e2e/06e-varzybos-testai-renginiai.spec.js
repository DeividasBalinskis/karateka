// v1.21 (Denio pastabos lapkričio 11-15): Taikos klubų kontaktai, 6 teisėjai, Enter per balus, registracija ir Karatekai (viena eilutė),
// LTKF - tik tikri klubai ir teisėjai iš Karatekos; testai - forma neišsitrina, Enter į kitą laukelį, įkėlimas iš teksto,
// kelių klausimų trynimas, nuoroda be paskyros; renginio priedas (reglamentas); vaikų tvarkaraštis telefone.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, flashes, ids } = require('./helpers');

test('Taika: club contacts, 6 judges, Enter through scores; registration for Karateka with one row; LTKF clubs and judges', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');

  // Klubai: tik LTKF klubai, „☎ Kontaktai“ (Karatekos - iš LTKF sąrašo), įrašytas telefonas išsaugomas
  await a.click('#tab-admin'); await a.click('#tab-klubai');
  await a.waitForSelector('[data-club-contacts]');
  const clubs = await a.$$eval('[data-club-contacts]', bs => bs.map(b => b.dataset.clubContacts).sort().join(', '));
  check('only LTKF clubs', clubs === 'Karate akademija, Karateka, Sanrei, Takami, Vilniaus karate mokykla', clubs);
  await a.click('[data-club-contacts="Karateka"]');
  check('contacts prefilled from LTKF list', (await a.inputValue('[data-ci-club="Karateka"][data-ci-field="email"]')) === 'info@karateka.lt');
  await a.click('[data-club-contacts="Sanrei"]');
  await a.fill('[data-ci-club="Sanrei"][data-ci-field="phone"]', '+370 600 00001');
  await a.waitForTimeout(1500);
  check('club contact saved', /\+370 600 00001/.test(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)));

  // Balai: 6 teisėjai galimi; Enter - kitas teisėjas, po paskutinio - kitas dalyvis
  await a.click('#tab-admin'); await a.click('#tab-kategorijos');
  await a.click('[data-catview=\"edit\"]');   // viena kategorija (numatyta - struktūra)
  await a.waitForSelector('#cat-list button');
  await a.locator('#cat-list button').first().click();
  await a.click('[data-sys="score"]');
  await a.waitForTimeout(300);
  check('judges: any number', await a.locator('#cat-detail input[data-cfg="judges"]').count() === 1);
  await a.fill('#cat-detail input[data-cfg="judges"]', '3');
  await a.locator('#cat-detail input[data-cfg="judges"]').dispatchEvent('change');
  await a.click('#cat-draw');
  await a.waitForSelector('#draw-view .j-in');
  const first = a.locator('#draw-view .j-in[data-j="0"]').first();
  const sp = await first.getAttribute('data-sp');
  await first.fill('7');
  await a.keyboard.press('Enter');   // vienas skaitmuo - laukia, Enter - kitas teisėjas
  await a.waitForTimeout(120);
  await a.keyboard.type('75');       // 75 → 7.5 ir pats pereina prie kito teisėjo (#61/#67)
  await a.waitForTimeout(200);
  const auto = await a.evaluate(() => ({ j: document.activeElement.dataset.j, prev: (document.querySelector('#draw-view .j-in[data-j="1"]') || {}).value }));
  check('full score jumps to the next judge by itself', auto.j === '2' && auto.prev === '7.5', JSON.stringify(auto));
  await a.keyboard.type('8');
  await a.keyboard.press('Enter');
  await a.waitForTimeout(200);
  const focus = await a.evaluate(() => ({ sp: document.activeElement.dataset.sp, j: document.activeElement.dataset.j }));
  check('Enter after last judge goes to next participant', focus.sp && focus.sp !== sp && focus.j === '0', JSON.stringify(focus));
  await a.waitForTimeout(900);
  check('scores saved', /"7.5"|7\.5/.test(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)));

  // Registracija: Karateka sąraše, iš pradžių viena eilutė, „+ Pridėti dar vieną“ ir ×
  await a.click('#share-toggle');
  await a.check('#reg-open');
  await a.waitForTimeout(500);
  const reg = sql(`SELECT reg_token FROM tournaments WHERE id = ${tid}`);
  const c = await login(browser, null, 390, 844);
  await c.goto(`varzybos-registracija.php?k=${reg}`);
  check('Karateka in club list', await c.locator('#regClubs option[value="Karateka"]').count() === 1);
  check('one participant row at start', await c.locator('.reg-row:not(.reg-head)').count() === 1);
  await c.click('#regMore');
  await c.click('#regMore');
  check('rows added', await c.locator('.reg-row:not(.reg-head)').count() === 3);
  check('rows numbered', (await c.locator('.reg-row:not(.reg-head) .reg-n').last().innerText()) === '3');
  await c.locator('.reg-row:not(.reg-head) .reg-del').nth(1).click();
  check('row removed', await c.locator('.reg-row:not(.reg-head)').count() === 2);

  // LTKF: teisėjai iš Karatekos narių su žyma „Teisėjas“
  sql(`INSERT IGNORE INTO member_tags (member_id, tag) VALUES (${ids().tomas}, 'teisejas')`);
  const tomas = sql(`SELECT CONCAT(first_name, ' ', last_name) FROM members WHERE id = ${ids().tomas}`);
  await a.goto('ltkf/teisejai.php');
  check('LTKF judges list from Karateka', (await a.locator('table.judges').innerText()).includes(tomas.split(' ')[0]));
  await a.goto('ltkf/klubai.php');
  check('LTKF without example clubs', !/Danas|Sokratus/.test(await a.locator('main').innerText()));
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a, c);
});

test('quiz: form keeps input, Enter to next answer, import from text, bulk delete, guest link', async ({ browser }) => {
  sql("INSERT INTO quizzes (title, audience, question_count, pass_percent, is_published) VALUES ('Denio testas (e2e)', 'karateka', 5, 50, 1)");
  const qz = +sql("SELECT id FROM quizzes WHERE title = 'Denio testas (e2e)'");
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto(`admin/testai.php?id=${qz}`);
  const f = a.locator('#naujas form.q-form');

  // Enter ir ↓ - į kitą atsakymą
  await f.locator('[name=body]').fill('Kiek sekundžių skiriama atakai Ko-Go kumite?');
  await f.locator('[name="ans_body[0]"]').fill('5');
  await a.keyboard.press('Enter');
  check('Enter moves to next answer', await a.evaluate(() => document.activeElement.name) === 'ans_body[1]');
  await a.keyboard.type('8');
  await a.keyboard.press('ArrowDown');
  check('Arrow down moves to next answer', await a.evaluate(() => document.activeElement.name) === 'ans_body[2]');

  // Nepažymėtas teisingas: naršyklėje nesiunčiama, laukeliai lieka
  await f.locator('button[type=submit]').click();
  await a.waitForTimeout(300);
  check('client check shown', await f.locator('.q-err').isVisible());
  check('nothing lost', (await f.locator('[name="ans_body[1]"]').inputValue()) === '8');
  // Net jei serveris atmeta - forma grąžinama su įvestais duomenimis
  await nav(a, () => a.evaluate(() => document.querySelector('#naujas form.q-form').submit()));
  check('server error keeps question', (await a.locator('#naujas [name=body]').inputValue()).includes('Ko-Go'));
  check('server error keeps answers', (await a.locator('#naujas [name="ans_body[1]"]').inputValue()) === '8');
  check('server error shown in form', /teising/i.test(await a.locator('#naujas .flash-err').first().innerText()));
  await a.check('#naujas input[name=correct][value="1"]');
  await nav(a, () => a.locator('#naujas form.q-form button[type=submit]').click());
  check('question saved', sql(`SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ${qz}`) === '1');

  // Įkėlimas iš teksto (kaip iš ChatGPT): peržiūra, tada įkelti; klaidingas praleidžiamas
  await a.click('#ikelti summary');
  await a.fill('#ikelti textarea[name=import_text]', '1. Kas yra kiai?\nA) šūksnis\nB) smūgis\nAtsakymas: A\n\n2. Kiek teisėjų kata finale?\nA) 3\nB) 5\nC) 7\nAtsakymas: B\nPaaiškinimas: pagal taisykles\n\n3. Be teisingo?\nA) x\nB) y\n');
  await nav(a, () => a.click('#ikelti button:has-text("Peržiūrėti")'));
  check('import preview', /2\s*tinkam/.test(await a.locator('#ikelti').innerText()));
  await nav(a, () => a.click('#ikelti button:has-text("Įkelti 2")'));
  check('imported', sql(`SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ${qz}`) === '3', await flashes(a));
  check('import keeps correct answer', sql(`SELECT a.body FROM quiz_answers a JOIN quiz_questions q ON q.id = a.question_id WHERE q.quiz_id = ${qz} AND q.body LIKE 'Kiek teisėjų%' AND a.is_correct = 1`) === '5');

  // Keli klausimai iš karto
  await a.locator('.q-pick input').nth(1).check();
  await a.locator('.q-pick input').nth(2).check();
  await nav(a, () => a.click('#bulkDel button[type=submit]'));
  check('bulk delete', sql(`SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ${qz}`) === '1');

  // Nuoroda be paskyros
  await nav(a, () => a.check('#nuoroda input[name=share_open]'));
  const link = await a.inputValue('#quizShareUrl');
  check('share link', /testas-svecias\.php\?k=[a-f0-9]{24}/.test(link), link);
  const g = await login(browser, null, 390, 844);
  await g.goto(link.replace(/^https?:\/\/[^/]+\//, ''));
  await g.fill('input[name=name]', 'Svečias Testuotojas');
  await g.fill('input[name=club]', 'Sanrei');
  await nav(g, () => g.click('#guestStart button[type=submit]'));
  await g.locator('.q-answer', { hasText: '8' }).first().click();
  await nav(g, () => g.click('#quizForm button[type=submit]'));
  check('guest sees result', /100 %/.test(await g.locator('.result-big').innerText()));
  check('guest result stored', sql(`SELECT CONCAT(name, '|', club, '|', score, '/', total, '|', passed) FROM quiz_guest_attempts WHERE quiz_id = ${qz}`) === 'Svečias Testuotojas|Sanrei|1/1|1');
  await a.reload();
  check('admin sees guest', /Svečias Testuotojas/.test(await a.locator('#nuoroda').innerText()));
  await nav(a, () => a.uncheck('#nuoroda input[name=share_open]'));
  const closed = await g.goto(link.replace(/^https?:\/\/[^/]+\//, ''));
  check('closed link 404', closed.status() === 404);
  sql(`DELETE FROM quizzes WHERE id = ${qz}`);
  noJsErrors(a, g);
});

test('event attachment (regulations) and kids schedule on phone', async ({ browser }) => {
  const ev = +sql("SELECT id FROM events WHERE starts_on >= CURDATE() ORDER BY starts_on LIMIT 1");
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto(`admin/renginiai.php?edit=${ev}`);
  await a.setInputFiles('input[name=file]', { name: 'Reglamentas 2026.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n% e2e\n') });
  await nav(a, () => a.click('form.form button.btn-primary[type=submit]'));
  const path = sql(`SELECT file_path FROM events WHERE id = ${ev}`);
  check('attachment stored', /^[a-f0-9]{20}\.pdf$/.test(path) && sql(`SELECT file_name FROM events WHERE id = ${ev}`) === 'Reglamentas 2026.pdf', path);
  const p = await login(browser, null, 390, 844);
  await p.goto(`renginys.php?id=${ev}`);
  const link = p.locator('a.event-file');
  check('download link on event page', await link.count() === 1 && (await link.getAttribute('download')) === 'Reglamentas 2026.pdf');
  const res = await p.request.get(await link.getAttribute('href'));
  check('file downloadable', res.status() === 200);
  await a.goto(`admin/renginiai.php?edit=${ev}`);
  await a.check('input[name=file_remove]');
  await nav(a, () => a.click('form.form button.btn-primary[type=submit]'));
  check('attachment removed', sql(`SELECT COALESCE(file_path, '') FROM events WHERE id = ${ev}`) === '');

  // Pagrindinis puslapis telefone: tvarkaraštis rodomas iš karto (pirma vieta), paspaudus kitą - pasikeičia
  await p.goto('index.php');
  const panel = p.locator('#vaikamsSchedulePanel');
  check('schedule shown without tapping', !/Pasirinkite|Paspauskite/.test(await panel.innerText()) && /\d{1,2}:\d{2}/.test(await panel.innerText()));
  await p.locator('.picker-locations details.loc-group summary').nth(1).click();
  const second = p.locator('.picker-locations details.loc-group').nth(1).locator('li[data-schedule]').first();
  const name = await second.getAttribute('data-name');
  await second.click();
  check('tap changes schedule', (await panel.innerText()).includes(name));
  noJsErrors(a, p);
});
