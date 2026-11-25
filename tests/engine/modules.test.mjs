// D3: assets/varzybos/ moduliai - sintaksė ir importai (ar toks eksportas yra), dvi vienodos funkcijos viename faile,
// variklis be naršyklės (tik rules + engine), niekas neimportuoja main.js (jis paleidžia viską paskutinis)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('../../public_html/assets/varzybos/', import.meta.url));
const walk = (d) => fs.readdirSync(d, { withFileTypes: true }).flatMap((e) =>
  e.isDirectory() ? walk(path.join(d, e.name)) : e.name.endsWith('.js') ? [path.join(d, e.name)] : []);
const files = walk(ROOT).sort();
const rel = (f) => path.relative(ROOT, f).split(path.sep).join('/');
const src = Object.fromEntries(files.map((f) => [f, fs.readFileSync(f, 'utf8')]));
const imports = (f) => [...src[f].matchAll(/^import (?:[^;]*? from )?'([^']+)';/gms)].map((m) => path.resolve(path.dirname(f), m[1]));

test('module files: main.js and the engine are there', () => {
  const names = files.map(rel);
  for (const n of ['main.js', 'rules.js', 'store.js', 'engine/draw.js', 'engine/bracket.js', 'engine/rr.js', 'engine/score.js', 'engine/category.js']) {
    assert.ok(names.includes(n), n);
  }
});

test('every import points to an existing module, nothing imports main.js', () => {
  for (const f of files) {
    for (const d of imports(f)) {
      assert.ok(src[d] !== undefined, `${rel(f)} imports a missing file ${d}`);
      assert.notEqual(rel(d), 'main.js', `${rel(f)} imports main.js`);
    }
  }
});

test('engine and rules import only engine and rules (they run without a browser)', () => {
  for (const f of files.filter((x) => /^(engine\/|rules\.js)/.test(rel(x)))) {
    for (const d of imports(f)) assert.match(rel(d), /^(engine\/|rules\.js)/, `${rel(f)} imports ${rel(d)}`);
    assert.doesNotMatch(src[f], /\b(window|document|localStorage|sessionStorage)\b/, `${rel(f)} uses the browser`);
  }
});

test('no two top-level functions with the same name in one file', () => {
  for (const f of files) {
    const seen = new Set();
    for (const m of src[f].matchAll(/^(?:export )?function ([A-Za-z_$][\w$]*)/gm)) {
      assert.ok(!seen.has(m[1]), `${rel(f)}: function ${m[1]} twice`);
      seen.add(m[1]);
    }
  }
});

// Sintaksė ir importų vardai - tikru ES modulių susiejimu (be paleidimo); reikia node --experimental-vm-modules
test('all modules parse and link (imported names are exported)', { skip: !vm.SourceTextModule && 'needs node --experimental-vm-modules' }, async () => {
  const mods = new Map(files.map((f) => [f, new vm.SourceTextModule(src[f], { identifier: f })]));
  const main = mods.get(path.join(ROOT, 'main.js'));
  await main.link((spec, ref) => mods.get(path.resolve(path.dirname(ref.identifier), spec)));
  for (const [f, m] of mods) assert.equal(m.status, 'linked', rel(f));
});
