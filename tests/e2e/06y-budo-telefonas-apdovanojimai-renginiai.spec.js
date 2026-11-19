// v1.47: renginio nuotrauka ir renginių stulpelis iki apačios (#119), Pranešimai „Tvarkyti“ (#121), Nustatymuose be programos (#120),
// protokolas be vidurkio ir telpa planšetėje (#124), telefone „⋯ Daugiau“ užsidaro paspaudus fone (#125), redagavimo juosta po antrašte (#123),
// Budo: centre plačiame ekrane, telefone balų kortelės ir vaizdas nebeperšoka; apdovanojimai (#122) - tikrinama 06i
const path = require('path');
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('event photo, events column to the bottom, back link', async ({ browser }) => {
  const ev = +sql('SELECT id FROM events WHERE starts_on >= CURDATE() ORDER BY starts_on LIMIT 1');
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto(`admin/renginiai.php?edit=${ev}`);
  await a.setInputFiles('input[name=cover]', path.join(__dirname, '../../public_html/img/apie-1.jpg'));
  await nav(a, () => a.click('form.form button.btn-primary[type=submit]'));
  const cover = sql(`SELECT COALESCE(cover, '') FROM events WHERE id = ${ev}`);
  check('photo stored', /^[a-f0-9]{24}\.jpg$/.test(cover), cover);

  const v = await login(browser, null, 1440, 900);
  await v.goto('naujienos.php');
  await v.evaluate(() => Promise.all(document.getAnimations().filter(x => x.effect && x.effect.getTiming().iterations !== Infinity).map(x => x.finished)));
  check('event with photo', await v.locator(`.nr-ev[href$="id=${ev}"] .nr-ph img`).count() === 1);
  check('events without photo get a coloured sign', await v.locator('.nr-ev .nr-ph.no-ph').count() >= 1);
  const geo = await v.evaluate(() => {
    const n = document.querySelectorAll('.nr-news .news-card'), e = document.querySelectorAll('.nr-events .nr-ev');
    const last = e[e.length - 1].getBoundingClientRect(), lastNews = n[n.length - 1].getBoundingClientRect();
    return { two: Math.round(e[1].getBoundingClientRect().bottom - e[0].getBoundingClientRect().top), news: Math.round(n[0].getBoundingClientRect().height),
      evBottom: Math.round(last.bottom + scrollY), newsBottom: Math.round(lastNews.bottom + scrollY), evW: Math.round(e[0].getBoundingClientRect().width) };
  });
  const minNews = await v.evaluate(() => parseFloat(getComputedStyle(document.querySelector('.nr-news .news-card')).minHeight));
  check('two events = one news', Math.abs(geo.two - minNews) <= 2 && geo.news >= minNews - 1, JSON.stringify(geo) + ' min ' + minNews);
  check('events column bigger', geo.evW >= 340, JSON.stringify(geo));
  check('events go down like the news', geo.evBottom > geo.newsBottom - 700, JSON.stringify(geo));
  await nav(v, () => v.click('.nr-h a'));
  check('back link on events page', await v.locator('.crumbs a[href$="naujienos.php"]').count() === 1);
  check('photo in the events list', await v.locator(`#e${ev} .event-ph img, .event.has-ph .event-ph img`).count() >= 1);

  await a.goto(`admin/renginiai.php?edit=${ev}`);
  await a.check('input[name=cover_remove]');
  await nav(a, () => a.click('form.form button.btn-primary[type=submit]'));
  check('photo removed', sql(`SELECT COALESCE(cover, '') FROM events WHERE id = ${ev}`) === '');
  noJsErrors(a, v);
});

