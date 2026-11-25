// v1.44: registracijos mygtukų jungikliai (Nustatymai → Registracija), Budo „🧪 +100 testinių“ dalyvių,
// atsiliepimai: „Mano“ - tik savo (net bendroje paskyroje kitoje naršyklėje), „Visi“ - tik skaityti, kito teksto taisyti negalima.
const { test } = require('@playwright/test');
const { sql, linkToken, day, login, nav, check, noJsErrors, openFeedback } = require('./helpers');

test('registration toggles hide every registration button', async ({ browser }) => {
  sql("DELETE FROM site_settings WHERE name = 'reg_off'");
  sql("DELETE FROM events WHERE title = 'E2E jungiklio renginys'");
  sql(`INSERT INTO events (type, title, starts_on, members_only, registration) VALUES ('seminar', 'E2E jungiklio renginys', '${day(9)}', 0, 1)`);
  const a = await login(browser, 'info@karateka.lt');
  await a.goto('admin/nustatymai.php');
  check('toggles listed', await a.locator('#registracija input.tg').count() === 4);
  // Išjungti renginius ir naujas paskyras
  await nav(a, () => a.locator('#registracija input[value=events]').uncheck());
  await nav(a, () => a.locator('#registracija input[value=join]').uncheck());
  check('saved', JSON.parse(sql("SELECT value FROM site_settings WHERE name = 'reg_off'")).sort().join(',') === 'events,join');

  const v = await login(browser, null, 390, 844);
  await v.goto('renginiai.php');
  check('no event register button', await v.locator('.event', { hasText: 'E2E jungiklio renginys' }).locator('.event-reg').count() === 0);
  await v.goto('prisijungti.php');
  check('no create account button', await v.locator('a[href*="registracija.php"]').count() === 0);
  await v.goto('registracija.php');
  check('join page says off', /išjungta/.test(await v.textContent('main')));

  // Įjungti atgal
  await a.goto('admin/nustatymai.php');
  await nav(a, () => a.locator('#registracija input[value=events]').check());
  await nav(a, () => a.locator('#registracija input[value=join]').check());
  await v.goto('renginiai.php');
  check('register button back', await v.locator('.event', { hasText: 'E2E jungiklio renginys' }).locator('a.event-reg').count() === 1);
  sql("DELETE FROM events WHERE title = 'E2E jungiklio renginys'");
  sql("DELETE FROM site_settings WHERE name = 'reg_off'");
  noJsErrors(a);
});

test('Budo: +100 test participants and remove them in one click', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.waitForTimeout(1500);   // test varžybos: kategorijos sudaromos atidarius
  await a.click('#tab-admin'); await a.click('#tab-dalyviai');
  const people = () => Object.keys(JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)).people || {}).length;
  const before = people();
  await a.click('#ppl-test100');
  await a.waitForTimeout(2000);
  check('100 added and saved', people() === before + 100, `${before} -> ${people()}`);
  check('remove button counts them', /\(100\)/.test(await a.textContent('#ppl-test-del')));
  await a.click('#ppl-test-del');
  await a.waitForTimeout(2000);
  check('test people removed', people() === before);
  sql(`DELETE FROM tournaments WHERE id = ${tid}`);
  noJsErrors(a);
});

