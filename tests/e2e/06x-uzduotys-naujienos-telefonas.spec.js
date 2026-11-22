// v1.46: „📋 Užduotys“ - užduotys ir pamokos lieka ir pažymėjus; Naujienos ir renginiai viename puslapyje (#118);
// telefono meniu variantai (numatyta - apatinė juosta); Dojo apačioje - reitingas ir draugų kvietimai (#115); arena be medienos (#116)
const { test } = require('@playwright/test');
const { sql, day, login, nav, check, noJsErrors } = require('./helpers');

test('tasks stay findable after marking them viewed or done', async ({ browser }) => {
  const mid = +sql("SELECT am.member_id FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE a.email = 'jaunuolis@karateka.test' AND am.relation = 'self'");
  sql("DELETE FROM coach_notes WHERE body LIKE 'E2E užduotis%'");
  sql(`INSERT INTO coach_notes (member_id, note_date, body, is_task) VALUES (${mid}, '${day(0)}', 'E2E užduotis: 20 kartų mae geri', 1)`);
  sql(`INSERT INTO coach_notes (member_id, note_date, body, is_task) VALUES (${mid}, '${day(0)}', 'E2E užduotis: pažiūrėk video', 0)`);
  const k = await login(browser, 'jaunuolis@karateka.test', 390, 844);
  await k.goto('paskyra.php');
  // Netyčia paspaudžia „Perskaičiau“ ir „Atlikau“ - iš „Svarbu“ dingsta
  await nav(k, () => k.locator('#svarbu .notif', { hasText: 'pažiūrėk video' }).locator('button').click());
  await nav(k, () => k.locator('#svarbu .notif', { hasText: '20 kartų' }).locator('button').click());
  check('gone from Svarbu', await k.locator('#svarbu .notif', { hasText: 'E2E užduotis' }).count() === 0);
  // Bet „📋 Užduotys“ - abi lieka
  await nav(k, () => k.click('.mt-member a[href*="uzduotys.php"]'));   // B8: telefone - apatinė juosta
  check('tasks page lists both', await k.locator('.task-item', { hasText: 'E2E užduotis' }).count() === 2);
  check('status shown', /Atlikta/.test(await k.locator('.task-item', { hasText: '20 kartų' }).textContent()) && /Perskaityta/.test(await k.locator('.task-item', { hasText: 'pažiūrėk video' }).textContent()));
  // Grąžinti į neatliktas - lieka tame pačiame puslapyje
  await nav(k, () => k.locator('.task-item', { hasText: '20 kartų' }).locator('button', { hasText: 'Grąžinti' }).click());
  check('back on tasks page, undone', k.url().includes('uzduotys.php') && /Neatlikta/.test(await k.locator('.task-item', { hasText: '20 kartų' }).textContent()));
  await k.goto('uzduotys.php?f=neatliktos');
  check('filter: not done', await k.locator('.task-item', { hasText: '20 kartų' }).count() === 1 && await k.locator('.task-item', { hasText: 'pažiūrėk video' }).count() === 0);
  sql("DELETE FROM coach_notes WHERE body LIKE 'E2E užduotis%'");
  noJsErrors(k);
});

