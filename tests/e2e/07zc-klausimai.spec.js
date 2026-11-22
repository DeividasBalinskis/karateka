// v1.90: klausimas atsiliepimo autoriui („Reikia atsakymo“) - paryškintas lange, ❓ ant mygtuko, viršuje „Reikia padaryti“, atsakoma čia pat
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

test('feedback question: highlighted, counted on the button and on Today, answered in the box', async ({ browser }) => {
  sql("REPLACE INTO site_settings (name, value) VALUES ('feedback_button', 'staff')");
  sql("DELETE FROM feedback WHERE body LIKE 'E2E klausimas%'");
  const acc = sql("SELECT id FROM accounts WHERE email = 'info@karateka.lt'");
  sql(`INSERT INTO feedback (account_id, page, site, body, status, reply) VALUES (${acc}, '/admin/egzaminai.php', 'karateka', 'E2E klausimas: padarykite sąrašą', 'question', 'Ar sąrašas bendras visiems egzaminams?')`);
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/');
  check('top of „Reikia padaryti“', /Atsakykite į klausimus apie jūsų atsiliepimus: 1/.test(await a.locator('body').innerText()));
  check('❓1 on the 💡 button', (await a.locator('#fbFab .fb-fab-n').innerText()).includes('❓1'));
  await a.goto('admin/#atsiliepimas');
  await a.waitForSelector('#fbPanel:not([hidden]) .fb-qitem');
  check('question first and highlighted', /Ar sąrašas bendras/.test(await a.locator('#fbList li').first().innerText()) && await a.locator('#fbList .fb-qitem').count() === 1);
  await a.click('#fbList .fb-qsend');
  check('empty answer not sent', sql("SELECT status FROM feedback WHERE body LIKE 'E2E klausimas%'") === 'question');
  await a.fill('#fbList .fb-qans', 'Taip, vienas sąrašas');
  await a.click('#fbList .fb-qsend');
  await a.waitForFunction(() => /Atsakymas išsiųstas/.test(document.getElementById('fbState').textContent));
  check('answer saved, back to the queue', sql("SELECT CONCAT(status, '|', answer) FROM feedback WHERE body LIKE 'E2E klausimas%'") === 'new|Taip, vienas sąrašas');
  check('question card gone, answer shown, badge gone', await a.locator('#fbList .fb-qitem').count() === 0 && /Taip, vienas sąrašas/.test(await a.locator('#fbList').innerText()) && await a.locator('#fbFab .fb-fab-n').isHidden());
  await a.goto('admin/atsiliepimai.php?b=visi&q=E2E+klausimas');
  check('admin sees the answer', /Taip, vienas sąrašas/.test(await a.locator('.fb-card-answer').innerText()));
  sql("DELETE FROM feedback WHERE body LIKE 'E2E klausimas%'");
  noJsErrors(a);
});
