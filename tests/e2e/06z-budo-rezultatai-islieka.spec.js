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
