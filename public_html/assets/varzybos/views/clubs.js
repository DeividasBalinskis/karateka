// Klubai: sąrašas ir kontaktai
import { CFG, esc } from '../util.js';
import { dirty, flush, S, save, saving, snapshot, updatePerson } from '../store.js';
import { render, renderSoon, showTab } from '../chrome.js';
import { setPplClub } from './people.js';
import { updateTeam } from './teams.js';

// ---------- Klubai (kaip Kihapp „Schools“) ----------
function clubStats() {
  var st = {};
  S.clubs.forEach(function (k) { st[k] = { people: 0, entries: 0, teams: 0 }; });
  Object.keys(S.teams).forEach(function (id) { var k = S.teams[id].club; st[k] = st[k] || { people: 0, entries: 0, teams: 0 }; st[k].teams++; });
  Object.keys(S.people).forEach(function (id) { var p = S.people[id]; st[p.club] = st[p.club] || { people: 0, entries: 0, teams: 0 }; st[p.club].people++; });
  S.categories.forEach(function (c) { c.competitors.forEach(function (p) { if (p.team) return; st[p.club] = st[p.club] || { people: 0, entries: 0, teams: 0 }; st[p.club].entries++; }); });
  return st;
}
export function renderClubs() {
  var st = clubStats(), names = Object.keys(st).sort(function (a, b) { return a.localeCompare(b, 'lt'); }), tp = 0, te = 0, tt = 0;
  document.getElementById('clubs-view').innerHTML = '<table><thead><tr><th>Klubas</th><th class="num">Dalyviai</th><th class="num">Kategorijų įrašai</th><th class="num">Komandos</th><th></th></tr></thead><tbody>' +
    names.map(function (k) {
      tp += st[k].people; te += st[k].entries; tt += st[k].teams;
      return '<tr><td><input class="inline" value="' + esc(k) + '" data-club-rename="' + esc(k) + '" aria-label="Klubo pavadinimas"></td><td class="num">' + st[k].people + '</td><td class="num">' + st[k].entries + '</td><td class="num">' + st[k].teams + '</td>' +
        '<td class="club-btns"><button class="btn' + (openContacts === k ? ' on' : '') + '" type="button" data-club-contacts="' + esc(k) + '" aria-expanded="' + (openContacts === k) + '">☎ Kontaktai</button> <button class="btn" type="button" data-club-show="' + esc(k) + '">Dalyviai →</button>' + (st[k].people ? '' : ' <button class="btn btn-danger" type="button" data-club-del="' + esc(k) + '">Pašalinti</button>') + '</td></tr>' +
        (openContacts === k ? clubContactsRow(k) : '');
    }).join('') + '</tbody><tfoot><tr><td>Iš viso</td><td class="num">' + tp + '</td><td class="num">' + te + '</td><td class="num">' + tt + '</td><td></td></tr></tfoot></table>';
}
// Klubo kontaktai: atsakingas asmuo, telefonas, el. paštas, pastaba (saugoma varžybose); LTKF sąrašo duomenys - kaip pradžia;
// žemiau - telefonai, kuriuos klubas nurodė registruodamasis per nuorodą
var openContacts = '';
function clubContactsRow(k) {
  var info = S.clubInfo[k] || {}, dir = (CFG.clubDirectory || {})[k] || {}, regs = (CFG.clubContacts || {})[k] || [];
  var field = function (f, label, type) {
    var v = info[f] !== undefined ? info[f] : (dir[f] || '');
    var link = v && f === 'phone' ? ' <a class="small" href="tel:' + esc(v.replace(/[^\d+]/g, '')) + '">skambinti</a>' : v && f === 'email' ? ' <a class="small" href="mailto:' + esc(v) + '">rašyti</a>' : '';
    return '<label class="ci-field"><span>' + label + link + '</span><input class="sel" type="' + type + '" value="' + esc(v) + '" data-ci-club="' + esc(k) + '" data-ci-field="' + f + '"' + (CFG.readOnly ? ' readonly' : '') + '></label>';
  };
  return '<tr class="club-contacts"><td colspan="5"><div class="ci-grid">' + field('person', 'Atsakingas asmuo (treneris)', 'text') + field('phone', 'Telefonas', 'tel') + field('email', 'El. paštas', 'email') + field('note', 'Pastaba', 'text') + '</div>' +
    (regs.length ? '<div class="ci-regs small"><b>Registravosi per nuorodą:</b> ' + regs.map(function (r) { return '<a href="tel:' + esc(String(r.phone).replace(/[^\d+]/g, '')) + '">' + esc(r.phone) + '</a> <span class="muted">(' + r.n + ' dal.)</span>'; }).join(', ') + '</div>'
      : '<div class="ci-regs small muted">Per registracijos nuorodą šis klubas dar nesiregistravo.</div>') + '</td></tr>';
}
document.getElementById('clubs-view').addEventListener('input', function (e) {
  var k = e.target.dataset.ciClub, f = e.target.dataset.ciField; if (!k || !f) return;
  S.clubInfo[k] = S.clubInfo[k] || {};
  S.clubInfo[k][f] = e.target.value.trim();
  save();
});
// Kontaktų Excel - pirmiausia išsaugoma, kad faile būtų ką tik įrašyti kontaktai
document.getElementById('clubs-xlsx').addEventListener('click', function (e) {
  if (!dirty && !saving) return;
  e.preventDefault(); var href = this.href; flush();
  var wait = setInterval(function () { if (!dirty && !saving) { clearInterval(wait); location.href = href; } }, 200);
  setTimeout(function () { clearInterval(wait); location.href = href; }, 4000);
});
document.getElementById('club-add').addEventListener('click', function () {
  var inp = document.getElementById('club-new-name'), n = inp.value.trim(); if (!n) { inp.focus(); return; }
  if (S.clubs.indexOf(n) === -1) { snapshot(); S.clubs.push(n); save(); }
  inp.value = ''; render();
});
document.getElementById('clubs-view').addEventListener('change', function (e) {
  var old = e.target.dataset.clubRename, n = e.target.value.trim(); if (!old || !n || n === old) return;
  snapshot();
  if (S.clubInfo[old]) { S.clubInfo[n] = S.clubInfo[old]; delete S.clubInfo[old]; }
  if (openContacts === old) openContacts = n;
  S.clubs = S.clubs.map(function (k) { return k === old ? n : k; }); if (S.clubs.indexOf(n) === -1) S.clubs.push(n);
  Object.keys(S.people).forEach(function (id) { if (S.people[id].club === old) updatePerson(id, { club: n }); });
  Object.keys(S.teams).forEach(function (id) { if (S.teams[id].club === old) updateTeam(id, { club: n }); });
  save(); renderSoon();
});
document.getElementById('clubs-view').addEventListener('click', function (e) {
  if (e.target.dataset.clubContacts !== undefined) { openContacts = openContacts === e.target.dataset.clubContacts ? '' : e.target.dataset.clubContacts; renderClubs(); }
  else if (e.target.dataset.clubShow) { setPplClub(e.target.dataset.clubShow); showTab('dalyviai'); }
  else if (e.target.dataset.clubDel) { snapshot(); S.clubs = S.clubs.filter(function (k) { return k !== e.target.dataset.clubDel; }); save(); render(); }
});
