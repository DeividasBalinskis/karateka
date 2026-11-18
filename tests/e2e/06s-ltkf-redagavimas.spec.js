// v1.41: LTKF svetainė redaguojama - tekstai tiesiai puslapyje, skilčių slėpimas, blokai (tekstas, mygtukas), apačios tekstai,
// meniu ir nauji puslapiai, išvaizda ir kontaktai, failų dėžė; redaktoriaus nuoroda (be paskyros, tik vardas) ir jos išjungimas.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors, BASE_URL } = require('./helpers');

// Mygtukai po prilipusia antrašte - spaudžiama per DOM
const press = (loc) => loc.evaluate(e => { e.scrollIntoView({ block: 'center' }); if (e.isContentEditable) e.focus(); else e.click(); });

test('LTKF: edit any text, hide a section, add blocks, footer text, new page in the menu', async ({ browser }) => {
  sql('DELETE FROM ltkf_pages'); sql("DELETE FROM site_settings WHERE name IN ('ltkf_menu', 'ltkf_nust', 'ltkf_editors')");
  const a = await login(browser, 'info@karateka.lt', 1300, 900);
  await a.goto('ltkf/index.php');
  check('edit button for admin', await a.locator('.ed-fab').count() === 1);
  check('Karate, not Karatė', !/aratė/.test(await a.textContent('body')));
  await a.goto('ltkf/index.php?redaguoti=1');
  check('texts editable, zones and section toggles', await a.locator('.ed-txt').count() > 30 && await a.locator('.lb-zone').count() >= 3 && await a.locator('.ed-sec-tools [data-st=hide]').count() >= 3);
  await press(a.locator('main h1').first()); await a.keyboard.press('Control+A'); await a.keyboard.type('Tradicinis karate Lietuvoje');
  await press(a.locator('.lb-zone[data-zone="po-0"] .ed-add-btn')); await press(a.locator('.lb-zone[data-zone="po-0"] [data-type=text]'));
  await press(a.locator('.lb-zone[data-zone="po-0"] .ed-rich')); await a.keyboard.press('Control+A'); await a.keyboard.type('Sveiki atvykę!');
  await press(a.locator('.lb-zone[data-zone="po-0"] .ed-add-btn')); await press(a.locator('.lb-zone[data-zone="po-0"] [data-type=btn]'));
  await a.locator('.lb-zone[data-zone="po-0"] [data-f=label]').fill('Registracija'); await a.locator('.lb-zone[data-zone="po-0"] [data-f=href]').fill('kalendorius.php');
  const before = await a.locator('main > section').count();
  await press(a.locator('main > section[data-sec] > .ed-sec-tools [data-st=hide]').nth(1));
  await press(a.locator('footer .footer-brand p')); await a.keyboard.press('Control+A'); await a.keyboard.type('Vienija klubus visoje Lietuvoje.');
  await press(a.locator('.ed-bar [data-a=save]')); await a.waitForTimeout(800);
  check('saved', /Išsaugota/.test(await a.textContent('#edStatus')));
  await a.goto('ltkf/index.php');
  check('visitors see the new heading', (await a.textContent('main h1')).trim() === 'Tradicinis karate Lietuvoje');
  check('text and button blocks', /Sveiki atvykę!/.test(await a.textContent('main .lb-text')) && (await a.getAttribute('main .lb-btn a', 'href')) === 'kalendorius.php');
  check('hidden section not shown', await a.locator('main > section').count() === before - 1);
  check('footer text on every page', /Vienija klubus visoje Lietuvoje/.test(await a.textContent('footer')) && (await a.goto('ltkf/apie.php'), /Vienija klubus visoje Lietuvoje/.test(await a.textContent('footer'))));
  // Atšaukti
  await a.goto('ltkf/index.php?redaguoti=1'); await press(a.locator('.ed-bar [data-a=undo]')); await a.waitForTimeout(800);
  await a.goto('ltkf/index.php');
  check('undo brings back the previous version', (await a.textContent('main h1')).includes('vienoje vietoje'));
  // Naujas puslapis meniu
  await a.goto('ltkf/apie.php?redaguoti=1');
  await press(a.locator('.ed-bar [data-a=newpage]')); await a.fill('.np-t', 'Seminarai'); await press(a.locator('.ed-modal [data-m=ok]'));
  await a.waitForURL(/puslapis\.php\?p=seminarai/);
  await press(a.locator('.lb-zone .ed-add-btn').first()); await press(a.locator('.lb-zone [data-type=h]').first());
  await press(a.locator('.ed-bar [data-a=save]')); await a.waitForTimeout(800);
  await a.goto('ltkf/index.php');
  check('new page in the menu', await a.locator('#main-nav a', { hasText: 'Seminarai' }).count() === 1);
  await a.click('#main-nav a:has-text("Seminarai")');
  check('new page shows its blocks', /Nauja antraštė/.test(await a.textContent('main')));
  noJsErrors(a);
});

