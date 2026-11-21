// v1.50: testų peržiūra Deniui (✕, komentarai, taisymas vietoje, paveikslėliai, ZIP, pastabos ChatGPT),
// „👥 Visi nariai“ aktyvumo taškuose (#141), paskyros (#142-#144), žaidimas paslėptas (#140), gongai (#151).
const { test } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const os = require('os');
const { execSync } = require('child_process');
const { sql, login, check, noJsErrors } = require('./helpers');

/** ZIP su klausimai.txt ir Q1.png (sukuriamas PHP, kad nereikėtų papildomų paketų) */
function makeZip() {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'qzip-'));
  fs.writeFileSync(path.join(dir, 'klausimai.txt'), [
    '1. E2E-P Kokia stovėsena paveikslėlyje?', 'A) Zenkutsu-dachi', 'B) Kiba-dachi', 'C) Kokutsu-dachi', 'Atsakymas: A', 'Paveikslėlis: Q1.png', '',
    '2. E2E-P Kiek kyu iki juodo diržo?', 'A) 9', 'B) 5', 'C) 12', 'Atsakymas: A', 'Paveikslėlis: diržų eilė nuo balto iki rudo', '',
    '3. E2E-P Ką reiškia „oss“?', 'A) Pagarbą ir pasiruošimą', 'B) Pabaigą', 'Atsakymas: A', ''].join('\n'));
  const zip = path.join(dir, 'paketas.zip');
  execSync(`php -r '$i = imagecreatetruecolor(120, 90); imagefill($i, 0, 0, imagecolorallocate($i, 200, 80, 60)); imagepng($i, "${dir}/Q1.png");
    $z = new ZipArchive(); $z->open("${zip}", ZipArchive::CREATE); $z->addFile("${dir}/klausimai.txt", "klausimai.txt"); $z->addFile("${dir}/Q1.png", "Q1.png"); $z->close();'`);
  return zip;
}

