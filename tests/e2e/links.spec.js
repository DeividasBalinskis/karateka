// C3: visos slaptos nuorodos - vienas modelis (app/links.php, db/062 access_links) ir vienas puslapis („Visos nuorodos“).
// Kiekviena rūšis: veikia, neteisinga, išjungta, pakeista nauja, pasibaigusi. Senos nuorodos (iki C3) veikia toliau.
// Rašančios nuorodos klausia vardo, o vardas įrašomas su pakeitimais (Budo istorija, apdovanojimai).
const { test } = require('@playwright/test');
const { sql, linkToken, linkNew, day, login, nav, check, noJsErrors, BASE_URL } = require('./helpers');

const DEAD = /Nuoroda neveikia/;
const hashOf = tok => `SHA2('${tok}', 256)`;

/** Bandomieji savininkai: varžybos, egzaminas su protokolo eilute, testas */
function owners() {
  sql("DELETE FROM tournaments WHERE title = 'E2E nuorodos'");
  sql(`INSERT INTO tournaments (title, starts_on, is_test) VALUES ('E2E nuorodos', '${day(3)}', 1)`);
  const tid = +sql("SELECT id FROM tournaments WHERE title = 'E2E nuorodos'");
  sql("DELETE FROM events WHERE title = 'E2E nuorodų egzaminas'");
  sql(`INSERT INTO events (type, title, starts_on, start_time) VALUES ('exam', 'E2E nuorodų egzaminas', '${day(10)}', '10:00')`);
  const eid = +sql("SELECT id FROM events WHERE title = 'E2E nuorodų egzaminas'");
  sql(`INSERT INTO exam_protocol_rows (event_id, number, num_date, seq, name, birth_date) VALUES (${eid}, 'E2E-L1', CURDATE(), 981, 'Nuoroda Testinė', '2013-03-03')`);
  const rid = +sql(`SELECT id FROM exam_protocol_rows WHERE event_id = ${eid} AND number = 'E2E-L1'`);
  sql("DELETE FROM quizzes WHERE title = 'E2E nuorodų testas'");
  sql("INSERT INTO quizzes (title, audience, question_count, pass_percent, is_published, share_open) VALUES ('E2E nuorodų testas', 'karateka', 1, 50, 1, 1)");
  const qz = +sql("SELECT id FROM quizzes WHERE title = 'E2E nuorodų testas'");
  return { tid, eid, rid, qz };
}

// Rūšis → [savininkas, adresas su raktu, kaip atrodo veikianti (statusas)]
function kinds(o) {
  return [
    ['sekr', o.tid, k => `varzybos-sekretoriatas.php?k=${k}`, 200],
    ['awards', o.tid, k => `varzybos-apdovanojimai.php?k=${k}`, 200],
    ['live', o.tid, k => `varzybos-gyvai.php?k=${k}&json=1`, 200],
    ['reg', o.tid, k => `varzybos-registracija.php?k=${k}`, 200],
    ['demo', null, k => `varzybos-bandymas.php?k=${k}`, 200],
    ['cam', null, k => `varzybos-kamera.php?k=${k}`, 200],
    ['exam_signup', o.eid, k => `e/?${k}`, 200],
    ['exam_waitlist', null, k => `e/?${k}`, 200],
    ['exam_row', o.rid, k => `mano-egzaminas.php?k=${k}`, 200],
    ['quiz', o.qz, k => `testas-svecias.php?k=${k}`, 200],
    ['just', null, k => `p/?${k}`, 200],
    ['ltkf_editor', null, k => `ltkf-redaktorius.php?k=${k}`, 200],
    ['ltkf_review', null, k => `ltkf-perziura.php?k=${k}`, 302],
  ];
}