test('LTKF editor link: name only, look and contacts, file box; switching the link off ends editing', async ({ browser }) => {
  sql("DELETE FROM site_settings WHERE name IN ('ltkf_nust', 'ltkf_editors')"); sql('DELETE FROM ltkf_files');
  const a = await login(browser, 'info@karateka.lt', 1300, 900);
  await a.goto('admin/ltkf-perziura.php');
  await a.fill('#redaktoriai input[name=name]', 'Jonas Sanrei');
  await Promise.all([a.waitForNavigation(), a.click('#redaktoriai button[value=ed_add]')]);
  const link = (await a.inputValue('#redaktoriai .ltkf-ed input[type=text]')).replace(/^https?:\/\/[^/]+\//, BASE_URL);
  check('editor link created', /ltkf-redaktorius\.php\?k=[0-9a-f]{24}$/.test(link), link);
  const ctx = await browser.newContext({ baseURL: BASE_URL, viewport: { width: 1300, height: 900 } });
  const g = await ctx.newPage();
  await g.goto(link); await g.fill('input[name=name]', 'Jonas');
  await Promise.all([g.waitForNavigation(), g.click('button[type=submit]')]);
  check('editor lands in edit mode without an account', /ltkf\/index\.php\?redaguoti=1/.test(g.url()) && await g.locator('.ed-bar').count() === 1);
  await press(g.locator('.ed-bar [data-a=look]')); await g.fill('.l-tel', '+370 600 00000'); await press(g.locator('.ed-modal [data-m=ok]')); await g.waitForTimeout(1200);
  check('contacts changed everywhere', /\+370 600 00000/.test(await g.textContent('footer')));
  await g.goto('ltkf/failai.php');
  await g.setInputFiles('#inboxFiles', { name: 'nuostatai.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 test') });
  await g.fill('textarea[name=note]', 'Į dokumentus'); await g.click('#inboxForm button[type=submit]'); await g.waitForTimeout(1500);
  check('file in the box with the note and name', +sql("SELECT COUNT(*) FROM ltkf_files WHERE orig = 'nuostatai.pdf' AND note = 'Į dokumentus' AND uploaded_by = 'Jonas'") === 1);
  await a.goto('ltkf/failai.php');
  check('admin sees the file box', await a.locator('.inbox-list li').count() === 1);
  await a.goto('admin/ltkf-perziura.php');
  await Promise.all([a.waitForNavigation(), a.click('#redaktoriai button[value=ed_toggle]')]);
  await g.goto('ltkf/index.php?redaguoti=1');
  check('switched off - no editing', await g.locator('.ed-bar').count() === 0);
  await ctx.close();
  sql("DELETE FROM site_settings WHERE name IN ('ltkf_nust', 'ltkf_editors', 'ltkf_menu')"); sql('DELETE FROM ltkf_pages'); sql('DELETE FROM ltkf_files');
  noJsErrors(a);
});

