// Test varžybos: +100 išgalvotų dalyvių, „🎲 Sužaisti“ (atsitiktiniai nugalėtojai)
import { CFG, HOME, newId, toast, YEAR } from './util.js';
import { cfg, defaultSystem } from './rules.js';
import { makeDraw } from './engine/draw.js';
import { allMatches, bracket } from './engine/bracket.js';
import { rrMatches } from './engine/rr.js';
import { scoreFinalists } from './engine/score.js';
import { drawStale } from './engine/category.js';
import { cat, S, save, snapshot } from './store.js';
import { render } from './chrome.js';
import { autoCategories, toastPlaced } from './views/categories.js';

// ---------- 🧪 +100 testinių dalyvių: išgalvoti vaikai ir suaugę iš kitų klubų (pažymėti test: true, ištrinami vienu mygtuku) ----------
var TEST_FIRST = { M: ['Jonas', 'Lukas', 'Matas', 'Domas', 'Tomas', 'Paulius', 'Rokas', 'Kajus', 'Nojus', 'Augustas', 'Ignas', 'Dovydas', 'Herkus', 'Benas', 'Jokūbas', 'Adomas'],
  F: ['Ona', 'Ieva', 'Gabija', 'Emilija', 'Austėja', 'Kamilė', 'Urtė', 'Gustė', 'Liepa', 'Saulė', 'Rugilė', 'Smiltė', 'Viltė', 'Elzė', 'Luknė', 'Goda'] };
var TEST_LAST = ['Kazlauskas', 'Petrauskas', 'Jankauskas', 'Stankevičius', 'Vasiliauskas', 'Žukauskas', 'Butkus', 'Paulauskas', 'Urbonas', 'Kavaliauskas',
  'Navickas', 'Rimkus', 'Balčiūnas', 'Mockus', 'Šimkus', 'Lukoševičius', 'Morkūnas', 'Grigas', 'Sakalauskas', 'Vaitkus'];
function testFemale(l) { return /ius$/.test(l) ? l.replace(/ius$/, 'iūtė') : l.replace(/as$/, 'aitė').replace(/us$/, 'utė').replace(/is$/, 'ytė'); }
function rnd(a) { return a[Math.floor(Math.random() * a.length)]; }
function testCount() { return S && S.people ? Object.keys(S.people).filter(function (k) { return S.people[k].test; }).length : 0; }
export function testButtons() { var n = testCount(), d = document.getElementById('ppl-test-del'); if (d) { d.hidden = !n; d.textContent = 'Ištrinti testinius (' + n + ')'; } }
document.getElementById('ppl-test100').addEventListener('click', function () {
  if (!confirm('Pridėti 100 išgalvotų dalyvių iš kitų klubų? Jie pažymėti kaip testiniai - vėliau ištrinsite vienu mygtuku.')) return;
  snapshot();
  var clubs = S.clubs.filter(function (c) { return c !== HOME; });
  if (!clubs.length) { clubs = ['Testo klubas A', 'Testo klubas B', 'Testo klubas C']; clubs.forEach(function (c) { if (S.clubs.indexOf(c) === -1) S.clubs.push(c); }); }
  var used = {}, ids = [];
  Object.keys(S.people).forEach(function (k) { used[S.people[k].name] = true; });
  for (var i = 0; i < 100; i++) {
    var g = Math.random() < 0.5 ? 'M' : 'F', name = '';
    for (var guard = 0; guard < 40; guard++) { var l = rnd(TEST_LAST); name = rnd(TEST_FIRST[g]) + ' ' + (g === 'F' ? testFemale(l) : l); if (!used[name]) break; }
    if (used[name]) name += ' ' + (i + 1);
    used[name] = true;
    var age = rnd([6, 7, 8, 8, 9, 9, 10, 10, 11, 11, 12, 12, 13, 13, 14, 15, 16, 17, 19, 24, 31, 38]);
    var belt = Math.max(1, Math.min(12, Math.round((age - 5) / 1.6) + Math.floor(Math.random() * 4) - 1));
    var id = newId('x') + i;
    S.people[id] = { id: id, name: name, club: rnd(clubs), birth: YEAR - age, gender: g, belt: belt, member: null, test: true };
    ids.push(id);
  }
  if (!S.categories.some(function (c) { return !c.team; })) autoCategories();   // kategorijų dar nėra - sudaromos
  else toastPlaced(ids);                                                     // yra - kiekvienas į tinkamas
  save(); render(); testButtons();
  toast('Pridėta 100 testinių dalyvių. Ištrinti - „Ištrinti testinius“.');
});
document.getElementById('ppl-test-del').addEventListener('click', function () {
  var n = testCount(); if (!n || !confirm('Ištrinti visus testinius dalyvius (' + n + ') iš varžybų ir kategorijų?')) return;
  snapshot();
  var gone = {};
  Object.keys(S.people).forEach(function (k) { if (S.people[k].test) { gone[k] = true; delete S.people[k]; } });
  S.categories.forEach(function (c) { c.competitors = c.competitors.filter(function (x) { return !gone[x.id]; }); });
  Object.keys(S.teams).forEach(function (k) { S.teams[k].members = S.teams[k].members.filter(function (m) { return !gone[m]; }); });
  save(); render(); testButtons();
});

// ---------- Testui: atsitiktiniai kovų nugalėtojai ----------
function playCategory(c, limit) {
  if (c.competitors.length < 2) return;
  if (!c.draw || drawStale(c)) c.draw = makeDraw(c, c.draw ? c.draw.system : defaultSystem(c));
  var rnd = function () { return Math.round((6 + Math.random() * 3) * 10) / 10; };
  if (c.draw.system === 'rr') {
    rrMatches(c).slice(0, limit * 3).forEach(function (m) { if (!m.winner) c.draw.results[m.id] = Math.random() < 0.5 ? m.a : m.b; });
    return;
  }
  if (c.draw.system === 'score') {
    var f = cfg(c), ids = (c.draw.order || []).slice(0, limit), j;
    // Trūkstami teisėjų balai užpildomi (jau įrašyti lieka)
    var fill = function (src, id) { var arr = src[id] = src[id] || []; for (j = 0; j < f.judges; j++) if (arr[j] === undefined || arr[j] === null || isNaN(parseFloat(arr[j]))) arr[j] = rnd(); };
    c.draw.scores = c.draw.scores || {};
    ids.forEach(function (id) { fill(c.draw.scores, id); });
    if (f.rounds === 2 && limit >= ids.length) { c.draw.final = c.draw.final || {}; scoreFinalists(c).forEach(function (id) { fill(c.draw.final, id); }); }
    return;
  }
  for (var n = 0; n < limit; n++) {
    var m = allMatches(bracket(c)).find(function (x) { return x.a && x.b && !x.winner; });
    if (!m) break;
    c.draw.results[m.id] = Math.random() < 0.5 ? m.a : m.b;
  }
}
export function simulate(frac) {
  var cats = S.categories.filter(function (c) { return c.competitors.length >= 2; }), full = Math.round(cats.length * frac);
  cats.forEach(function (c, i) { playCategory(c, i < full ? 999 : i === full ? 2 : 0); });
}
if (CFG.isTest) {
  document.getElementById('draw-sim').addEventListener('click', function () { var c = cat(S.selected); if (!c) return; snapshot(); playCategory(c, 999); save(); render(); });
  document.getElementById('res-sim').addEventListener('click', function () { snapshot(); simulate(1); save(); render(); });
}
