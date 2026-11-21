// v1.85 (#158 likutis): vardas pakeistas paskyroje dar iki v1.73 - nario kortelė ir Dojo personažas liko su senu vardu.
// Dabar atidarius paskyrą ar Dojo jie gauna paskyros vardą.
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

test('#158: an old name mismatch heals - member card and Dojo take the account name', async ({ browser }) => {
  const mid = sql("SELECT am.member_id FROM account_members am JOIN accounts a ON a.id = am.account_id WHERE a.email = 'narys@karateka.test' AND am.relation = 'self'");
  const old = sql(`SELECT CONCAT(first_name, '|', last_name) FROM members WHERE id = ${mid}`).split('|');
  sql(`DELETE FROM game_heroes WHERE member_id = ${mid}`);
  const m = await login(browser, 'narys@karateka.test', 1280, 900);
  await m.goto('dojo.php');   // personažas sukuriamas su nario vardu
  check('hero has the member name', sql(`SELECT name FROM game_heroes WHERE member_id = ${mid}`) === old[0]);
  // Kaip prieš v1.73: paskyroje vardas pakeistas, narys ir personažas - ne
  sql("UPDATE accounts SET first_name = 'Deividas' WHERE email = 'narys@karateka.test'");
  await m.goto('dojo.php');
  await m.waitForSelector('.df-name b');
  check('Dojo shows the account name', (await m.textContent('.df-name b')).trim().startsWith('Deividas'), await m.textContent('.df-name b'));
  check('member card renamed', sql(`SELECT first_name FROM members WHERE id = ${mid}`) === 'Deividas');
  check('hero renamed', sql(`SELECT name FROM game_heroes WHERE member_id = ${mid}`) === 'Deividas');
  // Atgal
  sql(`UPDATE accounts SET first_name = '${old[0]}' WHERE email = 'narys@karateka.test'`);
  await m.goto('paskyra.php');
  check('back to the old name', sql(`SELECT first_name FROM members WHERE id = ${mid}`) === old[0]);
  noJsErrors(m);
});
