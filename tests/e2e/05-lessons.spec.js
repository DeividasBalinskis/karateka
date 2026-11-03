// Pamokos: aplankai pagal diržą - kyu ir dan (diržo spalva), aplanke - tekstas, nuotrauka, video ir poaplankiai; narys mato savo diržų aplankus.
const { test, expect } = require('@playwright/test');
const path = require('path');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('kyu folders, photo without title, subfolder, member view', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt');
  await a.goto('pamokos.php');
  const titles = await a.locator('.kyu-tile strong').allTextContents();
  expect(titles.slice(0, 18)).toEqual(['9 kyu', '8 kyu', '7 kyu', '6 kyu', '5 kyu', '4 kyu', '3 kyu', '2 kyu', '1 kyu',
    '1 dan', '2 dan', '3 dan', '4 dan', '5 dan', '6 dan', '7 dan', '8 dan', '9 dan']);
  check('belt colors', await a.locator('.kyu-tile.has-belt').count() === 18);

  // 8 kyu aplanke: nuotrauka be pavadinimo
  await nav(a, () => a.locator('.kyu-tile', { hasText: '8 kyu' }).click());
  const folderUrl = a.url();
  await nav(a, () => a.locator('.ls-add-bar a', { hasText: 'Nuotrauka' }).click());
  check('editor starts with a photo block', await a.locator('#blocks .lblock.lb-image').count() === 1);
  await a.setInputFiles('#blocks input[type=file]', path.join(__dirname, '../../public_html/img/apie-1.jpg'));
  await nav(a, () => a.click('.lesson-save button:has-text("Išsaugoti")'));
  check('saved', /Pamoka išsaugota/.test(await a.locator('.flash').allTextContents().then(t => t.join(' '))));
  const id = +sql("SELECT id FROM lessons ORDER BY id DESC LIMIT 1");
  check('auto title', /^Nuotrauka · /.test(sql(`SELECT title FROM lessons WHERE id = ${id}`)));
  check('in 8 kyu folder', sql(`SELECT f.title FROM lessons l JOIN lesson_folders f ON f.id = l.folder_id WHERE l.id = ${id}`) === '8 kyu');

  // Poaplankis
  await a.goto(folderUrl);
  await a.click('.ls-add-bar button:has-text("Aplankas")');
  await a.fill('.ls-new-folder input[name=title]', 'Kumite');
  await nav(a, () => a.click('.ls-new-folder button[type=submit]'));
  check('subfolder created', +sql("SELECT COUNT(*) FROM lesson_folders f JOIN lesson_folders p ON p.id = f.parent_id WHERE f.title = 'Kumite' AND p.title = '8 kyu'") === 1);
  await a.goto(folderUrl);
  check('subfolder tile shown', await a.locator('.kyu-tile', { hasText: 'Kumite' }).count() === 1);
  check('photo card shown', await a.locator('.lesson-card', { hasText: 'Nuotrauka' }).count() >= 1);

  // Narys (tėvai: vaikai 9 kyu) mato savo, žemesnių ir kito diržo aplankus su turiniu
  const p = await login(browser, 'tevai@karateka.test');
  await p.goto('pamokos.php');
  const own = await p.locator('.kyu-tile strong').allTextContents();
  check('member sees 9 and 8 kyu only', own.join(',') === '9 kyu,8 kyu', own.join(','));
  check('own belt marked', await p.locator('.kyu-tile.is-own', { hasText: '9 kyu' }).count() === 1);
  const r = await p.goto('pamokos.php?aplankas=' + sql("SELECT id FROM lesson_folders WHERE title = '3 kyu' AND parent_id IS NULL"));
  check('higher belt folder closed', r.status() === 404, r.status());
  noJsErrors(a, p);
});