test('quiz review: ZIP with an image → for review (hidden from members) → ✕ / 💬 / edit → notes for ChatGPT → fixed file back → approve', async ({ browser }) => {
  const qid = +sql('SELECT id FROM quizzes ORDER BY id LIMIT 1');
  // Švari pradžia: šio testo juodraščiai ir pastabos (likę po ankstesnių bandymų)
  sql(`DELETE FROM quiz_questions WHERE quiz_id = ${qid} AND (is_draft = 1 OR body LIKE 'E2E%')`);
  sql(`DELETE r FROM quiz_review r JOIN quiz_questions q ON q.id = r.question_id WHERE q.quiz_id = ${qid}`);
  const a = await login(browser, 'info@karateka.lt', 375, 812);
  await a.goto(`admin/testai.php?id=${qid}#ikelti`);
  await a.locator('#ikelti > summary').click();
  await a.setInputFiles('#ikelti input[name="import_files[]"]', makeZip());
  await Promise.all([a.waitForNavigation(), a.click('#ikelti button:has-text("Peržiūrėti")')]);
  check('preview: 3 questions, the picture found', await a.locator('.import-preview tbody tr').count() === 3 && await a.locator('.import-preview img.imp-thumb').count() === 1);
  await Promise.all([a.waitForNavigation(), a.click('#ikelti button[name=draft][value="1"]')]);
  check('opens the review page', a.url().includes('admin/testo-perziura.php'), a.url());
  check('saved as drafts with the picture and the picture note',
    sql("SELECT COUNT(*) FROM quiz_questions WHERE body LIKE 'E2E-P%' AND is_draft = 1") === '3'
    && sql("SELECT COUNT(*) FROM quiz_questions WHERE body LIKE 'E2E-P%' AND image IS NOT NULL") === '1'
    && /diržų eilė/.test(sql("SELECT image_note FROM quiz_questions WHERE body LIKE 'E2E-P Kiek%'")));
  const ids = sql("SELECT id FROM quiz_questions WHERE body LIKE 'E2E-P%' ORDER BY sort_order").split('\n').map(Number);

  const card = () => a.locator('.rv-card:not([hidden])');
  check('one question at a time', await a.locator('.rv-card:not([hidden])').count() === 1);
  await card().locator('.rv-ans').nth(1).locator('.rv-x').click();
  await card().locator('.rv-head .rv-c').click();
  await card().locator('.rv-comment[data-aid="0"] textarea').fill('Per lengvas');
  await card().locator('.rv-body').fill('E2E-P Kokia stovėsena paveikslėlyje? (pataisyta)');
  await card().locator('.rv-body').blur();
  await a.waitForTimeout(900);
  await card().locator('.rv-next').click();
  await a.waitForTimeout(300);
  await card().locator('.rv-head .rv-x').click();
  await card().locator('.rv-next').click();
  await a.waitForTimeout(600);
  check('saved: ✕ answer, comment, ✕ question, edited text',
    sql(`SELECT COUNT(*) FROM quiz_review WHERE question_id = ${ids[0]} AND answer_id > 0 AND flag = 1`) === '1'
    && sql(`SELECT comment FROM quiz_review WHERE question_id = ${ids[0]} AND answer_id = 0`) === 'Per lengvas'
    && sql(`SELECT flag FROM quiz_review WHERE question_id = ${ids[1]} AND answer_id = 0`) === '1'
    && /pataisyta/.test(sql(`SELECT body FROM quiz_questions WHERE id = ${ids[0]}`)));
  const prog = [await a.textContent('#rvSeen'), await a.textContent('#rvNoted')];
  check('progress: 3 seen, 2 with notes', prog[0] === '3' && prog[1] === '2', JSON.stringify(prog));

  const r = await a.request.get(`admin/testo-perziura.php?id=${qid}&pastabos=1`);
  const notes = await r.text();
  check('notes file: only the 2 marked questions, with >> lines', (notes.match(/^\d+\. \[#\d+\]/mg) || []).length === 2
    && /\n>> ✕ Atsakymas B netinka/.test(notes) && /\n>> Komentaras: Per lengvas/.test(notes) && /\n>> ✕ Visas klausimas netinka/.test(notes), notes);

  // ChatGPT pataisė: tas pats failas be „>>“, kitas atsakymas B - įkeliamas atgal peržiūrai
  const fixed = notes.replace(/^>>.*$/mg, '').replace('B) Kiba-dachi', 'B) Kokutsu-dachi (atgal)');
  await a.goto(`admin/testai.php?id=${qid}#ikelti`);
  await a.locator('#ikelti > summary').click();
  await a.fill('#ikelti textarea[name=import_text]', fixed);
  await Promise.all([a.waitForNavigation(), a.click('#ikelti button:has-text("Peržiūrėti")')]);
  check('fixed ones recognised', await a.locator('.import-preview .badge', { hasText: 'pataisomas' }).count() === 2);
  await Promise.all([a.waitForNavigation(), a.click('#ikelti button[name=draft][value="1"]')]);
  check('fixed: old notes cleared, waiting for review again', sql(`SELECT COUNT(*) FROM quiz_review WHERE question_id IN (${ids[0]}, ${ids[1]}) AND (flag = 1 OR comment <> '')`) === '0'
    && sql(`SELECT SUM(is_draft) FROM quiz_questions WHERE id IN (${ids[0]}, ${ids[1]})`) === '2'
    && sql(`SELECT COUNT(*) FROM quiz_answers WHERE question_id = ${ids[0]} AND body = 'Kokutsu-dachi (atgal)'`) === '1');
  check('the picture stayed', sql(`SELECT image IS NOT NULL FROM quiz_questions WHERE id = ${ids[0]}`) === '1');

  // Antras ratas: viskas gerai - tik pereinama; patvirtinama
  await a.goto(`admin/testo-perziura.php?id=${qid}&rodyti=juodrasciai`);
  for (let i = 0; i < 2; i++) { await card().locator('.rv-next').click(); await a.waitForTimeout(250); }
  await Promise.all([a.waitForNavigation(), a.click('#rvDone button:has-text("Įtraukti į testą")')]);
  check('approved into the quiz', sql("SELECT COUNT(*) FROM quiz_questions WHERE body LIKE 'E2E-P%' AND is_draft = 0") === '3');
  sql("DELETE FROM quiz_questions WHERE body LIKE 'E2E-P%'");
  noJsErrors(a);
});

test('quiz review on a computer: add a picture, add and delete an answer, change the correct one', async ({ browser }) => {
  const qid = +sql('SELECT id FROM quizzes ORDER BY id LIMIT 1');
  sql(`DELETE FROM quiz_questions WHERE quiz_id = ${qid} AND (is_draft = 1 OR body LIKE 'E2E%')`);
  sql(`INSERT INTO quiz_questions (quiz_id, body, sort_order, is_draft) VALUES (${qid}, 'E2E-K klausimas?', 9999, 1)`);
  const q = +sql("SELECT id FROM quiz_questions WHERE body = 'E2E-K klausimas?'");
  sql(`INSERT INTO quiz_answers (question_id, body, is_correct, sort_order) VALUES (${q}, 'Taip', 1, 0), (${q}, 'Ne', 0, 1)`);
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto(`admin/testo-perziura.php?id=${qid}&rodyti=juodrasciai`);
  const card = a.locator(`.rv-card[data-qid="${q}"]`);
  const png = path.join(os.tmpdir(), 'e2e-k.png');
  execSync(`php -r '$i = imagecreatetruecolor(80, 60); imagepng($i, "${png}");'`);
  await card.locator('.rv-img input[type=file]').setInputFiles(png);
  await a.waitForTimeout(800);
  check('picture uploaded', await card.locator('.rv-img img').count() === 1 && sql(`SELECT image IS NOT NULL FROM quiz_questions WHERE id = ${q}`) === '1');
  await card.locator('.rv-ans').nth(1).locator('input[type=radio]').check();
  await a.waitForTimeout(500);
  check('correct answer changed', sql(`SELECT body FROM quiz_answers WHERE question_id = ${q} AND is_correct = 1`) === 'Ne');
  await Promise.all([a.waitForNavigation(), card.locator('.rv-add').click()]);
  check('answer added', sql(`SELECT COUNT(*) FROM quiz_answers WHERE question_id = ${q}`) === '3');
  await a.locator(`.rv-card[data-qid="${q}"] .rv-ans`).nth(2).locator('.rv-del').click();
  await a.waitForTimeout(600);
  check('answer deleted', sql(`SELECT COUNT(*) FROM quiz_answers WHERE question_id = ${q}`) === '2');
  await a.goto(`admin/testai.php?id=${qid}`);
  check('drafts banner and review button', await a.locator('a:has-text("Peržiūrėti klausimus")').count() === 1 && /Peržiūros laukia/.test(await a.textContent('main')));
  check('one-time ChatGPT instructions', /Instructions/.test(await a.textContent('#chatgpt-projektas')) && /\[#123\]/.test(await a.textContent('#projectPrompt')));
  sql(`DELETE FROM quiz_questions WHERE id = ${q}`);
  noJsErrors(a);
});

test('Aktyvumo taškai: „Visi nariai“ lists everyone (also with 0), sorts, and „Kita“ needs a comment (#141)', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/taskai.php?rt=visi');
  const n = await a.locator('#rankAll li').count();
  check('all active members listed', n === +sql("SELECT COUNT(*) FROM members WHERE status = 'active'"), String(n));
  await a.click('.rank-sort [data-sort=year]');
  const years = await a.locator('#rankAll li').evaluateAll(l => l.map(x => +x.dataset.year));
  check('sorted by birth year', years.every((y, i) => i === 0 || years[i - 1] >= y));
  const mid = await a.locator('#rankAll li').first().locator('.rank-xp').getAttribute('data-member');
  const before = +sql(`SELECT COALESCE(SUM(points), 0) FROM point_awards WHERE member_id = ${mid}`);
  await a.locator('#rankAll li').first().locator('.rank-xp').click();
  await a.selectOption('#rqCat', { label: '✎ Kita - įrašysiu pats (komentare)' });
  await a.fill('#rqPts', '7');
  await a.click('#rqGive');
  await a.waitForTimeout(400);
  check('not given without a comment', +sql(`SELECT COALESCE(SUM(points), 0) FROM point_awards WHERE member_id = ${mid}`) === before);
  await a.fill('#rqNote', 'E2E už pagalbą salėje');
  await a.click('#rqGive');
  await a.waitForTimeout(600);
  check('given with the comment', +sql(`SELECT COALESCE(SUM(points), 0) FROM point_awards WHERE member_id = ${mid}`) === before + 7);
  sql("DELETE FROM point_awards WHERE note = 'E2E už pagalbą salėje'");
  noJsErrors(a);
});

test('accounts: name change, repeat password, tester badge for any role, log out from ▾ (#142-#144)', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 375, 812);
  await a.goto('admin/nustatymai.php#paskyra');
  await a.locator('#paskyra details.setting:has(input[name=first_name]) > summary').click();
  const first = sql("SELECT first_name FROM accounts WHERE email = 'info@karateka.lt'");
  await a.fill('#paskyra input[name=first_name]', 'Denis E2E');
  await Promise.all([a.waitForNavigation(), a.click('#paskyra form:has(input[name=first_name]) button[type=submit]')]);
  check('name changed', sql("SELECT first_name FROM accounts WHERE email = 'info@karateka.lt'") === 'Denis E2E');
  sql(`UPDATE accounts SET first_name = '${first}' WHERE email = 'info@karateka.lt'`);
  await a.goto('admin/nustatymai.php#paskyra');
  await a.locator('#paskyra details.setting:has(input[name=password2]) > summary').click();
  await a.fill('#paskyra form:has(input[name=password2]) input[name=current]', 'testas123');
  await a.fill('#paskyra input[name=password]', 'naujas-slapt-1');
  await a.fill('#paskyra input[name=password2]', 'kitas-slapt-2');
  await Promise.all([a.waitForNavigation(), a.click('#paskyra form:has(input[name=password2]) button[type=submit]')]);
  check('mismatch refused', /nesutampa/.test(await a.textContent('body')));
  check('eye button on password fields', await a.locator('#paskyra .pw-eye').count() >= 2);

  await a.goto('admin/paskyros.php?q=narys');
  const row = a.locator('tr', { hasText: 'narys@karateka.test' });
  check('tester switch for a member account too', await row.locator('.tester-tg button').count() === 1);
  check('log out inside the ▾ view panel', await a.locator('.vs-logout').count() >= 1);
  noJsErrors(a);
});

test('game hidden: Dojo in the menu only for real admins while closed to members (#140, decision 2026-11-21)', async ({ browser }) => {
  sql("INSERT INTO site_settings (name, value) VALUES ('game_access', '[]') ON DUPLICATE KEY UPDATE value = '[]'");
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/');
  check('real admin sees Dojo in the menu', await a.locator('.site-header a[href*="dojo.php"]').count() >= 1);
  const m = await login(browser, 'narys@karateka.test', 1440, 900);
  await m.goto('paskyra.php');
  check('member: no Dojo link in the menu', await m.locator('.site-header a[href*="dojo.php"]').count() === 0);
  await m.context().close();
  await a.goto('admin/testavimas.php');
  check('admin link to try it', await a.locator('a:has-text("Atidaryti Dojo")').count() === 1);
  sql("UPDATE site_settings SET value = 'all' WHERE name = 'game_access'");
  noJsErrors(a);
});

test('trial form (plan A5): CSRF, robot trap, too fast, server checks, 3 per hour', async ({ browser }) => {
  sql("DELETE FROM form_submissions WHERE form = 'trial'");
  const ctx = await browser.newContext({ viewport: { width: 375, height: 812 }, baseURL: process.env.BASE_URL || 'http://127.0.0.1:8088/' });
  const p = await ctx.newPage();
  const r405 = await p.request.get('uzklausa.php');
  check('GET refused with JSON', r405.status() === 405 && (await r405.json()).ok === false);
  const rCsrf = await p.request.post('uzklausa.php', { form: { vardas: 'A', email: 'a@b.lt', telefonas: '+37060000000', lokacija: 'X' } });
  check('no CSRF - refused', rCsrf.status() === 400);

  const fill = async (email, phone = '+370 600 00000') => {
    await p.fill('#registrationForm input[name=vardas]', 'E2E Bandomasis');
    await p.fill('#registrationForm input[name=email]', email);
    await p.fill('#registrationForm input[name=telefonas]', phone);
    await p.selectOption('#registrationForm select[name=lokacija]', { index: 1 });
  };
  const submit = async () => { await p.click('#registrationForm button[type=submit]'); await p.waitForFunction(() => !/Siunčiama/.test(document.getElementById('formStatus').textContent)); return p.textContent('#formStatus'); };
  const rows = () => +sql("SELECT COUNT(*) FROM form_submissions WHERE form = 'trial'");

  // Per greitai: siunčiama iškart, vos gavus puslapio HTML (žmogus taip greitai neužpildo)
  await p.goto('index.php', { waitUntil: 'domcontentloaded' });
  const fast = await p.evaluate(async () => {
    const f = document.getElementById('registrationForm'), d = new FormData(f);
    d.set('vardas', 'E2E Greitas'); d.set('email', 'e2e-greitas@karateka.test'); d.set('telefonas', '+37060000000'); d.set('lokacija', 'X');
    const r = await fetch('uzklausa.php', { method: 'POST', body: d });
    return r.status + ' ' + JSON.stringify(await r.json());
  });
  check('too fast - looks sent, nothing saved', /"ok":true/.test(fast) && rows() === 0, fast);

  await p.goto('index.php');
  await fill('e2e-robotas@karateka.test');
  await p.evaluate(() => { document.querySelector('#registrationForm input[name=website]').value = 'http://spam.example'; });
  await p.waitForTimeout(3200);
  check('robot trap - looks sent, nothing saved', /Ačiū/.test(await submit()) && rows() === 0);

  await p.goto('index.php');
  await fill('e2e-zmogus@karateka.test', 'skambink');
  await p.waitForTimeout(3200);
  check('bad phone - clear Lithuanian message', /telefono/.test(await submit()) && rows() === 0);
  await p.fill('#registrationForm input[name=telefonas]', '+370 600 00000');
  const ok = await submit();
  check('valid - saved (and sent when mail works)', rows() === 1 && (/Ačiū/.test(ok) || /Nepavyko išsiųsti/.test(ok)), ok);

  // Dar dvi užklausos iš to paties IP (kaip įrašė serveris) - ketvirta turi būti sustabdyta
  sql("INSERT INTO form_submissions (form, ip, email, created_at) SELECT 'trial', ip, CONCAT('x', n, '@karateka.test'), created_at FROM form_submissions, (SELECT 1 AS n UNION SELECT 2) k WHERE form = 'trial' LIMIT 2");
  await p.goto('index.php');
  await fill('e2e-ketvirtas@karateka.test');
  await p.waitForTimeout(3200);
  const fourth = await submit();
  check('4th in an hour - stopped', /Jau gavome/.test(fourth) && rows() === 3, fourth + ' / ' + rows());
  sql("DELETE FROM form_submissions WHERE form = 'trial'");
  await ctx.close();
});

test('v1.51: new quiz with a questions file goes straight to review; no bare file inputs, download button has an icon', async ({ browser }) => {
  sql("DELETE FROM quizzes WHERE title = 'E2E naujas su failu'");
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/testai.php?new=1');
  await a.fill('input[name=title]', 'E2E naujas su failu');
  await a.setInputFiles('input[name="import_files[]"]', { name: 'klausimai.txt', mimeType: 'text/plain',
    buffer: Buffer.from('1. E2E-N Kas yra kiai?\nA) Šūksnis\nB) Smūgis\nAtsakymas: A\n\n2. E2E-N Be teisingo?\nA) x\nB) y\n') });
  check('chosen file name shown', /klausimai\.txt/.test(await a.textContent('.file-drop .fd-name')));
  await Promise.all([a.waitForNavigation(), a.click('form:has(input[name=title]) button[type=submit].btn-primary')]);
  const qz = +sql("SELECT id FROM quizzes WHERE title = 'E2E naujas su failu'");
  check('opens the review page', a.url().includes(`testo-perziura.php?id=${qz}`), a.url());
  check('valid question saved for review, broken one skipped', sql(`SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ${qz} AND is_draft = 1`) === '1'
    && /Praleista dėl klaidų: 1/.test(await a.textContent('body')));
  await a.goto(`admin/testai.php?id=${qz}`);
  await a.locator('#ikelti > summary').click();
  const bare = await a.evaluate(() => [...document.querySelectorAll('input[type=file]')].filter(i => !i.closest('.file-drop') && !i.hidden).length);
  check('no bare browser file inputs', bare === 0, String(bare));
  check('download button with an icon', await a.locator('#ikelti a[download] svg.ico').count() === 1);
  await a.locator('details.q-item > summary').first().click();
  check('correct answer marked by the letter button', await a.locator('details.q-item[open] .qf-ok input:checked').count() === 1
    && await a.locator('details.q-item[open] .icon-btn.danger[title="Ištrinti klausimą"]').count() === 1);
  sql(`DELETE FROM quizzes WHERE id = ${qz}`);
  noJsErrors(a);
});

test('B4 phone: view-as is the first row of ⋯ Daugiau; floating buttons hide over the sheet and while typing; 🎨 for a member', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 375, 812);
  await a.goto('admin/patvirtinimai.php');
  await a.click('.m-more > summary');
  await a.waitForFunction(() => document.body.classList.contains('m-more-open'));
  check('view-as buttons in the sheet', await a.locator('.m-vs .vs-modes button').first().isVisible());
  check('no floating buttons over the open sheet', !(await a.locator('.fb-fab').isVisible()));
  await a.keyboard.press('Escape');
  await a.goto('admin/nariai.php');
  await a.locator('main input[type=search], main input[type=text]').first().focus();
  check('floating buttons hidden while typing', !(await a.locator('.fb-fab').isVisible()));
  const m = await login(browser, 'tevai@karateka.test', 375, 812);
  await m.goto('paskyra.php');
  check('theme picker script for a member', await m.locator('script[src*="theme-pick.js"]').count() === 1);
  noJsErrors(a, m);
});
