// v1.73: #157 Budo varžybų rezultatai persikelia patys (be „Įvesk rezultatus“); #158 Dojo vardas po vardo pakeitimo; #159 arena aiškesnė.
const { test } = require('@playwright/test');
const { sql, day, login, check, noJsErrors } = require('./helpers');

test('#157: competition run in Budo - no „enter results“ to-do, places and points arrive by themselves', async ({ browser }) => {
  const mid = +sql("SELECT am.member_id FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE a.email = 'jaunuolis@karateka.test' AND am.relation = 'self'");
  sql("DELETE FROM tournaments WHERE title LIKE 'E2E Budo%'");
  sql("DELETE FROM events WHERE title LIKE 'E2E%varžybos'");
  sql(`INSERT INTO events (type, title, starts_on) VALUES ('competition', 'E2E Budo varžybos', '${day(-2)}'), ('competition', 'E2E kitų varžybos', '${day(-2)}')`);
  const ev = +sql("SELECT id FROM events WHERE title = 'E2E Budo varžybos'");
  const other = +sql("SELECT id FROM events WHERE title = 'E2E kitų varžybos'");
  const state = JSON.stringify({ people: [{ id: 1, member: mid, club: 'Karateka', name: 'Austėja' }] });
  const results = JSON.stringify({ categories: [{ id: 1, name: 'Kata', places: [{ place: 1, members: [mid] }] }], medals: [] });
  sql(`INSERT INTO tournaments (title, starts_on, is_test, state, results, event_id) VALUES ('E2E Budo varžybos', '${day(-2)}', 0, '${state.replace(/'/g, "''")}', '${results.replace(/'/g, "''")}', ${ev})`);

  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/');
  const txt = await a.locator('#svarbu').textContent();
  check('no „Įvesk rezultatus“ for the Budo competition', !/E2E Budo varžybos/.test(txt), txt.slice(0, 300));
  check('other organiser: asks for our members\' places', /Įrašyk mūsų narių vietas: E2E kitų varžybos/.test(txt));
  check('places and points arrived', +sql(`SELECT COUNT(*) FROM point_awards WHERE event_id = ${ev} AND member_id = ${mid}`) >= 1);
  await a.goto(`admin/renginio-rezultatai.php?id=${ev}`);
  check('results page says it came from Budo', /vyko per Budo/.test(await a.locator('.flash-info').first().textContent()));
  await a.goto(`admin/renginio-rezultatai.php?id=${other}`);
  check('other page says to mark places', /ne per Budo/.test(await a.locator('.flash-info').first().textContent()));

  sql(`DELETE FROM point_awards WHERE event_id IN (${ev}, ${other})`);
  sql("DELETE FROM tournaments WHERE title LIKE 'E2E Budo%'");
  sql(`DELETE FROM events WHERE id IN (${ev}, ${other})`);
  noJsErrors(a);
});

test('#158: new name in settings - member card and Dojo character follow at once', async ({ browser }) => {
  sql("INSERT INTO site_settings (name, value) VALUES ('game_access', 'all') ON DUPLICATE KEY UPDATE value = 'all'");
  const aid = +sql("SELECT id FROM accounts WHERE email = 'narys@karateka.test'");
  const mid = +sql(`SELECT member_id FROM account_members WHERE account_id = ${aid} AND relation = 'self'`);
  const old = sql(`SELECT CONCAT(first_name, '|', last_name) FROM members WHERE id = ${mid}`).split('|');
  const p = await login(browser, 'narys@karateka.test', 1280, 900);
  await p.goto('dojo.php');   // sukuria personažą
  check('hero exists with the old name', sql(`SELECT name FROM game_heroes WHERE member_id = ${mid}`) === old[0]);
  await p.goto('paskyra.php?skiltis=nustatymai');
  const box = p.locator('details:has(input[name="action"][value="name"])').first();
  await box.locator('summary').click();
  await box.locator('input[name=first_name]').fill('Vytautas');
  await Promise.all([p.waitForNavigation(), box.locator('button[type=submit]').click()]);
  check('member card renamed', sql(`SELECT first_name FROM members WHERE id = ${mid}`) === 'Vytautas');
  check('Dojo character renamed', sql(`SELECT name FROM game_heroes WHERE member_id = ${mid}`) === 'Vytautas');
  await p.goto('dojo.php');
  check('Dojo shows the new name', /Vytautas/.test(await p.locator('main').textContent()));
  // Atgal
  sql(`UPDATE accounts SET first_name = '${old[0]}' WHERE id = ${aid}`);
  sql(`UPDATE members SET first_name = '${old[0]}' WHERE id = ${mid}`);
  sql(`UPDATE game_heroes SET name = '${old[0]}' WHERE member_id = ${mid}`);
  noJsErrors(p);
});

test('#159: arena - pick a game, pick the opponent, one big „Žaisti“; choice remembered', async ({ browser }) => {
  sql("INSERT INTO site_settings (name, value) VALUES ('game_access', 'all') ON DUPLICATE KEY UPDATE value = 'all'");
  const p = await login(browser, 'jaunuolis@karateka.test', 375, 812);
  await p.goto('arena.php');
  check('5 games to choose from', await p.locator('.ar-games [data-pick-game]').count() === 5);
  await p.click('[data-pick-game="greita"]');
  check('quick fight: no opponent choice', await p.locator('.ar-levels').isHidden());
  await p.click('[data-pick-game="keturi"]');
  check('four in a row: opponent choice shown', await p.locator('.ar-levels').isVisible());
  await p.click('[data-pick-level="hard"]');
  check('play button says what', /Keturi iš eilės · .*Sunkus/.test(await p.locator('.ar-play').textContent()));
  await p.reload();
  check('choice remembered', await p.locator('[data-pick-game="keturi"].on').count() === 1 && await p.locator('[data-pick-level="hard"].on').count() === 1);
  await p.click('[data-pick-game="fair"]');
  await Promise.all([p.waitForURL(/arena\.php\?m=\d+/), p.click('.ar-play')]);
  check('starts a fight', /arena\.php\?m=\d+/.test(p.url()), p.url());
  noJsErrors(p);
});
