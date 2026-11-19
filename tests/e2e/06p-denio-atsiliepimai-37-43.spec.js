// v1.33: Denio atsiliepimai #37-#43 - sistemos pavadintos pagal rungtį (Kata vėliavėlėmis / balais...), medaliai - atskiras nustatymas,
// komandinės katos finalas lieka vėliavėlėmis; paprastame vaizde - kas ruošiasi, „Laimėjo AKA / SHIRO“ ir su vėliavėlėmis, juodas tekstas laukeliuose;
// perburtos grupės nebelieka „apdovanota“; registracijoje vietos pagal rajonus; LTKF peržiūros nuoroda (atsiliepimai be paskyros, be nuorodos į karateka.lt).
const { test } = require('@playwright/test');
const { sql, login, check, noJsErrors } = require('./helpers');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8088/';

async function newTest(a) {
  sql('DELETE FROM tournaments WHERE is_test = 1');
  await a.goto('admin/varzybos.php');
  await Promise.all([a.waitForNavigation(), a.click('text=🎲 Sukurti test varžybas')]);
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.click('#tab-live');
  await a.waitForTimeout(600);
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  if (await a.locator('#area-pick [data-my-area="all"]').count()) await a.click('#area-pick [data-my-area="all"]');
  return tid;
}
async function openCat(a, re) {
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  const card = a.locator('#sched [data-open-cat]', { hasText: re }).first();
  if (!(await card.count())) return false;
  await card.click();
  await a.waitForSelector('#draw-system');
  return true;
}

test('Taika: systems named by event, medals separate, team kata final stays with flags, simple view helpers, redraw clears awards', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 1000);
  await newTest(a);

  // #37 Komandinė kata: pavadinimai pagal rungtį, finalas - vėliavėlėmis (ne balais)
  check('team kata group', await openCat(a, /Kata komandos/));
  await a.click('[data-runview="lines"]');
  const opts = await a.locator('#draw-system option').allInnerTexts();
  check('system names by event', opts.includes('Komandinė kata vėliavėlėmis') && opts.includes('Komandinė kata balais'), opts.join(' | '));
  check('medals is its own setting', await a.locator('#draw-medals').isVisible());
  await a.selectOption('#draw-medals', 'bronze');
  await a.waitForTimeout(200);
  check('pill shows event + medals', /Komandinė kata vėliavėlėmis · Kova dėl 3 vietos/.test(await a.textContent('#draw-view .pill.sys')), await a.textContent('#draw-view .pill.sys'));
  await a.click('#draw-sim');
  await a.waitForTimeout(300);
  const fin = a.locator('#draw-view .match', { has: a.locator('.match-label', { hasText: /Finalas/ }) }).first();
  check('final keeps flags, no score input', await fin.locator('.m-flags-score').count() === 1 && await fin.locator('.m-score').count() === 0);

  // #41 Baigta → išsiųsta apdovanojimams (v1.47 - pati, #122) → perburta: „apdovanojama“ nebelieka
  const send = a.locator('#run-done [data-award-send]');
  if (await send.count()) await send.first().click().catch(() => {});
  await a.waitForTimeout(1500);
  await a.waitForSelector('#run-done:not([hidden])');
  check('sent to awards', /apdovanojimams|apdovanojama/i.test(await a.textContent('#run-done')));
  await a.click('#draw-new');
  await a.waitForTimeout(400);
  check('redraw clears the awarded state', await a.locator('#run-done').isHidden());

  // #38 / #40 Paprastas vaizdas: „Laimėjo“ mygtukai ir su vėliavėlėmis, kas ruošiasi
  await a.click('[data-runview="simple"]');
  await a.waitForSelector('#draw-view .sv');
  check('flags: winner buttons and flag chips', await a.locator('#draw-view .sv-win').count() === 2 && await a.locator('#draw-view .sv-flags').count() === 2);
  check('who is preparing is shown', await a.locator('#draw-view .sv-prep').count() === 1, await a.locator('#draw-view').innerText());

  // #39 Katos balai paprastame vaizde: tekstas laukeliuose juodas
  check('individual kata group', await openCat(a, /^Kata (M|F)/));
  await a.selectOption('#draw-system', 'score');
  await a.click('[data-runview="simple"]');
  await a.waitForSelector('#draw-view .sv .j-in');
  const col = await a.$eval('#draw-view .sv .j-in', el => getComputedStyle(el).color);
  check('judge input text is dark', col === 'rgb(18, 21, 28)', col);
  const kumNames = await a.locator('#draw-system option').allInnerTexts();
  check('kata names', kumNames.includes('Kata balais') && kumNames.includes('Kata vėliavėlėmis'), kumNames.join(' | '));

  // Kategorijos nustatymuose - tie patys pavadinimai ir „Medaliai“ (jei kategorijos langas atidarytas)
  await a.click('#tab-admin'); await a.click('#tab-kategorijos');
  await a.waitForTimeout(400);
  if (await a.locator('#cat-detail .sys-card').count()) {
    const cards = await a.locator('#cat-detail .sys-card b').allInnerTexts();
    check('category settings use event names', cards.some(t => /vėliavėlėmis|balais|olimpinė|Fukugo/.test(t)), cards.join(' | '));
  }
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});

test('home registration: locations grouped by district with who it is for', async ({ browser }) => {
  const p = await browser.newPage({ baseURL: BASE_URL });
  await p.goto('index.php');
  const sel = p.locator('select[name="lokacija"]');
  if (await sel.count()) {
    const groups = await sel.locator('optgroup').evaluateAll(gs => gs.map(g => g.label));
    check('districts as groups', groups.includes('Viršuliškės') && groups.includes('Pašilaičiai') && !groups.includes('Vaikai'), groups.join(' | '));
    const opt = await sel.locator('optgroup[label="Viršuliškės"] option').first().innerText();
    check('who it is for next to the place', /·\s*vaikai/.test(opt), opt);
  }
  await p.close();
});

