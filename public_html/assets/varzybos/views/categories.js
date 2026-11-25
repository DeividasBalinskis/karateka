// Kategorijos (grupės): struktūra, šablonas, nustatymai, dalyviai grupėje, dalyvis į tinkamas grupes, automatinis sudarymas
import { beltDot, beltName, beltOpts, BELTS, CFG, esc, hasBelt, KEY, newId, toast, YEAR } from '../util.js';
import { boutMode, cfg, defaultSystem, DISC, isElim, MEDALS, methodList, methodOf, methodToSys, MODE_NAME, person } from '../rules.js';
import { makeDraw } from '../engine/draw.js';
import { scoreDone } from '../engine/score.js';
import { drawStale } from '../engine/category.js';
import { addToCat, cat, catsOf, removeFromCat, S, save, snapshot } from '../store.js';
import { openRun, postUi, render, renderSoon, UI } from '../chrome.js';
import { applyDefaultSystem, setSystem } from './draw.js';
import { balance } from '../schedule.js';

// ---------- Kategorijos: sąrašas | kategorija su nustatymais | dalyvių sąrašas (tempti) ----------
var poolFilter = 'fit', poolQuery = '';
export function crit(c) { return c.crit || {}; }
function fits(c, p) {
  var k = crit(c), y = p.birth;
  if (!p || (k.g && k.g !== 'X' && p.gender && p.gender !== k.g)) return false;
  if (k.y0 && y && y < k.y0) return false;
  if (k.y1 && y && y > k.y1) return false;
  if (k.b0 && hasBelt(p.belt) && p.belt < k.b0) return false;
  if (k.b1 && hasBelt(p.belt) && p.belt > k.b1) return false;
  return true;
}
export function critName(c) {
  var k = crit(c), parts = [DISC[c.discipline] || c.discipline, k.g === 'F' ? 'F' : k.g === 'M' ? 'M' : 'Mix'];
  if (k.y0 || k.y1) parts.push(k.y0 && k.y1 ? (k.y1 === k.y0 ? k.y0 + ' m.' : k.y1 + '–' + k.y0 + ' m.') : k.y1 ? k.y1 + ' m. ir vyr.' : k.y0 + ' m. ir jaun.');
  var name = parts.join(' ');
  if (k.b0 || k.b1) name += ' · ' + (k.b0 && k.b1 ? kyu(k.b0).replace(' kyu', '') + '–' + kyu(k.b1) : k.b0 ? kyu(k.b0) + '+' : 'iki ' + kyu(k.b1));
  if (k.w) name += ' · ' + k.w;
  return name;
}
function sysBadge(c) { var s = c.draw ? c.draw.system : defaultSystem(c); return s === 'rr' ? 'Ratai' : s === 'score' ? 'Balai' : s === 'bronze' ? '1 trečia' : s === 'plain' ? '2 trečios' : s === 'fukugo' ? 'Fukugo' : 'Paguoda'; }
// ---------- Kategorijų struktūra (kaip Kihapp „Divisions“): visos rungtys stulpeliais, blokai - metai, lytis, kyu ----------
var catView = 'ov', ovQuery = '', ovGender = '', ovProblems = false, ovSort = 'order', ovOpen = {}, bDrag = null;
try { catView = sessionStorage.getItem(KEY + '-catview') || 'ov'; } catch (e) {}
function setCatView(v) { catView = v; try { sessionStorage.setItem(KEY + '-catview', v); } catch (e) {} renderCats(); }
function kyuOpts(cur) { return '<option value="">—</option>' + Object.keys(BELTS).filter(function (k) { return +k > 0; }).map(function (k) { return '<option value="' + k + '"' + (+k === +cur ? ' selected' : '') + '>' + kyu(+k).replace(' kyu', '') + '</option>'; }).join(''); }   // ribose 10 kyu nereikia - tai „nuo pradžios“
function catUnfit(c) { return c.competitors.filter(function (p) { return !p.team && S.people[p.id] && !fits(c, S.people[p.id]); }).length; }
function catProblems(c) { return c.competitors.length < 2 || catUnfit(c) > 0; }
// Rikiavimas didėjančia tvarka (gimimo metai, diržas, dalyvių sk.); ovDir: 1 - didėjančia, -1 - mažėjančia
var OV_SORT = {
  years: function (a, b) { return (crit(a).y0 || crit(a).y1 || 0) - (crit(b).y0 || crit(b).y1 || 0) || (crit(a).y1 || 0) - (crit(b).y1 || 0); },
  belt: function (a, b) { return (crit(a).b0 || 0) - (crit(b).b0 || 0) || (crit(a).b1 || 0) - (crit(b).b1 || 0); },
  count: function (a, b) { return a.competitors.length - b.competitors.length; }
};
var ovDir = { years: -1, belt: 1, count: -1 };
function renderOvDir() {
  var b = document.getElementById('ov-dir'); if (!b) return;
  b.hidden = !OV_SORT[ovSort];
  b.textContent = ovDir[ovSort] === 1 ? '↑ Didėjančia' : '↓ Mažėjančia';
  b.title = ovSort === 'years' ? (ovDir.years === 1 ? 'Vyriausi (seniausi gimimo metai) pirma' : 'Jauniausi pirma') : ovSort === 'belt' ? (ovDir.belt === 1 ? 'Žemesni diržai pirma' : 'Aukštesni diržai pirma') : (ovDir.count === 1 ? 'Mažiausiai dalyvių pirma' : 'Daugiausiai dalyvių pirma');
}
function ovMatch(c) {
  var g = crit(c).g || 'X', q = ovQuery;
  if (ovGender && g !== ovGender) return false;
  if (ovProblems && !catProblems(c)) return false;
  return !q || c.name.toLowerCase().indexOf(q) !== -1 || c.competitors.some(function (p) { return p.name.toLowerCase().indexOf(q) !== -1; });
}
function ovBlock(c) {
  var k = crit(c), n = c.competitors.length, unfit = catUnfit(c), custom = !!c.customName, g = k.g === 'M' || k.g === 'F' ? k.g : 'X';
  var people = ovOpen[c.id] ? '<div class="ovb-people">' + (c.competitors.map(function (p) {
    var pp = S.people[p.id] || {}, ok = p.team || fits(c, pp);
    return '<span class="ovp' + (ok ? '' : ' unfit') + '" draggable="true" data-drag="' + p.id + '" data-from="' + c.id + '" title="' + esc(p.club) + (ok ? '' : ' - netinka pagal kriterijus') + '">' + beltDot(pp.belt) + esc(p.name) + (pp.birth ? ' <i>' + pp.birth + '</i>' : '') + '</span>';
  }).join('') || '<span class="muted small">Tempkite dalyvius čia</span>') + '</div>' : '';
  return '<div class="ovb' + (n < 2 ? ' few' : '') + (unfit ? ' unfit' : '') + (c.team ? ' team' : '') + (catStarted(c) ? ' started' : '') + '" data-ovb="' + c.id + '" data-drop-cat="' + c.id + '">' +
    '<div class="ovb-row"><span class="ovb-grip" draggable="true" title="Tempkite - perkelti">⋮⋮</span>' +
    '<span class="ovb-years" title="Gimimo metai: jauniausi - vyriausi"><input class="ov-in" type="number" data-ov="y1" data-c="' + c.id + '" value="' + (k.y1 || '') + '" placeholder="−" title="' + (k.y1 ? 'Jauniausi gimę' : 'Be ribos (ir jaunesni) - paspauskite ir įrašykite metus') + '" aria-label="Gimę iki (jauniausi)">–<input class="ov-in" type="number" data-ov="y0" data-c="' + c.id + '" value="' + (k.y0 || '') + '" placeholder="+" title="' + (k.y0 ? 'Vyriausi gimę' : 'Be ribos (ir vyresni) - paspauskite ir įrašykite metus') + '" aria-label="Gimę nuo (vyriausi)"></span>' +
    '<select class="ov-sel" data-ov="g" data-c="' + c.id + '" aria-label="Lytis"><option value="M"' + (g === 'M' ? ' selected' : '') + '>M</option><option value="F"' + (g === 'F' ? ' selected' : '') + '>F</option><option value="X"' + (g === 'X' ? ' selected' : '') + '>Mix</option></select>' +
    '<span class="ovb-kyu" title="Kyu nuo - iki"><select class="ov-sel" data-ov="b0" data-c="' + c.id + '" aria-label="Diržas nuo">' + kyuOpts(k.b0) + '</select>–<select class="ov-sel" data-ov="b1" data-c="' + c.id + '" aria-label="Diržas iki">' + kyuOpts(k.b1) + '</select></span>' +
    '<button type="button" class="ovb-n" data-ov-open="' + c.id + '" aria-expanded="' + !!ovOpen[c.id] + '" title="' + (n < 2 ? 'Per mažai dalyvių' : 'Rodyti dalyvius') + (unfit ? ' · netinka: ' + unfit : '') + '">' + n + (unfit ? '<b>!</b>' : '') + '</button>' +
    '<button type="button" class="ovb-edit" data-ov-edit="' + c.id + '" title="Atidaryti kategoriją: sistema, kova, dalyviai" aria-label="Atidaryti kategoriją">→</button></div>' +
    (custom || c.team ? '<div class="ovb-name">' + (c.team ? '<span class="pill">komandos</span> ' : '') + esc(c.name) + '</div>' : '') + people + '</div>';
}
function renderOverview() {
  var ae = document.activeElement, keep = ae && ae.dataset && ae.dataset.c && ae.dataset.ov ? '[data-c="' + ae.dataset.c + '"][data-ov="' + ae.dataset.ov + '"]' : null;
  renderOverviewNow();
  if (keep) { var el = document.querySelector('#ov-cols ' + keep); if (el) el.focus(); }   // perpiešus - žymeklis lieka ten, kur buvo
}
function renderOverviewNow() {
  var discs = Object.keys(DISC).filter(function (d) { return S.categories.some(function (c) { return c.discipline === d; }); });
  var entries = 0, probs = 0;
  S.categories.forEach(function (c) { entries += c.competitors.length; if (catProblems(c)) probs++; });
  var none = Object.keys(S.people).filter(function (id) { return !catsOf(id).length; }).length;
  document.getElementById('ov-sum').innerHTML = S.categories.length + ' kategorijų · ' + entries + ' įrašų' + (probs ? ' · <b class="warn-t">' + probs + ' su problemomis</b>' : '') +
    (none ? ' · <button type="button" class="linkish" id="ov-none">' + none + ' dalyvių be kategorijos →</button>' : '');
  var others = function (d) { return Object.keys(DISC).filter(function (x) { return x !== d; }).map(function (x) { return '<option value="' + x + '">→ ' + DISC[x] + '</option>'; }).join(''); };
  document.getElementById('ov-cols').innerHTML = discs.map(function (d) {
    var list = S.categories.filter(function (c) { return c.discipline === d; }), shown = list.filter(ovMatch), n = 0;
    list.forEach(function (c) { n += c.competitors.length; });
    if (OV_SORT[ovSort]) shown = shown.slice().sort(function (x, y) { return OV_SORT[ovSort](x, y) * ovDir[ovSort]; });
    return '<div class="ov-col" data-ov-col="' + d + '"><div class="ov-head"><span class="disc d-' + d + '"></span><b>' + DISC[d] + '</b><span class="muted small">' + list.length + ' kat. · ' + n + ' dal.</span><span class="spacer"></span>' +
      '<button type="button" class="btn btn-xs" data-ov-redo="' + d + '" title="Netinkančius dalyvius perkelti į tinkamus blokus">⟳</button>' +
      '<select class="sel ov-copy" data-ov-copy="' + d + '" title="Nukopijuoti visus blokus į kitą rungtį" aria-label="Kopijuoti į"><option value="">⧉</option>' + others(d) + '</select>' +
      '<button type="button" class="btn btn-xs" data-ov-new="' + d + '" title="Naujas blokas">+</button></div>' +
      '<div class="ov-labels"><span></span><span>Gimę</span><span>Lytis</span><span>Kyu</span><span>Dalyv.</span><span></span></div>' +
      '<div class="ov-list">' + (shown.map(ovBlock).join('') || '<p class="muted small" style="padding:8px;">Nieko nerasta</p>') + '</div></div>';
  }).join('') + '<div class="ov-col ov-add"><select class="sel" id="ov-new-disc" aria-label="Nauja rungtis"><option value="">+ Rungtis…</option>' +
    Object.keys(DISC).filter(function (d) { return discs.indexOf(d) === -1; }).map(function (d) { return '<option value="' + d + '">' + DISC[d] + '</option>'; }).join('') + '</select></div>';
}
export function newCatIn(d, after) {
  var c = { id: newId('k'), name: '', discipline: d, competitors: [], tatami: (after && after.tatami) || S.areas[0].id, draw: null, crit: after ? JSON.parse(JSON.stringify(after.crit || {})) : {} };
  c.name = critName(c);
  var last = -1; S.categories.forEach(function (x, i) { if (x.discipline === d) last = i; });
  S.categories.splice(last + 1, 0, c);
  return c;
}
// Netinkantys dalyviai perkeliami į tinkamiausią (siauriausią) tos rungties bloką; pradėtos kategorijos nekeičiamos
function redistribute(d) {
  var movable = S.categories.filter(function (c) { return c.discipline === d && !c.team && !catStarted(c) && hasCrit(c); }), moved = 0, left = [];
  movable.forEach(function (c) {
    c.competitors.slice().forEach(function (p) {
      var pp = S.people[p.id]; if (!pp || fits(c, pp)) return;
      var target = movable.filter(function (x) { return x !== c && fits(x, pp); }).sort(function (x, y) { return catSpan(x) - catSpan(y); })[0];
      if (!target) { left.push(pp.name); return; }
      removeFromCat(p.id, c.id); addToCat(p.id, target.id); moved++;
    });
  });
  toast('Perkelta dalyvių: <b>' + moved + '</b>' + (left.length ? '<br>Tinkamo bloko nėra: ' + esc(left.slice(0, 6).join(', ')) + (left.length > 6 ? '…' : '') : ''));
}
// Visa rungties struktūra į kitą rungtį (pvz. Kata → Kumite): tie patys metai, lytis, kyu ir dalyviai; jau esantys tokie pat blokai praleidžiami
function copyDiscipline(from, to) {
  var have = S.categories.filter(function (c) { return c.discipline === to; }).map(function (c) { return JSON.stringify(crit(c)); }), n = 0;
  S.categories.filter(function (c) { return c.discipline === from && !c.team; }).forEach(function (c) {
    if (have.indexOf(JSON.stringify(crit(c))) !== -1) return;
    var nc = newCatIn(to, c); nc.competitors = c.competitors.map(function (p) { return { id: p.id, name: p.name, club: p.club }; }); nc.name = critName(nc); n++;
  });
  toast(DISC[from] + ' → ' + DISC[to] + ': nukopijuota blokų <b>' + n + '</b>. Pakeiskite, kas skiriasi.');
}
// Iššokantys meniu (↺ Atstatyti, 📋 Šablonas, 🎓 Diplomai) - neišlenda už ekrano krašto
document.addEventListener('toggle', function (e) {
  var d = e.target; if (!d.classList || !d.classList.contains('tool-menu') || !d.open) return;
  var body = d.querySelector('.tool-menu-body'); if (!body) return;
  body.style.left = ''; body.style.right = '';
  var r = body.getBoundingClientRect();
  if (r.left < 8) { body.style.left = '0'; body.style.right = 'auto'; }
}, true);
// ---------- Amžiaus kategorijų šablonas (📋): saugomas pagal amžių - tinka ir kitais metais; taikomas iš karto kelioms rungtims ----------
export var INDIV = ['kata', 'kumite', 'fukugo', 'kihon'];
function critKey(k) { return [k.y0 || '', k.y1 || '', k.g === 'M' || k.g === 'F' ? k.g : '', k.b0 || '', k.b1 || ''].join('|'); }
function tplFrom(d) {
  var Y = CFG.year;
  return S.categories.filter(function (c) { return c.discipline === d && !c.team; }).map(function (c) {
    var k = crit(c); return { a0: k.y1 ? Y - k.y1 : null, a1: k.y0 ? Y - k.y0 : null, g: k.g === 'M' || k.g === 'F' ? k.g : '', b0: k.b0 || null, b1: k.b1 || null };
  });
}
function tplApply(list, discs) {
  var Y = CFG.year, n = 0;
  discs.forEach(function (d) {
    var have = S.categories.filter(function (c) { return c.discipline === d; }).map(function (c) { return critKey(crit(c)); });
    list.forEach(function (b) {
      var k = {};
      if (b.a1 !== null && b.a1 !== undefined) k.y0 = Y - b.a1;
      if (b.a0 !== null && b.a0 !== undefined) k.y1 = Y - b.a0;
      if (b.g) k.g = b.g;
      if (b.b0) k.b0 = b.b0;
      if (b.b1) k.b1 = b.b1;
      if (have.indexOf(critKey(k)) !== -1) return;
      var nc = newCatIn(d, null); nc.crit = k; nc.name = critName(nc); have.push(critKey(k)); n++;
    });
    redistribute(d);   // jau esantys dalyviai - į tinkamus naujus blokus
  });
  return n;
}
function renderTpl() {
  var box = document.getElementById('ov-tpl-body'), tpl = UI.tpl || [];
  var discs = Object.keys(DISC).filter(function (d) { return S.categories.some(function (c) { return c.discipline === d && !c.team; }); });
  box.innerHTML = '<b>Amžiaus kategorijų šablonas</b><p class="small muted">' + (tpl.length
    ? 'Išsaugota blokų: <b>' + tpl.length + '</b> (pagal amžių - tinka ir kitais metais). Pažymėkite rungtis - blokai bus sukurti visiems iš karto.'
    : 'Sudėliokite vienos rungties blokus (metai, lytis, kyu) ir išsaugokite. Tada - viena paspauda visoms rungtims ir kitoms varžyboms.') + '</p>' +
    (tpl.length ? '<div class="tpl-discs">' + INDIV.map(function (d) { return '<label class="small"><input type="checkbox" data-tpl-disc="' + d + '" checked> ' + DISC[d] + '</label>'; }).join('') + '</div>' +
      '<button type="button" class="btn btn-primary btn-sm" id="tpl-apply">Sukurti blokus pažymėtoms rungtims</button>' : '') +
    (discs.length ? '<div class="tpl-save"><span class="small muted">' + (tpl.length ? 'Pakeisti šabloną pagal:' : 'Išsaugoti šabloną pagal:') + '</span>' +
      discs.map(function (d) { return '<button type="button" class="btn btn-sm" data-tpl-save="' + d + '">' + DISC[d] + '</button>'; }).join('') + '</div>' : '<p class="small muted">Pirma sukurkite bent vieną bloką.</p>');
}
document.getElementById('ov-tpl').addEventListener('toggle', function (e) { if (e.target.open) renderTpl(); });
document.getElementById('ov-tpl-body').addEventListener('click', function (e) {
  var sv = e.target.closest('[data-tpl-save]');
  if (sv) {
    var list = tplFrom(sv.dataset.tplSave);
    if (!list.length) return;
    if ((UI.tpl || []).length && !confirm('Pakeisti išsaugotą šabloną (' + UI.tpl.length + ' blokų) rungties „' + DISC[sv.dataset.tplSave] + '“ blokais (' + list.length + ')?')) return;
    postUi({ tpl: list }).then(function () { if (CFG.demo) UI.tpl = list; renderTpl(); toast('Šablonas išsaugotas: <b>' + list.length + '</b> blokų.'); });
    return;
  }
  if (e.target.id === 'tpl-apply') {
    var discs = [].slice.call(document.querySelectorAll('[data-tpl-disc]:checked')).map(function (x) { return x.dataset.tplDisc; });
    if (!discs.length) return;
    snapshot();
    var n = tplApply(UI.tpl || [], discs);
    document.getElementById('ov-tpl').open = false;
    save(); render();
    toast('Pagal šabloną sukurta blokų: <b>' + n + '</b> (' + discs.map(function (d) { return DISC[d]; }).join(', ') + '). Jau buvę tokie pat - praleisti.');
  }
});
var ovBox = document.getElementById('cat-overview');
document.getElementById('v-kategorijos').addEventListener('click', function (e) {
  var b = e.target.closest('[data-catview]'); if (b) { setCatView(b.dataset.catview); return; }
  var g = e.target.closest('[data-ovg]'); if (g) { ovGender = g.dataset.ovg; document.querySelectorAll('[data-ovg]').forEach(function (x) { x.classList.toggle('on', x === g); }); renderOverview(); return; }
  if (e.target.id === 'ov-auto') { document.getElementById('cat-auto').click(); return; }
});
document.getElementById('ov-search').addEventListener('input', function (e) { ovQuery = e.target.value.trim().toLowerCase(); renderOverview(); });
document.getElementById('ov-problems').addEventListener('change', function (e) { ovProblems = e.target.checked; renderOverview(); });
document.getElementById('ov-sort').addEventListener('click', function (e) {
  var b = e.target.closest('[data-sort]'); if (!b) return;
  ovSort = b.dataset.sort;
  this.querySelectorAll('[data-sort]').forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', x === b); });
  renderOvDir(); renderOverview();
});
document.getElementById('ov-dir').addEventListener('click', function () { if (!OV_SORT[ovSort]) return; ovDir[ovSort] = -ovDir[ovSort]; renderOvDir(); renderOverview(); });
ovBox.addEventListener('click', function (e) {
  var t = e.target.closest('button'); if (!t) return;
  if (t.dataset.ovOpen) { ovOpen[t.dataset.ovOpen] = !ovOpen[t.dataset.ovOpen]; renderOverview(); return; }
  if (t.dataset.ovEdit) { S.selected = t.dataset.ovEdit; save(); setCatView('edit'); window.scrollTo(0, 0); return; }
  if (t.id === 'ov-none') { poolFilter = 'none'; setCatView('edit'); return; }
  if (t.dataset.ovNew) { snapshot(); var nc = newCatIn(t.dataset.ovNew, null); save(); renderOverview(); var inp = ovBox.querySelector('[data-c="' + nc.id + '"][data-ov="y1"]'); if (inp) inp.focus(); return; }
  if (t.dataset.ovRedo) { snapshot(); redistribute(t.dataset.ovRedo); save(); render(); }
});
ovBox.addEventListener('change', function (e) {
  var t = e.target;
  if (t.dataset.ovCopy !== undefined) { if (t.value) { snapshot(); copyDiscipline(t.dataset.ovCopy, t.value); save(); render(); } return; }
  if (t.id === 'ov-new-disc') { if (t.value) { snapshot(); newCatIn(t.value, null); save(); render(); } return; }
  var c = cat(t.dataset.c); if (!c || !t.dataset.ov) return;
  snapshot();
  var v = t.value.trim();
  c.crit = c.crit || {};
  c.crit[t.dataset.ov] = t.dataset.ov === 'g' ? v : (+v || null);
  if (!c.customName) c.name = critName(c);   // pavadinimo niekas nekeitė ranka - atsinaujina pats
  save(); renderSoon();
});
// Bloko tempimas: į kitą vietą tame pačiame ar kitame stulpelyje (rungtis pasikeičia)
ovBox.addEventListener('dragstart', function (e) {
  var g = e.target.closest && e.target.closest('.ovb-grip'); if (!g) return;
  var blk = g.closest('[data-ovb]'); bDrag = blk.dataset.ovb;
  e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', bDrag); e.dataTransfer.setDragImage(blk, 16, 16); } catch (x) {}
  blk.classList.add('dragging');
});
ovBox.addEventListener('dragend', function () { bDrag = null; ovBox.querySelectorAll('.dragging, .drop-before').forEach(function (x) { x.classList.remove('dragging', 'drop-before'); }); });
ovBox.addEventListener('dragover', function (e) {
  if (!bDrag) return; var col = e.target.closest('[data-ov-col]'); if (!col) return;
  e.preventDefault();
  ovBox.querySelectorAll('.drop-before').forEach(function (x) { x.classList.remove('drop-before'); });
  var over = e.target.closest('[data-ovb]'); if (over && over.dataset.ovb !== bDrag) over.classList.add('drop-before');
});
ovBox.addEventListener('drop', function (e) {
  if (!bDrag) return; var col = e.target.closest('[data-ov-col]'); if (!col) return; e.preventDefault();
  var c = cat(bDrag), over = e.target.closest('[data-ovb]'), d = col.dataset.ovCol; bDrag = null;
  if (!c || (over && over.dataset.ovb === c.id)) return;
  snapshot();
  S.categories.splice(S.categories.indexOf(c), 1);
  if (c.discipline !== d) { c.discipline = d; delete cfg(c).time; cfg(c); applyDefaultSystem(c); if (!c.customName) c.name = critName(c); }
  var idx = over ? S.categories.indexOf(cat(over.dataset.ovb)) : -1;
  if (idx === -1) { var last = -1; S.categories.forEach(function (x, i) { if (x.discipline === d) last = i; }); S.categories.splice(last + 1, 0, c); }
  else S.categories.splice(idx, 0, c);
  save(); render();
});

