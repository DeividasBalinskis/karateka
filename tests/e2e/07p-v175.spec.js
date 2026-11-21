// v1.75 (C8, sprendimas 2026-11-21): trenerio atsakymas laiško automatiškai nesiunčia - tik pažymėjus „Siųsti ir el. paštu“.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

test('C8: coach reply - e-mail only when ticked; the box starts unticked', async ({ browser }) => {
  const mid = +sql("SELECT am.member_id FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE a.email = 'jaunuolis@karateka.test' AND am.relation = 'self'");
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto(`admin/?pokalbis=${mid}`);
  await a.waitForSelector('.chat-form .chat-mail');
  check('chat: „Siųsti ir el. paštu“ box, unticked', await a.locator('.chat-form .chat-mail input').count() === 1 && !(await a.locator('.chat-form .chat-mail input').isChecked()));
  const before = +sql(`SELECT COUNT(*) FROM member_messages WHERE member_id = ${mid} AND from_staff = 1`);
  await a.fill('.chat-form textarea', 'E2E atsakymas be laiško');
  await a.click('.chat-form .chat-send');
  await a.waitForTimeout(800);
  check('reply saved', +sql(`SELECT COUNT(*) FROM member_messages WHERE member_id = ${mid} AND from_staff = 1`) === before + 1);
  await a.goto(`admin/zinutes.php?m=${mid}`);
  const boxes = a.locator('form:has(input[name=action][value=reply]) input[name=email][type=checkbox]');
  if (await boxes.count()) check('messages page: „ir laiškas“ unticked', !(await boxes.first().isChecked()));
  sql("DELETE FROM member_messages WHERE body = 'E2E atsakymas be laiško'");
  noJsErrors(a);
});
