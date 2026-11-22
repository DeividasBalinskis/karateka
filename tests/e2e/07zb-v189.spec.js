// v1.89: atsiliepimai #164-#171 ir „gyvas taisymas“: autorius įvertina pataisymą (Patinka / Grąžinti), Claude prieiga diegimui,
// protokolo balai 6.4, Budo: aprašymai išimti, pavadinimas atidaro valdymą, kitos sistemos antraštėje, OBS juosta; LTKF meniu redagavimas.
const { test } = require('@playwright/test');
const { execFileSync } = require('child_process');
const { sql, day, login, nav, check, noJsErrors, openFeedback } = require('./helpers');

test('feedback: bigger box, one latest own item, author likes or asks to undo; Claude sees it', async ({ browser }) => {
  sql("REPLACE INTO site_settings (name, value) VALUES ('feedback_button', 'staff')");
  sql("DELETE FROM feedback WHERE body LIKE 'E2E v189%'");
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/nariai.php');
  await openFeedback(a);
  for (const t of ['E2E v189 pirmas', 'E2E v189 antras']) {
    await a.fill('#fbText', t); await a.press('#fbText', 'Enter');
    await a.waitForFunction(() => document.getElementById('fbText').value === '');
  }
  await a.waitForTimeout(500);
  check('#164 bigger box', (await a.locator('#fbPanel').boundingBox()).width >= 500 && (await a.locator('#fbText').boundingBox()).height >= 140);
  check('#164 only the latest own item, the rest behind „Rodyti visus“', await a.locator('#fbList .fb-item:not(.fb-qitem)').count() === 1 && await a.locator('#fbList [data-mine-all]').count() === 1);
  // Pataisyta - autorius mato „Patinka“ / „Grąžinti kaip buvo“ (ir kitame įrenginyje)
  sql("UPDATE feedback SET status = 'done', reply = 'Padaryta' WHERE body LIKE 'E2E v189%'");
  const b = await login(browser, 'info@karateka.lt', 1440, 900);   // kita naršyklė - tas pats Denis
  await b.goto('admin/nariai.php');
  await openFeedback(b);
  await b.waitForSelector('#fbList [data-verdict]');
  check('buttons on another device too', await b.locator('#fbList [data-verdict="ok"]').count() >= 1 && await b.locator('#fbList [data-verdict="revert"]').count() >= 1);
  await b.locator('#fbList [data-verdict="ok"]').first().click();
  await b.waitForFunction(() => /Paliekame/.test(document.getElementById('fbState').textContent));
  await b.click('#fbList [data-mine-all]');
  await b.locator('#fbList [data-verdict="revert"]').first().click();
  await b.waitForTimeout(800);
  check('verdicts saved', sql("SELECT GROUP_CONCAT(verdict ORDER BY verdict) FROM feedback WHERE body LIKE 'E2E v189%'") === 'ok,revert');
  check('cannot judge twice', await b.locator('#fbList .fb-item', { hasText: 'E2E v189' }).locator('[data-verdict]').count() === 0);
  // Valdymas → Atsiliepimai rodo įvertinimą
  await b.goto('admin/atsiliepimai.php?b=visi&q=E2E+v189');
  check('admin page shows verdicts', await b.locator('.fb-card .badge', { hasText: 'Patinka' }).count() === 1 && await b.locator('.fb-card .badge', { hasText: 'Prašo grąžinti' }).count() === 1);
  // Claude prieiga: atsiliepimai „grąžinti“ ir diegimo adresas (raktas - „Claude prieiga“)
  const hash = execFileSync('php', ['-r', 'echo password_hash("e2e-claude-key", PASSWORD_DEFAULT);']).toString();
  sql(`REPLACE INTO site_settings (name, value) VALUES ('feedback_api_key', '${hash}')`);
  const api = await b.request.get('atsiliepimai-api.php?status=revert', { headers: { 'X-Api-Key': 'e2e-claude-key' } });
  const items = (await api.json()).items || [];
  check('API lists items to undo', items.some(i => /E2E v189/.test(i.body) && i.verdict === 'revert'));
  const rid = items.find(i => /E2E v189/.test(i.body)).id;
  await b.request.post('atsiliepimai-api.php', { headers: { 'X-Api-Key': 'e2e-claude-key', 'Content-Type': 'application/json' }, data: { id: rid, status: 'planned', reply: 'Grąžinta', verdict: 'reverted' } });
  check('Claude marks it undone', sql(`SELECT verdict FROM feedback WHERE id = ${rid}`) === 'reverted');
  const noKey = await b.request.get('diegimas-api.php');
  const dep = await b.request.get('diegimas-api.php', { headers: { 'X-Api-Key': 'e2e-claude-key' } });
  const dj = await dep.json();
  check('deploy API needs the key', noKey.status() === 401);
  check('deploy API shows version and database state', dj.ok && /^\d+\.\d+$/.test(dj.version) && Array.isArray(dj.pending) && /^0\d\d_\w+\.sql$/.test(dj.db_required), JSON.stringify(dj));
  const upd = await (await b.request.post('diegimas-api.php', { headers: { 'X-Api-Key': 'e2e-claude-key', 'Content-Type': 'application/json' }, data: { action: 'dbupdate' } })).json();
  check('deploy API runs the database update', upd.ok && upd.pending.length === 0, JSON.stringify(upd));
  sql("DELETE FROM site_settings WHERE name = 'feedback_api_key'");
  sql("DELETE FROM feedback WHERE body LIKE 'E2E v189%'");
  noJsErrors(a, b);
});

