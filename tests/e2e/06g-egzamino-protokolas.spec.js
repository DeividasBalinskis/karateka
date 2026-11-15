// v1.26: egzamino protokolas internete (pagal LTKA formą): dalyviai su unikaliais numeriais MM-mm-dd-eilė, balai ir pastabos
// išsaugomi iš karto, „Išlaikė / Neišlaikė“, neatvykusio numeris daugiau nepriskiriamas, Excel (CSV), perkėlimas į rezultatus.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, flashes, day } = require('./helpers');

test('exam protocol: numbers, autosave, pass/fail, deleted number not reused, CSV, results', async ({ browser }) => {
  sql(`INSERT INTO events (type, title, starts_on, start_time) VALUES ('exam', 'Kyu egzaminas (protokolas e2e)', '${day(3)}', '11:00')`);
  const ev = +sql("SELECT id FROM events WHERE title = 'Kyu egzaminas (protokolas e2e)'");
  sql(`INSERT INTO event_participants (event_id, member_id, status) SELECT ${ev}, id, 'going' FROM members WHERE status = 'active' ORDER BY id LIMIT 3`);
  const prefix = day(3).slice(2).replace(/-/g, '-');   // 26-11-18
  const a = await login(browser, 'info@karateka.lt', 1400, 900);

  // Iš renginio rezultatų puslapio - nuoroda į protokolą
  await a.goto(`admin/renginio-rezultatai.php?id=${ev}`);
  await nav(a, () => a.click('a:has-text("Egzamino protokolas")'));
  check('protocol page', /EGZAMINO PROTOKOLAS/.test(await a.locator('.prot-title').innerText()));
  check('default organisation', /LIETUVOS TRADICINIO KARATE ASOCIACIJA/.test(await a.locator('.prot-org').innerText()));

  // Dalyviai ir numeriai
  await nav(a, () => a.click('button:has-text("Įtraukti užsiregistravusius")'));
  const numbers = await a.locator('.prot-nr').allInnerTexts();
  check('three participants numbered', numbers.length === 3 && numbers.every((n, i) => n === `${prefix}-0${i + 1}`), numbers.join(','));

  // Balai ir „Išlaikė“ išsaugomi iš karto
  const row = a.locator('#protTable tbody tr').first();
  const rid = await row.getAttribute('data-row');
  await row.locator('[data-f="kata"]').fill('9');
  await row.locator('[data-f="kata"]').press('Tab');
  await row.locator('[data-res="passed"]').click();
  await a.waitForTimeout(700);
  check('autosaved', sql(`SELECT CONCAT(kata, '|', result) FROM exam_protocol_rows WHERE id = ${rid}`) === '9|passed');

  // Antraštė: kyu, egzaminatorius - prisimenamas kitam protokolui
  await a.click('.prot-head-form summary');
  await a.fill('input[name=kyu_label]', '8-5');
  await a.fill('input[name=examiner_name]', 'Giedrius Dranevičius');
  await a.fill('input[name=examiner_license]', 'LT-007');
  await nav(a, () => a.click('.prot-head-form button[type=submit]'));
  check('header saved', /8-5/.test(await a.locator('.prot-meta').innerText()) && /Giedrius Dranevičius/.test(await a.locator('.prot-sign').innerText()));

  // Neatvykęs: ištrintas, jo numeris nepriskiriamas naujam
  const last = a.locator('#protTable tbody tr').last();
  await nav(a, () => last.locator('button[title*="Neatvyko"]').click());
  check('deleted from list', await a.locator('#protTable tbody tr').count() === 2);
  await a.click('.prot-add summary');
  await a.fill('.prot-add input[name=name]', 'Svečias Iš Kito Klubo');
  await nav(a, () => a.click('.prot-add button[type=submit]'));
  const after = await a.locator('.prot-nr').allInnerTexts();
  check('deleted number not reused', after.includes(`${prefix}-04`) && !after.includes(`${prefix}-03`), after.join(','));

  // Excel (CSV) ir perkėlimas į rezultatus
  const csv = await (await a.request.get(`admin/egzamino-protokolas.php?id=${ev}&csv=1`)).text();
  check('csv has protocol', /EGZAMINO PROTOKOLAS/.test(csv) && csv.includes(`${prefix}-01`) && /Išlaikė/.test(csv));
  await nav(a, () => a.click('button:has-text("renginio rezultatai")'));
  check('passed moved to results', /Perkelta/.test(await flashes(a)), await flashes(a));
  sql(`DELETE FROM events WHERE id = ${ev}`);
  noJsErrors(a);
});
