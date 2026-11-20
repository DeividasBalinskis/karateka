// v1.49: Denio atsiliepimai #126, #129-#131, #133, #135-#137
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

test('Valdymas: left menu starts with the first card (#129); "Apie klubą" opens downward and covers nothing (#131)', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/');
  await a.waitForTimeout(300);
  const g = await a.evaluate(() => {
    const side = document.querySelector('.adm-side').getBoundingClientRect(), card = document.querySelector('.dash').getBoundingClientRect();
    return { side: Math.round(side.top), card: Math.round(card.top) };
  });
  check('sidebar top = first card top', Math.abs(g.side - g.card) <= 2, JSON.stringify(g));
  await a.click('.fold-btn');
  const o = await a.evaluate(() => {
    const r = e => e.getBoundingClientRect(), list = r(document.querySelector('.fold-list')), btn = r(document.querySelector('.fold-btn'));
    const hits = ['.site-switch', '.nav-prefs', '.lang-dd', '.theme-pick'].map(s => document.querySelector('.site-header ' + s)).filter(Boolean)
      .filter(e => { const b = r(e); return !(b.right <= list.left || b.left >= list.right || b.bottom <= list.top || b.top >= list.bottom); }).length;
    return { below: list.top >= btn.bottom - 1, hits };
  });
  check('fold list opens below its button', o.below, JSON.stringify(o));
  check('fold list covers no header control', o.hits === 0, JSON.stringify(o));
  noJsErrors(a);
});

test('exam protocol: 6.3 or lower is red (#133); 10 kyu in prices and grades (#136)', async ({ browser }) => {
  const ev = +sql("SELECT id FROM events WHERE type = 'exam' ORDER BY id LIMIT 1");
  sql(`DELETE FROM exam_protocol_rows WHERE event_id = ${ev} AND name LIKE 'E2E raudona%'`);
  sql(`INSERT INTO exam_protocol_rows (event_id, number, num_date, seq, name, kyu, kihon, kata, kumite, result, created_at) VALUES (${ev}, 'E2E-R', CURDATE(), 931, 'E2E raudona Išlaikė', '10 kyu', '6.3', '7.0', '6.4', 'passed', NOW())`);
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto(`admin/egzamino-protokolas.php?id=${ev}`);
  const row = a.locator('#protTable tr[data-row]:has(input[data-f="name"][value^="E2E raudona"])');
  check('6.3 cell red in a passed row', await row.locator('td.sc.low').count() === 1);
  const k = row.locator('.pi-score[data-f="kumite"]');
  await k.fill('62'); await k.dispatchEvent('change');
  check('typed 6.2 turns red at once', await row.locator('td.sc.low').count() === 2);
  check('10 kyu in the grade list', await a.locator('#kyuList option[value="10 kyu"]').count() === 1);
  await a.goto('admin/egzaminai.php');
  check('10 kyu in the price list', await a.locator('.price-grid input[name="price[0]"]').count() === 1);
  sql(`DELETE FROM exam_protocol_rows WHERE event_id = ${ev} AND name LIKE 'E2E raudona%'`);
  noJsErrors(a);
});