export function renderCats() {
  document.querySelectorAll('[data-catview]').forEach(function (b) { b.classList.toggle('on', b.dataset.catview === catView); b.setAttribute('aria-pressed', b.dataset.catview === catView); });
  document.querySelectorAll('.ov-only').forEach(function (x) { x.hidden = catView !== 'ov'; });
  document.getElementById('cat-overview').hidden = catView !== 'ov';
  document.getElementById('cat-edit').hidden = catView === 'ov';
  if (catView === 'ov') { renderOverview(); return; }
  var ul = document.getElementById('cat-list');
  ul.innerHTML = S.categories.map(function (c) {
    var bad = c.competitors.length === 1 ? ' one' : '';
    return '<li data-drop-cat="' + c.id + '"' + (c.id === S.selected ? ' aria-current="true"' : '') + '><button type="button" data-cat="' + c.id + '"><span class="cl-name"><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) +
      (sysBadge(c) ? ' <span class="sys-mini">' + sysBadge(c) + '</span>' : '') + '</span><span class="cnt' + bad + '">' + c.competitors.length + '</span></button></li>';
  }).join('') || '<li class="muted small" style="padding:12px;">Kategorijų dar nėra. Spauskite „⚡ Automatiškai“ arba „+ Nauja“.</li>';
  var c = cat(S.selected) || S.categories[0];
  var curLi = ul.querySelector('[aria-current]'); if (curLi && curLi.scrollIntoView) curLi.scrollIntoView({ block: 'nearest' });
  renderPool(c);
  if (!c) { document.getElementById('cat-detail').innerHTML = '<h2>Kategorijų nėra</h2><p class="muted" style="padding:14px 16px;">Sudarykite kategorijas automatiškai pagal dalyvių amžių, lytį ir diržą arba sukurkite naują.</p>'; return; }
  S.selected = c.id;
  var others = S.categories.filter(function (x) { return x.id !== c.id; });
  var opts = others.map(function (x) { return '<option value="' + x.id + '">' + esc(x.name) + '</option>'; }).join('');
  var stale = drawStale(c) && c.competitors.length > 1, k = crit(c), sys = c.draw ? c.draw.system : defaultSystem(c), f = cfg(c);
  var started = c.draw && Object.keys(c.draw.results || {}).length;
  var fitCount = Object.keys(S.people).filter(function (id) { return !person(c, id) && fits(c, S.people[id]) && (crit(c).g || crit(c).y0 || crit(c).y1 || crit(c).b0 || crit(c).b1); }).length;
  var beltSel = function (key, cur) { return '<select class="sel" data-crit="' + key + '">' + beltOpts(cur) + '</select>'; };
  document.getElementById('cat-detail').innerHTML =
    '<div class="cat-back"><button type="button" class="btn btn-sm" id="cat-back" title="Atgal į visų kategorijų struktūrą (ten, kur buvote)">← Grįžti į kategorijas</button></div>' +
    '<h2><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + ' <span class="pill">' + c.competitors.length + ' dalyv.</span>' + (stale ? ' <span class="pill warn">Lentelė pasenusi</span>' : '') +
      '<span class="spacer"></span><button class="btn btn-primary" type="button" id="cat-draw" title="Atidaryti Live tvarkaraštyje: lentelė, kovos, balai">Valdyti →</button></h2>' +
    '<div class="cat-scroll">' +
    '<div class="fmt">' +
      '<div class="fmt-row"><span class="fmt-label">Pavadinimas</span><div class="fmt-ctl"><input type="text" class="sel wide" id="cat-name" value="' + esc(c.name) + '" aria-label="Kategorijos pavadinimas"><button class="btn" type="button" id="cat-autoname" title="Pagal rungtį ir kriterijus">↻ Pagal kriterijus</button></div></div>' +
      '<div class="fmt-row"><span class="fmt-label">Rungtis</span><div class="fmt-ctl seg">' + Object.keys(DISC).map(function (d) {
        return '<button type="button" class="seg-btn' + (d === c.discipline ? ' on' : '') + '" data-disc="' + d + '"><span class="disc d-' + d + '"></span>' + DISC[d] + '</button>';
      }).join('') + '<label class="small team-chk"><input type="checkbox" id="cat-team"' + (c.team ? ' checked' : '') + '> Komandinė</label></div></div>' +
      '<div class="fmt-row"><span class="fmt-label">Sistema</span><div class="fmt-ctl"><div class="sys-cards">' + methodList(c).map(function (x) {
        var key = methodToSys(c, x[0]);
        return '<button type="button" class="sys-card' + (methodOf(sys) === x[0] ? ' on' : '') + '" data-sys="' + key + '"><span class="sys-ico sys-' + key + '" aria-hidden="true"></span><b>' + esc(x[1]) + '</b><span>' + esc(x[2]) + '</span></button>';
      }).join('') + '</div>' +
      (methodOf(sys) === 'elim' ? '<label class="sys-medals">Medaliai <select class="sel" data-medals="1">' + Object.keys(MEDALS).map(function (k3) { return '<option value="' + k3 + '"' + (k3 === sys ? ' selected' : '') + '>' + MEDALS[k3] + '</option>'; }).join('') + '</select></label>' : '') +
      '</div></div>' +
      '<div class="fmt-row"><span class="fmt-label">Kova</span><div class="fmt-ctl crit">' +
        '<label>Teisėjai <input class="sel num-in" type="number" min="1" max="15" data-cfg="judges" value="' + f.judges + '" title="Kiek teisėjų vertina (bet koks skaičius)"></label>' +
        (c.discipline === 'kumite' || c.discipline === 'fukugo' || c.discipline === 'sumo' ? '<label>Kovos laikas <select class="sel" data-cfg="time">' + [10, 30, 60, 90, 120, 150, 180].map(function (n) { return '<option value="' + n + '"' + (n === f.time ? ' selected' : '') + '>' + Math.floor(n / 60) + ':' + (n % 60 < 10 ? '0' : '') + (n % 60) + (n === 10 ? ' (Jiyu ippon, Ko-Go)' : '') + '</option>'; }).join('') + '</select></label>' : '') +
        (c.discipline === 'sumo' ? '<label>Laimi surinkęs <input class="sel num-in" type="number" min="1" max="20" data-cfg="sumoTo" value="' + f.sumoTo + '"> tšk.</label>' : '') +
        (c.team && c.discipline !== 'kumite' && isElim(sys) ? '<label><input type="checkbox" data-cfg="bunkaiFinal"' + (f.bunkaiFinal ? ' checked' : '') + '> Finale ir dėl bronzos: Kata + Pritaikymas (balai)</label>' : '') +
        '<span class="muted small">' + MODE_NAME[boutMode(c, { left: 3 })] + (c.discipline === 'fukugo' ? ' / Kitei' : '') + '</span>' +
      '</div></div>' +
      '<div class="fmt-row"><span class="fmt-label">Kriterijai</span><div class="fmt-ctl crit">' +
        '<label>Lytis <select class="sel" data-crit="g"><option value="">—</option><option value="M"' + (k.g === 'M' ? ' selected' : '') + '>M</option><option value="F"' + (k.g === 'F' ? ' selected' : '') + '>F</option><option value="X"' + (k.g === 'X' ? ' selected' : '') + '>Mix</option></select></label>' +
        '<label>Gimę nuo <input class="sel num-in" type="number" data-crit="y0" value="' + (k.y0 || '') + '" placeholder="metai"></label>' +
        '<label>iki <input class="sel num-in" type="number" data-crit="y1" value="' + (k.y1 || '') + '" placeholder="metai"></label>' +
        '<label>Diržas nuo ' + beltSel('b0', k.b0) + '</label><label>iki ' + beltSel('b1', k.b1) + '</label>' +
        '<label>Svoris <input class="sel" style="width:90px" data-crit="w" value="' + esc(k.w || '') + '" placeholder="-45 kg"></label>' +
        (fitCount ? '<button class="btn" type="button" id="cat-addfit">+ Pridėti tinkamus (' + fitCount + ')</button>' : '') +
      '</div></div>' +
      '<div class="fmt-row"><span class="fmt-label">Veiksmai</span><div class="fmt-ctl">' +
        '<button class="btn" type="button" id="cat-split">Padalinti per pusę</button>' +
        '<select class="sel" id="cat-merge-into" aria-label="Sujungti su"><option value="">Sujungti su…</option>' + opts + '</select>' +
        '<button class="btn btn-danger" type="button" id="cat-del">Ištrinti kategoriją</button>' +
      '</div></div>' +
    '</div>' +
    '<div class="table-scroll drop-zone" data-drop-cat="' + c.id + '"><table><thead><tr><th></th><th>Dalyvis</th><th>Klubas</th><th class="num">Gim.</th><th>Diržas</th><th>Perkelti į</th><th></th></tr></thead><tbody>' +
    c.competitors.map(function (p) {
      var pp = S.people[p.id] || {}, ok = p.team || fits(c, pp);
      return '<tr draggable="true" data-drag="' + p.id + '" data-from="' + c.id + '"' + (ok ? '' : ' class="warn-row" title="Neatitinka kategorijos kriterijų"') + '><td class="grip" aria-hidden="true">⋮⋮</td><td><b>' + esc(p.name) + '</b>' + (pp.member ? ' <span class="home-mark" title="Karateka narys">K</span>' : '') + (pp.noPhoto ? ' <span class="nophoto">be nuotraukų</span>' : '') + (ok ? '' : ' <span class="pill warn">netinka</span>') + '</td><td>' + esc(p.club) + '</td>' +
        '<td class="num">' + (pp.birth || '') + '</td><td>' + beltDot(pp.belt) + esc(beltName(pp.belt)) + '</td>' +
        '<td><select class="sel add-cat" data-move="' + p.id + '" aria-label="Perkelti ' + esc(p.name) + '"><option value="">—</option>' + opts + '</select></td>' +
        '<td><button class="btn btn-danger btn-xs" type="button" data-remove="' + p.id + '" aria-label="Pašalinti ' + esc(p.name) + '">×</button></td></tr>';
    }).join('') + '</tbody></table>' + (c.competitors.length ? '' : '<p class="drop-hint">Tempkite dalyvius čia iš dešinės</p>') + '</div>' +
    '<div class="add-row"><input type="text" id="new-name" placeholder="Vardas Pavardė" aria-label="Naujo dalyvio vardas">' +
      '<select class="sel" id="new-club" aria-label="Klubas">' + S.clubs.map(function (x) { return '<option>' + esc(x) + '</option>'; }).join('') + '</select>' +
      '<button class="btn" type="button" id="add-person">+ Naujas dalyvis</button>' +
      '<span class="muted small">Dalyvius tempkite: iš dešinės - į kategoriją, iš lentelės - į kitą kategoriją kairėje.</span></div>' +
    '</div>';
}
function renderPool(c) {
  var box = document.getElementById('cat-pool'); if (!box) return;
  var list = Object.keys(S.people).map(function (id) { return S.people[id]; }).filter(function (p) {
    if (poolQuery && p.name.toLowerCase().indexOf(poolQuery) === -1 && String(p.club).toLowerCase().indexOf(poolQuery) === -1) return false;
    if (poolFilter === 'none') return !catsOf(p.id).length;
    if (poolFilter === 'fit') return c && !person(c, p.id) && fits(c, p);
    return true;
  }).sort(function (a, b) { return a.name.localeCompare(b.name, 'lt'); });
  box.querySelector('.pool-list').innerHTML = list.slice(0, 400).map(function (p) {
    var n = catsOf(p.id).length;
    return '<div class="pool-item" draggable="true" data-drag="' + p.id + '">' + beltDot(p.belt) + '<span class="pi-name">' + esc(p.name) + '<small>' + esc(p.club) + ' · ' + (p.birth || '?') + ' · ' + (p.gender || '?') + '</small></span>' +
      '<span class="pi-n' + (n ? '' : ' zero') + '" title="Kategorijų">' + n + '</span>' + (c && !person(c, p.id) ? '<button type="button" class="pi-add" data-pool-add="' + p.id + '" title="Pridėti į šią kategoriją">+</button>' : '') + '</div>';
  }).join('') || '<p class="muted small" style="padding:10px;">Nėra.</p>';
  box.querySelectorAll('[data-pool]').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.pool === poolFilter ? 'true' : 'false'); });
}
document.getElementById('cat-pool').addEventListener('click', function (e) {
  var t = e.target;
  if (t.dataset.pool) { poolFilter = t.dataset.pool; renderCats(); return; }
  if (t.dataset.poolAdd) { var c = cat(S.selected); if (!c) return; snapshot(); addToCat(t.dataset.poolAdd, c.id); save(); render(); }
});
document.getElementById('cat-pool').addEventListener('input', function (e) { if (e.target.id === 'pool-search') { poolQuery = e.target.value.trim().toLowerCase(); renderPool(cat(S.selected)); } });

