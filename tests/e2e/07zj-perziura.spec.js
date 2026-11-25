// v2.02: 🔎 Peržiūra - atsiliepimai pažymimi ir „▶ Paleisti taisymą“ (darbų eilė agentui), auksinės žymos test svetainėje
// (□ → ✓ → ✗ + komentaras, „Pateikti“), „🎯 Pastaba apie elementą“, agento API (perziura-api.php). Tik tikriems administratoriams.
const { test } = require('@playwright/test');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

// Testas naudoja savo pakeitimų sąrašą (app/review.json keičiasi su kiekviena versija); pabaigoje grąžinamas tikrasis
const MANIFEST = path.join(__dirname, '..', '..', 'public_html', 'app', 'review.json');
const TEST_MANIFEST = { rev: 'e2e-1', version: 'e2e', title: 'E2E', items: [
  { id: 'meniu-grupes', page: 'admin/*', href: 'admin/', sel: '.adm-side', title: 'Valdymo meniu: Kasdien, Darbai, Nustatymai', note: 'E2E' },
  { id: 'meniu-tvarkyti', page: 'admin/*', href: 'admin/', sel: '#am-edit', title: '✎ Meniu tvarkymas', note: 'E2E' },
  { id: 'perziura-taisyti', page: 'admin/perziura.php', sel: '#taisyti', title: 'Peržiūra: ką taisyti' },
  { id: 'perziura-eile', page: 'admin/perziura.php', sel: '#eile', title: 'Peržiūra: darbų eilė' },
] };
let saved = null;
// v2.10: Peržiūra numatytai išjungta - testui įjungiama ir pabaigoje išjungiama
test.beforeAll(() => { saved = fs.readFileSync(MANIFEST, 'utf8'); fs.writeFileSync(MANIFEST, JSON.stringify(TEST_MANIFEST)); sql("REPLACE INTO site_settings (name, value) VALUES ('review_on', '1')"); });
test.afterAll(() => { if (saved !== null) fs.writeFileSync(MANIFEST, saved); sql("DELETE FROM site_settings WHERE name = 'review_on'"); });

