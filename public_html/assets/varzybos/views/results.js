// Rezultatų skirtukas: diplomai, apdovanojimų eilė, medaliai, CSV
import { CFG, esc, hhmm, toast, toMin } from '../util.js';
import { cfg, DISC, person } from '../rules.js';
import { allMatches, bracket } from '../engine/bracket.js';
import { rrMatches } from '../engine/rr.js';
import { scoreFinalists, scoreTotal } from '../engine/score.js';
import { doneCount, drawStale, matchCount } from '../engine/category.js';
import { AW, S, setAward } from '../store.js';
import { openRun } from '../chrome.js';
import { diplomaUrl } from './draw.js';
import { areaOf, matchTitle, pubName } from '../bout.js';
import { minutesFor, placesShort } from '../schedule.js';
import { catStatus, results, stChip } from '../results.js';

// ---------- 🎓 Diplomai: šablono įkėlimas (bendras visoms varžyboms) ir atsisiuntimas / spausdinimas ----------
function renderDiplomaMenu() {
  var box = document.getElementById('dipl-body'); if (!box) return;
  var tpl = CFG.diplomaTpl, n = 0;
  results().forEach(function (r) { n += r.places.length; });
  box.innerHTML = '<b>🎓 Diplomai prizininkams</b>' +
    (n ? '<p class="small muted">Prizininkų dabar: <b>' + n + '</b>. Diplomai - ir prie kiekvienos kategorijos „Vietos“.</p>' : '<p class="small muted">Prizininkų dar nėra - diplomai atsiras, kai kategorijos bus baigtos.</p>') +
    (tpl ? '<a class="btn btn-primary btn-sm" href="' + esc(diplomaUrl('', false)) + '" target="_blank" rel="noopener">⬇ Excel pagal šabloną (visi)</a>' : '') +
    '<a class="btn btn-sm" href="' + esc(diplomaUrl('', true)) + '" target="_blank" rel="noopener">🖨 Spausdinti paprastus (visi)</a>' +
    '<div class="dipl-tpl"><b class="small">Jūsų diplomo šablonas (Excel)</b>' +
    (tpl ? '<p class="small">📄 ' + esc(tpl.name) + ' <span class="muted">· ' + esc(tpl.at) + '</span>' + (tpl.keys && tpl.keys.length ? '<br><span class="muted">Rastos žymos: ' + esc(tpl.keys.join(' ')) + '</span>' : '<br><b class="warn-t">Žymų nerasta</b> - įrašykite jas į langelius (žr. žemiau).') + '</p>' : '<p class="small muted">Neįkeltas - spausdinami paprasti diplomai.</p>') +
    '<p class="small muted">Excel faile (pirmas lapas - vienas diplomas su jūsų dizainu) į tuščias vietas įrašykite: <code>{vardas}</code> <code>{vieta}</code> (1, 2, 3) <code>{vietaR}</code> (I, II, III) <code>{kategorija}</code> <code>{klubas}</code> <code>{varzybos}</code> <code>{data}</code> <code>{rungtis}</code>. ' +
    'Atsisiuntus - kiekvienas diplomas atskirame lape, spausdinkite visus iš karto.</p>' +
    '<div class="sp-row"><label class="btn btn-sm dipl-up">' + (tpl ? '↻ Pakeisti šabloną' : '⬆ Įkelti šabloną (.xlsx)') + '<input type="file" id="dipl-file" accept=".xlsx" hidden></label>' +
    (tpl ? '<button type="button" class="btn btn-sm btn-danger" id="dipl-del">Ištrinti šabloną</button>' : '') + '</div><span class="small" id="dipl-msg" role="status"></span></div>';
}
var diplMenu = document.getElementById('dipl-menu');
if (diplMenu) {
  diplMenu.addEventListener('toggle', function () { if (diplMenu.open) renderDiplomaMenu(); });
  diplMenu.addEventListener('change', function (e) {
    if (e.target.id !== 'dipl-file' || !e.target.files[0]) return;
    var fd = new FormData(); fd.append('_csrf', CFG.csrf); fd.append('file', e.target.files[0]);
    document.getElementById('dipl-msg').textContent = 'Įkeliama…';
    fetch(CFG.diplomaApi, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok) { document.getElementById('dipl-msg').textContent = d.error || 'Nepavyko'; return; }
      CFG.diplomaTpl = d.tpl; renderDiplomaMenu(); toast('Diplomo šablonas įkeltas ✓');
    }).catch(function () { document.getElementById('dipl-msg').textContent = 'Nepavyko - patikrinkite ryšį.'; });
  });
  diplMenu.addEventListener('click', function (e) {
    if (e.target.id !== 'dipl-del' || !confirm('Ištrinti diplomo šabloną? Bus spausdinami paprasti diplomai.')) return;
    var fd = new FormData(); fd.append('_csrf', CFG.csrf); fd.append('action', 'delete');
    fetch(CFG.diplomaApi, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.ok) { CFG.diplomaTpl = null; renderDiplomaMenu(); } else document.getElementById('dipl-msg').textContent = d.error || 'Nepavyko';
    });
  });
}
// Žiūrovams ir ekranams: ant kiekvieno tatamio - kategorijos su laikais ir kas eina toliau (vaikų vardai sutrumpinti)
export function upcoming(c) {
  if (!c.draw || drawStale(c) || c.competitors.length < 2) return [];
  if (c.draw.system === 'score') {
    var r1 = (c.draw.order || []).filter(function (id) { return person(c, id) && scoreTotal(c, (c.draw.scores || {})[id]) === null; })
      .map(function (id) { return { title: cfg(c).rounds === 2 ? 'Pusfinalis' : 'Pasirodymas', a: id, b: null, which: 'scores' }; });
    if (r1.length || cfg(c).rounds !== 2) return r1;
    return scoreFinalists(c).filter(function (id) { return scoreTotal(c, (c.draw.final || {})[id]) === null; })
      .map(function (id) { return { title: 'Finalas', a: id, b: null, which: 'final' }; });
  }
  var list = c.draw.system === 'rr' ? rrMatches(c) : allMatches(bracket(c));
  return list.filter(function (m) { return m.a && m.b && !m.winner; }).sort(function (x, y) { return (x.no || 0) - (y.no || 0); });
}
// 📹 YouTube nuoroda → įterpimo adresas (vaizdo įrašas / transliacija arba kanalo tiesioginė transliacija); kita - ''
export function ytEmbed(u) {
  u = String(u || '').trim(); if (!u) return '';
  var m = u.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|live\/|embed\/|shorts\/))([A-Za-z0-9_-]{11})/);
  if (m) return 'https://www.youtube-nocookie.com/embed/' + m[1] + '?autoplay=1&mute=1&playsinline=1';
  m = u.match(/youtube\.com\/channel\/(UC[A-Za-z0-9_-]{22})/);
  return m ? 'https://www.youtube.com/embed/live_stream?channel=' + m[1] + '&autoplay=1&mute=1' : '';
}
export function areasSummary() {
  return S.areas.map(function (a) {
    var t = toMin(a.start), cats = [], next = [], cur = false;
    S.categories.filter(function (c) { return c.tatami === a.id; }).forEach(function (c) {
      if (c.at) t = Math.max(t, toMin(c.at));
      var start = t, total = matchCount(c), done = Math.min(doneCount(c), total); t += minutesFor(c);
      // Dabartinė grupė - pirma nebaigta tatamio eilėje (ekrane ji didesnė)
      var isCur = !cur && total > 0 && done < total; if (isCur) cur = true;
      cats.push({ id: c.id, name: c.name, disc: c.discipline, start: hhmm(start), done: done, total: total, cur: isCur });
      upcoming(c).slice(0, Math.max(0, 6 - next.length)).forEach(function (m) {
        next.push({ catId: c.id, cat: c.name, disc: c.discipline, no: m.no || '', title: m.title || matchTitle(c, m), a: pubName(m.a), ac: (person(c, m.a) || {}).club || '',
          b: m.b ? pubName(m.b) : '', bc: m.b ? (person(c, m.b) || {}).club || '' : '' });
      });
    });
    return { id: a.id, name: a.name, end: hhmm(t), cats: cats, next: next, video: ytEmbed(a.stream) };
  });
}
export function medalTable(rs) {
  var med = {};
  rs.forEach(function (r) {
    r.places.forEach(function (p) { var m = med[p.club] = med[p.club] || { g: 0, s: 0, b: 0, pts: 0 }; m[['g', 's', 'b'][p.place - 1]]++; m.pts += 4 - p.place; });
  });
  return Object.keys(med).map(function (k) { return { club: k, m: med[k] }; }).sort(function (a, b) {
    return b.m.g - a.m.g || b.m.s - a.m.s || b.m.b - a.m.b || a.club.localeCompare(b.club, 'lt');
  });
}
function shownResults() {
  var rs = results(), onlyDone = document.getElementById('res-done').checked;
  return { all: rs, list: onlyDone ? rs.filter(function (r) { return r.finished; }) : rs };
}
var MEDAL = { 1: '<span class="medal g">1</span>', 2: '<span class="medal s">2</span>', 3: '<span class="medal b">3</span>' };
// #122 Apdovanojimai rezultatuose: viršuje - ką tik baigtos ir dar neapdovanotos rungtys (rezultatai suskaičiuoti patys),
// „✓ Apdovanota“ vienu paspaudimu nukelia žemyn; žemiau - kas jau apdovanota (klaidoms atsekti), „↺ Grąžinti“
var awDoneOpen = false;
function awTime(ts) { var m = String(ts || '').match(/(\d{2}):(\d{2})/); return m ? m[1] + ':' + m[2] : ''; }
function renderAwardQueue() {
  var box = document.getElementById('award-view'); if (!box) return;
  var cats = S.categories.filter(function (c) { return c.competitors.length; });
  var at = function (c, k) { return (AW[c.id] && AW[c.id][k]) || ''; };
  var todo = cats.filter(function (c) { var st = catStatus(c); return st === 'sent' || st === 'fin'; })
    .sort(function (a, b) { var x = at(a, 'sent') || '9', y = at(b, 'sent') || '9'; return x < y ? -1 : x > y ? 1 : 0; });
  var done = cats.filter(function (c) { return catStatus(c) === 'awarded'; })
    .sort(function (a, b) { var x = at(a, 'done'), y = at(b, 'done'); return x > y ? -1 : x < y ? 1 : 0; });
  var card = function (c) {
    var t = awTime(at(c, 'sent'));
    return '<div class="aw-card"><div class="aw-top"><span class="disc d-' + c.discipline + '"></span><b>' + esc(c.name) + '</b>' +
      '<span class="muted small">' + esc((areaOf(c) || {}).name || '') + (t ? ' · baigta ' + t : '') + '</span>' +
      '<button type="button" class="btn btn-xs" data-res-open="' + c.id + '" title="Atidaryti lentelę - patikrinti">Lentelė</button></div>' +
      '<div class="aw-places">' + (placesShort(c) || '<span class="muted small">Prizininkų nėra</span>') + '</div>' +
      '<button type="button" class="btn btn-primary aw-done-btn" data-award-done="' + c.id + '">✓ Apdovanota</button></div>';
  };
  var html = '<div class="aw-head"><h2>🏅 Apdovanoti dabar' + (todo.length ? ' <span class="aw-n">' + todo.length + '</span>' : '') + '</h2>' +
    '<span class="muted small">Baigta rungtis atsiranda čia pati - rezultatai jau suskaičiuoti. Pirmiausia - anksčiausiai baigtos.</span></div>' +
    (todo.length ? '<div class="aw-grid">' + todo.map(card).join('') + '</div>' : '<p class="muted aw-empty">Kol kas nėra ką apdovanoti - kai rungtis baigsis, ji čia atsiras pati.</p>');
  if (done.length) {
    html += '<details class="aw-donebox"' + (awDoneOpen ? ' open' : '') + '><summary>✓ Jau apdovanota (' + done.length + ') - patikrinti, ar viskas teisingai</summary><ul class="aw-donelist">' +
      done.map(function (c) {
        return '<li><span class="disc d-' + c.discipline + '"></span><b>' + esc(c.name) + '</b><span class="aw-dl-pl">' + placesShort(c) + '</span>' +
          '<span class="muted small">' + awTime(at(c, 'done')) + '</span><button type="button" class="btn btn-xs" data-award-back="' + c.id + '" title="Grąžinti į „Apdovanoti dabar“">↺ Grąžinti</button></li>';
      }).join('') + '</ul></details>';
  }
  box.innerHTML = html;
  var db = box.querySelector('.aw-donebox'); if (db) db.addEventListener('toggle', function () { awDoneOpen = db.open; });
}
if (document.getElementById('award-view')) document.getElementById('award-view').addEventListener('click', function (e) {
  var d = e.target.closest('[data-award-done]'); if (d) { setAward([d.dataset.awardDone], 'done'); return; }
  var bk = e.target.closest('[data-award-back]'); if (bk) { setAward([bk.dataset.awardBack], 'sent'); return; }
  var o = e.target.closest('[data-res-open]'); if (o) openRun(o.dataset.resOpen);
});
export function renderResults() {
  renderAwardQueue();
  var sr = shownResults(), rs = sr.list, table = medalTable(rs), sum = { g: 0, s: 0, b: 0, pts: 0 };
  document.getElementById('res-count').textContent = 'Baigta ' + sr.all.filter(function (r) { return r.finished; }).length + ' iš ' + sr.all.length + ' kategorijų';
  document.getElementById('medals-view').innerHTML = table.length ? '<table><thead><tr><th class="num">#</th><th>Klubas</th><th class="num">' + MEDAL[1] + '</th><th class="num">' + MEDAL[2] + '</th><th class="num">' + MEDAL[3] + '</th><th class="num">Taškai</th></tr></thead><tbody>' +
    table.map(function (t, i) {
      ['g', 's', 'b', 'pts'].forEach(function (k) { sum[k] += t.m[k]; });
      return '<tr><td class="num">' + (i + 1) + '</td><td>' + esc(t.club) + '</td><td class="num">' + t.m.g + '</td><td class="num">' + t.m.s + '</td><td class="num">' + t.m.b + '</td><td class="num"><b>' + t.m.pts + '</b></td></tr>';
    }).join('') + '</tbody><tfoot><tr><td></td><td>Iš viso</td><td class="num">' + sum.g + '</td><td class="num">' + sum.s + '</td><td class="num">' + sum.b + '</td><td class="num">' + sum.pts + '</td></tr></tfoot></table>'
    : '<p class="muted" style="padding:12px 14px;margin:0;">Dar nėra prizininkų. Pažymėkite kovų nugalėtojus skirtuke „Lentelės“.</p>';
  document.getElementById('podium-view').innerHTML = rs.length ? '<div class="res-cats">' + rs.map(function (r) {
    var ast = AW[r.c.id] && AW[r.c.id].st;
    var state = (ast === 'done' ? stChip('awarded') : ast === 'sent' ? stChip('sent') : r.finished ? '<span class="pill ok">Baigta</span><button type="button" class="btn btn-award btn-xs" data-award-send="' + r.c.id + '" title="Siųsti apdovanojimams">🏅 Siųsti</button>' : '') +
      (r.finished || ast ? '' : r.done ? '<span class="pill warn">Vyksta ' + r.done + '/' + r.total + '</span>' : '<span class="pill">Nepradėta</span>');
    return '<div class="res-cat"><div class="res-cat-top"><button type="button" data-res-open="' + r.c.id + '" title="Atidaryti lentelę"><span class="disc d-' + r.c.discipline + '"></span>' + esc(r.c.name) + '</button><span class="res-cat-st">' + state + '</span></div>' +
      (r.places.length ? r.places.map(function (p) { return '<div class="rp">' + MEDAL[p.place] + '<span>' + esc(p.name) + ' <span class="club">' + esc(p.club) + '</span></span></div>'; }).join('') : '<span class="muted small">Prizininkų dar nėra</span>') + '</div>';
  }).join('') + '</div>' : '<p class="muted" style="padding:12px 14px;margin:0;">Nėra baigtų kategorijų.</p>';
}
document.getElementById('res-done').addEventListener('change', renderResults);
document.getElementById('podium-view').addEventListener('click', function (e) {
  var as = e.target.closest('[data-award-send]'); if (as) { setAward([as.dataset.awardSend], 'sent'); return; }
  var b = e.target.closest('[data-res-open]'); if (!b) return;
  openRun(b.dataset.resOpen);
});
document.getElementById('res-print').addEventListener('click', function () { window.print(); });
document.getElementById('draw-print').addEventListener('click', function () { window.print(); });
document.getElementById('res-csv').addEventListener('click', function () {
  var q = function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; };
  var lines = [['Kategorija', 'Rungtis', 'Vieta', 'Vardas', 'Klubas'].map(q).join(';')];
  shownResults().list.forEach(function (r) { r.places.forEach(function (p) { lines.push([r.c.name, DISC[r.c.discipline] || r.c.discipline, p.place, p.name, p.club].map(q).join(';')); }); });
  var a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
  // Failo pavadinimas be lietuviškų raidžių - kitaip kai kurios naršyklės jį pakeičia į „download“
  a.download = (S.tournament || 'varzybos').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '') + '-rezultatai.csv';
  document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
});