// Tempimas: dalyvis iš sąrašo dešinėje → į kategoriją; iš kategorijos → į kitą kategoriją (su Ctrl - nukopijuoti); atgal į sąrašą - pašalinti
var pDrag = null;
var catsTab = document.getElementById('v-kategorijos');
catsTab.addEventListener('dragstart', function (e) {
  var el = e.target.closest && e.target.closest('[data-drag]'); if (!el) return;
  pDrag = { id: el.dataset.drag, from: el.dataset.from || null };
  e.dataTransfer.effectAllowed = 'copyMove'; try { e.dataTransfer.setData('text/plain', pDrag.id); } catch (x) {}
  el.classList.add('dragging'); catsTab.classList.add('is-dragging');
});
catsTab.addEventListener('dragend', function () { pDrag = null; catsTab.classList.remove('is-dragging'); catsTab.querySelectorAll('.drop, .dragging').forEach(function (x) { x.classList.remove('drop', 'dragging'); }); });
catsTab.addEventListener('dragover', function (e) {
  if (!pDrag) return; var t = e.target.closest('[data-drop-cat], [data-drop-pool]'); if (!t) return;
  if (t.dataset.dropCat && t.dataset.dropCat === pDrag.from) return;
  e.preventDefault(); e.dataTransfer.dropEffect = e.ctrlKey || e.altKey || !pDrag.from ? 'copy' : 'move';
  catsTab.querySelectorAll('.drop').forEach(function (x) { if (x !== t) x.classList.remove('drop'); }); t.classList.add('drop');
});
catsTab.addEventListener('drop', function (e) {
  if (!pDrag) return; var t = e.target.closest('[data-drop-cat], [data-drop-pool]'); if (!t) return; e.preventDefault();
  var d = pDrag; pDrag = null; snapshot();
  if (t.dataset.dropPool !== undefined) { if (d.from) removeFromCat(d.id, d.from); }
  else {
    var to = cat(t.dataset.dropCat), src = d.from && cat(d.from), comp = src ? person(src, d.id) : null;
    if (comp && comp.team) { if (!person(to, d.id)) to.competitors.push(comp); }
    else addToCat(d.id, to.id);
    if (src && !(e.ctrlKey || e.altKey)) removeFromCat(d.id, src.id);
  }
  save(); render();
});

