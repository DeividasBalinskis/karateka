// v1.97-v1.99: Transliacija be OBS - telefonai jungiasi per vieną QR, kompiuteryje priskiriami tatamiui, telefone „Pradėti filmuoti“ (vdo.ninja + Meshcast);
// tatamių kortelės su vaizdu ir ± prie balų (pavyzdinė kova), žiūrovams „📹 Žiūrėti gyvai“, sekretoriatui kovoje „📹 Vaizdas“ su ± ant vaizdo;
// #182 - rungtis užsirakina pati, kai baigiasi.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

const blank = (r) => r.fulfill({ contentType: 'text/html', body: '<html><body style="background:#000"></body></html>' });

test('Budo streaming with phones only: one QR, assign to a tatami, ± on the video, viewers watch live', async ({ browser }) => {
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
  const key = (join.match(/varzybos-kamera\.php\?c=([a-f0-9]{16})/) || [])[1];
  check('one QR link for all phones', !!key, join);
  const cards = await a.locator('.tr-card').count();
  check('a card per tatami, no OBS text', cards >= 1 && cards === JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)).areas.length && !/OBS/.test(await a.locator('main').innerText()));
  const area1 = await a.locator('.tr-card').first().getAttribute('data-area');
  const cam1 = await a.locator('.tr-card').first().locator('.tr-cam').getAttribute('src');
  check('tatami video does not reveal the QR key', /vdo\.ninja\/\?view=kt[a-f0-9]{20}&cleanoutput&noaudio/.test(cam1) && !cam1.includes(key), cam1);

  // Telefonas: prisijungia, laukia; priskyrus tatamiui - „Pradėti filmuoti“ su to tatamio srautu per Meshcast
  const ping = async () => (await a.request.post(`varzybos-kamera.php?c=${key}&ping=1`, { data: { id: 'e2etel01', name: 'E2E telefonas' } })).json();
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

  // ± kairėje ir dešinėje balų ant vaizdo: pavyzdinė kova (ją mato ir žiūrovai)
  const bar = a.frameLocator(`.tr-card[data-area="${area1}"] .tr-bar`);
  await bar.locator('.ov-pm[data-d="a|1"]').click(); await a.waitForTimeout(500);
  await bar.locator('.ov-pm[data-d="a|1"]').click(); await a.waitForTimeout(500);
  await bar.locator('.ov-pm[data-d="b|1"]').click(); await a.waitForTimeout(500);
  const live = JSON.parse(sql(`SELECT data FROM tournament_live WHERE tournament_id = ${tid} AND area_id = ${area1}`) || '{}');
  check('sample bout saved', live.test === true && live.sa === 2 && live.sb === 1, JSON.stringify(live));
  check('one split button per side: + on top, − below', await bar.locator('.ov-pmv').count() === 2 && (await bar.locator('.ov-pmv').first().locator('button').first().textContent()) === '+' && (await bar.locator('.ov-pmv').first().locator('button').last().textContent()) === '−');

  // Žiūrovai: prie tatamio „▶ Žiūrėti gyvai“ - vaizdas tik paspaudus, su juosta
  const share = sql(`SELECT share_token FROM tournaments WHERE id = ${tid}`);
  const v = await login(browser, null, 390, 844);
  await v.route('https://vdo.ninja/**', blank);
  await v.goto(`varzybos-gyvai.php?k=${share}`);
  await v.waitForSelector(`[data-vid="${area1}"] [data-watch]`, { timeout: 8000 });
  check('no video before tapping', await v.locator(`[data-vid="${area1}"] iframe`).count() === 0);
  await v.click(`[data-vid="${area1}"] [data-watch]`);
  check('video after tapping watch', /view=kt[a-f0-9]{20}/.test(await v.locator(`[data-vid="${area1}"] iframe`).getAttribute('src')));
  await bar.locator('[data-end]').click(); await a.waitForTimeout(500);
  check('sample bout ended', JSON.parse(sql(`SELECT data FROM tournament_live WHERE tournament_id = ${tid} AND area_id = ${area1}`)).idle === true);

  // Kovos skydelis: „📹 Vaizdas“ - vaizdas šalia balų, ± ant vaizdo keičia balus, vaizdas nepersikrauna
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await a.click('#tab-admin'); await a.click('#tab-kategorijos');
  await a.click('[data-catview="edit"]');
  await a.waitForSelector('#cat-list button');
  await a.locator('#cat-list button', { hasText: 'Kumite' }).first().click();
  await a.click('#cat-draw');
  await a.click('#draw-reset-menu summary');
  await a.click('#draw-reset [data-reset="all"]');
  await a.locator('.bout-open').first().click();
  await a.click('#bt-vid');
  check('video window next to the bout', await a.locator('#bout-vid').isVisible() && /view=kt[a-f0-9]{20}/.test(await a.getAttribute('#bout-vid iframe', 'src')));
  await a.evaluate(() => { document.querySelector('#bout-vid iframe').dataset.mark = '1'; });
  await a.click('#bout-vid [data-vpt="a|1"]');
  await a.click('#bout-vid [data-vpt="a|1"]');
  await a.click('#bout-vid [data-vpt="b|1"]');
  check('± on the video change the bout scores', (await a.inputValue('[data-sc="a"]')) === '2' && (await a.inputValue('[data-sc="b"]')) === '1');
  await a.click('#bout-vid [data-vpt="a|-1"]');
  check('− on the video takes a point off', (await a.inputValue('[data-sc="a"]')) === '1');
  check('video not reloaded by score clicks', await a.evaluate(() => document.querySelector('#bout-vid iframe').dataset.mark === '1'));
  await a.click('#bt-vid');
  check('video can be turned off', await a.locator('#bout-vid').isHidden());

  // #182: rungtis užsirakina pati, kai baigiama paskutinė kova; atrakinti - paspaudus „🔒 Užrakinta“
  check('unlocked while bouts are left', (await a.textContent('#draw-lock')).includes('Užrakinti'));
  for (let i = 0; i < 30 && await a.locator('#bout [data-win="a"]').count(); i++) {
    await a.click('#bout [data-win="a"]'); await a.waitForTimeout(250);
    const next = a.locator('#draw-view .bout-open').first();
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
