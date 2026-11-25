// v1.22 (Taikos pertvarka, 1 etapas): Taikos pradžia su visomis varžybomis ir partneriais, perjungimas tarp varžybų,
// meniu (Klubai, Komandos, Dalyviai, Kategorijos | Live tvarkaraštis, Rezultatai), Live tvarkaraštis: dabartinė rungtis,
// vėlavimas varžybų dieną, rungties valdymas su „Toliau →“; testas be paskyros - el. paštas nebūtinas.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, day } = require('./helpers');

test('Taika home, switcher, menu order, live schedule with current category and run panel', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  sql(`UPDATE tournaments SET starts_on = '${day(0)}' WHERE id = ${tid}`);   // šiandien Vilniaus laiku (ne DB serverio)

  // Pradžia: kortelė su veiksmais, partneriai apačioje
  await a.goto('admin/varzybos.php');
  check('competition card', await a.locator(`#t${tid}.varz-card`).count() === 1);
  check('partners strip', await a.locator('.vz-partners .vzp-logo').count() >= 3);
  await nav(a, () => a.locator(`#t${tid} .varz-actions > a.btn-primary`).click());

  // Meniu tvarka ir perjungimas į kitas varžybas
  // Tvarkaraštis - pirmas (pagrindinė vieta), Klubai / Komandos / Dalyviai / Kategorijos - viename „Administracija“ lange
  const tabs = await a.$$eval('#tabs [role=tab]', bs => bs.map(b => b.id.replace('tab-', '')).join(','));
  check('menu order', tabs === 'live,rezultatai,admin', tabs);
  const sub = await a.$$eval('#tabs-sub [role=tab]', bs => bs.map(b => b.id.replace('tab-', '')).join(','));
  check('administration sub-tabs', sub === 'klubai,komandos,dalyviai,kategorijos,tinklai', sub);
  check('opens on the schedule', await a.locator('#v-live').isVisible() && !(await a.locator('#tabs-sub').isVisible()));
  check('switcher lists competitions', await a.locator('#t-switch option').count() >= 2);

  // Live tvarkaraštis: dabartinė rungtis, varžybų dieną - ar tatamis vėluoja
  await a.click('#tab-live');
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  await a.waitForSelector('#sched .card');
  check('current category marked', await a.locator('#sched .card.st-now').count() >= 1);
  check('finished categories dimmed', await a.locator('#sched .card.st-done').count() >= 1);
  check('delay shown on competition day', await a.locator('#sched .delay').count() >= 1);

  // Rungties valdymas: atidaroma lenta, juostelė, „Toliau →“
  const now = a.locator('#sched .card.st-now').first();
  const name = (await now.locator('.card-name').innerText()).trim();
  await now.locator('[data-open-cat]').click();
  check('run panel open', await a.locator('#run').isVisible() && !(await a.locator('#live-board').isVisible()));
  check('run shows category', (await a.textContent('#run-name')).includes(name.slice(0, 8)), name);
  check('list marks open category', await a.locator('#run-list .rl-row.on').count() === 1);
  const nextLabel = await a.getAttribute('#run-next', 'title');   // „Kita: <pavadinimas>“
  if (!(await a.locator('#run-next').isDisabled())) {
    await a.click('#run-next');
    check('next opens following category', (await a.textContent('#run-name')).length > 0 && nextLabel.includes((await a.textContent('#run-name')).trim().slice(0, 8)), nextLabel);
  }
  await a.click('#run-back');
  check('back to board', await a.locator('#live-board').isVisible());

  // Kategorijos → „Valdyti →“ atidaro tą rungtį Live tvarkaraštyje
  await a.click('#tab-admin'); await a.click('#tab-kategorijos');
  await a.click('[data-catview=\"edit\"]');   // viena kategorija (numatyta - struktūra)
  await a.locator('#cat-list button').nth(1).click();
  await a.click('#cat-draw');
  check('manage from categories', await a.locator('#v-live').isVisible() && await a.locator('#run').isVisible());
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});

