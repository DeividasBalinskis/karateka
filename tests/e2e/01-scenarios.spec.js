// Pagrindiniai scenarijai su pavyzdiniais duomenimis (dev_seed + demo-duomenys + dev_pavyzdziai), iš eilės:
// tėvai -> Denis (adminas) -> treneris -> narys -> svečias. Žingsniai remiasi ankstesniais (pvz. Denio atsakymas Tomui).
const { test } = require('@playwright/test');
const { sql, day, ids, login, nav, check, noJsErrors, flashes } = require('./helpers');

test.describe.configure({ mode: 'serial' });

let I, camp, sem, open;
test.beforeAll(() => {
  I = ids();
  camp = +sql("SELECT id FROM events WHERE title='TEST: Stovykla - registracija vyksta'");
  sem = +sql("SELECT id FROM events WHERE title='TEST: Seminaras - registracija baigėsi'");
  open = +sql("SELECT id FROM events WHERE title='TEST: Atviras renginys'");
});

test('parent: celebration, Svarbu, invitations, exam program, quiz XP, chat, comments', async ({ browser }) => {
  const p = await login(browser, 'tevai@karateka.test');
  sql(`UPDATE notifications SET read_at = NULL WHERE type='level_up'`);
  await p.goto('paskyra.php?m=' + I.jonas);
  check('celebration shown', await p.locator('#celebrate').count() === 1);
  await p.click('#celebrate button'); await p.waitForTimeout(600);
  await p.reload();
  check('celebration not again', await p.locator('#celebrate').count() === 0);
  check('svarbu exists', await p.locator('#svarbu').count() === 1);
  check('svarbu invite camp with countdown', await p.locator('#svarbu .notif.t-invite', { hasText: 'TEST: Stovykla' }).locator('.deadline[data-deadline]').count() === 1);
  check('svarbu reply item', await p.locator('#svarbu .notif.t-reply').count() === 1);
  check('svarbu sent lesson', await p.locator('#svarbu .notif.t-note', { hasText: 'atsiuntė pamoką' }).count() >= 1);
  check('svarbu broadcast', await p.locator('#svarbu .notif.t-broadcast').count() === 1);
  check('xp card', /2\s*lygis/.test((await p.locator('.points-card .xp-card').textContent()).replace(/\s+/g, ' ')));
  // RSVP camp
  await nav(p, () => p.locator('#svarbu .notif.t-invite', { hasText: 'TEST: Stovykla' }).locator('button', { hasText: 'Dalyvausiu' }).click());
  check('rsvp going', sql(`SELECT status FROM event_participants WHERE event_id=${camp} AND member_id=${I.jonas}`) === 'going');
  check('rsvp back to svarbu', p.url().includes('#svarbu'));
  check('camp gone from svarbu', await p.locator('#svarbu .notif.t-invite', { hasText: 'TEST: Stovykla' }).count() === 0);
  // late request seminar
  await nav(p, () => p.locator('#svarbu .notif.t-invite', { hasText: 'TEST: Seminaras' }).locator('button', { hasText: 'pavėlavau' }).click());
  check('late status', sql(`SELECT status FROM event_participants WHERE event_id=${sem} AND member_id=${I.jonas}`) === 'late');
  check('late notif to admin', +sql(`SELECT COUNT(*) FROM notifications WHERE type='late_request' AND member_id=${I.jonas} AND done_at IS NULL`) === 1);
  // sent lesson viewed from Svarbu
  await nav(p, () => p.locator('#svarbu .notif.t-note', { hasText: 'atsiuntė pamoką' }).first().locator('button').click());
  check('note handled', p.url().includes('#svarbu'));
  // broadcast Supratau
  await nav(p, () => p.locator('#svarbu .notif.t-broadcast button', { hasText: 'Supratau' }).click());
  check('broadcast done', await p.locator('#svarbu .notif.t-broadcast').count() === 0);
  // reply stays until read; text shown; Perskaičiau removes
  await p.reload();
  check('reply item persists', await p.locator('#svarbu .notif.t-reply', { hasText: 'vyks kaip įprastai' }).count() === 1);
  await nav(p, () => p.locator('#svarbu .notif.t-reply button', { hasText: 'Perskaičiau' }).first().click());
  check('reply item gone after read', await p.locator('#svarbu .notif.t-reply').count() === 0);
  // Egzaminas: request / cancel
  await p.goto('egzaminas.php?m=' + I.jonas);
  const pend0 = await p.locator('.req-list li.pending').count();
  await nav(p, () => p.locator('.req-list li:not(.pending):not(.done) button.req-check').first().click());
  check('skill pending +1', await p.locator('.req-list li.pending').count() === pend0 + 1);
  await nav(p, () => p.locator('.req-list li.pending button.req-check').last().click());
  check('skill cancel', await p.locator('.req-list li.pending').count() === pend0);
  check('approved stars no button', await p.locator('.req-list li.done button').count() === 0);
  // Quiz real attempt -> XP
  const xp0 = +sql(`SELECT COALESCE(SUM(points),0) FROM point_awards WHERE member_id=${I.jonas}`);
  await p.goto(`testas.php?q=2&m=${I.jonas}`);
  await p.evaluate(() => { const seen = {}; document.querySelectorAll('form input[type=radio]').forEach(r => { if (!seen[r.name]) { r.checked = true; seen[r.name] = 1; } }); });
  await nav(p, () => p.locator('form button[type=submit]').last().click());
  const xp1 = +sql(`SELECT COALESCE(SUM(points),0) FROM point_awards WHERE member_id=${I.jonas}`);
  check('quiz xp awarded', xp1 > xp0, `${xp0} -> ${xp1} flash ${await flashes(p)}`);
  // retake same season - no new attempt XP
  await p.goto(`testas.php?q=2&m=${I.jonas}`);
  await p.evaluate(() => { const seen = {}; document.querySelectorAll('form input[type=radio]').forEach(r => { if (!seen[r.name]) { r.checked = true; seen[r.name] = 1; } }); });
  await nav(p, () => p.locator('form button[type=submit]').last().click());
  const xp2 = +sql(`SELECT COALESCE(SUM(points),0) FROM point_awards WHERE member_id=${I.jonas}`);
  check('quiz no farming', xp2 === xp1 || xp2 - xp1 === 5, `${xp1} -> ${xp2}`);
  // Chat
  await p.goto('renginiai.php');
  await p.click('#chatFab'); await p.waitForTimeout(800);
  await p.selectOption('#chatPanel .chat-member select', String(I.jonas)); await p.waitForTimeout(800);
  const before = await p.locator('#chatPanel .chat-msg').count();
  check('chat thread loaded', before >= 2, before);
  await p.fill('#chatPanel .chat-form textarea', 'Ačiū už atsakymą!');
  await p.click('#chatPanel .chat-form button'); await p.waitForTimeout(900);
  check('chat sent', await p.locator('#chatPanel .chat-msg').count() === before + 1);
  await p.click('#chatPanel .chat-tabs button[data-tab=anon]');
  const anon0 = +sql('SELECT COUNT(*) FROM member_messages WHERE anonymous=1');
  await p.fill('#chatPanel .chat-anon textarea', 'Labai patinka nauja svetainė');
  await p.click('#chatPanel .chat-anon button'); await p.waitForTimeout(700);
  check('anon saved', +sql('SELECT COUNT(*) FROM member_messages WHERE anonymous=1') === anon0 + 1);
  check('anon has author for Denis', +sql("SELECT COUNT(*) FROM member_messages WHERE anonymous=1 AND body='Labai patinka nauja svetainė' AND author_id IS NOT NULL") === 1);
  check('anon page stored', sql("SELECT page FROM member_messages WHERE body='Labai patinka nauja svetainė'").includes('renginiai'));
  check('anon notif to admin', +sql("SELECT COUNT(*) FROM notifications WHERE type='comment' AND done_at IS NULL AND title LIKE 'Anoniminis%'") >= 2);
  // event page public Q&A + own question
  await p.goto('renginys.php?id=' + camp);
  check('public QA visible', await p.locator('.q-public .q-item').count() >= 1);
  await p.selectOption('#klausimai select[name=member_id]', String(I.austeja));
  await p.fill('#klausimai textarea', 'Ar bus maitinimas?');
  await nav(p, () => p.click('#klausimai button[type=submit]'));
  check('question saved with event', +sql(`SELECT COUNT(*) FROM member_messages WHERE event_id=${camp} AND body='Ar bus maitinimas?'`) === 1);
  check('comment notif links to post', sql(`SELECT link FROM notifications WHERE type='comment' AND body LIKE '%Ar bus maitinimas?%' ORDER BY id DESC LIMIT 1`).startsWith(`renginys.php?id=${camp}#q-`));
  await p.goto('renginys.php?id=' + camp);
  await p.check('#klausimai input[name=anonymous]');
  await p.fill('#klausimai textarea', 'Anoniminis klausimas prie renginio');
  await nav(p, () => p.click('#klausimai button[type=submit]'));
  check('event anon comment', +sql(`SELECT COUNT(*) FROM member_messages WHERE event_id=${camp} AND anonymous=1 AND body='Anoniminis klausimas prie renginio'`) === 1);
  // open event can join, not invited
  await p.goto('renginys.php?id=' + open);
  check('open event join buttons', await p.locator('.ep-reg button', { hasText: 'Dalyvausiu' }).count() === 2);
  // security: other member chat
  const r1 = await p.request.get('pokalbis.php?m=' + I.tomas);
  check('chat other member 404', r1.status() === 404);
  // consent withdraw for Austėja
  sql(`UPDATE members SET photo_consent=1 WHERE id=${I.austeja}`);
  await p.goto(`paskyra.php?skiltis=nustatymai&m=${I.austeja}`);
  await nav(p, () => p.locator('form.consent-line button').click());
  check('consent withdrawn notif', +sql(`SELECT COUNT(*) FROM notifications WHERE type='consent_withdrawn' AND member_id=${I.austeja} AND done_at IS NULL`) === 1);
  noJsErrors(p);
});

