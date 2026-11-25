// Puslapio rėmas: skirtukai ir „Atgal“, perpiešimas (render), tema ir meniu, istorija serveryje, nuorodos ir ekranai
import { CFG, esc, KEY, POPUP, toast } from './util.js';
import { cat, flush, load, markSaved, S, save, setRestored, setStatus, stateCh, stateObj, version } from './store.js';
import { renderCats } from './views/categories.js';
import { renderClubs } from './views/clubs.js';
import { renderEntries, renderPeople } from './views/people.js';
import { renderTeams } from './views/teams.js';
import { resetSwap } from './views/draw.js';
import { B, closeBout, keepBout, setBoutHist } from './bout.js';
import { deskArea, deskEl, deskScrollTo, renderSekrDesk, setDeskAll } from './views/desk.js';
import { isDetached, renderLive, renderPins } from './views/live.js';
import { renderDraws } from './views/draws.js';
import { renderResults } from './views/results.js';

// ---------- Skirtukai ----------
// Organizacinė pusė (klubai, komandos, dalyviai, kategorijos) ir varžybų diena (live tvarkaraštis, rezultatai).
// Live tvarkaraštis: tatamių lenta; atidarius rungtį - jos valdymas (buvusios „Lentelės“), „Toliau →“ - kita rungtis.
var tabs = ['klubai', 'komandos', 'dalyviai', 'kategorijos', 'tinklai', 'live', 'rezultatai'];
// Viršutinis meniu: pagrindinė vieta - tvarkaraštis; paruošimo skirtukai (klubai … kategorijos) - „Administracijoje“
var TOP = ['live', 'rezultatai', 'admin'], SUB = ['klubai', 'komandos', 'dalyviai', 'kategorijos', 'tinklai'];   // Tinklai - Administracijoje (#81)
var lastSub = 'dalyviai';
try { lastSub = sessionStorage.getItem(KEY + '-sub') || 'dalyviai'; } catch (e) {}
// Išsaugota meniu tvarka be naujų skirtukų - jie įterpiami po numatyto kaimyno
function menuOrder(o) {
  if (!o || !o.length) return TOP;
  o = o.filter(function (t, i) { return TOP.indexOf(t) >= 0 && o.indexOf(t) === i; });
  TOP.forEach(function (t, i) { if (o.indexOf(t) < 0) { var p = i ? o.indexOf(TOP[i - 1]) : -1; o.splice(p < 0 && i ? o.length : p + 1, 0, t); } });
  return o.join() === TOP.join() ? TOP : o;
}
export var runOpen = false;
try { runOpen = sessionStorage.getItem(KEY + '-run') === '1'; } catch (e) {}
export function setRun(open) { runOpen = !!open; try { sessionStorage.setItem(KEY + '-run', runOpen ? '1' : ''); } catch (e) {} }
export function openRun(id) {
  if (id && isDetached(id)) { toast('Ši rungtis atidaryta <b>atskirame lange</b> - ten ir valdykite. Uždarius tą langą, ji grįš čia.'); return; }
  // v2.01: darbastalyje - kito tatamio grupė atidaroma bendrame tvarkaraštyje („🏟 Visi tatamiai“), savas tatamis išlieka
  var oc = id ? cat(id) : null, da = deskArea();
  if (da && oc && String(oc.tatami) !== String(da.id)) setDeskAll(true);
  if (id && S.selected !== id) { S.selected = id; resetSwap(); save(); }
  var wasRun = runOpen && curTab() === 'live';
  setRun(true); showTab('live', true);
  if (!POPUP && !wasRun) history.pushState({ vz: 1, tab: 'live', run: S.selected }, '');
  if (deskArea()) { deskScrollTo(S.selected); return; }
  var r = document.getElementById('run'); if (r && r.getBoundingClientRect().top < 0) r.scrollIntoView({ block: 'start' });
}
export function curTab() { return tabs.find(function (t) { return !document.getElementById('v-' + t).hidden; }); }
export function showTab(t, noHist) {
  if (t === 'lenteles') { setRun(true); t = 'live'; } else if (t === 'tvarkarastis') t = 'live';   // seni pavadinimai (nuorodos, #)
  if (t === 'admin') t = SUB.indexOf(lastSub) >= 0 ? lastSub : 'dalyviai';
  var prev = curTab(), sub = SUB.indexOf(t) >= 0;
  if (sub) { lastSub = t; try { sessionStorage.setItem(KEY + '-sub', t); } catch (e) {} }
  tabs.forEach(function (x) {
    document.getElementById('tab-' + x).setAttribute('aria-selected', x === t ? 'true' : 'false');
    document.getElementById('v-' + x).hidden = x !== t;
  });
  var ta = document.getElementById('tab-admin'); if (ta) ta.setAttribute('aria-selected', sub ? 'true' : 'false');
  var sb = document.getElementById('tabs-sub'); if (sb) sb.hidden = !sub;
  try { sessionStorage.setItem(KEY + '-tab', t); } catch (e) {}
  // Naršyklės „Atgal“ grįžta į ankstesnį skirtuką ar tvarkaraštį, o ne į visų varžybų sąrašą
  if (!noHist && prev && prev !== t && !POPUP) history.pushState({ vz: 1, tab: t, run: t === 'live' && runOpen ? S.selected : null }, '');
  render();
}
tabs.forEach(function (t) { document.getElementById('tab-' + t).addEventListener('click', function () { if (t === 'live' && runOpen && curTab() === 'live') { setRun(false); render(); return; } showTab(t); }); });
var tabAdmin = document.getElementById('tab-admin'); if (tabAdmin) tabAdmin.addEventListener('click', function () { showTab('admin'); });
if (!POPUP) {
  history.replaceState({ vz: 1, tab: null, run: null }, '');
  window.addEventListener('popstate', function (e) {
    var st = e.state || {};
    // #62: naršyklės „Atgal“ su atidarytu kovos skydeliu - pirmiausia uždaro skydelį (balai išsaugomi), grupė lieka atidaryta
    if (B) { setBoutHist(false); keepBout(); closeBout(); return; }
    if (!st.vz) return;
    if (st.run && cat(st.run)) { S.selected = st.run; setRun(true); showTab('live', true); return; }
    setRun(false);
    showTab(st.tab || 'live', true);
  });
}
// Pirmas skirtukas atidarius puslapį: iš adreso (#), šio skirtuko atminties arba tvarkaraštis; atkabintos rungties lange - ta rungtis
export function openStartTab() {
  var startTab = 'live';
  try { startTab = location.hash.replace('#', '') || sessionStorage.getItem(KEY + '-tab') || 'live'; } catch (e) {}
  if (startTab === 'lenteles') { setRun(true); startTab = 'live'; } else if (startTab === 'tvarkarastis') startTab = 'live';
  if (POPUP && cat(POPUP)) { S.selected = POPUP; runOpen = true; startTab = 'live'; document.title = cat(POPUP).name + ' · ' + document.title; }
  showTab(tabs.indexOf(startTab) >= 0 || startTab === 'admin' ? startTab : 'live', true);
}