test('guest quiz: only name required, optional email stored', async ({ browser }) => {
  sql("DELETE FROM access_links WHERE token_hash = SHA2('aaaaaaaaaaaaaaaaaaaaaaaa', 256)");   // C3: sena nuoroda perkeliama iš naujo
  sql("INSERT INTO quizzes (title, audience, question_count, pass_percent, is_published, share_open, share_token) VALUES ('Svečio testas (e2e)', 'karateka', 1, 50, 1, 1, 'aaaaaaaaaaaaaaaaaaaaaaaa')");
  const qz = +sql("SELECT id FROM quizzes WHERE title = 'Svečio testas (e2e)'");
  sql(`INSERT INTO quiz_questions (quiz_id, body, sort_order) VALUES (${qz}, 'Kas yra kiai?', 1)`);
  const q = +sql(`SELECT id FROM quiz_questions WHERE quiz_id = ${qz}`);
  sql(`INSERT INTO quiz_answers (question_id, body, is_correct, sort_order) VALUES (${q}, 'šūksnis', 1, 0), (${q}, 'smūgis', 0, 1)`);
  const g = await login(browser, null, 390, 844);
  await g.goto('testas-svecias.php?k=aaaaaaaaaaaaaaaaaaaaaaaa');
  await g.fill('input[name=name]', 'Be Klubo');
  await g.fill('input[name=email]', 'netinkamas');
  check('bad email not accepted', await g.$eval('input[name=email]', el => !el.checkValidity()));
  await g.fill('input[name=email]', 'svecias@example.com');
  await nav(g, () => g.click('#guestStart button[type=submit]'));
  await g.locator('.q-answer', { hasText: 'šūksnis' }).click();
  await nav(g, () => g.click('#quizForm button[type=submit]'));
  check('result shown', /100 %/.test(await g.locator('.result-big').innerText()));
  check('email stored, club empty', sql(`SELECT CONCAT(name, '|', COALESCE(club, '-'), '|', email) FROM quiz_guest_attempts WHERE quiz_id = ${qz}`) === 'Be Klubo|-|svecias@example.com');
  sql(`DELETE FROM quizzes WHERE id = ${qz}`);
  noJsErrors(g);
});

test('categories structure: all disciplines, edit years and gender in place, open people, drag, redistribute, copy', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  const state = () => JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
  await a.click('#tab-admin'); await a.click('#tab-kategorijos');
  await a.click('[data-catview="ov"]');
  await a.waitForSelector('#ov-cols .ov-col[data-ov-col="kata"]');
  check('columns per discipline', await a.locator('#ov-cols .ov-col[data-ov-col]').count() >= 3);
  await a.waitForTimeout(1200);

  // Naujas blokas Fukugo stulpelyje (trumpas - matosi ekrane): metai, lytis, kyu - pavadinimas atsinaujina pats
  const before = state().categories.length;
  await a.click('[data-ov-new="fukugo"]');
  const blk = a.locator('#ov-cols [data-ov-col="fukugo"] .ovb').last();
  const cid = await blk.getAttribute('data-ovb');
  await blk.locator('[data-ov="y1"]').fill('2016');
  await blk.locator('[data-ov="y0"]').fill('2015');
  await blk.locator('[data-ov="y0"]').press('Tab');
  await a.locator(`[data-c="${cid}"][data-ov="g"]`).selectOption('F');
  await a.waitForTimeout(1200);
  const nc = state().categories.find(c => c.id === cid);
  check('new block saved', state().categories.length === before + 1 && nc.crit.y1 === 2016 && nc.crit.y0 === 2015 && nc.crit.g === 'F', JSON.stringify(nc));
  check('name follows criteria', /Fukugo F 2016–2015/.test(nc.name), nc.name);

  // Dalyviai bloke ir tempimas į kitą bloką
  const src = a.locator('#ov-cols [data-ov-col="kata"] .ovb').first();
  const srcId = await src.getAttribute('data-ovb');
  await src.locator('[data-ov-open]').click();
  const chip = a.locator(`[data-ovb="${srcId}"] .ovp`).first();
  const pid = await chip.getAttribute('data-drag');
  await chip.dragTo(a.locator(`[data-ovb="${cid}"] .ovb-row`));
  await a.waitForTimeout(1200);
  const st = state();
  check('person moved to block', st.categories.find(c => c.id === cid).competitors.some(p => p.id === pid) && !st.categories.find(c => c.id === srcId).competitors.some(p => p.id === pid));
  check('unfit marked', await a.locator(`[data-ovb="${cid}"].few, [data-ovb="${cid}"].unfit`).count() === 1);

  // ⟳ - netinkantys perkeliami į tinkamą bloką; ⧉ - struktūra į kitą rungtį
  await a.click('[data-ov-redo="kumite"]');
  await a.waitForSelector('.vz-toast:not([hidden])');
  check('redistribute toast', /Perkelta dalyvių/.test(await a.locator('.vz-toast').innerText()));
  const kihonBefore = state().categories.filter(c => c.discipline === 'kihon').length;
  await a.selectOption('[data-ov-copy="fukugo"]', 'kihon');
  await a.waitForTimeout(1200);
  check('copy discipline structure', state().categories.filter(c => c.discipline === 'kihon').length > kihonBefore);

  // Filtrai: tik problemos, lytis
  await a.check('#ov-problems');
  const probs = await a.locator('#ov-cols .ovb').count();
  await a.uncheck('#ov-problems');
  check('problems filter narrows', probs < await a.locator('#ov-cols .ovb').count());
  await a.click('[data-ovg="F"]');
  check('gender filter', await a.locator('#ov-cols select[data-ov="g"]').evaluateAll(s => s.every(x => x.value === 'F')));
  await a.click('[data-ovg=""]');

  // ✎ - viena kategorija redagavimui
  await a.locator(`[data-ov-edit="${cid}"]`).click();
  check('edit view opens category', await a.locator('#cat-edit').isVisible() && (await a.locator('#cat-name').inputValue()).includes('Fukugo F'));
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});

