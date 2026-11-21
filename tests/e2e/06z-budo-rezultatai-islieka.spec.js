// v1.48 (planas A1): Budo rezultatai nebedingsta po perkrovimo.
// Klaida: PHP tuščią rezultatų žemėlapį {} grąžindavo kaip [], naršyklė į tokį sąrašą įrašydavo rezultatą, rodydavo „Išsaugota ✓“,
// bet į serverį jis nenukeliaudavo. Čia tikrinama su „sena“ duomenų forma (results: []), kokia jau gali būti duomenų bazėje.
// Antra dalis: dar neišsaugoti burtai visur vienodi - perkrovus puslapį ir kitame įrenginyje poros tos pačios.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

async function testTournament(browser) {
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  // Kategorijas ir pirmus rezultatus test varžyboms sudaro naršyklė pirmą kartą atidarius - palaukiame, kol išsaugos
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.waitForFunction(() => /Išsaugota/.test((document.querySelector('.save-state') || {}).textContent || ''), null, { timeout: 15000 });
  await a.waitForTimeout(1200);
  const st = JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
  return { a, tid, st };
}
const stateOf = tid => JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
async function openCat(page, tid, catId) {
  await page.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await page.click('#tab-live');
  if (await page.locator('#run').isVisible()) await page.click('#run-back');
  if (await page.locator('#area-pick.ask [data-my-area="all"]').count()) await page.click('#area-pick.ask [data-my-area="all"]');
  await page.locator(`[data-open-cat="${catId}"]`).first().click();
  await page.waitForSelector('#run:not([hidden])');
  await page.waitForSelector('#draw-view .draw-head', { state: 'attached' });
}
const saved = page => page.waitForFunction(() => /Išsaugota/.test((document.querySelector('.save-state') || {}).textContent || ''), null, { timeout: 8000 });

