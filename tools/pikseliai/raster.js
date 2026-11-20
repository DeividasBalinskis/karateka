// Namai, dekoracijos ir daiktų ikonos (SVG) → mažos rastrinės (vėliau PIL: aiškus kraštas, mažiau spalvų, kontūras)
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const c = await b.newContext({ baseURL: 'http://127.0.0.1:8088/', viewport: { width: 1200, height: 900 }, deviceScaleFactor: 1 });
  const p = await c.newPage();
  await p.goto('prisijungti.php'); await p.fill('input[name=email]', 'jaunuolis@karateka.test'); await p.fill('input[name=password]', 'testas123');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto('dojo.php'); await p.waitForTimeout(800);
  const list = await p.evaluate(() => {
    const A = window.GameArt, C = GAME.catalog, out = [];
    document.body.innerHTML = '<div id="ras" style="display:flex;flex-wrap:wrap;gap:4px;background:transparent"></div>';
    document.documentElement.style.background = 'transparent'; document.body.style.background = 'transparent';
    const host = document.getElementById('ras'), seenH = {}, seenD = {};
    const add = (name, html, size) => { const d = document.createElement('div'); d.id = 'r-' + name; d.style.cssText = 'width:' + size + 'px;height:' + size + 'px;overflow:hidden;flex:none'; d.innerHTML = html; const s = d.querySelector('svg'); if (s) { s.style.width = size + 'px'; s.style.height = size + 'px'; s.style.display = 'block'; } host.appendChild(d); out.push(name); };
    Object.keys(C.items).forEach(k => {
      const it = C.items[k];
      if (it.slot === 'house') { if (!seenH[it.a.t]) { seenH[it.a.t] = 1; add('h-' + it.a.t, '<svg viewBox="0 0 200 160" xmlns="http://www.w3.org/2000/svg">' + (A.house(it.a.t).replace(/^<svg[^>]*>|<\/svg>$/g, '')) + '</svg>', 80); } }
      else if (it.slot === 'decor') { if (!seenD[it.a.t]) { seenD[it.a.t] = 1; add('d-' + it.a.t, A.decor(it.a.t), 40); } }
      if (!['bg', 'species'].includes(it.slot)) { const h = A.itemIcon(k, it); if (h && h.indexOf('<svg') === 0) add('i-' + k, h, 40); }
    });
    return out;
  });
  for (const n of list) await p.locator('#r-' + n).screenshot({ path: 'ras/' + n + '.png', omitBackground: true });
  console.log(list.length);
  await b.close();
})();
