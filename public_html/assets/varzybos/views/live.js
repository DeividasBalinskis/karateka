// Live tvarkaraštis: atkabinta rungtis, prisegti langeliai, lenta ir atidarytos rungties antraštė
import { CFG, esc, hhmm, KEY, POPUP, toast, toMin } from '../util.js';
import { person } from '../rules.js';
import { drawStale, matchCount } from '../engine/category.js';
import { cat, dirty, flush, S, save, saving, setAward } from '../store.js';
import { curTab, openRun, render, runOpen, setRun, showTab } from '../chrome.js';
import { renderDraw, runView, setRunView } from './draw.js';
import { toggleResult } from '../bout.js';
import { areaPlan, delayChip, dragId, goRun, moveCat, placesShort, renderRunDone, renderRunList, renderSched, setDragId } from '../schedule.js';
import { catBracket, catStatus, stChip } from '../results.js';
import { areaById, deskAll, deskArea, deskEl, deskOpenCat, deskScrollTo, myArea, renderDesk, runEl, runHome, setDeskAll, setHeadVar } from './desk.js';
import { upcoming } from './results.js';

// ---------- Atkabinta rungtis (atskiras naršyklės langas): tvarkaraštyje ji pilka, kol langas atidarytas ----------
export var detached = {}, winCh = null;
try { winCh = new BroadcastChannel('taika-win-' + CFG.id); } catch (e) {}
export function isDetached(id) { return !POPUP && detached[id] && Date.now() - detached[id] < 8000; }
if (winCh) {
  winCh.onmessage = function (ev) {
    var d = ev.data || {}; if (!d.cat || POPUP) return;
    var was = isDetached(d.cat);
    if (d.t === 'open') detached[d.cat] = Date.now(); else delete detached[d.cat];
    if (was !== isDetached(d.cat)) render();
  };
  if (POPUP) {
    var ping = function () { if (S && S.selected) winCh.postMessage({ t: 'open', cat: S.selected }); };
    setTimeout(ping, 0); setInterval(ping, 3000);
    window.addEventListener('pagehide', function () { if (S && S.selected) winCh.postMessage({ t: 'close', cat: S.selected }); });
  }
}
setInterval(function () { Object.keys(detached).forEach(function (k) { if (Date.now() - detached[k] >= 8000) { delete detached[k]; render(); } }); }, 4000);
function detach(c) {
  // Langas atidaromas iš karto (kitaip naršyklė jį užblokuotų), adresas - kai pakeitimai jau išsaugoti
  var w = window.open('', 'taika-' + CFG.id + '-' + c.id, 'width=1100,height=760');
  if (!w) { toast('Naršyklė neleido atidaryti lango - leiskite iššokančius langus šiam puslapiui.'); return; }
  var url = location.pathname + '?id=' + CFG.id + '&langas=1&cat=' + encodeURIComponent(c.id), tries = 0;
  flush();
  (function go() {
    if ((dirty || saving) && tries++ < 40) { setTimeout(go, 100); return; }
    w.location.href = url;
  })();
  detached[c.id] = Date.now(); setRun(false); render();
}

