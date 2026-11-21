// v1.80 D6 1 dalis: LTKF - vienodi tekstai (5 valdybos „[vardas, pavardė]“) keičiami atskirai; puslapio versijos su „Atkurti“; „Atšaukti“ kelis kartus.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

const press = (loc) => loc.evaluate(e => { e.scrollIntoView({ block: 'center' }); if (e.isContentEditable) e.focus(); else e.click(); });

test('D6: same placeholder edited one by one; versions list and restore; undo steps back', async ({ browser }) => {
  sql('DELETE FROM ltkf_pages'); sql('DELETE FROM ltkf_page_versions');
  const a = await login(browser, 'info@karateka.lt', 1300, 900);
  await a.goto('ltkf/apie.php?redaguoti=1');
  const ph = '[vardas, pavardė]';
  check('each board name has its own key', await a.locator(`[data-o="${ph} #1"]`).count() === 1 && await a.locator(`[data-o="${ph} #5"]`).count() === 1);
  await press(a.locator(`[data-o="${ph} #2"]`)); await a.keyboard.press('Control+A'); await a.keyboard.type('Jonas Jonaitis');
  await press(a.locator(`[data-o="${ph} #4"]`)); await a.keyboard.press('Control+A'); await a.keyboard.type('Ona Onaitė');
  await press(a.locator('.ed-bar [data-a=save]')); await a.waitForTimeout(800);
  await a.goto('ltkf/apie.php');
  const body = await a.textContent('main');
  check('two names changed separately', body.includes('Jonas Jonaitis') && body.includes('Ona Onaitė'));
  check('other three stay', (body.match(/\[vardas, pavardė\]/g) || []).length === 3);

  // Dar du išsaugojimai - istorijoje 3 versijos
  for (const h of ['Antra antraštė', 'Trečia antraštė']) {
    await a.goto('ltkf/apie.php?redaguoti=1');
    await press(a.locator('main h1').first()); await a.keyboard.press('Control+A'); await a.keyboard.type(h);
    await press(a.locator('.ed-bar [data-a=save]')); await a.waitForTimeout(800);
  }
  check('history keeps every save', sql("SELECT COUNT(*) FROM ltkf_page_versions WHERE slug = 'apie'") === '3');
  await a.goto('ltkf/apie.php?redaguoti=1');
  await press(a.locator('.ed-bar [data-a=versions]'));
  await a.waitForSelector('.ed-vers li');
  check('versions listed', await a.locator('.ed-vers li').count() === 3);
  await press(a.locator('.ed-vers [data-ver]').nth(1));   // antra nuo viršaus - po pirmo išsaugojimo (be antraštės pakeitimo)
  await a.waitForTimeout(1000);
  await a.goto('ltkf/apie.php');
  const restored = await a.textContent('main');
  check('restored version shown', restored.includes('Jonas Jonaitis') && !restored.includes('Trečia antraštė') && !restored.includes('Antra antraštė'));
  check('current kept in history too', sql("SELECT COUNT(*) FROM ltkf_page_versions WHERE slug = 'apie'") === '4');
  // Atšaukti - vienu išsaugojimu atgal, kelis kartus
  await a.goto('ltkf/apie.php?redaguoti=1'); await press(a.locator('.ed-bar [data-a=undo]')); await a.waitForTimeout(800);
  await a.goto('ltkf/apie.php');
  check('undo 1: back to before restore', (await a.textContent('main h1')).includes('Trečia antraštė'));
  await a.goto('ltkf/apie.php?redaguoti=1'); await press(a.locator('.ed-bar [data-a=undo]')); await a.waitForTimeout(800);
  await a.goto('ltkf/apie.php');
  check('undo 2: one more step back', (await a.textContent('main h1')).includes('Antra antraštė'));
  sql('DELETE FROM ltkf_pages'); sql('DELETE FROM ltkf_page_versions');
  noJsErrors(a);
});

