// Variklis: ratų sistema - visi su visais ir lentelė. Be naršyklės (tests/engine).
import { person } from '../rules.js';

// ---------- Ratų sistema: visi su visais (ratų tvarka - „apskritimo“ metodu) ----------
export function rrMatches(c) {
  var ids = (c.draw.order || []).filter(function (id) { return person(c, id); }), list = ids.slice(), out = [], no = 1;
  if (list.length % 2) list.push(null);
  var n = list.length;
  for (var r = 0; r < n - 1; r++) {
    for (var i = 0; i < n / 2; i++) {
      var a = list[i], b = list[n - 1 - i];
      if (a && b) {
        var key = [a, b].sort().join('~'), w = c.draw.results[key];
        out.push({ id: key, a: a, b: b, round: r + 1, no: no++, winner: w === a || w === b ? w : null, loser: w === a ? b : w === b ? a : null });
      }
    }
    list.splice(1, 0, list.pop());
  }
  return out;
}
export function parseScore(t) { var m = String(t || '').match(/(\d+(?:[.,]\d+)?)\s*[:\-]\s*(\d+(?:[.,]\d+)?)/); return m ? [parseFloat(m[1].replace(',', '.')), parseFloat(m[2].replace(',', '.'))] : null; }
export function rrStandings(c) {
  var ms = rrMatches(c), st = {};
  c.competitors.forEach(function (p) { st[p.id] = { id: p.id, w: 0, l: 0, diff: 0, pts: 0, played: 0 }; });
  ms.forEach(function (m) {
    if (!m.winner) return;
    st[m.winner].w++; st[m.loser].l++; st[m.a].played++; st[m.b].played++;
    var sc = parseScore((c.draw.scores || {})[m.id]);
    if (sc) { st[m.a].diff += sc[0] - sc[1]; st[m.b].diff += sc[1] - sc[0]; st[m.a].pts += sc[0]; st[m.b].pts += sc[1]; }
  });
  var head = function (x, y) { var m = ms.find(function (q) { return q.id === [x, y].sort().join('~'); }); return m && m.winner ? (m.winner === x ? -1 : 1) : 0; };
  return Object.keys(st).map(function (k) { return st[k]; }).sort(function (x, y) {
    return y.w - x.w || head(x.id, y.id) || y.diff - x.diff || (person(c, x.id) || {}).name.localeCompare((person(c, y.id) || {}).name, 'lt');
  });
}