var renderPending = false;
export function renderSoon() { if (renderPending) return; renderPending = true; setTimeout(function () { renderPending = false; render(); }, 0); }

// ---------- Išvaizda: tema ir meniu (pavadinimai, tvarka) - bendri visoms varžyboms ----------
export var UI = CFG.ui || { theme: 'light', menu: { order: [], names: {} } };
var TAB_DEFAULT = {}; tabs.concat(['admin']).forEach(function (t) { var b = document.getElementById('tab-' + t); if (b) TAB_DEFAULT[t] = b.lastChild.textContent; });
export function postUi(data) {
  return fetch(CFG.uiApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf, Accept: 'application/json' }, body: JSON.stringify(data) })
    .then(function (r) { return r.json(); }).then(function (d) { if (d.ui) UI = d.ui; return d; });
}
function applyMenu() {
  var nav = document.querySelector('.tabs'), sep = nav.querySelector('.tabs-sep'), cfgBtn = document.getElementById('menu-cfg');
  var order = menuOrder(UI.menu && UI.menu.order);
  order.forEach(function (t) {
    var b = document.getElementById('tab-' + t); if (!b) return;
    if (t === 'admin' && order === TOP) nav.insertBefore(sep, cfgBtn);
    nav.insertBefore(b, cfgBtn);
  });
  tabs.concat(['admin']).forEach(function (t) { var b = document.getElementById('tab-' + t); if (b) b.lastChild.textContent = (UI.menu && UI.menu.names && UI.menu.names[t]) || TAB_DEFAULT[t]; });
  sep.hidden = order !== TOP;
}
applyMenu();
var themePick = document.getElementById('theme-pick');
if (themePick) themePick.addEventListener('change', function () { document.body.dataset.theme = themePick.value; postUi({ theme: themePick.value }); });
var menuBtn = document.getElementById('menu-cfg'), menuPanel = document.getElementById('menu-panel'), menuDraft = null;
function renderMenuPanel() {
  document.getElementById('menu-list').innerHTML = menuDraft.order.map(function (t, i) {
    return '<div class="menu-row"><span class="muted small">' + (i + 1) + '</span><input class="sel" data-menu-name="' + t + '" value="' + esc(menuDraft.names[t] || TAB_DEFAULT[t]) + '" aria-label="Pavadinimas: ' + esc(TAB_DEFAULT[t]) + '">' +
      '<button type="button" class="btn btn-xs" data-menu-up="' + i + '"' + (i ? '' : ' disabled') + ' aria-label="Aukštyn">↑</button><button type="button" class="btn btn-xs" data-menu-down="' + i + '"' + (i < menuDraft.order.length - 1 ? '' : ' disabled') + ' aria-label="Žemyn">↓</button>' +
      '<span class="muted small">' + esc(TAB_DEFAULT[t]) + '</span></div>';
  }).join('');
}
if (menuBtn) {
  menuBtn.addEventListener('click', function () {
    menuPanel.hidden = !menuPanel.hidden; menuBtn.setAttribute('aria-expanded', !menuPanel.hidden);
    if (!menuPanel.hidden) {
      menuDraft = { order: menuOrder(UI.menu.order).slice(), names: Object.assign({}, UI.menu.names || {}) };
      renderMenuPanel(); window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });
  menuPanel.addEventListener('input', function (e) { var k = e.target.dataset.menuName; if (k) menuDraft.names[k] = e.target.value; });
  menuPanel.addEventListener('click', function (e) {
    var t = e.target.closest('button'); if (!t) return;
    var o = menuDraft.order, i;
    if (t.dataset.menuUp) { i = +t.dataset.menuUp; o.splice(i - 1, 0, o.splice(i, 1)[0]); renderMenuPanel(); }
    else if (t.dataset.menuDown) { i = +t.dataset.menuDown; o.splice(i + 1, 0, o.splice(i, 1)[0]); renderMenuPanel(); }
    else if (t.id === 'menu-reset') { menuDraft = { order: TOP.slice(), names: {} }; renderMenuPanel(); }
    else if (t.id === 'menu-save') {
      var names = {}; Object.keys(menuDraft.names).forEach(function (k) { var v = String(menuDraft.names[k]).trim(); if (v && v !== TAB_DEFAULT[k]) names[k] = v; });
      var same = menuDraft.order.join() === TOP.join();
      postUi({ menu: { order: same ? [] : menuDraft.order, names: names } }).then(function () { applyMenu(); menuPanel.hidden = true; toast('Meniu išsaugotas.'); });
    }
  });
}

// ---------- Istorija serveryje: „kaip prieš 5 min.“ ar bet kuri kopija ----------
var histBtn = document.getElementById('hist-toggle'), histPanel = document.getElementById('hist-panel'), histList = [];
function agoText(at, now) {
  var m = Math.round((new Date(now.replace(' ', 'T')) - new Date(at.replace(' ', 'T'))) / 60000);
  return m < 1 ? 'ką tik' : m < 60 ? 'prieš ' + m + ' min.' : m < 1440 ? 'prieš ' + Math.floor(m / 60) + ' val. ' + (m % 60) + ' min.' : at.slice(0, 16);
}
function loadHistory() {
  var box = document.getElementById('hist-list');
  fetch(CFG.historyApi, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
    histList = (d.list || []).map(function (x) { x.ago = agoText(x.at, d.now); x.min = Math.round((new Date(d.now.replace(' ', 'T')) - new Date(x.at.replace(' ', 'T'))) / 60000); return x; });
    box.innerHTML = histList.length ? '<table class="hist-table"><tbody>' + histList.map(function (x) {
      return '<tr><td><b>' + x.at.slice(11, 16) + '</b> <span class="muted small">' + esc(x.ago) + '</span></td><td class="small">' + x.cats + ' kat., baigta ' + x.done + (x.note ? ' · <i>' + esc(x.note) + '</i>' : '') + '</td>' +
        '<td><button type="button" class="btn btn-xs" data-hist-id="' + x.id + '">Atkurti</button></td></tr>';
    }).join('') + '</tbody></table>' : '<p class="muted small">Kopijų dar nėra - jos atsiranda, kai kas nors pakeičiama.</p>';
  }).catch(function () { box.innerHTML = '<p class="muted small">Nepavyko įkelti istorijos.</p>'; });
}
function restoreSnap(id, label) {
  if (!confirm('Atkurti varžybas, kaip buvo ' + label + '? Dabartinė būsena bus išsaugota istorijoje.')) return;
  flush();
  fetch(CFG.restoreApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf, Accept: 'application/json' }, body: JSON.stringify({ id: id }) })
    .then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok) { toast(esc(d.error || 'Nepavyko atkurti.')); return; }
      var sel = S.selected; CFG.state = d.state; load(); if (cat(sel)) S.selected = sel;
      setRestored(d.version); markSaved(stateObj());
      var cf = document.getElementById('conflict'); if (cf) cf.hidden = true;
      if (stateCh) { try { stateCh.postMessage({ v: version, state: d.state }); } catch (e) {} }
      setStatus('Atkurta ✓', 'ok'); toast('Atkurta: <b>' + esc(label) + '</b>. Jei ne tai - atkurkite kitą kopiją (ir „Prieš atkūrimą“).');
      histPanel.hidden = true; render();
    }).catch(function () { toast('Nepavyko atkurti - nėra ryšio.'); });
}
if (histBtn) {
  histBtn.addEventListener('click', function () { histPanel.hidden = !histPanel.hidden; histBtn.setAttribute('aria-expanded', !histPanel.hidden); if (!histPanel.hidden) { loadHistory(); window.scrollTo({ top: 0, behavior: 'smooth' }); } });
  histPanel.addEventListener('click', function (e) {
    var t = e.target.closest('button'); if (!t) return;
    if (t.dataset.histId) { var x = histList.find(function (h) { return h.id === +t.dataset.histId; }); restoreSnap(+t.dataset.histId, x ? x.at.slice(11, 16) + ' (' + x.ago + ')' : ''); }
    else if (t.dataset.histAgo) {
      var need = +t.dataset.histAgo, pick = histList.find(function (h) { return h.min >= need; });
      if (!pick) { toast('Tokios senos kopijos dar nėra.'); return; }
      restoreSnap(pick.id, pick.at.slice(11, 16) + ' (' + pick.ago + ')');
    }
  });
}