document.getElementById('cat-list').addEventListener('click', function (e) {
  var b = e.target.closest('[data-cat]'); if (!b) return;
  S.selected = b.dataset.cat; save(); render();
});
document.getElementById('cat-new').addEventListener('click', function () {
  snapshot();
  var id = newId('k');
  S.categories.push({ id: id, name: 'Nauja kategorija', discipline: 'kata', competitors: [], tatami: S.areas[0].id, draw: null, crit: {} });
  S.selected = id; save(); render();
  var inp = document.getElementById('cat-name'); if (inp) { inp.focus(); inp.select(); }
});
document.getElementById('cat-detail').addEventListener('change', function (e) {
  var c = cat(S.selected); if (!c) return;
  var t = e.target;
  if (t.id === 'cat-name') { c.name = t.value.trim() || c.name; c.customName = c.name !== critName(c); }
  else if (t.id === 'cat-team') { snapshot(); c.team = t.checked; applyDefaultSystem(c); }
  else if (t.dataset.medals) { cfg(c).medals = t.value; setSystem(c, t.value); return; }
  else if (t.dataset.cfg) { snapshot(); var f0 = cfg(c); f0[t.dataset.cfg] = t.type === 'checkbox' ? t.checked : Math.max(1, parseInt(t.value, 10) || 1); if (f0.judges > 15) f0.judges = 15; }
  else if (t.dataset.crit) {
    snapshot(); c.crit = c.crit || {};
    var v = t.value.trim(); c.crit[t.dataset.crit] = t.dataset.crit === 'g' || t.dataset.crit === 'w' ? v : (+v || null);
  }
  else if (t.id === 'cat-merge-into' && t.value) {
    var into = cat(t.value); snapshot();
    c.competitors.forEach(function (p) { if (!into.competitors.some(function (x) { return x.id === p.id; })) into.competitors.push(p); });
    S.categories = S.categories.filter(function (x) { return x.id !== c.id; });
    S.selected = into.id;
  } else if (t.dataset.move && t.value) {
    var to = cat(t.value), p = person(c, t.dataset.move); snapshot();
    c.competitors = c.competitors.filter(function (x) { return x !== p; });
    if (!to.competitors.some(function (x) { return x.id === p.id; })) to.competitors.push(p);
  } else return;
  save(); renderSoon();
});
document.getElementById('cat-detail').addEventListener('click', function (e) {
  var c = cat(S.selected); if (!c) return;
  var t = e.target.closest('button') || e.target;
  if (t.id === 'cat-back') {   // atgal į struktūrą - prie tos pačios kategorijos, trumpai paryškinta
    setCatView('ov');
    var blk = document.querySelector('#ov-cols [data-ovb="' + c.id + '"]');
    if (blk) { blk.scrollIntoView({ block: 'center' }); blk.classList.add('flash'); setTimeout(function () { blk.classList.remove('flash'); }, 1600); }
    return;
  }
  if (t.dataset.remove) { snapshot(); c.competitors = c.competitors.filter(function (p) { return p.id !== t.dataset.remove; }); }
  else if (t.dataset.disc) { snapshot(); c.discipline = t.dataset.disc; delete cfg(c).time; cfg(c); applyDefaultSystem(c); }
  else if (t.dataset.sys) { setSystem(c, t.dataset.sys); return; }
  else if (t.id === 'cat-autoname') { snapshot(); c.name = critName(c); delete c.customName; }
  else if (t.id === 'cat-addfit') {
    snapshot(); Object.keys(S.people).forEach(function (id) { if (!person(c, id) && fits(c, S.people[id])) addToCat(id, c.id); });
  }
  else if (t.id === 'add-person') {
    var name = document.getElementById('new-name').value.trim(); if (!name) { document.getElementById('new-name').focus(); return; }
    snapshot();
    var np = { id: newId('c'), name: name, club: document.getElementById('new-club').value, birth: null, gender: null, belt: null, member: null };
    S.people[np.id] = np; addToCat(np.id, c.id);
  } else if (t.id === 'cat-split') {
    if (c.competitors.length < 2) return; snapshot();
    var half = Math.ceil(c.competitors.length / 2);
    var nc = { id: newId('k'), name: c.name + ' (2)', discipline: c.discipline, competitors: c.competitors.slice(half), tatami: c.tatami, draw: null, crit: JSON.parse(JSON.stringify(c.crit || {})), cfg: c.cfg };
    c.competitors = c.competitors.slice(0, half); c.name = c.name + ' (1)';
    S.categories.splice(S.categories.indexOf(c) + 1, 0, nc);
  } else if (t.id === 'cat-del') {
    if (c.competitors.length && !confirm('Ištrinti kategoriją „' + c.name + '“? Dalyviai liks varžybose.')) return;
    snapshot(); S.categories = S.categories.filter(function (x) { return x !== c; }); S.selected = S.categories[0] && S.categories[0].id;
  } else if (t.id === 'cat-draw') { openRun(S.selected); return; }
  else return;
  save(); render();
});

