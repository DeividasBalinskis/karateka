// v2.02: 🔎 Peržiūra - atsiliepimai pažymimi ir „▶ Paleisti taisymą“ (darbų eilė agentui), auksinės žymos test svetainėje
// (□ → ✓ → ✗ + komentaras, „Pateikti“), „🎯 Pastaba apie elementą“, agento API (perziura-api.php). Tik tikriems administratoriams.
const { test } = require('@playwright/test');
const { execFileSync } = require('child_process');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

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
  const job = JSON.parse(sql("SELECT JSON_OBJECT('kind', kind, 'status', status, 'items', items, 'note', note) FROM review_jobs ORDER BY id DESC LIMIT 1"));
  check('fix job queued with the item and comments', job.kind === 'fix' && job.status === 'queued' && JSON.parse(job.items)[0].id === fid && JSON.parse(job.items)[0].note === 'Padidinti iki 44 px' && job.note === 'E2E bendras', JSON.stringify(job));
  check('item shows it is queued', /eilėje #/.test(await a.textContent(`#f${fid}`)));

  // Agento API: mato darbą su atsiliepimo tekstu, pažymi „daroma“ ir „padaryta“ su atsakymu
  const hash = execFileSync('php', ['-r', "echo password_hash('e2e-api-key', PASSWORD_DEFAULT);"]).toString().trim();
  sql(`INSERT INTO site_settings (name, value) VALUES ('feedback_api_key', '${hash}') ON DUPLICATE KEY UPDATE value = VALUES(value)`);
  const H = { headers: { 'X-Api-Key': 'e2e-api-key' } };
  check('API refuses a wrong key', (await a.request.get('perziura-api.php', { headers: { 'X-Api-Key': 'bad' } })).status() === 401);
  const api = await (await a.request.get('perziura-api.php', H)).json();
  const aj = (api.jobs || []).find(j => j.kind === 'fix');
  check('API lists the job with the feedback text', aj && aj.items[0].feedback.body.includes('mygtukas per mažas') && api.review.items.length > 0, JSON.stringify(api).slice(0, 300));
  await a.request.post('perziura-api.php', { ...H, data: { id: aj.id, status: 'working' } });
  await a.request.post('perziura-api.php', { ...H, data: { id: aj.id, status: 'done', result: 'E2E: pataisyta, paruošta įkelti' } });
  await a.reload();
  check('agent reply shown in the queue', /E2E: pataisyta, paruošta įkelti/.test(await a.textContent('#eile')) && /Padaryta/.test(await a.textContent(`[data-job="${aj.id}"]`)));

  // „⬆ Įkelti į test“ → darbas; laukiantį galima atšaukti
  await nav(a, () => a.click('#rvp-deploy'));
  check('deploy job queued', sql("SELECT kind FROM review_jobs ORDER BY id DESC LIMIT 1") === 'deploy');
  await nav(a, () => a.click('#eile [data-job] form button'));
  check('queued job can be cancelled', sql("SELECT status FROM review_jobs ORDER BY id DESC LIMIT 1") === 'cancelled');

  // Auksinės žymos: Valdymo puslapyje - meniu pakeitimai; □ → ✓ → ✗ (atsidaro komentaras)
  await a.goto('admin/');
  await a.waitForSelector('.rv-bar');
  await a.waitForSelector('.rv-ctl[data-rv="meniu-grupes"] .rv-mark');
  check('changed elements outlined in gold', await a.locator('.rv-box[data-rv="meniu-grupes"]').isVisible() && await a.locator('.rv-box[data-rv="meniu-tvarkyti"]').isVisible());
  const mk = a.locator('.rv-ctl[data-rv="meniu-grupes"] .rv-mark');
  await mk.click(); await a.waitForTimeout(400);
  check('first click: ✓ good', (await mk.textContent()) === '✓' && sql("SELECT mark FROM review_marks WHERE change_id = 'meniu-grupes'") === 'good');
  await mk.click();
  await a.waitForSelector('.rv-pop textarea');
  check('second click: ✗ bad opens the comment', (await mk.textContent()) === '✗');
  await a.fill('.rv-pop textarea', 'Per siauras meniu');
  await a.click('.rv-pop .rv-primary'); await a.waitForTimeout(400);
  check('bad mark with comment saved', sql("SELECT CONCAT(mark, '|', comment) FROM review_marks WHERE change_id = 'meniu-grupes'") === 'bad|Per siauras meniu');
  await a.locator('.rv-ctl[data-rv="meniu-tvarkyti"] .rv-mark').click(); await a.waitForTimeout(300);

  // Visų pakeitimų sąrašas - ir kitų puslapių su nuoroda
  await a.click('.rv-listbtn');
  check('list shows changes on other pages with a link', await a.locator('.rv-list a[href*="admin/perziura.php#rv-perziura-eile"]').count() === 1);
  await a.click('.rv-listbtn');

  // Pateikti → darbas „review“ agentui; juosta - „pateikta“
  await a.click('#rv-submit'); await a.waitForTimeout(600);
  const rj = JSON.parse(sql("SELECT JSON_OBJECT('kind', kind, 'items', items) FROM review_jobs ORDER BY id DESC LIMIT 1"));
  const ri = JSON.parse(rj.items);
  check('review job with marks and comments', rj.kind === 'review' && ri.find(x => x.id === 'meniu-grupes').mark === 'bad' && ri.find(x => x.id === 'meniu-grupes').comment === 'Per siauras meniu' && ri.find(x => x.id === 'meniu-tvarkyti').mark === 'good', rj.items);
  check('bar shows submitted', await a.locator('#rv-submit.is-sent').count() === 1);
  await a.reload();
  await a.waitForSelector('#rv-submit');
  check('submitted state stays after reload', await a.locator('#rv-submit.is-sent').count() === 1);

  // 🎯 Pastaba apie bet kurį elementą → atsiliepimas su elemento adresu
  await a.click('.rv-pick');
  await a.click('main h1');
  await a.waitForSelector('.rv-pop textarea');
  await a.fill('.rv-pop textarea', 'E2E: antraštė per didelė');
  await a.click('.rv-pop .rv-primary'); await a.waitForTimeout(600);
  const pick = JSON.parse(sql("SELECT context FROM feedback WHERE body = 'E2E: antraštė per didelė' ORDER BY id DESC LIMIT 1") || '{}');
  check('element note saved with its selector', pick.pick === true && /h1/.test(pick.selector), JSON.stringify(pick));
  await a.goto('admin/perziura.php');
  check('element note listed with 🎯', /🎯/.test(await a.textContent('#taisyti')));

  // Telefone juosta telpa ekrane
  const p = await login(browser, 'info@karateka.lt', 375, 812);
  await p.goto('admin/');
  await p.waitForSelector('.rv-bar');
  const bb = await p.locator('.rv-bar').boundingBox();
  check('bar fits the phone screen', bb && bb.x >= 0 && bb.x + bb.width <= 375, JSON.stringify(bb));

  // Treneris: nei žymų, nei puslapio, nei veiksmų
  const c = await login(browser, 'treneris@karateka.test', 1440, 900);
  await c.goto('admin/');
  check('coach sees no review bar', await c.locator('.rv-bar').count() === 0);
  check('coach cannot open Peržiūra', (await c.request.get('admin/perziura.php')).status() === 403);
  check('coach cannot mark', (await c.request.post('perziura-zymos.php', { data: { action: 'mark' } })).status() === 403);

  sql(`DELETE FROM feedback WHERE id = ${fid} OR body = 'E2E: antraštė per didelė'`);
  sql('DELETE FROM review_marks'); sql('DELETE FROM review_jobs');
  noJsErrors(a, p, c);
});
