// C5: stilių palyginimas - nuotraukos nemato uždarų meniu, tamsios temos ir pločių tarp 375/768/1440.
// Kiekvienam elementui, kurį liečia tikrinamas CSS failas, įrašomi apskaičiuoti stiliai:
//   visi pločiai prie lūžio taškų (abiejose pusėse) × būsenos: įkelta / atidaryti meniu / atidaryti meniu + tamsi tema.
// Paleidžia .github/workflows/visual.yml (bazinė šaka ir nauja - tas pats testas, tie patys selektoriai), lygina compare.js.
//   STYLE_CSS    - CSS failai (kableliais), iš kurių renkami selektoriai (bazinis ir naujas variantas - abu)
//   STYLE_OUT    - aplankas JSON failams (numatyta tests/visual-styles)
//   STYLE_WIDTHS - pločiai (kableliais)
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const { test } = require('@playwright/test');
const { login, BASE_URL } = require('../e2e/helpers');
const { PAGES } = require('./pages');

// Pirmas: „👁 Tėvai“ (.view-switch.is-test) - pirmą kartą persijungus sukuriamos testinės paskyros, todėl visi kiti mato tuos pačius duomenis
const FIRST = [['ziuri-kaip-tevai', 'paskyra.php', 'info@karateka.lt', { view: 'parent' }]];
const EXTRA = [
  ['be-meniu', 'privatumas.php?be_meniu=1', null],   // „bare“ antraštė (egzamino nuoroda)
  ['admin-isjungtas', 'index.php', 'info@karateka.lt', { adm: '0' }],   // „🛠 Grįžti į valdymą“
];
const WIDTHS = (process.env.STYLE_WIDTHS
  || '375,400,401,480,481,520,521,768,769,860,861,900,901,1099,1100,1101,1280,1281,1400,1401,1440,2560').split(',').map(Number);
const OUT = process.env.STYLE_OUT || path.join(__dirname, '../visual-styles');
const CSS_FILES = (process.env.STYLE_CSS || path.join(__dirname, '../../public_html/assets/header.css')).split(',').filter(Boolean);
const CSS_TEXT = CSS_FILES.map(f => fs.readFileSync(f, 'utf8'));

const PROPS = ['display', 'position', 'top', 'right', 'bottom', 'left', 'z-index', 'box-sizing', 'width', 'height',
  'min-width', 'min-height', 'max-width', 'max-height', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
  'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
  'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
  'border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style',
  'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color',
  'border-top-left-radius', 'border-top-right-radius', 'border-bottom-left-radius', 'border-bottom-right-radius',
  'background-color', 'background-image', 'background-size', 'background-position-x', 'background-position-y', 'background-repeat',
  'color', 'opacity', 'visibility', 'overflow-x', 'overflow-y', 'font-family', 'font-size', 'font-weight', 'font-style',
  'line-height', 'letter-spacing', 'text-align', 'text-decoration-line', 'text-transform', 'text-shadow', 'white-space',
  'vertical-align', 'list-style-type', 'cursor', 'box-shadow', 'filter', 'transform', 'translate', 'rotate',
  'transition-property', 'transition-duration', 'transition-timing-function', 'transition-delay',
  'flex-direction', 'flex-wrap', 'flex-grow', 'flex-shrink', 'flex-basis', 'order', 'align-items', 'align-self',
  'justify-content', 'justify-items', 'row-gap', 'column-gap', 'grid-template-columns', 'grid-column-start', 'grid-column-end',
  'object-fit', 'content', 'outline-width', 'outline-style', 'outline-color', 'outline-offset', 'user-select',
  'scrollbar-width', 'scrollbar-gutter', 'pointer-events', 'inset-inline-start', 'text-overflow'];