// ---------- Live: lenta arba atidarytos rungties valdymas ----------
// ---------- Prisegta kategorija: mažas langelis bet kurioje ekrano vietoje (matosi ir kituose skirtukuose) ----------
// Tvarkaraštyje nutempkite kategoriją už tatamių stulpelių (ar spauskite 📌) - langelis lieka, kur paleidote; jį galima tempti už antraštės.
var pins = [];
try { pins = JSON.parse(localStorage.getItem(KEY + '-pins') || '[]') || []; } catch (e) {}
var pinBox = document.createElement('div'); pinBox.className = 'pins'; document.body.appendChild(pinBox);
function savePins() { try { localStorage.setItem(KEY + '-pins', JSON.stringify(pins)); } catch (e) {} }
export function pinCat(id, x, y) {
  if (!cat(id)) return;
  pins = pins.filter(function (p) { return p.id !== id; });
  var w = Math.min(300, window.innerWidth - 24);
  pins.push({ id: id, x: x === undefined ? window.innerWidth - w - 16 : x - w / 2, y: y === undefined ? 90 + pins.length * 30 : y - 20, min: false });
  if (pins.length > 4) pins.shift();
  savePins(); renderPins();
  toast('📌 Prisegta: <b>' + esc(cat(id).name) + '</b>. Tempkite už antraštės, ✕ - atsegti.');
}
// Prisegtame langelyje - visa lentelė: paspaudus vardą pažymimas nugalėtojas (dar kartą - atšaukiama)
export function pinTree(c) {
  if (!c.draw || drawStale(c) || c.competitors.length < 2) return '<p class="muted small">Lentelė dar nesudaryta.</p>';
  var b = catBracket(c, true), h = '';
  (b.groups || []).forEach(function (g) {
    h += '<h4>' + esc(g.t) + '</h4>';
    if (g.score) { h += '<ol class="gv-score">' + g.rows.map(function (r) { return '<li><span>' + esc(r.n) + '</span><b>' + esc(r.s || '–') + '</b></li>'; }).join('') + '</ol>'; return; }
    h += '<div class="pin-ms">' + g.matches.map(function (m) {
      var side = function (k) {
        var id = m[k + 'i'], nm = m[k], won = m.w === k;
        return id && m.ai && m.bi ? '<button type="button" class="pin-p' + (won ? ' won' : '') + (k === 'a' ? ' aka-t' : '') + '" data-pin-res="' + esc(c.id + '|' + m.id + '|' + id) + '" title="Pažymėti nugalėtoju">' + esc(nm) + '</button>'
          : '<span class="pin-p' + (won ? ' won' : '') + '">' + esc(nm || '…') + '</span>';
      };
      return '<div class="pin-m' + (m.w ? ' done' : '') + '"><span class="muted">' + esc(m.no) + '</span>' + side('a') + '<span class="muted">' + esc(m.s || 'vs') + '</span>' + side('b') + '</div>';
    }).join('') + '</div>';
  });
  return h;
}
function pinBody(c, view) {
  if (view === 'tree') return pinTree(c);
  var a = areaById(c.tatami) || S.areas[0], plan = areaPlan(a), it = plan.items.find(function (x) { return x.c === c; }) || { start: toMin(a.start), done: 0, total: matchCount(c), st: '' };
  var st = catStatus(c, it.st === 'now'), html = '<div class="pin-meta">' + esc(a.name) + ' · ' + hhmm(it.start) + ' ' + stChip(st) + '</div>' +
    '<div class="prog' + (it.total && it.done >= it.total ? ' done' : '') + '"><i style="width:' + (it.total ? Math.round(it.done / it.total * 100) : 0) + '%"></i><span>' + it.done + ' / ' + it.total + '</span></div>';
  if (st === 'fin' || st === 'sent' || st === 'awarded') return html + '<div class="pin-places">' + placesShort(c) + '</div>';
  var up = upcoming(c), nm = function (id) { return esc((person(c, id) || {}).name || '—'); };
  if (up[0]) html += '<div class="pin-now"><span class="muted small">Dabar:</span> <b class="aka-t">' + nm(up[0].a) + '</b>' + (up[0].b ? ' <span class="muted">–</span> <b>' + nm(up[0].b) + '</b>' : '') + '</div>';
  if (up[1]) html += '<div class="pin-next small muted">Toliau: ' + nm(up[1].a) + (up[1].b ? ' – ' + nm(up[1].b) : '') + '</div>';
  return html;
}
export function renderPins() {
  if (POPUP) return;
  pins = pins.filter(function (p) { return cat(p.id); });
  pinBox.innerHTML = pins.map(function (p) {
    var c = cat(p.id), w = Math.max(220, Math.min(p.w || (p.view === 'tree' ? 380 : 300), window.innerWidth - 24));
    var x = Math.max(8, Math.min(p.x, window.innerWidth - w - 8)), y = Math.max(8, Math.min(p.y, window.innerHeight - 60));
    return '<section class="pin-win' + (p.min ? ' min' : '') + (p.view === 'tree' ? ' tree' : '') + '" data-pin="' + esc(p.id) + '" style="left:' + x + 'px;top:' + y + 'px;width:' + w + 'px' + (p.h && !p.min ? ';height:' + Math.min(p.h, window.innerHeight - 16) + 'px' : '') + '" aria-label="Prisegta: ' + esc(c.name) + '">' +
      '<header class="pin-head" data-pin-drag title="Tempkite - perkelti"><span class="disc d-' + c.discipline + '"></span><b>' + esc(c.name) + '</b>' +
      '<button type="button" data-pin-view title="' + (p.view === 'tree' ? 'Rodyti: kas kovoja dabar' : 'Rodyti visą lentelę (galima žymėti nugalėtojus)') + '">' + (p.view === 'tree' ? '◉' : '▦') + '</button>' +
      '<button type="button" data-pin-open title="Atidaryti">↗</button><button type="button" data-pin-min title="' + (p.min ? 'Išskleisti' : 'Suskleisti') + '">' + (p.min ? '▢' : '–') + '</button><button type="button" data-pin-x title="Atsegti">✕</button></header>' +
      (p.min ? '' : '<div class="pin-body">' + pinBody(c, p.view) + '</div><span class="pin-resize" data-pin-resize title="Tempkite - keisti dydį" aria-hidden="true"></span>') + '</section>';
  }).join('');
}
// Langelio tempimas už antraštės (pele ir pirštu); prie ekrano krašto - prilimpa
var pinMove = null;
pinBox.addEventListener('pointerdown', function (e) {
  var h = e.target.closest('[data-pin-drag]'); if (!h || e.target.closest('button')) return;
  var win = h.closest('.pin-win'), r = win.getBoundingClientRect();
  pinMove = { id: win.dataset.pin, win: win, dx: e.clientX - r.left, dy: e.clientY - r.top };
  win.classList.add('moving'); e.preventDefault();
});
var pinSize = null;
pinBox.addEventListener('pointerdown', function (e) {
  var g = e.target.closest('[data-pin-resize]'); if (!g) return;
  var win = g.closest('.pin-win'), r = win.getBoundingClientRect();
  pinSize = { id: win.dataset.pin, win: win, x: e.clientX, y: e.clientY, w: r.width, h: r.height }; e.preventDefault(); e.stopPropagation();
}, true);
document.addEventListener('pointermove', function (e) {
  if (!pinSize) return;
  pinSize.win.style.width = Math.max(220, pinSize.w + e.clientX - pinSize.x) + 'px';
  pinSize.win.style.height = Math.max(120, pinSize.h + e.clientY - pinSize.y) + 'px';
});
document.addEventListener('pointerup', function () {
  if (!pinSize) return;
  var m = pinSize, p = pins.find(function (x) { return x.id === m.id; }); pinSize = null;
  if (p) { p.w = Math.round(m.win.offsetWidth); p.h = Math.round(m.win.offsetHeight); savePins(); }
});
document.addEventListener('pointermove', function (e) {
  if (!pinMove) return;
  pinMove.win.style.left = (e.clientX - pinMove.dx) + 'px'; pinMove.win.style.top = (e.clientY - pinMove.dy) + 'px';
});
document.addEventListener('pointerup', function () {
  if (!pinMove) return;
  var m = pinMove, r = m.win.getBoundingClientRect(), p = pins.find(function (x) { return x.id === m.id; });
  pinMove = null; m.win.classList.remove('moving');
  if (!p) return;
  p.x = r.left < 24 ? 8 : r.right > window.innerWidth - 24 ? window.innerWidth - r.width - 8 : r.left;
  p.y = r.top < 24 ? 8 : r.bottom > window.innerHeight - 24 ? window.innerHeight - r.height - 8 : r.top;
  savePins(); renderPins();
});
pinBox.addEventListener('click', function (e) {
  var win = e.target.closest('.pin-win'); if (!win) return;
  var id = win.dataset.pin, p = pins.find(function (x) { return x.id === id; });
  if (e.target.closest('[data-pin-x]')) { pins = pins.filter(function (x) { return x.id !== id; }); savePins(); renderPins(); }
  else if (e.target.closest('[data-pin-min]') && p) { p.min = !p.min; savePins(); renderPins(); }
  else if (e.target.closest('[data-pin-view]') && p) { p.view = p.view === 'tree' ? '' : 'tree'; p.h = 0; savePins(); renderPins(); }
  else if (e.target.closest('[data-pin-res]')) {
    var pr = e.target.closest('[data-pin-res]').dataset.pinRes.split('|');
    toggleResult(pr[0], pr[1], pr[2]);
  }
  else if (e.target.closest('[data-pin-open]')) goRun(id);
});
window.addEventListener('resize', function () { renderPins(); });
// Tvarkaraščio kortelė nutempta ne ant tatamio - prisegama ten, kur paleista
document.addEventListener('dragover', function (e) { if (dragId && !e.target.closest('[data-drop]')) { e.preventDefault(); document.body.classList.add('pin-drop'); } });
document.addEventListener('dragleave', function (e) { if (!e.relatedTarget) document.body.classList.remove('pin-drop'); });
document.addEventListener('drop', function (e) {
  document.body.classList.remove('pin-drop');
  if (!dragId || e.target.closest('[data-drop]')) return;
  e.preventDefault(); var id = dragId; setDragId(null); pinCat(id, e.clientX, e.clientY);
});