test('live schedule: fixed time, rename, participants; simple bout view; detached window', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  const state = () => JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
  await a.waitForTimeout(1500);
  if (await a.locator('#run').isVisible()) await a.click('#run-back');

  // Tvarkaraštyje: laikas „ne anksčiau kaip“, pervadinimas, dalyviai
  const card = a.locator('#sched .card.st-now').first();
  const cid = await card.getAttribute('data-c');
  await card.locator('[data-cat-at]').fill('13:45');
  await card.locator('[data-cat-at]').dispatchEvent('change');
  await a.waitForTimeout(1200);
  check('fixed time saved', state().categories.find(c => c.id === cid).at === '13:45');
  check('time shown', (await a.locator(`#sched .card[data-c="${cid}"] [data-cat-at]`).inputValue()) === '13:45');
  await a.locator(`#sched .card[data-c="${cid}"] [data-rename]`).click();
  await a.fill(`[data-cname="${cid}"]`, 'Finalinė kata');
  await a.press(`[data-cname="${cid}"]`, 'Enter');
  await a.waitForTimeout(1200);
  check('renamed in schedule', state().categories.find(c => c.id === cid).name === 'Finalinė kata');
  await a.locator(`#sched .card[data-c="${cid}"] [data-people]`).click();
  check('participants shown', await a.locator(`#sched .card[data-c="${cid}"] .card-people span`).count() >= 2);

  // Paprastas vaizdas: kairėje AKA, dešinėje SHIRO, kas ruošiasi; pažymėjus - rezultatas
  await a.locator(`#sched .card[data-c="${cid}"] [data-open-cat]`).click();
  await a.click('[data-runview="simple"]');
  check('simple view', await a.locator('#draw-view .sv-a .sv-name').count() + await a.locator('#draw-view .sv-done').count() === 1);
  const doneBefore = Object.keys(state().categories.find(c => c.id === cid).draw.results || {}).length;
  if (await a.locator('#draw-view .sv-win').count()) await a.locator('#draw-view .sv-a .sv-win').click();
  else if (await a.locator('#draw-view .sv-a .flag-chip').count()) await a.locator('#draw-view .sv-a .flag-chip').last().click();
  await a.waitForTimeout(1200);
  check('winner marked from simple view', Object.keys(state().categories.find(c => c.id === cid).draw.results || {}).length > doneBefore || await a.locator('#draw-view .sv-done').count() === 1);
  await a.click('[data-runview="lines"]');

  // Atkabinti: atskiras langas su ta rungtimi, tvarkaraštyje ji pilka
  const ctx = a.context();
  const [pop] = await Promise.all([ctx.waitForEvent('page'), a.click('#run-detach')]);
  await pop.waitForURL(/langas=1/);
  await pop.waitForSelector('#run:not([hidden])');
  check('popup shows the category', (await pop.textContent('#run-name')).includes('Finalinė kata'));
  check('popup without menu', !(await pop.locator('#tabs').isVisible()));
  await a.waitForTimeout(500);
  check('detached greyed on board', await a.locator(`#sched .card.detached[data-c="${cid}"]`).count() === 1);
  await pop.close({ runBeforeUnload: true });
  await a.waitForTimeout(800);
  check('back after closing', await a.locator(`#sched .card.detached[data-c="${cid}"]`).count() === 0);
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});

