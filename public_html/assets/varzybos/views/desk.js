// Mano tatamis ir sekretoriato darbastalis (v2.01)
import { CFG, esc, hhmm, KEY, POPUP, toast } from '../util.js';
import { boutMode, person } from '../rules.js';
import { cat, S, save, setAward } from '../store.js';
import { openRun, render, runOpen, setRun, showTab } from '../chrome.js';
import { resetSwap } from './draw.js';
import { CAMS, openBout, setLive } from '../bout.js';
import { areaPlan, delayChip, fold, moveCat, nowMin, renderFound, schedHits, setCatAt, setDragId, ST_BADGE } from '../schedule.js';
import { catStatus, stChip } from '../results.js';
import { isDetached } from './live.js';
import { upcoming } from './results.js';

// ---------- Mano tatamis: šiame kompiuteryje (planšetėje) rodomas tik pasirinktas tatamis; kitą pažiūrėti - vienu paspaudimu ----------
export var myArea = null;
try { myArea = localStorage.getItem(KEY + '-myarea'); } catch (e) {}
export function areaById(id) { return S.areas.find(function (a) { return String(a.id) === String(id); }) || null; }
// v2.01: pasirinkus vieną tatamį - jo darbastalis (žr. „Sekretoriato darbastalis“); tvarkaraštyje - visi tatamiai
export function shownAreas() { return S.areas; }
function setMyArea(v) { myArea = v; setDeskAll(false); try { localStorage.setItem(KEY + '-myarea', v); } catch (e) {} render(); }
export function renderAreaPick() {
  var seg = document.getElementById('area-seg'), box = document.getElementById('area-pick'), cur = !deskAll && areaById(myArea) ? String(myArea) : 'all';
  var opts = S.areas.map(function (a) { return [String(a.id), a.name]; }).concat([['all', 'Visi']]);
  seg.hidden = !S.areas.length;
  seg.innerHTML = opts.map(function (o) { return '<button type="button" class="seg-btn' + (o[0] === cur ? ' on' : '') + '" aria-pressed="' + (o[0] === cur) + '" data-my-area="' + o[0] + '"' + (o[0] === 'all' ? '' : ' title="Šio tatamio darbastalis"') + '>' + esc(o[1]) + '</button>'; }).join('');
  // Pirmą kartą šiame kompiuteryje - paklausiama (dideli mygtukai)
  var ask = S.areas.length > 1 && !myArea;
  box.hidden = !ask;
  box.classList.toggle('ask', ask);
  if (ask) box.innerHTML = '<div class="ap-q"><b>▶ Start: prie kurio tatamio dirbate?</b><span class="muted small">Šiame kompiuteryje atsidarys to tatamio darbastalis: visos jo grupės, kovos ir Live kova. Visus tatamius - vienu paspaudimu.</span></div><div class="ap-btns">' +
    opts.map(function (o) { return '<button type="button" class="btn' + (o[0] === 'all' ? '' : ' btn-primary') + '" data-my-area="' + o[0] + '">' + (o[0] === 'all' ? 'Visi tatamiai (vyr. sekretoriatas)' : esc(o[1])) + '</button>'; }).join('') + '</div>';
  var db = document.getElementById('desk-back'), mine = areaById(myArea);
  if (db) { db.hidden = !(deskAll && mine && deskEl && !POPUP); if (mine) db.title = 'Grįžti į savo tatamio darbastalį: ' + mine.name; }
}
['area-seg', 'area-pick'].forEach(function (id) {
  document.getElementById(id).addEventListener('click', function (e) { var b = e.target.closest('[data-my-area]'); if (!b) return; setRun(false); setMyArea(b.dataset.myArea); window.scrollTo(0, 0); });
});
var deskBack = document.getElementById('desk-back');
if (deskBack) deskBack.addEventListener('click', function () { setDeskAll(false); setRun(false); render(); window.scrollTo(0, 0); });
// 📹 Live kova (v2.00): pasirinkto tatamio (arba pirmo su telefonu) vaizdas per visą ekraną
var liveCamBtn = document.getElementById('live-cam');
if (liveCamBtn) liveCamBtn.addEventListener('click', function () {
  var a = areaById(myArea);
  if (!a || !CAMS[a.id]) a = S.areas.find(function (x) { return CAMS[x.id]; });
  if (a) setLive(a.id);
});

