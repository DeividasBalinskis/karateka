// Rezultatai: grupių būsenos, prizininkai, duomenys žiūrovams ir LTKF (resultsPayload)
import { YEAR } from './util.js';
import { cfg, person, sysName, SYSTEMS } from './rules.js';
import { bracket, roundName } from './engine/bracket.js';
import { rrMatches } from './engine/rr.js';
import { fmtScore, scoreFinalists, scoreRank } from './engine/score.js';
import { catFinished, catPlaces, doneCount, drawStale, matchCount } from './engine/category.js';
import { AW, S } from './store.js';
import { crit } from './views/categories.js';
import { kataOf } from './views/draw.js';
import { areaOf, B, matchTitle, pubNameShort } from './bout.js';
import { areasSummary, medalTable } from './views/results.js';

// Tatamio planas: rungtys su laikais ir būsena (done - baigta, now - vyksta dabar, next - toliau, later); vėlavimas min. (tik varžybų dieną)
// ---------- Grupių būsenos ir apdovanojimai (db/033) ----------
// Laukiama → Vyksta → Užbaigta → „Siųsti apdovanojimams“ → Užbaigta · apdovanojama → (planšetėje „Užbaigti“) → Užbaigta ir apdovanota.
// Amžiaus grupė (tie patys gimimo metai: ir kata, ir kumite) apdovanojimų planšetėje žalia, kai visos jos rungtys išsiųstos.
export var ST_LABEL = { wait: 'Laukiama', run: 'Vyksta', fin: 'Užbaigta', sent: 'Užbaigta · apdovanojama', awarded: 'Užbaigta ir apdovanota' };
export function catStatus(c, isNow) {
  var aw = AW[c.id] && AW[c.id].st;
  if (aw === 'done') return 'awarded';
  if (aw === 'sent') return 'sent';
  if (catFinished(c)) return 'fin';
  return isNow || (c.draw && !drawStale(c) && doneCount(c) > 0) ? 'run' : 'wait';
}
export function stChip(st) { return '<span class="aw-chip aw-' + st + '">' + (st === 'sent' ? '🏅 ' : st === 'awarded' ? '✓ ' : '') + ST_LABEL[st] + '</span>'; }
export function ageKey(c) { var k = crit(c); return k.y0 || k.y1 ? 'y' + (k.y0 || '') + '-' + (k.y1 || '') : 'c' + c.id; }
export function ageName(c) {
  var k = crit(c); if (!k.y0 && !k.y1) return c.name;
  return k.y0 && k.y1 ? (k.y1 === k.y0 ? k.y0 + ' m.' : k.y1 + '–' + k.y0 + ' m.') : k.y1 ? k.y1 + ' m. ir vyr.' : k.y0 + ' m. ir jaun.';
}
export function ageGroup(c) { var key = ageKey(c); return S.categories.filter(function (x) { return x.competitors.length && ageKey(x) === key; }); }
export function typingNow() { var el = document.activeElement; return !!(el && /INPUT|SELECT|TEXTAREA/.test(el.tagName) && document.querySelector('main').contains(el)) || !!B; }
// ---------- Rezultatai: prizininkai ir klubų medaliai ----------
export function results() {
  return S.categories.filter(function (c) { return c.competitors.length; }).map(function (c) {
    var total = matchCount(c), done = doneCount(c), places = [];
    if (c.draw && !drawStale(c)) {
      catPlaces(c).forEach(function (pl) {
        pl[1].forEach(function (id) { var p = person(c, id); if (p && pl[0] <= 3) places.push({ place: pl[0], name: p.name, club: p.club, members: memberIds(id), minor: isMinor(id) }); });
      });
    }
    var finished = total > 0 ? done >= total && places.some(function (p) { return p.place === 1; }) : places.length > 0;
    return { c: c, total: total, done: done, finished: finished, places: places };
  });
}
// Karateka narių id (prizininko arba komandos narių) - rezultatams perkelti į narių taškus
function memberIds(id) {
  var ids = S.teams[id] ? S.teams[id].members : [id];
  return ids.map(function (x) { return S.people[x] && S.people[x].member; }).filter(Boolean);
}
// Vaikai ir jaunimas (iki 18 m.) - viešai rodomas vardas ir pavardės raidė
// Viešai trumpinama („Jonas P.“) iki 18 m. ir tada, kai gimimo metai nežinomi (įrašyta ranka) - saugiau (B3)
function isMinor(id) { var p = S.people[id]; return !S.teams[id] && !!(p && (!p.birth || YEAR - p.birth < 18)); }
export function resultsPayload() {
  var rs = results();
  return {
    categories: rs.map(function (r) {
      return { id: r.c.id, name: r.c.name, discipline: r.c.discipline, team: !!r.c.team, count: r.c.competitors.length, total: r.total, done: r.done, finished: r.finished, places: r.places,
        group: ageKey(r.c), groupName: ageName(r.c), tatami: (areaOf(r.c) || {}).name || '' };
    }),
    medals: medalTable(rs).map(function (t) { return { club: t.club, g: t.m.g, s: t.m.s, b: t.m.b, pts: t.m.pts }; }),
    people: Object.keys(S.people).length,
    clubs: S.clubs.length,
    areas: areasSummary(),
    brackets: bracketsSummary()
  };
}
// Viešai atidaroma grupė: kovos pagal ratus (kas su kuo, kas laimėjo, balai) ir vietos; vaikų vardai sutrumpinti
function bracketsSummary() {
  var out = {};
  S.categories.forEach(function (c) { if (c.competitors.length) out[c.id] = catBracket(c); });
  return out;
}
export function catBracket(c, full) {
  var club = function (id) { return id ? (person(c, id) || {}).club || '' : ''; };
  var pubName = full ? function (id) { return (person(c, id) || {}).name || ''; } : pubNameShort;
  var mm = function (m, t) {
    var o = { no: m.no || '', t: t, a: m.a ? pubName(m.a) : '', ac: club(m.a), b: m.b ? pubName(m.b) : '', bc: club(m.b),
      w: m.winner && !m.bye ? (m.winner === m.a ? 'a' : 'b') : '', s: (c.draw.scores || {})[m.id] || '' };
    if (full) { o.id = m.id; o.ai = m.a || ''; o.bi = m.b || ''; }   // valdymui (prisegtame langelyje) - kovos ir dalyvių id
    return o;
  };
  var out = { id: c.id, name: c.name, disc: c.discipline, sys: c.draw && SYSTEMS[c.draw.system] ? sysName(c, c.draw.system) : '', count: c.competitors.length, groups: [], places: [] };
  if (!c.draw || drawStale(c) || c.competitors.length < 2) {
    out.people = c.competitors.map(function (p) { return { n: pubName(p.id), c: p.club }; });
    return out;
  }
  var sys = c.draw.system;
  if (sys === 'score') {
    var ids = (c.draw.order || []).filter(function (id) { return person(c, id); });
    var finIds = cfg(c).rounds === 2 ? scoreFinalists(c) : [];
    if (finIds.length) out.groups.push({ t: 'Finalas (pusfinalis + finalas)', score: true, rows: scoreRank(c, 'final', finIds).map(function (x) { return { n: pubName(x.id), c: club(x.id), k: kataOf(c, 'final', x.id), s: x.sum === null ? '' : fmtScore(c, x.sum) }; }) });
    out.groups.push({ t: finIds.length || cfg(c).rounds === 2 ? 'Pusfinalis' : 'Balai', score: true, rows: scoreRank(c, 'scores', ids).map(function (x) { return { n: pubName(x.id), c: club(x.id), k: kataOf(c, 'scores', x.id), s: x.total === null ? '' : fmtScore(c, x.total) }; }) });
  } else if (sys === 'rr') {
    out.groups.push({ t: 'Ratų sistema', matches: rrMatches(c).map(function (m) { return mm(m, m.round + ' ratas'); }) });
  } else {
    var b = bracket(c);
    b.rounds.forEach(function (rd, r) { var ms = rd.filter(function (m) { return m.no; }); if (ms.length) out.groups.push({ t: roundName(r, b.R), round: r, matches: ms.map(function (m) { return mm(m, matchTitle(c, m)); }) }); });
    b.rep.forEach(function (g) { if (g.matches.length) out.groups.push({ t: g.title, matches: g.matches.map(function (m) { return mm(m, g.title); }) }); });
  }
  catPlaces(c).forEach(function (pl) { pl[1].forEach(function (id) { if (pl[0] <= 3) out.places.push({ p: pl[0], n: pubName(id), c: club(id) }); }); });
  return out;
}