test('D6: LTKF state off / draft / public; draft and off are not indexed', async ({ browser }) => {
  sql("DELETE FROM site_settings WHERE name = 'ltkf_mode'");
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  const g = await login(browser, null, 1280, 900);
  await g.goto('ltkf/index.php');
  check('default on the test site: draft, visible, noindex', await g.locator('meta[name=robots][content*=noindex]').count() === 1 && await g.locator('.karateka-bar').count() === 1);
  const setMode = async (m) => {
    await a.goto('admin/ltkf-perziura.php');
    await a.check(`#busena input[name=mode][value=${m}]`);
    await Promise.all([a.waitForNavigation(), a.click('#busena button[type=submit]')]);
  };
  await setMode('off');
  check('saved', sql("SELECT value FROM site_settings WHERE name = 'ltkf_mode'") === 'off');
  const r = await g.goto('ltkf/index.php');
  check('off: visitors get 404', r.status() === 404);
  await a.goto('ltkf/index.php');
  check('off: admin still sees it with the state shown', (await a.locator('.karateka-bar').innerText()).includes('išjungta'));
  await setMode('public');
  await g.goto('ltkf/index.php');
  check('public: visible, indexable, no draft bar', await g.locator('meta[name=robots]').count() === 0 && await g.locator('.karateka-bar').count() === 0);
  await g.goto('index.php');
  check('public: karateka.lt footer links to LTKF', await g.locator('footer a[href*="ltkf/"]').count() >= 1);
  sql("DELETE FROM site_settings WHERE name = 'ltkf_mode'");
  noJsErrors(a, g);
});

test('D6: news and documents are records - add, edit, delete on the LTKF site', async ({ browser }) => {
  sql("DELETE FROM ltkf_items"); sql('DELETE FROM ltkf_pages');
  const a = await login(browser, 'info@karateka.lt', 1300, 900);
  await a.goto('ltkf/naujienos.php');
  check('news seeded from the draft file', +sql("SELECT COUNT(*) FROM ltkf_items WHERE kind = 'news'") >= 3);
  await a.goto('ltkf/naujienos.php?redaguoti=1');
  await press(a.locator('[data-item-new=news]'));
  await a.fill('.ed-modal .it-title', 'E2E Vasaros stovykla');
  await a.fill('.ed-modal .it-sum', 'Liepos mėnesį - stovykla visiems klubams.');
  await a.fill('.ed-modal .it-body', 'Pirma pastraipa.\n\nAntra pastraipa.');
  await Promise.all([a.waitForNavigation(), press(a.locator('.ed-modal [data-m=ok]'))]);
  await a.goto('ltkf/naujienos.php');
  check('new news item listed', await a.locator('.news-card', { hasText: 'E2E Vasaros stovykla' }).count() === 1);
  await a.click('.news-card:has-text("E2E Vasaros stovykla")');
  check('article page with paragraphs', await a.locator('article p', { hasText: 'Antra pastraipa.' }).count() === 1);
  await a.goto('ltkf/naujienos.php?redaguoti=1');
  await press(a.locator('.ed-items li:has-text("E2E Vasaros stovykla") [data-item-edit]'));
  await a.fill('.ed-modal .it-title', 'E2E Vasaros stovykla 2027');
  await Promise.all([a.waitForNavigation(), press(a.locator('.ed-modal [data-m=ok]'))]);
  check('edited', sql("SELECT COUNT(*) FROM ltkf_items WHERE title = 'E2E Vasaros stovykla 2027'") === '1');
  await press(a.locator('.ed-items li:has-text("E2E Vasaros stovykla 2027") [data-item-del]'));
  await a.waitForTimeout(800);
  check('deleted', sql("SELECT COUNT(*) FROM ltkf_items WHERE title LIKE 'E2E Vasaros%'") === '0');

  await a.goto('ltkf/dokumentai.php?redaguoti=1');
  check('documents seeded', await a.locator('.ed-items[data-kind=doc] li').count() >= 5);
  await press(a.locator('[data-item-new=doc]'));
  await a.fill('.ed-modal .it-title', 'E2E Seminaro programa');
  await a.fill('.ed-modal .it-grp', 'Seminarai');
  const [chooser] = await Promise.all([a.waitForEvent('filechooser'), press(a.locator('.ed-modal .it-up'))]);
  await chooser.setFiles({ name: 'programa.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 e2e') });
  await a.waitForFunction(() => document.querySelector('.ed-modal .it-file').value !== '');
  await Promise.all([a.waitForNavigation(), press(a.locator('.ed-modal [data-m=ok]'))]);
  await a.goto('ltkf/dokumentai.php');
  check('new group and document with a download link', await a.locator('.doc-group h2', { hasText: 'Seminarai' }).count() === 1
    && await a.locator('.doc-list li:has-text("E2E Seminaro programa") a[href*="uploads/ltkf/failai/"]').count() === 1);
  sql("DELETE FROM ltkf_items WHERE title LIKE 'E2E%'");
  noJsErrors(a);
});