test('every link kind: works, wrong, switched off, replaced, expired', async ({ browser }) => {
  sql("DELETE FROM login_attempts WHERE email = '#link'");
  const o = owners();
  sql(`INSERT IGNORE INTO exam_signup_links (event_id, token, is_open) VALUES (${o.eid}, '#${o.eid}', 1)`);
  const g = await browser.newContext({ baseURL: BASE_URL });
  const get = url => g.request.get(url, { maxRedirects: 0 });
  for (const [kind, owner, url, okStatus] of kinds(o)) {
    const tok = linkNew(kind, owner, kind === 'ltkf_editor' ? 'E2E redaktorius' : '');
    check(`${kind}: long random key or short code`, kind === 'exam_signup' || kind === 'exam_waitlist' || kind === 'just' ? /^[a-z2-9]{6}$/.test(tok) : /^[a-f0-9]{64}$/.test(tok), tok);
    check(`${kind}: only the fingerprint is stored`, sql(`SELECT COUNT(*) FROM access_links WHERE token_hash = ${hashOf(tok)} AND token_lock NOT LIKE '%${tok}%'`) === '1');

    // Veikia (ir įrašoma, kada naudota)
    let r = await get(url(tok));
    check(`${kind}: works`, r.status() === okStatus, String(r.status()));
    check(`${kind}: last use recorded`, sql(`SELECT last_used_at IS NOT NULL FROM access_links WHERE token_hash = ${hashOf(tok)}`) === '1');

    // Neteisinga
    const wrong = tok.length === 6 ? 'zzzz99' : 'f'.repeat(64);
    r = await get(url(wrong));
    check(`${kind}: wrong key → the shared „does not work“ page`, r.status() === 404 && (kind === 'live' ? (await r.json()).ok === false : DEAD.test(await r.text())), String(r.status()));

    // Pakeista nauja: senoji neveikia, naujoji veikia
    const tok2 = linkNew(kind, owner, kind === 'ltkf_editor' ? 'E2E redaktorius 2' : '');
    if (kind !== 'ltkf_editor') {   // redaktorių gali būti keli - naujas nepakeičia seno
      r = await get(url(tok));
      check(`${kind}: replaced - the old one stops`, r.status() === 404, String(r.status()));
      check(`${kind}: marked as replaced`, sql(`SELECT replaced_by IS NOT NULL FROM access_links WHERE token_hash = ${hashOf(tok)}`) === '1');
    }
    r = await get(url(tok2));
    check(`${kind}: the new one works`, r.status() === okStatus, String(r.status()));

    // Pasibaigusi
    sql(`UPDATE access_links SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE token_hash = ${hashOf(tok2)}`);
    r = await get(url(tok2));
    check(`${kind}: expired`, r.status() === 404, String(r.status()));
    sql(`UPDATE access_links SET expires_at = NULL WHERE token_hash = ${hashOf(tok2)}`);

    // Išjungta
    sql(`UPDATE access_links SET revoked_at = NOW() WHERE token_hash = ${hashOf(tok2)}`);
    r = await get(url(tok2));
    check(`${kind}: switched off`, r.status() === 404 && (kind === 'live' ? true : DEAD.test(await r.text())), String(r.status()));
  }

  // Sekretoriato nuoroda veikia iki 2 dienų po varžybų (sprendimas v1.52): po to - pasibaigusi
  const sk = linkNew('sekr', o.tid);
  sql(`UPDATE tournaments SET starts_on = '${day(-2)}', ends_on = NULL WHERE id = ${o.tid}`);
  check('secretariat: still works 2 days after', (await get(`varzybos-sekretoriatas.php?k=${sk}`)).status() === 200);
  sql(`UPDATE tournaments SET starts_on = '${day(-3)}' WHERE id = ${o.tid}`);
  check('secretariat: expired on day 3', (await get(`varzybos-sekretoriatas.php?k=${sk}`)).status() === 404);
  // Vieną nuorodą varžyboms galima atidaryti su &t=N (tatamio užuomina) - ta pati nuoroda
  sql(`UPDATE tournaments SET starts_on = '${day(3)}' WHERE id = ${o.tid}`);
  check('secretariat: &t=N is only a hint on the same link', (await get(`varzybos-sekretoriatas.php?k=${sk}&t=2`)).status() === 200);

  // Neteisingi bandymai skaičiuojami duomenų bazėje pagal IP
  check('wrong guesses counted per IP in the database', +sql("SELECT COUNT(*) FROM login_attempts WHERE email = '#link' AND success = 0") >= 13);
  sql("DELETE FROM login_attempts WHERE email = '#link'");
  await g.close();
});