test('LTKF: menu and title edited in place, section background, delete and restore a section, new section with background', async ({ browser }) => {
  sql('DELETE FROM ltkf_pages'); sql("DELETE FROM site_settings WHERE name IN ('ltkf_menu', 'ltkf_nust')");
  const a = await login(browser, 'info@karateka.lt', 1300, 900);
  await a.goto('ltkf/index.php?redaguoti=1');
  check('bigger editor bar with a clear file box', await a.locator('.ed-bar .ed-inbox small', { hasText: 'įmesk failus ir komentarą' }).count() === 1);
  // Meniu punktas - pervadinamas tiesiai antraštėje, kitas išimamas
  const nav = a.locator('.main-nav > a').nth(1);
  await nav.hover(); await nav.locator('[data-nav=ren]').click();
  await a.keyboard.press('Control+A'); await a.keyboard.type('Klubai ir mokyklos'); await a.keyboard.press('Enter');
  const last = a.locator('.main-nav > a').nth(7); await last.hover(); await last.locator('[data-nav=del]').click();
  // Svetainės pavadinimas antraštėje
  await press(a.locator('.brand-text b')); await a.keyboard.press('Control+A'); await a.keyboard.type('LTKF.lt');
  // Skilties fonas ir šviesus tekstas; kita skiltis - ištrinama
  const s1 = a.locator('main > section[data-sec]').nth(1);
  await press(s1.locator('[data-st=bg]'));
  await s1.locator('input[data-bg=c]').evaluate(e => { e.value = '#1f3a5f'; e.dispatchEvent(new Event('input', { bubbles: true })); });
  await s1.locator('input[data-bg=light]').evaluate(e => { e.checked = true; e.dispatchEvent(new Event('change', { bubbles: true })); });
  await press(a.locator('main > section[data-sec]').nth(2).locator('[data-st=del]'));
  check('deleted section folds in the editor', await a.locator('main > section.lb-deleted').count() === 1);
  // Nauja skiltis su fonu
  await press(a.locator('.lb-zone[data-zone="po-0"] .ed-add-btn')); await press(a.locator('.lb-zone[data-zone="po-0"] [data-type=sec]'));
  await press(a.locator('.lb-zone[data-zone="po-0"] .ed-block [data-f=title]')); await a.keyboard.press('Control+A'); await a.keyboard.type('Rinktinė');
  const sections = await a.locator('main > section[data-sec]').count();
  await press(a.locator('.ed-bar [data-a=save]')); await a.waitForTimeout(1500);
  check('saved', /Išsaugota/.test(await a.textContent('#edStatus')));
  await a.goto('ltkf/index.php');
  const navs = await a.locator('.main-nav > a').allTextContents();
  check('menu item renamed and removed in place', navs.includes('Klubai ir mokyklos') && navs.length === 7, navs.join('|'));
  check('site title changed', (await a.textContent('.brand-text b')).trim() === 'LTKF.lt');
  check('section background with light text', await a.locator('main > section.lb-bg.lb-light').count() === 1);
  check('deleted section hidden for visitors', await a.locator('main > section:not(.lb-sec)').count() === sections - 1);
  check('new section with background', (await a.textContent('main .lb-sec h2')).trim() === 'Rinktinė');
  // Grąžinti ištrintą skiltį
  await a.goto('ltkf/index.php?redaguoti=1');
  await press(a.locator('main > section.lb-deleted [data-st=del]'));
  await press(a.locator('.ed-bar [data-a=save]')); await a.waitForTimeout(1200);
  await a.goto('ltkf/index.php');
  check('restored section visible again', await a.locator('main > section:not(.lb-sec)').count() === sections);
  sql('DELETE FROM ltkf_pages'); sql("DELETE FROM site_settings WHERE name IN ('ltkf_menu', 'ltkf_nust')");
  noJsErrors(a);
});

