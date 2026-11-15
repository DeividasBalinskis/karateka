// v1.28 (Denio pastabos apie karateka.lt): klubo treneriai ir pagalbiniai instruktoriai atskirai, „Kontaktai“ su vietomis žemėlapyje,
// privatumo tekstas, pokalbis atsinaujina be perkrovimo (ir atsakymas išsiunčiamas be perkrovimo), iššokantis pranešimas treneriui
// apie registraciją į egzaminą, kontaktai Excel formatu (nariai, Taikos klubai, egzamino protokolas).
const { test } = require('@playwright/test');
const { execSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { sql, login, check, noJsErrors, ids, day } = require('./helpers');

/** Excel failo (.xlsx) lapo XML */
function sheetXml(buf) {
  const f = path.join(os.tmpdir(), 'e2e-' + Date.now() + '.xlsx');
  fs.writeFileSync(f, buf);
  try { return execSync(`unzip -p ${f} xl/worksheets/sheet1.xml`).toString(); } finally { fs.unlinkSync(f); }
}

test('home: coaches and assistant instructors, contacts with map links; privacy text', async ({ browser }) => {
  const p = await login(browser, null);
  await p.goto('index.php');
  const coaches = await p.locator('.ins-coaches .instructor-card .name').allInnerTexts();
  const helpers = await p.locator('.ins-helpers .instructor-card .name').allInnerTexts();
  check('coaches: Denis and Lukas', coaches.length === 2 && coaches.some(n => /Denis/.test(n)) && coaches.some(n => /Lukas/.test(n)), coaches.join(','));
  check('assistant instructors separate', helpers.length >= 3 && !helpers.some(n => /Denis|Lukas/.test(n)), helpers.join(','));
  check('group titles', /Klubo treneriai/.test(await p.textContent('.ins-coaches')) && /Pagalbiniai instruktoriai/.test(await p.textContent('.ins-helpers')));
  check('contacts title', (await p.locator('.hb-contacts h2').innerText()).trim() === 'Kontaktai');
  const map = await p.locator('.contact-locs a[href*="google.com/maps"]').count();
  check('training places link to the map', map >= 3, String(map));
  await p.goto('privatumas.php');
  check('privacy: administrator', /prižiūri administratorius Deividas Balinskis/.test(await p.textContent('main')));
  noJsErrors(p);
});

test('chat updates without reload; reply sent without reload', async ({ browser }) => {
  const { jonas } = ids();
  const a = await login(browser, 'info@karateka.lt');
  await a.goto(`admin/zinutes.php?m=${jonas}`);
  await a.evaluate(() => { window.__sameDoc = 1; });
  // Jei pokalbio dar nėra - pirmoji žinutė per formą (atsiras tema)
  if (!(await a.locator('.msg-topic[id]').count())) {
    sql(`INSERT INTO member_messages (member_id, author_id, from_staff, body) SELECT ${jonas}, id, 0, 'Labas (e2e pradžia)' FROM accounts WHERE email = 'info@karateka.lt'`);
    await a.reload(); await a.evaluate(() => { window.__sameDoc = 1; });
  }
  const topic = a.locator('.msg-topic[id]').first();
  const topicId = await topic.getAttribute('id');
  // Narys parašo - žinutė atsiranda be perkrovimo
  const memberAcc = sql(`SELECT a.id FROM accounts a JOIN account_members am ON am.account_id = a.id WHERE am.member_id = ${jonas} LIMIT 1`) || sql("SELECT id FROM accounts WHERE email = 'info@karateka.lt'");
  const kind = topicId === 'general' ? '' : topicId;
  const col = { n: 'note_id', l: 'lesson_id', e: 'event_id', w: 'news_id' }[kind[0]] || null;
  sql(`INSERT INTO member_messages (member_id, author_id, from_staff, body${col ? ', ' + col : ''}) VALUES (${jonas}, ${memberAcc}, 0, 'Gyva žinutė e2e'${col ? ', ' + kind.slice(1) : ''})`);
  await a.waitForFunction(() => /Gyva žinutė e2e/.test(document.body.innerText), null, { timeout: 12000 });
  check('new message appeared without reload', await a.evaluate(() => window.__sameDoc === 1));
  // Atsakymas - be perkrovimo, laukelis išvalomas
  const ta = a.locator(`#${topicId} form.msg-form textarea`);
  await ta.fill('Atsakymas be perkrovimo e2e');
  await a.locator(`#${topicId} form.msg-form [type=submit]`).click();
  await a.waitForFunction(() => /Atsakymas be perkrovimo e2e/.test(document.querySelector('.msg-topic').innerText + document.body.innerText), null, { timeout: 8000 });
  check('reply shown, no reload, textarea cleared', await a.evaluate(() => window.__sameDoc === 1) && (await ta.inputValue()) === '');
  sql("DELETE FROM member_messages WHERE body IN ('Gyva žinutė e2e', 'Atsakymas be perkrovimo e2e', 'Labas (e2e pradžia)')");
  noJsErrors(a);
});

test('exam registration pops up for coaches; contacts as Excel', async ({ browser }) => {
  const { jonas } = ids();
  // Registracija į egzaminą: pranešimas ir treneriui (ne tik adminui)
  sql(`INSERT INTO events (type, title, starts_on, start_time) VALUES ('exam', 'Kyu egzaminas (iššokimas e2e)', '${day(5)}', '11:00')`);
  const ev = +sql("SELECT id FROM events WHERE title = 'Kyu egzaminas (iššokimas e2e)'");
  sql("DELETE FROM notifications WHERE type = 'exam_registration'");
  execSync(`php -r 'require "${path.resolve(__dirname, '../../public_html/app/bootstrap.php')}"; notify_staff("exam_registration", "Užsiregistravo į egzaminą: Jonas Petrauskas", "Kyu egzaminas (iššokimas e2e)", "admin/renginio-dalyviai.php?id=${ev}", ${jonas}, ${ev});'`,
    { env: { ...process.env, DB_HOST: process.env.DB_HOST || '127.0.0.1', DB_USER: process.env.DB_USER || 'root', DB_PASS: process.env.DB_PASS || 'root', DB_NAME: process.env.DB_NAME || 'karateka' } });
  check('coach also notified', +sql("SELECT COUNT(*) FROM notifications n JOIN accounts a ON a.id = n.account_id WHERE n.type = 'exam_registration' AND a.role = 'coach'") >= 1);
  const c = await login(browser, 'treneris@karateka.test');
  await c.goto('admin/');
  await c.waitForSelector('.ntf-card.in');
  check('pop-up shows registration', /Užsiregistravo į egzaminą: Jonas/.test(await c.textContent('.ntf-pop')));
  await c.click('.ntf-card .ntf-ok');
  await c.waitForTimeout(500);
  check('marked read', +sql("SELECT COUNT(*) FROM notifications n JOIN accounts a ON a.id = n.account_id WHERE a.email = 'treneris@karateka.test' AND n.type = 'exam_registration' AND n.read_at IS NULL") === 0);
  await c.reload();
  check('does not come back', await c.locator('.ntf-card').count() === 0);

  // Narių kontaktai Excel
  const a = await login(browser, 'info@karateka.lt');
  const xl = await a.request.get('admin/nariai.php?excel=1');
  check('members excel file', /spreadsheetml/.test(xl.headers()['content-type']));
  const xml = sheetXml(await xl.body());
  check('members excel has contacts', /Tėvų telefonas/.test(xml) && /Petrausk/.test(xml));
  // Egzamino protokolas Excel
  const pr = await a.request.get(`admin/egzamino-protokolas.php?id=${ev}&xlsx=1`);
  check('protocol excel', /spreadsheetml/.test(pr.headers()['content-type']) && /EGZAMINO PROTOKOLAS/.test(sheetXml(await pr.body())));
  // Taikos klubų kontaktai Excel
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  const kx = await a.request.get(`admin/varzybu-sistema.php?id=${tid}&kontaktai=1`);
  const kxml = sheetXml(await kx.body());
  check('clubs contacts excel', /Atsakingas asmuo/.test(kxml) && /Karateka/.test(kxml));
  sql(`DELETE FROM events WHERE id = ${ev}`);
  sql("DELETE FROM notifications WHERE type = 'exam_registration' AND body = 'Kyu egzaminas (iššokimas e2e)'");
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(c, a);
});
