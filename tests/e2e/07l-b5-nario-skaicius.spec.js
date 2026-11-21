// B5 (sprendimas 2026-11-21): nariui ir tėvams raudonas skaičius - tik darbai (užduotys, neperskaitytos pastabos ir atsakymai,
// kvietimai, laukiantys atsakymo). Naujienos rodomos sąraše, bet neskaičiuojamos.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

const badge = async p => +((await p.locator('.nav-login .count-badge').count()) ? await p.locator('.nav-login .count-badge').textContent() : 0);

test('B5 member: news is listed but not counted; an invitation waiting for an answer is counted until answered', async ({ browser }) => {
  const aid = +sql("SELECT id FROM accounts WHERE email = 'narys@karateka.test'");
  const mid = +sql(`SELECT member_id FROM account_members WHERE account_id = ${aid} LIMIT 1`);
  sql(`DELETE FROM notifications WHERE account_id = ${aid} AND title LIKE 'E2E%'`);
  sql("DELETE FROM events WHERE title = 'E2E kvietimas nariui'");
  const p = await login(browser, 'narys@karateka.test', 1440, 900);
  await p.goto('paskyra.php');
  const b0 = await badge(p);

  sql(`INSERT INTO notifications (account_id, member_id, type, title, important) VALUES (${aid}, ${mid}, 'broadcast', 'E2E žinia visiems', 1)`);
  await p.reload();
  check('news is listed', await p.locator('.notif', { hasText: 'E2E žinia visiems' }).count() >= 1);
  check('news is not counted', await badge(p) === b0, b0 + ' -> ' + await badge(p));

  const day = new Date(Date.now() + 6 * 864e5).toISOString().slice(0, 10);
  sql(`INSERT INTO events (type, title, starts_on, registration, invited_only) VALUES ('other', 'E2E kvietimas nariui', '${day}', 1, 1)`);
  const ev = +sql("SELECT id FROM events WHERE title = 'E2E kvietimas nariui'");
  sql(`INSERT INTO event_participants (event_id, member_id, status) VALUES (${ev}, ${mid}, 'invited')`);
  await p.reload();
  check('invitation waiting for an answer is counted', await badge(p) === b0 + 1, b0 + ' -> ' + await badge(p));
  check('the answer buttons are on the page', await p.locator(`form:has(input[name=event_id][value="${ev}"]) button[value=declined]`).count() >= 1);
  await Promise.all([p.waitForNavigation(), p.locator(`form:has(input[name=event_id][value="${ev}"]) button[value=declined]`).first().click()]);
  check('answered: no longer counted', await badge(p) === b0, b0 + ' -> ' + await badge(p));

  sql(`DELETE FROM events WHERE id = ${ev}`);
  sql(`DELETE FROM event_participants WHERE event_id = ${ev}`);
  sql(`DELETE FROM notifications WHERE account_id = ${aid} AND title LIKE 'E2E%'`);
  noJsErrors(p);
});
