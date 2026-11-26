// v1.97-v1.99: Transliacija be OBS - telefonai jungiasi per vieną QR, kompiuteryje priskiriami tatamiui, telefone „Pradėti filmuoti“ (vdo.ninja + Meshcast);
// tatamių kortelės su vaizdu ir juosta, žiūrovams „📹 Žiūrėti gyvai“ su ⛶ per visą ekraną ir 💡.
// v2.12: ± (pavyzdinė kova) ir sekretoriato vaizdas („📹 Vaizdas“, „📹 Live kova“) ištrinti; juosta be kovos skydelio - kas kovoja dabar.
// #182 - rungtis užsirakina pati, kai baigiasi; #185 - kita kova paryškinta raudonai.
const { test } = require('@playwright/test');
const { sql, linkToken, login, nav, check, noJsErrors } = require('./helpers');

const blank = (r) => r.fulfill({ contentType: 'text/html', body: '<html><body style="background:#000"></body></html>' });

test('Budo streaming with phones only: one QR, assign to a tatami, viewers watch live full screen', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.route('https://vdo.ninja/**', blank);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  sql('DELETE FROM stream_phones');

  // Budo → 📡 → „🎥 Prijungti telefonus →“ (OBS bloko nebėra)
  await a.click('#share-toggle');
  check('phones link in the share panel, no OBS', await a.locator(`#share-panel a[href*="varzybu-transliacija.php?id=${tid}"]`).count() === 1 && !/OBS/.test(await a.locator('#share-panel').innerText()));

  // Transliacijos puslapis: vienas QR, tatamių kortelės su vaizdu ir juosta
  await a.goto(`admin/varzybu-transliacija.php?id=${tid}`);
  const join = await a.locator('input[aria-label="Telefono prisijungimo nuoroda"]').inputValue();
  const key = (join.match(/varzybos-kamera\.php\?k=([a-f0-9]{16,64})/) || [])[1];
  check('one QR link for all phones', !!key, join);
  const cards = await a.locator('.tr-card').count();
  check('a card per tatami, no OBS text', cards >= 1 && cards === JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)).areas.length && !/OBS/.test(await a.locator('main').innerText()));
  const area1 = await a.locator('.tr-card').first().getAttribute('data-area');
  const cam1 = await a.locator('.tr-card').first().locator('.tr-cam').getAttribute('src');
  check('tatami video does not reveal the QR key', /vdo\.ninja\/\?view=kt[a-f0-9]{20}&cleanoutput&noaudio/.test(cam1) && !cam1.includes(key), cam1);

  // Telefonas: prisijungia, laukia; priskyrus tatamiui - „Pradėti filmuoti“ su to tatamio srautu per Meshcast
  // C1: kiekvienas POST - su sesijos žetonu (telefono puslapis jį gauna window.CAM.csrf; čia - tos pačios sesijos)
  const csrf = await a.getAttribute('#tr-main', 'data-csrf');
  const ping = async () => (await a.request.post(`varzybos-kamera.php?c=${key}&ping=1`, { data: { id: 'e2etel01', name: 'E2E telefonas' }, headers: { 'X-CSRF-Token': csrf } })).json();
  check('ping without the token is refused', (await a.request.post(`varzybos-kamera.php?c=${key}&ping=1`, { data: { id: 'e2etel03' } })).status() === 400);
  let ph = await ping();
  check('phone joins and waits', ph.ok && ph.area === 0 && ph.push === '', JSON.stringify(ph));
  check('wrong key is refused', (await a.request.post('varzybos-kamera.php?c=0000000000000000&ping=1', { data: { id: 'e2etel02' } })).status() === 404);
  await a.waitForSelector('#tr-waiting-list .tr-chip', { timeout: 8000 });
  check('waiting phone is listed', /E2E telefonas/.test(await a.textContent('#tr-waiting-list')));
  await a.click(`.tr-card[data-area="${area1}"] [data-assign-id="e2etel01"]`);
  await a.waitForSelector(`.tr-card[data-area="${area1}"].has-phone`, { timeout: 8000 });
  check('phone assigned to the tatami', sql("SELECT area_id FROM stream_phones WHERE id = 'e2etel01'") === area1);
  ph = await ping();
  const camId = (cam1.match(/view=(kt[a-f0-9]{20})/) || [])[1];
  check('assigned phone films that tatami via Meshcast', ph.area === +area1 && ph.push.includes('push=' + camId) && /&webcam&autostart&facing=rear&meshcast/.test(ph.push), ph.push);

  // Telefono puslapis: priskirtas → didelis „📹 Pradėti filmuoti“ (nuoroda į kamerą)
  const m = await login(browser, null, 390, 844);
  await m.addInitScript(() => { localStorage.setItem('vz-cam-id', 'e2etel01'); });
  await m.goto(join);
  await m.waitForSelector('#cam-start:not([hidden])', { timeout: 8000 });
  check('phone page: start filming link for the tatami', (await m.getAttribute('#cam-start', 'href')).includes('push=' + camId) && /Priskirta/.test(await m.textContent('#cam-state')));

  // v2.12: ± ant juostos nebėra
  const bar = a.frameLocator(`.tr-card[data-area="${area1}"] .tr-bar`);
  await bar.locator('.gv-bar').waitFor();
  check('no ± on the streaming page bar', await bar.locator('.ov-pm, [data-end]').count() === 0);
  const sample = await a.request.post(`varzybos-gyvai.php?k=${linkToken('live', tid)}&bandymas=1`, { data: { area: +area1, sa: 1 }, headers: { 'X-CSRF-Token': csrf } });
  check('sample bout endpoint is gone', !(await sample.text()).includes('"ok":true') && !sql(`SELECT data FROM tournament_live WHERE tournament_id = ${tid}`).includes('Bandomoji'));

  // Žiūrovai: prie tatamio „▶ Žiūrėti gyvai“ - vaizdas tik paspaudus, su juosta
  const share = linkToken('live', tid);
  const v = await login(browser, null, 390, 844);
  await v.route('https://vdo.ninja/**', blank);
  await v.goto(`varzybos-gyvai.php?k=${share}`);
  await v.waitForSelector(`[data-vid="${area1}"] [data-watch]`, { timeout: 8000 });
  check('no video before tapping', await v.locator(`[data-vid="${area1}"] iframe`).count() === 0);
  await v.click(`[data-vid="${area1}"] [data-watch]`);
  check('video after tapping watch', /view=kt[a-f0-9]{20}/.test(await v.locator(`[data-vid="${area1}"] iframe`).getAttribute('src')));
  // ⛶ per visą ekraną (Denis): visas kadras su juosta; dar kartą - atgal
  const full = v.locator(`[data-vid="${area1}"] [data-full]`);
  check('full screen button after tapping watch', await full.isVisible());
  await full.click(); await v.waitForTimeout(400);
  const box = await v.locator(`[data-vid="${area1}"] .gv-vid-frame`).boundingBox();
  check('video fills the screen', await v.locator(`[data-vid="${area1}"] .gv-vid-frame.gv-full`).count() === 1 && box && box.width >= 380 && box.height >= 800, JSON.stringify(box));
  await v.locator(`[data-vid="${area1}"] [data-full]`).click(); await v.waitForTimeout(400);
  check('back from full screen', await v.locator('.gv-vid-frame.gv-full').count() === 0);
  // 💡 žiūrovų puslapyje (Denis: „Budo cup nėra kaip palikt atsiliepimą“) - personalui visada
  await a.goto(`varzybos-gyvai.php?k=${share}`);
  check('feedback button on the viewers page', await a.locator('#fbFab').isVisible());

  // Kovos skydelis: „📹 Vaizdas“ - vaizdas šalia balų, ± ant vaizdo keičia balus, vaizdas nepersikrauna
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.click('#tab-admin'); await a.click('#tab-kategorijos');
  await a.click('[data-catview="edit"]');
  await a.waitForSelector('#cat-list button');
  await a.locator('#cat-list button', { hasText: 'Kumite' }).first().click();
  await a.click('#cat-draw');
  await a.click('#draw-reset-menu summary');
  await a.click('#draw-reset [data-reset="all"]');
  // v2.12: sekretoriatui telefono vaizdo nebėra
  check('no camera in the secretariat', await a.locator('#live-cam').count() === 0);
  // #185 (Denis): kita kova - raudonas rėmelis; pažymėjus nugalėtoją - kita kova paryškinama
  const firstNext = a.locator('#draw-view .match.is-next');
  check('next bout highlighted', await firstNext.count() === 1 && /Kita kova/.test(await firstNext.textContent()));
  const mid0 = await firstNext.locator('[data-bout]').getAttribute('data-bout');
  await firstNext.locator('.slot.aka button').click(); await a.waitForTimeout(300);
  const mid1 = await a.locator('#draw-view .match.is-next [data-bout]').first().getAttribute('data-bout').catch(() => null);
  check('after a winner the highlight moves on', mid1 && mid1 !== mid0, mid0 + ' → ' + mid1);
  await a.click('#draw-undo'); await a.waitForTimeout(300);
  await a.locator('.bout-open').first().click();
  check('no video buttons in the bout panel', await a.locator('#bt-vid, #bt-live').count() === 0);

  // #182: rungtis užsirakina pati, kai baigiama paskutinė kova; atrakinti - paspaudus „🔒 Užrakinta“
  check('unlocked while bouts are left', (await a.textContent('#draw-lock')).includes('Užrakinti'));
  // „▶“ rodomas ir baigtoms kovoms - imama pirma NEbaigta
  for (let i = 0; i < 40 && await a.locator('#bout:not([hidden]) [data-win="a"]').count(); i++) {
    await a.click('#bout [data-win="a"]'); await a.waitForTimeout(250);
    const next = a.locator('#draw-view .match:not(.is-done) .bout-open').first();
    if (!(await next.count())) break;
    await next.click(); await a.waitForTimeout(200);
  }
  check('locked by itself when the category is finished', (await a.textContent('#draw-lock')).includes('Užrakinta'), await a.textContent('#draw-lock'));
  await a.click('#draw-lock');
  check('can be unlocked to fix', (await a.textContent('#draw-lock')).includes('Užrakinti'));

  sql(`DELETE FROM tournaments WHERE id = ${tid}`);
  sql('DELETE FROM stream_phones');
  noJsErrors(a, m, v);
});

test('v2.12: the secretariat has no camera window', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.waitForSelector('#tab-live');
  check('no Live kova button or window', await a.locator('#live-cam, #live-win, [data-dk="live"]').count() === 0);
  check('no camera addresses sent to the secretariat', await a.evaluate(() => !('cams' in (window.VARZ || window.TAIKA || {}))));
  sql(`DELETE FROM tournaments WHERE id = ${tid}`);
  noJsErrors(a);
});
