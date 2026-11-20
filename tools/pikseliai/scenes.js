// SVG scenos → mažos rastrinės (vėliau PIL sumažina spalvas) - pikselinis fonas
const { chromium } = require('playwright');
const fs = require('fs');
(async () => {
  const dir = require('path').resolve(__dirname, '../../public_html/img/temos') + '/';
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1600, height: 1000 }, deviceScaleFactor: 1 });
  for (const f of fs.readdirSync(dir).filter(f => f.endsWith('.svg'))) {
    await p.goto('file://' + dir + f);
    await p.screenshot({ path: 'raw-' + f.replace('.svg', '.png') });
  }
  await b.close();
})();
