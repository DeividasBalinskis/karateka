// Variklis: visa grupė - kovų skaičius, kiek baigta, vietos, ar baigta, užrakinimas pačiai baigus (#182). Be naršyklės (tests/engine).
import { cfg, defaultSystem, isElim, person } from '../rules.js';
import { allMatches, bracket } from './bracket.js';
import { rrMatches, rrStandings } from './rr.js';
import { scoreDone, scoreFinalists, scoreRank } from './score.js';

export function matchCount(c) {
  var n = c.competitors.length;
  if (n < 2) return 0;
  var sys = c.draw ? c.draw.system : defaultSystem(c);
  if (sys === 'rr') return n * (n - 1) / 2;
  if (sys === 'score') return n + (cfg(c).rounds === 2 ? Math.min(cfg(c).finalists, n) : 0);
  var guess = n - 1 + (sys === 'wkf' ? Math.max(0, Math.min(4, n - 4)) : sys === 'bronze' && n >= 3 ? 1 : 0);
  if (!c.draw || drawStale(c)) return guess;
  var b = bracket(c), known = allMatches(b).length;
  var repReady = !b.rep.length || b.rep.every(function (g) { return g.ready; });
  return repReady ? known : Math.max(known, guess);
}
export function doneCount(c) {
  if (!c.draw) return 0;
  if (c.draw.system === 'rr') return rrMatches(c).filter(function (m) { return m.winner; }).length;
  if (c.draw.system === 'score') return scoreDone(c);
  var b = bracket(c); return allMatches(b).filter(function (m) { return m.winner && m.loser; }).length; }
export function prune(c) {
  // Pašalina rezultatus, kurie nebegalioja (pasikeitė, kas kovoja)
  if (!isElim(c.draw.system)) {
    var ids = c.competitors.map(function (p) { return p.id; });
    Object.keys(c.draw.results).forEach(function (k) { if (ids.indexOf(c.draw.results[k]) === -1) delete c.draw.results[k]; });
    return;
  }
  for (var guard = 0; guard < 5; guard++) {
    var b = bracket(c), valid = {}, changed = false;
    allMatches(b).forEach(function (m) { valid[m.id] = [m.a, m.b]; });
    Object.keys(c.draw.results).forEach(function (id) {
      var v = valid[id];
      if (!v || v.indexOf(c.draw.results[id]) === -1) { delete c.draw.results[id]; changed = true; }
    });
    if (!changed) break;
  }
}
export function drawStale(c) {
  if (!c.draw) return true;
  var ids = (isElim(c.draw.system) ? c.draw.slots : c.draw.order || []).filter(Boolean).slice().sort().join(','), now = c.competitors.map(function (p) { return p.id; }).sort().join(',');
  return ids !== now;
}
// Vietos kategorijoje: [[vieta, [id...]], ...] - tik kai kategorija baigta (olimpinėje - kai aiškios)
export function catPlaces(c) {
  if (!c.draw || drawStale(c)) return [];
  var sys = c.draw.system;
  if (isElim(sys)) return bracket(c).places;
  if (doneCount(c) < matchCount(c)) return [];
  var ranked;
  if (sys === 'rr') ranked = rrStandings(c).map(function (x) { return { id: x.id, key: x.w + '|' + x.diff }; });
  else {
    var ids = (c.draw.order || []).filter(function (id) { return person(c, id); });
    var fin = cfg(c).rounds === 2 ? scoreFinalists(c) : [];
    ranked = (fin.length ? scoreRank(c, 'final', fin).concat(scoreRank(c, 'scores', ids.filter(function (id) { return fin.indexOf(id) === -1; }))) : scoreRank(c, 'scores', ids))
      .map(function (x) { return { id: x.id, key: (fin.indexOf(x.id) >= 0 ? 'F' : '') + x.sum + '|' + x.total + '|' + x.all }; });
  }
  var out = [], place = 0;
  ranked.forEach(function (x, i) { if (!i || x.key !== ranked[i - 1].key) place = i + 1; out.push([place, [x.id]]); });
  return out;
}
// #182 (v1.99): rungtis užsirakina pati, kai baigiasi paskutinė kova; pataisyti - paspausti „🔒 Užrakinta“ (atsirakina)
export function autoLock(c, wasDone) { if (c && c.draw && !wasDone && catFinished(c)) c.draw.locked = true; }
export function catFinished(c) {
  if (!c.draw || drawStale(c)) return false;
  var total = matchCount(c), pl = catPlaces(c);
  return total > 0 ? doneCount(c) >= total && pl.some(function (x) { return x[0] === 1; }) : pl.length > 0;
}