test('history restore, category reset from a round, themes and menu customization', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  const state = () => JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
  await a.waitForTimeout(1500);
  check('snapshot made on save', +sql(`SELECT COUNT(*) FROM tournament_versions WHERE tournament_id = ${tid}`) >= 1);

  // Pakeitimas ir „kaip prieš 5 min.“
  sql(`UPDATE tournament_versions SET created_at = NOW() - INTERVAL 10 MINUTE WHERE tournament_id = ${tid}`);
  const n0 = state().categories.length;
  await a.click('#tab-admin'); await a.click('#tab-kategorijos');
  await a.click('[data-catview="ov"]');
  await a.click('[data-ov-new="kata"]');
  await a.waitForTimeout(1500);
  check('change saved', state().categories.length === n0 + 1);
  await a.click('#hist-toggle');
  await a.waitForSelector('#hist-list tr');
  await a.click('[data-hist-ago="5"]');
  await a.waitForTimeout(1500);
  check('restored as 5 min ago', state().categories.length === n0);
  check('current state kept before restore', +sql(`SELECT COUNT(*) FROM tournament_versions WHERE tournament_id = ${tid} AND note = 'Prieš atkūrimą'`) === 1);
  check('editor shows restored', await a.locator('#ov-cols .ovb').count() === n0);

  // Rungties atstatymas nuo rato
  await a.click('#tab-live');
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  const done = a.locator('#sched .card.st-done').first();
  await done.locator('[data-open-cat]').click();
  // #99: „Grupė baigta“ kortelė dešinėje apačioje gali uždengti įrankius - suskleidžiama „▾“, įrankiai - ekrano viduryje
  if (await a.locator('#run-done.rd-float:not(.rd-min) [data-rd-min]').isVisible()) await a.click('#run-done [data-rd-min]');
  await a.locator('#draw-reset-menu summary').evaluate(e => e.scrollIntoView({ block: 'center' }));
  await a.click('#draw-reset-menu summary');
  const opts = await a.locator('#draw-reset [data-reset]').allTextContents();
  await a.click('#draw-reset-menu summary');
  check('reset options', opts.some(o => /Visą rungtį/.test(o)), opts.join(' | '));

  // Tema ir meniu (bendri visoms varžyboms)
  await a.click('.theme-pick .tp-btn');
  await a.click('.theme-pick [data-theme-id="volcano"]');
  await a.waitForTimeout(600);
  check('theme saved', JSON.parse(sql("SELECT value FROM site_settings WHERE name = 'taika_ui'")).theme === 'volcano');
  await a.click('#menu-cfg');
  await a.fill('[data-menu-name="live"]', 'Varžybų diena');
  await a.locator('[data-menu-up]').nth(1).click();   // Rezultatai ↑ - pirmi
  await a.click('#menu-save');
  await a.waitForTimeout(600);
  await a.reload();
  check('theme applied', await a.evaluate(() => document.body.dataset.theme) === 'volcano');
  check('tab renamed', /Varžybų diena/.test(await a.textContent('#tab-live')));
  const order = await a.$$eval('#tabs [role=tab]', bs => bs.map(b => b.id.replace('tab-', '')).join(','));
  check('tab order changed', order.startsWith('rezultatai,live'), order);
  sql("DELETE FROM site_settings WHERE name = 'taika_ui'");
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});
