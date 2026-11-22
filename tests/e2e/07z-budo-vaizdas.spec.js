// v1.86 (Kumite Technology idėja): tatamį filmuoja telefonas (YouTube Live), žiūrovų puslapyje - vaizdas su kovos rezultatu ant jo.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors } = require('./helpers');

test('Budo video: YouTube link per tatami; viewers see the video with the live bout on it', async ({ browser }) => {
  const a = await login(browser, 'info@karateka.lt', 1400, 900);
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.click('#tab-live');
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  const inp = a.locator('input[data-stream]').first();
  check('video link field on each tatami', await a.locator('input[data-stream]').count() >= 1);
  const areaId = await inp.getAttribute('data-stream');
  await inp.fill('nevisai nuoroda');
  await inp.dispatchEvent('change');
  check('wrong link refused', !/nevisai/.test(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)));
  await inp.fill('https://www.youtube.com/live/abcdefghijk');
  await inp.dispatchEvent('change');
  for (let i = 0; i < 30 && !/abcdefghijk/.test(sql(`SELECT results FROM tournaments WHERE id = ${tid}`)); i++) await a.waitForTimeout(500);
  const res = sql(`SELECT results FROM tournaments WHERE id = ${tid}`);
  check('saved in the competition', /abcdefghijk/.test(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)) && /youtube-nocookie\.com.{0,6}embed.{0,6}abcdefghijk/.test(res));
  // Kova vyksta ant to tatamio
  const live = JSON.stringify({ mode: 'kumite', cat: 'Kumite M 10-11', round: 'Finalas', bout: '3', aka: { name: 'Jonas J.' }, shiro: { name: 'Petras P.' }, sa: 2, sb: 1, at: Date.now(), timer: { running: false, remaining: 61000 } });
  sql(`REPLACE INTO tournament_live (tournament_id, area_id, data, updated_ms) VALUES (${tid}, ${areaId}, '${live.replace(/'/g, "''")}', ${Date.now()})`);
  sql(`UPDATE tournaments SET share_token = 'abcdefabcdefabcdefab0786' WHERE id = ${tid}`);
  const v = await login(browser, null, 390, 844);
  await v.goto('varzybos-gyvai.php?k=abcdefabcdefabcdefab0786');
  await v.waitForSelector('#gv-vids .gv-vid iframe');
  check('video shown for the tatami', (await v.getAttribute('#gv-vids iframe', 'src')).includes('youtube-nocookie.com/embed/abcdefghijk'));
  await v.waitForSelector('#gv-vids .gv-ovl:not([hidden])');
  const ovl = await v.locator('#gv-vids .gv-ovl').innerText();
  check('bout and score over the video', /Jonas J\./.test(ovl) && /Petras P\./.test(ovl) && /2/.test(ovl) && /1:01/.test(ovl), ovl);
  const src1 = await v.evaluate(() => document.querySelector('#gv-vids iframe') === window.__f || (window.__f = document.querySelector('#gv-vids iframe'), 'first'));
  await v.waitForTimeout(3500);
  check('video is not reloaded on refresh', await v.evaluate(() => document.querySelector('#gv-vids iframe') === window.__f), String(src1));
  check('no sideways scroll on a phone', await v.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  sql(`DELETE FROM tournaments WHERE id = ${tid}`);
  noJsErrors(a, v);
});
