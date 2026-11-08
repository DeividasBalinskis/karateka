// Atsiliepimai (mygtukas visuose puslapiuose, admino sąrašas) ir varžybų sistema svetainėje:
// test varžybos (Karateka nariai + kiti klubai, automatinės kategorijos, dalis kovų), išsaugojimas, viešas puslapis, LTKF, rezultatai → taškai.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, flashes } = require('./helpers');

test('feedback: visitor and member write, admin answers', async ({ browser }) => {
  sql('DELETE FROM feedback');
  sql("DELETE FROM site_settings WHERE name = 'feedback_button'");

  // Lankytojas (test svetainėje mygtukas rodomas visiems)
  const v = await login(browser, null);
  await v.goto('renginiai.php');
  await v.click('#fbFab');
  await v.fill('#fbPanel textarea[name=body]', 'Renginių puslapyje trūksta filtro');
  await v.fill('#fbPanel input[name=name]', 'Lankytojas Jonas');
  await v.click('#fbPanel button[type=submit]');
  await v.waitForSelector('#fbPanel .fb-msg:not([hidden])');
  check('visitor thanked', /Ačiū/.test(await v.locator('#fbPanel .fb-msg').textContent()));
  check('visitor stored with page', sql("SELECT CONCAT(page, '|', name) FROM feedback WHERE account_id IS NULL") === '/renginiai.php|Lankytojas Jonas');

  // Narys mato savo atsiliepimą lange
  const m = await login(browser, 'narys@karateka.test', 390, 800);
  await m.goto('paskyra.php');
  await m.click('#fbFab');
  await m.fill('#fbPanel textarea[name=body]', 'Telefone mygtukas per mažas');
  await m.click('#fbPanel button[type=submit]');
  await m.waitForSelector('#fbPanel .fb-list .fb-item');
  check('member sees own feedback', /Telefone mygtukas per mažas/.test(await m.locator('#fbPanel .fb-list').textContent()));

  // Adminas: skaičiukas, sąrašas, atsakymas
  const a = await login(browser, 'info@karateka.lt');
  await a.goto('admin/');
  check('nav badge', (await a.locator('.sub-nav a[href*="atsiliepimai.php"]').textContent()).includes('2'));
  check('todo line', /Nauji atsiliepimai apie svetainę: 2/.test(await a.locator('body').textContent()));
  await a.goto('admin/atsiliepimai.php');
  check('both listed', await a.locator('.fb-card').count() === 2);
  check('copy button', await a.locator('#fbCopy').count() === 1);
  const card = a.locator('.fb-card', { hasText: 'Telefone mygtukas' });
  await card.locator('select[name=status]').selectOption('done');
  await card.locator('textarea[name=reply]').fill('Padidinome, ačiū!');
  await nav(a, () => card.locator('button[value=save]').click());
  check('saved', /Išsaugota/.test(await flashes(a)));
  check('member notified', +sql("SELECT COUNT(*) FROM notifications n JOIN accounts a ON a.id = n.account_id WHERE a.email = 'narys@karateka.test' AND n.type = 'feedback'") === 1);
  await m.goto('paskyra.php');
  await m.click('#fbFab');
  check('member sees reply', /Padidinome, ačiū!/.test(await m.locator('#fbPanel .fb-list').textContent()));

  // Išjungus - mygtuko nėra
  await a.locator('.fb-mode select').selectOption('members');
  await nav(a, () => a.click('.fb-mode button'));
  await v.goto('index.php');
  check('hidden for visitors when members-only', await v.locator('#fbFab').count() === 0);
  await m.goto('paskyra.php');
  check('still for members', await m.locator('#fbFab').count() === 1);
  sql("DELETE FROM site_settings WHERE name = 'feedback_button'");
  noJsErrors(v, m, a);
});