test('old links (before C3) keep working and move to the new table; nothing old is deleted', async ({ browser }) => {
  sql("DELETE FROM login_attempts WHERE email = '#link'");
  const o = owners();
  const g = await browser.newContext({ baseURL: BASE_URL });
  const get = url => g.request.get(url, { maxRedirects: 0 });
  const old = 'c3e2e0000000000000000001';
  sql(`DELETE FROM access_links WHERE token_hash IN (${hashOf(old)}, ${hashOf('c3e2ek')})`);
  sql(`UPDATE tournaments SET share_token = '${old}' WHERE id = ${o.tid}`);
  sql("REPLACE INTO site_settings (name, value) VALUES ('just_token', 'c3e2ek')");
  check('old spectator link works', (await get(`varzybos-gyvai.php?k=${old}&json=1`)).status() === 200);
  check('old excuse code works', (await get('p/?c3e2ek')).status() === 200);
  check('moved to the table (marked legacy)', sql(`SELECT COUNT(*) FROM access_links WHERE legacy = 1 AND token_hash IN (${hashOf(old)}, ${hashOf('c3e2ek')})`) === '2');
  check('old values are not deleted', sql(`SELECT share_token FROM tournaments WHERE id = ${o.tid}`) === old && sql("SELECT value FROM site_settings WHERE name = 'just_token'") === 'c3e2ek');
  check('the moved link can be copied again', linkToken('live', o.tid) === old);
  // Pakeitus - senoji nebeveikia, nors sena reikšmė stulpelyje liko
  linkNew('live', o.tid);
  check('replaced old link stops working', (await get(`varzybos-gyvai.php?k=${old}&json=1`)).status() === 404);
  sql("DELETE FROM site_settings WHERE name = 'just_token'");
  sql("UPDATE access_links SET revoked_at = NOW() WHERE kind = 'just' AND revoked_at IS NULL");
  await g.close();
});

