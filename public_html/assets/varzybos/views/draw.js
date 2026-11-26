// Rungties valdymas: lentelė, ratų sistema, balai, paprastas vaizdas (AKA / SHIRO), vietų keitimas, atstatymas
import { BELTS, CFG, esc, hasBelt, KEY } from '../util.js';
import { boutMode, cfg, defaultSystem, elimDefault, isElim, MEDALS, methodList, methodOf, methodToSys, MODE_NAME, person, sysName, SYSTEMS } from '../rules.js';
import { drawSeed, makeDraw, seededRnd } from '../engine/draw.js';
import { allMatches, bracket, roundName } from '../engine/bracket.js';
import { parseScore, rrMatches, rrStandings } from '../engine/rr.js';
import { fmtScore, scoreDone, scoreFinalists, scoreRank, scoreTotal } from '../engine/score.js';
import { autoLock, catFinished, catPlaces, drawStale, matchCount, prune } from '../engine/category.js';
import { AW, cat, S, save, setAward, snapshot, undo } from '../store.js';
import { render, runOpen } from '../chrome.js';
import { kyu } from './categories.js';
import { boutEl, droppedIdx, findMatch, liveMatch, matchTitle, openBout, pushScoreLive } from '../bout.js';
import { goRun, nextGroup } from '../schedule.js';
import { runNeighbours } from './live.js';
import { upcoming } from './results.js';

