// v1.97-v1.98: Transliacija - telefonai kameros (vienas QR, priskyrimas tatamiui), puslapis su paaiškinimu ir peržiūra (telefonas + juosta),
// sekretoriatas kovos skydelyje mato telefono vaizdą („📹 Vaizdas“) ir rašo balus kaip visada.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('Budo streaming: phones join by one QR and are assigned to tatamis, preview, OBS links, video next to the scores', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.route('https://vdo.ninja/**', (r) => r.fulfill({ contentType: 'text/html', body: '<html><body style="background:#000"></body></html>' }));
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');

  // Budo → 📡 → nuoroda į Transliacijos puslapį
  await a.click('#share-toggle');
  check('stream page link in the share panel', await a.locator(`#share-panel a[href*="varzybu-transliacija.php?id=${tid}"]`).count() === 1);

  // Transliacijos puslapis (v1.98): vienas QR telefonams, telefonai priskiriami tatamiams, OBS - viena nuoroda tatamiui arba „Visi tatamiai“
  sql('DELETE FROM stream_phones');
  await a.goto(`admin/varzybu-transliacija.php?id=${tid}`);
  const join = await a.locator('input[aria-label="Telefono prisijungimo nuoroda"]').inputValue();
  const key = (join.match(/varzybos-kamera\.php\?c=([a-f0-9]{16})/) || [])[1];
  check('one QR link for all phones', !!key, join);
  const view = await a.locator('input[aria-label="Telefono vaizdas OBS programai"]').inputValue();
  const area1 = (view.match(/view=kt[a-f0-9]{16}t(\d+)/) || [])[1];
  check('tatami video link uses the same club key', view.includes('view=kt' + key + 't') && /cleanoutput/.test(view), view);
  check('preview shows the tatami video without sound', (await a.getAttribute('.tr-cam', 'src')).includes('view=kt' + key + 't' + area1) && /noaudio/.test(await a.getAttribute('.tr-cam', 'src')));
  check('preview bar without help', /juosta=1/.test(await a.getAttribute('#tr-bar', 'src')) && /obs=1/.test(await a.getAttribute('#tr-bar', 'src')));
  check('where the video is shown, steps, check list and fixes', await a.locator('.tr-way').count() === 3 && await a.locator('.tr-steps .tr-step').count() === 3 && await a.locator('.tr-two li').count() === 9);
  const barLink = await a.locator('input[aria-label="Juosta OBS programai"]').inputValue();
  check('permanent bar link', /[?&]b=[a-f0-9]{16}&juosta=1&tatami=\d+/.test(barLink) && !/[?&]k=/.test(barLink), barLink);
  const obsOne = await a.locator('input[aria-label="Šis tatamis OBS programai"]').inputValue();
  const obsAll = await a.locator('input[aria-label="Visi tatamiai OBS programai"]').inputValue();
  check('one OBS link per tatami and one for all', /&vaizdas=1/.test(obsOne) && /[?&]visi=1/.test(obsAll), obsOne + ' ' + obsAll);
  check('print sheet has the phone QR', await a.locator('.tr-print .tr-pq').count() === 1);

  // Telefonas: prisijungia su klubo raktu, atsiranda sąraše, priskiriamas tatamiui - tada siunčia to tatamio srautu
  const ping = async () => (await a.request.post(`varzybos-kamera.php?c=${key}&ping=1`, { data: { id: 'e2etel01', name: 'E2E telefonas' } })).json();
  let ph = await ping();
  check('phone joins, not assigned yet', ph.ok && ph.area === 0 && ph.push.includes('push=kt' + key + 'pe2etel01'), JSON.stringify(ph));
  check('wrong key is refused', (await a.request.post('varzybos-kamera.php?c=0000000000000000&ping=1', { data: { id: 'e2etel02' } })).status() === 404);
  await a.waitForSelector('[data-ph="e2etel01"]', { timeout: 8000 });
  check('phone card shows its name and video', (await a.textContent('[data-ph="e2etel01"] .tr-ph-name')) === 'E2E telefonas' && /view=kt[a-f0-9]{16}pe2etel01/.test(await a.getAttribute('[data-ph="e2etel01"] iframe', 'src')));
  await a.selectOption('[data-assign="e2etel01"]', area1);
  await a.waitForTimeout(800);
  check('phone assigned to the tatami', sql("SELECT area_id FROM stream_phones WHERE id = 'e2etel01'") === area1);
  ph = await ping();
  check('assigned phone streams as the tatami', ph.area === +area1 && ph.push.includes('push=kt' + key + 't' + area1) && !!ph.areaName, JSON.stringify(ph));
  // Telefono puslapis: mygtukas „Įjungti kamerą“, po jo - prisijungta
  const m = await login(browser, null, 390, 844);
  await m.route('https://vdo.ninja/**', (r) => r.fulfill({ contentType: 'text/html', body: '<html><body style="background:#000"></body></html>' }));
  await m.goto(join);
  await m.click('#cam-start');
  await m.waitForFunction(() => /Prisijungta|Transliuojama/.test(document.getElementById('cam-state').textContent), null, { timeout: 8000 });
  check('phone page connects and shows the camera', await m.locator('#cam-frame').isVisible() && /push=kt[a-f0-9]{16}p[a-z0-9]+/.test(await m.getAttribute('#cam-if', 'src')));
  // OBS: viena nuoroda - telefono vaizdas po juosta; „Visi tatamiai“ - po langelį kiekvienam tatamiui
  await a.goto(obsOne);
  check('OBS link for one tatami has the video under the bar', /view=kt[a-f0-9]{16}t\d+/.test(await a.getAttribute('.gv-bar-video', 'src')));
  await a.goto(obsAll);
  check('all tatamis in one picture', await a.locator('.gv-all iframe').count() >= 1 && /vaizdas=1/.test(await a.getAttribute('.gv-all iframe', 'src')));
  await a.goto(`admin/varzybu-transliacija.php?id=${tid}`);
  const barFrame = a.frameLocator('#tr-bar');
  await barFrame.locator('.gv-bar').waitFor();
  check('no help text and no buttons inside the preview bar', await barFrame.locator('.gv-bar-help').count() === 0 && await barFrame.locator('.ov-ctl').count() === 0);
  // Bandomoji kova: ± abiem pusėms - serveryje, ją rodo ir juosta (OBS)
  await a.click('#tr-test [data-d="a|1"]'); await a.waitForTimeout(400);
  await a.click('#tr-test [data-d="a|1"]'); await a.waitForTimeout(400);
  await a.click('#tr-test [data-d="b|1"]'); await a.waitForTimeout(400);
  const area = +(await a.getAttribute('#tr-test', 'data-area'));
  const live = JSON.parse(sql(`SELECT data FROM tournament_live WHERE tournament_id = ${tid} AND area_id = ${area}`) || '{}');
  check('test bout saved for the tatami', live.test === true && live.sa === 2 && live.sb === 1, JSON.stringify(live));
  await barFrame.locator('.gv-bar:not(.idle)').waitFor({ timeout: 5000 });
  check('preview bar shows the test bout', /2[\s\S]*1:30[\s\S]*1/.test(await barFrame.locator('.gv-bar').innerText()));
  // OBS juosta naršyklėje treneriui: ± prie juostos
  const bar = await a.getAttribute('#tr-bar', 'data-src');
  await a.goto(bar.replace('&obs=1', ''));
  await a.waitForSelector('.gv-bar.has-ctl');
  await a.click('.gv-bar .ov-ctl [data-d="b|1"]'); await a.waitForTimeout(500);
  check('± next to the bar changes the test bout', JSON.parse(sql(`SELECT data FROM tournament_live WHERE tournament_id = ${tid} AND area_id = ${area}`)).sb === 2);
  await a.click('[data-end]'); await a.waitForTimeout(500);
  check('test bout ended', JSON.parse(sql(`SELECT data FROM tournament_live WHERE tournament_id = ${tid} AND area_id = ${area}`)).idle === true);
  // Žiūrovas be prisijungimo: mygtukų nėra, bandomoji kova jo puslapyje nerodoma
  const v = await login(browser, null, 1280, 800);
  await v.goto(bar.replace('&obs=1', ''));
  await v.waitForSelector('.gv-bar');
  check('no buttons for viewers', await v.locator('.ov-ctl').count() === 0);

  // Kovos skydelis: „📹 Vaizdas“ - telefono vaizdas šalia balų, balų paspaudimai jo nepersikrauna
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
  check('video window next to the bout', await a.locator('#bout-vid').isVisible() && /view=kt[a-f0-9]{16}t\d+/.test(await a.getAttribute('#bout-vid iframe', 'src')));
  await a.evaluate(() => { document.querySelector('#bout-vid iframe').dataset.mark = '1'; });
  await a.click('[data-pt="a|2"]');
  await a.click('[data-pt="b|1"]');
  check('scores still work with the video', (await a.inputValue('[data-sc="a"]')) === '2' && (await a.inputValue('[data-sc="b"]')) === '1');
  check('video not reloaded by score clicks', await a.evaluate(() => document.querySelector('#bout-vid iframe').dataset.mark === '1'));
  await a.click('#bt-vid');
  check('video can be turned off', await a.locator('#bout-vid').isHidden());
  sql(`DELETE FROM tournaments WHERE id = ${tid}`);
  sql('DELETE FROM stream_phones');
  noJsErrors(a, v, m);
});
