// Variklis: olimpinė lentelė iš burtų ir rezultatų - laisvi ratai, paguoda (WKF), kova dėl 3 vietos, vietos. Be naršyklės (tests/engine).
// ---------- Lentelė: kovos apskaičiuojamos iš burtų ir rezultatų ----------
export function roundName(r, R) {
  var left = R - r;
  return left === 1 ? 'Finalas' : left === 2 ? 'Pusfinalis' : left === 3 ? 'Ketvirtfinalis' : '1/' + Math.pow(2, left - 1);
}
export function play(m, res) {
  if (!m.wait && m.a && !m.b) { m.winner = m.a; m.bye = true; }
  else if (!m.wait && !m.a && m.b) { m.winner = m.b; m.bye = true; }
  else if (m.a && m.b && (res[m.id] === m.a || res[m.id] === m.b)) { m.winner = res[m.id]; m.loser = m.winner === m.a ? m.b : m.a; }
  return m;
}
export function bracket(c) {
  var d = c.draw, res = d.results, size = d.slots.length, R = Math.max(1, Math.log2(size));
  var rounds = [], no = 1, prev = [];
  if (size === 1) return { rounds: [], R: 0, rep: [], places: d.slots[0] ? [[1, [d.slots[0]]]] : [] };
  for (var i = 0; i < size / 2; i++) prev.push(play({ id: 'r0m' + i, r: 0, left: R, a: d.slots[2 * i], b: d.slots[2 * i + 1] }, res));
  rounds.push(prev);
  for (var r = 1; r < R; r++) {
    var cur = [];
    for (var j = 0; j < prev.length / 2; j++) {
      var x = prev[2 * j], y = prev[2 * j + 1];
      cur.push(play({ id: 'r' + r + 'm' + j, r: r, left: R - r, a: x.winner || null, b: y.winner || null, wait: !x.winner || !y.winner }, res));
    }
    rounds.push(cur);
    prev = cur;
  }
  // Numeruojamos visos tikros kovos (laisvi ratai būna tik pirmame rate)
  rounds.forEach(function (rd) { rd.forEach(function (m) { if (!m.bye && (m.r > 0 || (m.a && m.b))) m.no = no++; }); });
  var fin = rounds[R - 1][0];
  var rep = [], places = [];
  var semis = R >= 2 ? rounds[R - 2] : [];
  if (d.system === 'wkf' && R >= 2) {
    // Paguoda: kiekvienam finalininkui - visi, kuriuos jis nugalėjo; grandinė nuo anksčiausio rato, laimėtojas - bronza
    semis.forEach(function (sm, side) {
      var finalist = sm.winner, chain = [], bronze = null;
      if (finalist) {
        for (var rr = 0; rr <= R - 2; rr++) rounds[rr].forEach(function (m) { if (m.winner === finalist && m.loser) chain.push(m.loser); });
        var holder = chain[0] || null, ms = [];
        for (var k = 1; k < chain.length; k++) {
          var m = play({ id: 'rep' + side + 'm' + k, a: holder, b: chain[k], rep: true, wait: !holder }, res);
          m.no = no++;
          ms.push(m);
          holder = m.winner || null;
        }
        bronze = chain.length === 1 ? chain[0] : holder;
        rep.push({ title: 'Paguoda ' + (side === 0 ? 'A' : 'B') + ' pusė', matches: ms, ready: true });
      } else {
        rep.push({ title: 'Paguoda ' + (side === 0 ? 'A' : 'B') + ' pusė', matches: [], ready: false });
      }
      if (bronze) places.push([3, [bronze]]);
    });
  } else if (d.system === 'bronze' && R >= 2) {
    var ba = semis[0] && semis[0].loser || null, bb = semis[1] && semis[1].loser || null;
    var bm = play({ id: 'bronze', a: ba, b: bb, rep: true, wait: !(ba && bb) }, res);
    bm.no = no++;
    rep.push({ title: 'Kova dėl 3 vietos', matches: [bm], ready: !!(bm.a && bm.b) });
    if (bm.winner && bm.loser) { places.push([3, [bm.winner]]); places.push([4, [bm.loser]]); }
  } else if (R >= 2) {
    semis.forEach(function (sm) { if (sm.loser) places.push([3, [sm.loser]]); });
  }
  if (fin.winner && fin.loser) { places.unshift([2, [fin.loser]]); places.unshift([1, [fin.winner]]); }
  return { rounds: rounds, R: R, rep: rep, places: places };
}
export function allMatches(b) {
  var all = [];
  b.rounds.forEach(function (rd) { rd.forEach(function (m) { if (m.no) all.push(m); }); });
  b.rep.forEach(function (g) { g.matches.forEach(function (m) { if (m.no) all.push(m); }); });
  return all;
}