// ---------- Lentelės ----------
var swapMode = false, picked = null;
// #185 (Denis): kita kova (pirma nebaigta) - raudonas rėmelis; pažymėjus nugalėtoją - nuslenkama prie jos
var nextMid = null;
export function resetSwap() { swapMode = false; picked = null; }
function slotHtml(m, side, c, clickable, firstRound, extra) {
  var id = side === 'a' ? m.a : m.b, p = id ? person(c, id) : null;
  var cls = 'slot ' + (side === 'b' ? 'shiro' : 'aka') + (m.winner && m.winner === id && !m.bye ? ' win' : '') + (!p ? ' empty' : '') + (picked && picked === id ? ' picked' : '');
  var label = p ? '<b>' + esc(p.name) + '</b><span class="club">' + esc(p.club) + '</span>' : '<b>' + (m.wait || m.rep ? 'Laukiama' : 'Laisva vieta') + '</b><span class="club">&nbsp;</span>';
  // Pirmame rate, kol nėra rezultatų ir neužrakinta - dalyvius galima sukeisti tempiant
  var si = firstRound ? c.draw.slots.indexOf(id) : -1;
  var dnd = firstRound && !c.draw.locked && (swapMode || !Object.keys(c.draw.results).length);
  var slotIdx = firstRound ? (side === 'a' ? 2 * +m.id.replace('r0m', '') : 2 * +m.id.replace('r0m', '') + 1) : -1;
  return '<div class="' + cls + (dnd ? ' dnd' : '') + (extra ? ' has-extra' : '') + '"' + (dnd ? ' data-drop-slot="' + slotIdx + '"' : '') + '><span class="stripe"></span><button type="button" data-m="' + m.id + '" data-p="' + (id || '') + '"' +
    (dnd && p ? ' data-drag-slot="' + si + '" title="Tempkite ant kito dalyvio - apsikeis vietomis"' : '') + (clickable && p ? '' : ' disabled') + '>' + label + '</button>' + (extra || '') + '</div>';
}
// Vėliavėlės: kiek vėliavėlių gavo laimėjęs (3 teisėjai - 2 ar 3, 5 - 3, 4 ar 5); balas įrašomas „4:1“
function flagChips(c, m, side) {
  var J = cfg(c).judges, sc = parseScore((c.draw.scores || {})[m.id]), mine = sc ? (side === 'a' ? sc[0] : sc[1]) : null, won = c.draw.results[m.id] === (side === 'a' ? m.a : m.b);
  var out = '<span class="flags" title="Keliomis vėliavėlėmis laimėjo">';
  for (var n = Math.floor(J / 2) + 1; n <= J; n++) out += '<button type="button" class="flag-chip' + (won && mine === n ? ' on' : '') + '" data-flag="' + m.id + '|' + side + '|' + n + '">' + n + '</button>';
  return out + '</span>';
}
function scoreInput(c, m) {
  var v = (c.draw.scores || {})[m.id] || '';
  return '<input class="m-score" data-score="' + m.id + '" value="' + esc(v) + '" placeholder="balai" title="Kovos rezultatas, pvz. 3:1 ar vėliavėlės 2:1" aria-label="Kovos rezultatas">';
}
function matchHtml(m, c, title, firstRound) {
  if (m.bye && !(swapMode && firstRound) && !firstRound) return '<div class="match ghost" aria-hidden="true"><div class="match-label"><span>&nbsp;</span></div>' + slotHtml(m, 'a', c, false) + slotHtml(m, 'b', c, false) + '</div>';
  if (m.bye && firstRound && (c.draw.locked || Object.keys(c.draw.results).length)) return '<div class="match ghost" aria-hidden="true"><div class="match-label"><span>&nbsp;</span></div>' + slotHtml(m, 'a', c, false) + slotHtml(m, 'b', c, false) + '</div>';
  var canWin = !swapMode && m.a && m.b;
  var canSwap = swapMode && firstRound && !c.draw.locked;
  var mode = boutMode(c, m), ready = !m.bye && m.a && m.b && !swapMode, flags = ready && mode === 'flags';
  var right = m.bye ? '<span>Laisvas ratas</span>' : !(m.a && m.b) ? '' : flags ? '<span class="m-flags-score">' + esc((c.draw.scores || {})[m.id] || 'vėliavėlės') + '</span>'
    : scoreInput(c, m) + '<button type="button" class="bout-open" data-bout="' + m.id + '" title="Kova: balai ir laikrodis' + (CFG.readOnly ? '' : ', ekranas') + '">▶</button>';
  var live = liveMatch && liveMatch.cat === c.id && liveMatch.mid === m.id, isNext = !live && !swapMode && !m.winner && nextMid === m.id;
  return '<div class="match' + (m.bye ? ' is-bye' : '') + (m.winner && !m.bye ? ' is-done' : '') + (live ? ' is-live' : '') + (isNext ? ' is-next' : '') + '"><div class="match-label"><span>' + (isNext ? '<b class="m-next">Kita kova</b> · ' : '') + (m.no ? m.no + ' · ' : '') + esc(title) + (mode === 'flags' && c.discipline === 'fukugo' ? ' · Kitei' : mode === 'kumite' && c.discipline === 'fukugo' ? ' · Kumite' : '') + '</span>' + right + '</div>' +
    slotHtml(m, 'a', c, canWin || canSwap, firstRound, flags ? flagChips(c, m, 'a') : '') + slotHtml(m, 'b', c, canWin || canSwap, firstRound, flags ? flagChips(c, m, 'b') : '') + '</div>';
}
export function diplomaUrl(catId, html) { return CFG.diplomaApi + (catId ? '&cat=' + encodeURIComponent(catId) : '') + (html ? '&html=1' : ''); }
function placesHtml(c, places, empty) {
  return '<div class="podium"><h3>Vietos' + (places.length && CFG.diplomaApi && !CFG.readOnly ? ' <a class="btn btn-xs dipl-btn" href="' + esc(diplomaUrl(c.id, false)) + '" target="_blank" rel="noopener" title="' +
    (CFG.diplomaTpl ? 'Diplomai šios kategorijos prizininkams - Excel pagal jūsų šabloną' : 'Spausdinti diplomus šios kategorijos prizininkams') + '">🎓 Diplomai</a>' : '') + '</h3>' + (places.length ? places.map(function (pl) {
    return pl[1].map(function (id) { var p = person(c, id); return '<div class="place p' + Math.min(pl[0], 4) + '"><span>' + pl[0] + '</span><span><b>' + esc(p ? p.name : '?') + '</b> <span class="muted">' + esc(p ? p.club : '') + '</span></span></div>'; }).join('');
  }).join('') : '<p class="muted small">' + empty + '</p>') + '</div>';
}
function drawHead(c, done, all) {
  return '<div class="draw-head"><h2><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + '</h2><span class="pill">' + c.competitors.length + ' dalyv.</span>' +
    '<span class="pill sys">' + esc(sysName(c, c.draw.system)) + '</span><span class="pill ' + (done === all && all ? 'ok' : '') + '">' + (c.draw.system === 'score' ? 'Pasirodymai' : 'Kovos') + ': ' + done + ' / ' + all + '</span>' +
    (drawStale(c) ? '<span class="pill warn">Dalyviai pasikeitė – spauskite „Burti iš naujo“</span>' : '') + '</div>';
}
function renderElim(c, view) {
  var b = bracket(c), all = allMatches(b), done = all.filter(function (m) { return m.winner && m.loser; }).length;
  nextMid = (upcoming(c)[0] || {}).id || null;
  var html = drawHead(c, done, all.length);
  html += '<div class="bracket-wrap"><div class="bracket">' + b.rounds.map(function (rd, r) {
    var title = roundName(r, b.R);
    return '<div class="round"><h3>' + title + '</h3><div class="round-body">' + rd.map(function (m) { return matchHtml(m, c, title, r === 0); }).join('') + '</div></div>';
  }).join('') + '</div>';
  if (b.rep.length) {
    html += '<div class="rep-wrap">' + b.rep.map(function (g) {
      return '<div class="rep-col"><h3>' + esc(g.title) + '</h3>' + (g.ready ? (g.matches.length ? g.matches.map(function (m) { return matchHtml(m, c, g.title.indexOf('3') >= 0 ? 'Bronza' : 'Paguoda', false); }).join('') : '<p class="muted small">Paguodos kovų nereikia – bronza be kovos.</p>') : '<p class="muted small">Prasidės, kai paaiškės finalininkai.</p>') + '</div>';
    }).join('') + '</div>';
  }
  html += '</div><div class="draw-foot">' + placesHtml(c, b.places, 'Vietos atsiras, kai baigsis finalas.');
  html += '<p class="hint">' + (swapMode ? '✎ Keitimas: <b>nutempkite vardą ant kito</b> (ar spauskite du vardus) pirmame rate - jie iškart susikeis vietomis. Baigę - „✓ Baigti keitimą“.' : 'Spauskite dalyvio vardą kovoje – jis laimi (dar kartą – atšaukiama). Balus įrašykite kovos viršuje. Kol kovų nebuvo, dalyvius pirmame rate galima <b>tempti</b> - apsikeis vietomis. Raudonas – aka, baltas – shiro.') + '</p></div>';
  view.innerHTML = html;
}
function renderRR(c, view) {
  var ms = rrMatches(c), st = rrStandings(c), done = ms.filter(function (m) { return m.winner; }).length;
  nextMid = (upcoming(c)[0] || {}).id || null;
  var html = drawHead(c, done, ms.length) + '<div class="rr-layout"><div class="rr-matches">';
  var round = 0;
  ms.forEach(function (m) {
    if (m.round !== round) { round = m.round; html += '<h3 class="rr-round">' + round + ' ratas</h3>'; }
    html += matchHtml(m, c, 'Kova', false);
  });
  html += '</div><div class="panel rr-table"><h2>Lentelė</h2><table><thead><tr><th class="num">#</th><th>Dalyvis</th><th class="num">Perg.</th><th class="num">Pralaim.</th><th class="num" title="Visų kovų taškai (vėliavėlės) kartu">Taškai</th></tr></thead><tbody>' +
    st.map(function (x, i) { var p = person(c, x.id); return '<tr><td class="num">' + (i + 1) + '</td><td><b>' + esc(p.name) + '</b> <span class="muted small">' + esc(p.club) + '</span></td><td class="num"><b>' + x.w + '</b></td><td class="num">' + x.l + '</td><td class="num" title="Skirtumas ' + (x.diff > 0 ? '+' : '') + x.diff + '"><b>' + x.pts + '</b></td></tr>'; }).join('') +
    '</tbody></table></div></div>';
  html += '<div class="draw-foot">' + placesHtml(c, catPlaces(c), 'Vietos atsiras, kai įvyks visos kovos.') +
    '<p class="hint">Visi kovoja su visais. Vietos: pergalės → tarpusavio kova → balų skirtumas (iš kovos balų, pvz. 3:1).</p></div>';
  view.innerHTML = html;
}
// Katos pavadinimas kiekvienam dalyviui kiekviename rate (pasirinkti iš sąrašo ar įrašyti); finale ta pati kata kaip pusfinalyje - pažymima
var KATA_LIST = ['Taikyoku Shodan', 'Heian Shodan', 'Heian Nidan', 'Heian Sandan', 'Heian Yondan', 'Heian Godan', 'Tekki Shodan', 'Tekki Nidan', 'Tekki Sandan',
  'Bassai Dai', 'Bassai Sho', 'Kanku Dai', 'Kanku Sho', 'Enpi', 'Jion', 'Jitte', 'Jiin', 'Hangetsu', 'Gankaku', 'Chinte', 'Sochin', 'Nijushiho', 'Meikyo',
  'Unsu', 'Wankan', 'Gojushiho Dai', 'Gojushiho Sho', 'Rohai', 'Seienchin', 'Saifa', 'Seipai', 'Seisan', 'Kururunfa', 'Suparinpei', 'Sanchin', 'Tensho'];