test('admin: notifications, chat, comments at the post, broadcast, events, attendance, settings', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt');
  await a.goto('admin/');
  const types = await a.locator('#svarbu .notif').evaluateAll(n => n.map(x => [...x.classList].find(c => c.startsWith('t-'))));
  check('admin has skill_request', types.includes('t-skill_request'), types.join(','));
  check('admin has late_request', types.includes('t-late_request'));
  check('admin has exam_registration', types.includes('t-exam_registration'));
  check('admin has consent', types.includes('t-consent_withdrawn'));
  check('admin no own broadcast', !types.includes('t-broadcast'));
  const xpA = +sql(`SELECT COALESCE(SUM(points),0) FROM point_awards WHERE member_id=${I.jonas}`);
  await nav(a, () => a.locator('#svarbu .notif.t-skill_request button', { hasText: 'Patvirtinti' }).first().click());
  check('skill approve xp', +sql(`SELECT COALESCE(SUM(points),0) FROM point_awards WHERE member_id=${I.jonas}`) > xpA);
  await nav(a, () => a.locator('#svarbu .notif.t-skill_request button', { hasText: 'Dar ne' }).first().click());
  check('skill reject resolved', await a.locator('#svarbu .notif.t-skill_request').count() === 0);
  // late approve (Jonas & Tomas)
  while (await a.locator('#svarbu .notif.t-late_request button', { hasText: 'Patvirtinti' }).count()) {
    await nav(a, () => a.locator('#svarbu .notif.t-late_request button', { hasText: 'Patvirtinti' }).first().click());
  }
  check('late approved -> going', sql(`SELECT status FROM event_participants WHERE event_id=${sem} AND member_id=${I.jonas}`) === 'going');
  check('late approved notif member', +sql(`SELECT COUNT(*) FROM notifications WHERE type='late_approved' AND member_id=${I.jonas}`) >= 1);
  await nav(a, () => a.locator('#svarbu .notif.t-exam_registration button').first().click());
  check('exam reg dismissed', await a.locator('#svarbu .notif.t-exam_registration').count() === 0);
  // Komentarai: pranešimas veda į įrašą; bendras - atidaro pokalbį
  check('admin has comment', types.includes('t-comment'));
  const cHref = await a.locator('#svarbu .notif.t-comment', { hasText: 'Ar bus maitinimas?' }).first().locator('a.btn', { hasText: 'Atsakyti' }).getAttribute('href');
  check('comment Atsakyti -> post', /renginys\.php\?id=\d+#q-\d+/.test(cHref), cHref);
  const gen = a.locator('#svarbu .notif.t-comment', { hasText: 'vasarą' }).first();
  check('general comment opens chat', await gen.locator('a.btn[data-open-chat]').count() === 1);
  await gen.locator('a.btn[data-open-chat]').click(); await a.waitForTimeout(1200);
  check('chat panel opened (no redirect)', await a.locator('#chatPanel').isVisible() && /\/admin\/(#svarbu)?$/.test(a.url()), a.url());
  check('chat thread shows anon tag', await a.locator('#chatPanel .chat-thread .chat-tag.anon').count() >= 1);
  check('chat title Tomas', /Tomas/.test(await a.locator('#chatPanel .chat-main .chat-title').textContent()));
  await a.locator('#chatPanel .chat-thread .chat-msg', { hasText: 'vasarą' }).locator('button', { hasText: 'Rodyti visiems' }).click(); await a.waitForTimeout(900);
  check('anon published', +sql("SELECT is_public FROM member_messages WHERE body LIKE 'TEST: Gal galėtumėte vasarą%'") === 1);
  await a.fill('#chatPanel .chat-form textarea', 'Žinoma, ateik!');
  await a.click('#chatPanel .chat-form .chat-send'); await a.waitForTimeout(1000);
  check('reply saved', +sql(`SELECT COUNT(*) FROM member_messages WHERE member_id=${I.tomas} AND from_staff=1 AND body='Žinoma, ateik!'`) === 1);
  check('reply shown in chat', await a.locator('#chatPanel .chat-msg.mine', { hasText: 'Žinoma, ateik!' }).count() === 1);
  check('tomas comment notif resolved', +sql(`SELECT COUNT(*) FROM notifications WHERE type='comment' AND member_id=${I.tomas} AND done_at IS NULL`) === 0);
  // paieška kairėje
  await a.fill('#chatPanel .chat-search', 'Austė'); await a.waitForTimeout(1200);
  const names = await a.locator('#chatPanel .chat-list .chat-person strong').allTextContents();
  check('chat search', names.length >= 1 && names.every(n => /Austė/.test(n)), names.join(','));
  await a.fill('#chatPanel .chat-search', ''); await a.waitForTimeout(600);
  await a.click('#chatPanel .chat-main .chat-close');
  // Komentaras prie renginio: atsakymas ir paviešinimas čia pat
  await a.goto(cHref);
  const th = a.locator('#q-' + I.austeja);
  check('staff sees comment thread at post', await th.count() === 1);
  check('staff sees who and when', /Austėja Petrauskaitė/.test(await th.textContent()) && /šiandien/.test(await th.textContent()));
  await th.locator('textarea').fill('Taip, maitinimas 3 kartus.');
  await nav(a, () => th.locator('button[type=submit]', { hasText: 'Atsakyti' }).click());
  check('post reply saved', +sql(`SELECT COUNT(*) FROM member_messages WHERE event_id=${camp} AND member_id=${I.austeja} AND from_staff=1`) === 1);
  check('post reply back at thread', a.url().includes('#q-' + I.austeja));
  const pubBtn = a.locator('#q-' + I.austeja + ' .q-publish button', { hasText: 'Rodyti visiems' }).first();
  if (await pubBtn.count()) { await nav(a, () => pubBtn.click()); }
  check('publish toggled', +sql(`SELECT COUNT(*) FROM member_messages WHERE event_id=${camp} AND is_public=1`) >= 2);
  const live = await a.request.get('pokalbis.php?list=1');
  check('chat list json', (await live.json()).list.length >= 2);
  // Broadcast
  const bc0 = +sql("SELECT COUNT(*) FROM notifications WHERE type='broadcast'");
  await a.goto('admin/pranesimai.php');
  await a.fill('input[name=title]', 'Testinis pranešimas');
  await a.fill('textarea[name=body]', 'Tekstas');
  await nav(a, () => a.click('button:has-text("Siųsti")'));
  const bc1 = +sql("SELECT COUNT(*) FROM notifications WHERE type='broadcast'");
  check('broadcast created', bc1 > bc0, await flashes(a));
  check('broadcast not to self', +sql("SELECT COUNT(*) FROM notifications WHERE type='broadcast' AND account_id=1") === 0);
  // broadcast validation: SMS too long
  await a.goto('admin/pranesimai.php');
  await a.fill('input[name=title]', 'Ilgas');
  await a.fill('textarea[name=body]', 'x'.repeat(700));
  await a.check('input[name="channels[]"][value=sms]');
  await nav(a, () => a.click('button:has-text("Siųsti")'));
  check('broadcast sms too long error', /SMS per ilga/.test(await a.content()));
  // Member card: send lesson, approve requirement directly
  await a.goto('admin/nariai.php?id=' + I.austeja);
  await a.selectOption('#pamokos select[name=lesson_id]', { index: 2 });
  await nav(a, () => a.click('#pamokos button[type=submit]'));
  check('card lesson sent', /Pamoka išsiųsta/.test(await flashes(a)));
  const xpAu = +sql(`SELECT COALESCE(SUM(points),0) FROM point_awards WHERE member_id=${I.austeja}`);
  const appr = a.locator('#programa button[value=approve]').first();
  if (await appr.count()) { await nav(a, () => appr.click()); check('card approve xp', +sql(`SELECT COALESCE(SUM(points),0) FROM point_awards WHERE member_id=${I.austeja}`) > xpAu); }
  // Events: create with all groups -> invites; open; validation
  await a.goto('admin/renginiai.php?edit=new');
  await a.fill('input[name=title]', 'Testas visiems'); await a.fill('#evStart', day(5)); await a.dispatchEvent('#evStart', 'change');
  check('deadline autofill', await a.inputValue('#evUntil') === day(4));
  await nav(a, () => a.click('button.btn-primary[type=submit]'));
  check('event invites all', /Pakviesta: \d+/.test(await flashes(a)), await flashes(a));
  await a.goto('admin/renginiai.php?edit=new');
  await a.fill('input[name=title]', 'Testas blogas terminas'); await a.fill('#evStart', day(5)); await a.fill('#evUntil', day(6));
  await nav(a, () => a.click('button.btn-primary[type=submit]'));
  check('deadline validation', /Registracija turi baigtis/.test(await a.content()));
  // Results -> exam passed celebration for Tomas
  const pastExam = +sql("SELECT id FROM events WHERE type='exam' AND starts_on < CURDATE() ORDER BY starts_on DESC LIMIT 1");
  await a.goto(`admin/renginio-rezultatai.php?id=${pastExam}&visi=1`);
  const radio = a.locator(`input[name="result[${I.tomas}]"][value=yes]`);
  if (await radio.count()) {
    await radio.check({ force: true });
    await nav(a, () => a.locator('button[type=submit]', { hasText: /Išsaugoti/ }).first().click());
    check('exam_passed notif', +sql(`SELECT COUNT(*) FROM notifications WHERE type='exam_passed' AND member_id=${I.tomas}`) >= 1, await flashes(a));
  } else { check('exam result radio found', false); }
  // Points settings
  await a.goto('admin/taskai.php');
  await a.fill('input[name=xp_per_level]', '50');
  await nav(a, () => a.click('form:has(.xp-setting) button[type=submit]'));
  check('xp per level saved', sql("SELECT value FROM site_settings WHERE name='xp_per_level'") === '50');
  sql("UPDATE site_settings SET value='100' WHERE name='xp_per_level'");
  // Programa XP field
  await a.goto('admin/programa.php?dirzas=5');
  const newForm = a.locator('form.req-form').last();
  await newForm.locator('input[name=title]').fill('Testinis punktas');
  await newForm.locator('input[name=xp]').fill('35');
  await nav(a, () => newForm.locator('button[type=submit]').click());
  check('requirement xp saved', sql("SELECT xp FROM belt_requirements WHERE title='Testinis punktas'") === '35');
  // Group price
  await a.goto('admin/grupes.php?id=5');
  await a.fill('input[name=price_note]', '30 €/mėn. testas');
  await nav(a, () => a.locator('form:has(input[name=price_note]) button[type=submit]').first().click());
  check('group price saved', sql('SELECT price_note FROM training_groups WHERE id=5') === '30 €/mėn. testas');
  // Training: attendance + note via Enter
  await a.goto('admin/treniruote.php?g=12&d=' + day(0));
  const order = await a.locator('.att').first().locator('.att-btn').evaluateAll(b => b.map(x => x.dataset.v).join(''));
  check('attendance Buvo on right', order === '01', order);
  await a.click('details.group-picker summary');
  const tiles = a.locator('details.group-picker .group-tile');
  check('group tiles', await tiles.count() >= 10, await tiles.count());
  const tileColors = new Set(await tiles.evaluateAll(t => t.map(x => getComputedStyle(x).getPropertyValue('--gc') || x.style.cssText)));
  check('group tiles colored', tileColors.size >= 6, [...tileColors].slice(0, 3).join(' | '));
  await a.click('details.group-picker summary');
  const firstBox = a.locator('.att').first();
  const mid = await firstBox.getAttribute('data-member');
  await firstBox.locator('.att-btn.yes').click(); await a.waitForTimeout(700);
  check('attendance saved', sql(`SELECT present FROM attendance WHERE member_id=${mid} AND training_date='${day(0)}'`) === '1');
  await a.locator(`textarea[data-member="${mid}"]`).fill('Šaunu šiandien!');
  await a.locator(`textarea[data-member="${mid}"]`).press('Enter'); await a.waitForTimeout(800);
  check('note saved via enter', +sql(`SELECT COUNT(*) FROM coach_notes WHERE member_id=${mid} AND body='Šaunu šiandien!'`) === 1);
  // Apžvalga: kairėje darbai, dešinėje nustatymai; nustatymų skirtukai sujungti
  await a.goto('admin/');
  check('dash two columns', await a.locator('.dash .dash-main #svarbu').count() === 1 && await a.locator('.dash aside#nustatymai').count() === 1);
  const tabs = await a.locator('.sub-nav a').allTextContents();
  check('admin tabs merged', tabs.some(t => /Nustatymai/.test(t)) && !tabs.some(t => /^\s*Grupės\s*$/.test(t)), tabs.join(','));
  check('dash settings links', await a.locator('#nustatymai a[href*="grupes.php"]').count() === 1 && await a.locator('#nustatymai #paskyra').count() === 1);
  // Reakcijos: diržų spalvos ir teisėjo vėliavėlės
  await a.goto('admin/naujienos.php?emoji=1');
  check('belt reactions in settings', /Diržai ir teisėjai/.test(await a.content()));
  // news consent list
  await a.goto('admin/naujienos.php?edit=new');
  check('consent list admin', /Nesutinka/.test(await a.locator('.consent-list').textContent()));
  noJsErrors(a);
});

test('coach: limited rights', async ({ browser }) => {
  const c = await login(browser, 'treneris@karateka.test');
  await c.goto('admin/');
  check('coach dashboard ok', (await c.title()).length > 0);
  const r2 = await c.goto('admin/pranesimai.php');
  check('coach no broadcast page', r2.status() === 403, r2.status());
  const r3 = await c.request.post('admin/renginio-dalyviai.php?id=' + camp, { form: { _csrf: 'x', member_id: String(I.jonas), action: 'going' } });
  check('coach cannot change participants', r3.status() >= 400, r3.status());
  await c.goto('admin/nariai.php?id=' + I.jonas);
  check('coach sees card program', await c.locator('#programa').count() === 1);
  await c.goto('admin/taskai.php');
  check('coach xp setting disabled', await c.locator('input[name=xp_per_level][disabled]').count() === 1);
  await c.goto('renginys.php?id=' + camp);
  check('coach sees counts not rsvp', /Dalyvauja/.test(await c.locator('.ep-reg').textContent()));
  noJsErrors(c);
});

test('member: exam celebration and reply from Denis', async ({ browser }) => {
  const t = await login(browser, 'narys@karateka.test');
  await t.goto('paskyra.php');
  check('exam passed celebration', await t.locator('#celebrate .celebrate-kicker', { hasText: 'Egzaminas' }).count() === 1);
  check('tomas sees Denis reply badge', await t.locator('#chatFab .count-badge:not([hidden])').count() === 1);
  if (await t.locator('#celebrate').count()) { await t.click('#celebrate button'); await t.waitForTimeout(400); }
  check('tomas svarbu reply', await t.locator('#svarbu .notif.t-reply', { hasText: 'Žinoma, ateik' }).count() === 1);
  await t.locator('#svarbu .notif.t-reply button[data-open-chat]').click(); await t.waitForTimeout(900);
  check('atsakyti opens chat', await t.locator('#chatPanel').isVisible());
  await t.reload();
  check('chat open marks read', await t.locator('#svarbu .notif.t-reply').count() === 0);
  noJsErrors(t);
});

test('guest: event page, menu, contacts, instructors', async ({ browser }) => {
  const t = await login(browser, 'narys@karateka.test');
  const g = await login(browser, null);
  await g.goto('renginys.php?id=' + camp);
  check('guest login prompt', /Prisijunkite/.test(await g.locator('.ep-reg').textContent()));
  check('guest no chat', await g.locator('#chatFab').count() === 0);
  const r4 = await g.request.get('pokalbis.php?m=' + I.jonas);
  check('guest chat 403', r4.status() === 403);
  await g.goto('index.php');
  check('guest trial button', await g.locator('.site-header .page-section .nav-cta').count() === 1);
  // Kontaktai: visa sekcija matoma, blokai vienodo aukščio
  await g.click('.site-header a[href$="#kontaktai"]'); await g.waitForTimeout(1200);
  const top = await g.evaluate(() => document.getElementById('kontaktai').getBoundingClientRect().top);
  const hdrH = await g.evaluate(() => document.querySelector('.site-header').getBoundingClientRect().bottom);
  check('kontaktai not cut', top >= hdrH - 2 && top < hdrH + 80, `top ${top} header ${hdrH}`);
  const hs = await g.locator('#kontaktai .contacts-grid > .panel').evaluateAll(c => c.map(x => Math.round(x.getBoundingClientRect().height)));
  check('kontaktai equal blocks', hs.length >= 2 && Math.max(...hs) - Math.min(...hs) <= 2, hs.join(','));
  // Instruktorius: paspaudus - langas su daugiau informacijos
  await g.goto('index.php');
  await g.locator('[data-instructor]').first().click(); await g.waitForTimeout(500);
  check('instructor popup', await g.locator('.ins-modal:visible, dialog[open]').count() >= 1);
  // Be mygtuko „Išbandyk“ neturi likti tuščios vietos: tarpas tarp meniu dalių toks pat
  const gap = pg => pg.evaluate(() => { const pill = document.querySelector('.onpage-section > ul').getBoundingClientRect(); const next = document.querySelector('.site-header .page-section').getBoundingClientRect(); return Math.round(next.left - pill.right); });
  const gGap = await gap(g);
  await t.goto('index.php');
  check('no empty space without trial button', await t.locator('.site-header .nav-cta').count() === 0 && Math.abs(gGap - await gap(t)) <= 6, gGap + ' vs ' + await gap(t));
  await g.goto('index.php');
  check('guest main page price', /30 €\/mėn\. testas|45 €/.test(await g.content()));
  noJsErrors(g, t);
});
