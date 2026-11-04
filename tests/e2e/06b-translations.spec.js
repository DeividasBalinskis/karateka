// Kalbos: sąsaja LT / EN / RU; Denio turinio vertimai įklijuojami ranka (Nustatymai → Vertimai) ir rodomi pasirinkta kalba.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, flashes } = require('./helpers');

test('language switch, manual content translations, Denis bio', async ({ browser }) => {
  const newsId = sql("SELECT id FROM news WHERE is_published = 1 ORDER BY published_at DESC LIMIT 1");
  const title = sql(`SELECT title FROM news WHERE id = ${newsId}`);

  // Adminas įklijuoja vertimą
  const a = await login(browser, 'info@karateka.lt');
  await a.goto('admin/nustatymai.php');
  await nav(a, () => a.click('a.settings-link[href*="vertimai.php"]'));
  check('translations overview', await a.locator('.tr-lists a', { hasText: title }).count() >= 1);
  await nav(a, () => a.locator('.tr-lists a', { hasText: title }).first().click());
  check('source shown', (await a.locator('.tr-item').first().locator('.tr-src').textContent()).trim() === title);
  await a.locator('.tr-item').first().locator('[name="en[0]"]').fill('Translated news title');
  await a.locator('.tr-item').first().locator('[name="ru[0]"]').fill('Переведённый заголовок');
  await nav(a, () => a.click('.tr-save button'));
  check('saved', /Vertimai išsaugoti \(2\)/.test(await flashes(a)), await flashes(a));
  check('stored by text', +sql("SELECT COUNT(*) FROM content_translations WHERE body = 'Translated news title' AND lang = 'en'") === 1);
  await a.goto(`admin/naujienos.php?edit=${newsId}`);
  check('link from editor', await a.locator('a.tr-link[href*="e=news"]').count() === 1);

  // Lankytojas: anglų kalba
  const en = await login(browser, null, 1280, 900, undefined, 'en');
  await en.goto(`naujienos.php?id=${newsId}`);
  check('news title in English', (await en.locator('h1').first().textContent()).trim() === 'Translated news title');
  check('interface in English', /Events/.test(await en.locator('.nav-pages').textContent()));
  await en.goto('index.php');
  check('html lang en', await en.getAttribute('html', 'lang') === 'en');
  await en.locator('.instructor-card[data-instructor]', { hasText: 'Denis' }).first().click();
  check('Denis bio in English', /trained karate since the age of 5/.test(await en.locator('.ins-pop-bio').first().textContent()));

  // Rusų kalba ir atgal į lietuvių per meniu
  const ru = await login(browser, null, 1280, 900, undefined, 'ru');
  await ru.goto(`naujienos.php?id=${newsId}`);
  check('news title in Russian', (await ru.locator('h1').first().textContent()).trim() === 'Переведённый заголовок');
  await ru.click('.lang-switch summary');
  await nav(ru, () => ru.click('.lang-menu a[lang=lt]'));
  check('switched back to Lithuanian', (await ru.locator('h1').first().textContent()).trim() === title && ru.url().includes('naujienos.php'));

  // Pakeitus lietuvišką tekstą, senas vertimas nebetinka
  sql(`UPDATE news SET title = CONCAT(title, ' (pataisyta)') WHERE id = ${newsId}`);
  await en.goto(`naujienos.php?id=${newsId}`);
  check('changed text falls back to Lithuanian', /\(pataisyta\)/.test(await en.locator('h1').first().textContent()));
  sql(`UPDATE news SET title = REPLACE(title, ' (pataisyta)', '') WHERE id = ${newsId}`);
  noJsErrors(a, en, ru);
});