// ---------- Dalyvis - į tinkamas grupes (pakeitus gimimo metus, lytį ar diržą, pridėjus naują) ----------
// Jau esantis grupėse perkeliamas tos pačios rungties tinkamiausią grupę; be grupių - pridedamas į tinkamas visų rungčių grupes.
// Grupės, kuriose kovos jau vyksta, nekeičiamos.
function hasCrit(c) { var k = crit(c); return !!(k.g || k.y0 || k.y1 || k.b0 || k.b1); }
function catStarted(c) { return !!(c.draw && (Object.keys(c.draw.results || {}).length || (!isElim(c.draw.system) && scoreDone(c)))); }
function catSpan(c) { var k = crit(c); return ((k.y1 || YEAR) - (k.y0 || 1900)) * 10 + ((k.b1 || 20) - (k.b0 || 0)); }
function autoPlace(id) {
  var p = S.people[id]; if (!p || !p.birth || !p.gender) return [];   // be gimimo metų ir lyties - nespėliojama
  var mine = catsOf(id).filter(function (c) { return !c.team; }), out = [], discs = {};
  if (mine.length) mine.forEach(function (c) { discs[c.discipline] = true; });
  else S.categories.forEach(function (c) { if (!c.team && hasCrit(c) && fits(c, p) && (!p.discs || p.discs.indexOf(c.discipline) !== -1)) discs[c.discipline] = true; });
  Object.keys(discs).forEach(function (d) {
    var inD = mine.filter(function (c) { return c.discipline === d; });
    if (inD.some(function (c) { return fits(c, p); }) || inD.some(catStarted)) return;
    var target = S.categories.filter(function (c) { return !c.team && c.discipline === d && hasCrit(c) && fits(c, p) && !catStarted(c); })
      .sort(function (x, y) { return catSpan(x) - catSpan(y); })[0];
    if (!target) { if (inD.length) out.push('tinkamos ' + (DISC[d] || d) + ' grupės nėra - liko ' + inD.map(function (c) { return c.name; }).join(', ')); return; }
    inD.forEach(function (c) { removeFromCat(id, c.id); });
    addToCat(id, target.id);
    out.push((inD.length ? inD.map(function (c) { return c.name; }).join(', ') + ' → ' : '+ ') + target.name);
  });
  return out;
}
export function toastPlaced(ids) {
  var lines = [];
  ids.forEach(function (id) { autoPlace(id).forEach(function (x) { lines.push('<b>' + esc(S.people[id].name) + '</b>: ' + esc(x)); }); });
  if (lines.length) toast('Grupės pakeistos automatiškai (atšaukti - „Atšaukti“ Lentelėse):<br>' + lines.slice(0, 6).join('<br>') + (lines.length > 6 ? '<br>… ir dar ' + (lines.length - 6) : ''));
}