test('„Visos nuorodos“: every link with Kopijuoti · ↻ Nauja nuoroda · Išjungti, created and last used', async ({ browser }) => {
  sql('DELETE FROM tournaments WHERE is_test = 1');
  sql("UPDATE access_links SET revoked_at = NOW() WHERE kind IN ('demo', 'ltkf_review') AND revoked_at IS NULL");
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const o = { tid: +sql('SELECT MAX(id) FROM tournaments') };
  const title = sql(`SELECT title FROM tournaments WHERE id = ${o.tid}`);
  const live = linkNew('live', o.tid);
  await a.goto('admin/');
  check('in the Valdymas menu', await a.locator('a[href$="admin/nuorodos.php"]').count() >= 1);
  await a.goto('admin/nuorodos.php');
  check('page title', /Visos nuorodos/.test(await a.textContent('.page-head h1')));
  const liveId = sql(`SELECT id FROM access_links WHERE token_hash = ${hashOf(live)}`);
  const box = a.locator(`.lk[data-link-id="${liveId}"]`);
  check('spectator link listed with its competition', (await a.locator(`#k-live .lk-item:has(.lk[data-link-id="${liveId}"])`).innerText()).includes(title));
  check('three buttons', await box.locator('[data-link-copy]').count() === 1 && await box.locator('[data-lk="renew"]').count() === 1 && await box.locator('[data-lk="off"]').count() === 1);
  check('created and used shown', /sukurta .*dar nenaudota/.test(await box.locator('.lk-meta').innerText()));
  check('the copy is the real link', (await box.locator('.lk-url').inputValue()).endsWith(`varzybos-gyvai.php?k=${live}`));

  // ↻ Nauja nuoroda
  await nav(a, () => box.locator('[data-lk="renew"]').click());
  const live2 = linkToken('live', o.tid);
  check('↻ gives a new link, the old one is replaced', live2 !== live && sql(`SELECT replaced_by IS NOT NULL FROM access_links WHERE token_hash = ${hashOf(live)}`) === '1');
  // Išjungti
  const id2 = sql(`SELECT id FROM access_links WHERE token_hash = ${hashOf(live2)}`);
  await nav(a, () => a.locator(`.lk[data-link-id="${id2}"] [data-lk="off"]`).click());
  check('switched off', sql(`SELECT revoked_at IS NOT NULL AND replaced_by IS NULL FROM access_links WHERE id = ${id2}`) === '1');
  check('a switched-off spectator link is not made again by itself', linkToken('live', o.tid) === '');
  await a.goto(`admin/varzybu-sistema.php?id=${o.tid}&share=1`);
  check('competition page offers „Sukurti nuorodą“', await a.locator('#share-panel [data-link-kind="live"] [data-lk="new"]').count() === 1);
  await nav(a, () => a.click('#share-panel [data-link-kind="live"] [data-lk="new"]'));
  check('created again with a button', linkToken('live', o.tid) !== '' && /share=1/.test(a.url()));

  // Bendros nuorodos sukuriamos čia pat
  await a.goto('admin/nuorodos.php');
  await nav(a, () => a.click('#k-demo [data-lk="new"]'));
  check('trial link created from the page', /^[a-f0-9]{64}$/.test(linkToken('demo')));
  await a.goto('admin/nuorodos.php?visos=1');
  check('switched off and replaced shown on „Rodyti ir išjungtas“', /išjungta/.test(await a.locator('#k-live').textContent()));   // daugiau nei 8 - suskleista
  // Treneris šio puslapio nemato
  const c = await login(browser, 'treneris@karateka.test', 1280, 900);
  await c.goto('admin/nuorodos.php');
  check('coach: no access', await c.locator('.lk').count() === 0);
  sql("UPDATE access_links SET revoked_at = NOW() WHERE kind = 'demo' AND revoked_at IS NULL");
  noJsErrors(a);
});

