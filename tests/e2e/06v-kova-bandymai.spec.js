// v1.44: 🧪 Nauja kova (bandymai): variantai ◀ ▶, apranga aplink personažą, kiaušinis → gyvūnėlis, kortos, kova ėjimais su 5 zonomis
const { test } = require('@playwright/test');
const { login, nav, check, noJsErrors } = require('./helpers');

test('game trials: variants, paperdoll slots, egg, cards, turn-based fight with hit / blocked marks', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto('dojo.php');
  check('dojo links to the trials', await a.locator('.dw-sign.lab').count() === 1);
  await nav(a, () => a.click('.dw-sign.lab'));
  await a.evaluate(() => { try { localStorage.clear(); } catch (e) {} });
  await a.reload();
  check('variant 1: paperdoll', /Apranga \+ gyvūnėlis/.test(await a.textContent('.lab-vt')) && await a.locator('.lsk-w').count() === 1 && await a.locator('.lsk-m').count() === 3);
  // Kairė ranka: skydas → 2 gynybos zonos; dvirankis ginklas užrakina kairę ranką
  check('shield gives 2 blocks', /2\s*🛡 gynybos zonos/.test(await a.textContent('.lab-stats')));
  await a.click('.lsk-w'); await a.click('.lp-o[data-val="w_bo"]');
  check('two-handed locks the left hand', await a.locator('.lsk-l.locked').count() === 1);
  await a.click('.lsk-w'); await a.click('.lp-o[data-val="w_sai"]');
  await a.click('.lsk-l'); await a.click('.lp-o[data-val="w_kama"]');
  check('second weapon gives 2 attacks', /2\s*👊 smūgiai/.test(await a.textContent('.lab-stats')));
  // Kiaušinis
  await a.click('.lab-egg');
  await a.waitForTimeout(2100);
  check('pet hatched', await a.locator('.lab-hatch .lh-pet').count() === 1);
  await a.click('.lab-hatch .lab-btn');
  check('pet next to the hero', await a.locator('.lab-pets .lab-petbtn').count() === 1);
  // Variantas su kortomis
  await a.click('.lab-arr[data-v="1"]');
  check('variant 2: 3 cards', /3 kortos/.test(await a.textContent('.lab-vt')) && await a.locator('.lab-bottom .lc').count() === 3);
  // Kova: pasirenkama 1 smūgio ir 2 gynybos zonos (skydas), ėjimas išsprendžiamas
  await a.click('.lsk-l'); await a.click('.lp-o[data-val="sh_iron"]');
  await a.click('[data-fight]');
  check('fight screen', await a.locator('.lab-fight .lk-z').count() === 10);
  await a.click('.lk-z.a[data-a="0"]'); await a.click('.lk-z.b[data-b="1"]'); await a.click('.lk-z.b[data-b="2"]');
  check('go enabled when all chosen', await a.locator('#lkGo').isEnabled());
  await a.click('#lkGo');
  await a.waitForSelector('.lab-log li', { timeout: 6000 });
  check('turn resolved with marks', await a.locator('.lab-log li').count() >= 2 && await a.locator('.lz-z.hit, .lz-z.parried, .lz-z.dodged, .lz-z.blk').count() >= 1);
  noJsErrors(a);
});