// Naršyklėje: selektorių elementai ir jų stiliai. Grąžina { raktas: [klasės, reikšmės|...] }
function dumpStyles({ cssText, props }) {
  const split = s => { const out = []; let d = 0, cur = ''; for (const ch of s) { if (ch === '(' || ch === '[') d++; if (ch === ')' || ch === ']') d--; if (ch === ',' && d === 0) { out.push(cur.trim()); cur = ''; } else cur += ch; } if (cur.trim()) out.push(cur.trim()); return out; };
  const sels = new Set();
  const walk = rules => { for (const r of rules) { if (r.selectorText) split(r.selectorText).forEach(x => sels.add(x)); if (r.cssRules) walk(r.cssRules); } };
  for (const t of cssText) { const sh = new CSSStyleSheet(); sh.replaceSync(t); walk(sh.cssRules); }
  const els = new Map();   // elementas -> pseudo elementai
  const add = (el, ps) => { if (!els.has(el)) els.set(el, new Set()); if (ps) els.get(el).add(ps); };
  add(document.documentElement); add(document.body, '::after');
  for (const s of sels) {
    const ps = /::before\b/.test(s) ? '::before' : (/::after\b/.test(s) ? '::after' : null);
    let q = s.replace(/::?(before|after)\b/g, '').replace(/::-webkit-[a-z-]+/g, '')
      .replace(/:(hover|focus-visible|focus-within|focus|active)\b/g, '').trim();
    if (!q || /[>+~]$/.test(q)) q += '*';
    let list = [];
    try { list = document.querySelectorAll(q); } catch (e) { continue; }
    list.forEach(el => add(el, ps));
  }
  const key = el => { const parts = []; for (let e = el; e && e.nodeType === 1; e = e.parentElement) { let i = 1; for (let s = e.previousElementSibling; s; s = s.previousElementSibling) i++; parts.unshift(e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + ':' + i); } return parts.join('>'); };
  const out = {};
  for (const [el, pss] of els) {
    const k = key(el), cls = (el.getAttribute('class') || '') + (el.hasAttribute('hidden') ? ' [hidden]' : '');
    for (const ps of [null, ...pss]) {
      const cs = getComputedStyle(el, ps);
      if (ps && (cs.content === 'none' || cs.content === 'normal')) { out[k + ps] = [cls, 'none']; continue; }
      out[k + (ps || '')] = [cls, props.map(p => cs.getPropertyValue(p)).join('|')];
    }
  }
  return out;
}

// Būsenos - tas pats DOM pakeitimas abiejose šakose
async function setState(p, state) {
  if (state === 'open' || state === 'dark-open') {
    await p.evaluate(() => {
      document.querySelectorAll('details').forEach(d => { d.open = true; });
      document.querySelectorAll('.site-header ul.nav-list').forEach(u => u.classList.add('nav-open'));
      document.querySelectorAll('.nav-onpage.folded').forEach(u => u.classList.add('open'));
      if (document.querySelector('.m-more')) document.body.classList.add('m-more-open');
    });
  }
  if (state === 'dark-open') {
    await p.evaluate(() => { document.body.classList.add('theme-dark'); document.body.setAttribute('data-theme', 'dark'); });
  }
}

async function settle(p) {
  await p.evaluate(() => new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r))));
  await p.evaluate(() => document.getAnimations().forEach(a => { try { if (a.effect && a.effect.getTiming().iterations === Infinity) { a.pause(); a.currentTime = 0; } else a.finish(); } catch (e) {} }));
}

fs.mkdirSync(OUT, { recursive: true });
for (const [name, url, who, opt] of FIRST.concat(PAGES, EXTRA)) {
  test(`stiliai ${name}`, async ({ browser }) => {
    test.setTimeout(300_000);
    const p = await login(browser, who, 1440, 900);
    if (opt && opt.adm) await p.context().addCookies([{ name: 'adm', value: opt.adm, url: BASE_URL }]);
    if (opt && opt.view) {
      await p.goto('index.php');
      await Promise.all([p.waitForNavigation(), p.evaluate(v => { const b = document.querySelector('.vs-modes button[value="' + v + '"]'); b.form.requestSubmit(b); }, opt.view)]);
    }
    await p.goto(url);
    await p.waitForLoadState('networkidle').catch(() => {});
    // Valdymo meniu tvarkymas (✎) - atidaromas atskirai, kad būtų matomi ir jo elementai
    const res = {};
    for (const state of ['load', 'open', 'dark-open']) {
      if (state !== 'load') { await p.goto(url); await p.waitForLoadState('networkidle').catch(() => {}); await setState(p, state); }
      for (const w of WIDTHS) {
        await p.setViewportSize({ width: w, height: 900 });
        await settle(p);
        res[`${state}@${w}`] = await p.evaluate(dumpStyles, { cssText: CSS_TEXT, props: PROPS });
      }
    }
    const edit = p.locator('.am-edit-btn').first();
    if (await edit.count()) {
      await p.goto(url); await p.waitForLoadState('networkidle').catch(() => {});
      await p.setViewportSize({ width: 1440, height: 900 });
      await p.locator('.am-edit-btn').first().click().catch(() => {});
      await settle(p);
      for (const w of [1101, 1440, 2560]) {
        await p.setViewportSize({ width: w, height: 900 });
        await settle(p);
        res[`edit@${w}`] = await p.evaluate(dumpStyles, { cssText: CSS_TEXT, props: PROPS });
      }
    }
    fs.writeFileSync(path.join(OUT, `${name}.json.gz`), zlib.gzipSync(JSON.stringify({ props: PROPS, res })));
  });
}
