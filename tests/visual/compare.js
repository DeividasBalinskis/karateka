// C5: griežtas palyginimas (0 pikselių leidžiama) - bazinės ir naujos šakos nuotraukos ir apskaičiuoti stiliai.
//   node compare.js <nuotraukos-A> <nuotraukos-B> <stiliai-A> <stiliai-B> <rezultatų aplankas>
// Reikia pngjs ir pixelmatch (workflow įdiegia laikinai). Rašo <rez>/santrauka.md ir skirtumų nuotraukas; klaida, jei yra skirtumų.
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const { PNG } = require('pngjs');
const pixelmatch = require('pixelmatch');

const [shotsA, shotsB, stylesA, stylesB, out] = process.argv.slice(2);
fs.mkdirSync(out, { recursive: true });
const md = [];
let bad = 0;

// 1. Nuotraukos
const pngs = d => (fs.existsSync(d) ? fs.readdirSync(d).filter(f => f.endsWith('.png')).sort() : []);
const names = [...new Set(pngs(shotsA).concat(pngs(shotsB)))].sort();
const rows = [];
for (const f of names) {
  const a = path.join(shotsA, f), b = path.join(shotsB, f);
  if (!fs.existsSync(a) || !fs.existsSync(b)) { rows.push([f, 'trūksta ' + (fs.existsSync(a) ? 'B' : 'A')]); bad++; continue; }
  if (fs.readFileSync(a).equals(fs.readFileSync(b))) { rows.push([f, 0]); continue; }
  const A = PNG.sync.read(fs.readFileSync(a)), B = PNG.sync.read(fs.readFileSync(b));
  if (A.width !== B.width || A.height !== B.height) {
    rows.push([f, `dydis ${A.width}×${A.height} → ${B.width}×${B.height}`]); bad++;
    fs.copyFileSync(a, path.join(out, f.replace('.png', '-A.png'))); fs.copyFileSync(b, path.join(out, f.replace('.png', '-B.png')));
    continue;
  }
  const D = new PNG({ width: A.width, height: A.height });
  const n = pixelmatch(A.data, B.data, D.data, A.width, A.height, { threshold: 0, includeAA: true });
  rows.push([f, n]);
  if (n) {
    bad++;
    fs.writeFileSync(path.join(out, f.replace('.png', '-skirtumas.png')), PNG.sync.write(D));
    fs.copyFileSync(a, path.join(out, f.replace('.png', '-A.png'))); fs.copyFileSync(b, path.join(out, f.replace('.png', '-B.png')));
  }
}
const same = rows.filter(r => r[1] === 0).length;
md.push(`## Nuotraukos: ${same} / ${rows.length} vienodos (0 px skirtumo)`, '');
const diffRows = rows.filter(r => r[1] !== 0);
if (diffRows.length) { md.push('| Nuotrauka | Skirtingi pikseliai |', '|---|---|'); diffRows.forEach(r => md.push(`| ${r[0]} | ${r[1]} |`)); md.push(''); }

// 2. Stiliai
const gz = d => (fs.existsSync(d) ? fs.readdirSync(d).filter(f => f.endsWith('.json.gz')).sort() : []);
const files = [...new Set(gz(stylesA).concat(gz(stylesB)))].sort();
const lines = [];
let checked = 0, diffs = 0;
const byProp = {};
for (const f of files) {
  const pa = path.join(stylesA, f), pb = path.join(stylesB, f);
  if (!fs.existsSync(pa) || !fs.existsSync(pb)) { lines.push(`- ${f}: trūksta ${fs.existsSync(pa) ? 'B' : 'A'}`); diffs++; continue; }
  const A = JSON.parse(zlib.gunzipSync(fs.readFileSync(pa))), B = JSON.parse(zlib.gunzipSync(fs.readFileSync(pb)));
  const props = A.props;
  for (const st of Object.keys(A.res).concat(Object.keys(B.res).filter(k => !(k in A.res)))) {
    const ea = A.res[st] || {}, eb = B.res[st] || {};
    for (const k of new Set(Object.keys(ea).concat(Object.keys(eb)))) {
      checked++;
      const va = ea[k], vb = eb[k];
      if (va && vb && va[1] === vb[1]) continue;
      diffs++;
      const page = f.replace('.json.gz', '');
      if (!va || !vb) { if (lines.length < 400) lines.push(`- ${page} ${st} \`${k.split('>').slice(-3).join('>')}\` (${(va || vb)[0]}): elementas tik ${va ? 'A' : 'B'}`); continue; }
      const xa = va[1].split('|'), xb = vb[1].split('|');
      const ch = [];
      if (xa.length !== xb.length) ch.push(`${va[1]} → ${vb[1]}`);
      else props.forEach((p, i) => { if (xa[i] !== xb[i]) { ch.push(`${p}: ${xa[i]} → ${xb[i]}`); byProp[p] = (byProp[p] || 0) + 1; } });
      if (lines.length < 400) lines.push(`- ${page} ${st} \`${k.split('>').slice(-3).join('>')}\` (${vb[0]}): ${ch.join('; ')}`);
    }
  }
}
md.push(`## Stiliai: ${checked - diffs} / ${checked} elementų būsenų vienodi`, '');
if (diffs) {
  bad++;
  md.push('Skirtumai pagal savybę: ' + Object.entries(byProp).sort((a, b) => b[1] - a[1]).map(([p, n]) => `${p} ${n}`).join(', '), '');
  md.push(...lines);
  if (diffs > lines.length) md.push(`- … ir dar ${diffs - lines.length}`);
}
fs.writeFileSync(path.join(out, 'santrauka.md'), md.join('\n') + '\n');
console.log(md.join('\n'));
process.exit(bad ? 1 : 0);
