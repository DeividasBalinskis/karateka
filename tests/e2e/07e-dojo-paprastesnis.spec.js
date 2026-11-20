// v1.49: paprastesnis Dojo (šviesi kortelė, personažas ryškus), reitingas skiltimis (#128), XP = aktyvumo taškai, lygis - dovana
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

test('Dojo: calm look, character stands out, ranking by level and by wins in every game (#128)', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('dojo.php');
  await a.waitForSelector('.dojo.calm .dw-hero img');
  const look = await a.evaluate(() => ({ frame: getComputedStyle(document.querySelector('.dw-frame')).backgroundImage, outline: getComputedStyle(document.querySelector('.dw-hero img')).filter, radius: getComputedStyle(document.querySelector('.dw')).borderTopLeftRadius }));
  check('no wooden frame', look.frame === 'none', look.frame);
  check('character has a light outline', /drop-shadow/.test(look.outline), look.outline);
  check('rounded card like the site', parseFloat(look.radius) >= 20, look.radius);
  const tabs = await a.locator('.dojo-social .ds-tab').allInnerTexts();
  check('ranking tabs: level + each game', tabs.length === 5 && /Lygis/.test(tabs[0]) && tabs.some(t => /Keturi/.test(t)) && tabs.some(t => /ritmas/.test(t)), JSON.stringify(tabs));
  await a.locator('.dojo-social .ds-tab', { hasText: 'Keturi' }).click();
  check('switching tabs', await a.locator('.dojo-social .ds-panel[data-rk="keturi"]').isVisible() && await a.locator('.dojo-social .ds-panel[data-rk="lygis"]').isHidden());
  check('level ranking lists members by real XP', await a.locator('.dojo-social .ds-panel[data-rk="lygis"] li').count() >= 1);
  noJsErrors(a);
});

test('XP = aktyvumo taškai on the member page level card', async ({ browser }) => {
  const p = await login(browser, 'tevai@karateka.test', 1280, 900);
  await p.goto('paskyra.php');
  const card = p.locator('.xp-card').first();
  check('card shows XP and its name', /XP/.test(await card.textContent()) && /aktyvumo taškai/.test(await card.textContent()));
  noJsErrors(p);
});
