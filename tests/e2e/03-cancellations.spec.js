// Atšauktos treniruotės: adminas atšaukia šiandienos treniruotę, ji pažymima „Šiandien“ ir „Treniruotė“,
// nariai gauna pranešimą ir mato įspėjimą tvarkaraštyje; ištrynus - vėl kaip įprastai.
const { test, expect } = require('@playwright/test');
const { sql, day, login, nav, check, noJsErrors, DEMO_PASSWORD } = require('./helpers');

test('admin cancels a training, members see it, delete restores', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt');
  await a.goto('admin/');
  // Šiandienos treniruotė su nariais (kad būtų kam pranešti)
  const row = a.locator('.today-list li', { has: a.locator('.cancel-btn') }).filter({ hasNotText: ' 0 nar.' }).first();
  test.skip(await row.count() === 0, 'Šiandien pagal tvarkaraštį treniruočių nėra');
  const groupName = (await row.locator('strong').first().textContent()).trim();
  await nav(a, () => row.locator('.cancel-btn').click());
  check('form prefilled with today', await a.inputValue('input[name=starts_on]') === day(0));
  const checked = await a.locator('[data-cg]:checked').count();
  check('only that group checked', checked === 1, checked);
  await a.fill('input[name=reason]', 'Treneris serga');
  await nav(a, () => a.click('button:has-text("Išsaugoti")'));
  check('saved and notified', /Išsaugota: .*pranešta \d+ paskyr/.test(await a.locator('.flash').allTextContents().then(t => t.join(' '))));
  const id = +sql("SELECT id FROM training_cancellations WHERE reason = 'Treneris serga'");
  const gid = +sql(`SELECT group_id FROM training_cancellation_groups WHERE cancellation_id = ${id}`);

  await a.goto('admin/');
  const off = a.locator('.today-list li.is-cancelled', { hasText: groupName });
  check('dashboard shows cancelled', await off.count() === 1);
  check('reason shown', /Treneris serga/.test(await off.textContent()));
  await a.goto(`admin/treniruote.php?g=${gid}`);
  check('attendance page warns', await a.locator('.group-picker .cancel-tag', { hasText: 'Treneris serga' }).count() === 1);
  check('tile marked', await a.locator('.group-tile.on .gt-off').count() >= 1);

  // Narys šios grupės: pranešimas ir įspėjimas tvarkaraštyje
  const member = sql(`SELECT a.email FROM accounts a JOIN account_members am ON am.account_id = a.id JOIN members m ON m.id = am.member_id
                      WHERE m.group_id = ${gid} AND m.status = 'active' AND a.status = 'active' AND a.role = 'member' AND a.email LIKE '%@demo.karateka.lt' LIMIT 1`);
  if (member) {
    const m = await login(browser, member, 1280, 900, DEMO_PASSWORD);
    await m.goto('paskyra.php');
    check('member notified', await m.locator('#svarbu .notif.t-cancelled', { hasText: 'Treniruočių nebus' }).count() >= 1);
    check('schedule note', await m.locator('.cancel-note', { hasText: 'Treneris serga' }).count() === 1);
    noJsErrors(m);
  }

  // Ištrinti - vėl įprastai, pranešimai išsprendžiami
  await a.goto('admin/atsaukimai.php');
  await nav(a, () => a.locator('.cancel-list li', { hasText: 'Treneris serga' }).locator('button[value=delete]').click());
  expect(+sql(`SELECT COUNT(*) FROM training_cancellations WHERE id = ${id}`)).toBe(0);
  check('notifications resolved', +sql(`SELECT COUNT(*) FROM notifications WHERE type = 'cancelled' AND ref_id = ${id} AND done_at IS NULL`) === 0);
  await a.goto('admin/');
  check('dashboard back to normal', await a.locator('.today-list li.is-cancelled', { hasText: groupName }).count() === 0);
  noJsErrors(a);
});

test('validation and coach is read-only', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt');
  await a.goto('admin/atsaukimai.php');
  await a.fill('input[name=starts_on]', day(3));
  await a.fill('input[name=ends_on]', day(1));
  await a.fill('input[name=reason]', 'x');
  await nav(a, () => a.click('button:has-text("Išsaugoti")'));
  check('end before start rejected', /Pabaiga negali būti anksčiau/.test(await a.content()));
  const c = await login(browser, 'treneris@karateka.test');
  await c.goto('admin/atsaukimai.php');
  check('coach sees list, no form', await c.locator('form#naujas').count() === 0 && await c.locator('.cancel-list li').count() >= 1);
  check('examples visible', await c.locator('.cancel-list li', { hasText: 'TEST:' }).count() >= 1);
  noJsErrors(a, c);
});