export function kataOf(c, which, id) { var k = c.draw && c.draw.kata && c.draw.kata[which]; return (k && k[id]) || ''; }
function kataDatalist() {
  var el = document.getElementById('kata-list'), have = {};
  if (!el) { el = document.createElement('datalist'); el.id = 'kata-list'; document.body.appendChild(el); }
  var names = KATA_LIST.slice();
  S.categories.forEach(function (c) { var k = c.draw && c.draw.kata; if (k) ['scores', 'final'].forEach(function (w) { Object.keys(k[w] || {}).forEach(function (id) { var n = k[w][id]; if (n && names.indexOf(n) === -1) names.push(n); }); }); });
  var html = names.map(function (n) { return have[n] ? '' : (have[n] = 1, '<option value="' + esc(n) + '">'); }).join('');
  if (el.innerHTML !== html) el.innerHTML = html;
}
function kataInput(c, which, id, cls) {
  var v = kataOf(c, which, id), dup = which === 'final' && v && v.toLowerCase() === kataOf(c, 'scores', id).toLowerCase();
  return '<input class="kata-in' + (cls ? ' ' + cls : '') + (dup ? ' dup' : '') + '" list="kata-list" maxlength="40" placeholder="Kata…" data-kata="' + id + '" data-which="' + which + '" value="' + esc(v) + '" aria-label="Kata"' + (dup ? ' title="Ši kata jau buvo pusfinalyje"' : '') + '>';
}
function scoreTable(c, ids, which, title) {
  var f = cfg(c), src = which === 'final' ? (c.draw.final = c.draw.final || {}) : (c.draw.scores = c.draw.scores || {});
  var rank = scoreRank(c, which, ids), placeOf = {}, kata = c.discipline === 'kata', fin = which === 'final';
  rank.forEach(function (x, i) { if (x.sum !== null) placeOf[x.id] = i + 1; });
  var sumOf = {}; rank.forEach(function (x) { sumOf[x.id] = x; });
  var head = '<tr><th class="num">Eil.</th><th>Dalyvis</th>' + (kata ? '<th>Kata</th>' : '');
  for (var j = 0; j < f.judges; j++) head += '<th class="num">T' + (j + 1) + '</th>';
  var lbl = (f.avg ? 'Vidurkis' : 'Suma') + (f.drop && f.judges >= 5 ? '*' : '');
  head += fin ? '<th class="num">Finalas</th><th class="num">Pusfinalis</th><th class="num">Iš viso</th>' : '<th class="num">' + lbl + '</th>';
  head += '<th class="num">Vieta</th></tr>';
  return '<div class="panel score-panel"><h2>' + esc(title) + '</h2><div class="table-scroll"><table class="score-table"><thead>' + head + '</thead><tbody>' +
    ids.map(function (id, i) {
      var p = person(c, id), arr = src[id] || [], t = scoreTotal(c, arr), drop = droppedIdx(c, arr), x = sumOf[id] || {};
      var canOrd = which === 'scores' && (swapMode || !scoreDone(c));
      // Klasės sc-*: telefone eilutė tampa kortele (vardas, balas ir vieta viršuje, kata, teisėjų langeliai eilute)
      var row = '<tr' + (t !== null ? ' class="scored"' : '') + (canOrd ? ' data-drop-ord="' + id + '"' : '') + '><td class="num sc-eil">' + (canOrd ? '<span class="ord-grip" data-drag-ord="' + id + '" title="Tempkite - keisti eilę">⠿</span>' : '') + (i + 1) + '</td><td class="sc-name"><b>' + esc(p.name) + '</b> <span class="muted small">' + esc(p.club) + '</span></td>' + (kata ? '<td class="sc-kata">' + kataInput(c, which, id) + '</td>' : '');
      for (var j = 0; j < f.judges; j++) row += '<td class="num sc-j"><input class="j-in' + (drop.indexOf(j) >= 0 ? ' dropped' : '') + '" type="number" step="0.1" min="0" max="10" inputmode="decimal" placeholder="T' + (j + 1) + '" data-j="' + j + '" data-sp="' + id + '" data-which="' + which + '" value="' + (arr[j] !== undefined && arr[j] !== null ? esc(arr[j]) : '') + '" aria-label="Teisėjas ' + (j + 1) + '"></td>';
      row += fin ? '<td class="num sc-sub" data-l="Finalas">' + fmtScore(c, t) + '</td><td class="num sc-sub" data-l="Pusfinalis">' + fmtScore(c, x.r1) + '</td><td class="num sc-sum" data-l="Iš viso"><b>' + fmtScore(c, x.sum) + '</b></td>' : '<td class="num sc-sum" data-l="' + lbl + '"><b>' + fmtScore(c, t) + '</b></td>';
      return row + '<td class="num sc-place">' + (placeOf[id] ? '<span class="rank r' + Math.min(placeOf[id], 4) + '">' + placeOf[id] + '</span>' : '') + '</td></tr>';
    }).join('') + '</tbody></table></div></div>';
}
function renderScore(c, view) {
  var f = cfg(c), ids = (c.draw.order || []).filter(function (id) { return person(c, id); });
  var html = drawHead(c, scoreDone(c), matchCount(c));
  html += '<div class="score-cfg"><label>Teisėjai <input class="sel num-in" type="number" min="1" max="15" data-cfg="judges" value="' + f.judges + '" title="Kiek teisėjų vertina (bet koks skaičius)"></label>' +
    '<label><input type="checkbox" data-cfg="drop"' + (f.drop ? ' checked' : '') + (f.judges < 5 ? ' disabled' : '') + '> Atmesti aukščiausią ir žemiausią balą</label>' +
    '<label>Ratai <select class="sel" data-cfg="rounds"><option value="1"' + (f.rounds === 1 ? ' selected' : '') + '>1</option><option value="2"' + (f.rounds === 2 ? ' selected' : '') + '>2 (finalas)</option></select></label>' +
    (f.rounds === 2 ? '<label>Į finalą <input class="sel num-in" type="number" min="2" max="16" data-cfg="finalists" value="' + f.finalists + '"></label>' : '') + '</div>';
  kataDatalist();
  html += scoreTable(c, ids, 'scores', f.rounds === 2 ? 'Pusfinalis (1 ratas)' : 'Balai');
  if (f.rounds === 2) {
    var fin = scoreFinalists(c);
    html += fin.length ? scoreTable(c, fin, 'final', 'Finalas (' + fin.length + ' geriausi)') : '<p class="muted small">Finalas atsiras, kai visi gaus pusfinalio balus.</p>';
  }
  var what = f.avg ? 'vidurkis' : 'suma';
  html += '<div class="draw-foot">' + placesHtml(c, catPlaces(c), 'Vietos atsiras, kai visi gaus balus.') +
    (swapMode ? '<p class="hint"><b>✎ Keitimas: nutempkite ⠿ ant kito dalyvio - jie iškart susikeis vietomis.</b></p>' : '') +
    '<p class="hint">Įrašykite kiekvieno teisėjo balą be taško: 75 → 7.5. ' + (f.drop && f.judges >= 5 ? '* ' + (f.avg ? 'Vidurkis' : 'Suma') + ' be aukščiausio ir žemiausio balo - jie lieka matomi perbraukti. ' : '') +
    (f.rounds === 2 ? 'Finalininkų vietos - pusfinalio ir finalo ' + (f.avg ? 'vidurkių' : 'sumų') + ' suma; lygiųjų atveju - finalo ' + what + ', tada visų balų suma. ' : 'Lygiųjų atveju lemia visų balų suma. ') +
    (c.discipline === 'kata' ? 'Kata - pasirinkite iš sąrašo ar įrašykite. ' : '') + 'Eilės tvarka - burtų; „Burti iš naujo“ ją sumaišo.' +
    (f.rounds === 2 ? ' <b>Finale pasirodo nuo žemiausio pusfinalio balo iki aukščiausio.</b>' : '') + '</p></div>';
  var y = window.scrollY; view.style.minHeight = view.offsetHeight + 'px';
  // v1.94 (#175): lentelė atnaujinama vietoje - langelis, į kurį rašoma, nepakeičiamas nauju, todėl planšetėje
  // klaviatūra neužsidaro ir ekranas nebeperšoka į viršų
  if (view.querySelector('.score-panel')) morphHtml(view, html); else view.innerHTML = html;
  view.style.minHeight = ''; if (window.scrollY !== y) window.scrollTo(window.scrollX, y);
}
// Paprastas DOM atnaujinimas vietoje: tie patys elementai lieka (su fokusu), keičiami tik tekstai, atributai ir tai, kas skiriasi
function morphHtml(el, html) {
  var tpl = document.createElement('div'); tpl.innerHTML = html;
  morphKids(el, tpl);
}
function morphKids(a, b) {
  var bn = Array.prototype.slice.call(b.childNodes);
  for (var i = 0; i < bn.length; i++) {
    var x = a.childNodes[i], y = bn[i];
    if (!x) { a.appendChild(y); continue; }
    if (x.nodeType !== y.nodeType || x.nodeName !== y.nodeName) { a.replaceChild(y, x); continue; }
    if (x.nodeType !== 1) { if (x.nodeValue !== y.nodeValue) x.nodeValue = y.nodeValue; continue; }
    var active = x === document.activeElement;
    Array.prototype.slice.call(x.attributes).forEach(function (at) { if (!y.hasAttribute(at.name)) x.removeAttribute(at.name); });
    Array.prototype.slice.call(y.attributes).forEach(function (at) { if (!(active && at.name === 'value') && x.getAttribute(at.name) !== at.value) x.setAttribute(at.name, at.value); });
    if (x.tagName === 'INPUT') {
      if (x.type === 'checkbox' || x.type === 'radio') x.checked = y.hasAttribute('checked');
      else if (!active && x.value !== (y.getAttribute('value') || '')) x.value = y.getAttribute('value') || '';
      continue;
    }
    if (x.tagName === 'SELECT' && !active) { x.innerHTML = y.innerHTML; continue; }
    if (x.tagName === 'TEXTAREA') { if (!active) x.value = y.value; continue; }
    morphKids(x, y);
  }
  while (a.childNodes.length > bn.length) a.removeChild(a.lastChild);
}
export function renderDraw() {
  var sel = document.getElementById('draw-cat');
  sel.innerHTML = S.categories.map(function (c) { return '<option value="' + c.id + '"' + (c.id === S.selected ? ' selected' : '') + '>' + esc(c.name) + ' (' + c.competitors.length + ')</option>'; }).join('');
  var c = cat(S.selected), view = document.getElementById('draw-view');
  if (!c) { view.innerHTML = '<p class="muted">Kategorijų dar nėra.</p>'; return; }
  if (!c.draw || (drawStale(c) && !c.draw.locked)) {
    if (c.competitors.length < 2) { view.innerHTML = '<p class="muted">Kategorijoje per mažai dalyvių lentelei.</p>'; return; }
  }
  if (!c.draw) { var sys0 = defaultSystem(c); c.draw = makeDraw(c, sys0, seededRnd(drawSeed(c, sys0, CFG.id))); }
  c.draw.scores = c.draw.scores || {};
  // Pasikeitė dalyviai, o kovų dar nebuvo - burtai traukiami iš naujo automatiškai
  var started = Object.keys(c.draw.results).length || (!isElim(c.draw.system) && scoreDone(c));
  if (drawStale(c) && !c.draw.locked && !started) { c.draw = makeDraw(c, c.draw.system); clearAward(c); save(); }
  var dsel = document.getElementById('draw-system'), msel = document.getElementById('draw-medals');
  dsel.innerHTML = methodList(c).map(function (x) { return '<option value="' + x[0] + '">' + esc(x[1]) + '</option>'; }).join('');
  dsel.value = methodOf(c.draw.system);
  if (msel) { msel.closest('label').style.display = methodOf(c.draw.system) === 'elim' ? '' : 'none'; msel.value = MEDALS[c.draw.system] ? c.draw.system : elimDefault(c); }
  // v2.01: „✎ Keisti“ - varžovų sukeitimas tempiant (olimpinėje - pirmame rate, balų sistemoje - pasirodymo eilė)
  var sw = document.getElementById('draw-swap'); sw.setAttribute('aria-pressed', swapMode ? 'true' : 'false'); sw.textContent = swapMode ? '✓ Baigti keitimą' : '✎ Keisti';
  document.getElementById('draw-view').classList.toggle('swap-mode', swapMode);
  document.getElementById('draw-swap').hidden = c.draw.system === 'rr';
  document.getElementById('draw-lock').setAttribute('aria-pressed', c.draw.locked ? 'true' : 'false');
  document.getElementById('draw-lock').textContent = c.draw.locked ? '🔒 Užrakinta' : '🔓 Užrakinti';
  document.getElementById('draw-new').disabled = c.draw.locked;
  document.getElementById('draw-swap').disabled = c.draw.locked;
  document.getElementById('draw-undo').disabled = !S.history.length;
  fillReset(c);
  if (runView === 'simple' && runOpen && !document.getElementById('run').hidden) renderSimple(c, view);
  else if (c.draw.system === 'rr') renderRR(c, view);
  else if (c.draw.system === 'score') renderScore(c, view);
  else renderElim(c, view);
}
// ---------- Paprastas vaizdas (kaip Kihapp): kairėje AKA, dešinėje SHIRO, diržai, kas ruošiasi; balų sistemoje - vienas dalyvis ----------
export var runView = 'lines';
try { runView = sessionStorage.getItem(KEY + '-runview') || 'lines'; } catch (e) {}
export function setRunView(v) { runView = v; }
function svPerson(c, id, side) {
  var p = person(c, id) || {}, pp = S.people[id] || {}, belt = pp.belt;
  return '<div class="sv-who"><div class="sv-label">' + (side === 'a' ? 'AKA' : side === 'b' ? 'SHIRO' : '') + '</div>' +
    '<div class="sv-name">' + esc(p.name || '—') + '</div><div class="sv-club">' + esc(p.club || '') + (pp.birth ? ' · ' + pp.birth : '') + '</div>' +
    (hasBelt(belt) ? '<div class="sv-belt"><span style="background:' + BELTS[belt][2] + '"></span>' + esc(kyu(+belt)) + '</div>' : '') + '</div>';
}
// Kai kita pora dar nežinoma (laukia šios kovos nugalėtojo) - rodoma, kas ruošiasi: žinomas dalyvis ir „šios kovos nugalėtojas“, arba kita grupė
export function prepLater(c, cur) {
  var nm = function (id) { return esc((person(c, id) || {}).name || ''); };
  var all = isElim(c.draw.system) ? allMatches(bracket(c)) : [];
  var nx = all.filter(function (x) { return x.id !== cur.id && !x.winner && !x.bye && (x.a || x.b); }).sort(function (x, y) { return (x.no || 99) - (y.no || 99); })[0];
  if (nx) {
    var one = nx.a || nx.b;
    return '<div class="sv-prep"><b>Ruošiasi:</b> ' + esc(nx.title || matchTitle(c, nx)) + ' - <span class="sv-dot ' + (nx.a ? 'a' : 'b') + '"></span>' + nm(one) + ' <span class="muted">vs</span> <i>šios kovos nugalėtojas</i></div>';
  }
  var ng = nextGroup(c);
  if (!ng) return '';
  var up = upcoming(ng)[0], pair = up ? (up.b ? esc((person(ng, up.a) || {}).name || '') + ' <span class="muted">vs</span> ' + esc((person(ng, up.b) || {}).name || '') : esc((person(ng, up.a) || {}).name || '')) : '';
  return '<div class="sv-prep"><b>Ruošiasi kita grupė:</b> ' + esc(ng.name) + (pair ? ' - ' + pair : '') + '</div>';
}
function renderSimple(c, view) {
  var list = upcoming(c), m = list[0], prep = list[1];
  var nb = runNeighbours(c);
  if (!m) {
    var pl = catPlaces(c).filter(function (x) { return x[0] <= 3; });
    view.innerHTML = '<div class="sv-done"><div class="sv-done-mark">✓</div><h2>Kategorija baigta</h2>' +
      (pl.length ? '<ol class="sv-places">' + pl.map(function (x) { return x[1].map(function (id) { return '<li><b>' + x[0] + '</b> ' + esc((person(c, id) || {}).name || '') + ' <span class="muted">' + esc((person(c, id) || {}).club || '') + '</span></li>'; }).join(''); }).join('') + '</ol>' : '') +
      (function () { var ng = nextGroup(c); return ng ? '<button type="button" class="btn btn-primary sv-next-cat" data-run-go="' + ng.id + '">Sekanti grupė: ' + esc(ng.name) + ' →</button>' : ''; })() + '</div>';
    return;
  }
  if (c.draw.system === 'score') {
    var w = m.which || 'scores', arr = ((w === 'final' ? c.draw.final : c.draw.scores) || {})[m.a] || [], J = cfg(c).judges, cells = '';
    for (var j = 0; j < J; j++) cells += '<input class="j-in sv-j" type="number" step="0.1" min="0" max="10" inputmode="decimal" data-j="' + j + '" data-sp="' + m.a + '" data-which="' + w + '" value="' + (arr[j] !== undefined && arr[j] !== null ? esc(arr[j]) : '') + '" aria-label="Teisėjas ' + (j + 1) + '">';
    if (c.discipline === 'kata') kataDatalist();
    pushScoreLive(c, m.a, w);
    view.innerHTML = '<div class="sv-meta"><b>' + esc(m.title) + '</b></div><div class="sv sv-score"><div class="sv-side sv-a">' + svPerson(c, m.a, '') +
      (c.discipline === 'kata' ? '<label class="sv-kata">Kata ' + kataInput(c, w, m.a) + '</label>' : '') +
      '<div class="sv-judges">' + cells + '</div><p class="muted small">Balai be taško: 75 → 7.5. Enter - kitas teisėjas, po paskutinio - kitas dalyvis.</p></div></div>' +
      (prep ? '<div class="sv-prep"><b>Ruošiasi:</b> ' + esc((person(c, prep.a) || {}).name || '') + ' <span class="muted">' + esc((person(c, prep.a) || {}).club || '') + '</span></div>' : '');
    return;
  }
  var mode = boutMode(c, m), flags = mode === 'flags';
  var winBtn = function (side) { var id = side === 'a' ? m.a : m.b; return '<button type="button" class="sv-win" data-m="' + m.id + '" data-p="' + id + '">Laimėjo ' + (side === 'a' ? 'AKA' : 'SHIRO') + '</button>' + (flags ? '<div class="sv-flags" title="Kiek vėliavėlių gavo laimėjęs">' + flagChips(c, m, side) + '</div>' : ''); };
  view.innerHTML = '<div class="sv-meta"><b>' + esc(m.title || matchTitle(c, m)) + '</b>' + (m.no ? ' · kova ' + m.no : '') + ' · ' + MODE_NAME[mode] +
      (flags ? ' · <span class="muted">pažymėkite, kiek vėliavėlių gavo laimėjęs</span>' : '') +
      (mode !== 'flags' ? '<span class="spacer"></span><button type="button" class="btn" data-bout="' + m.id + '">▶ Kovos skydelis (balai, laikas)</button>' : '') + '</div>' +
    '<div class="sv"><div class="sv-side sv-a">' + svPerson(c, m.a, 'a') + winBtn('a') + '</div><div class="sv-vs">VS</div><div class="sv-side sv-b">' + svPerson(c, m.b, 'b') + winBtn('b') + '</div></div>' +
    (prep ? '<div class="sv-prep"><b>Ruošiasi:</b> <span class="sv-dot a"></span>' + esc((person(c, prep.a) || {}).name || '') + ' <span class="muted">vs</span> <span class="sv-dot b"></span>' + esc((person(c, prep.b) || {}).name || '') +
      (list.length > 2 ? ' <span class="muted">· dar ' + (list.length - 2) + ' kov.</span>' : '') + '</div>' : prepLater(c, m));
}
document.getElementById('draw-view').addEventListener('click', function (e) { var g = e.target.closest('[data-run-go]'); if (g) goRun(g.dataset.runGo); });
// Sistemos keitimas: olimpinės tarpusavyje - rezultatai lieka; į ratų ar balų - nauji burtai
export function setSystem(c, sys) {
  if (!c || !SYSTEMS[sys] || (c.draw && c.draw.system === sys)) return;
  var started = c.draw && (Object.keys(c.draw.results || {}).length || (!isElim(c.draw.system) && scoreDone(c)));
  var same = c.draw && isElim(c.draw.system) && isElim(sys);
  if (started && !same && !confirm('Pakeitus sistemą, jau įrašyti kovų rezultatai ar balai dings. Tęsti?')) { render(); return; }
  snapshot();
  if (same) { c.draw.system = sys; prune(c); } else { c.draw = makeDraw(c, sys); clearAward(c); }
  save(); render();
}
// Rungtis ar „komandinė“ pakeista: numatyta sistema, jei kovų dar nebuvo
export function applyDefaultSystem(c) {
  var sys = defaultSystem(c);
  var started = c.draw && (Object.keys(c.draw.results || {}).length || (!isElim(c.draw.system) && scoreDone(c)));
  if (started || (c.draw && c.draw.system === sys)) return;
  if (c.draw && isElim(c.draw.system) && isElim(sys)) { c.draw.system = sys; return; }
  c.draw = c.competitors.length >= 2 ? makeDraw(c, sys) : null;
}
document.getElementById('draw-cat').addEventListener('change', function (e) { S.selected = e.target.value; picked = null; save(); render(); });
document.getElementById('draw-system').addEventListener('change', function (e) { var c = cat(S.selected); setSystem(c, methodToSys(c, e.target.value)); });
var drawMedals = document.getElementById('draw-medals');
if (drawMedals) drawMedals.addEventListener('change', function (e) { var c = cat(S.selected); cfg(c).medals = e.target.value; setSystem(c, e.target.value); });
function clearAward(c) { if (AW[c.id]) setAward([c.id], ''); }
document.getElementById('draw-new').addEventListener('click', function () { var c = cat(S.selected); if (c.competitors.length < 2) return; snapshot(); c.draw = makeDraw(c, c.draw ? c.draw.system : defaultSystem(c)); clearAward(c); picked = null; save(); render(); });
document.getElementById('draw-swap').addEventListener('click', function () { swapMode = !swapMode; picked = null; render(); });
document.getElementById('draw-lock').addEventListener('click', function () { var c = cat(S.selected); c.draw.locked = !c.draw.locked; swapMode = false; save(); render(); });
document.getElementById('draw-undo').addEventListener('click', function () { undo(); save(); render(); });
document.getElementById('draw-view').addEventListener('click', function (e) {
  var fl = e.target.closest('[data-flag]');
  if (fl) {
    var c0 = cat(S.selected), parts = fl.dataset.flag.split('|'), m0 = findMatch(c0, parts[0]); if (!m0 || !m0.a || !m0.b) return;
    var J = cfg(c0).judges, n = +parts[2], wid = parts[1] === 'a' ? m0.a : m0.b, txt = parts[1] === 'a' ? n + ':' + (J - n) : (J - n) + ':' + n;
    snapshot();
    var wasDone0 = catFinished(c0);
    if (c0.draw.results[m0.id] === wid && c0.draw.scores[m0.id] === txt) { delete c0.draw.results[m0.id]; delete c0.draw.scores[m0.id]; }
    else { c0.draw.results[m0.id] = wid; c0.draw.scores[m0.id] = txt; }
    prune(c0); autoLock(c0, wasDone0); save(); render();
    if (c0.draw.results[m0.id] === wid) goNext();
    return;
  }
  var bo = e.target.closest('[data-bout]');
  if (bo) { openBout(S.selected, bo.dataset.bout); return; }
  var btn = e.target.closest('button[data-m]'); if (!btn || btn.disabled) return;
  var c = cat(S.selected), pid = btn.dataset.p, mid = btn.dataset.m;
  if (swapMode) {
    if (!picked) { picked = pid; render(); return; }
    if (picked === pid) { picked = null; render(); return; }
    var pk = picked; picked = null;
    if (isElim(c.draw.system)) swapSlots(c, c.draw.slots.indexOf(pk), c.draw.slots.indexOf(pid)); else render();
    return;
  }
  snapshot();
  var wasDone = catFinished(c);
  var won = c.draw.results[mid] !== pid;
  if (won) c.draw.results[mid] = pid; else delete c.draw.results[mid];
  prune(c); autoLock(c, wasDone); save(); render();
  if (won) goNext();
});
// #185: kita kova - į matomą vietą (lentelė gali būti plati ir ilga) ir trumpai sumirksi
export function goNext() {
  var n = document.querySelector('#draw-view .match.is-next');
  if (!n || !n.offsetParent) return;
  var r = n.getBoundingClientRect(), wrap = n.closest('.bracket-wrap'), wr = wrap ? wrap.getBoundingClientRect() : null;
  var outY = r.top < 60 || r.bottom > window.innerHeight - 20, outX = wr ? (r.left < wr.left || r.right > wr.right) : (r.left < 0 || r.right > window.innerWidth);
  if (outY || outX) n.scrollIntoView({ block: outY ? 'center' : 'nearest', inline: outX ? 'center' : 'nearest', behavior: 'smooth' });
  n.classList.add('next-pulse');
}
document.getElementById('draw-view').addEventListener('change', function (e) {
  var t = e.target, c = cat(S.selected); if (!c) return;
  if (t.dataset.score) { c.draw.scores = c.draw.scores || {}; if (t.value.trim()) c.draw.scores[t.dataset.score] = t.value.trim(); else delete c.draw.scores[t.dataset.score]; save(); if (c.draw.system === 'rr') render(); return; }
  if (t.dataset.kata) {
    var kw = t.dataset.which === 'final' ? 'final' : 'scores';
    c.draw.kata = c.draw.kata || {}; c.draw.kata[kw] = c.draw.kata[kw] || {};
    var kv = t.value.trim().slice(0, 40);
    if (kv) c.draw.kata[kw][t.dataset.kata] = kv; else delete c.draw.kata[kw][t.dataset.kata];
    save(); kataDatalist(); pushScoreLive(c, t.dataset.kata, kw);
    // Ta pati kata finale kaip pusfinalyje - pažymima vietoje (be perpiešimo, kad fokusas liktų)
    var fk = document.querySelector('.kata-in[data-kata="' + t.dataset.kata + '"][data-which="final"]');
    if (fk) { var dup = !!fk.value.trim() && fk.value.trim().toLowerCase() === kataOf(c, 'scores', t.dataset.kata).toLowerCase(); fk.classList.toggle('dup', dup); fk.title = dup ? 'Ši kata jau buvo pusfinalyje' : ''; }
    return;
  }
  if (t.dataset.sp) {
    var src = t.dataset.which === 'final' ? (c.draw.final = c.draw.final || {}) : (c.draw.scores = c.draw.scores || {});
    var arr = src[t.dataset.sp] = src[t.dataset.sp] || [], v = t.value.replace(',', '.');
    arr[+t.dataset.j] = v === '' ? null : Math.max(0, Math.min(10, parseFloat(v)));
    save(); pushScoreLive(c, t.dataset.sp, t.dataset.which);
    // Perpiešiama, kai fokusas jau persikėlė (Tab ar paspaustas kitas langelis) - jis grąžinamas į tą patį langelį
    // Planšetėje vaizdas lieka toje pačioje vietoje (nebeperšoka į viršų)
    setTimeout(function () {
      var key = focusKey(document.activeElement), y = window.scrollY;
      render();
      var el = key && document.querySelector(key);
      if (el) { focusNoScroll(el); }
      window.scrollTo(window.scrollX, y);
    }, 0);
    return;
  }
  if (t.dataset.cfg) {
    var f = cfg(c), k = t.dataset.cfg;
    snapshot();
    if (k === 'drop' || k === 'avg') f[k] = t.checked; else f[k] = Math.max(1, parseInt(t.value, 10) || 1);
    if (f.judges > 15) f.judges = 15;
    save(); render();
  }
});
// Balai rašomi be taško: įvedus antrą skaitmenį taškas įdedamas pats (75 → 7.5, 80 → 8.0); 10 lieka 10.
// Daugiausia vienas skaitmuo po kablelio; įvedus pilną balą (7.7 ar 10) - iškart į kitą langelį.
document.addEventListener('input', function (e) {
  var t = e.target;
  if (!t.classList || !t.classList.contains('j-in')) return;
  var v = String(t.value), m = v.match(/^(\d)(\d)$/);
  if (m && v !== '10') v = t.value = m[1] + '.' + m[2];
  var long = v.match(/^(\d{1,2})[.,](\d)\d+$/);
  if (long) v = t.value = long[1] + '.' + long[2];
  if (/^\d{3,}$/.test(v)) v = t.value = v.slice(0, 2) === '10' ? '10' : v[0] + '.' + v[1];
  if (/^\d[.,]\d$/.test(v) || v === '10') moveJ(t, false);
});
function moveJ(t, back) {
  var scope = t.closest('#draw-view') || boutEl || document;
  var sel = t.dataset.part ? '.j-in[data-part]' : '.j-in[data-sp][data-which="' + t.dataset.which + '"]';
  var list = Array.prototype.slice.call(scope.querySelectorAll(sel)), nx = list[list.indexOf(t) + (back ? -1 : 1)];
  var key = !nx ? null : nx.dataset.part ? '[data-part="' + nx.dataset.part + '"]' : '[data-sp="' + nx.dataset.sp + '"][data-which="' + nx.dataset.which + '"][data-j="' + nx.dataset.j + '"]';
  var y = window.scrollY;
  // v1.94 (#175): fokusas perkeliamas iškart, be blur() - planšetėje klaviatūra neužsidaro ir vaizdas nešokinėja
  // (pasikeitęs balas išsaugomas „change“ įvykiu, kai langelis netenka fokuso)
  if (nx) { focusNoScroll(nx); return; }
  t.blur();
  setTimeout(function () {
    var el = key && scope.querySelector(key);
    if (el) focusNoScroll(el);
    if (Math.abs(window.scrollY - y) > 40) window.scrollTo(window.scrollX, y);
  }, 40);
}
// Fokusas be peršokimo: jei langelis matomas - vaizdas nejuda; jei ne - tik tiek, kad jis matytųsi
function focusNoScroll(el) {
  try { el.focus({ preventScroll: true }); } catch (ex) { el.focus(); }
  try { el.select(); } catch (ex) {}
  var r = el.getBoundingClientRect(), vh = window.visualViewport ? window.visualViewport.height : window.innerHeight;
  if (r.top < 80 || r.bottom > vh - 20) window.scrollBy(0, r.top - Math.max(100, vh / 3));
  // Į šoną - tik tiek, kad langelis matytųsi savo slenkamame rėmelyje (ne į pradžią)
  var sc = el.closest('.table-scroll, .bracket-wrap');
  if (sc && sc.scrollWidth > sc.clientWidth) {
    var er = el.getBoundingClientRect(), cr = sc.getBoundingClientRect();
    if (er.left < cr.left + 4) sc.scrollLeft -= cr.left - er.left + 30;
    else if (er.right > cr.right - 4) sc.scrollLeft += er.right - cr.right + 30;
  }
}
function focusKey(el) {
  if (!el || !el.dataset) return null;
  if (el.dataset.sp) return '.j-in[data-sp="' + el.dataset.sp + '"][data-which="' + el.dataset.which + '"][data-j="' + el.dataset.j + '"]';
  if (el.dataset.kata) return '.kata-in[data-kata="' + el.dataset.kata + '"][data-which="' + el.dataset.which + '"]';
  return null;
}
// Kata: Enter - to paties dalyvio pirmo teisėjo balas
document.addEventListener('keydown', function (e) {
  var t = e.target;
  if (e.key !== 'Enter' || !t.classList || !t.classList.contains('kata-in')) return;
  e.preventDefault();
  var sel = '.j-in[data-sp="' + t.dataset.kata + '"][data-which="' + t.dataset.which + '"][data-j="0"]';
  t.blur();
  setTimeout(function () { var el = document.querySelector(sel); if (el) { el.focus(); try { el.select(); } catch (ex) {} } }, 40);
});
// Teisėjų balai: Enter - kitas teisėjas, po paskutinio - kito dalyvio pirmas teisėjas (Shift+Enter - atgal).
// Lentelė perpiešiama pasikeitus balui, todėl kitas laukelis surandamas pagal jo žymes ir pažymimas po perpiešimo.
document.addEventListener('keydown', function (e) {
  var t = e.target;
  if (e.key !== 'Enter' || !t.classList || !t.classList.contains('j-in')) return;
  e.preventDefault();
  moveJ(t, e.shiftKey);
});
// Vietų keitimas tempiant (pele ir pirštu): pirmame rate - du dalyviai apsikeičia vietomis (ar į laisvą vietą),
// balų sistemoje - pasirodymo eilė. Kol rezultatų nėra - galima visada; po to - tik „⇄ Keisti vietas“ režime (rezultatai išvalomi).
var pd = null, skipClick = false;
function dropTarget(x, y, kind) {
  var el = document.elementFromPoint(x, y);
  return el && el.closest(kind === 'slot' ? '[data-drop-slot]' : '[data-drop-ord]');
}
function swapSlots(c, i, j) {
  var s = c.draw.slots;
  if (i === j || i < 0 || j < 0) return;
  if (Object.keys(c.draw.results || {}).length && !confirm('Jau yra kovų rezultatų. Sukeitus vietas, šios rungties rezultatai bus išvalyti. Tęsti?')) return;
  snapshot(); var x = s[i]; s[i] = s[j]; s[j] = x; c.draw.results = {}; c.draw.scores = c.draw.system === 'rr' ? c.draw.scores : {}; save(); render();
}
function moveOrder(c, id, beforeId) {
  var o = (c.draw.order || []).slice(), from = o.indexOf(id), to = o.indexOf(beforeId);
  if (from < 0 || to < 0 || from === to) return;
  snapshot();
  // v2.01: „✎ Keisti“ režime - abu dalyviai iškart susikeičia vietomis (kaip lentelėje); kitaip - įterpiama prieš kitą
  if (swapMode) { o[from] = beforeId; o[to] = id; } else { o.splice(from, 1); o.splice(o.indexOf(beforeId) + (from < to ? 1 : 0), 0, id); }
  c.draw.order = o; save(); render();
}
document.getElementById('draw-view').addEventListener('pointerdown', function (e) {
  if (e.button !== 0) return;
  var b = e.target.closest('[data-drag-slot], [data-drag-ord]'); if (!b) return;
  pd = { el: b, kind: b.hasAttribute('data-drag-slot') ? 'slot' : 'ord', from: b.dataset.dragSlot !== undefined ? +b.dataset.dragSlot : b.dataset.dragOrd, x: e.clientX, y: e.clientY, on: false, ghost: null };
});
document.addEventListener('pointermove', function (e) {
  if (!pd) return;
  if (!pd.on) {
    if (Math.abs(e.clientX - pd.x) + Math.abs(e.clientY - pd.y) < 8) return;
    pd.on = true;
    pd.ghost = document.createElement('div'); pd.ghost.className = 'drag-ghost';
    pd.ghost.textContent = (pd.el.querySelector('b') || pd.el).textContent;
    document.body.appendChild(pd.ghost); document.body.classList.add('is-dragging-name');
    (pd.el.closest('.slot, tr') || pd.el).classList.add('dragging');
  }
  e.preventDefault();
  pd.ghost.style.left = e.clientX + 'px'; pd.ghost.style.top = e.clientY + 'px';
  document.querySelectorAll('.drop').forEach(function (x) { x.classList.remove('drop'); });
  var t = dropTarget(e.clientX, e.clientY, pd.kind); if (t) t.classList.add('drop');
}, { passive: false });
function endDrag(e, cancel) {
  if (!pd) return;
  var d = pd; pd = null;
  if (!d.on) return;
  skipClick = true; setTimeout(function () { skipClick = false; }, 0);
  if (d.ghost) d.ghost.remove();
  document.body.classList.remove('is-dragging-name');
  document.querySelectorAll('.drop, .dragging').forEach(function (x) { x.classList.remove('drop', 'dragging'); });
  if (cancel) return;
  var t = dropTarget(e.clientX, e.clientY, d.kind), c = cat(S.selected); if (!t || !c || !c.draw) return;
  if (d.kind === 'slot') swapSlots(c, d.from, +t.dataset.dropSlot); else moveOrder(c, d.from, t.dataset.dropOrd);
}
document.addEventListener('pointerup', function (e) { endDrag(e, false); });
document.addEventListener('pointercancel', function (e) { endDrag(e, true); });
document.getElementById('draw-view').addEventListener('click', function (e) { if (skipClick) { e.stopPropagation(); e.preventDefault(); } }, true);

