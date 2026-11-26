// Kovos vieta vaizdo įraše (v2.13, Deividas: „pasirinkus pasibaigusią kovą - atsidaro jos vieta vaizdo įraše“). Be naršyklės - tests/engine/video.test.mjs.
// Laikai - ms (Date.now()). Kova (c.draw.times[id]): { s: pradžia, e: pabaiga, a: tatamio id }.
// Įrašas (S.videos[]): { a: tatamio id, yt: YouTube vaizdo id ARBA file: 'failo vardas|dydis', from: kada buvo įrašo 0:00, ok: laikas patikrintas }.
export var LEAD = 10;   // s: atidaroma kiek anksčiau kovos pradžios (matosi, kaip išeina į tatamį)

// Kova be kovos skydelio (vėliavėlės, „Laimėjo“, kata balai): prasidėjo, kai baigėsi ankstesnė kova ant to paties tatamio
// (ne anksčiau nei prieš 4 min. ir ne vėliau nei prieš 20 s); jei ankstesnės nėra - prieš 3 min.
export function guessStart(times, a, now) {
  var prev = 0;
  times.forEach(function (x) { if (x && x.a === a && x.e < now && x.e > prev) prev = x.e; });
  if (!prev) prev = now - 3 * 60000;
  return Math.min(now - 20000, Math.max(prev, now - 4 * 60000));
}

// Kuris įrašas rodo kovą: naujausias to tatamio įrašas (kind - 'yt' ar 'file'), prasidėjęs iki kovos pabaigos.
// Jei tokio nėra - artimiausias vėlesnis nepatikrintas (nuoroda įklijuota, kai transliacija jau ėjo; ne vėliau nei po 30 min.)
export function pickVideo(videos, t, kind) {
  var mine = (videos || []).filter(function (v) { return v && v.a === t.a && (!kind || v[kind]) && typeof v.from === 'number'; }), best = null;
  mine.forEach(function (v) { if (v.from <= t.e && (!best || v.from > best.from)) best = v; });
  if (!best) mine.forEach(function (v) { if (!v.ok && v.from - t.e < 30 * 60000 && (!best || v.from < best.from)) best = v; });
  return best;
}

// Kiek sekundžių nuo įrašo pradžios atidaryti kovą
export function offsetSec(t, v) { return Math.max(0, Math.round((t.s - v.from) / 1000) - LEAD); }

// Ar kova yra įraše, kuris trunka dur s
export function inVideo(t, v, dur) { return t.e >= v.from && t.s <= v.from + dur * 1000; }

// Telefono įrašas rašomas į failą visą laiką, todėl failo pakeitimo laikas - įrašo pabaiga; pradžia = pabaiga − trukmė
export function fileStart(lastModified, durationSec) { return Math.round(lastModified - durationSec * 1000); }

// „📍 Ši kova prasideda čia“: vaizdo laikas (s), kai kova prasideda → kada buvo įrašo 0:00 (kitos šio įrašo kovos atsidarys tiksliau)
export function calibrate(t, curSec) { return Math.round(t.s - curSec * 1000); }

// 3725 → „1:02:05“, 65 → „1:05“
export function clock(sec) {
  sec = Math.max(0, Math.round(sec));
  var h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
  return (h ? h + ':' + (m < 10 ? '0' : '') : '') + m + ':' + (s < 10 ? '0' : '') + s;
}

// „1:02:05“, „62:05“, „3725“ → sekundės; netinkama - null
export function parseClock(txt) {
  var p = String(txt || '').trim().split(':');
  if (!p[0] || p.length > 3 || p.some(function (x) { return !/^\d+$/.test(x); })) return null;
  return p.reduce(function (s, x) { return s * 60 + +x; }, 0);
}

// YouTube vaizdo id iš nuorodos (watch?v=, youtu.be/, live/, embed/, shorts/); kanalo nuoroda - ''
export function ytId(u) {
  var m = String(u || '').match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|live\/|embed\/|shorts\/))([A-Za-z0-9_-]{11})/);
  return m ? m[1] : '';
}

// Nuoroda žiūrovams: YouTube įrašas nuo kovos pradžios
export function ytLink(t, v) { return 'https://youtu.be/' + v.yt + '?t=' + offsetSec(t, v); }