test('news and events on one page, one menu item', async ({ browser }) => {
  const v = await login(browser, 'jaunuolis@karateka.test', 1366, 900);
  await v.goto('naujienos.php');
  check('one menu item', await v.locator('.nav-pages a', { hasText: 'Naujienos ir renginiai' }).count() === 1 && await v.locator('.nav-pages a[href$="renginiai.php"]').count() === 0);
  check('events on the right', await v.locator('.nr-events .nr-ev').count() >= 2);
  await v.evaluate(() => Promise.all(document.getAnimations().filter(x => x.effect && x.effect.getTiming().iterations !== Infinity).map(x => x.finished)));   // kortelės „įplaukia“
  const geo = await v.evaluate(() => {
    const n = document.querySelector('.nr-news .news-card').getBoundingClientRect(), e = document.querySelectorAll('.nr-ev');
    return { newsLeft: n.left, evLeft: e[0].getBoundingClientRect().left, two: Math.round(e[1].getBoundingClientRect().bottom - e[0].getBoundingClientRect().top), news: Math.round(n.height), top: Math.round(n.top - e[0].getBoundingClientRect().top) };
  });
  check('events right of news', geo.evLeft > geo.newsLeft, JSON.stringify(geo));
  // Naujiena su daug reakcijų gali paaugti (#119: pridėta reakcija turi matytis) - lyginama su įprastu (mažiausiu) aukščiu
  const minNews = await v.evaluate(() => parseFloat(getComputedStyle(document.querySelector('.nr-news .news-card')).minHeight));
  check('two events = one news height, aligned', Math.abs(geo.two - minNews) <= 2 && geo.news >= minNews - 1 && Math.abs(geo.top) <= 2, JSON.stringify(geo) + ' min ' + minNews);
  await nav(v, () => v.click('.nr-h a'));
  check('full events page still there', v.url().includes('renginiai.php') && await v.locator('.nav-pages a.is-current', { hasText: 'Naujienos ir renginiai' }).count() === 1);
  noJsErrors(v);
});

test('phone menu for staff (B9, v1.86 top bar): bar only, More has the rest; nothing of it on a computer', async ({ browser }) => {
  const p = await login(browser, 'info@karateka.lt', 390, 844);
  await p.goto('admin/treniruote.php');
  check('bottom bar, no burger, no admin row', await p.locator('.m-tabbar').isVisible() && !(await p.locator('#burgerBtn').isVisible()) && !(await p.locator('.am-line').isVisible()));
  await p.click('.m-more > summary');
  check('More: rest of admin (Renginiai too) and the website', await p.locator('.m-sheet a[href*="testai.php"]').isVisible() && await p.locator('.m-sheet a[href$="admin/renginiai.php"]').isVisible() && await p.locator('.m-sheet a[href*="naujienos.php"]').isVisible());
  const c = await login(browser, 'info@karateka.lt', 1366, 900);
  await c.goto('admin/');
  check('computer: no phone bar', !(await c.locator('.m-tabbar').isVisible()) && await c.locator('.adm-side').isVisible());
  noJsErrors(p, c);
});

test('dojo: ranking and invite a friend; arena without wood', async ({ browser }) => {
  // Žaidimas atidarytas visiems (kiti testai jį uždaro - #140)
  sql("INSERT INTO site_settings (name, value) VALUES ('game_access', 'all') ON DUPLICATE KEY UPDATE value = 'all'");
  // Kiti žaidėjai: personažai atsiranda, kai narys pirmą kartą atidaro Dojo (nepriklausomai nuo kitų testų)
  for (const who of ['tevai@karateka.test', 'narys@karateka.test']) {
    const o = await login(browser, who, 1280, 900);
    await o.goto('dojo.php');
    await o.context().close();
  }
  const k = await login(browser, 'jaunuolis@karateka.test', 1280, 900);
  await k.goto('dojo.php');
  await k.waitForSelector('.dojo-social');
  check('ranking and players', await k.locator('.ds-top h2').count() === 1 && await k.locator('.ds-players li').count() >= 3);
  await k.fill('.ds-q', 'zzzz');
  check('search filters players', await k.locator('.ds-players li:not([hidden])').count() === 0);
  await k.fill('.ds-q', '');
  const btn = k.locator('.ds-players [data-inv]').first();
  await btn.click();
  await k.waitForSelector('.ds-toast:not([hidden])');
  check('invite sent', /Kvietimas išsiųstas/.test(await k.textContent('.ds-toast')));
  check('no panda or white tiger characters', !/"(panda|wtiger|raven)"/.test(JSON.stringify(await k.evaluate(() => Object.keys((window.GAME.catalog || {}).chars || {})))));
  await k.goto('arena.php');
  await k.waitForSelector('.arena .g-card');
  const bg = await k.evaluate(() => getComputedStyle(document.querySelector('.arena .g-card')).backgroundImage);
  check('arena cards without wood or paper texture', !/mediena|popierius/.test(bg), bg);
  noJsErrors(k);
});
