// v1.97: Transliacija - telefono kamera (vdo.ninja) kiekvienam tatamiui, puslapis su paaiškinimu ir peržiūra (telefonas + juosta),
// sekretoriatas kovos skydelyje mato telefono vaizdą („📹 Vaizdas“) ir rašo balus kaip visada.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('Budo streaming: phone camera link, preview, OBS links, video next to the scores', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.route('https://vdo.ninja/**', (r) => r.fulfill({ contentType: 'text/html', body: '<html><body style="background:#000"></body></html>' }));
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');

  // Budo → 📡 → nuoroda į Transliacijos puslapį
  await a.click('#share-toggle');
  check('stream page link in the share panel', await a.locator(`#share-panel a[href*="varzybu-transliacija.php?id=${tid}"]`).count() === 1);

  // Transliacijos puslapis: telefono nuoroda ir OBS nuorodos - tas pats slaptas srautas
  await a.goto(`admin/varzybu-transliacija.php?id=${tid}`);
  const push = await a.locator('input[aria-label="Telefono nuoroda"]').inputValue();
  const view = await a.locator('input[aria-label="Telefono vaizdas OBS programai"]').inputValue();
  const id = (push.match(/push=(kt[a-f0-9]{16}t\d+)/) || [])[1];
  check('phone link pushes a secret stream', !!id && /&webcam&autostart&facing=rear/.test(push), push);
  check('OBS view link shows the same stream', view.includes('view=' + id) && /cleanoutput/.test(view), view);
  check('preview shows the phone video', (await a.getAttribute('.tr-cam', 'src')).includes('view=' + id));
  check('preview bar without help', /juosta=1/.test(await a.getAttribute('#tr-bar', 'src')) && /obs=1/.test(await a.getAttribute('#tr-bar', 'src')));
  await a.click('#tr-sample');
  check('sample bout in the preview', /pavyzdys=1/.test(await a.getAttribute('#tr-bar', 'src')));
  check('how-to and when it works', await a.locator('.tr-step').count() === 3 && await a.locator('.tr-when li').count() === 5);
  const barFrame = a.frameLocator('#tr-bar');
  await barFrame.locator('.gv-bar').waitFor();
  check('no help text inside the preview bar', await barFrame.locator('.gv-bar-help').count() === 0);

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
  noJsErrors(a);
});
