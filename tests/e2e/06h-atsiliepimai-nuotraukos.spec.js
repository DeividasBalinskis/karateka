// v1.27: atsiliepimų langas visose svetainėse (karateka.lt, Taika, LTKF): išsaugoma pati, Ctrl+V nuotraukos (daug),
// Enter - išsaugoti ir naujas, ankstesni atsiliepimai (pavadinimas, nuotraukų skaičius) - paspaudus taisyti; Claude prieiga su raktu.
const { test } = require('@playwright/test');
const { sql, login, nav, check, noJsErrors, flashes, openFeedback } = require('./helpers');

/** Įklijuoja n ekrano nuotraukų (kaip Ctrl+V) į atsiliepimo laukelį */
async function pasteImages(page, n) {
  await page.evaluate(async (count) => {
    for (let i = 0; i < count; i++) {
      const c = document.createElement('canvas'); c.width = 320; c.height = 200;
      const x = c.getContext('2d'); x.fillStyle = ['#e8590c', '#1c7ed6', '#2b8a3e'][i % 3]; x.fillRect(0, 0, 320, 200); x.fillStyle = '#fff'; x.font = '28px sans-serif'; x.fillText('Ekranas ' + (i + 1), 60, 110);
      const blob = await new Promise(r => c.toBlob(r, 'image/png'));
      const dt = new DataTransfer(); dt.items.add(new File([blob], 'image.png', { type: 'image/png' }));
      document.getElementById('fbText').dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
    }
  }, n);
}

