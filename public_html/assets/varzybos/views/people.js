// Dalyviai: sąrašas, nauji, Karateka nariai, užsiregistravę per nuorodą
import { beltDot, beltName, beltOpts, CFG, esc, hasBelt, HOME, newId, YEAR } from '../util.js';
import { DISC } from '../rules.js';
import { addToCat, catsOf, removeFromCat, S, save, setStatus, snapshot, updatePerson } from '../store.js';
import { render, renderSoon } from '../chrome.js';
import { INDIV, toastPlaced } from './categories.js';
import { testButtons } from '../test.js';

var pplClub = '';
export function setPplClub(v) { pplClub = v; }

// ---------- Dalyviai (kaip Kihapp „Individuals“) ----------
export function renderPeople() {
  testButtons();
  var clubSel = document.getElementById('ppl-club'), newClub = document.getElementById('ppl-new-club');
  clubSel.innerHTML = '<option value="">Visi klubai</option>' + S.clubs.map(function (k) { return '<option' + (k === pplClub ? ' selected' : '') + '>' + esc(k) + '</option>'; }).join('');
  newClub.innerHTML = S.clubs.map(function (k) { return '<option' + (k === pplClub ? ' selected' : '') + '>' + esc(k) + '</option>'; }).join('');
  var q = document.getElementById('ppl-search').value.trim().toLowerCase(), noCat = document.getElementById('ppl-nocat').checked;
  var list = Object.keys(S.people).map(function (id) { return S.people[id]; })
    .filter(function (p) { return (!q || p.name.toLowerCase().indexOf(q) !== -1) && (!pplClub || p.club === pplClub); })
    .map(function (p) { return { p: p, cats: catsOf(p.id) }; })
    .filter(function (x) { return !noCat || !x.cats.length; })
    .sort(function (a, b) { return a.p.name.localeCompare(b.p.name, 'lt'); });
  var catOpts = S.categories.map(function (c) { return '<option value="' + c.id + '">' + esc(c.name) + '</option>'; }).join('');
  var clubOpts = function (cur) { return S.clubs.map(function (k) { return '<option' + (k === cur ? ' selected' : '') + '>' + esc(k) + '</option>'; }).join(''); };
  var noneCount = Object.keys(S.people).filter(function (id) { return !catsOf(id).length; }).length;
  document.getElementById('people-view').innerHTML = findStrip(q) +
    '<h2>Dalyviai (' + list.length + ')' + (noneCount ? ' <span class="pill warn">' + noneCount + ' be kategorijos</span>' : '') + '</h2>' +
    '<div class="table-scroll"><table class="ppl-table"><thead><tr><th>Vardas, pavardė</th><th>Klubas</th><th class="num">Gim. metai</th><th>Lytis</th><th>Diržas</th><th>Kategorijos</th><th class="num">Įrašai</th><th></th></tr></thead><tbody>' +
    list.map(function (x) {
      var p = x.p;
      return '<tr' + (x.cats.length ? '' : ' class="warn-row"') + '><td><div class="name-cell">' + (p.member ? '<span class="home-mark" title="Karateka narys">K</span>' : '') + '<input class="inline" value="' + esc(p.name) + '" data-p-name="' + p.id + '" aria-label="Vardas">' + (p.noPhoto ? '<span class="nophoto" title="Nesutinka būti fotografuojamas ir filmuojamas">be nuotraukų</span>' : '') + '</div></td>' +
        '<td data-label="Klubas"><select class="sel" data-p-club="' + p.id + '" aria-label="Klubas">' + clubOpts(p.club) + '</select></td>' +
        '<td class="num" data-label="Gim. metai"><input class="inline num-in" type="number" min="1940" max="' + YEAR + '" value="' + (p.birth || '') + '" data-p-birth="' + p.id + '" aria-label="Gimimo metai"></td>' +
        '<td data-label="Lytis"><select class="sel" data-p-gender="' + p.id + '" aria-label="Lytis"><option value="">—</option><option value="M"' + (p.gender === 'M' ? ' selected' : '') + '>M</option><option value="F"' + (p.gender === 'F' ? ' selected' : '') + '>F</option></select></td>' +
        '<td class="belt-cell" data-label="Diržas">' + beltDot(p.belt) + '<select class="sel" data-p-belt="' + p.id + '" aria-label="Diržas">' + beltOpts(p.belt) + '</select></td>' +
        '<td class="cats-cell" data-label="Kategorijos">' + x.cats.map(function (c) { return '<span class="cchip"><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + '<button type="button" data-p-rm="' + p.id + '" data-c="' + c.id + '" aria-label="Išimti iš ' + esc(c.name) + '">×</button></span>'; }).join('') +
        '<select class="sel add-cat" data-p-add="' + p.id + '" aria-label="Pridėti į kategoriją"><option value="">+ kategorija…</option>' + catOpts + '</select></td>' +
        '<td class="num cnt-cell">' + x.cats.length + '</td><td class="del-cell"><button class="btn btn-danger btn-xs" type="button" data-p-del="' + p.id + '" aria-label="Ištrinti ' + esc(p.name) + '">×</button></td></tr>';
    }).join('') + '</tbody></table></div>' + (list.length ? '' : '<p class="muted" style="padding:12px 14px;">' + (q || pplClub || noCat ? 'Pagal filtrą dalyvių nėra.' : 'Dalyvių dar nėra - įrašykite vardą paieškoje ar „Naujas“ laukelyje arba spauskite „+ Karateka nariai“.') + '</p>');
}
// Paieška ir kartu pridėjimas: randami dar nepridėti klubo nariai (vienu paspaudimu) arba pridedamas naujas žmogus tokiu vardu
function findStrip(q) {
  if (!q || CFG.readOnly) return '';
  var mem = (CFG.members || []).filter(function (m) { return !S.people['m' + m.id] && m.name.toLowerCase().indexOf(q) !== -1; }).slice(0, 12);
  var raw = document.getElementById('ppl-search').value.trim(), exists = Object.keys(S.people).some(function (id) { return S.people[id].name.toLowerCase() === q; });
  return '<div class="find-strip">' + (mem.length ? '<span class="small muted">Klubo nariai:</span>' + mem.map(function (m) {
    return '<button type="button" class="btn btn-sm" data-find-member="' + m.id + '">+ ' + esc(m.name) + (m.birth ? ' <span class="muted">' + m.birth + '</span>' : '') + '</button>'; }).join('') : '') +
    (!exists && raw.length > 2 ? '<button type="button" class="btn btn-sm btn-ghost" data-find-new="1">+ Pridėti naują: <b>' + esc(raw) + '</b></button>' : '') + '</div>';
}
function addMemberPeople(ids) {
  if (S.clubs.indexOf(HOME) === -1) S.clubs.unshift(HOME);
  (CFG.members || []).forEach(function (m) {
    if (ids.indexOf(m.id) === -1 || S.people['m' + m.id]) return;
    S.people['m' + m.id] = { id: 'm' + m.id, name: m.name, club: HOME, birth: m.birth, gender: m.gender, belt: m.belt, member: m.id, noPhoto: m.photo === 0 };
  });
}
function addNewPeople(names, club) {
  var added = [];
  names.forEach(function (n) {
    n = n.replace(/\s+/g, ' ').trim(); if (!n) return;
    if (Object.keys(S.people).some(function (id) { return S.people[id].name.toLowerCase() === n.toLowerCase(); })) return;
    var id = newId('c'); S.people[id] = { id: id, name: n, club: club || HOME, birth: null, gender: null, belt: null, member: null }; added.push(id);
  });
  if (club && S.clubs.indexOf(club) === -1) S.clubs.push(club);
  return added;
}
document.getElementById('people-view').addEventListener('click', function (e) {
  var fm = e.target.closest('[data-find-member]'), fn = e.target.closest('[data-find-new]');
  if (!fm && !fn) return;
  snapshot();
  if (fm) { addMemberPeople([+fm.dataset.findMember]); toastPlaced(['m' + fm.dataset.findMember]); }
  else { addNewPeople([document.getElementById('ppl-search').value], document.getElementById('ppl-new-club').value || HOME); }
  var s = document.getElementById('ppl-search'); s.value = ''; save(); render(); s.focus();
});
['ppl-search', 'ppl-nocat'].forEach(function (id) { document.getElementById(id).addEventListener('input', function () { renderPeople(); }); });
document.getElementById('ppl-club').addEventListener('change', function (e) { pplClub = e.target.value; renderPeople(); });
document.getElementById('ppl-add').addEventListener('click', function () {
  var inp = document.getElementById('ppl-new-name'), n = inp.value.trim(); if (!n) { inp.focus(); return; }
  snapshot(); addNewPeople(n.split(/\n|;/), document.getElementById('ppl-new-club').value);
  inp.value = ''; save(); render(); inp.focus();
});
// Enter - pridėti iškart; įklijavus kelias eilutes - pridedami visi
document.getElementById('ppl-new-name').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('ppl-add').click(); } });
document.getElementById('ppl-new-name').addEventListener('paste', function (e) {
  var tx = (e.clipboardData || window.clipboardData).getData('text'); if (!/\n/.test(tx)) return;
  e.preventDefault(); snapshot(); var n = addNewPeople(tx.split(/\n/), document.getElementById('ppl-new-club').value).length;
  save(); render(); if (n) setStatus('Pridėta dalyvių: ' + n, 'ok');
});
document.getElementById('ppl-search').addEventListener('keydown', function (e) {
  if (e.key !== 'Enter') return; e.preventDefault();
  var b = document.querySelector('#people-view [data-find-member], #people-view [data-find-new]'); if (b) b.click();
});
document.getElementById('people-view').addEventListener('change', function (e) {
  var t = e.target;
  if (t.dataset.pName && t.value.trim()) { snapshot(); updatePerson(t.dataset.pName, { name: t.value.trim() }); }
  else if (t.dataset.pClub) { snapshot(); updatePerson(t.dataset.pClub, { club: t.value }); }
  else if (t.dataset.pBirth) { snapshot(); S.people[t.dataset.pBirth].birth = +t.value || null; toastPlaced([t.dataset.pBirth]); }
  else if (t.dataset.pGender) { snapshot(); S.people[t.dataset.pGender].gender = t.value || null; toastPlaced([t.dataset.pGender]); }
  else if (t.dataset.pBelt) { snapshot(); S.people[t.dataset.pBelt].belt = t.value === '' ? null : +t.value; toastPlaced([t.dataset.pBelt]); }
  else if (t.dataset.pAdd && t.value) { snapshot(); addToCat(t.dataset.pAdd, t.value); }
  else return;
  save(); renderSoon();
});
document.getElementById('people-view').addEventListener('click', function (e) {
  var t = e.target;
  if (t.dataset.pDel) {
    var p = S.people[t.dataset.pDel];
    if (!p || !confirm('Ištrinti dalyvį ' + p.name + ' iš varžybų (ir visų kategorijų)?')) return;
    snapshot();
    S.categories.forEach(function (c) { c.competitors = c.competitors.filter(function (x) { return x.id !== p.id; }); });
    Object.keys(S.teams).forEach(function (k) { S.teams[k].members = S.teams[k].members.filter(function (m) { return m !== p.id; }); });
    delete S.people[p.id];
    save(); render(); return;
  }
  if (!t.dataset.pRm) return;
  snapshot(); removeFromCat(t.dataset.pRm, t.dataset.c); save(); render();
});

