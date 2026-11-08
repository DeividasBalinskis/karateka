// v1.19: renginio lankomumas Treniruotės puslapyje, XP keliems ir „+XP“, varžybų kovos skydelis (kumite balai, laikrodis),
// transliacija (žiūrovų nuoroda, ekranas), kitų klubų registracija per nuorodą, DB atnaujinimas vienu mygtuku.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, flashes, day } = require('./helpers');

test('past event attendance on the training page, XP for several, +XP', async ({ browser }) => {
  sql(`INSERT INTO events (type, title, starts_on, start_time, registration) VALUES ('other', 'Rinktinės treniruotė (testas)', '${day(-1)}', '18:00', 1)`);
  const ev = +sql("SELECT id FROM events WHERE title = 'Rinktinės treniruotė (testas)'");
  sql(`INSERT INTO event_participants (event_id, member_id, status) SELECT ${ev}, id, 'going' FROM members WHERE status = 'active' ORDER BY id LIMIT 3`);
  const a = await login(browser, 'info@karateka.lt');

  // Vakarykštis renginys matomas Treniruotėje (neseniai vyko - pažymėkite)
  await a.goto('admin/treniruote.php');
  const tile = a.locator('.event-tile', { hasText: 'Rinktinės treniruotė (testas)' });
  check('recent event tile', await tile.count() === 1);
  await nav(a, () => tile.first().click());
  check('event participants listed', await a.locator('.note-row').count() === 3);
  await a.click('#attAll');
  await a.waitForTimeout(800);
  check('all attended', sql(`SELECT COUNT(*) FROM event_participants WHERE event_id = ${ev} AND attended = 1`) === '3');
  check('event XP given', +sql(`SELECT COUNT(*) FROM point_awards WHERE event_id = ${ev}`) === 3);
  await a.locator('.att-btn.no').first().click();
  await a.waitForTimeout(800);
  check('not present removes XP', +sql(`SELECT COUNT(*) FROM point_awards WHERE event_id = ${ev}`) === 2);

  // „Atėjo, nors nebuvo sąraše“
  await a.selectOption('.ev-add select', { index: 1 });
  await nav(a, () => a.click('.ev-add button'));
  check('walk-in added', (await a.locator('.note-row').count()) === 4);

  // +XP prie vardo
  const before = +sql("SELECT COUNT(*) FROM point_awards pa JOIN point_categories pc ON pc.id = pa.category_id WHERE pc.code = 'help_coach'");
  await a.locator('.xp-btn').first().click();
  await a.selectOption('#xpCat', { label: 'Pagalba treneriui' });
  await a.fill('#xpNote', 'Padėjo apšilime');
  await a.click('#xpGive');
  await a.waitForSelector('.xp-line.just-saved');
  check('+XP saved', +sql("SELECT COUNT(*) FROM point_awards pa JOIN point_categories pc ON pc.id = pa.category_id WHERE pc.code = 'help_coach'") === before + 1);

  // XP keliems: renginio dalyviai jau pažymėti; nauja kategorija čia pat
  await nav(a, () => a.click('a:has-text("XP keliems")'));
  check('event members preselected', await a.locator('#skirti input[name="members[]"]:checked').count() >= 3);
  await a.selectOption('#giveCat', 'new');
  await a.fill('input[name=new_name]', 'Pagalba renginyje (testas)');
  await a.fill('#givePts', '7');
  await nav(a, () => a.click('#skirti button[type=submit]'));
  check('bulk XP flash', /Skirta \+7 XP/.test(await flashes(a)), await flashes(a));
  check('bulk XP rows', +sql("SELECT COUNT(*) FROM point_awards pa JOIN point_categories pc ON pc.id = pa.category_id WHERE pc.name = 'Pagalba renginyje (testas)'") >= 3);
  check('member notified', +sql("SELECT COUNT(*) FROM notifications WHERE type = 'xp_award'") >= 4);

  // Praėjusiam renginiui - „✓ Kas buvo“
  await a.goto('renginiai.php');
  check('who-was-there button on past event', await a.locator(`#e${ev} a:has-text("Kas buvo")`).count() === 1);
  noJsErrors(a);
});