test('competitions: test tournament, editor saves, public results, LTKF, points', async ({ browser }) => {
  sql('DELETE FROM tournaments');
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/varzybos.php');
  check('empty state', await a.locator('.varz-empty').count() === 1);

  // Test varžybos: kategorijos ir dalis kovų atsiranda atidarius, išsaugoma serveryje
  await nav(a, () => a.click('button:has-text("Sukurti test varžybas")'));
  check('editor opened', a.url().includes('varzybu-sistema.php'));
  await a.waitForFunction(() => /Išsaugota/.test(document.getElementById('save-state').textContent) && +document.querySelectorAll('#draw-cat option').length > 3);
  await a.waitForTimeout(400);
  const res = JSON.parse(sql('SELECT results FROM tournaments ORDER BY id DESC LIMIT 1'));
  check('results saved with medals', res.medals.length > 2 && res.categories.length > 5, JSON.stringify(res).slice(0, 200));
  check('some categories finished', res.categories.filter(c => c.finished).length > 0);
  const state = JSON.parse(sql('SELECT state FROM tournaments ORDER BY id DESC LIMIT 1'));
  check('auto flags consumed', !state.autoCats && !state.simulate);
  check('karateka members included', Object.values(state.people).some(p => p.member && p.club === 'Karateka'));
  check('other clubs included', new Set(Object.values(state.people).map(p => p.club)).size >= 5);
  check('no duplicate home club', !Object.values(state.people).some(p => p.club === 'Karate Ateitis'));

  // Valdymas: skirtukai veikia, test mygtukas užbaigia visas kovas
  for (const tab of ['dalyviai', 'klubai', 'komandos', 'kategorijos', 'tvarkarastis', 'rezultatai']) {
    await a.click('#tab-' + tab);
    check('tab ' + tab, await a.locator('#v-' + tab).isVisible());
  }
  await a.click('#res-sim');
  await a.waitForFunction(() => /Išsaugota/.test(document.getElementById('save-state').textContent));
  await a.waitForTimeout(1200);
  const all = JSON.parse(sql('SELECT results FROM tournaments ORDER BY id DESC LIMIT 1'));
  check('all finished after simulate', all.categories.filter(c => c.count >= 2).every(c => c.finished));

  // Kategorija: sistemos pasirinkimas kortelėmis (ratų, balų), tempimas iš dalyvių sąrašo
  await a.click('#tab-kategorijos');
  check('system cards', await a.locator('.sys-card').count() === 6);   // olimpinės (3), fukugo, ratų, balai
  const catId = await a.locator('#cat-list li[aria-current="true"]').getAttribute('data-drop-cat');
  await a.click('.sys-card[data-sys="rr"]');
  await a.waitForTimeout(1200);
  check('system changed to round robin', JSON.parse(sql('SELECT state FROM tournaments ORDER BY id DESC LIMIT 1')).categories.find(c => c.id === catId).draw.system === 'rr');
  await a.click('[data-pool="all"]');
  const rowsBefore = await a.locator('#cat-detail tbody tr').count();
  await a.locator('.pool-item:has(.pi-add)').first().dragTo(a.locator('#cat-detail .drop-zone'));
  check('drag from pool adds', await a.locator('#cat-detail tbody tr').count() === rowsBefore + 1);
  await a.click('.sys-card[data-sys="score"]');
  await a.click('#cat-draw');
  check('score table', await a.locator('.score-table .j-in').count() >= 5);
  const first = a.locator('.score-table tbody tr').first().locator('.j-in');
  for (let j = 0; j < await first.count(); j++) await first.nth(j).fill('8.' + j);
  await first.last().press('Tab');
  check('score total shown', /\d+\.\d/.test(await a.locator('.score-table tbody tr').first().locator('td').nth(-2).textContent()));

  // Kategorijos sudaromos iš naujo mygtuku; dalyvio diržo keitimas išsaugomas
  await a.click('#tab-dalyviai');
  const pid = await a.locator('[data-p-belt]').first().getAttribute('data-p-belt');
  await a.locator(`[data-p-belt="${pid}"]`).selectOption('12');
  await a.waitForTimeout(1500);
  check('belt saved', JSON.parse(sql('SELECT state FROM tournaments ORDER BY id DESC LIMIT 1')).people[pid].belt === 12);

  // Konfliktas: kitas žmogus išsaugojo - perrašyti neleidžiama
  sql('UPDATE tournaments SET version = version + 5');
  await a.locator(`[data-p-belt="${pid}"]`).selectOption('11');
  await a.waitForSelector('#conflict:not([hidden])');
  check('conflict shown', /perkraukite/.test(await a.locator('#save-state').textContent()));

  // Viešas puslapis ir LTKF
  const tid = sql('SELECT id FROM tournaments ORDER BY id DESC LIMIT 1');
  const v = await login(browser, null);
  await v.goto(`varzybu-rezultatai.php?id=${tid}`);
  check('public medals', await v.locator('table tbody tr').count() > 2);
  check('public podiums', await v.locator('.res-cat').count() > 5);
  await v.goto('varzybu-rezultatai.php');
  check('public list', await v.locator('.pub-item').count() === 1);
  await v.goto('ltkf/rezultatai.php');
  check('ltkf results from system', /Iš varžybų sistemos/.test(await v.locator('.results-head').textContent()));
  await v.goto('ltkf/');
  check('ltkf latest results', await v.locator('.latest-podium li').count() >= 3);
  await v.goto('ltkf/kalendorius.php');
  check('ltkf calendar has tournament', /Test varžybos/.test(await v.locator('body').textContent()));
  await v.goto('ltkf/klubai.php');
  check('karateka club live data', /gyvai iš karateka.lt/.test(await v.locator('.club-list').textContent()));
  await v.goto('index.php');
  check('footer links ltkf and results', await v.locator('footer a[href*="ltkf/"]').count() === 1 && await v.locator('footer a[href*="varzybu-rezultatai"]').count() === 1);

  // Tikros varžybos su renginiu; Karateka nariai per sąrašą; rezultatai → taškai
  await a.goto('admin/varzybos.php');
  await a.click('#nauja summary');
  await a.fill('#nauja input[name=title]', 'Karateka taurė');
  await a.fill('#nauja input[name=starts_on]', '2026-12-12');
  await nav(a, () => a.click('#nauja button[type=submit]'));
  check('real tournament + event', +sql("SELECT COUNT(*) FROM tournaments t JOIN events e ON e.id = t.event_id WHERE t.title = 'Karateka taurė' AND t.is_test = 0 AND e.type = 'competition'") === 1);
  await a.click('#ppl-members');
  await a.click('#mp-all');
  await a.click('#mp-add');
  await a.waitForFunction(() => /Išsaugota/.test(document.getElementById('save-state').textContent));
  await a.waitForTimeout(1200);
  const real = JSON.parse(sql("SELECT state FROM tournaments WHERE title = 'Karateka taurė'"));
  const members = Object.values(real.people).filter(p => p.member);
  check('members imported', members.length >= 3 && members.every(p => p.club === 'Karateka' && p.birth));
  check('real tournament has no test buttons', await a.locator('#res-sim').count() === 0);
  const [m1, m2] = members.map(p => p.member);
  sql(`UPDATE tournaments SET results = '${JSON.stringify({ categories: [{ id: 'k1', name: 'Kata', count: 2, finished: true, places: [{ place: 1, name: 'A', club: 'Karateka', members: [m1] }, { place: 2, name: 'B', club: 'Karateka', members: [m2] }] }], medals: [{ club: 'Karateka', g: 1, s: 1, b: 0, pts: 5 }] })}' WHERE title = 'Karateka taurė'`);
  await a.goto('admin/varzybos.php');
  const realCard = a.locator('.varz-card', { hasText: 'Karateka taurė' });
  await realCard.locator('.varz-more summary').click();
  await nav(a, () => realCard.locator('button:has-text("narių taškai")').click());
  check('points flash', /Rezultatai perkelti/.test(await flashes(a)), await flashes(a));
  const ev = sql("SELECT event_id FROM tournaments WHERE title = 'Karateka taurė'");
  check('place 1 awarded', +sql(`SELECT COUNT(*) FROM point_awards pa JOIN point_categories pc ON pc.id = pa.category_id WHERE pa.event_id = ${ev} AND pa.member_id = ${m1} AND pc.code LIKE 'place1_%'`) === 1);
  check('participants awarded', +sql(`SELECT COUNT(DISTINCT member_id) FROM point_awards WHERE event_id = ${ev}`) === members.length);

  // Test varžybų trynimas - tikros lieka
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('.varz-test-del button'));
  check('test deleted, real kept', +sql('SELECT COUNT(*) FROM tournaments WHERE is_test = 1') === 0 && +sql('SELECT COUNT(*) FROM tournaments WHERE is_test = 0') === 1);
  noJsErrors(a, v);
});
