// v1.95: šriftų bandymas „Aa“ (tik administratoriams, slapukas), „👍 Patinka“ temoms ir šriftams (matoma diegimas-api.php),
// gražesnės temos (kortelės su temos atspalviu, spalvos juostelė temų paveikslėliuose).
const { test } = require('@playwright/test');
const { execFileSync } = require('child_process');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('Aa font test for admins, 👍 likes for fonts and themes, themed cards', async ({ browser }) => {
  sql("DELETE FROM site_settings WHERE name = 'design_likes'");
  sql("UPDATE accounts SET theme = 'sakura' WHERE email = 'info@karateka.lt'");
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/');
  check('Aa next to 🎨', await a.locator('.theme-li .fp-btn').count() === 1 && await a.locator('.theme-li .tp-btn').count() === 1);
  const panelBg = await a.$eval('.panel', (el) => getComputedStyle(el).backgroundColor);
  check('sakura cards have a pink tint', /rgba\(255, 248, 250/.test(panelBg), panelBg);

  // Šriftas: atidarius matomi visi, paspaudus - pritaikoma visam puslapiui
  await a.click('.fp-btn');
  check('font list', await a.locator('.fp-sw').count() >= 12 && /Manrope/.test(await a.locator('.fp-panel').innerText()));
  await nav(a, () => a.click('.fp-sw[data-font-id="manrope"]'));
  check('font applied', /Manrope/.test(await a.$eval('body', (el) => getComputedStyle(el).fontFamily)) && /Manrope/.test(await a.$eval('h1', (el) => getComputedStyle(el).fontFamily)));
  check('override stylesheet loaded', await a.locator('link[href*="sriftas.php?f=manrope"]').count() === 1);
  const css = await (await a.request.get('sriftas.php?f=manrope')).text();
  check('override keeps mono, covers class rules', /\.kicker|\.event-date \.d|h1/.test(css) && !/JetBrains[^;]*!important/.test(css));

  // 👍 šriftui ir temai
  await a.click('.fp-btn');
  await a.check('.fp-item:has(.fp-sw[data-font-id="manrope"]) .dl-chk');
  await a.waitForTimeout(400);
  await a.click('.theme-pick .tp-btn');
  check('theme tiles have a colour strip', /#B4436F/i.test(await a.getAttribute('.tp-sw[data-theme-id="sakura"] .tp-prev', 'style')));
  await a.check('.tp-item:has(.tp-sw[data-theme-id="sakura"]) .dl-chk');
  await a.waitForTimeout(400);
  const likes = JSON.parse(sql("SELECT value FROM site_settings WHERE name = 'design_likes'"));
  const me = +sql("SELECT id FROM accounts WHERE email = 'info@karateka.lt'");
  check('likes saved', likes.font.manrope.includes(me) && likes.theme.sakura.includes(me), JSON.stringify(likes));
  await a.reload();
  await a.click('.theme-pick .tp-btn');
  check('like stays ticked, name shown', await a.isChecked('.tp-item:has(.tp-sw[data-theme-id="sakura"]) .dl-chk') && /Denis/.test(await a.locator('.tp-item:has(.tp-sw[data-theme-id="sakura"]) .dl-like').innerText()));
  const hash = execFileSync('php', ['-r', 'echo password_hash("e2e-api-key", PASSWORD_DEFAULT);']).toString();
  sql(`REPLACE INTO site_settings (name, value) VALUES ('feedback_api_key', '${hash}')`);
  const dj = await (await a.request.get('diegimas-api.php', { headers: { 'X-Api-Key': 'e2e-api-key' } })).json();
  check('API shows who liked what', (dj.design_likes.font.manrope || []).includes('Denis') && (dj.design_likes.theme.sakura || []).includes('Denis'), JSON.stringify(dj.design_likes));
  // Atžymėjus - dingsta
  await a.uncheck('.tp-item:has(.tp-sw[data-theme-id="sakura"]) .dl-chk');
  await a.waitForTimeout(400);
  check('unlike removes', !JSON.parse(sql("SELECT value FROM site_settings WHERE name = 'design_likes'")).theme.sakura);
  // Grąžinti dabartinį šriftą
  await a.keyboard.press('Escape');
  await a.click('.fp-btn');
  await nav(a, () => a.click('.fp-sw[data-font-id="default"]'));
  check('default font back', /Inter/.test(await a.$eval('body', (el) => getComputedStyle(el).fontFamily)) && await a.locator('link[href*="sriftas.php"]').count() === 0);

  // Treneris (ne adminas): nei „Aa“, nei 👍; slapukas jam nieko nekeičia
  const c = await login(browser, 'treneris@karateka.test', 1440, 900);
  await c.context().addCookies([{ name: 'sriftas', value: 'manrope', url: 'http://127.0.0.1:8088/' }]);
  await c.goto('admin/');
  check('coach: no Aa, no likes, font unchanged', await c.locator('.fp-btn').count() === 0 && await c.locator('.dl-chk').count() === 0 && await c.locator('link[href*="sriftas.php"]').count() === 0);
  sql("UPDATE accounts SET theme = NULL WHERE email = 'info@karateka.lt'");
  sql("DELETE FROM site_settings WHERE name = 'design_likes'");
  noJsErrors(a, c);
});