test('Budo: a result entered after a reload is kept (kata flags and kumite panel, old stored shape)', async ({ browser }) => {
  const { a, tid, st } = await testTournament(browser);
  const ki = st.categories.findIndex(c => c.discipline === 'kata' && !c.team && c.competitors.length >= 4 && c.draw && c.draw.slots);
  const ui = st.categories.findIndex(c => c.discipline === 'kumite' && !c.team && c.competitors.length >= 4 && c.draw && c.draw.slots);
  check('test categories exist', ki >= 0 && ui >= 0);
  // Sena forma duomenų bazėje: burtai išsaugoti, o tušti žemėlapiai virtę sąrašais
  for (const i of [ki, ui]) {
    sql(`UPDATE tournaments SET state = JSON_REMOVE(JSON_SET(state, '$.categories[${i}].draw.results', JSON_ARRAY(), '$.categories[${i}].draw.scores', JSON_ARRAY()), '$.categories[${i}].draw.bouts') WHERE id = ${tid}`);
  }
  const before = stateOf(tid);
  check('old shape prepared', Array.isArray(before.categories[ki].draw.results) && Array.isArray(before.categories[ui].draw.scores));

  // Kata vėliavėlėmis
  const kata = st.categories[ki];
  await openCat(a, tid, kata.id);
  await a.locator('#draw-view [data-flag]').first().click();
  await saved(a); await a.waitForTimeout(900); await saved(a);
  let now = stateOf(tid).categories[ki].draw;
  check('kata result stored on the server', !Array.isArray(now.results) && Object.keys(now.results).length === 1 && Object.keys(now.scores).length === 1, JSON.stringify([now.results, now.scores]));
  await openCat(a, tid, kata.id);
  check('kata result still shown after reload', await a.locator('#draw-view .flag-chip.on').count() === 1 && /Kovos: 1 \//.test(await a.textContent('#draw-view .draw-head')));

  // Kumite kovos skydelis
  const kum = st.categories[ui];
  await openCat(a, tid, kum.id);
  await a.locator('#draw-view .bout-open').first().click();
  await a.click('[data-pt="a|2"]');
  await a.click('[data-pt="b|1"]');
  await a.click('#bt-finish');
  await a.waitForTimeout(1200); await saved(a);
  now = stateOf(tid).categories[ui].draw;
  const mid = Object.keys(now.results)[0];
  check('kumite result stored on the server', !Array.isArray(now.results) && !!mid && now.scores[mid] === '2:1', JSON.stringify([now.results, now.scores]));
  await openCat(a, tid, kum.id);
  check('kumite result still shown after reload', /Kovos: 1 \//.test(await a.textContent('#draw-view .draw-head')));

  // „Burti iš naujo“ → perkrauti → rezultatas: serveris tuščius žemėlapius saugo kaip objektus
  await a.click('#draw-new'); await a.waitForTimeout(1200); await saved(a);
  now = stateOf(tid).categories[ui].draw;
  check('empty maps are stored as objects', !Array.isArray(now.results) && !Array.isArray(now.scores), JSON.stringify([now.results, now.scores]));
  noJsErrors(a);
});

test('Budo: a draw nobody saved yet is the same after a reload and on a second device', async ({ browser }) => {
  const { a, tid, st } = await testTournament(browser);
  const i = st.categories.findIndex(c => c.discipline === 'kata' && !c.team && c.competitors.length >= 6 && c.draw && c.draw.slots);
  check('category exists', i >= 0);
  const cat = st.categories[i];
  sql(`UPDATE tournaments SET state = JSON_SET(state, '$.categories[${i}].draw', NULL) WHERE id = ${tid}`);
  check('no draw stored', stateOf(tid).categories[i].draw === null);
  const pairs = page => page.evaluate(() => Array.from(document.querySelectorAll('#draw-view [data-drag-slot]')).map(e => e.dataset.dragSlot + ':' + e.dataset.p).join(' '));

  await openCat(a, tid, cat.id);
  const first = await pairs(a);
  check('draw shown', first.split(' ').length === cat.competitors.length, first);
  await openCat(a, tid, cat.id);
  check('same pairs after reload', await pairs(a) === first, await pairs(a));

  const b = await login(browser, 'treneris@karateka.test', 1280, 900);
  await openCat(b, tid, cat.id);
  check('same pairs on a second device', await pairs(b) === first, await pairs(b));

  // „Burti iš naujo“ lieka tikrai atsitiktinis: keli bandymai iš eilės neduoda vis to paties
  const seen = new Set([first]);
  for (let n = 0; n < 4; n++) { await a.click('#draw-new'); await a.waitForTimeout(250); seen.add(await pairs(a)); }
  check('redraw is random', seen.size >= 2, String(seen.size));
  noJsErrors(a, b);
});

// Planas A2 ir B1: greitos pataisos
test('quick fixes: stopwatch starts at 0:00, theme picker closes, coach has no SMS box, game closed by default, role change asks', async ({ browser }) => {
  const { a, tid, st } = await testTournament(browser);
  // #134: laikmatis nuo 0 (Enbu) pradeda nuo 0:00, ne nuo 0:01
  const kum = st.categories.find(c => c.discipline === 'kumite' && !c.team && c.competitors.length >= 4 && c.draw && c.draw.slots);
  await openCat(a, tid, kum.id);
  await a.locator('#draw-view .bout-open').first().click();
  await a.click('.bt-presets [data-up]');
  await a.click('#bt-start');
  await a.waitForTimeout(450);
  check('stopwatch shows 0:00 right after start', (await a.textContent('#bt-timer')).trim() === '0:00', await a.textContent('#bt-timer'));
  await a.waitForTimeout(1100);
  check('stopwatch counts up', /^0:0[1-3]$/.test((await a.textContent('#bt-timer')).trim()), await a.textContent('#bt-timer'));
  await a.click('#bt-start');

  // #127: pasirinkus temą langelis užsidaro
  await a.goto('admin/varzybos.php');
  await a.click('.theme-pick .tp-btn');
  const cur = await a.getAttribute('.theme-pick .tp-sw.on', 'data-theme-id');
  await a.locator('.theme-pick .tp-sw:not(.on)').first().click();
  check('theme panel closes after a choice', await a.locator('.theme-pick .tp-panel').isHidden() && await a.getAttribute('.theme-pick .tp-btn', 'aria-expanded') === 'false');
  await a.click('.theme-pick .tp-btn');
  await a.click(`.theme-pick .tp-sw[data-theme-id="${cur}"]`);

  // Treniruotė: taškų langelis nematomas, kol nepaspausta „+ Taškai“; eilutėje - ne „XP“
  await a.goto('admin/treniruote.php');
  if (await a.locator('#xpQuick').count()) check('quick points form hidden on load', await a.locator('#xpQuick').isHidden());
  await a.goto('admin/');
  check('footer says karate', /tradicinio karate federacija/.test(await a.textContent('body')) && !/karatė federacija/.test(await a.textContent('body')));

  // Rolės: keičiant klausiama; nauja paskyra pagal nutylėjimą - narys
  await a.goto('admin/paskyros.php');
  check('role select asks first', await a.locator('select[name=role][data-ask][data-cur]').count() >= 1);
  if (await a.locator('details summary', { hasText: 'Nauja paskyra' }).count()) await a.locator('details summary', { hasText: 'Nauja paskyra' }).click();
  check('new account defaults to member', await a.evaluate(() => { const s = document.querySelector('form select[name=role]:not([data-ask])'); return s ? s.value : ''; }) === 'member');

  // SMS: treneris varnelės nemato, adminas mato
  const ev = +sql('SELECT id FROM events WHERE starts_on >= CURDATE() ORDER BY starts_on LIMIT 1');
  await a.goto(`admin/renginio-dalyviai.php?id=${ev}`);
  const adminSms = await a.locator('input[name=send_sms]').count();
  const c = await login(browser, 'treneris@karateka.test', 1280, 900);
  await c.goto(`admin/renginio-dalyviai.php?id=${ev}`);
  check('coach has no SMS checkbox, admin has', await c.locator('input[name=send_sms]').count() === 0 && adminSms === 1, String(adminSms));

  // Žaidimas: be nustatymo jo nemato niekas (anksčiau - visi)
  sql("DELETE FROM site_settings WHERE name = 'game_access'");
  await c.goto('dojo.php');
  check('no setting → game closed', !c.url().includes('dojo.php') && await c.locator('.nav-game').count() === 0, c.url());
  sql("INSERT INTO site_settings (name, value) VALUES ('game_access', 'all') ON DUPLICATE KEY UPDATE value = 'all'");
  await c.goto('dojo.php');
  check('explicit all → game open', c.url().includes('dojo.php'));
  noJsErrors(a, c);
});

test('A1: tab closed while saving fails - the change is kept on the device and saved after reopening', async ({ browser }) => {
  const { a, tid, st } = await testTournament(browser);
  const ki = st.categories.findIndex(c => c.discipline === 'kata' && !c.team && c.competitors.length >= 4 && c.draw && c.draw.slots);
  const kata = st.categories[ki];
  await openCat(a, tid, kata.id);
  const draw = () => JSON.stringify(stateOf(tid).categories[ki].draw.results || {});
  const before = draw();
  // Ryšys nutrūksta: išsaugojimas nepavyksta, ir puslapis uždaromas
  await a.route(/varzybu-sistema\.php/, r => r.request().method() === 'POST' ? r.abort() : r.continue());
  await a.locator('#draw-view [data-flag]').first().click();
  await a.waitForTimeout(1500);
  check('server did not get it', draw() === before);
  check('kept on this device', await a.evaluate(id => !!localStorage.getItem('taika-pend-' + id), tid));
  await a.unroute(/varzybu-sistema\.php/);
  const p2 = await a.context().newPage();
  await a.close();
  await p2.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await p2.waitForFunction(() => /Išsaugota/.test((document.querySelector('.save-state') || {}).textContent || ''), null, { timeout: 15000 });
  await p2.waitForTimeout(1000);
  check('change restored and saved after reopening', draw() !== before, draw());
  check('device copy cleared', !(await p2.evaluate(id => localStorage.getItem('taika-pend-' + id), tid)));
});