export function renderLive() {
  var c = runOpen ? cat(S.selected) : null, da = deskArea();
  if (runOpen && !c) setRun(false);
  // v2.01: darbastalis (vienas tatamis) - rungties valdymas perkeliamas po išskleista grupe; kitur - į savo vietą
  if (deskEl) {
    var home = da ? document.getElementById('dk-run') : runHome;
    if (runEl.parentNode !== home) home.appendChild(runEl);
    runEl.classList.toggle('in-desk', !!da);
    deskEl.hidden = !da;
    document.body.classList.toggle('desk-on', !!da);
  }
  if (da) {
    if (c && String(c.tatami) !== String(da.id)) { setRun(false); c = null; }
    document.getElementById('live-board').hidden = true;
    runEl.hidden = !c;
    setHeadVar();
    renderDesk(da, c);
    if (c) { renderRunHead(c); renderDraw(); }
    return;
  }
  document.getElementById('live-board').hidden = !!c;
  runEl.hidden = !c;
  if (!c) { renderSched(); return; }
  renderRunHead(c);
  renderDraw();
}
export function runNeighbours(c) {
  var list = S.categories.filter(function (x) { return x.tatami === c.tatami; }), i = list.indexOf(c);
  return { prev: list[i - 1] || null, next: list[i + 1] || null };
}
function renderRunHead(c) {
  var a = S.areas.find(function (x) { return x.id === c.tatami; }) || S.areas[0], plan = areaPlan(a);
  var it = plan.items.find(function (x) { return x.c === c; }) || { start: toMin(a.start), done: 0, total: matchCount(c) };
  document.getElementById('run-name').innerHTML = '<span class="disc d-' + c.discipline + '"></span>' + esc(c.name);
  document.getElementById('run-meta').textContent = a.name + ' · ' + hhmm(it.start) + ' · ' + c.competitors.length + ' dalyv. · kovos ' + it.done + ' / ' + it.total;
  var nb = runNeighbours(c);
  document.querySelectorAll('[data-runview]').forEach(function (x) { x.classList.toggle('on', x.dataset.runview === runView); });
  document.getElementById('run-prev').disabled = !nb.prev;
  var nx = document.getElementById('run-next'); nx.disabled = !nb.next;
  nx.title = nb.next ? 'Kita: ' + nb.next.name : 'Šio tatamio grupių daugiau nėra';
  document.getElementById('run-prev').title = nb.prev ? 'Ankstesnė: ' + nb.prev.name : 'Tai pirma šio tatamio grupė';
  var idx = plan.items.findIndex(function (x) { return x.c === c; }), left = plan.items.filter(function (x) { return x.st !== 'done'; }).length;
  var mine = deskEl && !POPUP && deskAll ? areaById(myArea) : null;   // v2.01: žiūrima per „🏟 Visi tatamiai“ - grįžti į savo darbastalį
  document.getElementById('run-strip').innerHTML = (mine ? '<button type="button" class="btn btn-primary btn-sm" data-desk-back title="Grįžti į savo tatamio darbastalį: ' + esc(mine.name) + '">📋 Mano tatamis</button>' : '') +
    (S.areas.length > 1 && S.areas.length <= 4 ? '<span class="seg rs-area" role="group" aria-label="Tatamis">' + S.areas.map(function (x) { return '<button type="button" class="seg-btn' + (x.id === a.id ? ' on' : '') + '" data-rs-area="' + x.id + '" title="' + (x.id === a.id ? 'Šis tatamis' : 'Pažiūrėti: dabartinė ' + esc(x.name) + ' grupė') + '">' + esc(x.name) + '</button>'; }).join('') + '</span>'
      : S.areas.length > 4 ? '<select class="sel rs-area" id="rs-area" aria-label="Tatamis" title="Pažiūrėti kitą tatamį">' + S.areas.map(function (x) { return '<option value="' + x.id + '"' + (x.id === a.id ? ' selected' : '') + '>' + esc(x.name) + '</option>'; }).join('') + '</select>' : '<b class="rs-area">' + esc(a.name) + '</b>') +
    delayChip(plan.delay) + '<span class="rs-pos muted small">Grupė ' + (idx + 1) + ' iš ' + plan.items.length + ' · liko ' + left + '</span>' + stChip(catStatus(c, it.st === 'now')) +
    '<a class="rs-all small" href="#run-list">Visos grupės ↓</a>';
  // Iš pačios rungties: pradėti ją dabar (be eilės) arba perkelti į kitą tatamį
  var isCur = plan.cur && plan.cur.c === c, itSt = (plan.items.find(function (x) { return x.c === c; }) || {}).st;
  document.getElementById('run-move').innerHTML = (CFG.readOnly ? '' :
    (!isCur && itSt && itSt !== 'done' ? '<button type="button" class="btn btn-sm" data-run-start="' + c.id + '" title="Ši grupė taps dabartine šiame tatamyje (kitos pasislinks)">▶ Pradėti dabar</button>' : '') +
    '');
  renderRunDone(c);
  renderRunList(c, a, plan);
}
document.getElementById('run-move').addEventListener('click', function (e) {
  var c = cat(S.selected); if (!c) return;
  var st = e.target.closest('[data-run-start]');
  if (st) { var pl = areaPlan(areaById(c.tatami)); if (pl.cur && pl.cur.c !== c) moveCat(c.id, c.tatami, pl.cur.c.id); toast('„' + esc(c.name) + '“ - dabar šiame tatamyje.'); return; }
  var to = e.target.closest('[data-run-to]');
  if (to) { var ar = areaById(to.dataset.runTo); moveCat(c.id, +to.dataset.runTo, null); toast('Perkelta į <b>' + esc(ar ? ar.name : '') + '</b>.'); }
});
document.getElementById('run-back').addEventListener('click', function () { if (POPUP) { window.close(); return; } setRun(false); render(); });
// Viršutinė „←“: iš atidarytos rungties - atgal į visą tvarkaraštį (ne į visų varžybų sąrašą)
var vzBack = document.querySelector('.vz-back');
if (vzBack) vzBack.addEventListener('click', function (e) { if (runOpen && curTab() === 'live') { e.preventDefault(); setRun(false); render(); } else if (curTab() !== 'live') { e.preventDefault(); showTab('live'); } });
document.getElementById('run').addEventListener('click', function (e) {
  var v = e.target.closest('[data-runview]'); if (!v) return;
  setRunView(v.dataset.runview); try { sessionStorage.setItem(KEY + '-runview', runView); } catch (x) {}
  render();
});
document.getElementById('run-prev').addEventListener('click', function () {
  var nb = runNeighbours(cat(S.selected)); if (!nb.prev) return;
  if (POPUP) { S.selected = nb.prev.id; save(); render(); return; }
  openRun(nb.prev.id);
});
document.getElementById('run-next').addEventListener('click', function () {
  var nb = runNeighbours(cat(S.selected)); if (!nb.next) return;
  if (POPUP) { S.selected = nb.next.id; save(); render(); if (winCh) winCh.postMessage({ t: 'open', cat: nb.next.id }); return; }
  openRun(nb.next.id);
});
document.getElementById('run-detach').addEventListener('click', function () { var c = cat(S.selected); if (c) detach(c); });
document.getElementById('run-pin').addEventListener('click', function () { if (S.selected) pinCat(S.selected); });
document.getElementById('run-strip').addEventListener('click', function (e) {
  if (e.target.closest('[data-desk-back]')) { setDeskAll(false); render(); var sc = cat(S.selected), da = deskArea(); if (sc && da && String(sc.tatami) === String(da.id)) deskScrollTo(sc.id); else window.scrollTo(0, 0); return; }
  var b = e.target.closest('[data-run]'); if (b) openRun(b.dataset.run);
  var ra = e.target.closest('[data-rs-area]');
  if (ra) { var pl = areaPlan(areaById(ra.dataset.rsArea)), go = pl.cur || pl.items[0]; if (go) openRun(go.c.id); return; }
  var all = e.target.closest('.rs-all'); if (all) { e.preventDefault(); document.getElementById('run-list').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
});
document.getElementById('run-list').addEventListener('click', function (e) { var b = e.target.closest('[data-run]'); if (b) goRun(b.dataset.run); });
document.getElementById('run-done').addEventListener('click', function (e) {
  var b = e.target.closest('button'); if (!b) return;
  if (b.dataset.awardSend) setAward([b.dataset.awardSend], 'sent');
  else if (b.dataset.awardUndo) { if (confirm('Atšaukti siuntimą apdovanojimams? Planšetėje ši grupė vėl bus nebaigta.')) setAward([b.dataset.awardUndo], ''); }
  else if (b.dataset.runGo) goRun(b.dataset.runGo);
});
document.getElementById('run-strip').addEventListener('change', function (e) {
  if (e.target.id !== 'rs-area') return;
  var plan = areaPlan(S.areas.find(function (x) { return x.id === +e.target.value; }));
  var go = plan.cur || plan.items[0]; if (go) openRun(go.c.id);
});
// Laikrodis ir vėlavimas atsinaujina kas 30 s (jei niekas nevelkama ir nerašoma lentoje)
setInterval(function () {
  var board = document.getElementById('live-board'), da = deskArea();
  if (document.getElementById('v-live').hidden || dragId) return;
  // v2.01: darbastalis - atnaujinamos tik grupių eilutės, laikas ir „Dabar“ (išskleista lentelė nepaliečiama)
  if (da && deskEl && !deskEl.hidden) {
    var ae = document.activeElement;
    if (!(ae && deskEl.contains(ae) && /INPUT|SELECT|TEXTAREA/.test(ae.tagName))) renderDesk(da, deskOpenCat());
    return;
  }
  if (board.hidden) return;
  if (document.activeElement && board.contains(document.activeElement) && /INPUT|SELECT/.test(document.activeElement.tagName)) return;
  renderSched();
}, 30000);
var tSwitch = document.getElementById('t-switch');
if (tSwitch) tSwitch.addEventListener('change', function () { flush(true); location.href = tSwitch.value; });
