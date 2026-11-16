// v1.31: žaidimas „Mano dojo“ - herojus, daiktai, dovanų dėžės, gyvūnėliai, kolekcija; admin režimas (adminas mato svetainę kaip narys);
// arena: kova ėjimais su botu, „Greita kova“, „Keturi iš eilės“, „Kata ritmas“; kvietimas kitam žaidėjui; vertimai.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, BASE_URL } = require('./helpers');

const resetGame = (where) => { sql(`DELETE FROM game_heroes WHERE ${where}`); };

test('admin mode: admin sees the site as a member until the switch is on', async ({ browser }) => {
  const a = await login(browser, null, 1280, 900);
  await a.context().clearCookies();
  await a.goto('prisijungti.php');
  await a.fill('input[name=email]', 'info@karateka.lt'); await a.fill('input[name=password]', 'testas123');
  await nav(a, () => a.click('button[type=submit]'));
  check('lands on own dojo', /paskyra\.php/.test(a.url()) && await a.locator('#dojo').count() === 1);
  check('switch off, no Management link', await a.locator('.adm-sw:not(.is-on)').count() === 1 && await a.locator('.nav-account a[href$="admin/"]').count() === 0);
  check('account tabs: dojo, games, management window', await a.locator('.sub-nav a', { hasText: 'Mano dojo' }).count() === 1 && await a.locator('.sub-nav a', { hasText: 'Žaidimai' }).count() === 1 && await a.locator('.sub-nav a', { hasText: 'Valdymas' }).count() === 1);
  await a.goto('renginiai.php');
  check('no staff tools in member view', await a.locator('.staff-bar').count() === 0);
  await nav(a, () => a.click('.adm-sw'));
  check('switch on: staff tools back', await a.locator('.adm-sw.is-on').count() === 1 && await a.locator('.staff-bar').count() === 1 && await a.locator('.nav-account a[href$="admin/"]').count() === 1);
  await nav(a, () => a.click('.adm-sw'));
  check('switch off again', await a.locator('.staff-bar').count() === 0);
  await a.goto('admin/');
  check('opening management turns admin mode on', await a.locator('.adm-sw.is-on').count() === 1);
  noJsErrors(a);
});

test('my dojo: create the hero, test panel, gift box, equip, pet, collection', async ({ browser }) => {
  resetGame('1=1');
  // Vaikas: pirmą kartą - herojaus kūrimas
  const k = await login(browser, 'jaunuolis@karateka.test', 1280, 900);
  await k.waitForSelector('.dw-setup');
  await k.click('.dw-setup');
  await k.waitForSelector('.gm .ed-sp');
  check('first visit invites to create the hero', await k.locator('.gm h2', { hasText: 'Susikurk savo herojų' }).count() === 1);
  check('mystical heroes locked', await k.locator('.ed-sp button.locked').count() === 6);
  await k.click('.ed-sp button[data-sp=panda]');
  await k.fill('#ed-name', 'Pandutė');
  await k.click('[data-e=save]');
  await k.waitForTimeout(600);
  const kid = sql("SELECT h.species, h.name FROM game_heroes h JOIN account_members am ON am.member_id = h.member_id JOIN accounts a ON a.id = am.account_id WHERE a.email='jaunuolis@karateka.test' AND am.relation='self'");
  check('hero saved', kid === 'panda\tPandutė', kid);
  check('kid has no test panel', await k.locator('#g-test').count() === 0);
  check('world with hero and XP bar', await k.locator('.dw .dw-hero svg').count() === 1 && await k.locator('.dojo-xp .xp-track').count() === 1);
  // Pirmas gyvūnėlis
  await k.evaluate(() => { document.querySelector('#g-pets').closest('details').open = true; });
  await k.click('[data-starter=bunny]');
  await k.fill('#g-pet-name', 'Ausytė');
  await k.click('[data-act=adopt]');
  await k.waitForTimeout(700);
  check('pet adopted and in the world', await k.locator('.dw-pet').count() === 1 && sql("SELECT COUNT(*) FROM game_pets WHERE name = 'Ausytė'") === '1');
  noJsErrors(k);

  // Adminas: testavimo skydelis - lygis, visi daiktai, dėžės
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto('paskyra.php');
  await a.waitForSelector('#dojo .dojo-head');
  await a.evaluate(() => { document.querySelector('#g-test').closest('details').open = true; });
  await a.fill('#t-lvl', '6'); await a.click('[data-test=level]'); await a.waitForTimeout(700);
  check('test level applied', (await a.textContent('.dojo-lvl')).includes('6'));
  await a.click('[data-test=all]'); await a.waitForTimeout(900);
  await a.click('[data-test=boxes]'); await a.waitForTimeout(700);
  await a.click('[data-act=boxes]', { force: true });
  await a.click('.box-gift');
  await a.waitForSelector('#box-foot [data-b=ok]', { timeout: 8000 });
  check('gift box opened and shows an item', (await a.textContent('#box-hint')).trim().length > 2);
  await a.click('#box-foot [data-b=ok]');
  // Užsidėti ginklą ir pakeisti aplinką
  await a.evaluate(() => { document.querySelector('#g-inv').closest('details').open = true; });
  await a.click('#g-inv [data-filt=weapon]');
  await a.click('#g-inv [data-item=w_bo]'); await a.click('.gm [data-do=equip]'); await a.waitForTimeout(600);
  check('weapon equipped', await a.locator('#g-equip [data-slot=weapon].on').count() === 1);
  await a.evaluate(() => { document.querySelector('#g-home').closest('details').open = true; });
  await a.click('#g-home [data-bg=bg_sakura]'); await a.waitForTimeout(600);
  check('scenery changed', (await a.getAttribute('.dw', 'style') || '').includes('sakura'));
  // Mistinis personažas (turi visus daiktus - žetonas yra)
  await a.click('#g-inv [data-filt=all]');
  await a.click('#g-inv [data-item=sp_unicorn]'); await a.click('.gm [data-do=become]'); await a.waitForTimeout(700);
  check('became a unicorn', sql("SELECT species FROM game_heroes WHERE account_id IS NULL AND member_id = (SELECT member_id FROM account_members am JOIN accounts x ON x.id = am.account_id WHERE x.email='info@karateka.lt' AND am.relation='self' LIMIT 1)") === 'unicorn' || sql("SELECT COUNT(*) FROM game_heroes WHERE species = 'unicorn'") !== '0');
  await a.evaluate(() => { document.querySelector('#g-col').closest('details').open = true; });
  check('collection shows progress', /\d+ \/ \d+/.test(await a.textContent('#g-col-cnt')));
  noJsErrors(a);
});

