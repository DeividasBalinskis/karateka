// B5: pranešimai - raudonas skaičius tik darbams (kol atlikta), darbai ir naujienos kraunami atskirai, naujienos suskleidžiamos.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

test('B5: 35 feedback replies + 1 request - the request stays; header number = listed; popup „Supratau“ does not hide it; notices fold', async ({ browser }) => {
  const aid = +sql("SELECT id FROM accounts WHERE email = 'info@karateka.lt'");
  sql(`DELETE FROM notifications WHERE account_id = ${aid}`);
  const vals = [];
  for (let i = 1; i <= 35; i++) vals.push(`(${aid}, 'feedback', 'E2E atsakymas ${i}', 1, ${i}, NOW() - INTERVAL ${36 - i} MINUTE)`);
  sql(`INSERT INTO notifications (account_id, type, title, important, ref_id, created_at) VALUES ${vals.join(',')}`);
  sql(`INSERT INTO notifications (account_id, type, title, important, created_at) VALUES
       (${aid}, 'skill_request', 'E2E prašymas: Moku', 1, NOW() - INTERVAL 2 HOUR),
       (${aid}, 'exam_registration', 'E2E registracija 1', 1, NOW() - INTERVAL 5 MINUTE),
       (${aid}, 'exam_registration', 'E2E registracija 2', 1, NOW() - INTERVAL 4 MINUTE),
       (${aid}, 'exam_registration', 'E2E registracija 3', 1, NOW() - INTERVAL 3 MINUTE)`);
  const req = sql(`SELECT id FROM notifications WHERE account_id = ${aid} AND type = 'skill_request'`);

  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/');
  check('the request is listed under „Reikia padaryti“', await a.locator('#svarbu .notif.t-skill_request', { hasText: 'E2E prašymas' }).count() === 1);
  const nums = await a.evaluate(() => ({ head: +((document.querySelector('.nav-login .count-badge') || {}).textContent || 0),
    kicker: +((document.querySelector('#svarbu .kicker .count-badge') || {}).textContent || 0),
    listed: document.querySelectorAll('#svarbu > .notif-list .notif').length }));
  check('header number = number in the list = items listed', nums.head === nums.kicker && nums.kicker === nums.listed && nums.head >= 1, JSON.stringify(nums));
  check('notices: 2 shown, the rest folded', await a.locator('.news-replies > .notif-list .notif').count() === 2 && /Rodyti ankstesnius \(1\)/.test(await a.locator('.news-replies details summary').textContent()));
  check('feedback replies folded too', await a.locator('.fb-replies:not(.news-replies) > .notif-list .notif').count() === 2);

  // „Supratau“ iššokusiame langelyje (perskaityta) - darbas lieka skaičiuje
  sql(`UPDATE notifications SET read_at = NOW() WHERE id = ${req}`);
  await a.reload();
  check('read but not done: still counted', await a.locator('#svarbu .notif.t-skill_request').count() === 1 && +(await a.locator('.nav-login .count-badge').textContent()) === nums.head);

  await Promise.all([a.waitForNavigation(), a.click('.news-replies button:has-text("Supratau visus")')]);
  check('„Supratau visus“ hides notices, keeps the request', await a.locator('.news-replies').count() === 0 && await a.locator('#svarbu .notif.t-skill_request').count() === 1
    && +sql(`SELECT COUNT(*) FROM notifications WHERE account_id = ${aid} AND type = 'feedback' AND done_at IS NULL`) === 35);
  sql(`DELETE FROM notifications WHERE account_id = ${aid} AND title LIKE 'E2E%'`);
  noJsErrors(a);
});