test('admin: Pranešimai under Tvarkyti, no exam program in settings, protocol without average fits a tablet', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/');
  const groups = await a.locator('.adm-side .am-group').allInnerTexts();
  check('Pranešimai in Tvarkyti', /Tvarkyti[\s\S]*Pranešimai/i.test(groups[2] || '') && !/Pranešimai/.test(groups[0] || ''), JSON.stringify(groups));
  await a.goto('admin/nustatymai.php');
  check('no exam program card', await a.locator('.settings-group a[href$="programa.php"]').count() === 0);
  const ev = +sql("SELECT id FROM events WHERE type = 'exam' ORDER BY id LIMIT 1");
  sql(`DELETE FROM exam_protocol_rows WHERE event_id = ${ev} AND name LIKE 'E2E planšetė%'`);
  sql(`INSERT INTO exam_protocol_rows (event_id, number, num_date, seq, name, kyu, kihon, kata, kumite, test, notes, created_at) VALUES (${ev}, 'E2E-1', CURDATE(), 901, 'E2E planšetė Ilgavardė Pavardenytė', '2 kyu', '6.7', '6.9', '7.2', '70', 'Ilgas komentaras apie egzaminą', NOW())`);
  const t = await login(browser, 'info@karateka.lt', 1175, 900);
  await t.goto(`admin/egzamino-protokolas.php?id=${ev}`);
  check('no average column', await t.locator('#protTable .avg, #protTable .col-avg').count() === 0);
  const w = await t.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth, Math.round(document.querySelector('#protTable').getBoundingClientRect().right), Math.round(document.querySelector('.prot-wrap').getBoundingClientRect().right)]);
  check('page not wider than the tablet', w[0] <= w[1] + 1, JSON.stringify(w));
  check('passed / failed buttons visible without scrolling', w[2] <= w[3] + 1 && await t.locator('#protTable tr[data-row] .res-btn').first().isVisible(), JSON.stringify(w));
  check('number under the name', await t.locator('#protTable .nr-in').first().isVisible());
  sql(`DELETE FROM exam_protocol_rows WHERE event_id = ${ev} AND name LIKE 'E2E planšetė%'`);
  noJsErrors(a, t);
});

test('phone: More sheet closes on background tap; tablet: edit bar below the header', async ({ browser }) => {
  sql("DELETE FROM site_settings WHERE name = 'phone_menu'");
  const p = await login(browser, 'info@karateka.lt', 390, 844);
  await p.goto('admin/nariai.php');
  await p.click('.m-more > summary');
  await p.waitForTimeout(300);
  check('sheet open with dimmed background', await p.locator('.m-sheet').isVisible() && await p.evaluate(() => document.body.classList.contains('m-more-open')));
  await p.mouse.click(200, 120);
  await p.waitForTimeout(300);
  check('closed after tapping the background', !(await p.locator('.m-sheet').isVisible()) && !(await p.evaluate(() => document.body.classList.contains('m-more-open'))));

  const t = await login(browser, 'info@karateka.lt', 800, 1100);
  await t.goto('index.php?redaguoti=1');
  await t.evaluate(() => window.scrollTo(0, 1600));
  await t.waitForTimeout(400);
  const g = await t.evaluate(() => [Math.round(document.querySelector('.site-header').getBoundingClientRect().bottom), Math.round(document.querySelector('.hb-toolbar').getBoundingClientRect().top)]);
  check('Save bar not hidden under the header', g[1] >= g[0], JSON.stringify(g));
  noJsErrors(p, t);
});