test('arena: fight a bot, quick fight, four in a row and kata rhythm', async ({ browser }) => {
  const k = await login(browser, 'jaunuolis@karateka.test', 1280, 900);
  await k.goto('arena.php');
  check('lobby with club players', await k.locator('#ar-heroes .g-hcard').count() > 3);
  await nav(k, () => k.click('[data-bot=easy][data-mode=power]'));
  await k.waitForSelector('.fa-opt');
  await k.click('.fa-opt[data-att=gyaku]'); await k.click('.fa-opt[data-def=age]');
  await k.click('[data-go]');
  await k.waitForSelector('.fa-opt, .fa-end', { timeout: 10000 });
  const mid = +new URL(k.url()).searchParams.get('m');
  const st = JSON.parse(sql(`SELECT state FROM game_matches WHERE id = ${mid}`));
  check('turn resolved against the bot', st.turn === 2 && st.log.length >= 2, JSON.stringify(st.log.slice(0, 3)));
  // Kortos - tik gudrumo kovoje
  check('no cards in power mode', await k.locator('.fa-card').count() === 0);
  await k.click('[data-react="👏"]');
  await k.waitForTimeout(400);
  check('reaction saved', JSON.parse(sql(`SELECT state FROM game_matches WHERE id = ${mid}`)).reactions.length === 1);
  // Greita kova: pradžia, blokas ir smūgis
  await k.goto('arena.php?greita=1');
  await k.click('[data-lv=easy]'); await k.click('#gr-start');
  await k.waitForTimeout(3200);
  await k.keyboard.press('ArrowUp'); await k.keyboard.press('w');
  check('quick fight running', await k.locator('.gr-pad .gr-b').count() === 6 && /\d+ s/.test(await k.textContent('#gr-time')));
  // Keturi iš eilės prieš botą
  await k.goto('arena.php');
  await nav(k, () => k.click('[data-bot=easy][data-game=keturi]'));
  await k.waitForSelector('.k4-col');
  await k.click('.k4-col[data-col="3"]');
  await k.waitForTimeout(800);
  check('token dropped and bot answered', await k.locator('.k4-tok').count() === 2);
  // Kata ritmas
  await k.goto('ritmas.php');
  check('rhythm menu with katas', await k.locator('[data-song]').count() === 3);
  noJsErrors(k);
});

test('invite another player: accept and fight', async ({ browser }) => {
  const k = await login(browser, 'jaunuolis@karateka.test', 1280, 900);
  const p = await login(browser, 'narys@karateka.test', 1280, 900);
  await p.goto('paskyra.php');
  const pid = +sql("SELECT h.id FROM game_heroes h JOIN account_members am ON am.member_id = h.member_id JOIN accounts a ON a.id = am.account_id WHERE a.email='narys@karateka.test' AND am.relation='self'");
  await k.goto('arena.php');
  await k.locator(`[data-inv="${pid}"][data-mode=fair]`).click();
  await k.waitForTimeout(600);
  check('invite created', sql(`SELECT COUNT(*) FROM game_matches WHERE b_hero = ${pid} AND status = 'invited'`) === '1');
  await p.goto('arena.php');
  await p.waitForSelector('[data-acc]');
  await nav(p, () => p.click('[data-acc]'));
  check('match opened for the invited player', /arena\.php\?m=\d+/.test(p.url()));
  await p.waitForSelector('.fa-card');
  check('fair mode has 3 cards', await p.locator('.fa-card').count() === 3);
  await k.waitForURL(/arena\.php\?m=\d+/, { timeout: 8000 });
  check('inviter moved into the fight', /m=\d+/.test(k.url()));
  noJsErrors(k, p);
});

test('game in English', async ({ browser }) => {
  const k = await login(browser, 'jaunuolis@karateka.test', 1280, 900, undefined, 'en');
  await k.goto('paskyra.php');
  await k.waitForSelector('#dojo .dojo-title');
  check('dojo title translated', (await k.textContent('.dojo-title')).includes('My dojo'));
  check('stats translated', (await k.textContent('.dojo-side')).includes('Strength'));
  noJsErrors(k);
});