// ---------- Transliacija ir registracija: nuorodos, ekranai ----------
var shareBtn = document.getElementById('share-toggle'), sharePanel = document.getElementById('share-panel');
function renderScreens() {
  var box = document.getElementById('sp-screens'); if (!box) return;
  box.innerHTML = S.areas.map(function (a) { return '<button type="button" class="btn" data-screen="' + a.id + '">📺 ' + esc(a.name) + '</button>'; }).join('');
}
if (shareBtn) {
  shareBtn.addEventListener('click', function () { sharePanel.hidden = !sharePanel.hidden; shareBtn.setAttribute('aria-expanded', sharePanel.hidden ? 'false' : 'true'); renderScreens(); if (!sharePanel.hidden) window.scrollTo({ top: 0, behavior: 'smooth' }); });
  sharePanel.addEventListener('click', function (e) {
    var t = e.target.closest('button'); if (!t) return;
    if (t.dataset.screen) { window.open(CFG.screenUrl + '&tatami=' + t.dataset.screen, 'taika-ekranas-' + t.dataset.screen); return; }
    if (t.dataset.copy) {
      var done = function () { var o = t.textContent; t.textContent = 'Nukopijuota ✓'; setTimeout(function () { t.textContent = o; }, 1500); };
      if (navigator.clipboard) navigator.clipboard.writeText(t.dataset.copy).then(done, function () { t.previousElementSibling.select(); });
      else { t.previousElementSibling.select(); document.execCommand('copy'); done(); }
    }
  });
  var ss = document.getElementById('stream-save');
  if (ss) ss.addEventListener('click', function () {
    fetch(CFG.regApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf },
      body: JSON.stringify({ stream_url: document.getElementById('stream-url').value, stream_at: document.getElementById('stream-at').value }) })
      .then(function (r) { return r.json(); }).then(function (d) { setStatus(d.ok ? 'Transliacija išsaugota ✓' : (d.error || 'Nepavyko'), d.ok ? 'ok' : 'err'); });
  });
  document.getElementById('reg-open').addEventListener('change', function (e) {
    fetch(CFG.regApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf }, body: JSON.stringify({ open: e.target.checked }) })
      .then(function () { setStatus(e.target.checked ? 'Registracija atidaryta ✓' : 'Registracija uždaryta', 'ok'); });
  });
}