// ---------- Rungties atstatymas: visa arba nuo pasirinkto rato (vėlesni rezultatai ištrinami) ----------
function fillReset(c) {
  var box = document.getElementById('draw-reset'); if (!box) return;
  var opt = function (v, t) { return '<button type="button" class="btn btn-sm" data-reset="' + v + '">' + t + '</button>'; };
  var html = opt('all', 'Visą rungtį (visi rezultatai)');
  if (c && c.draw && isElim(c.draw.system)) {
    var b = bracket(c);
    b.rounds.forEach(function (rd, i) { if (i > 0) html += opt('r' + i, 'Nuo: ' + esc(roundName(i, b.R))); });
    if (b.rep.length) html += opt('rep', 'Tik paguoda / dėl 3 vietos');
  }
  box.innerHTML = html;
}
document.getElementById('draw-reset').addEventListener('click', function (e) {
  var btn = e.target.closest('[data-reset]'); if (!btn) return;
  document.getElementById('draw-reset-menu').open = false;
  var c = cat(S.selected), v = btn.dataset.reset; if (!c || !c.draw || !v) return;
  if (v === 'all') { if (!confirm('Atstatyti visą rungtį „' + c.name + '“ - ištrinti visus rezultatus ir balus?')) return; snapshot(); c.draw.results = {}; c.draw.scores = {}; c.draw.final = {}; }
  else {
    var b = bracket(c), ids = {};
    if (v === 'rep') b.rep.forEach(function (g) { g.matches.forEach(function (m) { ids[m.id] = 1; }); });
    else { var from = +v.slice(1); b.rounds.forEach(function (rd, i) { if (i >= from) rd.forEach(function (m) { ids[m.id] = 1; }); }); b.rep.forEach(function (g) { g.matches.forEach(function (m) { ids[m.id] = 1; }); }); }
    if (!confirm('Ištrinti ' + Object.keys(ids).filter(function (k) { return c.draw.results[k]; }).length + ' kovų rezultatus nuo pasirinkto rato?')) return;
    snapshot();
    Object.keys(ids).forEach(function (k) { delete c.draw.results[k]; if (c.draw.scores) delete c.draw.scores[k]; });
    prune(c);
  }
  clearAward(c);   // rezultatai ištrinti - nebe „apdovanota“
  save(); render();
});