test('LTKF review link: guests leave feedback, karateka.lt link only for the admin', async ({ browser }) => {
  sql("DELETE FROM site_settings WHERE name = 'ltkf_review_token'");
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  await a.goto('ltkf/index.php');
  check('admin sees ← karateka.lt and the review link', await a.locator('.karateka-bar a', { hasText: 'karateka.lt' }).count() === 1 && await a.locator('.karateka-bar a[href*="ltkf-perziura"]').count() === 1);
  await a.goto('admin/ltkf-perziura.php');
  await Promise.all([a.waitForNavigation(), a.click('button[value="on"]')]);
  const link = await a.inputValue('#ltkfLink');
  check('link created', /ltkf-perziura\.php\?k=[0-9a-f]{24}$/.test(link), link);

  const g = await browser.newPage();
  await g.goto(BASE_URL + 'ltkf/index.php');
  check('guest without link: no karateka.lt link', await g.locator('.karateka-bar a', { hasText: 'karateka.lt' }).count() === 0);
  await g.goto(link);
  check('link opens the LTKF site', /\/ltkf\/(index\.php)?$/.test(g.url()), g.url());
  check('reviewer: no karateka.lt link', await g.locator('.karateka-bar a', { hasText: 'karateka.lt' }).count() === 0);
  await g.waitForSelector('#fbFab', { timeout: 8000 });
  await g.click('#fbFab');
  await g.fill('#fbText', 'LTKF peržiūra: meniu aiškus');
  await g.press('#fbText', 'Enter');
  await g.waitForTimeout(1200);
  check('reviewer feedback saved', sql("SELECT CONCAT(IFNULL(name,''), '|', site) FROM feedback WHERE body LIKE 'LTKF peržiūra: meniu%' ORDER BY id DESC LIMIT 1") === 'Svečias (LTKF peržiūra)|ltkf');
  sql("DELETE FROM site_settings WHERE name = 'ltkf_review_token'");
  await g.close();
  noJsErrors(a);
});

test('quick new member: only name + year or group; invite link connects the person who registers', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1280, 900);
  // Tik vardas, pavardė ir gimimo metai (be grupės, be el. pašto) → nuoroda nukopijuoti
  await a.goto('admin/nariai.php?new=1');
  await a.fill('input[name="first_name"]', 'Greitas');
  await a.fill('input[name="last_name"]', 'Pridėtasis');
  await a.fill('input[name="birth_year"]', '2016');
  await Promise.all([a.waitForNavigation(), a.click('button:has-text("Pridėti narį")')]);
  const mid = +sql("SELECT id FROM members WHERE first_name = 'Greitas' AND last_name = 'Pridėtasis' ORDER BY id DESC LIMIT 1");
  check('member created with only a year', sql(`SELECT CONCAT(birth_date, '|', IFNULL(group_id, '-'), '|', birth_precision) FROM members WHERE id = ${mid}`) === '2016-01-01|-|1');
  const link = await a.inputValue('#joinLink');
  check('invite link shown to copy', /jungtis\.php\?t=[0-9a-f]{64}$/.test(link), link);

  // Tik grupė (be metų) → metai apytiksliai pagal grupę
  await a.goto('admin/nariai.php?new=1');
  await a.fill('input[name="first_name"]', 'Grupinis');
  await a.fill('input[name="last_name"]', 'Narys');
  await a.selectOption('select[name="group_id"]', { index: 1 });
  await a.fill('input[name="link_email"]', 'grupinis.tevai@example.test');
  await Promise.all([a.waitForNavigation(), a.click('button:has-text("Pridėti narį")')]);
  const gid = +sql("SELECT id FROM members WHERE first_name = 'Grupinis' ORDER BY id DESC LIMIT 1");
  check('group only: approximate year', sql(`SELECT birth_precision FROM members WHERE id = ${gid}`) === '2' && +sql(`SELECT group_id FROM members WHERE id = ${gid}`) > 0);
  check('email invite token created', +sql(`SELECT COUNT(*) FROM email_tokens WHERE purpose = 'join' AND member_id = ${gid} AND email = 'grupinis.tevai@example.test'`) === 1);

  // Svečias atidaro nuorodą, užsiregistruoja kaip tėvai → susietas su tuo nariu
  const g = await browser.newPage({ baseURL: BASE_URL });
  await g.goto(link);
  check('join page shows the member', /Greitas Pridėtasis/.test(await g.textContent('h1')));
  await g.check('input[name="relation"][value="parent"]');
  await g.fill('input[name="first_name"]', 'Tėtis');
  await g.fill('input[name="last_name"]', 'Pridėtasis');
  await g.fill('input[name="email"]', 'tetis.pridetasis@example.test');
  await g.fill('input[name="password"]', 'Slaptas123!');
  await g.fill('input[name="password2"]', 'Slaptas123!');
  await Promise.all([g.waitForNavigation(), g.click('button:has-text("Sukurti paskyrą")')]);
  check('lands in the account', /paskyra\.php/.test(g.url()), g.url());
  check('account linked to the member as parent', sql(`SELECT CONCAT(a.status, '|', am.relation) FROM accounts a JOIN account_members am ON am.account_id = a.id WHERE a.email = 'tetis.pridetasis@example.test' AND am.member_id = ${mid}`) === 'active|parent');
  await g.goto(link);
  check('link works only once', /nebegalioja/.test(await g.textContent('h1')));
  await g.close();
  noJsErrors(a);
});
