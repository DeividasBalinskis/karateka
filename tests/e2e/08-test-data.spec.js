// Testinių duomenų ištrynimas (Atsarginės kopijos ir sistema): peržiūra, kas bus ištrinta ir kas lieka.
// Tikri - pačių užsiregistravę ir Denio pridėti nariai - lieka; prieš trinant sukuriama kopija. Paleidžiamas paskutinis - ištrina pavyzdžius.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, flashes } = require('./helpers');

test('delete test data, keep real people; delete a member', async ({ browser }) => {
  // Tikra paskyra su vaiku, Denio pridėtas narys be paskyros, tikras ir testinis tėvas su bendru vaiku
  sql("INSERT INTO accounts (email, password_hash, first_name, last_name, status) VALUES ('esmile.tikra@gmail.com', 'x', 'Esmilė', 'Tikra', 'active')");
  const real = sql("SELECT id FROM accounts WHERE email = 'esmile.tikra@gmail.com'");
  sql("INSERT INTO members (first_name, last_name, birth_date, status) VALUES ('Joris', 'Tikras', '2015-01-01', 'active'), ('Rūta', 'Pridėta', '2014-01-01', 'active'), ('Ona', 'Bendra', '2016-01-01', 'active')");
  const joris = sql("SELECT id FROM members WHERE last_name = 'Tikras'");
  const ona = sql("SELECT id FROM members WHERE last_name = 'Bendra'");
  const demoParent = sql("SELECT id FROM accounts WHERE email LIKE '%@demo.karateka.lt' ORDER BY id LIMIT 1");
  sql(`INSERT INTO account_members (account_id, member_id, relation) VALUES (${real}, ${joris}, 'parent'), (${real}, ${ona}, 'parent'), (${demoParent}, ${ona}, 'parent')`);
  sql("INSERT INTO news (title, body, author_id) VALUES ('Tikra naujiena', 'Denio tekstas', 1)");

  const a = await login(browser, 'info@karateka.lt');
  await a.goto('admin/kopijos.php');
  const panel = a.locator('#testiniai');
  check('test data panel', await panel.count() === 1);
  const people = (await panel.locator('.td-part').first().textContent());
  check('demo people listed', /@demo\.karateka\.lt/.test(people) && /tevai@karateka\.test/.test(people));
  check('real people not listed', !/Tikras|Pridėta|Bendra|esmile\.tikra/.test(await panel.locator('.td-parts').textContent()));
  const kept = await panel.locator('.td-kept').textContent();
  check('kept list shows real', /Joris Tikras/.test(kept) && /Rūta Pridėta/.test(kept) && /Ona Bendra/.test(kept) && /Esmilė Tikra/.test(kept), kept.slice(0, 300));

  // Nuimta varnelė - ta dalis lieka
  await panel.locator('input[value=program]').uncheck();
  await nav(a, () => panel.locator('button[name=action]').click());
  check('deleted', /Testiniai duomenys ištrinti/.test(await flashes(a)), await flashes(a));
  check('backup made first', /Prieš tai sukurta kopija karateka-/.test(await flashes(a)));

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
  check('real news stays', +sql("SELECT COUNT(*) FROM news WHERE title = 'Tikra naujiena'") === 1);
  check('kata lessons and belt folders stay', +sql("SELECT COUNT(*) FROM lessons WHERE topic = 'Kata'") >= 15
    && +sql('SELECT COUNT(*) FROM lesson_folders WHERE parent_id IS NULL AND belt_level IS NOT NULL') === 18);
  check('referee test stays', +sql("SELECT COUNT(*) FROM quizzes WHERE audience = 'teisejas'") === 1);
  check('unchecked part kept', +sql("SELECT COUNT(*) FROM belt_requirements WHERE created_at = '2000-01-01 00:00:00'") > 0);

  // Lieka tik nepažymėta dalis; nieko nesugadinta
  await a.goto('admin/kopijos.php');
  check('only program left', (await a.locator('#testiniai .td-parts input').evaluateAll(i => i.map(x => x.value))).join(',') === 'program');
  for (const page of ['index.php', 'admin/', 'admin/nariai.php', 'renginiai.php', 'naujienos.php', 'pamokos.php', 'admin/programa.php']) {
    const r = await a.goto(page);
    check('page ok after cleanup: ' + page, r.status() === 200 && !/Fatal error|Warning:|Notice:|Uncaught/.test(await a.content()));
  }

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
  noJsErrors(a);
});