// Perpiešus vaizdas nebeperšoka į kairę / į pradžią / į viršų (telefone - balų lentelė, tinklas, AKA / SHIRO): išsaugoma ir grąžinama
// kiekvieno slenkamo rėmelio ir lango padėtis
var HSCROLL = '.table-scroll, .bracket-wrap, .tk-tree, .ov-cols, .sched, .rs-list, .rs-area, .cat-scroll, .cat-list, .pool-list, .mp-list';
function scrollKey(el) { var host = el.parentElement && el.parentElement.closest('[id]'); return [host ? host.id : '', Array.prototype.indexOf.call((host || document).querySelectorAll(HSCROLL), el), el.className]; }
function scrollSnap() {
  var o = { x: window.scrollX, y: window.scrollY, els: [] };
  document.querySelectorAll(HSCROLL).forEach(function (el) { if (el.scrollLeft || el.scrollTop) o.els.push({ k: scrollKey(el), l: el.scrollLeft, t: el.scrollTop }); });
  return o;
}
function scrollRestore(o) {
  o.els.forEach(function (s) {
    var host = s.k[0] ? document.getElementById(s.k[0]) : document, el = host && host.querySelectorAll(HSCROLL)[s.k[1]];
    if (el && el.className === s.k[2]) { el.scrollLeft = s.l; el.scrollTop = s.t; }
  });
  if (window.scrollX !== o.x || Math.abs(window.scrollY - o.y) > 1) window.scrollTo(o.x, o.y);
}
export function render() { var sn = scrollSnap(); renderAll(); scrollRestore(sn); }
function renderAll() {
  document.getElementById('t-name').textContent = S.tournament;
  var tab = curTab();
  if (tab === 'klubai') renderClubs(); else if (tab === 'dalyviai') { renderEntries(); renderPeople(); } else if (tab === 'komandos') renderTeams(); else if (tab === 'kategorijos') renderCats(); else if (tab === 'rezultatai') renderResults(); else if (tab === 'tinklai') renderDraws(); else renderLive();
  if (tab !== 'live' && deskEl) { deskEl.hidden = true; document.body.classList.remove('desk-on'); }
  renderPins(); renderSekrDesk();
}
