// Būsena S ir išsaugojimas serveryje: automatiškai, kelios planšetės (sujungimas), neišsaugoti pakeitimai įrenginyje, apdovanojimų būsenos
import { CFG, HOME, toast } from './util.js';
import { render } from './chrome.js';
import { dragId } from './schedule.js';
import { resultsPayload, typingNow } from './results.js';

export var S;
// PHP tuščią žemėlapį {} grąžina kaip sąrašą []. Į sąrašą įrašytas rezultatas (results['r0m0'] = ...) ekrane matomas,
// bet JSON.stringify jo neperduoda - po perkrovimo dingdavo. Todėl visi būsenos žemėlapiai užkraunant verčiami objektais.
function asMap(x) {
  if (x && typeof x === 'object' && !Array.isArray(x)) return x;
  var o = {};
  if (Array.isArray(x)) x.forEach(function (v, i) { if (v !== null && v !== undefined) o[i] = v; });
  return o;
}
function fixMaps(c) {
  if (Array.isArray(c.crit)) c.crit = asMap(c.crit);
  if (Array.isArray(c.cfg)) c.cfg = asMap(c.cfg);
  var d = c.draw;
  if (!d || typeof d !== 'object') return;
  d.results = asMap(d.results); d.scores = asMap(d.scores);
  ['final', 'bouts', 'kata', 'times'].forEach(function (k) { if (Array.isArray(d[k])) d[k] = asMap(d[k]); });   // times - kovų laikai (🎬, v2.13)
  if (d.kata) ['scores', 'final'].forEach(function (k) { if (Array.isArray(d.kata[k])) d.kata[k] = asMap(d.kata[k]); });
}
export function load() {
  S = CFG.state && typeof CFG.state === 'object' ? CFG.state : {};
  S.categories = Array.isArray(S.categories) ? S.categories : [];
  S.categories.forEach(fixMaps);
  S.people = S.people && !Array.isArray(S.people) ? S.people : {};
  S.teams = S.teams && !Array.isArray(S.teams) ? S.teams : {};
  S.categories.forEach(function (c) { c.competitors.forEach(function (p) { if (!p.team && !S.people[p.id]) S.people[p.id] = { id: p.id, name: p.name, club: p.club }; }); });
  S.clubs = Array.isArray(S.clubs) && S.clubs.length ? S.clubs : [HOME];
  S.areas = Array.isArray(S.areas) && S.areas.length ? S.areas : [{ id: 1, name: '1 TATAMIS', start: '09:30' }];
  S.clubInfo = S.clubInfo && !Array.isArray(S.clubInfo) ? S.clubInfo : {};
  S.videos = Array.isArray(S.videos) ? S.videos : [];   // v2.13: tatamių vaizdo įrašai (YouTube, telefono failai) ir kada jie prasidėjo - 🎬 prie baigtų kovų   // klubų kontaktai: { klubas: { person, phone, email, note } }
  S.minutes = S.minutes || { kata: 2, kumite: 3, fukugo: 4, kihon: 1.5 };
  if (!S.minutes.enbu) S.minutes.enbu = 3;
  if (!S.minutes.sumo) S.minutes.sumo = 2;
  S.history = [];
  S.tournament = CFG.title || '';
  if (!S.selected && S.categories[0]) S.selected = S.categories[0].id;
}

