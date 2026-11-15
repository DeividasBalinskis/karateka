// v1.22 (Taikos pertvarka, 1 etapas): Taikos pradžia su visomis varžybomis ir partneriais, perjungimas tarp varžybų,
// meniu (Klubai, Komandos, Dalyviai, Kategorijos | Live tvarkaraštis, Rezultatai), Live tvarkaraštis: dabartinė rungtis,
// vėlavimas varžybų dieną, rungties valdymas su „Toliau →“; testas be paskyros - el. paštas nebūtinas.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('Taika home, switcher, menu order, live schedule with current category and run panel', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  sql(`UPDATE tournaments SET starts_on = CURDATE() WHERE id = ${tid}`);

  // Pradžia: kortelė su veiksmais, partneriai apačioje
  await a.goto('admin/varzybos.php');
  check('competition card', await a.locator(`#t${tid}.varz-card`).count() === 1);
  check('partners strip', await a.locator('.vz-partners .vzp-logo').count() >= 3);
  await nav(a, () => a.locator(`#t${tid} .varz-actions > a.btn-primary`).click());

  // Meniu tvarka ir perjungimas į kitas varžybas
  const tabs = await a.$$eval('.tabs [role=tab]', bs => bs.map(b => b.id.replace('tab-', '')).join(','));
  check('menu order', tabs === 'klubai,komandos,dalyviai,kategorijos,live,rezultatai', tabs);
  check('switcher lists competitions', await a.locator('#t-switch option').count() >= 2);

  // Live tvarkaraštis: dabartinė rungtis, varžybų dieną - ar tatamis vėluoja
  await a.click('#tab-live');
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  await a.waitForSelector('#sched .card');
  check('current category marked', await a.locator('#sched .card.st-now').count() >= 1);
  check('finished categories dimmed', await a.locator('#sched .card.st-done').count() >= 1);
  check('delay shown on competition day', await a.locator('#sched .delay').count() >= 1);

  // Rungties valdymas: atidaroma lenta, juostelė, „Toliau →“
  const now = a.locator('#sched .card.st-now').first();
  const name = (await now.locator('.card-name').innerText()).trim();
  await now.locator('[data-open-cat]').click();
  check('run panel open', await a.locator('#run').isVisible() && !(await a.locator('#live-board').isVisible()));
  check('run shows category', (await a.textContent('#run-name')).includes(name.slice(0, 8)), name);
  check('strip marks open category', await a.locator('.rs-chip.on').count() === 1);
  const nextLabel = await a.textContent('#run-next');
  if (!(await a.locator('#run-next').isDisabled())) {
    await a.click('#run-next');
    check('next opens following category', (await a.textContent('#run-name')).length > 0 && nextLabel.includes((await a.textContent('#run-name')).trim().slice(0, 8)), nextLabel);
  }
  await a.click('#run-back');
  check('back to board', await a.locator('#live-board').isVisible());

  // Kategorijos → „Valdyti →“ atidaro tą rungtį Live tvarkaraštyje
  await a.click('#tab-kategorijos');
  await a.locator('#cat-list button').nth(1).click();
  await a.click('#cat-draw');
  check('manage from categories', await a.locator('#v-live').isVisible() && await a.locator('#run').isVisible());
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});

test('guest quiz: only name required, optional email stored', async ({ browser }) => {
  sql("INSERT INTO quizzes (title, audience, question_count, pass_percent, is_published, share_open, share_token) VALUES ('Svečio testas (e2e)', 'karateka', 1, 50, 1, 1, 'aaaaaaaaaaaaaaaaaaaaaaaa')");
  const qz = +sql("SELECT id FROM quizzes WHERE title = 'Svečio testas (e2e)'");
  sql(`INSERT INTO quiz_questions (quiz_id, body, sort_order) VALUES (${qz}, 'Kas yra kiai?', 1)`);
  const q = +sql(`SELECT id FROM quiz_questions WHERE quiz_id = ${qz}`);
  sql(`INSERT INTO quiz_answers (question_id, body, is_correct, sort_order) VALUES (${q}, 'šūksnis', 1, 0), (${q}, 'smūgis', 0, 1)`);
  const g = await login(browser, null, 390, 844);
  await g.goto('testas-svecias.php?k=aaaaaaaaaaaaaaaaaaaaaaaa');
  await g.fill('input[name=name]', 'Be Klubo');
  await g.fill('input[name=email]', 'netinkamas');
  check('bad email not accepted', await g.$eval('input[name=email]', el => !el.checkValidity()));
  await g.fill('input[name=email]', 'svecias@example.com');
  await nav(g, () => g.click('#guestStart button[type=submit]'));
  await g.locator('.q-answer', { hasText: 'šūksnis' }).click();
  await nav(g, () => g.click('#quizForm button[type=submit]'));
  check('result shown', /100 %/.test(await g.locator('.result-big').innerText()));
  check('email stored, club empty', sql(`SELECT CONCAT(name, '|', COALESCE(club, '-'), '|', email) FROM quiz_guest_attempts WHERE quiz_id = ${qz}`) === 'Be Klubo|-|svecias@example.com');
  sql(`DELETE FROM quizzes WHERE id = ${qz}`);
  noJsErrors(g);
});