test('write links ask for a name; the name is saved with each change (Budo history, awards); „Baigti darbą“', async ({ browser }) => {
  sql('DELETE FROM tournaments WHERE is_test = 1');
  const a = await login(browser, 'info@karateka.lt', 1400, 1000);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  const sk = linkNew('sekr', tid);
  await a.goto('admin/');   // trenerio Budo langas uždaromas - kad jo išsaugojimai nepatektų į tą pačią istorijos kopiją

  const ctx = await browser.newContext({ baseURL: BASE_URL, viewport: { width: 1400, height: 1000 } });
  const s = await ctx.newPage();
  s.jsErrors = []; s.on('pageerror', e => s.jsErrors.push(e.message)); s.on('dialog', d => d.accept());
  await s.goto(`varzybos-sekretoriatas.php?k=${sk}`);
  await s.fill('input[name="name"]', 'Tomas Sekretorius');
  await nav(s, () => s.click('button[type="submit"]'));
  check('secretariat in its competition', new RegExp(`varzybu-sistema\\.php\\?id=${tid}`).test(s.url()));
  check('secretariat cannot restore history', await s.locator('#hist-toggle').count() === 0);
  const rs = await s.request.post(`admin/varzybu-sistema.php?id=${tid}&restore=1`, { data: { id: 1 }, headers: { 'X-CSRF-Token': await s.evaluate(() => window.VARZ.csrf) } });
  check('restore refused on the server too', rs.status() === 403, String(rs.status()));
  // Kad kopija atsirastų iškart (kopija - ne dažniau kaip kas minutę)
  sql(`UPDATE tournament_versions SET created_at = NOW() - INTERVAL 5 MINUTE WHERE tournament_id = ${tid}`);
  const v0 = +sql(`SELECT version FROM tournaments WHERE id = ${tid}`);
  await s.click('#tab-live');
  await s.waitForTimeout(600);
  if (!(await s.locator('#run').isVisible())) await s.locator('#sched [data-open-cat]').first().click();
  await s.click('#draw-sim');
  for (let i = 0; i < 20 && +sql(`SELECT version FROM tournaments WHERE id = ${tid}`) <= v0; i++) await s.waitForTimeout(300);
  await s.waitForTimeout(800);
  check('saved version has the name', +sql(`SELECT COUNT(*) FROM tournament_versions WHERE tournament_id = ${tid} AND saved_by LIKE '%Tomas Sekretorius%'`) >= 1,
    sql(`SELECT GROUP_CONCAT(CONCAT(id, ':', IFNULL(saved_by, '-'), '@', created_at) SEPARATOR ' | ') FROM tournament_versions WHERE tournament_id = ${tid}`) + ' / pending ' + sql(`SELECT IFNULL(saved_by, '-') FROM tournaments WHERE id = ${tid}`));

  // Treneris istorijoje mato vardą prie kopijos
  sql(`UPDATE tournament_versions SET created_at = NOW() - INTERVAL 5 MINUTE WHERE tournament_id = ${tid}`);
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  const saved = await a.evaluate(async tid => (await fetch(`varzybu-sistema.php?id=${tid}&history=1`, { credentials: 'same-origin' }).then(r => r.json())).list.map(x => x.by), tid);
  check('history API gives the name', saved.some(b => /Tomas Sekretorius/.test(b)), JSON.stringify(saved));
  await a.click('#hist-toggle');
  await a.waitForSelector('#hist-list .hist-table');
  check('history shows a name next to the saved version', /👤 Tomas Sekretorius/.test(await a.locator('#hist-list').innerText()));

  // „Baigti darbą“ - šio įrenginio prieiga baigiasi, nuoroda veikia toliau
  await s.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await nav(s, () => s.click('#sekr-end'));
  check('work finished page', /Darbas baigtas/.test(await s.textContent('main')));
  await s.goto(`admin/varzybu-sistema.php?id=${tid}`);
  check('after „Baigti darbą“ the session is gone', /prisijungti\.php/.test(s.url()), s.url());
  check('the link itself still works', (await s.request.get(`varzybos-sekretoriatas.php?k=${sk}`)).status() === 200);

  // Apdovanojimų planšetė: vardas pirmiausia, be vardo keisti negalima
  const aw = linkNew('awards', tid);
  const p = await ctx.newPage();
  await p.goto(`varzybos-apdovanojimai.php?k=${aw}`);
  check('awards tablet asks for a name first', await p.locator('#apd-name input[name=name]').count() === 1);
  // su sesijos žetonu (C1: be jo - 400), kad būtų tikrinamas būtent vardas
  const tok = await p.inputValue('#apd-name input[name=_csrf]');
  const no = await p.request.post(`varzybos-apdovanojimai.php?k=${aw}`, { data: { cats: ['x'], status: 'done' }, headers: { 'X-CSRF-Token': tok } });
  check('no changes without a name', no.status() === 403, String(no.status()));
  await p.fill('#apd-name input[name=name]', 'Ona');
  await nav(p, () => p.click('#apd-name button[type=submit]'));
  check('tablet shows who hands out awards', /Ona/.test(await p.textContent('.vz-top-right')));
  await ctx.close();
  sql('DELETE FROM tournaments WHERE is_test = 1');
  noJsErrors(a);
});

test.afterAll(() => {
  sql("DELETE FROM tournaments WHERE title = 'E2E nuorodos'");
  sql("DELETE FROM exam_protocol_rows WHERE number = 'E2E-L1'");
  sql("DELETE FROM events WHERE title = 'E2E nuorodų egzaminas'");
  sql("DELETE FROM quizzes WHERE title = 'E2E nuorodų testas'");
  sql("DELETE FROM login_attempts WHERE email = '#link'");
});
