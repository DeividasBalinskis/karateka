// Budo: bendri dalykai - nustatymai iš serverio (window.VARZ), HTML, diržai, laikas, pranešimas ekrane (toast)
export var CFG = window.VARZ || {};
export var KEY = 'varzybos-' + (CFG.id || 0);
export var HOME = CFG.homeClub || 'Karateka';
export var BELTS = CFG.belts || {};
export var POPUP = CFG.popup || null;   // atkabinta rungtis atskirame lange (jos id)
export function esc(s) { return String(s).replace(/[&<>"]/g, function (ch) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]; }); }
export function hasBelt(l) { return l !== null && l !== undefined && l !== '' && !!BELTS[l]; }   // 0 = 10 kyu (ne „nėra“)
export function beltDot(l) { return hasBelt(l) ? '<span class="belt-dot" style="background:' + BELTS[l][2] + '" title="' + esc(BELTS[l][0]) + '"></span>' : ''; }
export function beltName(l) { return hasBelt(l) ? BELTS[l][0] : ''; }
export function beltOpts(cur) { return '<option value="">—</option>' + Object.keys(BELTS).map(function (k) { return '<option value="' + k + '"' + (cur !== null && cur !== undefined && cur !== '' && +k === +cur ? ' selected' : '') + '>' + esc(BELTS[k][0]) + '</option>'; }).join(''); }
export function newId(prefix) { return prefix + Date.now().toString(36) + Math.floor(Math.random() * 1000); }
export var YEAR = CFG.year || new Date().getFullYear();
var toastEl = null, toastTimer = null;
export function toast(msg) {
  if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'vz-toast'; toastEl.setAttribute('role', 'status'); document.body.appendChild(toastEl); }
  toastEl.innerHTML = msg; toastEl.hidden = false;
  clearTimeout(toastTimer); toastTimer = setTimeout(function () { toastEl.hidden = true; }, 6000);
}
export function hhmm(min) { min = Math.round(min); var h = Math.floor(min / 60) % 24, m = min % 60; return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m; }
export function toMin(t) { var p = String(t || '09:00').split(':'); return (+p[0]) * 60 + (+p[1] || 0); }
export function pad2(n) { return (n < 10 ? '0' : '') + n; }
