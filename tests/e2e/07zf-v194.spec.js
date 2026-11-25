// v1.94: atsiliepimai #172-#178 - 💡 visur apačioje kairėje (dešinėje - 💬 ir 🔔), 🔔 su išskleidžiamu sąrašu,
// naujienose dešinėje tik pagrindinis egzaminas (be papildomų protokolų, #174), Budo balai - rašant ekranas nešokinėja (#175).
const { test } = require('@playwright/test');
const { sql, day, login, nav, check, noJsErrors } = require('./helpers');

test('💡 bottom left on every page, admin side menu - last item stays above it; 🔔 is a small bell with a list', async ({ browser }) => {
  sql("DELETE FROM notifications WHERE type = 'exam_registration' AND title LIKE 'v194:%'");
  const admId = sql("SELECT id FROM accounts WHERE email = 'info@karateka.lt'");
  sql(`INSERT INTO notifications (account_id, type, title, body, link) VALUES (${admId}, 'exam_registration', 'v194: Užsiregistravo į egzaminą: Ona', '3 kyu', 'admin/egzaminai.php')`);
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  for (const url of ['admin/', 'admin/egzaminai.php', 'pamokos.php', 'naujienos.php']) {
    await a.goto(url);
    const fb = await a.locator('#fbFab').boundingBox();
    check(`💡 bottom left on ${url}`, fb && fb.x < 40 && fb.y > 800, JSON.stringify(fb));
  }
  await a.goto('admin/');
  // v2.09 (Deividas): šoninis meniu eina iki ekrano apačios ir slenka pats; apačioje - vieta 💡, kad paskutinis punktas būtų virš jo
  const sm = await a.evaluate(async () => {
    window.scrollTo(0, document.documentElement.scrollHeight);
    await new Promise(r => setTimeout(r, 200));
    const s = document.querySelector('.adm-side');
    s.scrollTop = s.scrollHeight;
    await new Promise(r => setTimeout(r, 200));
    const links = s.querySelectorAll('a'), last = links[links.length - 1].getBoundingClientRect();
    const res = { last: Math.round(last.bottom), fb: Math.round(document.getElementById('fbFab').getBoundingClientRect().top), ov: getComputedStyle(s).overflowY };
    s.scrollTop = 0; window.scrollTo(0, 0);
    return res;
  });
  check('side menu: last item above 💡, the menu scrolls', sm.last <= sm.fb - 4 && sm.ov === 'auto', JSON.stringify(sm));
  const bell = await a.locator('.ntf-bell').boundingBox(), chat = await a.locator('.chat-fab').boundingBox();
  check('🔔 on the right above 💬', bell && chat && bell.x > 1300 && bell.y + bell.height <= chat.y, JSON.stringify([bell, chat]));
  check('no side cards, list closed', await a.locator('.ntf-card').count() === 0 && await a.locator('.ntf-menu').isHidden());
  await a.click('.ntf-bell');
  check('list opens with the item', /v194: Užsiregistravo į egzaminą: Ona/.test(await a.locator('.ntf-menu').innerText()));
  await a.mouse.click(700, 120);
  check('click outside closes', await a.locator('.ntf-menu').isHidden());
  await a.click('.ntf-bell');
  await a.click('.ntf-row:has-text("v194:") .ntf-ok');
  await a.waitForTimeout(400);
  check('marked read', sql("SELECT COUNT(*) FROM notifications WHERE title LIKE 'v194:%' AND read_at IS NULL") === '0');
  // Telefone - tas pats: 💡 kairėje, 💬 dešinėje
  const p = await login(browser, 'info@karateka.lt', 390, 844);
  await p.goto('admin/');
  const pf = await p.locator('#fbFab').boundingBox(), pc = await p.locator('.chat-fab').boundingBox();
  check('phone: 💡 left, 💬 right', pf.x < 40 && pc.x > 300, JSON.stringify([pf, pc]));
  noJsErrors(a, p);
});

