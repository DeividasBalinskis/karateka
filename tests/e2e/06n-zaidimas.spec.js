// v1.31: žaidimas „Mano dojo“ - herojus, daiktai, dovanų dėžės, gyvūnėliai, kolekcija; admin režimas (adminas mato svetainę kaip narys);
// arena: kova ėjimais su botu, „Greita kova“, „Keturi iš eilės“, „Kata ritmas“; kvietimas kitam žaidėjui; vertimai.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, BASE_URL } = require('./helpers');

const resetGame = (where) => { sql(`DELETE FROM game_heroes WHERE ${where}`); };

test('admin mode: on by default; view as a member from the menu, clear way back', async ({ browser }) => {
  const a = await login(browser, null, 1280, 900);
  await a.context().clearCookies();
  await a.goto('prisijungti.php');
  await a.fill('input[name=email]', 'info@karateka.lt'); await a.fill('input[name=password]', 'testas123');
  await nav(a, () => a.click('button[type=submit]'));
  // Sprendimas 2026-11-20 (40): treneris ar adminas po prisijungimo - Valdymas → Šiandien
  check('lands on Valdymas → Šiandien', new URL(a.url()).pathname.endsWith('/admin/'), a.url());
  await a.goto('paskyra.php');
  check('own account with a link to the game', await a.locator('.game-teaser').count() === 1 && await a.locator('.nav-game').count() === 1);
  check('no on/off slider; Management link and view menu', await a.locator('.adm-sw.is-on, .adm-knob:visible').count() === 0 && await a.locator('.nav-account a[href$="admin/"]').count() === 1 && await a.locator('.adm-li .view-switch').count() === 1);
  await a.goto('renginiai.php');
  check('staff tools by default', await a.locator('.staff-bar').count() === 1);
  await a.click('.adm-li .view-switch > summary');
  await nav(a, () => a.click('.vs-member-btn'));
  check('member view: no staff tools, clear way back', await a.locator('.staff-bar').count() === 0 && await a.locator('.adm-back').count() === 1);
  await nav(a, () => a.click('.adm-back'));
  check('back to management view', await a.locator('.staff-bar').count() === 1 && await a.locator('.adm-back').count() === 0);
  noJsErrors(a);
});

