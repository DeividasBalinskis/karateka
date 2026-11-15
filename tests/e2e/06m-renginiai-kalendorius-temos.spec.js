// v1.30: Denio atsiliepimai #12-#17 - renginio „📝 Registracija →“ (nuoroda renginyje arba rasta aprašyme), 📅 renginių kalendorius
// ir prenumerata telefonui (kalendorius.php), naujos temos (Kopos, Dojo, Pašvaistė) ir tikroviškesnė gamta.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, day } = require('./helpers');

test('event registration button: from a link in the description and from the event form', async ({ browser }) => {
  sql(`INSERT INTO events (type, title, starts_on, description) VALUES ('competition', 'Lodzės turnyras (e2e)', '${day(9)}', 'Registracija: https://forms.gle/E2eTestForm123 iki penktadienio.')`);
  const ev = +sql("SELECT id FROM events WHERE title = 'Lodzės turnyras (e2e)' ORDER BY id DESC LIMIT 1");
  const g = await login(browser, null, 1280, 900);
  await g.goto('renginiai.php');
  const btn = g.locator(`#e${ev} a.event-reg`);
  check('registration button on the card', await btn.count() === 1 && (await btn.getAttribute('href')) === 'https://forms.gle/E2eTestForm123');
  await g.goto(`renginys.php?id=${ev}`);
  check('registration button on the event page', await g.locator('.ep-reg a.event-reg').count() === 1);

  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto(`admin/renginiai.php?edit=${ev}`);
  await a.fill('input[name=registration_url]', 'https://docs.google.com/forms/d/e/e2e/viewform');
  await nav(a, () => a.click('form.form button.btn-primary[type=submit]'));
  check('link saved', sql(`SELECT registration_url FROM events WHERE id = ${ev}`) === 'https://docs.google.com/forms/d/e/e2e/viewform');
  await g.goto('renginiai.php');
  check('saved link used', (await g.locator(`#e${ev} a.event-reg`).getAttribute('href')) === 'https://docs.google.com/forms/d/e/e2e/viewform');
  sql(`DELETE FROM events WHERE id = ${ev}`);
  noJsErrors(g);
});

test('events calendar: month grid, next month, subscription for phones', async ({ browser }) => {
  sql(`INSERT INTO events (type, title, starts_on, ends_on) VALUES ('camp', 'Vasaros stovykla (e2e)', '${day(2)}', '${day(3)}')`);
  sql(`INSERT INTO events (type, title, starts_on, members_only) VALUES ('other', 'Tik nariams (e2e)', '${day(2)}', 1)`);
  const ev = +sql("SELECT id FROM events WHERE title = 'Vasaros stovykla (e2e)'");
  const ym = day(2).slice(0, 7);
  const g = await login(browser, null, 1280, 900);
  await g.goto('renginiai.php');
  await nav(g, () => g.click('.seg-links a:has-text("Kalendorius")'));
  if (!g.url().includes(ym) && ym !== new Date().toISOString().slice(0, 7)) await g.goto(`renginiai.php?vaizdas=kalendorius&men=${ym}`);
  check('calendar grid', await g.locator('.ev-grid .ev-day').count() >= 28);
  check('event on its days', await g.locator(`.ev-grid a.ev-chip[href*="id=${ev}"]`).count() >= 1);
  check('members-only hidden for guests', await g.locator('.ev-grid a.ev-chip', { hasText: 'Tik nariams (e2e)' }).count() === 0);
  const title = await g.textContent('.ev-cal-head h2');
  await nav(g, () => g.click('.ev-cal-head a[aria-label]:last-of-type'));
  check('next month', (await g.textContent('.ev-cal-head h2')) !== title);
  check('phone subscription link', /^webcal:\/\//.test(await g.getAttribute('.ev-ics', 'href')));
  const res = await g.request.get('kalendorius.php');
  const ics = await res.text();
  check('ics feed', res.headers()['content-type'].includes('text/calendar') && /BEGIN:VCALENDAR/.test(ics) && ics.includes('Vasaros stovykla (e2e)') && !ics.includes('Tik nariams (e2e)'));
  sql("DELETE FROM events WHERE title IN ('Vasaros stovykla (e2e)', 'Tik nariams (e2e)')");
  noJsErrors(g);
});

test('new themes in the picker: dunes, dojo, aurora (dark)', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto('admin/');
  await a.click('.theme-li .tp-btn');
  for (const id of ['dunes', 'dojo', 'aurora']) check('theme ' + id + ' offered', await a.locator(`.theme-li [data-theme-id="${id}"]`).count() === 1);
  await a.click('.theme-li [data-theme-id="aurora"]');
  await a.waitForTimeout(500);
  await a.reload();
  check('aurora is dark', await a.evaluate(() => document.body.dataset.theme === 'aurora' && document.body.classList.contains('theme-dark')));
  const img = await a.request.get('img/temos/pasvaiste.svg');
  check('scene image', img.status() === 200);
  sql("DELETE FROM site_settings WHERE name = 'site_theme'");
  noJsErrors(a);
});
