// Tvarkaraštis: tatamių planas ir laikai, kita grupė, paieška, grupių perkėlimas ir laikas
import { CFG, esc, hhmm, pad2, POPUP, toast, toMin } from './util.js';
import { DISC, person } from './rules.js';
import { addStream } from './video.js';
import { catPlaces, doneCount, matchCount } from './engine/category.js';
import { cat, S, save, setAward, snapshot } from './store.js';
import { openRun, render } from './chrome.js';
import { critName } from './views/categories.js';
import { diplomaUrl } from './views/draw.js';
import { ageGroup, ageName, catStatus, ST_LABEL, stChip } from './results.js';
import { areaById, deskArea, renderAreaPick, shownAreas } from './views/desk.js';
import { isDetached, pinCat, winCh } from './views/live.js';
import { ytEmbed } from './views/results.js';

// ---------- Tvarkaraštis ----------
export function minutesFor(c) { return Math.round(matchCount(c) * (S.minutes[c.discipline] || 2) * (c.team ? 3 : 1) + 2); }   // komandoje - 3 kovos
// Varžybų diena: ar šiandien (tada rodoma, ar tatamis vėluoja)
function liveDay() { var d = new Date(), s = d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); return !!CFG.date && s >= CFG.date && s <= (CFG.dateEnd || CFG.date); }
export function nowMin() { var d = new Date(); return d.getHours() * 60 + d.getMinutes() + d.getSeconds() / 60; }

