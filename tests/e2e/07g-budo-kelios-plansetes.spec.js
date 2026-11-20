// v1.52 (planas D4): kelios planšetės vienu metu - rezultatai nedingsta ir nesusimaišo.
// Anksčiau antroji planšetė gaudavo „Kitas žmogus pakeitė varžybas - perkraukite puslapį“ ir jos įvestas rezultatas dingdavo.
// Dabar konfliktas sujungiamas automatiškai: serverio būsena + šios planšetės pakeistos grupės.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

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
const saved = page => page.waitForFunction(() => /Išsaugota/.test((document.querySelector('.save-state') || {}).textContent || ''), null, { timeout: 10000 });
const nRes = (st, id) => Object.keys((st.categories.find(c => c.id === id).draw || {}).results || {}).length;

test('Budo: two tablets enter results at the same time - both kept, no reload asked', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.waitForFunction(() => /Išsaugota/.test((document.querySelector('.save-state') || {}).textContent || ''), null, { timeout: 15000 });
  await a.waitForTimeout(1200);
  const st = stateOf(tid);
  // Grupės be rezultatų - pirmas paspaudimas tikrai prideda naują rezultatą
  // Test varžybos sudaromos atsitiktinai: jei grupių su 4+ dalyviais be rezultatų per mažai - tinka ir 3 dalyvių
  const free = min => st.categories.filter(c => c.discipline === 'kata' && !c.team && c.competitors.length >= min && c.draw && c.draw.slots && nRes(st, c.id) === 0);
  const katas = free(4).length >= 2 ? free(4) : free(3);
  check('two kata groups', katas.length >= 2);
  const [k1, k2] = katas;
  const before = [nRes(st, k1.id), nRes(st, k2.id)];

  // Abi planšetės atsidaro tuo pačiu metu (ta pati versija)
  const b = await login(browser, 'info@karateka.lt', 1280, 800);
  await openCat(a, tid, k1.id);
  await openCat(b, tid, k2.id);
  // A įveda ir išsaugo pirmoji
  await a.locator('#draw-view [data-flag]').first().click();
  await saved(a); await a.waitForTimeout(900); await saved(a);
  // B - su sena versija: turi susijungti, ne prašyti perkrauti
  await b.locator('#draw-view [data-flag]').first().click();
  await b.waitForTimeout(2500); await saved(b);
  check('no "reload" notice on tablet B', await b.locator('#conflict').isHidden());
  const after = stateOf(tid);
  check('result of tablet A kept', nRes(after, k1.id) === before[0] + 1, JSON.stringify([before, nRes(after, k1.id)]));
  check('result of tablet B kept', nRes(after, k2.id) === before[1] + 1, JSON.stringify([before, nRes(after, k2.id)]));

  // Laisva planšetė A pati pasiima B pakeitimą (kas 8 s), be perkrovimo
  await a.click('#run-back').catch(() => {});
  await a.waitForTimeout(9000);
  const seen = await a.evaluate(() => document.querySelector('.save-state').textContent);
  check('tablet A still saved and in sync', /Išsaugota/.test(seen), seen);
  noJsErrors(a, b);
});