test('quiz bank: download with answers, fix, upload back - questions are fixed in place (#137)', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  const qid = +sql("SELECT id FROM quizzes ORDER BY id LIMIT 1");
  const before = +sql(`SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ${qid}`);
  const r = await a.request.get(`admin/testai.php?id=${qid}&eksportas=1`);
  const text = await r.text();
  check('download works', r.status() === 200 && /attachment/.test(r.headers()['content-disposition'] || ''));
  const blocks = text.split(/\n\n+/).filter(b => /^\d+\. \[#\d+\]/.test(b));
  check('every question with answers and the correct one', blocks.length === before && blocks.every(b => /\nA\) /.test(b) && /\nAtsakymas: [A-D]/.test(b)), blocks.length + ' / ' + before);
  const first = blocks[0], id = +first.match(/\[#(\d+)\]/)[1];
  const fixed = first.replace(/^(\d+\. \[#\d+\] )(.*)$/m, '$1E2E pataisytas klausimas?');
  const extra = '\n\n99. E2E naujas klausimas?\nA) Taip\nB) Ne\nAtsakymas: A';
  await a.goto(`admin/testai.php?id=${qid}#ikelti`);
  await a.locator('#ikelti > summary').click();
  await a.fill('#ikelti textarea[name=import_text]', '// komentaras\n' + fixed + extra);
  await Promise.all([a.waitForNavigation(), a.click('#ikelti button:has-text("Peržiūrėti")')]);
  check('preview shows the fixed one', await a.locator('.import-preview .badge', { hasText: 'pataisomas' }).count() === 1);
  await Promise.all([a.waitForNavigation(), a.click('#ikelti button[name=draft][value="0"]')]);
  check('fixed in place', sql(`SELECT body FROM quiz_questions WHERE id = ${id}`) === 'E2E pataisytas klausimas?');
  check('new one added, none lost', +sql(`SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ${qid}`) === before + 1);
  sql(`DELETE FROM quiz_questions WHERE quiz_id = ${qid} AND body = 'E2E naujas klausimas?'`);
  noJsErrors(a);
});

test('Budo: open ends show − and + (#135); tatami settings inside the screen, no text cursor (#130); bout panel shows who is next (#126)', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 375, 812);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.waitForFunction(() => /Išsaugota/.test((document.querySelector('.save-state') || {}).textContent || ''), null, { timeout: 15000 });
  await a.waitForTimeout(800);
  await a.evaluate(() => document.querySelector('#tab-admin, [data-tab="admin"]') && document.querySelector('#tab-admin, [data-tab="admin"]').click());
  const ph = await a.evaluate(() => [...document.querySelectorAll('[data-ov="y1"]')].slice(0, 1).map(x => x.placeholder).concat([...document.querySelectorAll('[data-ov="y0"]')].slice(0, 1).map(x => x.placeholder)));
  check('youngest − / oldest +', ph.length === 0 || (ph[0] === '−' && ph[1] === '+'), JSON.stringify(ph));

  await a.click('#tab-live');
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  if (await a.locator('#area-pick.ask [data-my-area="all"]').count()) await a.click('#area-pick.ask [data-my-area="all"]');
  await a.locator('details.live-settings > summary').click();
  await a.waitForTimeout(150);
  const box = await a.evaluate(() => { const b = document.querySelector('.live-settings > .toolbar'), r = b.getBoundingClientRect(), add = document.querySelector('#area-new').getBoundingClientRect();
    return { l: Math.round(r.left), r: Math.round(r.right), addL: Math.round(add.left), vw: document.documentElement.clientWidth, cur: getComputedStyle(b.querySelector('.muted')).cursor }; });
  check('settings panel inside the screen', box.l >= 0 && box.r <= box.vw && box.addL >= 0, JSON.stringify(box));
  check('no text cursor over labels', box.cur === 'default', box.cur);
  await a.locator('details.live-settings > summary').click();

  const st = JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
  const kum = st.categories.find(c => c.discipline === 'kumite' && !c.team && c.competitors.length >= 6 && c.draw && c.draw.slots);
  await a.locator(`[data-open-cat="${kum.id}"]`).first().click();
  await a.waitForSelector('#run:not([hidden])');
  await a.locator('#draw-view .bout-open').first().click();
  check('who is next in the bout panel', /Ruošiasi/.test(await a.locator('.bout-card .bt-next').first().textContent()));
  // #62: naršyklės „Atgal“ su atidarytu kovos skydeliu - uždaro skydelį, grupė lieka atidaryta
  await a.goBack();
  await a.waitForTimeout(300);
  check('Back closes the bout panel first', await a.locator('#bout').isHidden() && await a.locator('#run').isVisible() && /varzybu-sistema/.test(a.url()));
  noJsErrors(a);
});

test('Valdymas: "Šiandien: N" counts the same groups as the Treniruotė page (#87)', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/');
  const txt = (await a.locator('.today-trainings .tt-line b').count()) ? await a.textContent('.today-trainings .tt-line b') : 'Šiandien: 0';
  const n = +(txt.match(/(\d+)/) || [0, 0])[1];
  await a.goto('admin/treniruote.php');
  const tiles = await a.locator('.today-tiles .group-tile').count();
  check('same number', n === tiles, n + ' vs ' + tiles);
  noJsErrors(a);
});