// ---------- Sekanti grupė: pirma nebaigta po šios tame pačiame tatamyje ----------
export function nextGroup(c) {
  var a = areaById(c.tatami) || S.areas[0], items = areaPlan(a).items, i = items.findIndex(function (x) { return x.c === c; });
  var after = items.slice(i + 1).find(function (x) { return x.st !== 'done'; }) || items.find(function (x) { return x.st !== 'done' && x.c !== c; });
  return after ? after.c : null;
}
export function goRun(id) {
  if (POPUP) { S.selected = id; save(); render(); if (winCh) winCh.postMessage({ t: 'open', cat: id }); window.scrollTo(0, 0); return; }
  openRun(id);
  if (!deskArea()) window.scrollTo(0, 0);   // darbastalyje openRun pats parodo grupės eilutę
}
export function placesShort(c) {
  var pl = catPlaces(c).filter(function (x) { return x[0] <= 3; });
  return pl.map(function (x) { return x[1].map(function (id) { var p = person(c, id) || {}; return '<span class="rd-p"><span class="medal ' + ['', 'g', 's', 'b'][x[0]] + '">' + x[0] + '</span>' + esc(p.name || '') + ' <span class="muted">' + esc(p.club || '') + '</span></span>'; }).join(''); }).join('');
}
// Kai grupė baigta: „Siųsti apdovanojimams“ ir „Sekanti grupė →“
export function renderRunDone(c) {
  var box = document.getElementById('run-done'), st = catStatus(c);
  if (st === 'wait' || st === 'run') { box.hidden = true; box.innerHTML = ''; box.classList.remove('rd-float'); return; }
  var nx = nextGroup(c), others = ageGroup(c).filter(function (x) { return x !== c; });
  var pending = others.filter(function (x) { var s2 = catStatus(x); return s2 !== 'sent' && s2 !== 'awarded'; });
  var html = '<div class="rd-main"><span class="rd-mark">✓</span><div class="rd-txt"><div class="rd-title"><b>' + ({ sent: 'Baigta · išsiųsta apdovanojimams', awarded: 'Baigta ir apdovanota' }[st] || 'Grupė baigta') + '</b></div><div class="rd-places">' + placesShort(c) + '</div></div></div><div class="rd-btns">';
  if (st === 'fin') html += '<button type="button" class="btn btn-award" data-award-send="' + c.id + '">🏅 Siųsti apdovanojimams</button>';
  else if (st === 'sent') html += '<button type="button" class="btn btn-sm" data-award-undo="' + c.id + '" title="Grąžinti - dar nesiųsta">Atšaukti siuntimą</button>';
  html += nx ? '<button type="button" class="btn btn-primary rd-next" data-run-go="' + nx.id + '">Sekanti grupė: ' + esc(nx.name) + ' →</button>' : '<span class="muted small">Šio tatamio grupių daugiau nėra.</span>';
  if (CFG.diplomaApi && !CFG.readOnly) html += '<a class="btn btn-sm rd-dipl" href="' + esc(diplomaUrl(c.id, false)) + '" target="_blank" rel="noopener" title="Diplomai šios kategorijos prizininkams">🎓 Diplomai</a>';
  html += '</div>';
  if (others.length) {
    html += '<p class="rd-age small">Amžiaus grupė <b>' + esc(ageName(c)) + '</b>: ' + (pending.length
      ? 'apdovanojimų planšetėje ji užsidegs žaliai, kai bus išsiųsta ir ' + pending.map(function (x) { return esc(x.name) + ' <span class="muted">(' + ST_LABEL[catStatus(x)].toLowerCase() + ')</span>'; }).join(', ') + '.'
      : (st === 'fin' ? 'kitos rungtys jau išsiųstos - išsiuntus šią, planšetėje užsidegs žaliai.' : 'visos rungtys išsiųstos - planšetėje ji žalia.')) + '</p>';
  }
  // Dešinėje ekrano pusėje (nereikia slinkti į viršų pasibaigus paskutinei kovai); „▾“ - suskleisti
  html = '<button type="button" class="rd-min-btn" data-rd-min title="' + (rdMin ? 'Išskleisti' : 'Suskleisti') + '">' + (rdMin ? '▴' : '▾') + '</button>' + html;
  box.innerHTML = html; box.hidden = false; box.classList.add('rd-float'); box.classList.toggle('rd-min', rdMin);
}
var rdMin = false;
document.addEventListener('click', function (e) { var b = e.target.closest && e.target.closest('[data-rd-min]'); if (!b) return; rdMin = !rdMin; var bx = document.getElementById('run-done'); bx.classList.toggle('rd-min', rdMin); b.textContent = rdMin ? '▴' : '▾'; b.title = rdMin ? 'Išskleisti' : 'Suskleisti'; });
// Pačioje apačioje - visos šio tatamio grupės: kiek liko, kas toliau, būsenos
export function renderRunList(c, a, plan) {
  var left = plan.items.filter(function (x) { return x.st !== 'done'; }).length;
  document.getElementById('run-list').innerHTML = '<h2>' + esc(a.name) + ' - visos grupės <span class="muted small">liko ' + left + ' iš ' + plan.items.length + ' · pabaiga apie ' + hhmm(plan.end) + '</span></h2>' +
    '<ol class="rl">' + plan.items.map(function (x) {
      var st = catStatus(x.c, x.st === 'now');
      return '<li class="rl-row st-' + x.st + (x.c === c ? ' on' : '') + (isDetached(x.c.id) ? ' detached' : '') + '"><button type="button" data-run="' + x.c.id + '"><span class="rl-time">' + hhmm(x.start) + '</span>' +
        '<span class="rl-name"><span class="disc d-' + x.c.discipline + '"></span>' + esc(x.c.name) + (x.c === c ? ' <span class="rl-here">← atidaryta</span>' : '') + '</span>' +
        '<span class="rl-prog">' + x.done + '/' + x.total + '</span><span class="rl-badge">' + (x.st === 'next' ? ST_BADGE.next : '') + '</span>' + stChip(st) + '</button></li>';
    }).join('') + '</ol>';
}
export function areaPlan(a) {
  var t = toMin(a.start), out = [], cur = null;
  S.categories.filter(function (c) { return c.tatami === a.id; }).forEach(function (c) {
    if (c.at) t = Math.max(t, toMin(c.at));   // rungčiai nustatytas laikas „ne anksčiau kaip“
    var dur = minutesFor(c), total = matchCount(c), done = Math.min(doneCount(c), total), st;
    if ((total && done >= total) || (!total && c.competitors.length < 2)) st = 'done';
    else if (!cur) st = 'now';
    else st = out.length && out[out.length - 1].st === 'now' ? 'next' : 'later';
    var it = { c: c, start: t, dur: dur, total: total, done: done, pct: total ? Math.round(done / total * 100) : 0, st: st };
    if (st === 'now') cur = it;
    out.push(it); t += dur;
  });
  var delay = null;
  if (cur && liveDay()) {
    var left = cur.dur * (1 - cur.done / Math.max(1, cur.total)), rest = 0;
    out.forEach(function (x) { if (x.st !== 'done' && x !== cur) rest += x.dur; });
    delay = Math.round(nowMin() + left + rest - t);
  }
  return { items: out, end: t, delay: delay, cur: cur };
}
export function delayChip(d) {
  if (d === null) return '';
  if (d > 5) return '<span class="delay late" title="Pagal dabartinį tempą tatamis baigs vėliau nei planuota">vėluoja ~' + d + ' min</span>';
  if (d < -5) return '<span class="delay early" title="Tatamis lenkia planą">lenkia ' + (-d) + ' min</span>';
  return '<span class="delay ok">laiku</span>';
}
var editName = null, peopleOpen = {};
export var ST_BADGE = { now: '<span class="st-badge now">DABAR</span>', next: '<span class="st-badge next">toliau</span>', done: '<span class="st-badge done">✓</span>', later: '' };
// Paieška tvarkaraštyje: dalyvis, klubas ar kategorija → kur ir kada (be lietuviškų raidžių skirtumo)
var schedQ = '';
export function fold(x) { return String(x || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }
export function schedHits(c, q) { return q ? c.competitors.filter(function (p) { return fold(p.name + ' ' + p.club).indexOf(q) >= 0; }) : []; }
export function renderFound(q, boxId) {
  var box = document.getElementById(boxId || 'sched-found'); if (!box) return;
  if (!q) { box.hidden = true; box.innerHTML = ''; return; }
  var rows = [];
  S.areas.forEach(function (a) {
    areaPlan(a).items.forEach(function (x) {
      var c = x.c, hits = schedHits(c, q), catHit = fold(c.name).indexOf(q) >= 0;
      (hits.length ? hits : catHit ? [null] : []).forEach(function (p) { rows.push({ p: p, c: c, a: a, start: x.start, st: x.st }); });
    });
  });
  box.hidden = false;
  box.innerHTML = '<div class="sf-head"><b>Rasta: ' + rows.length + '</b> <span class="muted small">Paspauskite - atsidarys kategorija.</span></div>' + (rows.length ? '<div class="sf-list">' + rows.slice(0, 60).map(function (r) {
    return '<button type="button" class="sf-row" data-sq-open="' + r.c.id + '">' + (r.p ? '<b>' + esc(r.p.name) + '</b> <span class="muted">' + esc(r.p.club) + '</span>' : '<b>' + esc(r.c.name) + '</b>') +
      '<span class="sf-where"><span class="disc d-' + r.c.discipline + '"></span>' + (r.p ? esc(r.c.name) + ' · ' : '') + esc(r.a.name) + ' · ~' + hhmm(r.start) + '</span>' + (ST_BADGE[r.st] || (r.st === 'done' ? '<span class="st-badge">baigta</span>' : '')) + '</button>';
  }).join('') + '</div>' : '<p class="muted small">Nieko nerasta - patikrinkite vardą ar klubą.</p>');
}
var sqIn = document.getElementById('sched-q');
if (sqIn) {
  sqIn.addEventListener('input', function () { schedQ = sqIn.value; renderSched(); });
  document.getElementById('sched-found').addEventListener('click', function (e) { var b = e.target.closest('[data-sq-open]'); if (b) openRun(b.dataset.sqOpen); });
}
export function renderSched() {
  Object.keys(DISC).forEach(function (k) { document.getElementById('m-' + k).value = S.minutes[k]; });
  var q = fold(schedQ.trim());
  renderFound(q);
  var clock = document.getElementById('live-clock'); if (clock) clock.textContent = hhmm(nowMin());
  var wrap = document.getElementById('sched');
  renderAreaPick();
  var areas = shownAreas();
  wrap.classList.toggle('one', areas.length === 1);
  wrap.innerHTML = areas.map(function (a, ai) {
    var plan = areaPlan(a), list = plan.items.map(function (x) { return x.c; }), t = plan.end;
    var items = plan.items.map(function (x, idx) {
      var c = x.c, start = x.start, dur = x.dur, total = x.total, done = x.done, pct = x.pct;
      var moveOpts = S.areas.filter(function (y) { return y.id !== a.id; }).map(function (y) { return '<button class="btn" type="button" data-to="' + y.id + '" data-c="' + c.id + '" title="Perkelti į ' + esc(y.name) + '">→ ' + esc(y.name.replace(' TATAMIS', '')) + '</button>'; }).join('');
      var det = isDetached(c.id);
      var nameEl = editName === c.id ? '<input class="sel card-name-in" data-cname="' + c.id + '" value="' + esc(c.name) + '" aria-label="Rungties pavadinimas">'
        : '<button type="button" class="card-name" data-open-cat="' + c.id + '" title="Atidaryti: lentelė, kovos, balai"><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + '</button>';
      var hits = schedHits(c, q), hit = q && (hits.length || fold(c.name).indexOf(q) >= 0);
      return '<li class="card st-' + x.st + (det ? ' detached' : '') + (x.st === 'done' && catStatus(c) === 'fin' ? ' needs-send' : '') + (q ? (hit ? ' sq-hit' : ' sq-dim') : '') + '" draggable="true" data-c="' + c.id + '"><div class="card-top">' +
        '<span class="card-grip" data-card-grip title="Tempkite - pakeisti eilę ar tatamį (ir pirštu)" aria-hidden="true">⠿</span>' +
        '<input class="card-time-in" type="text" inputmode="numeric" maxlength="5" value="' + hhmm(start) + '" data-cat-at="' + c.id + '" title="' + (c.at ? 'Nustatytas laikas (ištrinkite - vėl skaičiuosis pats)' : 'Pakeiskite - rungtis prasidės ne anksčiau') + '"' + (c.at ? ' data-fixed="1"' : '') + '>' +
        nameEl + (det ? '<span class="st-badge det">atskirame lange</span>' : x.st === 'done' ? '' : ST_BADGE[x.st]) + (function () {
          var st = catStatus(c, x.st === 'now');
          return st === 'wait' ? '' : st === 'fin' ? '<button type="button" class="btn btn-award btn-xs" data-award-send="' + c.id + '" title="Siųsti apdovanojimams">🏅 Siųsti</button>' : stChip(st);
        })() + '</div>' +
        (peopleOpen[c.id] || hits.length ? '<div class="card-people">' + c.competitors.map(function (p) { return '<span' + (hits.indexOf(p) >= 0 ? ' class="sq-me"' : '') + '>' + esc(p.name) + ' <i>' + esc(p.club) + '</i></span>'; }).join('') + '</div>' : '') +
        '<div class="prog' + (pct === 100 ? ' done' : '') + '"><i style="width:' + pct + '%"></i><span>' + done + ' / ' + total + '</span></div>' +
        '<div class="card-actions"><button type="button" class="btn" data-people="' + c.id + '" aria-expanded="' + !!peopleOpen[c.id] + '" title="Dalyviai">👥 ' + c.competitors.length + '</button><span class="muted small">~' + dur + ' min</span>' +
        '<span class="card-tools"><button type="button" class="btn" data-rename="' + c.id + '" title="Pervadinti">✎</button><button type="button" class="btn" data-pin-cat="' + c.id + '" title="Prisegti langelį ekrane (ar nutempkite kortelę už tatamių)">📌</button><span class="spacer"></span>' +
        '<button class="btn" type="button" data-up="' + c.id + '"' + (idx === 0 ? ' disabled' : '') + ' aria-label="Aukštyn" title="Aukštyn">↑</button><button class="btn" type="button" data-down="' + c.id + '"' + (idx === list.length - 1 ? ' disabled' : '') + ' aria-label="Žemyn" title="Žemyn">↓</button>' + moveOpts + '</span></div></li>';
    }).join('');
    return '<div class="area' + (plan.delay !== null && plan.delay > 5 ? ' late' : '') + '" data-area="' + a.id + '"><div class="area-head"><input class="name" value="' + esc(a.name) + '" data-rename="' + a.id + '" aria-label="Tatamio pavadinimas">' +
      '<div class="area-meta"><label>Pradžia <input type="time" value="' + esc(a.start) + '" data-start="' + a.id + '"></label>' + delayChip(plan.delay) +
        (CFG.admin ? '<label class="area-vid" title="Telefonu filmuojamas tatamis: YouTube programėlėje „Transliuoti“ ir čia įklijuokite nuorodą - žiūrovai matys vaizdą su kovos rezultatu">📹 <input type="url" value="' + esc(a.stream || '') + '" data-stream="' + a.id + '" placeholder="YouTube Live nuoroda" aria-label="Tatamio vaizdo nuoroda (YouTube Live)"></label>' : '') + '</div>' +
      (plan.items.length ? '<button type="button" class="btn btn-primary area-go" data-start-run="' + a.id + '">▶ ' + (plan.cur ? 'Dabar: ' + esc(plan.cur.c.name) : 'Visos grupės baigtos') + '</button>' : '') + '</div>' +
      '<ul class="area-list" data-drop="' + a.id + '">' + (items || '<li class="muted small">Tempkite kategoriją čia</li>') + '</ul>' +
      '<div class="area-foot">Pabaiga apie <b>' + hhmm(t) + '</b> · ' + list.length + ' kateg.' + (S.areas.length > 1 && CFG.admin ? ' · <button class="area-del" type="button" data-del-area="' + a.id + '" title="Pašalinti šį tatamį (jo rungtys pereis į kitus)">Pašalinti tatamį</button>' : '') + '</div></div>';
  }).join('');
}
export function moveCat(id, areaId, beforeId) {
  var c = cat(id); if (!c) return;
  snapshot();
  S.categories.splice(S.categories.indexOf(c), 1);
  c.tatami = areaId;
  var idx = beforeId ? S.categories.findIndex(function (x) { return x.id === beforeId; }) : -1;
  if (idx === -1) {
    var last = -1; S.categories.forEach(function (x, i) { if (x.tatami === areaId) last = i; });
    S.categories.splice(last + 1, 0, c);
  } else S.categories.splice(idx, 0, c);
  save(); render();
}
export var sched = document.getElementById('sched'), dragId = null;
export function setDragId(v) { dragId = v; }
// Liečiamame ekrane (planšetė) HTML5 tempimas neveikia - kortelė tempiama už ⠿: į kitą eilę, kitą tatamį arba už tatamių (prisegti)
var cd = null;
sched.addEventListener('pointerdown', function (e) {
  var g = e.target.closest('[data-card-grip]'); if (!g || e.pointerType === 'mouse') return;
  var li = g.closest('.card'); if (!li) return;
  e.preventDefault();
  var r = li.getBoundingClientRect(), gh = li.cloneNode(true);
  gh.className += ' drag-ghost'; gh.style.cssText = 'position:fixed;left:' + r.left + 'px;top:' + r.top + 'px;width:' + r.width + 'px;pointer-events:none;z-index:2000;opacity:.9;';
  document.body.appendChild(gh); li.classList.add('dragging');
  cd = { id: li.dataset.c, gh: gh, dx: e.clientX - r.left, dy: e.clientY - r.top };
});
document.addEventListener('pointermove', function (e) {
  if (!cd) return; e.preventDefault();
  cd.gh.style.left = (e.clientX - cd.dx) + 'px'; cd.gh.style.top = (e.clientY - cd.dy) + 'px';
  var el = document.elementFromPoint(e.clientX, e.clientY), ul = el && el.closest('[data-drop]');
  sched.querySelectorAll('.drop').forEach(function (x) { x.classList.remove('drop'); }); if (ul) ul.classList.add('drop');
  document.body.classList.toggle('pin-drop', !ul);
}, { passive: false });
function cardDrop(e, cancel) {
  if (!cd) return;
  var id = cd.id; cd.gh.remove(); cd = null;
  document.body.classList.remove('pin-drop'); sched.querySelectorAll('.drop, .dragging').forEach(function (x) { x.classList.remove('drop', 'dragging'); });
  if (cancel) return;
  var el = document.elementFromPoint(e.clientX, e.clientY), ul = el && el.closest('[data-drop]');
  if (ul) { var over = el.closest('.card'); moveCat(id, +ul.dataset.drop, over && over.dataset.c !== id ? over.dataset.c : null); }
  else if (el && !el.closest('#sched')) pinCat(id, e.clientX, e.clientY);
}
document.addEventListener('pointerup', function (e) { cardDrop(e, false); });
document.addEventListener('pointercancel', function (e) { cardDrop(e, true); });
sched.addEventListener('keydown', function (e) { if (e.target.dataset && e.target.dataset.cname && (e.key === 'Enter' || e.key === 'Escape')) { if (e.key === 'Escape') { editName = null; renderSched(); } else e.target.blur(); } });
sched.addEventListener('dragstart', function (e) { var li = e.target.closest('.card'); if (!li || /INPUT/.test(e.target.tagName)) return; dragId = li.dataset.c; li.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', dragId); } catch (x) {} });
sched.addEventListener('dragend', function () { dragId = null; render(); });
sched.addEventListener('dragover', function (e) { var ul = e.target.closest('[data-drop]'); if (!ul || !dragId) return; e.preventDefault(); sched.querySelectorAll('.drop').forEach(function (x) { x.classList.remove('drop'); }); ul.classList.add('drop'); });
sched.addEventListener('drop', function (e) {
  var ul = e.target.closest('[data-drop]'); if (!ul || !dragId) return; e.preventDefault();
  var over = e.target.closest('.card'); var before = over && over.dataset.c !== dragId ? over.dataset.c : null;
  moveCat(dragId, +ul.dataset.drop, before);
});
sched.addEventListener('click', function (e) {
  var t = e.target, c, list, i;
  var oc = t.closest && t.closest('[data-open-cat]');
  if (oc) { openRun(oc.dataset.openCat); return; }
  var pc = t.closest && t.closest('[data-pin-cat]'); if (pc) { pinCat(pc.dataset.pinCat); return; }
  var sr = t.closest && t.closest('[data-start-run]');
  if (sr) { var pl = areaPlan(areaById(sr.dataset.startRun)), go = pl.cur || pl.items[pl.items.length - 1]; if (go) openRun(go.c.id); return; }
  var as = t.closest && t.closest('[data-award-send]'); if (as) { setAward([as.dataset.awardSend], 'sent'); return; }
  var pb = t.closest && t.closest('[data-people]'); if (pb) { peopleOpen[pb.dataset.people] = !peopleOpen[pb.dataset.people]; renderSched(); return; }
  var rb = t.closest && t.closest('[data-rename]'); if (rb) { editName = rb.dataset.rename; renderSched(); var ip = document.querySelector('[data-cname="' + editName + '"]'); if (ip) { ip.focus(); ip.select(); } return; }
  if (t.dataset.to) { moveCat(t.dataset.c, +t.dataset.to, null); return; }
  if (t.dataset.up || t.dataset.down) {
    c = cat(t.dataset.up || t.dataset.down);
    list = S.categories.filter(function (x) { return x.tatami === c.tatami; }); i = list.indexOf(c);
    var other = t.dataset.up ? list[i - 1] : list[i + 1]; if (!other) return;
    snapshot();
    var a = S.categories.indexOf(c), b = S.categories.indexOf(other); S.categories[a] = other; S.categories[b] = c;
    save(); render(); return;
  }
  if (t.dataset.delArea) {
    var id = +t.dataset.delArea, to = S.areas.find(function (x) { return x.id !== id; }).id;
    snapshot(); S.categories.forEach(function (x) { if (x.tatami === id) x.tatami = to; });
    S.areas = S.areas.filter(function (x) { return x.id !== id; }); save(); render();
  }
});
// Grupės laikas „ne anksčiau kaip“ (tvarkaraščio kortelėje ir darbastalio eilutėje); tuščias - skaičiuojasi pats
export function setCatAt(t0) {
  var ca = cat(t0.dataset.catAt), tv = t0.value.trim().replace('.', ':'); if (!ca) return;
  if (tv && !/^\d{1,2}:\d{2}$/.test(tv)) { toast('Laiką įrašykite taip: 13:45'); render(); return; }
  snapshot(); if (tv) ca.at = hhmm(toMin(tv)); else delete ca.at; save(); render();
}
sched.addEventListener('change', function (e) {
  var t0 = e.target;
  if (t0.dataset.catAt) { setCatAt(t0); return; }
  if (t0.dataset.cname) { var cn = cat(t0.dataset.cname); editName = null; if (!cn) return; var v = t0.value.trim(); if (v && v !== cn.name) { snapshot(); cn.name = v; cn.customName = v !== critName(cn); save(); } render(); return; }
  var t = e.target;
  if (t.dataset.start) { S.areas.find(function (a) { return a.id === +t.dataset.start; }).start = t.value || '09:00'; }
  else if (t.dataset.rename) { S.areas.find(function (a) { return a.id === +t.dataset.rename; }).name = t.value.trim() || 'TATAMIS'; }
  else if (t.dataset.stream) {   // 📹 tatamio vaizdas (YouTube Live) - žiūrovų puslapyje su kovos rezultatu ant vaizdo
    var v = t.value.trim();
    if (v && !ytEmbed(v)) { toast('Įklijuokite YouTube nuorodą (pvz. https://youtube.com/live/…)'); return; }
    var sa = S.areas.find(function (a) { return a.id === +t.dataset.stream; });
    sa.stream = v; addStream(sa, v);   // v2.13: įrašas ir jo pradžia - kad baigtos kovos (🎬) atsidarytų YouTube įraše nuo savo pradžios
  }
  else return;
  save(); render();
});
document.getElementById('area-new').addEventListener('click', function () {
  var id = Math.max.apply(null, S.areas.map(function (a) { return a.id; })) + 1;
  S.areas.push({ id: id, name: id + ' TATAMIS', start: S.areas[0] ? S.areas[0].start : '09:30' }); save(); render();
});
Object.keys(DISC).forEach(function (k) {
  document.getElementById('m-' + k).addEventListener('change', function (e) { var v = parseFloat(e.target.value); if (v > 0) { S.minutes[k] = v; save(); render(); } });
});
// Kategorijos paskirstomos tatamiams taip, kad pabaigos laikai būtų kuo artimesni (ilgiausios - pirmos)
export function balance() {
  var load = {}; S.areas.forEach(function (a) { load[a.id] = toMin(a.start); });
  S.categories.slice().sort(function (a, b) { return minutesFor(b) - minutesFor(a); }).forEach(function (c) {
    var best = S.areas[0].id; S.areas.forEach(function (a) { if (load[a.id] < load[best]) best = a.id; });
    c.tatami = best; load[best] += minutesFor(c);
  });
}
document.getElementById('auto-balance').addEventListener('click', function () { snapshot(); balance(); save(); render(); });
