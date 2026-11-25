// Variklis: balų sistema (kata, enbu) - kraštiniai balai, 1 ar 2 ratai, finalininkai. Be naršyklės (tests/engine).
import { cfg, person } from '../rules.js';

// ---------- Balai: teisėjų balai, kraštiniai atmetami (nebūtina), 1 arba 2 ratai ----------
// Rato balas: teisėjų balų vidurkis (ar suma), kraštiniai (aukščiausias ir žemiausias) atmetami, kai teisėjų 5 ir daugiau
export function scoreTotal(c, arr) {
  var f = cfg(c), v = (arr || []).slice(0, f.judges).map(function (x) { return parseFloat(x); });
  if (v.length < f.judges || v.some(function (x) { return isNaN(x); })) return null;
  var sum = v.reduce(function (a, b) { return a + b; }, 0), n = v.length;
  if (f.drop && f.judges >= 5) { sum -= Math.max.apply(null, v) + Math.min.apply(null, v); n -= 2; }
  return f.avg ? Math.round(sum / n * 1000) / 1000 : Math.round(sum * 100) / 100;
}
export function fmtScore(c, v) { return v === null || v === undefined ? '–' : cfg(c).avg ? (Math.round(v * 100) / 100).toFixed(2) : v.toFixed(1); }
// Finale vieta - pusfinalio (1 rato) ir finalo balų suma (sum); lygiųjų atveju - finalo balas, tada visų balų suma
export function scoreRank(c, which, ids) {
  var d = c.draw, src = which === 'final' ? d.final : d.scores;
  return ids.map(function (id) {
    var arr = (src || {})[id], t = scoreTotal(c, arr), all = (arr || []).reduce(function (a, b) { return a + (parseFloat(b) || 0); }, 0);
    var r1 = which === 'final' ? scoreTotal(c, (d.scores || {})[id]) : null;
    return { id: id, total: t, all: all, r1: r1, sum: which === 'final' ? (t === null || r1 === null ? null : Math.round((t + r1) * 1000) / 1000) : t };
  }).sort(function (x, y) {
    if (x.sum === null || y.sum === null) return (x.sum === null) - (y.sum === null);
    return y.sum - x.sum || (y.total || 0) - (x.total || 0) || y.all - x.all;
  });
}
export function scoreFinalists(c) {
  var r1 = scoreRank(c, 'scores', (c.draw.order || []).filter(function (id) { return person(c, id); }));
  if (r1.some(function (x) { return x.total === null; })) return [];
  // Finale pasirodymo eilė: nuo žemiausio pusfinalio balo iki aukščiausio (geriausias - paskutinis)
  return r1.slice(0, Math.min(cfg(c).finalists, r1.length)).map(function (x) { return x.id; }).reverse();
}
export function scoreDone(c) {
  var ids = (c.draw.order || []).filter(function (id) { return person(c, id); });
  var n = ids.filter(function (id) { return scoreTotal(c, (c.draw.scores || {})[id]) !== null; }).length;
  if (cfg(c).rounds === 2) n += scoreFinalists(c).filter(function (id) { return scoreTotal(c, (c.draw.final || {})[id]) !== null; }).length;
  return n;
}