test('Peržiūra: queue fixes, gold marks with ✓/✗ and comments, submit, element notes, agent API', async ({ browser }) => {
  sql('DELETE FROM review_marks'); sql('DELETE FROM review_jobs');
  sql("INSERT INTO feedback (account_id, page, site, body, status) VALUES (NULL, '/admin/nariai.php', 'karateka', 'E2E peržiūra: mygtukas per mažas', 'new')");
  const fid = +sql('SELECT MAX(id) FROM feedback');
  const a = await login(browser, 'info@karateka.lt', 1440, 900);

  // Valdymas → Darbai → 🔎 Peržiūra: atsiliepimas sąraše, pažymimas su komentaru → darbas eilėje
  await a.goto('admin/');
  check('Peržiūra in the Darbai group', await a.locator('.am-group[data-g="darbai"] .am-row[data-k="perziura"]').count() === 1);
  await a.goto('admin/perziura.php');
  check('three parts: queue, test changes, what to fix', await a.locator('#eile').count() === 1 && await a.locator('#test').count() === 1 && await a.locator('#taisyti').count() === 1);
  await a.check(`input[name="ids[]"][value="${fid}"]`);
  await a.fill(`input[name="note[${fid}]"]`, 'Padidinti iki 44 px');
  await a.fill('textarea[name="note_all"]', 'E2E bendras');
  check('selected count updates', /1/.test(await a.textContent('#rvp-n')));
  await nav(a, () => a.click('#rvp-run'));
  // (mysql -N išvestyje „\“ dvigubinami - JSON skaitomas atskirai, ne JSON_OBJECT)
  const [jk, js, jn] = sql("SELECT kind, status, note FROM review_jobs ORDER BY id DESC LIMIT 1").split(String.fromCharCode(9));
  const ji = JSON.parse(sql('SELECT items FROM review_jobs ORDER BY id DESC LIMIT 1'));
  check('fix job queued with the item and comments', jk === 'fix' && js === 'queued' && ji[0].id === fid && ji[0].note === 'Padidinti iki 44 px' && jn === 'E2E bendras', [jk, js, jn, JSON.stringify(ji)].join(' | '));
  check('item shows it is queued', /eilėje #/.test(await a.textContent(`#f${fid}`)));

  // Agento API: mato darbą su atsiliepimo tekstu, pažymi „daroma“ ir „padaryta“ su atsakymu
  const hash = execFileSync('php', ['-r', "echo password_hash('e2e-api-key', PASSWORD_DEFAULT);"]).toString().trim();
  sql(`INSERT INTO site_settings (name, value) VALUES ('feedback_api_key', '${hash}') ON DUPLICATE KEY UPDATE value = VALUES(value)`);
  const H = { headers: { 'X-Api-Key': 'e2e-api-key' } };
  check('API refuses a wrong key', (await a.request.get('perziura-api.php', { headers: { 'X-Api-Key': 'bad' } })).status() === 401);
  const api = await (await a.request.get('perziura-api.php', H)).json();
  const aj = (api.jobs || []).find(j => j.kind === 'fix');
  check('API lists the job with the feedback text', aj && aj.items[0].feedback.body.includes('mygtukas per mažas') && api.review.items.length > 0, JSON.stringify(api).slice(0, 300));
  const claim1 = await a.request.post('perziura-api.php', { ...H, data: { id: aj.id, status: 'working' } });
  const claim2 = await a.request.post('perziura-api.php', { ...H, data: { id: aj.id, status: 'working' } });
  check('only one agent takes a job', claim1.status() === 200 && claim2.status() === 409 && (await claim2.json()).taken === true, `${claim1.status()} ${claim2.status()}`);
  await a.request.post('perziura-api.php', { ...H, data: { id: aj.id, status: 'done', result: 'E2E: pataisyta, paruošta įkelti' } });
  await a.reload();
  check('agent reply shown in the queue', /E2E: pataisyta, paruošta įkelti/.test(await a.textContent('#eile')) && /Padaryta/.test(await a.textContent(`[data-job="${aj.id}"]`)));

  // „⬆ Įkelti į test“ → darbas; laukiantį galima atšaukti
  await nav(a, () => a.click('#rvp-deploy'));
  check('deploy job queued', sql("SELECT kind FROM review_jobs ORDER BY id DESC LIMIT 1") === 'deploy');
  await nav(a, () => a.click('#eile [data-job] form button'));
  check('queued job can be cancelled', sql("SELECT status FROM review_jobs ORDER BY id DESC LIMIT 1") === 'cancelled');
  // Darbą padarė žmogus - „✓“ ir atsakymas
  await nav(a, () => a.click('#rvp-deploy'));
  await a.click('#eile [data-job] .rvp-close summary');
  await a.fill('#eile [data-job] .rvp-close input[name=result]', 'E2E: padaryta ranka');
  await nav(a, () => a.click('#eile [data-job] .rvp-close button'));
  check('job closed by hand with a reply', sql("SELECT CONCAT(status, '|', result) FROM review_jobs ORDER BY id DESC LIMIT 1") === 'done|E2E: padaryta ranka');

  // Auksinės žymos: Valdymo puslapyje - meniu pakeitimai; □ → ✓ → ✗ (atsidaro komentaras)
  await a.goto('admin/');
  await a.waitForSelector('.pzr-bar');
  await a.waitForSelector('.pzr-ctl[data-pzr="meniu-grupes"] .pzr-mark');
  check('changed elements outlined in gold', await a.locator('.pzr-box[data-pzr="meniu-grupes"]').isVisible() && await a.locator('.pzr-box[data-pzr="meniu-tvarkyti"]').isVisible());
  const mk = a.locator('.pzr-ctl[data-pzr="meniu-grupes"] .pzr-mark');
  await mk.click(); await a.waitForTimeout(400);
  check('first click: ✓ good', (await mk.textContent()) === '✓' && sql("SELECT mark FROM review_marks WHERE change_id = 'meniu-grupes'") === 'good');
  await mk.click();
  await a.waitForSelector('.pzr-pop textarea');
  check('second click: ✗ bad opens the comment', (await mk.textContent()) === '✗');
  await a.fill('.pzr-pop textarea', 'Per siauras meniu');
  await a.click('.pzr-pop .pzr-primary'); await a.waitForTimeout(400);
  check('bad mark with comment saved', sql("SELECT CONCAT(mark, '|', comment) FROM review_marks WHERE change_id = 'meniu-grupes'") === 'bad|Per siauras meniu');
  await a.locator('.pzr-ctl[data-pzr="meniu-tvarkyti"] .pzr-mark').click(); await a.waitForTimeout(300);

  // Visų pakeitimų sąrašas - ir kitų puslapių su nuoroda
  await a.click('.pzr-listbtn');
  check('list shows changes on other pages with a link', await a.locator('.pzr-list a[href*="admin/perziura.php#pzr-perziura-eile"]').count() === 1);
  await a.click('.pzr-listbtn');

  // Pateikti → darbas „review“ agentui; juosta - „pateikta“
  await a.click('#pzr-submit'); await a.waitForTimeout(600);
  const rk = sql('SELECT kind FROM review_jobs ORDER BY id DESC LIMIT 1');
  const ri = JSON.parse(sql('SELECT items FROM review_jobs ORDER BY id DESC LIMIT 1'));
  check('review job with marks and comments', rk === 'review' && ri.find(x => x.id === 'meniu-grupes').mark === 'bad' && ri.find(x => x.id === 'meniu-grupes').comment === 'Per siauras meniu' && ri.find(x => x.id === 'meniu-tvarkyti').mark === 'good', JSON.stringify(ri));
  check('bar shows submitted', await a.locator('#pzr-submit.is-sent').count() === 1);
  await a.reload();
  await a.waitForSelector('#pzr-submit');
  check('submitted state stays after reload', await a.locator('#pzr-submit.is-sent').count() === 1);

  // 🎯 Pastaba apie bet kurį elementą → atsiliepimas su elemento adresu
  await a.click('.pzr-pick');
  await a.click('main h1');
  await a.waitForSelector('.pzr-pop textarea');
  await a.fill('.pzr-pop textarea', 'E2E: antraštė per didelė');
  await a.click('.pzr-pop .pzr-primary'); await a.waitForTimeout(600);
  const pick = JSON.parse(sql("SELECT context FROM feedback WHERE body = 'E2E: antraštė per didelė' ORDER BY id DESC LIMIT 1") || '{}');
  check('element note saved with its selector', pick.pick === true && /h1/.test(pick.selector), JSON.stringify(pick));
  await a.goto('admin/perziura.php');
  check('element note listed with 🎯', /🎯/.test(await a.textContent('#taisyti')));

  // Telefone juosta telpa ekrane
  const p = await login(browser, 'info@karateka.lt', 375, 812);
  await p.goto('admin/');
  await p.waitForSelector('.pzr-bar');
  const bb = await p.locator('.pzr-bar').boundingBox();
  check('bar fits the phone screen', bb && bb.x >= 0 && bb.x + bb.width <= 375, JSON.stringify(bb));

  // Treneris: nei žymų, nei puslapio, nei veiksmų
  const c = await login(browser, 'treneris@karateka.test', 1440, 900);
  await c.goto('admin/');
  check('coach sees no review bar', await c.locator('.pzr-bar').count() === 0);
  check('coach cannot open Peržiūra', (await c.request.get('admin/perziura.php')).status() === 403);
  check('coach cannot mark', (await c.request.post('perziura-zymos.php', { data: { action: 'mark' } })).status() === 403);

  sql(`DELETE FROM feedback WHERE id = ${fid} OR body = 'E2E: antraštė per didelė'`);
  sql('DELETE FROM review_marks'); sql('DELETE FROM review_jobs');
  noJsErrors(a, p, c);
});

test('Peržiūra switched off: no menu item, no marks, the page and the agent API say it is off', async ({ browser }) => {
  sql("DELETE FROM site_settings WHERE name = 'review_on'");
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/');
  check('no Peržiūra in the menu', await a.locator('.am-row[data-k="perziura"]').count() === 0);
  check('no review bar', await a.locator('.pzr-bar').count() === 0);
  await a.goto('admin/perziura.php');
  check('page says it is off with a link to switch it on', /išjungta/.test(await a.textContent('main')) && await a.locator('a[href*="testavimas.php#perziura"]').count() === 1);
  const api = await (await a.request.get('perziura-api.php', { headers: { 'X-Api-Key': 'e2e-api-key' } })).json();
  check('agent API says off', api.ok === false && api.off === true, JSON.stringify(api));
  // Įjungiama Testavimo puslapyje ir vėl išjungiama
  await a.goto('admin/testavimas.php');
  await nav(a, () => a.click('#review-toggle'));
  check('switched on', sql("SELECT value FROM site_settings WHERE name = 'review_on'") === '1');
  await a.goto('admin/');
  check('menu item back when on', await a.locator('.am-row[data-k="perziura"]').count() === 1);
  await a.goto('admin/testavimas.php');
  await nav(a, () => a.click('#review-toggle'));
  check('switched off again', sql("SELECT IFNULL(MAX(value), '') FROM site_settings WHERE name = 'review_on'") === '');
  noJsErrors(a);
});
