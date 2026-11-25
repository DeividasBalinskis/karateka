// Tinklai: amžiaus grupės ir jų lentelės (tik peržiūra)
import { CFG, esc, KEY } from '../util.js';
import { catFinished } from '../engine/category.js';
import { S } from '../store.js';
import { openRun } from '../chrome.js';
import { crit } from './categories.js';
import { areaOf } from '../bout.js';
import { ageKey, ageName, catBracket, catStatus, stChip } from '../results.js';

// ---------- Tinklai (Draws): amžiaus grupės → visos tos grupės rungtys - kas su kuo kovoja, kas laimėjo (tik peržiūra) ----------
var tkGroup = null;
try { tkGroup = sessionStorage.getItem(KEY + '-tk'); } catch (e) {}
function drawGroups() {
  var map = {}, list = [];
  S.categories.forEach(function (c) {
    if (!c.competitors.length) return;
    var k = ageKey(c), y = crit(c).y1 || crit(c).y0 || 0;
    if (!map[k]) { map[k] = { key: k, name: ageName(c), cats: [], y: y }; list.push(map[k]); }
    map[k].cats.push(c);
  });
  return list.sort(function (a, b) { return b.y - a.y || a.name.localeCompare(b.name); });   // jauniausi pirma
}
function bracketHtml(b) {
  var h = '';
  if (b.places && b.places.length) h += '<div class="gv-places">' + b.places.map(function (p) { return '<div class="rp"><span class="medal ' + ['', 'g', 's', 'b'][p.p] + '">' + p.p + '</span><span>' + esc(p.n) + ' <span class="club">' + esc(p.c) + '</span></span></div>'; }).join('') + '</div>';
  if (b.people) h += '<p class="muted small">Lentelė dar nesudaryta. Dalyviai:</p><ul class="gv-people">' + b.people.map(function (p) { return '<li>' + esc(p.n) + ' <span class="muted">' + esc(p.c) + '</span></li>'; }).join('') + '</ul>';
  (b.groups || []).forEach(function (g) {
    h += '<h4>' + esc(g.t) + '</h4>';
    if (g.score) h += '<ol class="gv-score">' + g.rows.map(function (r) { return '<li><span>' + esc(r.n) + ' <span class="muted">' + esc(r.c) + (r.k ? ' · ' + esc(r.k) : '') + '</span></span><b>' + esc(r.s || '–') + '</b></li>'; }).join('') + '</ol>';
    else h += '<div class="gv-matches">' + g.matches.map(function (m) {
      return '<div class="gv-m' + (m.w ? ' done' : '') + '"><span class="gv-mno">' + esc(m.no) + '</span>' +
        '<span class="gv-mp' + (m.w === 'a' ? ' won' : '') + '"><b class="aka-t">' + esc(m.a || '…') + '</b> <small>' + esc(m.ac) + '</small></span>' +
        '<span class="gv-ms">' + esc(m.s || (m.w ? '–' : 'vs')) + '</span>' +
        '<span class="gv-mp' + (m.w === 'b' ? ' won' : '') + '"><b>' + esc(m.b || '…') + '</b> <small>' + esc(m.bc) + '</small></span></div>';
    }).join('') + '</div>';
  });
  return h;
}
// Tinklai - medis: ratai stulpeliais (ketvirtfinalis → pusfinalis → finalas), laimėtojas paryškintas
function treeHtml(b) {
  var rounds = (b.groups || []).filter(function (g) { return g.round !== undefined; });
  if (!rounds.length) return bracketHtml(b);
  var rest = { places: b.places, groups: (b.groups || []).filter(function (g) { return g.round === undefined; }) };
  var side = function (m, k) { var won = m.w === k; return '<div class="tk-p ' + (k === 'a' ? 'aka' : 'shiro') + (won ? ' won' : m.w ? ' lost' : '') + '"><b>' + esc(m[k] || '…') + '</b><small>' + esc(m[k + 'c']) + '</small>' + (won ? '<i>✓</i>' : '') + '</div>'; };
  var h = '<div class="tk-tree">' + rounds.map(function (g, i) {
    return '<div class="tk-col' + (i === rounds.length - 1 ? ' last' : '') + '"><h4>' + esc(g.t) + '</h4><div class="tk-col-body">' + g.matches.map(function (m) {
      return '<div class="tk-m' + (m.w ? ' done' : '') + '"><span class="tk-no">' + esc(m.no) + (m.s ? ' · ' + esc(m.s) : '') + '</span>' + side(m, 'a') + side(m, 'b') + '</div>';
    }).join('') + '</div></div>';
  }).join('') + '</div>';
  return (rest.places && rest.places.length ? bracketHtml({ places: rest.places }) : '') + h + bracketHtml({ groups: rest.groups });
}
export function renderDraws() {
  var groups = drawGroups(), cur = groups.find(function (g) { return g.key === tkGroup; }) || groups[0];
  document.getElementById('tk-groups').innerHTML = groups.map(function (g) {
    var st = g.cats.every(function (c) { return catFinished(c); }) ? 'fin' : g.cats.some(function (c) { return catStatus(c) === 'run'; }) ? 'run' : 'wait', n = 0;
    g.cats.forEach(function (c) { n += c.competitors.length; });
    return '<button type="button" class="tk-g' + (cur && g.key === cur.key ? ' on' : '') + '" data-tk="' + esc(g.key) + '"><b>' + esc(g.name) + '</b><span class="tk-meta">' +
      g.cats.map(function (c) { return '<span class="disc d-' + c.discipline + '"></span>'; }).join('') + ' ' + g.cats.length + ' rungt. · ' + n + ' dalyv.</span>' + stChip(st) + '</button>';
  }).join('') || '<p class="muted small">Kategorijų su dalyviais dar nėra.</p>';
  document.getElementById('tk-view').innerHTML = cur ? '<h2 class="tk-title">' + esc(cur.name) + ' <span class="muted small">' + cur.cats.length + ' rungt.</span></h2><div class="tk-cats">' + cur.cats.map(function (c) {
    var a = areaOf(c);
    // #170: kategorijos pavadinimas irgi atidaro valdymą (kaip „Valdyti →“)
    return '<section class="panel tk-cat"><div class="tk-cat-head"><span class="disc d-' + c.discipline + '"></span>' +
      (CFG.readOnly ? '<b>' + esc(c.name) + '</b>' : '<button type="button" class="tk-cat-name" data-tk-open="' + c.id + '" title="Atidaryti Live tvarkaraštyje">' + esc(c.name) + '</button>') + '<span class="muted small">' + (a ? esc(a.name) + ' · ' : '') + c.competitors.length + ' dalyv.</span>' +
      '<span class="spacer"></span>' + stChip(catStatus(c)) + (CFG.readOnly ? '' : '<button type="button" class="btn btn-xs" data-tk-open="' + c.id + '" title="Atidaryti Live tvarkaraštyje">Valdyti →</button>') + '</div>' +
      '<div class="tk-body">' + treeHtml(catBracket(c, true)) + '</div></section>';
  }).join('') + '</div>' : '';
}
document.getElementById('v-tinklai').addEventListener('click', function (e) {
  var g = e.target.closest('[data-tk]');
  if (g) { tkGroup = g.dataset.tk; try { sessionStorage.setItem(KEY + '-tk', tkGroup); } catch (x) {} renderDraws(); if (window.innerWidth < 900) document.getElementById('tk-view').scrollIntoView({ block: 'start' }); return; }
  var o = e.target.closest('[data-tk-open]'); if (o) openRun(o.dataset.tkOpen);
});
