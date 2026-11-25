// Budo varžybų valdymas (admin/varzybu-sistema.php): pradžia. Būsena S - iš serverio (window.VARZ.state), pakeitimai išsaugomi automatiškai.
// Moduliai (D3) - žr. docs/handoff.md. Kraunamas modulis neskaito kito modulio būsenos (tik iškviečia funkcijas vėliau),
// todėl modulių krovimo tvarka nesvarbi; viskas pradedama čia.
import { DISC } from './rules.js';
import { addToCat, load, markSaved, restorePending, S, save, stateObj } from './store.js';
import { openStartTab, render } from './chrome.js';
import { autoCategories, critName, newCatIn } from './views/categories.js';
import { simulate } from './test.js';
import { beep } from './bout.js';
import { deskFromLink } from './views/desk.js';
// visi moduliai (jų mygtukai ir įvykiai), net jei main.js jų funkcijų nenaudoja
import './views/clubs.js';
import './views/people.js';
import './views/teams.js';
import './views/draw.js';
import './schedule.js';
import './results.js';
import './views/live.js';
import './views/draws.js';
import './views/results.js';

load(); markSaved(stateObj());
restorePending();
// Naujos test varžybos: kategorijos sudaromos ir dalis kovų „sužaidžiama“ pirmą kartą atidarius
if (S.autoCats) { delete S.autoCats; autoCategories(); save(); }
// Greitos varžybos: kuriant pažymėta „po vieną grupę visiems“ - grupė kiekvienai rungčiai su visais dalyviais
if (Array.isArray(S.quickCats)) {
  var qc = S.quickCats; delete S.quickCats;
  qc.forEach(function (d) { if (!DISC[d]) return; var c = newCatIn(d, null); Object.keys(S.people).forEach(function (id) { addToCat(id, c.id); }); c.name = critName(c); });
  save(); render();
}
if (S.simulate) { var fr = +S.simulate; delete S.simulate; simulate(fr); save(); }
deskFromLink();
openStartTab();

// Iššokantys langai (pvz. „⚙ Tatamiai ir kovų trukmė“) visada telpa ekrane - anksčiau atsidarydavo už kairio krašto ir „+ Tatamis“ nebuvo pasiekiamas
document.addEventListener('toggle', function (e) {
  var d = e.target; if (!d.matches || !d.matches('details.live-settings') || !d.open) return;
  var box = d.querySelector('.toolbar'); if (!box) return;
  box.style.transform = ''; box.style.right = '0px'; box.style.left = 'auto';
  var r = box.getBoundingClientRect(), vw = document.documentElement.clientWidth, dx = 0;
  if (r.left < 12) dx = 12 - r.left; else if (r.right > vw - 12) dx = vw - 12 - r.right;
  if (dx) box.style.transform = 'translateX(' + Math.round(dx) + 'px)';
}, true);
document.addEventListener('change', function (e) {
  if (e.target && e.target.id === 'bt-gong') { try { localStorage.setItem('budo-gong', e.target.value); } catch (x) {} beep(e.target.value); }
});