// ---------- v2.01: Sekretoriato darbastalis - vienas tatamis šiame įrenginyje ----------
// Pasirinkus tatamį („1 TATAMIS … Visi“, „▶ Start“ ar sekretoriato nuoroda tatamiui): kairėje meniu („📋 Mano tatamis“, „📹 Live kova“,
// „🏟 Visi tatamiai“) ir kas dabar / kas toliau; dešinėje - visos to tatamio grupės su laikais, būsenomis ir vėlavimu.
// Paspaudus grupę - ji išsiskleidžia čia pat: tas pats rungties valdymas (#run) perkeliamas po jos eilute (lentelė, vardas - laimėjo,
// „✎ Keisti“ - sukeisti tempiant). Grupių eilė - tempiant už ⠿ (pele ir pirštu). „🏟 Visi tatamiai“ - bendras tvarkaraštis (šio
// naršyklės skirtuko), savas tatamis išlieka: grįžti - „📋 Mano tatamis“.
export var deskEl = document.getElementById('desk'), runEl = document.getElementById('run'), runHome = runEl.parentNode;
export var deskAll = false, deskQ = '', skipDeskClick = false, dkd = null;
try { deskAll = sessionStorage.getItem(KEY + '-deskall') === '1'; } catch (e) {}
export function deskArea() { return deskEl && !POPUP && !deskAll ? areaById(myArea) : null; }
export function setDeskAll(on) { deskAll = !!on; try { sessionStorage.setItem(KEY + '-deskall', deskAll ? '1' : ''); } catch (e) {} }
// v2.01: nuoroda tatamiui (?tatamis=N - sekretoriato nuoroda su &t=N ar „📋 Darbastalis“) - šiame įrenginyje atsidaro to tatamio darbastalis
export function deskFromLink() {
  if (CFG.deskArea && !POPUP && areaById(CFG.deskArea)) {
    myArea = String(CFG.deskArea); setDeskAll(false); setRun(false);
    try { localStorage.setItem(KEY + '-myarea', myArea); } catch (e) {}
    try { history.replaceState(history.state, '', location.href.replace(/([?&])tatamis=\d+&?/, '$1').replace(/[?&](#|$)/, '$1')); } catch (e) {}
  }
}
export function deskOpenCat() { return runOpen && !runEl.hidden ? cat(S.selected) : null; }
export function deskScrollTo(id) {
  var r = id && deskEl.querySelector('[data-dk-row="' + (window.CSS && CSS.escape ? CSS.escape(id) : id) + '"]'); if (!r) return;
  var top = r.getBoundingClientRect().top, head = document.querySelector('.vz-top'), hh = head && getComputedStyle(head).position === 'sticky' ? head.offsetHeight : 0;
  if (top < hh + 8 || top > window.innerHeight * 0.55) window.scrollTo(window.scrollX, window.scrollY + top - hh - 12);
}
function deskName(c, id) { return esc((person(c, id) || {}).name || '—'); }
// Prilipusio viršaus aukštis: meniu kairėje ir eilutės (slenkant) nelenda po juo; telefone viršus neprilimpa - 0
export function setHeadVar() {
  var h = document.querySelector('.vz-top'), v = h && getComputedStyle(h).position === 'sticky' ? h.offsetHeight : 0;
  if (document.documentElement.style.getPropertyValue('--vz-head') !== v + 'px') document.documentElement.style.setProperty('--vz-head', v + 'px');
}
window.addEventListener('resize', function () { if (deskArea()) setHeadVar(); });
// Kas dabar / kas toliau / laikas (kairėje, telefone - virš grupių)
function deskNowHtml(a, plan) {
  var cur = plan.cur, h = '';
  if (!cur) return '<div class="dk-card"><span class="dk-k">Dabar</span><b>' + (plan.items.length ? 'Visos grupės baigtos' : 'Grupių dar nėra') + '</b></div>';
  var c = cur.c, up = upcoming(c), m = up[0], nm = function (id) { return deskName(c, id); };
  h += '<div class="dk-card dk-cur"><span class="dk-k">Dabar · ' + hhmm(cur.start) + '</span><button type="button" class="dk-link" data-dk-open="' + esc(c.id) + '"><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + '</button>';
  if (m) {
    h += '<div class="dk-bout"><span class="dk-bno">' + esc(m.no ? 'Kova ' + m.no : m.title || '') + '</span><span class="dk-p aka">' + nm(m.a) + '</span>' +
      (m.b ? '<span class="dk-vs">vs</span><span class="dk-p shiro">' + nm(m.b) + '</span>' : '') + '</div>';
    if (m.b && m.id && boutMode(c, m) !== 'flags') h += '<button type="button" class="btn btn-primary btn-sm dk-boutbtn" data-dk-bout="' + esc(c.id) + '|' + esc(m.id) + '">▶ Kovos skydelis</button>';
  }
  h += '</div>';
  var nb = up[1], nx = plan.items.filter(function (x) { return x.st !== 'done' && x !== cur; })[0];
  if (nb || nx) {
    h += '<div class="dk-card"><span class="dk-k">Toliau</span>';
    if (nb) h += '<div class="dk-bout"><span class="dk-bno">' + esc(nb.no ? 'Kova ' + nb.no : nb.title || '') + '</span><span class="dk-p aka">' + nm(nb.a) + '</span>' + (nb.b ? '<span class="dk-vs">vs</span><span class="dk-p shiro">' + nm(nb.b) + '</span>' : '') + '</div>';
    if (nx) h += '<button type="button" class="dk-link dk-nextg" data-dk-open="' + esc(nx.c.id) + '"><span class="dk-t">' + hhmm(nx.start) + '</span><span class="disc d-' + nx.c.discipline + '"></span>' + esc(nx.c.name) + '</button>';
    h += '</div>';
  }
  return h;
}
function deskRow(x, open, q) {
  var c = x.c, st = catStatus(c, x.st === 'now'), isOpen = open === c, det = isDetached(c.id);
  var hits = schedHits(c, q), hit = q && (hits.length || fold(c.name).indexOf(q) >= 0);
  var chip = st === 'wait' ? '' : st === 'fin' ? '<button type="button" class="btn btn-award btn-xs" data-award-send="' + esc(c.id) + '" title="Siųsti apdovanojimams">🏅 Siųsti</button>' : stChip(st);
  var sub = '<span class="dk-sub">' + c.competitors.length + ' dalyv. · ~' + x.dur + ' min</span>';
  if (x.st === 'now' && !isOpen) {
    var m = upcoming(c)[0];
    if (m) sub += '<span class="dk-sub dk-nowb"><b>' + esc(m.no ? 'Kova ' + m.no : m.title || '') + ':</b> <span class="dk-p aka">' + deskName(c, m.a) + '</span>' + (m.b ? ' <span class="dk-vs">vs</span> <span class="dk-p shiro">' + deskName(c, m.b) + '</span>' : '') + '</span>';
  }
  if (hits.length) sub += '<span class="dk-sub dk-hits">' + hits.map(function (p) { return '<span class="sq-me">' + esc(p.name) + '</span>'; }).join(' ') + '</span>';
  return '<li class="dk-row st-' + x.st + (isOpen ? ' open' : '') + (det ? ' detached' : '') + (x.st === 'done' && st === 'fin' ? ' needs-send' : '') + (q ? (hit ? ' sq-hit' : ' sq-dim') : '') + '" data-dk-row="' + esc(c.id) + '">' +
    '<span class="dk-grip" data-dk-grip title="Tempkite - pakeisti eilę">⠿</span>' +
    '<input class="card-time-in dk-time" type="text" inputmode="numeric" maxlength="5" value="' + hhmm(x.start) + '" data-cat-at="' + esc(c.id) + '" aria-label="Pradžios laikas" title="' + (c.at ? 'Nustatytas laikas (ištrinkite - vėl skaičiuosis pats)' : 'Pakeiskite - rungtis prasidės ne anksčiau') + '"' + (c.at ? ' data-fixed="1"' : '') + '>' +
    '<button type="button" class="dk-open" data-dk-open="' + esc(c.id) + '" aria-expanded="' + isOpen + '" title="' + (isOpen ? 'Suskleisti' : 'Atidaryti: lentelė, kovos, balai') + '"><span class="dk-name"><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + '</span>' + sub + '</button>' +
    '<span class="dk-prog' + (x.total && x.done >= x.total ? ' done' : '') + '" title="Kovos: ' + x.done + ' / ' + x.total + '"><i style="width:' + x.pct + '%"></i><span>' + x.done + '/' + x.total + '</span></span>' +
    '<span class="dk-st">' + (det ? '<span class="st-badge det">atskirame lange</span>' : x.st === 'done' ? '' : ST_BADGE[x.st]) + chip + '</span>' +
    '<span class="dk-chev" aria-hidden="true">' + (isOpen ? '▴' : '▾') + '</span></li>';
}
export function renderDesk(a, open) {
  var plan = areaPlan(a), q = fold(deskQ.trim()), sel = document.getElementById('dk-area');
  var opts = S.areas.map(function (x) { return '<option value="' + x.id + '">' + esc(x.name) + '</option>'; }).join('');
  if (sel.dataset.h !== opts) { sel.dataset.h = opts; sel.innerHTML = opts; }
  sel.value = String(a.id);
  var lb = deskEl.querySelector('[data-dk="live"]'); if (lb) lb.hidden = !CAMS[a.id];
  var left = plan.items.filter(function (x) { return x.st !== 'done'; }).length;
  document.getElementById('dk-title').innerHTML = '<h2>📋 ' + esc(a.name) + '</h2><span class="dk-meta"><span>' + plan.items.length + ' grup.</span><span>liko ' + left + '</span><span>Pabaiga apie ' + hhmm(plan.end) + '</span></span>' + delayChip(plan.delay);
  var st = document.getElementById('dk-start'); if (document.activeElement !== st) st.value = a.start || '09:30';
  document.getElementById('dk-clock').textContent = hhmm(nowMin());
  document.getElementById('dk-now').innerHTML = deskNowHtml(a, plan);
  renderFound(q, 'dk-found');
  var idx = open ? plan.items.findIndex(function (x) { return x.c === open; }) : -1, rows = plan.items.map(function (x) { return deskRow(x, open, q); });
  document.getElementById('dk-list').innerHTML = (idx >= 0 ? rows.slice(0, idx + 1) : rows).join('') || '<li class="dk-empty muted">Šiam tatamiui grupių dar nėra. Jas paskirstysite „🏟 Visi tatamiai“ (tempkite kortelę į tatamį).</li>';
  document.getElementById('dk-after').innerHTML = idx >= 0 ? rows.slice(idx + 1).join('') : '';
  deskEl.classList.toggle('has-open', idx >= 0);
}
function toggleDeskGroup(id) {
  if (deskOpenCat() && S.selected === id) { setRun(false); resetSwap(); render(); deskScrollTo(id); return; }
  openRun(id);
}
if (deskEl) {
  deskEl.addEventListener('click', function (e) {
    var t = e.target;
    if (skipDeskClick || t.closest('#dk-run')) return;
    var nav = t.closest('[data-dk]');
    if (nav) {
      var v = nav.dataset.dk, a = deskArea();
      if (v === 'live') { if (a) setLive(a.id); }
      else if (v === 'all') { setDeskAll(true); setRun(false); render(); window.scrollTo(0, 0); }
      else window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }
    var as = t.closest('[data-award-send]'); if (as) { setAward([as.dataset.awardSend], 'sent'); return; }
    var bo = t.closest('[data-dk-bout]'); if (bo) { var q = bo.dataset.dkBout.split('|'); openBout(q[0], q[1]); return; }
    var sq = t.closest('[data-sq-open]'); if (sq) { openRun(sq.dataset.sqOpen); return; }
    if (t.closest('input, select, a, [data-dk-grip]')) return;
    var row = t.closest('[data-dk-open]') || t.closest('[data-dk-row]');
    if (row) toggleDeskGroup(row.dataset.dkOpen || row.dataset.dkRow);
  });
  deskEl.addEventListener('change', function (e) {
    var t = e.target;
    if (t.id === 'dk-area') { setRun(false); setMyArea(t.value); return; }
    if (t.id === 'dk-start') { var a = deskArea(); if (a) { a.start = t.value || '09:00'; save(); render(); } return; }
    if (t.dataset.catAt) setCatAt(t);
  });
  deskEl.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.dataset && e.target.dataset.catAt) e.target.blur(); });
  document.getElementById('dk-q').addEventListener('input', function (e) { deskQ = e.target.value; var a = deskArea(); if (a) renderDesk(a, deskOpenCat()); });
  // Grupių eilė: tempiama už ⠿ (pele ir pirštu); kol tempiama - automatinis perpiešimas palaukia (dragId)
  deskEl.addEventListener('pointerdown', function (e) {
    var g = e.target.closest('[data-dk-grip]'); if (!g || e.button > 0) return;
    var li = g.closest('.dk-row'); if (!li) return;
    e.preventDefault();
    dkd = { id: li.dataset.dkRow, li: li, x: e.clientX, y: e.clientY, on: false, gh: null, before: undefined };
  });
  document.addEventListener('pointermove', function (e) {
    if (!dkd) return;
    if (!dkd.on) {
      if (Math.abs(e.clientY - dkd.y) + Math.abs(e.clientX - dkd.x) < 6) return;
      var r = dkd.li.getBoundingClientRect();
      dkd.on = true; setDragId(dkd.id); dkd.dy = dkd.y - r.top;
      dkd.gh = dkd.li.cloneNode(true); dkd.gh.classList.add('dk-ghost');
      dkd.gh.style.cssText = 'position:fixed;left:' + r.left + 'px;top:' + r.top + 'px;width:' + r.width + 'px;pointer-events:none;z-index:2000;';
      document.body.appendChild(dkd.gh); dkd.li.classList.add('dragging'); document.body.classList.add('is-dragging-name');
    }
    e.preventDefault();
    dkd.gh.style.top = (e.clientY - dkd.dy) + 'px';
    var rows = Array.prototype.slice.call(deskEl.querySelectorAll('.dk-row')), before = null;
    for (var i = 0; i < rows.length; i++) { var rr = rows[i].getBoundingClientRect(); if (e.clientY < rr.top + rr.height / 2) { before = rows[i]; break; } }
    deskEl.querySelectorAll('.dk-drop-before, .dk-drop-after').forEach(function (x) { x.classList.remove('dk-drop-before', 'dk-drop-after'); });
    if (before) before.classList.add('dk-drop-before'); else if (rows.length) rows[rows.length - 1].classList.add('dk-drop-after');
    dkd.before = before ? before.dataset.dkRow : null;
    // Prie ekrano krašto - slenkama
    if (e.clientY < 90) window.scrollBy(0, -12); else if (e.clientY > window.innerHeight - 50) window.scrollBy(0, 12);
  }, { passive: false });
  var deskDrop = function (cancel) {
    if (!dkd) return;
    var d = dkd; dkd = null;
    if (!d.on) return;
    setDragId(null); skipDeskClick = true; setTimeout(function () { skipDeskClick = false; }, 0);
    if (d.gh) d.gh.remove();
    document.body.classList.remove('is-dragging-name');
    deskEl.querySelectorAll('.dk-drop-before, .dk-drop-after, .dragging').forEach(function (x) { x.classList.remove('dk-drop-before', 'dk-drop-after', 'dragging'); });
    var a = deskArea();
    if (cancel || !a || d.before === undefined || d.before === d.id) return;
    var list = S.categories.filter(function (x) { return x.tatami === a.id; }), i = list.findIndex(function (x) { return x.id === d.id; });
    if (d.before === (list[i + 1] ? list[i + 1].id : null)) return;   // ta pati vieta
    moveCat(d.id, a.id, d.before);
    toast('Eilė pakeista - laikai persiskaičiavo.');
  };
  document.addEventListener('pointerup', function () { deskDrop(false); });
  document.addEventListener('pointercancel', function () { deskDrop(true); });
}

// ---------- v2.01: „🧑‍💼 Sekretoriatas“ → „📋 Sekretoriato darbastalis“: atidaryti čia arba nukopijuoti nuorodą tatamiui (&t=N) ----------
export function renderSekrDesk() {
  var box = document.getElementById('sekr-desk'), links = document.getElementById('sekr-links');
  if (box) {
    var h = S.areas.map(function (a) { return '<button type="button" class="btn btn-sm" data-desk-open="' + a.id + '">📋 ' + esc(a.name) + '</button>'; }).join('');
    if (box.dataset.h !== h) { box.dataset.h = h; box.innerHTML = h; }
  }
  if (links && links.dataset.url) {
    var l = S.areas.map(function (a) { return '<button type="button" class="btn btn-sm" data-copy="' + esc(links.dataset.url + '&t=' + a.id) + '" title="Nuoroda sekretoriatui: ' + esc(a.name) + '">⧉ ' + esc(a.name) + '</button>'; }).join('');
    if (links.dataset.h !== l) { links.dataset.h = l; links.innerHTML = l; }
  }
}
var sekrPanel = document.getElementById('sekr-panel');
if (sekrPanel) sekrPanel.addEventListener('click', function (e) {
  var b = e.target.closest('[data-desk-open]'); if (!b) return;
  sekrPanel.hidden = true; var st = document.getElementById('sekr-toggle'); if (st) st.setAttribute('aria-expanded', 'false');
  setRun(false); myArea = b.dataset.deskOpen; setDeskAll(false);
  try { localStorage.setItem(KEY + '-myarea', myArea); } catch (x) {}
  showTab('live'); window.scrollTo(0, 0);
});
