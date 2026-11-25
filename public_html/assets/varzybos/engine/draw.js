// Variklis: burtai - pastovūs pagal sėklą, to paties klubo dalyviai pirmame rate nesusitinka. Be naršyklės (tests/engine).
// ---------- Burtai: atskiriant to paties klubo dalyvius ----------
export function seedOrder(size) {
  var order = [1, 2];
  while (order.length < size) {
    var n = order.length * 2;
    order = order.reduce(function (acc, s) { acc.push(s, n + 1 - s); return acc; }, []);
  }
  return order;
}
export function shuffle(a, rnd) { rnd = rnd || Math.random; for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(rnd() * (i + 1)); var t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
// Burtai, kurių dar niekas neprašė (grupė tik atidaryta), neišsaugomi iki pirmo veiksmo. Kad perkrovus puslapį ar kitoje
// planšetėje poros būtų tos pačios, jie traukiami pagal pastovų skaičių iš varžybų, rungties ir dalyvių. „Burti iš naujo“ - tikrai atsitiktinai.
export function seededRnd(str) {
  var h = 1779033703 ^ str.length;
  for (var i = 0; i < str.length; i++) { h = Math.imul(h ^ str.charCodeAt(i), 3432918353); h = (h << 13) | (h >>> 19); }
  return function () {
    h = Math.imul(h ^ (h >>> 16), 2246822507); h = Math.imul(h ^ (h >>> 13), 3266489909); h ^= h >>> 16;
    var a = (h >>> 0) + 0x6D2B79F5; a = Math.imul(a ^ (a >>> 15), a | 1); a ^= a + Math.imul(a ^ (a >>> 7), a | 61);
    return ((a ^ (a >>> 14)) >>> 0) / 4294967296;
  };
}
export function drawSeed(c, system, tid) { return [tid || 0, c.id, system, c.competitors.map(function (p) { return p.id; }).sort().join(',')].join('|'); }
export function makeDraw(c, system, rnd) {
  if (system === 'rr' || system === 'score') {
    return { system: system, order: shuffle(c.competitors.map(function (p) { return p.id; }), rnd), results: {}, scores: {}, final: {}, locked: false };
  }
  var n = c.competitors.length, size = 1;
  while (size < n) size *= 2;
  var byClub = {};
  shuffle(c.competitors.slice(), rnd).forEach(function (p) { (byClub[p.club] = byClub[p.club] || []).push(p.id); });
  var groups = Object.keys(byClub).map(function (k) { return byClub[k]; }).sort(function (a, b) { return b.length - a.length; });
  var seeds = [];
  while (seeds.length < n) groups.forEach(function (g) { if (g.length) seeds.push(g.shift()); });
  var slots = seedOrder(size).map(function (s) { return s <= n ? seeds[s - 1] : null; });
  // Pirmame rate to paties klubo dalyviai neturi susitikti: sukeičiame su kitos poros dalyviu, jei įmanoma
  var clubOf = {};
  c.competitors.forEach(function (p) { clubOf[p.id] = p.club; });
  for (var pass = 0; pass < 3; pass++) {
    for (var i = 0; i < slots.length; i += 2) {
      var x = slots[i], y = slots[i + 1];
      if (!x || !y || clubOf[x] !== clubOf[y]) continue;
      for (var j = 0; j < slots.length; j++) {
        if (j === i || j === i + 1 || !slots[j]) continue;
        var z = slots[j], partner = slots[j % 2 ? j - 1 : j + 1];
        if (clubOf[z] !== clubOf[x] && (!partner || clubOf[partner] !== clubOf[y])) { slots[i + 1] = z; slots[j] = y; break; }
      }
    }
  }
  return { system: system || 'wkf', slots: slots, results: {}, scores: {}, locked: false };
}