test('feedback widget: autosave, paste many images, Enter for next, edit earlier, Taika and LTKF, Claude API', async ({ browser }) => {
  sql('DELETE FROM feedback');
  const a = await login(browser, 'info@karateka.lt', 1280, 860);

  // Taika: rašai - išsaugoma pati (be mygtuko), įklijuoji 3 nuotraukas
  await a.goto('admin/varzybos.php');
  await openFeedback(a);
  await a.fill('#fbText', 'Kategorijų struktūroje trūksta filtro pagal kyu\nIr dar viena mintis');
  await a.waitForFunction(() => /Išsaugota/.test(document.getElementById('fbState').textContent), null, { timeout: 5000 });
  check('autosaved without button', sql("SELECT CONCAT(site, '|', page) FROM feedback") === 'taika|/admin/varzybos.php');
  await pasteImages(a, 3);
  await a.waitForFunction(() => document.querySelectorAll('#fbImgs .fb-img img').length === 3 && !document.querySelector('#fbImgs [data-tmp]'), null, { timeout: 15000 });
  check('three images attached', sql('SELECT COUNT(*) FROM feedback_images') === '3');
  check('context saved', /"w":1280/.test(sql('SELECT context FROM feedback')));

  // Enter - išsaugota ir naujas; sąraše - pavadinimas ir 🖼 3
  await a.press('#fbText', 'Enter');
  await a.waitForFunction(() => document.getElementById('fbText').value === '');
  const first = a.locator('#fbList .fb-item').first();
  check('list shows title and image count', /Kategorijų struktūroje/.test(await first.innerText()) && /🖼 3/.test(await first.innerText()));

  // Iš karto kitas: tik nuotrauka, paskui tekstas - tas pats atsiliepimas
  await pasteImages(a, 1);
  await a.waitForFunction(() => document.querySelectorAll('#fbImgs .fb-img img').length === 1 && !document.querySelector('#fbImgs [data-tmp]'), null, { timeout: 15000 });
  await a.fill('#fbText', 'Antras: mygtukas per mažas');
  await a.press('#fbText', 'Enter');
  // v1.89 (#164): matomas tik paskutinis, kiti - „Rodyti visus (2)“
  await a.waitForFunction(() => /\(2\)/.test((document.querySelector('#fbList [data-mine-all]') || {}).textContent || ''));
  await a.click('#fbList [data-mine-all]');
  await a.waitForFunction(() => document.querySelectorAll('#fbList .fb-item').length === 2);
  check('image-first then text = one item', sql("SELECT COUNT(*) FROM feedback WHERE body = 'Antras: mygtukas per mažas'") === '1' && sql('SELECT COUNT(*) FROM feedback') === '2');

  // Ankstesnį galima atsidaryti ir pataisyti, nuotrauką - pašalinti
  await a.locator('#fbList .fb-item', { hasText: 'Kategorijų struktūroje' }).click();
  check('editing earlier', await a.locator('#fbEditing').isVisible() && /Ir dar viena mintis/.test(await a.inputValue('#fbText')));
  await a.fill('#fbText', 'Kategorijų struktūroje trūksta filtro pagal kyu (pataisyta)');
  await a.locator('#fbImgs .fb-img-x').first().click();
  await a.waitForTimeout(1500);
  check('edit saved', sql("SELECT COUNT(*) FROM feedback WHERE body LIKE '%(pataisyta)'") === '1');
  check('image removed', sql('SELECT COUNT(*) FROM feedback_images') === '3');

  // LTKF - tas pats langas, svetainė pažymima
  await a.goto('ltkf/klubai.php');
  await a.waitForSelector('#fbFab');
  await openFeedback(a);
  // v1.87: pereinant į kitą puslapį langas ir taisomas atsiliepimas išlieka - naujam spaudžiama „+ Naujas“
  check('editing continues on another page', /\(pataisyta\)/.test(await a.inputValue('#fbText')) && await a.locator('#fbEditing').isVisible());
  await a.click('#fbNew');
  await a.fill('#fbText', 'LTKF klubų žemėlapis per mažas');
  await a.press('#fbText', 'Enter');
  await a.waitForFunction(() => /Išsaugota/.test(document.getElementById('fbState').textContent));
  check('ltkf feedback', sql("SELECT CONCAT(site, '|', page) FROM feedback WHERE body LIKE 'LTKF%'") === 'ltkf|/ltkf/klubai.php');

  // Adminas: nuotraukos sąraše; Claude raktas ir API
  await a.goto('admin/atsiliepimai.php');
  check('admin sees images', await a.locator('.fb-card-imgs img').count() === 3);
  await nav(a, () => a.click('button[value=api_key]'));
  const key = await a.inputValue('#fbKey');
  check('key shown once', /^[a-f0-9]{40}$/.test(key));
  const api = await a.context().request.get('atsiliepimai-api.php', { headers: { 'X-Api-Key': key } });
  const d = await api.json();
  check('api lists feedback with images', d.ok && d.items.length === 3 && d.items[0].images.length === 2, JSON.stringify(d).slice(0, 300));
  const img = await a.context().request.get(d.items[0].images[0].replace(/^https?:\/\/[^/]+\//, ''), { headers: { 'X-Api-Key': key } });
  check('api image', img.status() === 200 && (img.headers()['content-type'] || '').includes('image/jpeg'));
  const bad = await a.context().request.get('atsiliepimai-api.php', { headers: { 'X-Api-Key': 'neteisingas' } });
  check('wrong key refused', bad.status() === 401);
  const upd = await a.context().request.post('atsiliepimai-api.php', { headers: { 'X-Api-Key': key, 'Content-Type': 'application/json' }, data: { id: d.items[0].id, status: 'done', reply: 'Padaryta v1.27' } });
  check('api marks done', (await upd.json()).ok && sql(`SELECT CONCAT(status, '|', reply) FROM feedback WHERE id = ${d.items[0].id}`) === 'done|Padaryta v1.27');

  // Nuotraukos tiesiogiai nepasiekiamos be teisių
  const anon = await login(browser, null);
  const r = await anon.goto(`atsiliepimas.php?img=${sql('SELECT MIN(id) FROM feedback_images')}`);
  check('image hidden from strangers', r.status() === 404);
  sql("DELETE FROM site_settings WHERE name = 'feedback_api_key'");
  noJsErrors(a);
});
