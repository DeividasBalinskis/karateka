// Kova: skydelis su balais, laikrodis ir gongas, ekranas salėje (v2.12: telefono vaizdo sekretoriatui nebėra - jį mato žiūrovai)
import { CFG, esc, POPUP, toast, YEAR } from './util.js';
import { boutMode, cfg, isElim, MODE_NAME, person } from './rules.js';
import { allMatches, bracket } from './engine/bracket.js';
import { rrMatches } from './engine/rr.js';
import { fmtScore, scoreFinalists, scoreRank, scoreTotal } from './engine/score.js';
import { autoLock, catFinished, prune } from './engine/category.js';
import { cat, S, save, snapshot } from './store.js';
import { render } from './chrome.js';
import { goNext, kataOf, prepLater } from './views/draw.js';
import { stamp, unstamp } from './video.js';
import './schedule.js';   // v2.12: vardų nebereikia, modulių eilė - kaip anksčiau
import './views/desk.js';
import './views/live.js';
import { upcoming } from './views/results.js';

// ---------- Kova: balai, laikrodis, transliacija į ekraną ----------
// Atidaroma ▶ prie kovos. Kumite: +1/+2/+3/−1 ir laikrodis; komandinis kumite - 3 kovos, balai sumuojasi;
// sumo - kas pirmas surenka N taškų; komandinės katos finalas - Kata ir Pritaikymas balais (kraštiniai atmetami, matosi perbraukti).
export var liveMatch = null, B = null;
export function findMatch(c, mid) {
  if (!c || !c.draw) return null;
  var list = c.draw.system === 'rr' ? rrMatches(c) : isElim(c.draw.system) ? allMatches(bracket(c)) : [];
  return list.find(function (m) { return m.id === mid; }) || null;
}
function leftName(L) { return L === 1 ? 'Finalas' : L === 2 ? 'Pusfinalis' : L === 3 ? 'Ketvirtfinalis' : '1/' + Math.pow(2, L - 1); }
export function matchTitle(c, m) {
  var t = m.left ? leftName(m.left) : m.id === 'bronze' ? 'Kova dėl 3 vietos' : m.rep ? 'Paguoda' : m.round ? m.round + ' ratas' : 'Kova';
  var mode = boutMode(c, m);
  return t + (c.discipline === 'fukugo' ? (mode === 'flags' ? ' · Kitei' : ' · Kumite') : '');
}
// Viešai (ekranas, žiūrovų nuoroda): vaikų ir jaunimo - vardas ir pavardės raidė
export function pubNameShort(id) { return pubName(id); }
export function pubName(id) {
  if (S.teams[id]) return S.teams[id].name;
  var p = S.people[id]; if (!p) return '';
  if (!(p.birth && YEAR - p.birth < 18)) return p.name;
  var w = p.name.split(' '); return w[0] + (w[1] ? ' ' + w[1].charAt(0) + '.' : '');
}
export function areaOf(c) { return S.areas.find(function (a) { return a.id === c.tatami; }) || S.areas[0]; }
function judgeTotal(c, arr) {
  var J = cfg(c).judges, v = (arr || []).slice(0, J).map(function (x) { return parseFloat(x); });
  if (v.length < J || v.some(function (x) { return isNaN(x); })) return null;
  var sum = v.reduce(function (a, b) { return a + b; }, 0);
  if (cfg(c).drop && J >= 5) sum -= Math.max.apply(null, v) + Math.min.apply(null, v);
  return Math.round(sum * 100) / 100;
}
// Kurie teisėjų balai atmesti (aukščiausias ir žemiausias) - jie rodomi perbraukti, bet nedingsta
export function droppedIdx(c, arr) {
  var J = cfg(c).judges, v = (arr || []).slice(0, J).map(function (x) { return parseFloat(x); });
  if (!cfg(c).drop || J < 5 || v.length < J || v.some(function (x) { return isNaN(x); })) return [];
  var hi = v.indexOf(Math.max.apply(null, v)), lo = v.indexOf(Math.min.apply(null, v));
  if (lo === hi) lo = v.indexOf(Math.min.apply(null, v), hi + 1);
  return [hi, lo];
}
function teamMembers(id) { return S.teams[id] ? S.teams[id].members.filter(function (x) { return S.people[x]; }) : []; }

export var boutEl = document.createElement('div');
boutEl.className = 'bout'; boutEl.id = 'bout'; boutEl.hidden = true;
boutEl.addEventListener('toggle', function (e) { if (e.target.classList && e.target.classList.contains('bt-more')) boutMore = e.target.open; }, true);
boutEl.setAttribute('role', 'dialog'); boutEl.setAttribute('aria-modal', 'true'); boutEl.setAttribute('aria-label', 'Kova');
document.body.appendChild(boutEl);

