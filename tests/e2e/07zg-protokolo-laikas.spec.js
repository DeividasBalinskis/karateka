// Deividas: kiekvienas egzamino protokolas turi savo pradžios laiką - įrašomas „+ Protokolas šiai datai“ formoje,
// keičiamas protokolo antraštėje (išsaugoma iš karto); rodomas Egzaminų sąraše (tos dienos protokolai - pagal laiką),
// Excel / CSV antraštėje ir „Mano egzaminas“ puslapyje.
const { test } = require('@playwright/test');
const { sql, day, login, nav, check, noJsErrors } = require('./helpers');

const clean = () => {
  sql("DELETE FROM exam_protocol_rows WHERE event_id IN (SELECT id FROM events WHERE title LIKE 'Laiko egzaminas%')");
  sql("DELETE FROM exam_protocols WHERE event_id IN (SELECT id FROM events WHERE title LIKE 'Laiko egzaminas%')");
  sql("DELETE FROM events WHERE title LIKE 'Laiko egzaminas%'");
};

test('exam protocol start time: set when copying, changed on the protocol page, shown in the list, Excel and Mano egzaminas', async ({ browser }) => {
  clean();
  sql(`INSERT INTO events (type, title, starts_on, start_time, members_only, registration) VALUES ('exam', 'Laiko egzaminas', '${day(11)}', '10:00:00', 0, 0)`);
  const ev = sql("SELECT id FROM events WHERE title = 'Laiko egzaminas'");
  const a = await login(browser, 'info@karateka.lt', 1440, 900);

  // „+ Protokolas šiai datai“: laikas iš anksto toks pat kaip šio protokolo, galima pakeisti
  await a.goto('admin/egzaminai.php');
  const form = a.locator(`form.exam-copy:has(input[name="id"][value="${ev}"])`);
  check('copy form: time field with the same time', await form.locator('input[name="start_time"]').inputValue() === '10:00');
  await form.locator('input[name="label"]').fill('1-3 kyu');
  await form.locator('input[name="start_time"]').fill('08:30');
  await nav(a, () => form.locator('button[type="submit"]').click());
  const kid = sql("SELECT id FROM events WHERE title = 'Laiko egzaminas · 1-3 kyu'");
  check('new protocol has its own time', sql(`SELECT start_time FROM events WHERE id = ${kid}`) === '08:30:00');
  check('main exam keeps its time', sql(`SELECT start_time FROM events WHERE id = ${ev}`) === '10:00:00');
  const rows = await a.locator('.exam-row', { hasText: 'Laiko egzaminas' }).allInnerTexts();
  check('list: same day sorted by time, time shown', rows.length === 2 && /08:30/.test(rows[0]) && /1-3 kyu/.test(rows[0]) && /10:00/.test(rows[1]), JSON.stringify(rows));

  // Protokolo antraštė: laikas keičiamas ir išsaugomas iš karto (be mygtuko)
  await a.goto(`admin/egzamino-protokolas.php?id=${kid}`);
  check('protocol header shows its time', await a.inputValue('#protTime') === '08:30');
  const saved = () => a.waitForResponse(r => r.url().includes('save=1') && r.request().method() === 'POST');
  await Promise.all([saved(), a.fill('#protTime', '13:15')]);
  await a.waitForFunction(() => /Išsaugota/.test(document.getElementById('protSaved').textContent));
  check('time saved at once', sql(`SELECT start_time FROM events WHERE id = ${kid}`) === '13:15:00');
  check('printed header follows', (await a.textContent('#protTimePrint')).trim() === '13:15');
  check('other protocol of the day unchanged', sql(`SELECT start_time FROM events WHERE id = ${ev}`) === '10:00:00');
  await a.reload();
  check('kept after reload', await a.inputValue('#protTime') === '13:15');
  const bad = await a.evaluate(async () => (await fetch(location.pathname + location.search + '&save=1', { method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('input[name=_csrf]').value },
    body: JSON.stringify({ row: 0, field: 'start_time', value: '25:99' }) })).status);
  check('wrong time refused', bad === 400 && sql(`SELECT start_time FROM events WHERE id = ${kid}`) === '13:15:00');
  const csv = await (await a.request.get(`admin/egzamino-protokolas.php?id=${kid}&csv=1`)).text();
  check('Excel / CSV header has the time', /Pradžios laikas: 13:15/.test(csv));

  // Dalyvis mato savo protokolo laiką „Mano egzaminas“ puslapyje
  await a.click('.prot-add > summary');
  await a.fill('.prot-add-form input[name="name"]', 'Laiko Dalyvis');
  await nav(a, () => a.click('.prot-add-form button[type="submit"]'));
  const link = await a.locator('#protTable tr[data-row] .pr-link').first().getAttribute('data-copy');
  const tok = (link.match(/[?&][kt]=([^&]+)/) || [])[1];
  const v = await login(browser, null, 375, 812);
  await v.goto(`mano-egzaminas.php?t=${tok}`);
  check('Mano egzaminas shows the protocol time', /13:15/.test(await v.textContent('.my-exam-hero')));

  // Telefone laukas matomas; ištrynus laiką - protokolas be laiko
  const p = await login(browser, 'info@karateka.lt', 375, 812);
  await p.goto(`admin/egzamino-protokolas.php?id=${kid}`);
  check('phone: time field visible', await p.locator('#protTime').isVisible());
  await Promise.all([saved(), a.fill('#protTime', '')]);
  check('time cleared', sql(`SELECT start_time IS NULL FROM events WHERE id = ${kid}`) === '1');
  await a.goto('admin/egzaminai.php');
  const after = await a.locator('.exam-row', { hasText: '1-3 kyu' }).innerText();
  check('list: no time for the cleared protocol', !/13:15/.test(after), after);
  clean();
  noJsErrors(a, v, p);
});