test('Budo: centred on a wide screen; phone score cards keep the view in place', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 2560, 1100);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.waitForTimeout(1500);
  const m = await a.evaluate(() => { const r = document.querySelector('main').getBoundingClientRect(); return [Math.round(r.left), Math.round(innerWidth - r.right), Math.round(r.width)]; });
  check('centred, not edge to edge', m[2] <= 1700 && Math.abs(m[0] - m[1]) <= 20, JSON.stringify(m));

  const st = JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
  const kata = st.categories.find(c => c.discipline === 'kata' && !c.team && c.competitors.length >= 3);
  const kumite = st.categories.find(c => c.discipline === 'kumite' && !c.team && c.competitors.length >= 5);
  const p = await login(browser, 'info@karateka.lt', 390, 844);
  await p.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await p.waitForTimeout(1500);
  check('top bar not sticky on phone', await p.evaluate(() => getComputedStyle(document.querySelector('.vz-top')).position) !== 'sticky');
  await p.click('#tab-live');
  await p.locator(`[data-open-cat="${kata.id}"]`).first().click();
  await p.selectOption('#draw-system', 'score');
  await p.waitForTimeout(500);
  check('score rows are cards, no sideways scroll', await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1 && getComputedStyle(document.querySelector('.score-table tr')).display === 'flex'));
  const inp = p.locator('#draw-view .j-in[data-sp]').nth(1);
  await inp.evaluate(e => e.scrollIntoView({ block: 'center' }));
  await p.waitForTimeout(200);
  const y0 = await p.evaluate(() => scrollY);
  await inp.focus(); await p.keyboard.type('75');
  await p.waitForTimeout(800);
  const y1 = await p.evaluate(() => scrollY);
  check('score typed, view stays', Math.abs(y1 - y0) < 120 && JSON.stringify(JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)).categories.find(c => c.id === kata.id).draw.scores || {}).includes('7.5'), `${y0} -> ${y1}`);

  // Tinklas: paslinkus į šoną ir pažymėjus nugalėtoją - lieka toje pačioje vietoje
  await p.click('#run-back');
  await p.locator(`[data-open-cat="${kumite.id}"]`).first().click();
  await p.waitForTimeout(500);
  const btn = p.locator('#draw-view [data-bout]').first();
  await btn.scrollIntoViewIfNeeded();
  await btn.click();
  await p.evaluate(() => { const w = document.querySelector('#draw-view .bracket-wrap'); if (w) w.scrollLeft = 60; });
  const before = await p.evaluate(() => [document.querySelector('#draw-view .bracket-wrap').scrollLeft, Math.round(scrollY)]);
  await p.locator('[data-win="a"]').first().click();
  await p.waitForTimeout(800);
  const after = await p.evaluate(() => [document.querySelector('#draw-view .bracket-wrap').scrollLeft, Math.round(scrollY)]);
  check('AKA won: bracket does not jump to the start', after[0] === before[0] && Math.abs(after[1] - before[1]) < 120, `${before} -> ${after}`);
  sql(`DELETE FROM tournaments WHERE id = ${tid}`);
  noJsErrors(a, p);
});

test('game hidden: only chosen accounts (Denis) see Dojo; settings switch', async ({ browser }) => {
  const adminId = +sql("SELECT id FROM accounts WHERE email = 'info@karateka.lt'");
  sql(`INSERT INTO site_settings (name, value) VALUES ('game_access', '[${adminId}]') ON DUPLICATE KEY UPDATE value = '[${adminId}]'`);
  const k = await login(browser, 'jaunuolis@karateka.test', 1280, 900);
  await k.goto('paskyra.php');
  check('kid: no Dojo in the menu', await k.locator('.nav-game').count() === 0 && await k.locator('.game-teaser').count() === 0);
  await k.goto('dojo.php');
  check('kid: Dojo page closed', !k.url().includes('dojo.php'));
  const r = await k.request.get('zaidimas.php?list=1');
  check('kid: game API closed', r.status() >= 400 || !(await r.text()).includes('"ok":true'));
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto('dojo.php');
  check('Denis sees the game', a.url().includes('dojo.php') && await a.locator('.nav-game').count() === 1);
  await a.goto('admin/nustatymai.php');
  check('settings: who sees it', /Mato tik/.test(await a.textContent('#zaidimas')));
  await nav(a, () => a.click('#zaidimas button:has-text("Rodyti visiems")'));
  check('switched to everyone', sql("SELECT value FROM site_settings WHERE name = 'game_access'") === 'all');
  await k.goto('paskyra.php');
  check('kid sees Dojo again', await k.locator('.nav-game').count() === 1);
  sql("DELETE FROM site_settings WHERE name = 'game_access'");
  noJsErrors(a, k);
});