// Nugalėtojas paspaudus vardą prisegtame langelyje; dar kartą - atšaukiama
export function toggleResult(catId, mid, pid) {
  var pc = cat(catId); if (!pc || !pc.draw) return;
  if (B && B.cat === catId && B.mid === mid) { toast('Ši kova atidaryta kovos skydelyje - baikite ją ten.'); return; }
  snapshot();
  var wasDone = catFinished(pc);
  if (pc.draw.results[mid] === pid) { delete pc.draw.results[mid]; unstamp(pc, mid); } else { if (!pc.draw.results[mid]) stamp(pc, mid); pc.draw.results[mid] = pid; }
  prune(pc); autoLock(pc, wasDone); save(); render();
}
export function openBout(catId, mid) {

  var c = cat(catId), m = findMatch(c, mid); if (!m || !m.a || !m.b) return;
  var mode = boutMode(c, m), f = cfg(c), saved = (c.draw.bouts || {})[mid] || {};
  SL = null; slKey = '';
  B = { cat: c.id, mid: mid, mode: mode, a: m.a, b: m.b, title: matchTitle(c, m), no: m.no || '',
    sa: saved.sa || 0, sb: saved.sb || 0, log: saved.log || [], i: saved.i || 0,
    ma: saved.ma || teamMembers(m.a), mb: saved.mb || teamMembers(m.b),
    parts: saved.parts || { a: { k: [], p: [] }, b: { k: [], p: [] } },
    timer: null, winner: null, t0: saved.t0 || Date.now(), go: saved.go || 0 };   // v2.13: t0 - skydelis atidarytas, go - pirmą kartą paleistas laikas (🎬 kovos pradžia)
  var kum = mode === 'kumite' || mode === 'team', t0 = saved.tTotal !== undefined ? saved.tTotal : kum ? 10000 : f.time * 1000;
  B.timer = { total: t0, remaining: saved.remaining !== undefined ? saved.remaining : t0, running: false, at: Date.now(), up: !!saved.tUp, preset: saved.tPreset || (kum ? 'Jiyu ippon' : '') };
  liveMatch = { cat: c.id, mid: mid };
  boutEl.hidden = false; document.body.classList.add('bout-open-body');
  renderBout(); pushLive(); render();
  if (!POPUP) { try { history.pushState({ vz: 1, tab: 'live', run: S.selected, bout: mid }, ''); boutHist = true; } catch (e) {} }
}
var boutHist = false;
export function setBoutHist(v) { boutHist = v; }
export function keepBout() {
  // Nebaigtos kovos balai išsaugomi (perkrovus puslapį - tęsiama)
  var c = cat(B.cat); c.draw.bouts = c.draw.bouts || {};
  c.draw.bouts[B.mid] = { sa: B.sa, sb: B.sb, log: B.log, i: B.i, ma: B.ma, mb: B.mb, parts: B.parts, remaining: timeLeft(), tTotal: B.timer.total, tUp: B.timer.up || undefined, tPreset: B.timer.preset || undefined, t0: B.t0, go: B.go || undefined };
  save();
}
export function closeBout() {
  if (!B) return;
  if (B.timer.running) toggleTimer();
  var area = areaOf(cat(B.cat));
  B = null; liveMatch = null; boutEl.hidden = true; document.body.classList.remove('bout-open-body');
  pushLive(area); render();
  if (boutHist && history.state && history.state.bout) { boutHist = false; history.back(); }
  boutHist = false;
}
// Enbu (t.up): laikas skaičiuojamas nuo 0 į priekį; „remaining“ tada - jau praėjęs laikas
function timeLeft() { var t = B.timer; if (t.up) return Math.min(30 * 60000, t.running ? t.remaining + (Date.now() - t.at) : t.remaining); return Math.max(0, t.running ? t.remaining - (Date.now() - t.at) : t.remaining); }
// Atgalinis laikas apvalinamas į viršų (0:01 rodoma iki pat galo), laikmatis nuo 0 (Enbu) - į apačią, kad prasidėtų nuo 0:00 (#134)
function fmtTime(ms, up) { var s = up ? Math.floor(ms / 1000) : Math.ceil(ms / 1000), m = Math.floor(s / 60); s = s % 60; return m + ':' + (s < 10 ? '0' : '') + s; }
var tickTimer = null, beeped = false;
function toggleTimer() {
  var t = B.timer;
  if (t.running) { t.remaining = timeLeft(); t.running = false; clearInterval(tickTimer); }
  else { if (!t.up && timeLeft() <= 0) return; audio(); t.at = Date.now(); t.running = true; if (!B.go) B.go = t.at; beeped = false; warned30 = timeLeft() <= 30000; tickTimer = setInterval(tick, 200); }
  t.at = Date.now(); renderTimer(); pushLive();
}
function setTime(ms) { var t = B.timer; t.remaining = Math.max(0, Math.min(ms, 30 * 60000)); t.at = Date.now(); if (t.running) { clearInterval(tickTimer); tickTimer = setInterval(tick, 200); } renderTimer(); pushLive(); }
function tick() {
  if (!B) { clearInterval(tickTimer); return; }
  if (B.timer.running && !B.timer.up && isJiyu() && !warned30 && timeLeft() <= 30000 && timeLeft() > 0) { warned30 = true; beep(); }
  if (!B.timer.up && timeLeft() <= 0 && B.timer.running) {
    B.timer.remaining = 0; B.timer.running = false; clearInterval(tickTimer);
    if (!beeped) { beep(); beeped = true; if (isJiyu()) setTimeout(function () { beep(); }, 380); }   // #152: dvigubas gongas - antras iškart po pirmo
    pushLive();
    // Laikas baigėsi pats - po 3 s laikmatis atsistato iš naujo (kitai kovai / pratęsimui), jei niekas jo nepalietė
    var bt = B;
    setTimeout(function () { if (B === bt && !B.timer.running && !B.timer.up && timeLeft() <= 0) setTime(B.timer.total); }, 3000);
  }
  renderTimer();
}
// Gongas, kai baigiasi laikas: keli nedarnūs dažniai su ilgu nuslopimu + kompresorius, kad būtų garsu.
var actx = null;
function audio() {
  try { if (!actx) actx = new (window.AudioContext || window.webkitAudioContext)(); if (actx.state === 'suspended') actx.resume(); } catch (e) { actx = null; }
  return actx;
}
// #151: gongo garsai - pasirenkama kovos skydelyje (šiam įrenginiui)
var GONGS = { deep: 'Gilus gongas', bright: 'Šviesus gongas', bell: 'Varpas' };
function gongKind() { try { var k = localStorage.getItem('budo-gong'); return GONGS[k] ? k : 'deep'; } catch (e) { return 'deep'; } }
export function beep(kind) {
  kind = kind || gongKind();
  if (kind !== 'deep') { gongTone(kind); return; }
  var a = audio(); if (!a) return;
  try {
    var t = a.currentTime + 0.02, comp = a.createDynamicsCompressor(), out = a.createGain();
    comp.threshold.value = -20; comp.ratio.value = 6; out.gain.value = 1.6;
    comp.connect(out); out.connect(a.destination);
    [[98, 1], [147, 0.7], [196, 0.55], [271, 0.45], [383, 0.3], [502, 0.22], [661, 0.14]].forEach(function (pt, i) {
      var o = a.createOscillator(), g = a.createGain(), dur = 4.2 - i * 0.35;
      o.type = i ? 'sine' : 'triangle'; o.frequency.setValueAtTime(pt[0] * 1.012, t); o.frequency.exponentialRampToValueAtTime(pt[0], t + 0.4);
      g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(pt[1], t + 0.015); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
      o.connect(g); g.connect(comp); o.start(t); o.stop(t + dur + 0.05);
    });
    var n = a.createBufferSource(), buf = a.createBuffer(1, a.sampleRate * 0.12, a.sampleRate), d = buf.getChannelData(0);
    for (var i = 0; i < d.length; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / d.length, 3);
    var ng = a.createGain(); ng.gain.value = 0.5; n.buffer = buf; n.connect(ng); ng.connect(comp); n.start(t);
  } catch (e) {}
}
// Šviesus gongas ir varpas: kiti dažniai ir nuslopimas (be failų - sugeneruojama naršyklėje)
function gongTone(kind) {
  var a = audio(); if (!a) return;
  try {
    var t = a.currentTime + 0.02, comp = a.createDynamicsCompressor(), out = a.createGain();
    comp.threshold.value = -20; comp.ratio.value = 6; out.gain.value = 1.5; comp.connect(out); out.connect(a.destination);
    var parts = kind === 'bell' ? [[523, 1, 2.6], [1046, .5, 2.0], [1318, .35, 1.6], [1568, .25, 1.3], [2093, .18, 1.0], [2637, .1, .8]]
      : [[196, 1, 3.4], [294, .7, 3.0], [392, .6, 2.6], [587, .45, 2.2], [784, .3, 1.8], [1175, .18, 1.2]];
    parts.forEach(function (pt, i) {
      var o = a.createOscillator(), g = a.createGain();
      o.type = 'sine'; o.frequency.setValueAtTime(pt[0] * (kind === 'bell' ? 1 : 1.01), t); o.frequency.exponentialRampToValueAtTime(pt[0], t + 0.3);
      g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(pt[1], t + 0.01); g.gain.exponentialRampToValueAtTime(0.0001, t + pt[2]);
      o.connect(g); g.connect(comp); o.start(t); o.stop(t + pt[2] + 0.05);
    });
  } catch (e) {}
}
// „Jiyu / Pratęsimas“: likus 30 s - vienas gongas, laikui baigiantis - du (#151)
function isJiyu() { return B && B.timer && /Jiyu \/ Pratęsimas/.test(B.timer.preset || ''); }
var warned30 = false;
function renderTimer() {
  var el = document.getElementById('bt-timer'); if (!el || !B || el.tagName === 'INPUT') return;
  var left = timeLeft();
  el.textContent = fmtTime(left, B.timer.up);
  el.className = 'bt-timer' + (B.timer.up ? ' up' : left <= 0 ? ' over' : left <= 15000 ? ' last' : '') + (B.timer.running ? ' running' : '');
  var tb = document.getElementById('bt-start'); if (tb) tb.textContent = B.timer.running ? '⏸ Sustabdyti' : '▶ Pradėti';
}
function sideHtml(side) {
  var c = cat(B.cat), id = side === 'a' ? B.a : B.b, p = person(c, id) || {}, sc = side === 'a' ? B.sa : B.sb, mode = B.mode;
  var team = mode === 'team', list = side === 'a' ? B.ma : B.mb, cur = list[B.i] || list[list.length - 1];
  var html = '<div class="bt-side ' + (side === 'a' ? 'aka' : 'shiro') + '"><div class="bt-tag">' + (side === 'a' ? 'AKA' : 'SHIRO') + '</div>' +
    '<div class="bt-name">' + esc(p.name || '') + '</div><div class="bt-club">' + esc(p.club || '') + '</div>';
  if (team) html += '<label class="bt-member">Kovoja <select class="sel" data-tm="' + side + '">' + list.map(function (x, j) { return '<option value="' + j + '"' + (j === B.i ? ' selected' : '') + '>' + esc(S.people[x].name) + '</option>'; }).join('') + '</select></label>';
  // Didelį balą galima ir įrašyti (paspausti ant skaičiaus)
  html += '<input class="bt-score" type="number" min="0" max="99" inputmode="numeric" data-sc="' + side + '" value="' + sc + '" aria-label="' + (side === 'a' ? 'AKA' : 'SHIRO') + ' balai" title="Paspauskite ir įrašykite balą">';
  if (mode === 'sumo') html += '<div class="bt-btns"><button type="button" class="btn bt-big" data-pt="' + side + '|1">+1 taškas</button><button type="button" class="btn" data-pt="' + side + '|-1">−1</button></div>' +
    '<div class="muted small">Laimi surinkęs ' + cfg(c).sumoTo + '</div>';
  else html += '<div class="bt-btns">' + [1, 2, 4].map(function (n) { return '<button type="button" class="btn bt-big" data-pt="' + side + '|' + n + '">+' + n + '</button>'; }).join('') + '<button type="button" class="btn" data-pt="' + side + '|-1">−1</button></div>';
  return html + '</div>';
}
function partsTable() {
  var c = cat(B.cat), J = cfg(c).judges, side = function (sd) {
    var id = sd === 'a' ? B.a : B.b, p = person(c, id) || {}, pr = B.parts[sd], rows = '';
    [['k', 'Kata'], ['p', 'Pritaikymas']].forEach(function (x) {
      var part = x[0], drop = droppedIdx(c, pr[part]), cells = '', t = judgeTotal(c, pr[part]);
      for (var j = 0; j < J; j++) cells += '<input class="j-in' + (drop.indexOf(j) >= 0 ? ' dropped' : '') + '" type="number" step="0.1" min="0" max="10" inputmode="decimal" aria-label="' + x[1] + ' T' + (j + 1) + '" data-part="' + sd + '|' + part + '|' + j + '" value="' + (pr[part][j] !== undefined && pr[part][j] !== null ? esc(pr[part][j]) : '') + '">';
      rows += '<div class="bp-row"><span class="bp-label">' + x[1] + '</span><span class="bp-cells">' + cells + '</span><b class="bp-sum">' + (t === null ? '–' : t.toFixed(1)) + '</b></div>';
    });
    var tk = judgeTotal(c, pr.k), tp = judgeTotal(c, pr.p);
    return '<div class="bt-pside ' + (sd === 'a' ? 'aka' : 'shiro') + '"><div class="bt-tag">' + (sd === 'a' ? 'AKA' : 'SHIRO') + '</div><div class="bt-name">' + esc(p.name || '') + '</div>' + rows +
      '<div class="bp-total">Iš viso <b>' + (tk === null || tp === null ? '–' : (tk + tp).toFixed(1)) + '</b></div></div>';
  };
  return '<div class="bt-psides">' + side('a') + side('b') + '</div>' +
    '<p class="hint">' + (cfg(c).drop && J >= 5 ? 'Aukščiausias ir žemiausias kiekvienos dalies balas atmetamas - jie lieka matomi perbraukti. ' : '') + 'Iš viso = Kata + Pritaikymas.</p>';
}
// Kumite laiko šablonai: Jiyu ippon ir Ko-Go - 10 s (pradinis), Jiyu / Pratęsimas - 1:30, Enbu - paprastas laikmatis nuo 0 (skaičiuoja į priekį)
var TIME_PRESETS = [['Jiyu ippon', 10], ['Ko-Go', 10], ['Jiyu / Pratęsimas', 90], ['Enbu', 0, 1]];
function timePresets() { return TIME_PRESETS; }
var TOUCH = window.matchMedia && matchMedia('(pointer: coarse)').matches;
// B3: „⏱ laikas ir gongas“ - kompiuteryje atidaryta, telefone suskleista; atsimenama, kol kova atidaryta (perpiešiant)
var boutMore = null;
function boutMoreOpen() { return boutMore !== null ? boutMore : window.innerWidth > 600; }
// #126: kovos skydelyje matosi, kas ruošiasi (kita pora šioje grupėje arba kita grupė)
function boutNextHtml(c) {
  var nx = upcoming(c).filter(function (x) { return x.id !== B.mid; })[0];
  var nm = function (id) { return esc((person(c, id) || {}).name || ''); };
  if (nx && nx.a && nx.b) return '<div class="sv-prep bt-next"><b>Ruošiasi:</b> <span class="sv-dot a"></span>' + nm(nx.a) + ' <span class="muted">vs</span> <span class="sv-dot b"></span>' + nm(nx.b) + '</div>';
  var later = prepLater(c, { id: B.mid });
  return later ? later.replace('class="sv-prep"', 'class="sv-prep bt-next"') : '';
}
function renderBout() {
  if (!B) return;
  var c = cat(B.cat), area = areaOf(c), body, mode = B.mode, f = cfg(c);
  var head = '<div class="bout-top"><div class="bout-info"><span class="disc d-' + c.discipline + '"></span><b>' + esc(c.name) + '</b><span>' + (B.no ? B.no + ' · ' : '') + esc(B.title) + '</span><span class="pill sys">' + MODE_NAME[mode] + '</span><span class="pill">' + esc(area.name) + '</span></div>' +
    '<span class="spacer"></span>' +
    (CFG.screenUrl ? '<button type="button" class="btn" id="bt-screen" title="Atidaryti ekrano langą šiam tatamiui (antram monitoriui ar televizoriui)" aria-label="Ekranas">📺<span class="bt-lbl"> Ekranas</span></button>' : '') +
    '<button type="button" class="btn" id="bt-close" aria-label="Uždaryti">✕<span class="bt-lbl"> Uždaryti</span></button></div>';
  if (mode === 'score2') {
    body = partsTable();
  } else {
    var mid = '<div class="bt-mid">' + (mode === 'team' ? '<div class="bt-round">Kova ' + (B.i + 1) + ' / ' + Math.max(B.ma.length, B.mb.length, 1) + '</div>' : '') +
      '<button type="button" class="bt-timer" id="bt-timer" title="Paspauskite ir įrašykite laiką (pvz. 1:30)"></button><div class="bt-tbtns"><button type="button" class="btn btn-primary" id="bt-start"></button><button type="button" class="btn" id="bt-reset" title="Iš naujo">↺ ' + fmtTime(B.timer.total) + '</button></div>' +
      // B3: ±10 s, laiko šablonai ir gongas - viename skyriuje; telefone suskleistas (pora ir balai lieka ekrane)
      '<details class="bt-more"' + (boutMoreOpen() ? ' open' : '') + '><summary>⏱ ' + (B.timer.preset ? esc(B.timer.preset) + ' · ' : '') + 'laikas ir gongas</summary>' +
      '<div class="bt-tbtns"><button type="button" class="btn" data-dt="-10000">−10 s</button><button type="button" class="btn" data-dt="10000">+10 s</button></div>' +
      (mode === 'kumite' || mode === 'team' ? '<div class="bt-presets" role="group" aria-label="Laiko šablonai">' + timePresets().map(function (x) {
        var on = B.timer.preset === x[0];
        return '<button type="button" class="btn btn-sm' + (on ? ' on' : '') + '" data-preset="' + x[1] + '" data-preset-name="' + esc(x[0]) + '"' + (x[2] ? ' data-up="1" title="Paprastas laikmatis nuo 0"' : '') + '>' + esc(x[0]) + ' <b>' + fmtTime(x[1] * 1000) + (x[2] ? ' ↑' : '') + '</b></button>';
      }).join('') + '</div>' : '') +
      '<label class="bt-gong small">🔔 <select id="bt-gong" aria-label="Gongo garsas">' + Object.keys(GONGS).map(function (k) { return '<option value="' + k + '"' + (k === gongKind() ? ' selected' : '') + '>' + GONGS[k] + '</option>'; }).join('') + '</select>' +
      (isJiyu() ? ' <span class="muted">likus 30 s - 1 gongas, pabaigoje - 2</span>' : '') + '</label></details>' +
      (mode === 'team' && B.log.length ? '<ol class="bt-log">' + B.log.map(function (l, j) { return '<li>' + (j + 1) + ' kova: ' + esc(l.an) + ' <b>' + l.a + ':' + l.b + '</b> ' + esc(l.bn) + '</li>'; }).join('') + '</ol>' : '') +
      (TOUCH ? '' : '<p class="muted small">Tarpas - laikrodis</p>') + '</div>';
    body = '<div class="bt-sides">' + sideHtml('a') + mid + sideHtml('b') + '</div>';
  }
  var tie = mode === 'score2' ? (function () { var a = judgeTotal(c, B.parts.a.k), b = judgeTotal(c, B.parts.a.p), x = judgeTotal(c, B.parts.b.k), y = judgeTotal(c, B.parts.b.p); return a !== null && b !== null && x !== null && y !== null && a + b === x + y; })() : B.sa === B.sb;
  var lastTeamBout = mode !== 'team' || B.i >= Math.max(B.ma.length, B.mb.length, 1) - 1;
  var foot = '<div class="bt-foot">' + (mode === 'team' && !lastTeamBout ? '<button type="button" class="btn btn-primary" id="bt-next">Kita kova →</button>' : '') +
    (lastTeamBout ? '<button type="button" class="btn btn-primary" id="bt-finish">✓ Baigti kovą</button>' : '') +
    '<span class="spacer"></span><span class="muted small">' + (tie ? 'Lygiosios - nugalėtoją pasirinkite:' : 'Arba sprendimu (hantei):') + '</span><button type="button" class="btn" data-win="a">Laimėjo AKA</button><button type="button" class="btn" data-win="b">Laimėjo SHIRO</button></div>';
  // Telefone: perpiešus (pvz. +1 taškas) lieka toje pačioje slinkimo vietoje
  var oldCard = boutEl.querySelector('.bout-card'), keepY = oldCard ? oldCard.scrollTop : 0, keepX = oldCard ? oldCard.scrollLeft : 0, keepWin = window.scrollY;
  var oldTs = oldCard && oldCard.querySelector('.table-scroll'), keepTs = oldTs ? oldTs.scrollLeft : 0;
  boutEl.innerHTML = '<div class="bout-card">' + head + boutNextHtml(c) + '<div class="bout-body">' + body + '</div>' + foot + '</div>';
  renderTimer();
  var nc = boutEl.querySelector('.bout-card');
  if (keepY) nc.scrollTop = keepY;
  if (keepX) nc.scrollLeft = keepX;
  if (keepTs && nc.querySelector('.table-scroll')) nc.querySelector('.table-scroll').scrollLeft = keepTs;
  if (window.scrollY !== keepWin) window.scrollTo(window.scrollX, keepWin);
}
function finishBout(forced) {
  var c = cat(B.cat), winner, txt, mode = B.mode;
  if (mode === 'team' && (B.log.length <= B.i)) B.log.push({ a: B.sa - B.log.reduce(function (s, l) { return s + l.a; }, 0), b: B.sb - B.log.reduce(function (s, l) { return s + l.b; }, 0), an: (S.people[B.ma[B.i]] || {}).name || '', bn: (S.people[B.mb[B.i]] || {}).name || '' });
  if (mode === 'score2') {
    var ta = (judgeTotal(c, B.parts.a.k) || 0) + (judgeTotal(c, B.parts.a.p) || 0), tb = (judgeTotal(c, B.parts.b.k) || 0) + (judgeTotal(c, B.parts.b.p) || 0);
    if ([B.parts.a.k, B.parts.a.p, B.parts.b.k, B.parts.b.p].some(function (x) { return judgeTotal(c, x) === null; }) && !forced) { alert('Įrašykite visų teisėjų balus abiem komandoms (Kata ir Pritaikymas).'); return; }
    winner = forced || (ta > tb ? 'a' : tb > ta ? 'b' : null); txt = ta.toFixed(1) + ':' + tb.toFixed(1);
  } else {
    winner = forced || (B.sa > B.sb ? 'a' : B.sb > B.sa ? 'b' : null); txt = B.sa + ':' + B.sb;
  }
  if (!winner) { alert('Lygiosios - pasirinkite nugalėtoją mygtuku „Laimėjo AKA“ arba „Laimėjo SHIRO“.'); return; }
  snapshot();
  keepBout();
  var wasDone = catFinished(c);
  // v2.13: kova prasidėjo ~5 s prieš pirmą „Hajime“ (laikas nepaleistas - kai atidarytas skydelis); taisant baigtą kovą laikas lieka
  if (!c.draw.results[B.mid] || !(c.draw.times || {})[B.mid]) stamp(c, B.mid, B.go ? B.go - 5000 : B.t0);
  c.draw.results[B.mid] = winner === 'a' ? B.a : B.b;
  c.draw.scores[B.mid] = txt;
  B.winner = winner;
  if (B.timer.running) toggleTimer();
  pushLive();
  prune(c); autoLock(c, wasDone); save();
  var area = areaOf(c);
  setTimeout(function () { pushLive(area); }, 8000);   // ekrane nugalėtojas rodomas 8 s
  B = null; liveMatch = null; boutEl.hidden = true; document.body.classList.remove('bout-open-body'); render();
  goNext();   // #185: po „Laimėjo“ - į kitą kovą
}
boutEl.addEventListener('click', function (e) {
  if (!B) return;
  var t = e.target.closest('button'); if (!t) return;
  var c = cat(B.cat);
  if (t.id === 'bt-close') { keepBout(); closeBout(); return; }
  if (t.id === 'bt-screen') { window.open(CFG.screenUrl + '&tatami=' + areaOf(c).id, 'taika-ekranas-' + areaOf(c).id); return; }
  if (t.id === 'bt-start') { toggleTimer(); return; }
  if (t.id === 'bt-timer') { editTimer(); return; }
  if (t.id === 'bt-reset') { if (B.timer.running) toggleTimer(); setTime(B.timer.total); return; }
  if (t.dataset.dt) { setTime(timeLeft() + +t.dataset.dt); return; }
  if (t.dataset.preset !== undefined) {
    if (B.timer.running) toggleTimer();
    B.timer.total = +t.dataset.preset * 1000; B.timer.preset = t.dataset.presetName; B.timer.up = !!t.dataset.up; setTime(B.timer.total); keepBout(); renderBout(); return;
  }
  if (t.dataset.pt) {
    var q = t.dataset.pt.split('|'), side = q[0], n = +q[1];
    if (side === 'a') B.sa = Math.max(0, B.sa + n); else B.sb = Math.max(0, B.sb + n);
    keepBout();
    if (B.mode === 'sumo' && (B.sa >= cfg(c).sumoTo || B.sb >= cfg(c).sumoTo)) { finishBout(); return; }
    renderBout(); pushLive(); return;
  }
  if (t.id === 'bt-next') {
    // Komandinis kumite: kova baigta, nugalėtojas neskelbiamas - kovoja kiti; balai sumuojasi
    var prevA = B.log.reduce(function (s, l) { return s + l.a; }, 0), prevB = B.log.reduce(function (s, l) { return s + l.b; }, 0);
    B.log.push({ a: B.sa - prevA, b: B.sb - prevB, an: (S.people[B.ma[B.i]] || {}).name || '', bn: (S.people[B.mb[B.i]] || {}).name || '' });
    B.i++; if (B.timer.running) toggleTimer(); B.timer.remaining = B.timer.total;
    keepBout(); renderBout(); pushLive(); return;
  }
  if (t.id === 'bt-finish') { finishBout(); return; }
  if (t.dataset.win) { finishBout(t.dataset.win); return; }
});
// Laiko įrašymas: „1:30“, „2“ (minutės) arba „90“ (sekundės)
function editTimer() {
  if (B.timer.running) toggleTimer();
  var el = document.getElementById('bt-timer'), inp = document.createElement('input'), done = false;
  inp.className = 'bt-timer bt-timer-in'; inp.value = fmtTime(timeLeft(), B.timer.up); inp.setAttribute('aria-label', 'Laikas (min:sek)');
  el.replaceWith(inp); inp.focus(); inp.select();
  var commit = function (save) {
    if (done) return; done = true;
    var m = inp.value.trim().match(/^(\d{1,3})(?:[:.,](\d{1,2}))?$/);
    if (save && m) setTime(m[2] !== undefined ? (+m[1] * 60 + +m[2]) * 1000 : (+m[1] <= 10 ? +m[1] * 60000 : +m[1] * 1000));
    setTimeout(renderBout, 0);
  };
  inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); commit(true); } else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); commit(false); } });
  inp.addEventListener('blur', function () { commit(true); });
}
boutEl.addEventListener('change', function (e) {
  if (!B) return;
  var t = e.target;
  if (t.dataset.sc) {
    var v = Math.max(0, Math.min(99, parseInt(t.value, 10) || 0));
    if (t.dataset.sc === 'a') B.sa = v; else B.sb = v;
    keepBout(); pushLive();
    if (B.mode === 'sumo' && (B.sa >= cfg(cat(B.cat)).sumoTo || B.sb >= cfg(cat(B.cat)).sumoTo)) { finishBout(); return; }
    setTimeout(function () { if (B) renderBout(); }, 0);
    return;
  }
  if (t.dataset.tm) {
    // Kuris komandos narys kovoja šią kovą - sukeičiama su tuo, kuris buvo šioje vietoje
    var list = t.dataset.tm === 'a' ? B.ma : B.mb, j = +t.value, x = list[B.i]; list[B.i] = list[j]; list[j] = x;
    keepBout(); renderBout(); pushLive(); return;
  }
  if (t.dataset.part) {
    var q = t.dataset.part.split('|'), arr = B.parts[q[0]][q[1]], v = t.value.replace(',', '.');
    arr[+q[2]] = v === '' ? null : Math.max(0, Math.min(10, parseFloat(v)));
    keepBout(); pushLive();
    // Perpiešiama po „blur“, kad nedingtų laukelis, iš kurio išeinama
    setTimeout(function () {
      if (!B) return;
      renderBout();
      var next = boutEl.querySelector('[data-part="' + q[0] + '|' + q[1] + '|' + (+q[2] + 1) + '"]') || (q[1] === 'k' ? boutEl.querySelector('[data-part="' + q[0] + '|p|0"]') : null);
      if (next) next.focus();
    }, 0);
  }
});
document.addEventListener('keydown', function (e) {
  if (!B || boutEl.hidden) return;
  if (e.key === 'Escape') { keepBout(); closeBout(); return; }
  if ((e.key === ' ' || e.code === 'Space') && !/INPUT|SELECT|TEXTAREA/.test((e.target.tagName || '')) && B.mode !== 'score2') {
    e.preventDefault();
    if (document.activeElement && document.activeElement.tagName === 'BUTTON') document.activeElement.blur();   // kad tarpas nepaspaustų ir mygtuko
    toggleTimer();
  }
});
document.addEventListener('keyup', function (e) {
  if (B && !boutEl.hidden && (e.key === ' ' || e.code === 'Space') && !/INPUT|SELECT|TEXTAREA/.test((e.target.tagName || ''))) e.preventDefault();
});