test('my dojo: create the hero, test panel, gift box, equip, pet, collection', async ({ browser }) => {
  resetGame('1=1');
  // Vaikas: pirmą kartą - herojaus kūrimas
  const k = await login(browser, 'jaunuolis@karateka.test', 1280, 900);
  await nav(k, () => k.click('.nav-game'));
  await k.waitForSelector('.dw-setup');
  await k.click('.dw-setup');
  await k.waitForSelector('.gm .ed-chars');
  check('first visit invites to create the hero', await k.locator('.gm h2', { hasText: 'Susikurk savo herojų' }).count() === 1);
  // Pikselinis personažas - lapė (kitsunė): renkamasi kailio ir liepsnų spalva
  // 23 personažai (22 paveikslėliai + perdažoma lapė; be pandos, baltojo tigro ir varno - v1.46); lapei - kailio ir liepsnų spalva
  check('character picker with the real characters', await k.locator('.ed-chars button').count() === 23 && await k.locator('.ed-chars img.px-char').count() === 22);
  check('each character shows its own skill', await k.locator('.ed-chars .ed-sk').count() === 23);
  await k.click('.ed-chars [data-sp=kitsune]');
  check('repaintable fox: fur and flame colours', await k.locator('.ed-sw [data-sw=flame]').count() >= 6);
  await k.click('.ed-sw [data-sw=fur][data-c="#F4F2EE"]');
  await k.click('.ed-sw [data-sw=flame][data-c="#F27FD0"]');
  await k.fill('#ed-name', 'Pandutė');
  await k.click('[data-e=save]');
  await k.waitForTimeout(600);
  const kid = sql("SELECT h.species, h.name FROM game_heroes h JOIN account_members am ON am.member_id = h.member_id JOIN accounts a ON a.id = am.account_id WHERE a.email='jaunuolis@karateka.test' AND am.relation='self'");
  check('hero saved', kid === 'kitsune\tPandutė', kid);
  check('flame colour saved', /F27FD0/.test(sql("SELECT look FROM game_heroes WHERE name = 'Pandutė'")));
  check('kid has no test panel', await k.locator('#g-test').count() === 0);
  check('world with hero and XP bar', await k.locator('.dw .dw-hero img.px-hero').count() === 1 && await k.locator('.dw .dw-hero svg').count() === 0 && await k.locator('.dw-foot .df-xp').count() === 1);
  // Pirmas gyvūnėlis
  // Visa sąsaja aplinkoje: lenta su savybėmis, rodyklės į žaidimus, įrankiai; skiltys - ritinyje
  check('UI inside the scene: stats board, game signs, tools', await k.locator('.dw-ui .dw-board').count() === 1 && await k.locator('.dw-ui .dw-post .dw-sign').count() === 5 && await k.locator('.dw-ui .dw-tool[data-pane=pets]').count() === 1);
  check('no house or decorations in the world', await k.locator('.dw .dw-house, .dw .dw-decor').count() === 0);
  await k.click('.dw-board'); await k.waitForTimeout(300);
  check('stats unroll as a scroll', await k.locator('.dw-scroll:not([hidden]) [data-pane=stats]:not([hidden]) .g-stat').count() === 4);
  check('character skill with chance on the stats board', /Susikaupimas[\s\S]*10%/.test(await k.locator('.dw-scroll [data-pane=stats] .g-skill').textContent()));
  await k.click('.sc-x');
  await k.click('.dw-tool[data-pane=pets]');
  await k.click('[data-starter=bunny]');
  await k.fill('#g-pet-name', 'Ausytė');
  await k.click('[data-act=adopt]');
  await k.waitForTimeout(700);
  check('pet adopted and in the world', await k.locator('.dw-pet').count() === 1 && sql("SELECT COUNT(*) FROM game_pets WHERE name = 'Ausytė'") === '1');
  // Testavimui - bet kuris gyvūnėlis (ir ne pradinis), galima paleisti
  await k.click('[data-starter=dragonling]'); await k.fill('#g-pet-name', 'Ugnelė'); await k.click('[data-act=adopt]'); await k.waitForTimeout(700);
  check('second (rare) pet adopted in testing', sql("SELECT COUNT(*) FROM game_pets WHERE name = 'Ugnelė' AND species = 'dragonling'") === '1');
  const ug = sql("SELECT id FROM game_pets WHERE name = 'Ugnelė'");
  // Į kovą - 2 gyvūnėliai; paspaudus dar kartą - lieka namie
  await k.click(`[data-active="${ug}"]`); await k.waitForTimeout(700);
  check('two pets go to battle', sql(`SELECT COUNT(*) FROM game_heroes WHERE name = 'Pandutė' AND pet_id IS NOT NULL AND pet2_id = ${ug}`) === '1');
  await k.click(`[data-active="${ug}"]`); await k.waitForTimeout(700);
  check('second pet stays home again', sql("SELECT COUNT(*) FROM game_heroes WHERE name = 'Pandutė' AND pet2_id IS NULL") === '1');
  await k.click(`[data-release="${ug}"]`); await k.waitForTimeout(700);
  check('pet released', sql("SELECT COUNT(*) FROM game_pets WHERE name = 'Ugnelė'") === '0');
  noJsErrors(k);

  // Adminas: testavimo skydelis - lygis, visi daiktai, dėžės
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto('dojo.php');
  await a.waitForSelector('#dojo .dojo-head');
  await a.click('.dw-tool[data-pane=test]');
  await a.fill('#t-lvl', '6'); await a.click('[data-test=level]'); await a.waitForTimeout(700);
  check('test level applied', (await a.textContent('.df-lv')).includes('6'));
  await a.click('[data-test=all]'); await a.waitForTimeout(900);
  await a.click('[data-test=boxes]'); await a.waitForTimeout(700);
  await a.click('.sc-x');
  await a.click('[data-act=boxes]');
  await a.click('.box-gift');
  await a.waitForSelector('#box-foot [data-b=ok]', { timeout: 8000 });
  check('gift box opened and shows an item', (await a.textContent('#box-hint')).trim().length > 2);
  await a.click('#box-foot [data-b=ok]');
  // Užsidėti ginklą ir pakeisti aplinką
  await a.click('.dw-tool[data-pane=equip]');
  await a.click('#g-inv [data-filt=weapon]');
  await a.click('#g-inv [data-item=w_bo]'); await a.click('.gm [data-do=equip]'); await a.waitForTimeout(600);
  check('weapon equipped', await a.locator('#g-equip [data-slot=weapon].on').count() === 1);
  await a.click('.dw-tool[data-pane=home]');
  await a.click('#g-home [data-bg=bg_sakura]'); await a.waitForTimeout(600);
  check('scenery changed', (await a.getAttribute('.dw', 'style') || '').includes('sakura'));
  // Kimono keičia pikselinės lapės spalvą
  await a.click('.dw-tool[data-pane=equip]');
  await a.click('#g-inv [data-filt=all]');
  // Lėtame serveryje ritinys gali būti persipiešęs ir užsidaręs - atidaromas dar kartą
  if (!(await a.locator('#g-inv [data-item=k_emerald]').isVisible())) { await a.click('.dw-tool[data-pane=equip]'); await a.click('#g-inv [data-filt=all]'); }
  await a.click('#g-inv [data-item=k_emerald]'); await a.click('.gm [data-do=equip]'); await a.waitForTimeout(600);
  check('kimono equipped and the pixel hero repainted', await a.locator('#g-equip [data-slot=kimono].on').count() === 1 && /emerald/.test(sql("SELECT equipped FROM game_heroes WHERE account_id = (SELECT id FROM accounts WHERE email='info@karateka.lt') OR member_id IN (SELECT member_id FROM account_members am JOIN accounts x ON x.id = am.account_id WHERE x.email='info@karateka.lt' AND am.relation='self') LIMIT 1")));
  check('not yet pixel slots are hidden', await a.locator('#g-equip [data-slot=head]').count() === 0 && await a.locator('#g-inv [data-item=sp_unicorn]').count() === 0);
  await a.click('.dw-tool[data-pane=col]');
  check('collection shows progress', /\d+ \/ \d+/.test(await a.textContent('#g-col-cnt')));
  noJsErrors(a);
});

