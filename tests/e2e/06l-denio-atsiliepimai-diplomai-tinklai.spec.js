// v1.29: Denio atsiliepimai #5-#11 - nario ištrynimas iš kortelės lango, tatamio šalinimas tik adminui, „+“ tuščiai metų ribai,
// kategorijų šablonas visoms rungtims, „→“ į kategoriją ir „← Grįžti į kategorijas“, diplomai (paprasti ir pagal Excel šabloną), „Tinklai“ (ir žiūrovams).
const { test } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('member quick view: admin can delete the member right there', async ({ browser }) => {
  sql("INSERT INTO members (first_name, last_name, birth_date, status) VALUES ('Trinamas', 'Testas', '2015-01-01', 'active')");
  const mid = +sql("SELECT id FROM members WHERE first_name = 'Trinamas' AND last_name = 'Testas' ORDER BY id DESC LIMIT 1");
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto('admin/nariai.php?q=Trinamas');
  await a.locator(`[data-member-profile="${mid}"]`).first().click();
  await a.waitForSelector('.mp-actions .mp-del button');
  await nav(a, () => a.click('.mp-actions .mp-del button'));   // patvirtinimas priimamas automatiškai
  check('member deleted', +sql(`SELECT COUNT(*) FROM members WHERE id = ${mid}`) === 0);
  check('back on members list', /admin\/nariai\.php/.test(a.url()));
  noJsErrors(a);
});

test('Taika: remove tatami only for admin, + for open year, template for all events, open category and back', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  const state = () => JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
  await a.waitForTimeout(1500);
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  check('admin sees remove tatami', await a.locator('#sched [data-del-area]').count() > 0);

  // Treneris (ne pagrindinis adminas) tatamio šalinti nemato
  const c = await login(browser, 'treneris@karateka.test', 1440, 900);
  await c.goto(`admin/varzybu-sistema.php?id=${tid}#live`);
  await c.waitForTimeout(1200);
  if (await c.locator('#run').isVisible()) await c.click('#run-back');
  check('coach does not see remove tatami', await c.locator('#sched .area').count() > 0 && await c.locator('#sched [data-del-area]').count() === 0);

  // Kategorijų struktūra: tuščia metų riba - „+“, „→“ atidaro kategoriją, „← Grįžti į kategorijas“ - atgal
  await a.click('#tab-kategorijos');
  await a.click('[data-catview="ov"]');
  await a.waitForSelector('#ov-cols .ovb');
  check('empty year shows +', await a.locator('#ov-cols .ov-in[placeholder="+"]').count() > 0);
  const first = a.locator('#ov-cols [data-ov-col="kata"] .ovb').first();
  const cid = await first.getAttribute('data-ovb');
  await first.locator('[data-ov-edit]').click();
  await a.waitForTimeout(1200);   // išsaugoma po akimirkos
  check('category opened', await a.locator('#cat-edit').isVisible() && state().selected === cid);
  await a.click('#cat-back');
  check('back to structure', await a.locator('#cat-overview').isVisible());

  // Šablonas: išsaugoti iš Kata, pritaikyti Kumite ir Kihon - blokai sukuriami iš karto
  sql("UPDATE site_settings SET value = JSON_REMOVE(value, '$.tpl') WHERE name = 'taika_ui' AND JSON_VALID(value)");
  await a.reload();
  await a.click('#tab-kategorijos');
  await a.click('#ov-tpl summary');
  await a.click('#ov-tpl-body [data-tpl-save="kata"]');
  await a.waitForTimeout(800);
  const tpl = JSON.parse(sql("SELECT value FROM site_settings WHERE name = 'taika_ui'")).tpl || [];
  const kataBlocks = state().categories.filter(x => x.discipline === 'kata' && !x.team).length;
  check('template saved by age', tpl.length === kataBlocks && tpl.some(b => b.a0 !== null || b.a1 !== null), JSON.stringify(tpl.slice(0, 2)));
  const before = state().categories.filter(x => x.discipline === 'kihon').length;
  await a.uncheck('[data-tpl-disc="kata"]');
  await a.uncheck('[data-tpl-disc="fukugo"]');
  await a.click('#tpl-apply');
  await a.waitForTimeout(1200);
  const after = state().categories.filter(x => x.discipline === 'kihon');
  check('blocks created for kihon', after.length > before, `${before} → ${after.length}`);
  const kataYears = state().categories.filter(x => x.discipline === 'kata' && !x.team).map(x => JSON.stringify([x.crit.y0 || null, x.crit.y1 || null, x.crit.g === 'M' || x.crit.g === 'F' ? x.crit.g : '']));
  check('same years as kata', after.every(x => kataYears.includes(JSON.stringify([x.crit.y0 || null, x.crit.y1 || null, x.crit.g === 'M' || x.crit.g === 'F' ? x.crit.g : '']))));
  noJsErrors(a);
});

