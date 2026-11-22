// v1.86 (Denis): testas su 244 klausimų failu (iš ChatGPT) iškart veikia; jei klausimai laukia peržiūros - „Įtraukti visus ir pradėti“.
// #161: treniruotėje paspaudus atsakymą jis tik pažymimas - teisingas parodomas paspaudus „Patvirtinti“.
const { test } = require('@playwright/test');
const path = require('path');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('Denis: a test with 244 questions from a file can be taken right away; review-first shows „add all and start“', async ({ browser }) => {
  sql("DELETE FROM quizzes WHERE title LIKE 'E2E 244%'");
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto('admin/testai.php?new=1');
  await a.fill('input[name=title]', 'E2E 244');
  await a.setInputFiles('input[name="import_files[]"]', path.join(__dirname, '../fixtures/klausimai-244.txt'));
  await nav(a, () => a.click('form:has(input[name=title]) button[type=submit].btn-primary'));
  const qz = +sql("SELECT id FROM quizzes WHERE title = 'E2E 244'");
  check('all 244 questions live', sql(`SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ${qz} AND is_draft = 0`) === '244');
  await a.goto(`testas.php?q=${qz}&rezimas=treniruote`);
  check('practice starts', /1 \/ 244/.test(await a.textContent('.quiz-progress')));

  // #161: pirmas paspaudimas tik pažymi, pakeisti galima, teisingas - po „Patvirtinti“
  const ans = a.locator('#pAnswers .q-answer');
  await ans.nth(0).click();
  check('tap only marks the answer', await a.locator('#pAnswers .correct, #pAnswers .wrong').count() === 0 && await ans.nth(0).evaluate(e => e.classList.contains('chosen')));
  await ans.nth(1).click();
  check('choice can be changed', await ans.nth(1).evaluate(e => e.classList.contains('chosen')) && !(await ans.nth(0).evaluate(e => e.classList.contains('chosen'))));
  await a.click('#pConfirm');
  check('confirm shows the correct answer', await a.locator('#pAnswers .correct').count() === 1 && await a.locator('#pNext').isVisible());

  // Peržiūra pirmiau: klausimai - juodraščiai, „Išbandyti“ siūlo įtraukti visus ir pradėti
  await a.goto('admin/testai.php?new=1');
  await a.fill('input[name=title]', 'E2E 244 peržiūrai');
  await a.setInputFiles('input[name="import_files[]"]', path.join(__dirname, '../fixtures/klausimai-244.txt'));
  await a.check('input[name=review_first]');
  await nav(a, () => a.click('form:has(input[name=title]) button[type=submit].btn-primary'));
  const qz2 = +sql("SELECT id FROM quizzes WHERE title = 'E2E 244 peržiūrai'");
  check('review first: drafts', sql(`SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ${qz2} AND is_draft = 1`) === '244');
  await a.goto(`testas.php?q=${qz2}&rezimas=treniruote`);
  check('waiting questions explained', /laukia peržiūros: 244/.test(await a.textContent('main')));
  await nav(a, () => a.click('button:has-text("Įtraukti visus ir pradėti")'));
  check('approved and practice started', /1 \/ 244/.test(await a.textContent('.quiz-progress')) && sql(`SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ${qz2} AND is_draft = 1`) === '0');
  sql("DELETE FROM quizzes WHERE title LIKE 'E2E 244%'");
  noJsErrors(a);
});
