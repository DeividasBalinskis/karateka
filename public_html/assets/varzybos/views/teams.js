// Komandos
import { esc, newId } from '../util.js';
import { cat, removeFromCat, S, save, snapshot } from '../store.js';
import { render, renderSoon } from '../chrome.js';

// ---------- Komandos (kaip Kihapp „Teams“) ----------
export function updateTeam(id, fields) {
  Object.assign(S.teams[id], fields);
  S.categories.forEach(function (c) { c.competitors.forEach(function (p) { if (p.id === id) Object.assign(p, { name: S.teams[id].name, club: S.teams[id].club }); }); });
}
var teamClub = '';
function teamCats() { return S.categories.filter(function (c) { return c.team; }); }
export function renderTeams() {
  if (teamClub !== null) teamClub = teamClub || S.clubs[0] || '';
  var tc = teamClub || '';   // '' - visi klubai (mišri komanda)
  document.getElementById('team-club').innerHTML = '<option value=""' + (tc === '' ? ' selected' : '') + '>Visi klubai (mišri komanda)</option>' +
    S.clubs.map(function (k) { return '<option' + (k === tc ? ' selected' : '') + '>' + esc(k) + '</option>'; }).join('');
  document.getElementById('team-cat').innerHTML = teamCats().map(function (c) { return '<option value="' + c.id + '">' + esc(c.name) + '</option>'; }).join('') || '<option value="">Nėra komandinių kategorijų</option>';
  var members = Object.keys(S.people).map(function (id) { return S.people[id]; }).filter(function (p) { return !tc || p.club === tc; })
    .sort(function (a, b) { return (tc ? 0 : a.club.localeCompare(b.club, 'lt')) || a.name.localeCompare(b.name, 'lt'); });
  document.getElementById('team-pick').innerHTML = '<h2>Pasirinkite narius (' + esc(tc || 'visi klubai') + ')<span class="spacer"></span><span class="muted small">Pažymėjimo tvarka = kovų tvarka</span></h2><div style="display:flex; flex-wrap:wrap; gap:6px 14px; padding:12px 14px;">' +
    (members.map(function (p) { return '<label class="small"><input type="checkbox" class="team-member" value="' + p.id + '"> ' + esc(p.name) + (tc ? '' : ' <span class="muted">· ' + esc(p.club) + '</span>') + '</label>'; }).join('') || '<span class="muted">Šiame klube dalyvių nėra.</span>') + '</div>';
  var list = Object.keys(S.teams).map(function (id) { return S.teams[id]; }).sort(function (a, b) { return a.club.localeCompare(b.club, 'lt') || a.name.localeCompare(b.name, 'lt'); });
  var catOpts = teamCats().map(function (c) { return '<option value="' + c.id + '">' + esc(c.name) + '</option>'; }).join('');
  document.getElementById('teams-view').innerHTML = '<h2>Komandos (' + list.length + ')</h2><table><thead><tr><th>Pavadinimas</th><th>Klubas</th><th>Nariai</th><th>Kategorijos</th><th></th></tr></thead><tbody>' +
    list.map(function (t) {
      var cats = S.categories.filter(function (c) { return c.competitors.some(function (x) { return x.id === t.id; }); });
      return '<tr><td><input class="inline" value="' + esc(t.name) + '" data-t-name="' + t.id + '" aria-label="Komandos pavadinimas"></td><td>' + esc(t.club) + '</td>' +
        '<td>' + t.members.map(function (m) { return '<span class="cchip">' + esc(S.people[m] ? S.people[m].name : '?') + '</span>'; }).join('') + '</td>' +
        '<td>' + cats.map(function (c) { return '<span class="cchip">' + esc(c.name) + '<button type="button" data-t-rm="' + t.id + '" data-c="' + c.id + '" aria-label="Išimti">×</button></span>'; }).join('') +
        '<select class="sel" data-t-add="' + t.id + '" aria-label="Pridėti į kategoriją"><option value="">+ kategorija…</option>' + catOpts + '</select></td>' +
        '<td><button class="btn btn-danger" type="button" data-t-del="' + t.id + '">Ištrinti</button></td></tr>';
    }).join('') + '</tbody></table>';
}
document.getElementById('team-club').addEventListener('change', function (e) { teamClub = e.target.value === '' ? null : e.target.value; renderTeams(); });
// Pažymėjimo tvarka išsaugoma (komandiniame kumite - kas kovoja pirmas, antras, trečias)
var teamPickOrder = [];
document.getElementById('team-pick').addEventListener('change', function (e) {
  if (!e.target.classList.contains('team-member')) return;
  teamPickOrder = teamPickOrder.filter(function (x) { return x !== e.target.value; });
  if (e.target.checked) teamPickOrder.push(e.target.value);
});
document.getElementById('team-add').addEventListener('click', function () {
  var checked = [].slice.call(document.querySelectorAll('.team-member:checked')).map(function (x) { return x.value; });
  var ids = teamPickOrder.filter(function (x) { return checked.indexOf(x) !== -1; }).concat(checked.filter(function (x) { return teamPickOrder.indexOf(x) === -1; }));
  if (ids.length < 2) { alertBox('Pažymėkite bent 2 komandos narius.'); return; }
  snapshot();
  // Mišri komanda - klubų pavadinimai per „+“ (medalių lentelėje - kaip atskiras klubas)
  var clubs = []; ids.forEach(function (m) { if (clubs.indexOf(S.people[m].club) === -1) clubs.push(S.people[m].club); });
  var club = clubs.join(' + ');
  var id = newId('t'), name = document.getElementById('team-name').value.trim() || (clubs.length > 1 ? 'Mišri' : club) + ' (' + ids.map(function (m) { return S.people[m].name.split(' ')[0]; }).join(', ') + ')';
  S.teams[id] = { id: id, name: name, club: club, members: ids };
  teamPickOrder = [];
  var catId = document.getElementById('team-cat').value, c = cat(catId);
  if (c) c.competitors.push({ id: id, name: name, club: club, team: true });
  document.getElementById('team-name').value = '';
  save(); render();
});
document.getElementById('team-cat-new').addEventListener('click', function () {
  snapshot(); var id = 'k' + Date.now();
  S.categories.push({ id: id, name: 'Komandos (nauja kategorija)', discipline: 'kumite', team: true, competitors: [], tatami: S.areas[0].id, draw: null });
  save(); render();
});
document.getElementById('teams-view').addEventListener('change', function (e) {
  var t = e.target;
  if (t.dataset.tName && t.value.trim()) { snapshot(); updateTeam(t.dataset.tName, { name: t.value.trim() }); }
  else if (t.dataset.tAdd && t.value) { var c = cat(t.value), tm = S.teams[t.dataset.tAdd]; snapshot(); if (!c.competitors.some(function (x) { return x.id === tm.id; })) c.competitors.push({ id: tm.id, name: tm.name, club: tm.club, team: true }); }
  else return;
  save(); renderSoon();
});
document.getElementById('teams-view').addEventListener('click', function (e) {
  var t = e.target;
  if (t.dataset.tRm) { snapshot(); removeFromCat(t.dataset.tRm, t.dataset.c); }
  else if (t.dataset.tDel) { snapshot(); S.categories.forEach(function (c) { c.competitors = c.competitors.filter(function (x) { return x.id !== t.dataset.tDel; }); }); delete S.teams[t.dataset.tDel]; }
  else return;
  save(); render();
});
function alertBox(msg) { var el = document.getElementById('team-pick'); var p = document.createElement('p'); p.className = 'pill warn'; p.style.margin = '0 14px 12px'; p.textContent = msg; el.appendChild(p); }
