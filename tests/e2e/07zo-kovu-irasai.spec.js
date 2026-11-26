// v2.13 (Deividas: „pasirinkus baigtą kovą - jos vieta telefonu nufilmuotame vaizde“; Denis: „ar galima įrašyti, kad liktų YouTube“):
// pažymėjus rezultatą įrašomas kovos laikas, prie baigtos kovos - 🎬. Jis atidaro YouTube įrašą nuo kovos pradžios (serveris sužino,
// kada transliacija prasidėjo), telefono įrašą (failas niekur nekeliamas), „📍 Ši kova prasideda čia“ pataiso laiką visam įrašui.
// Žiūrovai prie kovos rezultato mato 🎬 su YouTube nuoroda. Telefone - „⏺ Įrašyti vaizdą ir šiame telefone“ (vdo.ninja &autorecordlocal).
const { test } = require('@playwright/test');
const { sql, linkToken, login, nav, check, noJsErrors } = require('./helpers');

const blank = (r) => r.fulfill({ contentType: 'text/html', body: '<html><body style="background:#000"></body></html>' });
const stateOf = (tid) => JSON.parse(sql(`SELECT state FROM tournaments WHERE id = ${tid}`));
const saved = (page) => page.waitForFunction(() => /Išsaugota/.test((document.querySelector('.save-state') || {}).textContent || ''), null, { timeout: 10000 });
async function openCat(page, tid, catId) {
  await page.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await page.click('#tab-live');
  if (await page.locator('#run').isVisible()) await page.click('#run-back');
  if (await page.locator('#area-pick.ask [data-my-area="all"]').count()) await page.click('#area-pick.ask [data-my-area="all"]');
  await page.locator(`[data-open-cat="${catId}"]`).first().click();
  await page.waitForSelector('#run:not([hidden])');
  await page.waitForSelector('#draw-view .draw-head', { state: 'attached' });
}
// Telefonu įrašytas WebM (kaip vdo.ninja): 3 s vaizdas, failo pakeitimo laikas - įrašo pabaiga; įdedamas į „📂 Įrašas iš telefono“
const phoneFile = (page, name, lastModified) => page.evaluate(async ([name, last]) => {
  const cv = document.createElement('canvas'); cv.width = 160; cv.height = 90;
  const g = cv.getContext('2d'), rec = new MediaRecorder(cv.captureStream(15), { mimeType: 'video/webm' }), parts = [];
  rec.ondataavailable = (e) => parts.push(e.data);
  let i = 0; const tick = setInterval(() => { g.fillStyle = i++ % 2 ? '#c00' : '#00c'; g.fillRect(0, 0, 160, 90); }, 60);
  rec.start(250); await new Promise((r) => setTimeout(r, 3000));
  const stopped = new Promise((r) => { rec.onstop = r; }); rec.stop(); await stopped; clearInterval(tick);
  const f = new File(parts, name, { type: 'video/webm', lastModified: last }), dt = new DataTransfer();
  dt.items.add(f);
  const inp = document.getElementById('vd-file'); inp.files = dt.files; inp.dispatchEvent(new Event('change', { bubbles: true }));
  return f.size;
}, [name, lastModified]);