test('feedback: own list per browser even on a shared account, all list is read only', async ({ browser }) => {
  sql("DELETE FROM feedback WHERE body LIKE 'E2E bendra paskyra%'");
  const a = await login(browser, 'info@karateka.lt');
  await a.goto('naujienos.php');
  await openFeedback(a);
  await a.fill('#fbText', 'E2E bendra paskyra: pirmo žmogaus tekstas');
  await a.click('#fbSend');
  await a.waitForTimeout(1200);
  check('own list shows it', await a.locator('#fbList .fb-item', { hasText: 'pirmo žmogaus' }).count() === 1);
  const fid = +sql("SELECT id FROM feedback WHERE body LIKE 'E2E bendra paskyra%'");

  // Kitas žmogus ta pačia paskyra, kita naršyklė
  const b = await login(browser, 'info@karateka.lt');
  await b.goto('naujienos.php');
  await openFeedback(b);
  await b.waitForTimeout(800);
  check('not in the other person\'s own list', await b.locator('#fbList .fb-item', { hasText: 'pirmo žmogaus' }).count() === 0);
  await b.click('#fbAllBtn');   // v1.87: personalui - atskiras vaizdas „Visi atsiliepimai“
  await b.waitForTimeout(800);
  const ro = b.locator('#fbAllList .fb-ro', { hasText: 'pirmo žmogaus' });
  check('visible in Visi', await ro.count() === 1);
  await ro.click();
  check('read only: text not loaded for editing', (await b.inputValue('#fbText')) === '' && await b.locator('#fbEditing').isHidden());
  // Ir per API taisyti nepavyksta
  const csrf = await b.evaluate(() => JSON.parse(document.getElementById('fb').dataset.cfg).csrf);
  const r = await b.request.post('atsiliepimas.php', { headers: { Accept: 'application/json', 'X-CSRF-Token': csrf }, multipart: { _csrf: csrf, action: 'save', id: String(fid), body: 'perrašyta' } });
  check('cannot edit another person\'s text', r.status() === 404 && /pirmo žmogaus/.test(sql(`SELECT body FROM feedback WHERE id = ${fid}`)));
  sql(`DELETE FROM feedback WHERE id = ${fid}`);
  noJsErrors(b);
});

test('Budo: kata scores on the screen, Jiyu / Pratęsimas, timer resets itself, group done panel on the side', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.waitForTimeout(1500);
  const st = JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
  const kata = st.categories.find(c => c.discipline === 'kata' && !c.team && c.competitors.length >= 2);
  const kumite = st.categories.find(c => c.discipline === 'kumite' && !c.team && c.competitors.length >= 2);
  check('test categories exist', !!kata && !!kumite);

  // Kata balais: įrašius balą - ekrane dalyvis, teisėjų balai ir rezultatas
  await a.click('#tab-live');
  await a.locator(`[data-open-cat="${kata.id}"]`).first().click();
  await a.selectOption('#draw-system', 'score');
  await a.waitForTimeout(400);
  const first = a.locator('#draw-view .j-in[data-sp]').first();
  await first.fill('75'); await a.waitForTimeout(300);
  await a.waitForTimeout(800);
  const live = sql(`SELECT data FROM tournament_live WHERE tournament_id = ${tid} LIMIT 1`);
  check('score pushed live', /"mode":"score"/.test(live) && /7\.5/.test(live), live.slice(0, 200));
  const area = JSON.parse(live).area;
  const share = linkToken('live', tid);
  const scr = await login(browser, null, 1280, 720);
  await scr.goto(`varzybos-gyvai.php?k=${share}&ekranas=1&tatami=${area}`);
  await scr.waitForSelector('.scr-judges');
  check('screen shows judge scores', /7\.5/.test(await scr.textContent('.scr-judges')) && await scr.locator('.scr-board').count() === 1);

  // Kumite: laiko šablonas „Jiyu / Pratęsimas“; pasibaigus laikui - po 3 s atsistato
  await a.click('#run-back');
  await a.locator(`[data-open-cat="${kumite.id}"]`).first().click();
  await a.waitForTimeout(300);
  await a.locator('#draw-view .bout-open').first().click();
  check('preset renamed', await a.locator('.bt-presets button', { hasText: 'Jiyu / Pratęsimas' }).count() === 1);
  await a.locator('.bt-presets button', { hasText: 'Jiyu ippon' }).click();
  await a.click('#bt-start');
  await a.waitForTimeout(11500);
  check('time is over', (await a.textContent('#bt-timer')).trim() === '0:00');
  await a.waitForTimeout(3500);
  check('timer reset by itself', (await a.textContent('#bt-timer')).trim() === '0:10', await a.textContent('#bt-timer'));
  await a.click('#bt-close');
  sql(`DELETE FROM tournaments WHERE id = ${tid}`);
  noJsErrors(a, scr);
});
