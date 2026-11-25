// v1.76: #109 / sprendimas 38 - kairė ranka arenoje: skydas (2 gynybos) arba antras ginklas (2 smūgiai).
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors, closeCelebrate } = require('./helpers');

test('#109: shield in the left hand - two blocks per turn; second weapon - two strikes', async ({ browser }) => {
  sql("INSERT INTO site_settings (name, value) VALUES ('game_access', 'all') ON DUPLICATE KEY UPDATE value = 'all'");
  const k = await login(browser, 'jaunuolis@karateka.test', 1280, 900);
  await k.goto('dojo.php'); await closeCelebrate(k);   // personažas sukuriamas
  const mid = +sql("SELECT am.member_id FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE a.email = 'jaunuolis@karateka.test' AND am.relation = 'self'");
  const hid = +sql(`SELECT id FROM game_heroes WHERE member_id = ${mid}`);
  sql(`INSERT IGNORE INTO game_items (hero_id, item_key, source) VALUES (${hid}, 'o_shield_wood', 'test'), (${hid}, 'o_sai', 'test')`);
  sql(`UPDATE game_heroes SET equipped = JSON_SET(equipped, '$.offhand', 'o_shield_wood') WHERE id = ${hid}`);
  await k.goto('dojo.php'); await closeCelebrate(k);
  check('left hand slot shown in Dojo', await k.locator('.g-slot[data-slot="offhand"]').count() >= 1 || /Kairė ranka/.test(await k.locator('main').textContent()));
  await k.goto('arena.php'); await closeCelebrate(k);
  await k.click('[data-pick-game=power]'); await k.click('[data-pick-level=easy]');
  await Promise.all([k.waitForURL(/arena\.php\?m=\d+/), k.click('.ar-play')]);
  await k.waitForSelector('.fa-chk');
  check('shield hint over the board', /Skydas/.test(await k.locator('.fa-hands').textContent()));
  await k.click('.fa-chk[data-def=age]'); await k.click('.fa-chk[data-def=soto]'); await k.click('.fa-chk[data-att=gyaku]');
  check('two blocks marked', await k.locator('.fa-chk.def.sel').count() === 2);
  const m = +new URL(k.url()).searchParams.get('m');
  await k.click('[data-go]');
  await k.waitForSelector('.fa-chk, .fa-end', { timeout: 10000 });
  const st = JSON.parse(sql(`SELECT state FROM game_matches WHERE id = ${m}`));
  const last = (st.hist && st.hist.a && st.hist.a[st.hist.a.length - 1]) || {};
  check('server took both blocks', last.def2 === 'soto' || last.def2 === 'age', JSON.stringify(last));
  // Antras ginklas: du smūgiai (pirma kova baigiama, kad galima būtų pradėti naują)
  sql(`UPDATE game_matches SET status = 'done' WHERE id = ${m}`);
  sql(`UPDATE game_heroes SET equipped = JSON_SET(equipped, '$.offhand', 'o_sai') WHERE id = ${hid}`);
  await k.goto('arena.php'); await closeCelebrate(k);
  await Promise.all([k.waitForURL(/arena\.php\?m=\d+/), k.click('.ar-play')]);
  await k.waitForSelector('.fa-chk');
  check('two weapons hint', /Du ginklai/.test(await k.locator('.fa-hands').textContent()));
  await k.click('.fa-chk[data-att=kizami]'); await k.click('.fa-chk[data-att=maegeri]'); await k.click('.fa-chk[data-def=age]');
  check('two strikes marked', await k.locator('.fa-chk.att.sel').count() === 2);
  sql(`UPDATE game_heroes SET equipped = JSON_REMOVE(equipped, '$.offhand') WHERE id = ${hid}`);
  noJsErrors(k);
});