// Transliacija: ekrano langui tame pačiame kompiuteryje (iškart) ir per serverį (kiti įrenginiai, žiūrovų nuoroda)
var chan = null; try { chan = new BroadcastChannel('taika-' + (CFG.id || 0)); } catch (e) {}
var liveTimer = null, livePending = {};
// Balų sistema (kata balais) ekrane (#103): dabartinis dalyvis, kata, teisėjų balai (atmesti - perbraukti), rezultatas ir geriausių lentelė
var SL = null, slKey = '';
function scoreLive(c, id, which) {
  var w = which === 'final' ? 'final' : 'scores', f = cfg(c), src = (w === 'final' ? c.draw.final : c.draw.scores) || {}, arr = src[id] || [], t = scoreTotal(c, arr);
  var ids = w === 'final' ? scoreFinalists(c) : (c.draw.order || []).filter(function (x) { return person(c, x); });
  var board = scoreRank(c, w, ids).filter(function (x) { return x.sum !== null; }).slice(0, 6).map(function (x, i) { var p = person(c, x.id) || {}; return { n: pubName(x.id), c: p.club || '', s: fmtScore(c, x.sum), pl: i + 1 }; });
  var p = person(c, id) || {}, a = areaOf(c), sc = [];
  for (var j = 0; j < f.judges; j++) sc.push(arr[j] !== undefined && arr[j] !== null ? arr[j] : null);
  return { area: a.id, areaName: a.name, cat: c.name, catId: c.id, disc: c.discipline, round: w === 'final' ? 'Finalas' : (f.rounds === 2 ? 'Pusfinalis' : 'Balai'), mode: 'score',
    aka: { name: pubName(id), club: p.club || '' }, shiro: { name: '', club: '' }, sa: fmtScore(c, t), sb: '', at: Date.now(),
    perf: { kata: kataOf(c, w, id), scores: sc, dropped: droppedIdx(c, arr), total: t === null ? null : fmtScore(c, t), label: f.avg ? 'Vidurkis' : 'Suma' }, board: board };
}
export function pushScoreLive(c, id, which) {
  if (B || !c || !c.draw || !id || CFG.readOnly) return;
  var st = scoreLive(c, id, which), k = JSON.stringify([st.catId, st.aka.name, st.perf, st.board]);
  if (k === slKey) return;
  slKey = k; SL = st; pushLive(areaOf(c));
}
function liveState(area) {
  if (!B && SL && SL.area === area.id) { SL.at = Date.now(); return SL; }
  if (!B) return { area: area.id, areaName: area.name, idle: true, at: Date.now() };
  var c = cat(B.cat), st = { area: areaOf(c).id, areaName: areaOf(c).name, cat: c.name, catId: c.id, disc: c.discipline, round: (B.no ? B.no + ' · ' : '') + B.title, mode: B.mode,
    aka: { name: pubName(B.a), club: (person(c, B.a) || {}).club || '' }, shiro: { name: pubName(B.b), club: (person(c, B.b) || {}).club || '' },
    sa: B.sa, sb: B.sb, winner: B.winner, at: Date.now(),
    timer: { total: B.timer.total, remaining: timeLeft(), running: B.timer.running, up: B.timer.up || undefined } };
  if (B.mode === 'team') { st.bout = (B.i + 1) + ' / ' + Math.max(B.ma.length, B.mb.length, 1); st.akaNow = pubName(B.ma[B.i]); st.shiroNow = pubName(B.mb[B.i]); }
  if (B.mode === 'sumo') st.to = cfg(c).sumoTo;
  if (B.mode === 'score2') {
    st.parts = {};
    ['a', 'b'].forEach(function (sd) { st.parts[sd] = { k: B.parts[sd].k, p: B.parts[sd].p, dk: droppedIdx(c, B.parts[sd].k), dp: droppedIdx(c, B.parts[sd].p), tk: judgeTotal(c, B.parts[sd].k), tp: judgeTotal(c, B.parts[sd].p) }; });
  }
  return st;
}
function pushLive(area) {
  var st = liveState(area || (B ? areaOf(cat(B.cat)) : S.areas[0]));
  if (chan) try { chan.postMessage(st); } catch (e) {}
  if (!CFG.liveApi || CFG.readOnly) return;
  livePending[st.area] = st;
  clearTimeout(liveTimer);
  liveTimer = setTimeout(function () {
    var send = livePending; livePending = {};
    Object.keys(send).forEach(function (k) {
      fetch(CFG.liveApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf },
        body: JSON.stringify({ area: +k, data: send[k] }) }).catch(function () {});
    });
  }, 250);
}
