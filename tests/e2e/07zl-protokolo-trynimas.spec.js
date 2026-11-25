// v2.08 (#179): Valdymas → Egzaminai - administratorius gali ištrinti protokolą („🗑“). Tuščias - po vieno klausimo;
// su dalyviais - pirma parodoma, kas jame yra, ir klausiama dar kartą. Papildomi tos dienos protokolai lieka. Treneris trinti negali.
const { test } = require('@playwright/test');
const { sql, login, nav, day, check, noJsErrors } = require('./helpers');

test('#179: admin deletes an exam protocol; with participants it shows who is in it and asks again', async ({ browser }) => {
  sql(`INSERT INTO events (type, title, starts_on, start_time) VALUES ('exam', 'E2E tuščias protokolas', '${day(9)}', '10:00')`);
  const empty = +sql("SELECT MAX(id) FROM events WHERE title = 'E2E tuščias protokolas'");
  sql(`INSERT INTO events (type, title, starts_on, start_time) VALUES ('exam', 'E2E protokolas su dalyviais', '${day(10)}', '10:00')`);
  const full = +sql("SELECT MAX(id) FROM events WHERE title = 'E2E protokolas su dalyviais'");
  sql(`INSERT INTO events (type, title, starts_on, start_time, parent_id) VALUES ('exam', 'E2E protokolas su dalyviais · 4 kyu', '${day(10)}', '12:00', ${full})`);
  const kid = +sql("SELECT MAX(id) FROM events WHERE title = 'E2E protokolas su dalyviais · 4 kyu'");
  sql(`INSERT INTO event_participants (event_id, member_id, status) SELECT ${full}, id, 'going' FROM members WHERE status = 'active' ORDER BY id LIMIT 2`);
  const names = sql(`SELECT CONCAT(m.first_name, ' ', m.last_name) FROM event_participants ep JOIN members m ON m.id = ep.member_id WHERE ep.event_id = ${full} ORDER BY m.id LIMIT 1`);

  // Treneris: „🗑“ nėra, siųstas prašymas nieko netrina
  const c = await login(browser, 'treneris@karateka.test', 1440, 900);
  await c.goto('admin/egzaminai.php');
  check('coach sees no delete button', await c.locator('.exam-del').count() === 0);
  const tok = await c.evaluate(() => (document.querySelector('input[name=_csrf]') || {}).value || '');
  await c.request.post('admin/egzaminai.php', { form: { _csrf: tok, action: 'delete_protocol', id: String(empty), confirm: '1' } });
  check('coach cannot delete', sql(`SELECT COUNT(*) FROM events WHERE id = ${empty}`) === '1');

  // Administratorius: tuščias protokolas - vienas klausimas (patvirtinama automatiškai) ir ištrinta
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/egzaminai.php');
  const delEmpty = a.locator(`form:has(input[name=id][value="${empty}"]) .exam-del`);
  check('admin sees the delete button', await delEmpty.count() === 1);
  await nav(a, () => delEmpty.click());
  check('empty protocol deleted', sql(`SELECT COUNT(*) FROM events WHERE id = ${empty}`) === '0');
  check('deleted message', /Protokolas ištrintas/.test(await a.textContent('body')));

  // Su dalyviais: pirma - kas jame yra, tik po to „Taip, ištrinti su 2 dalyviais“
  await nav(a, () => a.locator(`form:has(input[name=id][value="${full}"]) .exam-del`).click());
  await a.waitForSelector('#trinti');
  check('not deleted before the second question', sql(`SELECT COUNT(*) FROM events WHERE id = ${full}`) === '1');
  const box = await a.textContent('#trinti');
  check('shows how many and who', /Jame yra dalyvių: 2/.test(box) && box.includes(names), box.slice(0, 300));
  check('button says how many', /Taip, ištrinti su 2 dalyviais/.test(await a.textContent('#exam-del-yes')));
  await nav(a, () => a.click('#exam-del-yes'));
  check('protocol with participants deleted', sql(`SELECT COUNT(*) FROM events WHERE id = ${full}`) === '0');
  check('its registrations deleted too', sql(`SELECT COUNT(*) FROM event_participants WHERE event_id = ${full}`) === '0');
  check('the other protocol of that day stays and becomes the main one', sql(`SELECT IFNULL(parent_id, 'NULL') FROM events WHERE id = ${kid}`) === 'NULL');

  // Atšaukti - nieko netrina
  sql(`INSERT INTO event_participants (event_id, member_id, status) SELECT ${kid}, id, 'going' FROM members WHERE status = 'active' ORDER BY id LIMIT 1`);
  await a.goto('admin/egzaminai.php');
  await nav(a, () => a.locator(`form:has(input[name=id][value="${kid}"]) .exam-del`).click());
  await nav(a, () => a.click('#trinti a.btn'));
  check('cancel keeps the protocol', sql(`SELECT COUNT(*) FROM events WHERE id = ${kid}`) === '1');

  sql(`DELETE FROM events WHERE id IN (${empty}, ${full}, ${kid})`);
  noJsErrors(a, c);
});