test('arena: fight a bot, quick fight, four in a row and kata rhythm', async ({ browser }) => {
  const k = await login(browser, 'jaunuolis@karateka.test', 1280, 900);
  await k.goto('arena.php');
  check('lobby with club players', await k.locator('#ar-heroes .g-hcard').count() > 3);
  await nav(k, () => k.click('[data-bot=easy][data-mode=power]'));
  await k.waitForSelector('.fa-chk');
  // Viena arena: abu pikseliniai kovotojai tame pačiame fone, lentelė - ant fono, kovos eiga - šone
  check('one arena: both pixel fighters, board over the background, log on the side', await k.locator('.fa-arena #f-a img.px-hero').count() === 1 && await k.locator('.fa-arena #f-b img.px-hero').count() === 1
    && await k.locator('.fa-arena .fa-ui .fa-board').count() === 1 && await k.locator('.fa-side #fa-log').count() === 1);
  check('technique named with level and explanation', /Kizami tsuki jodan/.test(await k.textContent('.fa-chk[data-att=kizami] b')) && /\[smūgis į galvą\]/.test(await k.textContent('.fa-chk[data-att=kizami] small'))
    && /\[blokas nuo smūgio į pilvą\]/.test(await k.textContent('.fa-chk[data-def=soto] small')));
  check('low HP at level 1', +(await k.textContent('#hp-a .bar span')).split('/')[1] < 40);
  await k.click('.fa-chk[data-att=gyaku]'); await k.click('.fa-chk[data-def=age]');
  check('checkboxes marked', await k.locator('.fa-chk.sel').count() === 2);
  await k.click('[data-go]');
  await k.waitForSelector('.fa-chk, .fa-end', { timeout: 10000 });
  const mid = +new URL(k.url()).searchParams.get('m');
  const st = JSON.parse(sql(`SELECT state FROM game_matches WHERE id = ${mid}`));
  check('turn resolved against the bot', st.turn === 2 && st.log.length >= 2, JSON.stringify(st.log.slice(0, 3)));
  check('live log shows what happened', await k.locator('.fa-side #fa-log .ln').count() >= 2);
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
  await k.goto('keturi.php');
  check('four in a row has its own start page', await k.locator('.k4-bot').count() === 3 && await k.locator('.k4-cardlist li').count() === 4);
  await nav(k, () => k.click('.k4-bot[value=easy]'));
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
  await p.goto('dojo.php');
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
  await k.goto('dojo.php');
  await k.waitForSelector('#dojo .dojo-title');
  check('dojo title and tools translated', (await k.textContent('.dojo-title')).includes('Dojo') && !/Dovanos/.test(await k.textContent('.dw-bar')));
  await k.click('.dw-board');
  check('stats translated', (await k.textContent('#g-stats')).includes('Strength'));
  noJsErrors(k);
});