test('Taika: Draws tab and for spectators, diplomas printable and from an Excel template', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.waitForTimeout(1500);

  // Tinklai: amžiaus grupės, paspaudus - visos tos grupės rungtys su poromis
  await a.click('#tab-tinklai');
  await a.waitForSelector('#tk-groups .tk-g');
  const groups = await a.locator('#tk-groups .tk-g').count();
  check('age groups listed', groups > 3, groups);
  await a.locator('#tk-groups .tk-g').nth(2).click();
  check('group categories shown', await a.locator('#tk-view .tk-cat').count() >= 1);
  check('who fights whom', await a.locator('#tk-view .gv-m, #tk-view .gv-score li, #tk-view .gv-people li').count() > 0);
  await a.locator('#tk-view [data-tk-open]').first().click();
  check('opens in live', await a.locator('#run').isVisible());

  // Žiūrovams: transliacijos nuorodoje - „Tinklai“
  const share = sql(`SELECT share_token FROM tournaments WHERE id = ${tid}`);
  const v = await login(browser, null, 390, 844);
  await v.goto(`varzybos-gyvai.php?k=${share}`);
  await v.waitForSelector('.gv-tk [data-cat^="grp:"]');
  await v.locator('.gv-tk [data-cat^="grp:"]').nth(2).click();
  await v.waitForSelector('.gv-sheet .tk-cat');
  check('spectators see the draws', await v.locator('.gv-sheet .gv-m, .gv-sheet .gv-score li, .gv-sheet .gv-people li').count() > 0);

  // Diplomai be šablono - paprasti spausdinami
  sql("DELETE FROM site_settings WHERE name = 'taika_diploma'");
  const tplFile = path.join(__dirname, '..', '..', 'public_html', 'uploads', 'diplomai', 'sablonas.xlsx');
  if (fs.existsSync(tplFile)) fs.unlinkSync(tplFile);
  await a.click('#tab-rezultatai');
  await a.click('#dipl-menu summary');
  const [html] = await Promise.all([a.context().waitForEvent('page'), a.click('#dipl-body a:has-text("Spausdinti paprastus")')]);
  await html.waitForSelector('.dipl');
  const n = await html.locator('.dipl').count();
  check('printable diplomas', n >= 3 && /DIPLOMAS/.test(await html.locator('.dipl').first().innerText()), n);
  await html.close();

  // Šablono įkėlimas (Excel su žymomis) → atsisiunčiamas užpildytas Excel, kiekvienas diplomas - atskiras lapas
  const tpl = path.join(__dirname, '..', 'fixtures', 'diplomo-sablonas.xlsx');
  await a.setInputFiles('#dipl-file', tpl);
  await a.waitForSelector('#dipl-body a:has-text("Excel pagal šabloną")');
  check('template keys found', /\{vardas\}/i.test(await a.textContent('#dipl-body')));
  const res = await a.request.get(`admin/varzybu-sistema.php?id=${tid}&diplomai=1`);
  const buf = await res.body();
  check('xlsx returned', res.headers()['content-type'].includes('spreadsheetml') && buf.slice(0, 2).toString() === 'PK');
  const tmp = path.join(require('os').tmpdir(), 'diplomai-e2e.xlsx');
  fs.writeFileSync(tmp, buf);
  const wbXml = require('child_process').execSync(`unzip -p ${tmp} xl/workbook.xml`).toString();
  check('one sheet per diploma', (wbXml.match(/<sheet\b/g) || []).length === n, (wbXml.match(/<sheet\b/g) || []).length + ' vs ' + n);
  const s2 = require('child_process').execSync(`unzip -p ${tmp} xl/worksheets/diplomas2.xml`).toString();
  check('placeholders filled', !/\{vardas\}/i.test(s2) && /inlineStr/.test(s2));
  await a.click('#dipl-del');
  await a.waitForTimeout(600);
  check('template deleted', !fs.existsSync(tplFile));
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});