// ---------- Išsaugojimas serveryje (po 0,7 s nuo paskutinio pakeitimo) ----------
export var version = CFG.version || 0, saveTimer = null, saving = false, dirty = false, conflict = false;
var statusEl = document.getElementById('save-state');
export function setStatus(text, cls) { if (statusEl) { statusEl.textContent = text; statusEl.className = 'save-state ' + (cls || ''); } }
function payload() {
  var st = {};
  Object.keys(S).forEach(function (k) { if (k !== 'history' && k !== 'tournament') st[k] = S[k]; });
  return JSON.stringify({ state: st, results: resultsPayload(), version: version });
}
// Keli langai (pvz. atkabinta rungtis): išsaugojus - nauja būsena kitiems langams; jie ją perima, jei patys neturi neišsaugotų pakeitimų
export var stateCh = null;
try { stateCh = new BroadcastChannel('taika-state-' + CFG.id); } catch (e) {}
if (stateCh) stateCh.onmessage = function (ev) {
  var d = ev.data || {}; if (!d.state || d.v <= version || dirty || saving) return;
  var sel = S.selected;
  CFG.state = d.state; load(); S.selected = sel; version = d.v; markSaved(stateObj());
  setStatus('Išsaugota ✓', 'ok'); render();
};
export function flush(keepalive) {
  if (!dirty || saving || conflict || !CFG.api) return;
  clearTimeout(saveTimer);
  saving = true; dirty = false;
  setStatus('Saugoma…', 'busy');
  var body = payload();
  fetch(CFG.api, { method: 'POST', credentials: 'same-origin', keepalive: !!keepalive,
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf, Accept: 'application/json' }, body: body })
    .then(function (r) { return r.json().then(function (d) { return { status: r.status, d: d }; }); })
    .then(function (x) {
      saving = false;
      if (x.status === 409) { resolveConflict(); return; }
      if (!x.d.ok) { dirty = true; setStatus('Neišsaugota: ' + (x.d.error || 'klaida'), 'err'); return; }
      version = x.d.version;
      markSaved(JSON.parse(body).state);
      setStatus('Išsaugota ✓', 'ok');
      // Baigtos rungtys automatiškai pažymimos „apdovanojama“ (#122) - būsena atnaujinama iš karto
      if (x.d.awards && !Array.isArray(x.d.awards) && JSON.stringify(x.d.awards) !== JSON.stringify(AW)) {
        AW = x.d.awards;
        if (!typingNow() && !dragId) render(); else awPending = true;
      }
      if (stateCh) { try { stateCh.postMessage({ v: version, state: JSON.parse(body).state }); } catch (e) {} }
      if (dirty) flush(); else dropPending();
    })
    .catch(function () { saving = false; dirty = true; setStatus('Nėra ryšio - bandysime dar kartą', 'err'); saveTimer = setTimeout(flush, 5000); });
}
// ---------- Kelios planšetės (D4): kas išsaugota paskutinį kartą - kad konflikto atveju būtų galima sujungti, o ne prašyti perkrauti ----------
var savedSnap = null;
export function stateObj() { var st = {}; Object.keys(S).forEach(function (k) { if (k !== 'history' && k !== 'tournament') st[k] = S[k]; }); return JSON.parse(JSON.stringify(st)); }
export function markSaved(st) {
  var o = { cats: {}, people: {}, other: {} };
  (st.categories || []).forEach(function (c) { o.cats[c.id] = JSON.stringify(c); });
  Object.keys(st.people || {}).forEach(function (id) { o.people[id] = JSON.stringify(st.people[id]); });
  Object.keys(st).forEach(function (k) { if (k !== 'categories' && k !== 'people' && k !== 'selected') o.other[k] = JSON.stringify(st[k]); });
  savedSnap = o;
}
// Serverio būsena + šios planšetės pakeitimai (tik tos grupės, dalyviai ir nustatymai, kuriuos čia keitėme po paskutinio išsaugojimo)
function rebase(server) {
  var local = stateObj(), snap = savedSnap || { cats: {}, people: {}, other: {} }, out = [], onServer = {}, inLocal = {};
  local.categories.forEach(function (c) { inLocal[c.id] = c; });
  (server.categories || []).forEach(function (c) {
    onServer[c.id] = true;
    var lc = inLocal[c.id];
    if (!lc) { if (snap.cats[c.id] === undefined) out.push(c); return; }   // čia ištrinta - neliko; kitur sukurta - lieka
    out.push(JSON.stringify(lc) !== snap.cats[c.id] ? lc : c);
  });
  local.categories.forEach(function (c) { if (!onServer[c.id] && snap.cats[c.id] === undefined) out.push(c); });   // čia sukurtos naujos
  server.categories = out;
  server.people = server.people && !Array.isArray(server.people) ? server.people : {};
  Object.keys(local.people || {}).forEach(function (id) { if (JSON.stringify(local.people[id]) !== snap.people[id]) server.people[id] = local.people[id]; });
  Object.keys(snap.people).forEach(function (id) { if (!(local.people || {})[id]) delete server.people[id]; });
  Object.keys(local).forEach(function (k) {
    if (k === 'categories' || k === 'people') return;
    if (k === 'selected' || snap.other[k] === undefined || JSON.stringify(local[k]) !== snap.other[k]) server[k] = local[k];
  });
  return server;
}
function applyServer(st, ver) {
  var sel = S.selected;
  CFG.state = st; load(); if (cat(sel)) S.selected = sel; version = ver;
}
// Konfliktas: kita planšetė ką tik išsaugojo - sujungiama automatiškai ir išsaugoma iš naujo (be perkrovimo)
function resolveConflict() {
  setStatus('Sujungiama su kitų planšečių pakeitimais…', 'busy');
  fetch(CFG.api + '&state=1&v=-1', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok || !d.state) throw new Error('state');
      var serverSnapState = JSON.parse(JSON.stringify(d.state));
      var merged = rebase(d.state);
      applyServer(merged, d.version);
      markSaved(serverSnapState);   // tai, kas jau serveryje; mūsų pakeitimai - dar neišsaugoti
      dirty = true; conflict = false;
      if (!typingNow() && !dragId) render(); else awPending = true;
      flush();
    })
    .catch(function () { conflict = true; setStatus('Kitas žmogus pakeitė varžybas - perkraukite puslapį', 'err'); showConflict(); });
}
// Laisva planšetė kas 8 s pasiima kitų pakeitimus (kai niekas nerašoma ir neatidarytas kovos langas)
function pullServer() {
  if (!CFG.api || CFG.readOnly || document.hidden || dirty || saving || conflict || typingNow() || dragId) return;
  fetch(CFG.api + '&state=1&v=' + version, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok || !d.state || d.version <= version || dirty || saving || typingNow() || dragId) return;
      applyServer(d.state, d.version); markSaved(stateObj()); render();
    }).catch(function () {});
}
setInterval(pullServer, 8000);
// A1: neišsaugotų pakeitimų kopija šiame įrenginyje - jei skirtukas uždaromas, kol saugoma, po perkrovimo sujungiama ir išsaugoma
var PEND = 'taika-pend-' + CFG.id;
function keepPending() {
  if (CFG.readOnly || !CFG.api) return;
  try { localStorage.setItem(PEND, JSON.stringify({ t: Date.now(), v: version, snap: savedSnap, state: stateObj() })); } catch (e) {}
}
function dropPending() { try { localStorage.removeItem(PEND); } catch (e) {} }
export function save() {
  if (CFG.readOnly) return;
  dirty = true;
  keepPending();
  setStatus('Keičiama…', 'busy');
  clearTimeout(saveTimer);
  saveTimer = setTimeout(flush, 700);
}
function showConflict() {
  var b = document.getElementById('conflict');
  if (b) b.hidden = false;
}
// Atkūrus kopiją iš istorijos: nauja versija, neišsaugotų pakeitimų nėra
export function setRestored(v) { version = v; dirty = false; conflict = false; }
// A1: po perkrovimo - neišsaugoti šio įrenginio pakeitimai sujungiami su serverio būsena ir išsaugomi
export function restorePending() {
  var p = null;
  try { p = JSON.parse(localStorage.getItem(PEND) || 'null'); } catch (e) {}
  if (!p || !p.state || !p.snap || CFG.readOnly || !CFG.api) return;
  if (Date.now() - (p.t || 0) > 2 * 86400000) { dropPending(); return; }   // senesnė nei 2 d. - nebeaktuali
  // Mūsų paskutiniai pakeitimai (palyginus su tuo, kas tada buvo išsaugota) uždedami ant dabartinės serverio būsenos ir išsaugomi
  var server = stateObj(), before = JSON.stringify(server), serverCopy = JSON.parse(before);
  CFG.state = p.state; load(); savedSnap = p.snap;
  var merged = rebase(server);   // rebase keičia ir grąžina tą patį objektą - todėl palyginimui kopija
  CFG.state = merged; load(); markSaved(serverCopy);
  if (JSON.stringify(stateObj()) === before) { dropPending(); return; }   // viskas jau buvo išsaugota
  dirty = true; setStatus('Atkurti neišsaugoti pakeitimai - saugoma…', 'busy'); setTimeout(flush, 300);
}
window.addEventListener('pagehide', function () { flush(true); });
document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') flush(true); });
window.addEventListener('beforeunload', function (e) { if (dirty || saving) { flush(true); e.preventDefault(); e.returnValue = ''; } });
export function cat(id) { return S.categories.find(function (c) { return c.id === id; }); }
export function snapshot() { S.history.push(JSON.stringify({ categories: S.categories, people: S.people, clubs: S.clubs, teams: S.teams })); if (S.history.length > 40) S.history.shift(); }
export function undo() {
  if (!S.history.length) return;
  var h = JSON.parse(S.history.pop());
  if (Array.isArray(h)) { S.categories = h; } else { S.categories = h.categories; S.people = h.people; S.clubs = h.clubs; S.teams = h.teams || S.teams; }
}
// Dalyvio pakeitimas (vardas, klubas) - visose kategorijose iškart
export function updatePerson(id, fields) {
  Object.assign(S.people[id], fields);
  S.categories.forEach(function (c) { c.competitors.forEach(function (p) { if (p.id === id) Object.assign(p, fields); }); });
}
export function catsOf(id) { return S.categories.filter(function (c) { return c.competitors.some(function (p) { return p.id === id; }); }); }
export function addToCat(id, catId) {
  var c = cat(catId), p = S.people[id];
  if (c && p && !c.competitors.some(function (x) { return x.id === id; })) c.competitors.push({ id: p.id, name: p.name, club: p.club });
}
export function removeFromCat(id, catId) { var c = cat(catId); if (c) c.competitors = c.competitors.filter(function (x) { return x.id !== id; }); }
// Apdovanojimų būsenos { grupės id: { st: 'sent' | 'done' } } - iš serverio (išsaugojus ir kas 10 s); awPending - perpiešti, kai baigs rašyti
export var AW = CFG.awards && typeof CFG.awards === 'object' && !Array.isArray(CFG.awards) ? CFG.awards : {}, awPending = false;
export function setAward(ids, st) {
  ids.forEach(function (id) { if (st) AW[id] = { st: st }; else delete AW[id]; });
  render();
  if (!CFG.awardsApi) { toast('Apdovanojimai veiks, kai bus atnaujinta duomenų bazė (Šiandien → „Atnaujinkite duomenų bazę“).'); return; }
  fetch(CFG.awardsApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf }, body: JSON.stringify({ cats: ids, status: st || '' }) })
    .then(function (r) { return r.json(); }).then(function (d) { if (d.awards) { AW = d.awards; render(); } })
    .catch(function () { toast('Nepavyko išsiųsti apdovanojimams - nėra ryšio. Bandykite dar kartą.'); });
}
// Apdovanojimų būsenos iš serverio (planšetėje paspaudus „Užbaigti“) - kas 10 s
function loadAwards() {
  if (!CFG.awardsApi || document.hidden) return;
  fetch(CFG.awardsApi, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
    if (!d.awards) return;
    if (JSON.stringify(d.awards) !== JSON.stringify(AW)) { AW = d.awards; awPending = true; }
    if (awPending && !typingNow() && !dragId && (!document.getElementById('v-live').hidden || !document.getElementById('v-rezultatai').hidden)) { awPending = false; render(); }
  }).catch(function () {});
}
setInterval(loadAwards, 10000);
