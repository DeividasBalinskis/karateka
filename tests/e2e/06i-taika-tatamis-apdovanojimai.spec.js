// v1.28: sekretoriatas dirba prie savo tatamio (rodomas tik jis, kitą galima pažiūrėti), apačioje - visos tatamio grupės,
// baigus grupę - „Siųsti apdovanojimams“ ir „Sekanti grupė →“; apdovanojimų planšetėje amžiaus grupė žalia, kai visos jos rungtys
// išsiųstos, „Užbaigti“ - „Užbaigta ir apdovanota“; katos balai: 75 → 7.5, katos sąrašas, finalas = pusfinalis + finalas;
// kumite laiko šablonai; kategorijų rikiavimas didėjančia / mažėjančia; ekranas telefone nešokinėja į viršų.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

test('my tatami, next group, send to awards, awards tablet finishes the age group', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 1000);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  sql(`UPDATE tournaments SET starts_on = CURDATE() WHERE id = ${tid}`);
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.click('#tab-live');
  if (await a.locator('#run').isVisible()) await a.click('#run-back');

  // Pirmą kartą: „Prie kurio tatamio dirbate?“ → rodomas tik jis, kitą galima pažiūrėti
  await a.waitForSelector('#area-pick.ask');
  const allAreas = await a.locator('#sched .area').count();
  await a.click('#area-pick [data-my-area="1"]');
  check('only my tatami shown', await a.locator('#sched .area').count() === 1 && allAreas > 1, String(allAreas));
  await a.click('#area-pick [data-view-area="2"]');
  check('peek another tatami', (await a.locator('#sched .area .name').inputValue()) === '2 TATAMIS');
  await a.click('#area-pick [data-view-area=""]');
  check('back to mine', (await a.locator('#sched .area .name').inputValue()) === '1 TATAMIS');
  await a.reload();
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  check('remembered on this device', await a.locator('#sched .area').count() === 1 && !(await a.locator('#area-pick').evaluate(x => x.classList.contains('ask'))));

  // „▶ Dabar: …“ atidaro dabartinę grupę; apačioje - visos tatamio grupės
  await a.click('[data-start-run="1"]');
  await a.waitForSelector('#run:not([hidden])');
  check('all groups listed at the bottom', await a.locator('#run-list .rl-row').count() >= 3 && await a.locator('#run-list .rl-row.on').count() === 1);
  const first = (await a.textContent('#run-name')).trim();

  // Sužaidžiame grupę → „Grupė baigta“, siųsti apdovanojimams, sekanti grupė
  await a.click('#draw-sim');
  await a.waitForSelector('#run-done:not([hidden])');
  check('send to awards offered', await a.locator('#run-done [data-award-send]').count() === 1);
  const catId = await a.locator('#run-done [data-award-send]').getAttribute('data-award-send');
  await a.click('#run-done [data-award-send]');
  await a.waitForTimeout(600);
  check('sent stored', sql(`SELECT status FROM tournament_awards WHERE tournament_id = ${tid} AND cat_id = '${catId}'`) === 'sent');
  check('status chip apdovanojama', /apdovanojama/.test(await a.textContent('#run-strip')));
  const nextBtn = a.locator('#run-done [data-run-go]');
  if (await nextBtn.count()) {
    await nextBtn.click();
    check('next group opened', (await a.textContent('#run-name')).trim() !== first);
  }

  // Apdovanojimų planšetė: visos amžiaus grupės rungtys išsiųstos → žalia → „Užbaigti“
  const token = sql(`SELECT awards_token FROM tournaments WHERE id = ${tid}`);
  const p = await browser.newPage({ viewport: { width: 900, height: 1100 } });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(`varzybos-apdovanojimai.php?k=${token}`);
  await p.waitForSelector('.apd-sum');
  const data = await (await p.request.get(`varzybos-apdovanojimai.php?k=${token}&json=1`)).json();
  const mine = data.cats.find(c => c.id === catId);
  const group = data.cats.filter(c => c.group === mine.group).map(c => c.id);
  if (group.length > 1) check('grey while the age group runs', await p.locator('.apd-g.wait').count() >= 1);
  group.forEach(id => sql(`INSERT IGNORE INTO tournament_awards (tournament_id, cat_id, status, sent_at) VALUES (${tid}, '${id}', 'sent', NOW())`));
  await p.waitForSelector('.apd-g.ready', { timeout: 8000 });
  check('ready group shows podium', await p.locator('.apd-g.ready .apd-places li').count() >= 1);
  await p.click('.apd-g.ready .apd-done');
  await p.waitForSelector('.apd-donebox');
  check('awarded stored', sql(`SELECT status FROM tournament_awards WHERE tournament_id = ${tid} AND cat_id = '${catId}'`) === 'done');
  check('no tablet js errors', !errs.length, errs.join('; '));

  // Sekretoriatas mato „Užbaigta ir apdovanota“ (perkrovus ar po ≤10 s), žiūrovai - „apdovanota“
  await a.reload();
  await a.click('#tab-live');
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  check('board shows awarded', /Užbaigta ir apdovanota/.test(await a.textContent('#sched')));
  const share = sql(`SELECT share_token FROM tournaments WHERE id = ${tid}`);
  const pub = await (await p.request.get(`varzybos-gyvai.php?k=${share}&json=1`)).json();
  check('public sees awarded', pub.areas.some(ar => ar.cats.some(c => c.id === catId && c.aw === 'done')));
  await p.close();
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});