test('#174: news shows only the main exam, not each protocol of the same day', async ({ browser }) => {
  sql("DELETE FROM events WHERE title LIKE 'V194 egzaminas%'");
  sql(`INSERT INTO events (type, title, starts_on, members_only, registration) VALUES ('exam', 'V194 egzaminas', '${day(9)}', 0, 0)`);
  const ev = sql("SELECT id FROM events WHERE title = 'V194 egzaminas'");
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/egzaminai.php');
  const r = await a.evaluate(async ({ ev }) => {
    const f = new FormData(); f.append('_csrf', document.querySelector('input[name=_csrf]').value);
    f.append('action', 'copy_exam'); f.append('id', ev); f.append('label', '1-3 kyu');
    return (await fetch('egzaminai.php', { method: 'POST', body: f, credentials: 'same-origin' })).status;
  }, { ev });
  check('copy made', r === 200 && sql("SELECT COUNT(*) FROM events WHERE title LIKE 'V194 egzaminas%'") === '2');
  check('copy linked to the main exam', sql(`SELECT parent_id FROM events WHERE title = 'V194 egzaminas · 1-3 kyu'`) === ev);
  await a.goto('admin/egzaminai.php');
  check('both protocols in Valdymas → Egzaminai', /V194 egzaminas · 1-3 kyu/.test(await a.locator('body').innerText()));
  const v = await login(browser, null, 1440, 900);
  await v.goto('naujienos.php');
  const side = await v.locator('.nr-events').innerText();
  check('news: one exam only', (side.match(/V194 egzaminas/g) || []).length === 1, side);
  await v.goto('renginiai.php');
  check('events page: no protocol copy', !/V194 egzaminas · 1-3 kyu/.test(await v.locator('body').innerText()));
  sql("DELETE FROM events WHERE title LIKE 'V194 egzaminas%'");
  noJsErrors(a, v);
});

test('#175: Budo scores - typing keeps the same field and the page does not jump', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1280, 800);
  sql('DELETE FROM tournaments WHERE is_test = 1');
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.click('#tab-live');
  await a.waitForTimeout(600);
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  if (await a.locator('#area-pick [data-my-area="all"]').count()) await a.click('#area-pick [data-my-area="all"]');
  await a.locator('#sched [data-open-cat]', { hasText: /^Kata (M|F)/ }).first().click();
  await a.waitForSelector('#draw-system');
  await a.selectOption('#draw-system', 'score');
  if (await a.locator('[data-runview="lines"]').count()) await a.click('[data-runview="lines"]');
  await a.waitForSelector('#draw-view .score-table .j-in');
  const second = a.locator('#draw-view .score-table tbody tr').nth(1).locator('.j-in').first();
  await second.scrollIntoViewIfNeeded();
  await a.evaluate(() => window.scrollBy(0, 120));
  await second.click();
  await a.evaluate(() => { document.querySelectorAll('#draw-view .j-in').forEach((el, i) => { el.__mark = i; }); });
  const y0 = await a.evaluate(() => window.scrollY);
  await a.keyboard.type('75');
  await a.waitForTimeout(300);
  await a.keyboard.type('80');
  await a.waitForTimeout(400);
  const st = await a.evaluate(() => {
    const act = document.activeElement;
    return { same: [...document.querySelectorAll('#draw-view .j-in')].every((el) => el.__mark !== undefined), j: act && act.dataset.j, y: window.scrollY };
  });
  check('fields are the same elements (not rebuilt)', st.same);
  check('focus moved on to the third judge', st.j === '2', JSON.stringify(st));
  check('page did not jump', Math.abs(st.y - y0) < 40, `${y0} → ${st.y}`);
  const vals = await a.locator('#draw-view .score-table tbody tr').nth(1).locator('.j-in').evaluateAll((els) => els.slice(0, 2).map((e) => e.value));
  check('scores kept', vals[0] === '7.5' && /^8(\.0)?$/.test(vals[1]), vals.join(','));
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});