test('#167: on a low screen the box top with × stays visible', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1280, 560);
  await a.goto('admin/');
  await openFeedback(a);
  await a.waitForTimeout(600);
  const head = await a.locator('#fbPanel .fb-head').boundingBox();
  check('header and × visible', head && head.y >= 0 && await a.locator('#fbPanel .fb-close').isVisible(), JSON.stringify(head));
  noJsErrors(a);
});

test('#166: a new person in the exam protocol starts with 6.4', async ({ browser }) => {
  sql(`INSERT INTO events (type, title, starts_on, start_time) VALUES ('exam', 'Kyu egzaminas (v189)', '${day(5)}', '11:00')`);
  const ev = +sql("SELECT id FROM events WHERE title = 'Kyu egzaminas (v189)'");
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto(`admin/egzamino-protokolas.php?id=${ev}`);
  await a.click('.prot-add summary');
  await a.fill('.prot-add input[name=name]', 'Naujas Dalyvis');
  await nav(a, () => a.click('.prot-add button[type=submit]'));
  check('scores 6.4 at once', sql(`SELECT CONCAT(kihon, '|', kata, '|', kumite) FROM exam_protocol_rows WHERE event_id = ${ev}`) === '6.4|6.4|6.4');
  check('shown in the table', await a.locator(`[data-f="kihon"]`).first().inputValue() === '6.4');
  sql(`DELETE FROM events WHERE id = ${ev}`);
  noJsErrors(a);
});

test('Budo: no long descriptions, group name opens control, other systems menu, OBS bar', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/varzybos.php');
  check('#171 other systems in the Budo header', await a.locator('.vz-top .site-switch a[href$="index.php"]').count() === 1 && await a.locator('.vz-top .site-switch a[href*="ltkf/"]').count() === 1);
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  check('#171 also inside a competition', await a.locator('.vz-top .site-switch').count() === 1);
  check('#169 descriptions removed', await a.locator('.tk-explain').count() === 0 && !(await a.locator('#sekr-panel').innerText()).includes('Atidaręs nuorodą'));
  await a.click('#tab-admin'); await a.click('#tab-tinklai');
  await a.waitForSelector('#tk-view .tk-cat-name');
  await a.locator('#tk-view .tk-cat-name').first().click();
  await a.waitForTimeout(600);
  check('#170 category name opens control', await a.locator('#run').isVisible());
  // OBS juosta: permatoma, su kova
  const areaId = JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)).areas[0].id;
  const live = JSON.stringify({ mode: 'kumite', cat: 'Kumite M 10-11', round: 'Finalas', bout: '3', aka: { name: 'Jonas J.' }, shiro: { name: 'Petras P.' }, sa: 4, sb: 2, at: Date.now(), timer: { running: false, remaining: 61000, total: 90000 } });
  sql(`REPLACE INTO tournament_live (tournament_id, area_id, data, updated_ms) VALUES (${tid}, ${areaId}, '${live.replace(/'/g, "''")}', ${Date.now()})`);
  sql(`UPDATE tournaments SET share_token = 'abcdefabcdefabcdefab0789' WHERE id = ${tid}`);
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.click('#share-toggle');
  check('OBS bar link per tatami', /juosta=1&tatami=/.test(await a.locator('#sp-bars input').first().inputValue()));
  const v = await login(browser, null, 1920, 1080);
  await v.goto(`varzybos-gyvai.php?k=abcdefabcdefabcdefab0789&juosta=1&tatami=${areaId}`);
  await v.waitForSelector('.gv-bar');
  const txt = await v.locator('.gv-bar').innerText();
  check('bar shows the bout', /Jonas J\./.test(txt) && /Petras P\./.test(txt) && /4/.test(txt) && /1:01/.test(txt), txt);
  check('background is transparent', await v.evaluate(() => getComputedStyle(document.body).backgroundColor) === 'rgba(0, 0, 0, 0)');
  sql(`DELETE FROM tournaments WHERE id = ${tid}`);
  noJsErrors(a, v);
});

test('#168 LTKF: while editing, a menu item click renames it; ↗ opens the page', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1300, 900);
  await a.goto('ltkf/index.php?redaguoti=1');
  const navA = a.locator('.main-nav > a').nth(2);
  await navA.click();
  await a.waitForTimeout(500);
  check('stays on the page', /ltkf\/index\.php\?redaguoti=1/.test(a.url()), a.url());
  check('label editable', await navA.locator('.ed-navlab[contenteditable="true"]').count() === 1);
  check('↗ to open the page', await navA.locator('[data-nav="go"]').count() === 1);
  noJsErrors(a);
});