test('Budo: a finished bout opens at its moment in the YouTube video, in the phone recording and for viewers', async ({ browser }) => {
  test.setTimeout(240000);
  const a = await login(browser, 'info@karateka.lt', 1440, 900);
  await a.route(/^https:\/\/(www\.)?(youtube(-nocookie)?\.com|youtu\.be|vdo\.ninja)\//, blank);
  // Serveris YouTube puslapyje randa, kada transliacija prasidėjo - teste atsakymas paruoštas (be interneto)
  const ytStart = Date.now() - 30 * 60000;
  let asked = '';
  await a.route(/varzybu-sistema\.php\?.*yt=1/, async (r) => { asked = (r.request().postDataJSON() || {}).url || ''; await r.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, id: 'dQw4w9WgXcQ', start: ytStart }) }); });
  await a.goto('admin/varzybos.php');
  await nav(a, () => a.click('text=🎲 Sukurti test varžybas'));
  const tid = +sql('SELECT MAX(id) FROM tournaments');
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);
  await saved(a); await a.waitForTimeout(1200);
  let st = stateOf(tid);
  const ki = st.categories.findIndex((c) => c.discipline === 'kata' && !c.team && c.competitors.length >= 4 && c.draw && c.draw.slots);
  const ui = st.categories.findIndex((c) => c.discipline === 'kumite' && !c.team && c.competitors.length >= 4 && c.draw && c.draw.slots);
  check('test categories exist', ki >= 0 && ui >= 0);
  const kata = st.categories[ki], area = kata.tatami || st.areas[0].id;
  // Test varžybose dalis kovų jau įvykusi - šių dviejų rungčių rezultatai išvalomi (puslapis perkraunamas, kad neužrašytų senų)
  for (const i of [ki, ui]) sql(`UPDATE tournaments SET state = JSON_SET(state, '$.categories[${i}].draw.results', JSON_OBJECT(), '$.categories[${i}].draw.scores', JSON_OBJECT(), '$.categories[${i}].draw.locked', JSON_EXTRACT('false', '$')) WHERE id = ${tid}`);
  check('results cleared', Object.keys(stateOf(tid).categories[ki].draw.results).length === 0);
  await a.goto(`admin/varzybu-sistema.php?id=${tid}`);

  // 📹 YouTube nuoroda prie tatamio → įrašas su tikra transliacijos pradžia
  await a.click('#tab-live');
  if (await a.locator('#run').isVisible()) await a.click('#run-back');
  if (await a.locator('#area-pick.ask [data-my-area="all"]').count()) await a.click('#area-pick.ask [data-my-area="all"]');
  const inp = a.locator(`input[data-stream="${area}"]`);
  await inp.fill('https://www.youtube.com/live/dQw4w9WgXcQ?si=e2e');
  await inp.dispatchEvent('change');
  await a.waitForTimeout(1500); await saved(a); await a.waitForTimeout(900);
  st = stateOf(tid);
  const yv = (st.videos || []).find((v) => v.yt === 'dQw4w9WgXcQ');
  check('stream start asked from the server', asked.includes('dQw4w9WgXcQ'), asked);
  check('recording kept with its real start', !!yv && yv.a === area && yv.from === ytStart && yv.ok === true, JSON.stringify(st.videos));

  // Kata vėliavėlėmis: pažymėjus nugalėtoją - kovos laikas ir 🎬
  await openCat(a, tid, kata.id);
  const t0 = Date.now();
  await a.locator('#draw-view .match:not(.is-done):not(.is-bye) [data-flag]').first().click();
  await saved(a); await a.waitForTimeout(900); await saved(a);
  st = stateOf(tid);
  const kd = st.categories[ki].draw, mid = Object.keys(kd.times || {})[0], t = (kd.times || {})[mid];
  check('bout time saved with the result', !!t && !!kd.results[mid] && t.a === area && t.e >= t0 - 2000 && t.e <= Date.now() + 2000 && t.s <= t.e - 20000 && t.s >= t.e - 4 * 60000, JSON.stringify(kd.times));
  check('🎬 only at the bout with a time', await a.locator(`#draw-view .m-vid[data-vid="${mid}"]`).count() === 1 && await a.locator('#draw-view .m-vid').count() === 1);

  // 🎬 → YouTube nuo kovos pradžios (10 s anksčiau)
  await a.click(`#draw-view .m-vid[data-vid="${mid}"]`);
  await a.waitForSelector('.vd-back:not([hidden]) #vd-frame');
  const off = Math.max(0, Math.round((t.s - ytStart) / 1000) - 10);
  const src = await a.getAttribute('#vd-frame', 'src');
  check('YouTube opens at the bout', src.includes('youtube-nocookie.com/embed/dQw4w9WgXcQ?start=' + off + '&'), src + ' / ' + off);
  check('link to YouTube at the bout', await a.getAttribute('#vd-yt', 'href') === 'https://youtu.be/dQw4w9WgXcQ?t=' + off);
  check('title and time shown', /Kova \d\d:\d\d–\d\d:\d\d/.test(await a.textContent('#vd-meta')) && (await a.textContent('#vd-title')).length > 5);

  // 📍 Ši kova prasideda čia (grotuvas laiko nepraneša - klausiama): visas įrašas pataisomas
  await a.evaluate(() => { window.prompt = () => '20:00'; });   // login() pats uždaro langus - atsakymas įrašomas čia
  await a.click('#vd-here');
  await saved(a); await a.waitForTimeout(900); await saved(a);
  st = stateOf(tid);
  check('"starts here" fixes the recording start', st.videos.find((v) => v.yt === 'dQw4w9WgXcQ').from === t.s - 20 * 60000);
  check('YouTube link follows the fix', await a.getAttribute('#vd-yt', 'href') === 'https://youtu.be/dQw4w9WgXcQ?t=1190');

  // Žiūrovams: 🎬 prie kovos rezultato (YouTube nuo kovos pradžios)
  for (let i = 0; i < 20 && !/youtu\.be\\?\/dQw4w9WgXcQ\?t=1190/.test(sql(`SELECT results FROM tournaments WHERE id = ${tid}`)); i++) await a.waitForTimeout(500);
  const br = JSON.parse(sql(`SELECT results FROM tournaments WHERE id = ${tid}`)).brackets[kata.id];
  const pm = br.groups.flatMap((g) => g.matches || []).filter((m) => m.v);
  check('viewers get the bout link', pm.length === 1 && pm[0].v.replace(/\\\//g, '/') === 'https://youtu.be/dQw4w9WgXcQ?t=1190', JSON.stringify(pm));   // sql() išvestyje „/“ - „\/“

  // Telefono įrašas: pradžia = failo laikas − trukmė; atidaroma kovos vieta; kitas failas - „ne šiame įraše“
  const size = await phoneFile(a, 'budo-tatamis.webm', t.s + 2000);
  await a.waitForFunction(() => /telefono įraše nuo|ne šiame įraše/.test(document.getElementById('vd-meta').textContent), null, { timeout: 15000 });
  check('phone recording opens at the bout', /telefono įraše nuo 0:00/.test(await a.textContent('#vd-meta')) && await a.locator('#vd-vid').count() === 1 && await a.locator('#vd-frame').count() === 0, await a.textContent('#vd-meta'));
  await saved(a); await a.waitForTimeout(900); await saved(a);
  st = stateOf(tid);
  const fv = st.videos.find((v) => v.file === 'budo-tatamis.webm|' + size);
  check('phone recording start from the file', !!fv && fv.a === area && Math.abs(fv.from - (t.s - 1000)) < 1500, JSON.stringify(st.videos));
  check('the file itself is not uploaded', !/blob:|data:video/.test(sql(`SELECT state FROM tournaments WHERE id = ${tid}`)));
  await phoneFile(a, 'kitas.webm', t.s - 3600000);
  await a.waitForFunction(() => /ne šiame įraše/.test(document.getElementById('vd-meta').textContent), null, { timeout: 15000 });
  check('a recording without the bout says so', /kova vyko/.test(await a.textContent('#vd-help')));
  await a.keyboard.press('Escape');
  check('window closes', await a.locator('.vd-back').isHidden() && await a.locator('#vd-player iframe, #vd-player video').count() === 0);

  // Kumite kovos skydelis: pradžia - pirmas „Hajime“ (laikas paleistas)
  const kum = st.categories[ui];
  await openCat(a, tid, kum.id);
  await a.locator('#draw-view .match:not(.is-done):not(.is-bye) .bout-open').first().click();
  const go = Date.now();
  await a.click('#bt-start'); await a.waitForTimeout(1200); await a.click('#bt-start');
  await a.click('[data-pt="a|2"]');
  await a.click('#bt-finish');
  await a.waitForTimeout(1200); await saved(a);
  const ud = stateOf(tid).categories[ui].draw, um = Object.keys(ud.times || {})[0], ut = (ud.times || {})[um];
  check('bout panel: start just before the first Hajime', !!ut && Math.abs(ut.s - (go - 5000)) < 2500 && ut.e - ut.s > 5000 && ut.e - ut.s < 15000, JSON.stringify(ud.times));
  check('🎬 at the kumite bout', await a.locator(`#draw-view .m-vid[data-vid="${um}"]`).count() === 1);

  // Žiūrovų puslapis: rungtyje prie baigtos kovos - 🎬 nuoroda į YouTube
  const v = await login(browser, null, 390, 844);
  await v.route(/^https:\/\/(www\.)?(youtube(-nocookie)?\.com|youtu\.be|vdo\.ninja)\//, blank);
  await v.goto(`varzybos-gyvai.php?k=${linkToken('live', tid)}`);
  await v.locator(`[data-cat="${kata.id}"]`).first().evaluate((b) => b.click());
  await v.waitForSelector('.gv-sheet:not([hidden]) .gv-mv', { timeout: 12000 });
  check('viewer sees 🎬 at the bout', await v.getAttribute('.gv-sheet .gv-mv', 'href') === 'https://youtu.be/dQw4w9WgXcQ?t=1190' && await v.locator('.gv-sheet .gv-mv').count() === 1);
  check('no sideways scroll on a phone', await v.evaluate(() => document.documentElement.scrollWidth <= innerWidth));

  // Telefonas: „⏺ Įrašyti vaizdą ir šiame telefone“ → kamera įrašo ir telefone (lieka pažymėta)
  await a.goto(`admin/varzybu-transliacija.php?id=${tid}`);
  check('help: how to watch a finished bout', /Kovų įrašai/.test(await a.locator('main').innerText()) && /📍 Ši kova prasideda čia/.test(await a.locator('main').innerText()));
  const join = await a.locator('input[aria-label="Telefono prisijungimo nuoroda"]').inputValue();
  sql("DELETE FROM stream_phones WHERE id = 'e2erec01'");
  sql(`INSERT INTO stream_phones (id, name, seen_at, area_id, area_name) VALUES ('e2erec01', 'E2E įrašas', NOW(), ${area}, 'E2E TATAMIS')`);
  const m = await login(browser, null, 390, 844);
  await m.addInitScript(() => { localStorage.setItem('vz-cam-id', 'e2erec01'); });
  await m.goto(join);
  await m.waitForSelector('#cam-start:not([hidden])', { timeout: 8000 });
  check('phone: filming without recording by default', !/autorecordlocal/.test(await m.getAttribute('#cam-start', 'href')) && await m.locator('#cam-rec-note').isHidden());
  await m.check('#cam-rec');
  check('phone: ⏺ records on the phone too', /&autorecordlocal=1500$/.test(await m.getAttribute('#cam-start', 'href')) && await m.locator('#cam-rec-note').isVisible());
  await m.reload();
  await m.waitForSelector('#cam-start:not([hidden])', { timeout: 8000 });
  check('phone: ⏺ stays ticked', await m.isChecked('#cam-rec') && /&autorecordlocal=1500$/.test(await m.getAttribute('#cam-start', 'href')));
  check('phone page fits the screen', await m.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  sql("DELETE FROM stream_phones WHERE id = 'e2erec01'");
  sql(`DELETE FROM tournaments WHERE id = ${tid}`);
  noJsErrors(a, v, m);
});