test('kata scores: auto decimal, kata per round, final = semi + final; kumite time presets; sort direction', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 1000);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.click('#tab-kategorijos');

  // Rikiavimas: didėjančia / mažėjančia
  await a.selectOption('#ov-sort', 'count');
  const counts = async () => a.$$eval('.ov-col', cols => cols.map(c => Array.from(c.querySelectorAll('[data-ov-open]')).map(b => +b.textContent.replace(/\D/g, ''))));
  const desc = await counts();
  await a.click('#ov-dir');
  const asc = await counts();
  const sorted = (arr, dir) => arr.every((v, i) => !i || (dir > 0 ? arr[i - 1] <= v : arr[i - 1] >= v));
  check('count descending then ascending', desc.every(c => sorted(c, -1)) && asc.every(c => sorted(c, 1)) && /Didėjančia/.test(await a.textContent('#ov-dir')), JSON.stringify([desc[0], asc[0]]));

  // Katos kategorija balais, 2 ratai
  await a.click('#tab-live');
  if (!(await a.locator('#run').isVisible())) await a.locator('#sched [data-open-cat]').first().click();
  // Kategorija pasirenkama iš apačios sąrašo (visos tatamio grupės)
  const openFromList = async (re) => {
    const rows = a.locator('#run-list .rl-row button');
    const n = await rows.count();
    for (let i = 0; i < n; i++) { if (re.test(await rows.nth(i).innerText())) { await rows.nth(i).click(); return true; } }
    return false;
  };
  check('kata group on tatami', await openFromList(/Kata (M|F)/));
  await a.selectOption('#draw-system', 'score');
  await a.waitForSelector('.score-table');
  await a.selectOption('[data-cfg="rounds"]', '2');
  await a.waitForSelector('[data-cfg="finalists"]');
  await a.fill('[data-cfg="finalists"]', '2'); await a.press('[data-cfg="finalists"]', 'Tab');
  const k = a.locator('.kata-in[data-which="scores"]').first();
  await k.fill('Heian Nidan'); await k.press('Enter');   // Enter - pirmo teisėjo balas
  check('kata column and list', await a.locator('#kata-list option').count() > 20);
  await a.waitForTimeout(100);
  check('enter moves to judge 1', await a.evaluate(() => document.activeElement.dataset.j === '0'));
  await a.keyboard.type('75');
  const j = a.locator('.j-in[data-which="scores"]').first();
  check('auto decimal 75 → 7.5', (await j.inputValue()) === '7.5', await j.inputValue());
  await a.locator('.j-in[data-which="scores"][data-j="3"]').first().click();   // paspaudus kitą langelį - fokusas lieka jame
  await a.waitForTimeout(100);
  check('focus stays where clicked', await a.evaluate(() => document.activeElement.dataset.j === '3'));
  await a.click('#draw-sim');
  await a.waitForSelector('.score-table >> nth=1');
  const head = await a.locator('.score-panel').nth(1).locator('thead').innerText();
  check('final shows semi + final total', /pusfinalis/i.test(head) && /iš viso/i.test(head), head);
  // Vieta finale pagal sumą: pirmos eilutės „Iš viso“ didžiausia
  const totals = await a.locator('.score-panel').nth(1).locator('tbody tr').evaluateAll(rows => rows.map(r => ({ sum: parseFloat(r.querySelector('td:nth-last-child(2)').textContent), place: +r.querySelector('td:last-child').textContent || 0 })));
  const best = totals.reduce((m, x) => x.sum > m.sum ? x : m, totals[0]);
  check('place 1 = highest semi + final', best.place === 1, JSON.stringify(totals));
  check('kata kept after round', (await a.locator('.kata-in[data-which="scores"]').first().inputValue()) === 'Heian Nidan');

  // Kumite kovos skydelis: laiko šablonai
  check('kumite group on tatami', await openFromList(/Kumite/));
  await a.click('[data-runview="lines"]');
  const bout = a.locator('#draw-view .match:not(.done) [data-bout], #draw-view [data-bout]').first();
  if (await bout.count()) {
    await bout.click();
    await a.waitForSelector('.bt-presets');
    await a.click('.bt-presets [data-preset="10"] >> nth=0');
    check('preset sets 0:10', (await a.textContent('#bt-timer')).trim() === '0:10');
    await a.click('.bt-presets [data-preset="90"]');
    check('extension 1:30', (await a.textContent('#bt-timer')).trim() === '1:30');
    await a.click('#bt-close');
  }
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});

