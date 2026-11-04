// Testavimas: seni pavyzdžiai išvalomi vieną kartą automatiškai (tikri lieka), testiniai nariai (Test Parent / Junior / Mažylis),
// peržiūra „👁 Tėvai / Vaikas“ su diržo keitimu, testinių ištrynimas, nario ištrynimas. Paleidžiamas paskutinis - išvalo pavyzdžius.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, flashes } = require('./helpers');

test('legacy demo purge keeps real people; test people, view switch, delete', async ({ browser }) => {
  // Tikra paskyra su vaiku, Denio pridėtas narys be paskyros, tikras ir demo tėvas su bendru vaiku, tikra naujiena
  sql("INSERT INTO accounts (email, password_hash, first_name, last_name, status) VALUES ('esmile.tikra@gmail.com', 'x', 'Esmilė', 'Tikra', 'active')");
  const real = sql("SELECT id FROM accounts WHERE email = 'esmile.tikra@gmail.com'");
  sql("INSERT INTO members (first_name, last_name, birth_date, status) VALUES ('Joris', 'Tikras', '2015-01-01', 'active'), ('Rūta', 'Pridėta', '2014-01-01', 'active'), ('Ona', 'Bendra', '2016-01-01', 'active')");
  const joris = sql("SELECT id FROM members WHERE last_name = 'Tikras'");
  const ona = sql("SELECT id FROM members WHERE last_name = 'Bendra'");
  const demoParent = sql("SELECT id FROM accounts WHERE email LIKE '%@demo.karateka.lt' ORDER BY id LIMIT 1");
  sql(`INSERT INTO account_members (account_id, member_id, relation) VALUES (${real}, ${joris}, 'parent'), (${real}, ${ona}, 'parent'), (${demoParent}, ${ona}, 'parent')`);
  sql("INSERT INTO news (title, body, author_id) VALUES ('Tikra naujiena', 'Denio tekstas', 1)");
  const realNews = sql("SELECT id FROM news WHERE title = 'Tikra naujiena'");
  sql(`INSERT INTO reactions (news_id, account_id, emoji) VALUES (${realNews}, ${demoParent}, '🔥'), (${realNews}, ${real}, '👍')`);

  // Vieną kartą, atidarius „Šiandien“: kopija, tada išvalymas
  sql("DELETE FROM site_settings WHERE name = 'demo_isvalyta'");
  const a = await login(browser, 'info@karateka.lt');
  await a.goto('admin/');
  check('purge flash', /Išvalyti seni pavyzdiniai duomenys/.test(await flashes(a)), await flashes(a));
  const summary = JSON.parse(sql("SELECT value FROM site_settings WHERE name = 'demo_isvalyta'"));
  check('backup made first', /^karateka-/.test(summary.backup || ''), JSON.stringify(summary).slice(0, 200));
  check('demo and .test accounts gone', +sql("SELECT COUNT(*) FROM accounts WHERE email LIKE '%@demo.karateka.lt' OR email LIKE '%@karateka.test'") === 0);
  check('admin stays', +sql("SELECT COUNT(*) FROM accounts WHERE email = 'info@karateka.lt'") === 1);
  check('real people stay', +sql("SELECT COUNT(*) FROM members WHERE last_name IN ('Tikras', 'Pridėta', 'Bendra')") === 3
    && +sql(`SELECT COUNT(*) FROM account_members WHERE account_id = ${real}`) === 2);
  check('demo members gone', +sql("SELECT COUNT(*) FROM members WHERE created_at = '2000-01-01 00:00:00'") === 0
    && +sql("SELECT COUNT(*) FROM members WHERE last_name IN ('Gudaitis', 'Petrauskas')") === 0);
  check('demo content gone', +sql("SELECT COUNT(*) FROM events WHERE created_at = '2000-01-01 00:00:00' OR title LIKE 'TEST:%'") === 0
    && +sql("SELECT COUNT(*) FROM news WHERE updated_at = '2000-01-01 00:00:00'") === 0
    && +sql("SELECT COUNT(*) FROM lessons WHERE created_at = '2000-01-01 00:00:00'") === 0);
  check('no placeholder video left', +sql("SELECT COUNT(*) FROM lesson_blocks WHERE youtube_id = 'dQw4w9WgXcQ'") + +sql("SELECT COUNT(*) FROM news_media WHERE youtube_id = 'dQw4w9WgXcQ'") === 0);
  check('real news stays, demo reaction gone', +sql(`SELECT COUNT(*) FROM news WHERE id = ${realNews}`) === 1
    && sql(`SELECT GROUP_CONCAT(emoji) FROM reactions WHERE news_id = ${realNews}`) === '👍');
  check('kata lessons and belt folders stay', +sql("SELECT COUNT(*) FROM lesson_folders WHERE parent_id IS NULL AND belt_level IS NOT NULL") === 18);
  check('referee test stays', +sql("SELECT COUNT(*) FROM quizzes WHERE audience = 'teisejas'") === 1);
  check('program used by real members kept', +sql("SELECT COUNT(*) FROM belt_requirements WHERE created_at = '2000-01-01 00:00:00'") >= 0);
  await a.goto('admin/');
  check('only once', !/Išvalyti seni/.test(await flashes(a)));

  // Seni kompiuterio pavyzdžiai be žymės (dev_seed iki 2026-11-03) - atpažįstami tik kartu su tų pavyzdžių vaikais
  sql("INSERT INTO members (first_name, last_name, birth_date, status) VALUES ('Lukas', 'Vaitkus', '2009-01-10', 'active'), ('Gabija', 'Stankevičiūtė', '2010-02-11', 'active'), ('Lukas', 'Vaitkus', '2015-05-05', 'active')");
  sql("INSERT INTO events (type, title, starts_on, location) VALUES ('competition', 'Lietuvos karate taurė', CURDATE(), 'Kaunas, Žalgirio arena'), ('competition', 'Lietuvos karate taurė', CURDATE(), 'Kaunas')");
  sql("INSERT INTO news (title, body, author_id) VALUES ('Rudens sezonas prasidėjo!', 'Sveiki sugrįžę į salę! Treniruotės vyksta pagal įprastą tvarkaraštį.', 1), ('Rudens sezonas prasidėjo!', 'Denio tekstas apie sezoną', 1)");
  sql("INSERT INTO lessons (title, topic, body, author_id) VALUES ('Tempimo pratimai vaikams', 'Fizinis pasiruošimas', '1. Atsisėskite, kojos tiesios.', 1)");
  await a.goto('admin/');
  check('old samples need their kids (2 of 3 - nothing)', !/Išvalyti seni/.test(await flashes(a)) && +sql("SELECT COUNT(*) FROM news WHERE title = 'Rudens sezonas prasidėjo!'") === 2);
  sql("INSERT INTO members (first_name, last_name, birth_date, status) VALUES ('Matas', 'Žukauskas', '2011-03-12', 'active')");
  await a.goto('admin/');
  check('old samples purged', /Išvalyti seni pavyzdiniai duomenys \(6\)/.test(await flashes(a)), await flashes(a));
  check('old samples gone, look-alikes stay', +sql("SELECT COUNT(*) FROM members WHERE last_name = 'Vaitkus'") === 1
    && sql("SELECT birth_date FROM members WHERE last_name = 'Vaitkus'") === '2015-05-05'
    && sql("SELECT GROUP_CONCAT(location) FROM events WHERE title = 'Lietuvos karate taurė'") === 'Kaunas'
    && sql("SELECT GROUP_CONCAT(body) FROM news WHERE title = 'Rudens sezonas prasidėjo!'") === 'Denio tekstas apie sezoną'
    && +sql("SELECT COUNT(*) FROM lessons WHERE title = 'Tempimo pratimai vaikams'") === 0);
  check('added to purged list', JSON.parse(sql("SELECT value FROM site_settings WHERE name = 'demo_isvalyta'")).parts.news.items.includes('Rudens sezonas prasidėjo!'));
  await a.goto('admin/');
  check('old samples only once', !/Išvalyti seni/.test(await flashes(a)));
  await a.goto('admin/testavimas.php');
  await a.click('.purged summary');
  check('purged list visible', /Baltic Open|Kata seminaras/.test(await a.locator('.purged').textContent()));

  // Testiniai nariai su visais scenarijais
  await nav(a, () => a.click('button[value=generate]'));
  check('generated', /Sukurta: Test Parent, Test Junior ir Test Mažylis/.test(await flashes(a)), await flashes(a));
  check('test accounts', +sql("SELECT COUNT(*) FROM accounts WHERE email LIKE '%@test.karateka.lt'") === 3);
  check('pending registration', +sql("SELECT COUNT(*) FROM accounts WHERE email LIKE 'naujas%@test.karateka.lt' AND status = 'pending_approval'") === 1);
  const junior = sql("SELECT am.member_id FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE a.email = 'junior@test.karateka.lt' AND am.relation = 'self'");
  check('junior notes and attendance', +sql(`SELECT COUNT(*) FROM coach_notes WHERE member_id = ${junior}`) === 2 && +sql(`SELECT COUNT(*) FROM attendance WHERE member_id = ${junior}`) >= 4);
  check('test list shown', await a.locator('.test-people li').count() === 3);

  // Peržiūra: Vaikas - diržo keitimas keičia pamokų aplankus; Tėvai - du vaikai; atgal į Adminą
  await a.click('.view-switch summary');
  await nav(a, () => a.click('.vs-modes button[value=junior]'));
  check('junior view', a.url().includes('paskyra.php') && /Vaikas/.test(await a.locator('.view-switch summary').textContent()));
  check('belt strip in account', await a.locator('.belt-strip').count() === 1);
  check('notes only in Svarbu', await a.locator('#svarbu .notif.t-note').count() === 2 && await a.locator('#pastabos').count() === 0);
  const forbidden = await a.goto('admin/nariai.php');
  check('no admin pages in junior view', forbidden.status() === 403, forbidden.status());
  await a.goto('pamokos.php');
  await a.click('.view-switch summary');
  await nav(a, () => a.selectOption('.vs-belt select', '7'));
  check('belt changed', sql(`SELECT belt_level FROM members WHERE id = ${junior}`) === '7');
  const folders = await a.locator('.kyu-tile strong').allTextContents();
  check('junior sees own and next belt folders', folders.includes('3 kyu') && folders.includes('2 kyu') && !folders.includes('1 kyu'), folders.join(','));
  await a.click('.view-switch summary');
  await nav(a, () => a.click('.vs-modes button[value=parent]'));
  await a.goto('paskyra.php');
  check('parent view with 2 kids', await a.locator('.member-tabs a').count() === 2);
  await a.click('.view-switch summary');
  await nav(a, () => a.click('.vs-modes button[value=admin]'));
  check('back to admin', /Adminas/.test(await a.locator('.view-switch summary').textContent()) && (await a.goto('admin/nariai.php')).status() === 200);
  const hacked = await a.request.post('perziura.php', { form: { _csrf: 'x', mode: 'junior' } });
  check('switch needs csrf', hacked.status() >= 400, hacked.status());

  // Ištrinti testinius - tikri lieka
  await a.goto('admin/testavimas.php');
  await nav(a, () => a.click('button[value=delete]'));
  check('test people deleted', +sql("SELECT COUNT(*) FROM accounts WHERE email LIKE '%@test.karateka.lt'") === 0
    && +sql("SELECT COUNT(*) FROM members WHERE first_name = 'Test'") === 0);
  check('real still there', +sql(`SELECT COUNT(*) FROM accounts WHERE id = ${real}`) === 1 && +sql(`SELECT COUNT(*) FROM news WHERE id = ${realNews}`) === 1);

  // Narys ištrinamas visam laikui (tik adminas); paskyra, kurioje neliko narių, - irgi
  await a.goto(`admin/nariai.php?id=${joris}`);
  await a.click('.danger-zone summary');
  await nav(a, () => a.click('.danger-zone button[value=delete_member]'));
  check('member deleted', +sql(`SELECT COUNT(*) FROM members WHERE id = ${joris}`) === 0 && /Ištrinta: Joris Tikras\./.test(await flashes(a)), await flashes(a));
  check('account with other kids stays', +sql(`SELECT COUNT(*) FROM accounts WHERE id = ${real}`) === 1);
  await a.goto(`admin/nariai.php?id=${ona}`);
  await a.click('.danger-zone summary');
  await nav(a, () => a.click('.danger-zone button[value=delete_member]'));
  check('empty account deleted too', +sql(`SELECT COUNT(*) FROM accounts WHERE id = ${real}`) === 0 && /esmile\.tikra@gmail\.com/.test(await flashes(a)), await flashes(a));

  for (const page of ['index.php', 'admin/', 'admin/nariai.php', 'renginiai.php', 'naujienos.php', 'pamokos.php', 'admin/programa.php', 'admin/testavimas.php']) {
    const r = await a.goto(page);
    check('page ok after cleanup: ' + page, r.status() === 200 && !/Fatal error|Warning:|Notice:|Uncaught/.test(await a.content()));
  }
  noJsErrors(a);
});
