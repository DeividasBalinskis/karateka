// B8 (sprendimas 2026-11-21, variantas B): 7-10 m. vaikas savo paskyroje - lygis ir XP viršuje, 2 kortelės, „Kas toliau“;
// telpa į pirmą telefono ekraną, be „Atsijungti“ ir kainų; tėvai mato įprastą apžvalgą.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors, closeCelebrate } = require('./helpers');

test('B8: kid home - level on top, next training, one task with „✓ Atlikau“, „Kas toliau“; fits 375x812; parent view unchanged', async ({ browser }) => {
  sql("DELETE FROM accounts WHERE email LIKE '%@vaikas.invalid'");
  sql("DELETE FROM login_attempts WHERE email LIKE '%@vaikas.invalid'");
  const mid = sql("SELECT m.id FROM members m JOIN account_members am ON am.member_id = m.id JOIN accounts a ON a.id = am.account_id WHERE a.email = 'tevai@karateka.test' AND am.relation = 'parent' AND m.group_id IS NOT NULL AND TIMESTAMPDIFF(YEAR, m.birth_date, CURDATE()) BETWEEN 7 AND 10 LIMIT 1");
  sql(`DELETE FROM coach_notes WHERE member_id = ${mid} AND body LIKE 'E2E%'`);
  sql(`INSERT INTO coach_notes (member_id, note_date, body, is_task) VALUES (${mid}, CURDATE(), 'E2E Heian Shodan 3 kartus', 1)`);
  const note = sql(`SELECT id FROM coach_notes WHERE member_id = ${mid} AND body LIKE 'E2E%'`);
  const p = await login(browser, 'tevai@karateka.test', 1440, 900);
  await p.goto(`paskyra.php?skiltis=nustatymai&m=${mid}`);
  await closeCelebrate(p);
  await p.click('#vaiko-prisijungimas > summary');
  await p.fill('#vaiko-prisijungimas input[name=username]', 'e2e.diena');
  await p.fill('#vaiko-prisijungimas input[name=pin]', '4821');
  await Promise.all([p.waitForNavigation(), p.click('#vaiko-prisijungimas button[type=submit]')]);
  await p.goto(`paskyra.php?m=${mid}`);
  check('parent: no kid home', await p.locator('.kid-day').count() === 0 && await p.locator('.account-top .logout').count() === 1);

  const k = await login(browser, 'e2e.diena', 375, 812, '4821');
  await closeCelebrate(k);
  await k.goto('paskyra.php');
  await closeCelebrate(k);
  check('kid home shown', await k.locator('.kid-day .kid-ring').count() === 1 && /Labas,/.test(await k.locator('.kid-hi h1').textContent()));
  check('XP line with what is left to the next level', /XP · dar \d+ XP iki \d+ lygio/.test(await k.locator('.kid-hi p').textContent()));
  check('next training card', await k.locator('.kid-mini').first().locator('b').count() === 1);
  check('the task is in the card', /E2E Heian Shodan/.test(await k.locator('.kid-two').textContent()));
  check('the task is not repeated in „Svarbu“', await k.locator('#svarbu', { hasText: 'E2E Heian Shodan' }).count() === 0);
  check('no „Atsijungti“ on the first screen', await k.locator('.account-top .logout').count() === 0);
  check('no prices', !/€|💶/.test(await k.locator('main').textContent()));
  const box = await k.evaluate(() => ({ bottom: document.querySelector('.kid-day').getBoundingClientRect().bottom + scrollY, sw: document.documentElement.scrollWidth, w: innerWidth }));
  check('fits the first phone screen, no sideways scroll', box.bottom <= 812 && box.sw <= box.w, JSON.stringify(box));
  await Promise.all([k.waitForNavigation(), k.click('.kid-two button:has-text("Atlikau")')]);
  check('„✓ Atlikau“ marks the task done', sql(`SELECT done_at IS NOT NULL FROM coach_notes WHERE id = ${note}`) === '1');
  check('back on top, task gone from the card', !/E2E Heian Shodan/.test(await k.locator('.kid-two').textContent()));

  await k.setViewportSize({ width: 1440, height: 900 });
  await k.reload();
  const sw = await k.evaluate(() => document.documentElement.scrollWidth <= innerWidth);
  check('1440: no sideways scroll', sw);
  sql(`DELETE FROM coach_notes WHERE id = ${note}`);
  sql("DELETE FROM accounts WHERE email LIKE '%@vaikas.invalid'");
  noJsErrors(p, k);
});