test('tatami screen on a phone does not jump to the top', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 1000);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.click('#tab-live');
  await a.waitForTimeout(1500);   // santrauka išsaugoma serveryje
  const share = sql(`SELECT share_token FROM tournaments WHERE id = ${tid}`);
  const p = await browser.newPage({ viewport: { width: 390, height: 760 } });
  await p.goto(`varzybos-gyvai.php?k=${share}&ekranas=1&tatami=1`);
  await p.waitForSelector('.scr-stage');
  const stageFirst = await p.evaluate(() => document.querySelector('.scr-stage').getBoundingClientRect().top < document.querySelector('.scr-groups').getBoundingClientRect().top);
  check('phone: current bout on top, groups below', stageFirst);
  await p.evaluate(() => window.scrollTo(0, 500));
  const y0 = await p.evaluate(() => window.scrollY);
  await p.waitForTimeout(3500);
  const y = await p.evaluate(() => window.scrollY);
  check('scroll position kept', y0 > 100 && Math.abs(y - y0) < 5, y0 + ' → ' + y);
  await p.close();
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});

test('Taika test link: no account, only demo competitions, no real members, can leave feedback', async ({ browser }) => {
  sql("DELETE FROM tournaments WHERE title = 'Tikros varžybos (e2e)'");
  sql("INSERT INTO tournaments (title, starts_on, is_test) VALUES ('Tikros varžybos (e2e)', CURDATE(), 0)");
  const real = +sql("SELECT id FROM tournaments WHERE title = 'Tikros varžybos (e2e)'");
  const a = await login(browser, 'info@karateka.lt', 1300, 900);
  await a.goto('admin/varzybos.php');
  await a.evaluate(() => { document.getElementById('bandymas').open = true; });
  await Promise.all([a.waitForNavigation(), a.click('#bandymas button[value="demo_on"]')]);
  const link = await a.locator('#bandymas .sp-url').inputValue();
  check('test link created', /varzybos-bandymas\.php\?k=[a-f0-9]{24}$/.test(link), link);

  const ctx = await browser.newContext({ viewport: { width: 1300, height: 900 } });
  const g = await ctx.newPage();
  const errs = []; g.on('pageerror', e => errs.push(e.message));
  g.on('dialog', d => d.accept());
  await g.goto(link);
  await g.fill('input[name=name]', 'Tomas');
  await Promise.all([g.waitForNavigation(), g.click('button[type=submit]')]);
  check('lands in Taika', /admin\/varzybos\.php/.test(g.url()) && /Sveiki, Tomas/.test(await g.textContent('.varz-demo-note')));
  check('real competitions hidden', !(await g.content()).includes('Tikros varžybos (e2e)'));
  await Promise.all([g.waitForNavigation(), g.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  check('demo competition', sql(`SELECT CONCAT(is_test, is_demo, COALESCE(created_by, 'null')) FROM tournaments WHERE id = ${tid}`) === '11null');
  check('no real members in state', sql(`SELECT COUNT(*) FROM tournaments WHERE id = ${tid} AND state LIKE '%"member":%' AND state NOT LIKE '%"member":null%'`) === '0'
    && !(await g.evaluate(() => (window.VARZ.members || []).length)));
  check('members button hidden', await g.locator('#ppl-members').isHidden());
  await g.goto(`admin/varzybu-sistema.php?id=${real}`);
  check('real competition not reachable', /admin\/varzybos\.php/.test(g.url()));
  await g.goto('admin/index.php');
  check('club admin not reachable', /prisijungti/.test(g.url()));

  // Atsiliepimas su vardu
  await g.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await g.click('#fbFab');
  await g.fill('#fbText', 'Bandymas: patogu, bet norėčiau didesnių mygtukų');
  await g.press('#fbText', 'Enter');
  await g.waitForTimeout(800);
  check('feedback saved with name', sql("SELECT CONCAT(name, '|', site) FROM feedback WHERE body LIKE 'Bandymas: patogu%'") === 'Tomas (Taikos bandymas)|taika');
  check('no js errors (guest)', !errs.length, errs.join('; '));

  // Išjungus nuorodą - prieiga baigiasi
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('#bandymas button[value="demo_off"]')]);
  await g.goto('admin/varzybos.php');
  check('access ends when link off', /prisijungti/.test(g.url()));
  await ctx.close();
  sql("DELETE FROM feedback WHERE body LIKE 'Bandymas: patogu%'");
  sql(`DELETE FROM tournaments WHERE id = ${real} OR is_test = 1`);
  noJsErrors(a);
});