test('competition: kumite bout panel, live link and screen, club registration, DB update button', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.click('#tab-kategorijos');   // sukūrus atsidaro pačios varžybos (Lentelės)
  await a.waitForSelector('#cat-list button');
  check('default system by discipline', /2 trečios/.test(await a.locator('#cat-list').innerText()));

  // Kumite kova: balai ir laikrodis
  await a.locator('#cat-list button', { hasText: 'Kumite' }).first().click();
  await a.click('#tab-lenteles');
  await a.click('#draw-clear');
  await a.locator('.bout-open').first().click();
  check('bout panel open', await a.locator('#bout').isVisible());
  await a.click('[data-pt="a|3"]');
  await a.click('[data-pt="b|1"]');
  await a.click('#bt-start');
  await a.waitForTimeout(1200);
  check('timer runs', (await a.textContent('#bt-timer')) !== '2:00');
  await a.waitForTimeout(500);
  check('live row stored', +sql(`SELECT COUNT(*) FROM tournament_live WHERE tournament_id = ${tid}`) >= 1);
  const share = sql(`SELECT share_token FROM tournaments WHERE id = ${tid}`);
  const v = await login(browser, null, 390, 844);
  await v.goto(`varzybos-gyvai.php?k=${share}`);
  await v.waitForSelector('.gv-now', { timeout: 10_000 });
  check('viewer sees live bout', /3/.test(await v.locator('.gv-now').first().innerText()));
  const scr = await login(browser, null, 1280, 720);
  await scr.goto(`varzybos-gyvai.php?k=${share}&ekranas=1&tatami=${sql(`SELECT area_id FROM tournament_live WHERE tournament_id = ${tid} LIMIT 1`)}`);
  await scr.waitForSelector('.scr-score', { timeout: 10_000 });
  check('screen shows score', (await scr.locator('.scr-score').allInnerTexts()).join(':') === '3:1');
  await a.click('#bt-finish');
  await a.waitForTimeout(800);
  check('bout result saved', await a.locator('.m-score').first().inputValue() === '3:1');
  const bad = await login(browser, null);
  const res = await bad.goto('varzybos-gyvai.php?k=000000000000000000000000');
  check('wrong live token 404', res.status() === 404);

  // Registracija kitiems klubams
  await a.click('#share-toggle');
  await a.check('#reg-open');
  await a.waitForTimeout(500);
  const reg = sql(`SELECT reg_token FROM tournaments WHERE id = ${tid}`);
  const c = await login(browser, null, 390, 844);
  await c.goto(`varzybos-registracija.php?k=${reg}`);
  await c.selectOption('#regClub', '__other');
  await c.fill('input[name=club_other]', 'Testo klubas');
  await c.fill('input[name=contact]', 'treneris@testas.lt');
  await c.locator('input[name="name[]"]').first().fill('Ona Testaitė');
  await c.locator('input[name="birth[]"]').first().fill('2014');
  await c.locator('select[name="gender[]"]').first().selectOption('F');
  await nav(c, () => c.click('form.reg-form button[type=submit]'));
  check('registration stored', sql(`SELECT COUNT(*) FROM tournament_entries WHERE tournament_id = ${tid} AND status = 'new'`) === '1');
  await a.click('#tab-dalyviai');
  await a.click('#en-refresh');
  await a.waitForSelector('#en-add-all');
  await a.click('#en-add-all');
  await a.waitForTimeout(1500);
  check('registration added', sql(`SELECT COUNT(*) FROM tournament_entries WHERE tournament_id = ${tid} AND status = 'added'`) === '1');
  check('registered person in state', /Ona Testaitė/.test(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)));

  // DB atnaujinimas vienu mygtuku: trūkstamo failo nėra - mygtuko nėra; ištrynus žymą - mygtukas ir importas
  await a.goto('admin/kopijos.php');
  check('no update needed', await a.locator('.db-update-box').count() === 0);
  sql("DELETE FROM db_versija WHERE failas = '026_lankomumas_xp_varzybos.sql'");
  await a.goto('admin/kopijos.php');
  check('update box shown', await a.locator('.db-update-box').count() === 1);
  await nav(a, () => a.click('.db-update-box button'));
  check('db updated flash', /Duomenų bazė atnaujinta/.test(await flashes(a)), await flashes(a));
  check('026 recorded again', sql("SELECT COUNT(*) FROM db_versija WHERE failas = '026_lankomumas_xp_varzybos.sql'") === '1');
  sql('DELETE FROM tournaments WHERE is_test = 1');   // kiti testai tikisi švarių varžybų sąrašo
  noJsErrors(a, v, scr, c);
});