// ---------- Kategorijos automatiškai: rungtis × lytis × amžius × diržas, mažos sujungiamos su artimiausiomis ----------
var AGE_GROUPS = [[0, 7], [8, 9], [10, 11], [12, 13], [14, 15], [16, 17], [18, 34], [35, 99]];
var BELT_GROUPS = [[0, 3], [4, 6], [7, 99]];
var DISC_ORDER = { kihon: 0, kata: 1, kumite: 2, fukugo: 3 };
export function kyu(l) { return l <= 9 ? (10 - l) + ' kyu' : (l - 9) + ' dan'; }
function catName(b) {
  var g = b.gs.F && b.gs.M || b.gs.X ? 'Mix' : b.gs.F ? 'F' : 'M';
  var yMin = AGE_GROUPS[b.a0][0], yMax = AGE_GROUPS[b.a1][1];
  var ages = yMax >= 99 ? (YEAR - yMin) + ' m. ir vyr.' : yMin === 0 ? (YEAR - yMax) + ' m. ir jaun.' : (YEAR - yMin) + '–' + (YEAR - yMax) + ' m.';
  var lo = BELT_GROUPS[b.b0][0], hi = BELT_GROUPS[b.b1][1];
  var belts = hi >= 99 ? kyu(lo) + '+' : kyu(lo).replace(' kyu', '') + '–' + kyu(hi);
  if (b.b0 === 0 && b.b1 === BELT_GROUPS.length - 1) belts = 'visi diržai';
  return DISC[b.d] + ' ' + g + ' ' + ages + ' · ' + belts;
}
export function autoCategories() {
  var buckets = {};
  Object.keys(S.people).forEach(function (id) {
    var p = S.people[id]; if (!p.birth) return;
    var a = YEAR - p.birth, ai = AGE_GROUPS.findIndex(function (g) { return a >= g[0] && a <= g[1]; });
    if (ai < 0) return;
    var bl = hasBelt(p.belt) ? p.belt : 1, bi = BELT_GROUPS.findIndex(function (g) { return bl >= g[0] && bl <= g[1]; });
    var g = a < 8 ? 'X' : (p.gender === 'F' ? 'F' : 'M');
    var discs = a < 8 ? ['kihon', 'kata'] : ['kata', 'kumite'];
    if (a >= 16 && bl >= 7) discs.push('fukugo');
    if (p.discs && p.discs.length) discs = p.discs.slice();   // pats pasirinko registruodamasis
    discs.forEach(function (d) {
      var k = d + '|' + g + '|' + ai + '|' + bi, gs = {}; gs[g] = true;
      (buckets[k] = buckets[k] || { d: d, gs: gs, a0: ai, a1: ai, b0: bi, b1: bi, people: [] }).people.push(p);
    });
  });
  var list = Object.keys(buckets).map(function (k) { return buckets[k]; });
  function cost(x, y) {
    if (x.d !== y.d) return Infinity;
    var ga = Math.max(0, Math.max(x.a0, y.a0) - Math.min(x.a1, y.a1)), gb = Math.max(0, Math.max(x.b0, y.b0) - Math.min(x.b1, y.b1));
    var mixG = (x.gs.M && y.gs.F) || (x.gs.F && y.gs.M) ? 1 : 0;
    if (mixG && Math.min(x.a0, y.a0) >= 4) return Infinity;   // nuo 14 m. vaikinai ir merginos atskirai
    return ga * 3 + gb * 2 + mixG * 4 + (ga && gb ? 3 : 0);
  }
  // Kol yra kategorija su vienu dalyviu - jungiama su pigiausia (artimiausia) tos pačios rungties
  for (var guard = 0; guard < 500; guard++) {
    var small = list.filter(function (b) { return b.people.length < 2; }).sort(function (a, b) { return a.people.length - b.people.length; });
    var merged = false;
    for (var i = 0; i < small.length && !merged; i++) {
      var x = small[i], best = null, bc = Infinity;
      list.forEach(function (y) { if (y !== x) { var c = cost(x, y); if (c < bc || (c === bc && best && y.people.length < best.people.length)) { bc = c; best = y; } } });
      if (best && bc < 12) {
        best.a0 = Math.min(best.a0, x.a0); best.a1 = Math.max(best.a1, x.a1); best.b0 = Math.min(best.b0, x.b0); best.b1 = Math.max(best.b1, x.b1);
        Object.keys(x.gs).forEach(function (k) { best.gs[k] = true; });
        x.people.forEach(function (p) { if (best.people.indexOf(p) === -1) best.people.push(p); });
        list.splice(list.indexOf(x), 1);
        merged = true;
      }
    }
    if (!merged) break;
  }
  list.sort(function (a, b) { return a.a0 - b.a0 || DISC_ORDER[a.d] - DISC_ORDER[b.d] || a.b0 - b.b0 || (a.gs.F ? 1 : 0) - (b.gs.F ? 1 : 0); });
  var teams = S.categories.filter(function (c) { return c.team; });
  S.categories = list.map(function (b, i) {
    var lo = BELT_GROUPS[b.b0][0], hi = BELT_GROUPS[b.b1][1], yMin = AGE_GROUPS[b.a0][0], yMax = AGE_GROUPS[b.a1][1];
    var c = { id: 'k' + (i + 1) + Date.now().toString(36), name: catName(b), discipline: b.d, competitors: b.people.map(function (p) { return { id: p.id, name: p.name, club: p.club }; }), tatami: S.areas[0].id, draw: null,
      crit: { g: b.gs.F && b.gs.M || b.gs.X ? 'X' : b.gs.F ? 'F' : 'M', y0: yMax >= 99 ? null : YEAR - yMax, y1: yMin === 0 ? null : YEAR - yMin, b0: lo > 1 ? lo : null, b1: hi < 99 ? hi : null } };
    if (c.competitors.length >= 2) c.draw = makeDraw(c, defaultSystem(c));
    return c;
  }).concat(teams);
  teams.forEach(function (c) { if (c.competitors.length >= 2 && !c.draw) c.draw = makeDraw(c, defaultSystem(c)); });
  balance();
  S.selected = S.categories[0] && S.categories[0].id;
}
document.getElementById('cat-auto').addEventListener('click', function () {
  var played = S.categories.some(function (c) { return c.draw && Object.keys(c.draw.results).length; });
  if (!confirm((played ? 'Dalis kovų jau įvyko - jų rezultatai dings. ' : '') + 'Sudaryti kategorijas iš naujo pagal dalyvių amžių, lytį ir diržą? Komandinės kategorijos liks.')) return;
  snapshot(); autoCategories(); save(); render();
});