// ---------- Karateka nariai į varžybas ----------
var pickGroup = '';
function renderMembersPick() {
  var box = document.getElementById('members-pick'); if (box.hidden) return;
  var members = CFG.members || [], q = (document.getElementById('mp-search') || {}).value || '';
  var groups = []; members.forEach(function (m) { if (m.group && groups.indexOf(m.group) === -1) groups.push(m.group); });
  var shown = members.filter(function (m) { return (!pickGroup || m.group === pickGroup) && (!q || m.name.toLowerCase().indexOf(q.toLowerCase()) !== -1); });
  var free = shown.filter(function (m) { return !S.people['m' + m.id]; });
  box.querySelector('.mp-groups').innerHTML = '<button type="button" class="fchip" data-g="" aria-pressed="' + (!pickGroup) + '">Visi (' + members.length + ')</button>' +
    groups.map(function (g) { return '<button type="button" class="fchip" data-g="' + esc(g) + '" aria-pressed="' + (g === pickGroup) + '">' + esc(g) + '</button>'; }).join('');
  box.querySelector('.mp-list').innerHTML = shown.map(function (m) {
    var added = !!S.people['m' + m.id];
    return '<label class="mp-item' + (added ? ' added' : '') + '"><input type="checkbox" value="' + m.id + '"' + (added ? ' checked disabled' : '') + '>' +
      beltDot(m.belt) + '<span class="mp-name">' + esc(m.name) + '</span><span class="mp-meta">' + (m.birth || '?') + ' · ' + (m.gender || '?') + (hasBelt(m.belt) ? ' · ' + esc(beltName(m.belt)) : '') + (added ? ' · jau pridėtas' : '') + '</span></label>';
  }).join('') || '<p class="muted small">Narių nerasta.</p>';
  box.querySelector('#mp-all').textContent = 'Pažymėti visus (' + free.length + ')';
}
document.getElementById('ppl-members').addEventListener('click', function () {
  var box = document.getElementById('members-pick'); box.hidden = !box.hidden; renderMembersPick();
  if (!box.hidden) document.getElementById('mp-search').focus();
});
document.getElementById('members-pick').addEventListener('click', function (e) {
  var t = e.target;
  if (t.dataset && t.dataset.g !== undefined && t.classList.contains('fchip')) { pickGroup = t.dataset.g; renderMembersPick(); }
  else if (t.id === 'mp-all') { this.querySelectorAll('.mp-list input:not(:disabled)').forEach(function (i) { i.checked = true; }); }
  else if (t.id === 'mp-close') { this.hidden = true; }
  else if (t.id === 'mp-add') {
    var ids = [].slice.call(this.querySelectorAll('.mp-list input:checked:not(:disabled)')).map(function (i) { return +i.value; });
    if (!ids.length) return;
    snapshot();
    addMemberPeople(ids);
    toastPlaced(ids.map(function (x) { return 'm' + x; }));
    save(); render(); renderMembersPick();
  }
});
document.getElementById('members-pick').addEventListener('input', function (e) { if (e.target.id === 'mp-search') renderMembersPick(); });
// Užsiregistravę per nuorodą: Denis peržiūri ir prideda (arba atmeta)
var entries = CFG.entries || [];
function entryPersonId(en) { return 'r' + en.id; }
function entryDiscs(en) { return String(en.disciplines || '').split(',').filter(function (d) { return INDIV.indexOf(d) !== -1; }); }
export function renderEntries() {
  var box = document.getElementById('entries-box'); if (!box) return;
  box.hidden = !entries.length && !CFG.regUrl;
  if (box.hidden) return;
  box.classList.toggle('compact', !entries.length);
  if (!entries.length) {
    box.innerHTML = '<div class="en-empty"><span><b>Užsiregistravę per nuorodą:</b> <span class="muted">naujų nėra</span></span><button type="button" class="btn btn-sm" id="en-refresh" title="Patikrinti, ar yra naujų">↻ Atnaujinti</button></div>';
    return;
  }
  var byClub = {};
  entries.forEach(function (en) { (byClub[en.club] = byClub[en.club] || []).push(en); });
  box.innerHTML = '<h2>Užsiregistravę per nuorodą' + (entries.length ? ' <span class="pill warn">' + entries.length + ' nauji</span>' : '') + '<span class="spacer"></span>' +
    '<button type="button" class="btn" id="en-refresh" title="Patikrinti, ar yra naujų">↻ Atnaujinti</button>' + (entries.length ? '<button type="button" class="btn btn-primary" id="en-add-all">Pridėti visus (' + entries.length + ')</button>' : '') + '</h2>' +
    (entries.length ? '<div class="table-scroll"><table><thead><tr><th>Klubas</th><th>Vardas, pavardė</th><th class="num">Gim.</th><th>Lytis</th><th>Diržas</th><th>Rungtys</th><th>Nuotraukos</th><th>Tėvų tel.</th><th>Kontaktas</th><th></th></tr></thead><tbody>' +
      Object.keys(byClub).sort().map(function (k) {
        return byClub[k].map(function (en) {
          return '<tr><td>' + esc(en.club) + '</td><td><b>' + esc(en.name) + '</b></td><td class="num">' + (en.birth_year || '') + '</td><td>' + esc(en.gender || '') + '</td><td>' + beltDot(+en.belt) + esc(beltName(+en.belt)) + '</td>' +
            '<td class="small">' + esc(entryDiscs(en).map(function (d) { return DISC[d] || d; }).join(', ')) + '</td>' +
            '<td>' + (en.photo_ok === null || en.photo_ok === undefined ? '<span class="muted">?</span>' : +en.photo_ok ? '✓ sutinka' : '<span class="nophoto">be nuotraukų</span>') + '</td>' +
            '<td class="small">' + esc(en.parent_phone || '') + '</td><td class="small">' + esc(en.contact || '') + '</td>' +
            '<td><button type="button" class="btn btn-xs" data-en-add="' + en.id + '">Pridėti</button> <button type="button" class="btn btn-danger btn-xs" data-en-rej="' + en.id + '" title="Atmesti">✕</button></td></tr>';
        }).join('');
      }).join('') + '</tbody></table></div>'
      : '<p class="muted small" style="padding:10px 14px; margin:0;">Naujų nėra. Registracijos nuorodą rasite „📡 Transliacija ir registracija“.</p>');
}
function markEntries(ids, status) {
  fetch(CFG.entriesApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf }, body: JSON.stringify({ ids: ids, status: status }) }).catch(function () {});
  entries = entries.filter(function (en) { return ids.indexOf(+en.id) === -1; });
}
function addEntries(list) {
  if (!list.length) return;
  snapshot();
  list.forEach(function (en) {
    if (S.clubs.indexOf(en.club) === -1) S.clubs.push(en.club);
    // Tas pats žmogus (vardas, gimimo metai, klubas) nepridedamas antrą kartą
    var dup = Object.keys(S.people).some(function (id) { var p = S.people[id]; return p.name === en.name && p.club === en.club && (+p.birth || 0) === (+en.birth_year || 0); });
    if (!dup) S.people[entryPersonId(en)] = { id: entryPersonId(en), name: en.name, club: en.club, birth: +en.birth_year || null, gender: en.gender || null, belt: en.belt === null || en.belt === undefined || en.belt === '' ? null : +en.belt, member: null,
      noPhoto: en.photo_ok !== null && en.photo_ok !== undefined && !+en.photo_ok };
    if (!dup && entryDiscs(en).length) S.people[entryPersonId(en)].discs = entryDiscs(en);   // registruojantis pasirinktos rungtys
  });
  markEntries(list.map(function (en) { return +en.id; }), 'added');
  toastPlaced(list.map(entryPersonId).filter(function (id) { return S.people[id]; }));
  save(); render();
}
document.getElementById('entries-box').addEventListener('click', function (e) {
  var t = e.target.closest('button'); if (!t) return;
  if (t.id === 'en-refresh') {
    fetch(CFG.entriesApi, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); })
      .then(function (d) { entries = d.entries || []; renderEntries(); }).catch(function () {});
  } else if (t.id === 'en-add-all') addEntries(entries.slice());
  else if (t.dataset.enAdd) addEntries(entries.filter(function (en) { return +en.id === +t.dataset.enAdd; }));
  else if (t.dataset.enRej) { markEntries([+t.dataset.enRej], 'rejected'); renderEntries(); }
});
