(function () {
  'use strict';
  // Varžybų valdymas (admin/varzybu-sistema.php). Būsena S - iš serverio (window.VARZ.state), pakeitimai išsaugomi automatiškai.
  var CFG = window.VARZ || {};
  var KEY = 'varzybos-' + (CFG.id || 0);
  var DISC = { kata: 'Kata', kumite: 'Kumite', fukugo: 'Fukugo', kihon: 'Kihon', enbu: 'Enbu', sumo: 'Sumo' };
  // Sistemos (kaip Kihapp): olimpinės lentelės, fukugo, ratų sistema, balai
  var SYSTEMS = {
    wkf: ['Olimpinė + paguoda', 'Pralaimėję finalininkams kovoja paguodoje, 2 bronzos (WKF).'],
    bronze: ['Olimpinė + kova dėl 3 vietos', 'Viena trečia vieta: pusfinalių pralaimėtojai kovoja dėl bronzos.'],
    plain: ['Olimpinė, 2 bronzos', 'Dvi trečios vietos: abu pusfinalių pralaimėtojai - be kovos.'],
    fukugo: ['Fukugo', 'Finalas - kumite, pusfinalis - kitei (vėliavėlės), toliau pakaitomis. 2 bronzos.'],
    rr: ['Ratų sistema', 'Visi su visais. Kovoje - kiek vėliavėlių prieš kiek (ar kumite balai).'],
    score: ['Balai', 'Teisėjai vertina balais, kraštiniai atmetami (matosi perbraukti). Enbu, kata.']
  };
  function isElim(sys) { return sys !== 'rr' && sys !== 'score'; }
  // Numatyta sistema pagal rungtį (pasirinkus rungtį - nustatoma pati, kol kovų dar nebuvo)
  function defaultSystem(c) {
    if (c.discipline === 'enbu') return 'score';
    if (c.discipline === 'fukugo') return 'fukugo';
    return c.team ? 'bronze' : 'plain';
  }
  function cfg(c) {
    c.cfg = c.cfg || {}; var f = c.cfg; f.judges = f.judges || 5; f.rounds = f.rounds || 1; f.finalists = f.finalists || 8; if (f.drop === undefined) f.drop = true;
    f.time = f.time || (c.discipline === 'sumo' ? 60 : 120); f.sumoTo = f.sumoTo || 3; if (f.bunkai === undefined) f.bunkai = true;
    return f;
  }
  // Kaip vyksta kova: kumite (balai +/- ir laikrodis), komandinis kumite (3 kovos, balai sumuojasi), sumo (iki N taškų),
  // vėliavėlės (kata, kihon, kitei), komandinės katos finalas (Kata + Pritaikymas balais)
  function boutMode(c, m) {
    var d = c.discipline, sys = c.draw ? c.draw.system : defaultSystem(c);
    if (d === 'sumo') return 'sumo';
    if (sys === 'fukugo' || d === 'fukugo') return m.left && m.left % 2 === 0 ? 'flags' : 'kumite';
    if (d === 'kumite') return c.team ? 'team' : 'kumite';
    if (c.team && cfg(c).bunkai && (m.left === 1 || m.id === 'bronze') && isElim(sys)) return 'score2';
    return 'flags';
  }
  var MODE_NAME = { kumite: 'Kumite', team: 'Komandinis kumite', sumo: 'Sumo', flags: 'Vėliavėlės', score2: 'Kata + Pritaikymas' };
  var HOME = CFG.homeClub || 'Karateka';
  var BELTS = CFG.belts || {};

  var S;
  function load() {
    S = CFG.state && typeof CFG.state === 'object' ? CFG.state : {};
    S.categories = Array.isArray(S.categories) ? S.categories : [];
    S.people = S.people && !Array.isArray(S.people) ? S.people : {};
    S.teams = S.teams && !Array.isArray(S.teams) ? S.teams : {};
    S.categories.forEach(function (c) { c.competitors.forEach(function (p) { if (!p.team && !S.people[p.id]) S.people[p.id] = { id: p.id, name: p.name, club: p.club }; }); });
    S.clubs = Array.isArray(S.clubs) && S.clubs.length ? S.clubs : [HOME];
    S.areas = Array.isArray(S.areas) && S.areas.length ? S.areas : [{ id: 1, name: '1 TATAMIS', start: '09:30' }];
    S.minutes = S.minutes || { kata: 2, kumite: 3, fukugo: 4, kihon: 1.5 };
    if (!S.minutes.enbu) S.minutes.enbu = 3;
    if (!S.minutes.sumo) S.minutes.sumo = 2;
    S.history = [];
    S.tournament = CFG.title || '';
    if (!S.selected && S.categories[0]) S.selected = S.categories[0].id;
  }

  // ---------- Išsaugojimas serveryje (po 0,7 s nuo paskutinio pakeitimo) ----------
  var version = CFG.version || 0, saveTimer = null, saving = false, dirty = false, conflict = false;
  var statusEl = document.getElementById('save-state');
  function setStatus(text, cls) { if (statusEl) { statusEl.textContent = text; statusEl.className = 'save-state ' + (cls || ''); } }
  function payload() {
    var st = {};
    Object.keys(S).forEach(function (k) { if (k !== 'history' && k !== 'tournament') st[k] = S[k]; });
    return JSON.stringify({ state: st, results: resultsPayload(), version: version });
  }
  function flush(keepalive) {
    if (!dirty || saving || conflict || !CFG.api) return;
    clearTimeout(saveTimer);
    saving = true; dirty = false;
    setStatus('Saugoma…', 'busy');
    fetch(CFG.api, { method: 'POST', credentials: 'same-origin', keepalive: !!keepalive,
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf, Accept: 'application/json' }, body: payload() })
      .then(function (r) { return r.json().then(function (d) { return { status: r.status, d: d }; }); })
      .then(function (x) {
        saving = false;
        if (x.status === 409) { conflict = true; setStatus('Kitas žmogus pakeitė varžybas - perkraukite puslapį', 'err'); showConflict(); return; }
        if (!x.d.ok) { dirty = true; setStatus('Neišsaugota: ' + (x.d.error || 'klaida'), 'err'); return; }
        version = x.d.version;
        setStatus('Išsaugota ✓', 'ok');
        if (dirty) flush();
      })
      .catch(function () { saving = false; dirty = true; setStatus('Nėra ryšio - bandysime dar kartą', 'err'); saveTimer = setTimeout(flush, 5000); });
  }
  function save() {
    if (CFG.readOnly) return;
    dirty = true;
    setStatus('Keičiama…', 'busy');
    clearTimeout(saveTimer);
    saveTimer = setTimeout(flush, 700);
  }
  function showConflict() {
    var b = document.getElementById('conflict');
    if (b) b.hidden = false;
  }
  window.addEventListener('pagehide', function () { flush(true); });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') flush(true); });
  window.addEventListener('beforeunload', function (e) { if (dirty || saving) { flush(true); e.preventDefault(); e.returnValue = ''; } });
  function cat(id) { return S.categories.find(function (c) { return c.id === id; }); }
  function person(c, id) { return c.competitors.find(function (p) { return p.id === id; }) || null; }
  function snapshot() { S.history.push(JSON.stringify({ categories: S.categories, people: S.people, clubs: S.clubs, teams: S.teams })); if (S.history.length > 40) S.history.shift(); }
  function undo() {
    if (!S.history.length) return;
    var h = JSON.parse(S.history.pop());
    if (Array.isArray(h)) { S.categories = h; } else { S.categories = h.categories; S.people = h.people; S.clubs = h.clubs; S.teams = h.teams || S.teams; }
  }
  // Dalyvio pakeitimas (vardas, klubas) - visose kategorijose iškart
  function updatePerson(id, fields) {
    Object.assign(S.people[id], fields);
    S.categories.forEach(function (c) { c.competitors.forEach(function (p) { if (p.id === id) Object.assign(p, fields); }); });
  }
  function catsOf(id) { return S.categories.filter(function (c) { return c.competitors.some(function (p) { return p.id === id; }); }); }
  function addToCat(id, catId) {
    var c = cat(catId), p = S.people[id];
    if (c && p && !c.competitors.some(function (x) { return x.id === id; })) c.competitors.push({ id: p.id, name: p.name, club: p.club });
  }
  function removeFromCat(id, catId) { var c = cat(catId); if (c) c.competitors = c.competitors.filter(function (x) { return x.id !== id; }); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (ch) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]; }); }
  function beltDot(l) { return l && BELTS[l] ? '<span class="belt-dot" style="background:' + BELTS[l][2] + '" title="' + esc(BELTS[l][0]) + '"></span>' : ''; }
  function beltName(l) { return l && BELTS[l] ? BELTS[l][0] : ''; }
  function beltOpts(cur) { return '<option value="">—</option>' + Object.keys(BELTS).map(function (k) { return '<option value="' + k + '"' + (+k === +cur ? ' selected' : '') + '>' + esc(BELTS[k][0]) + '</option>'; }).join(''); }
  function newId(prefix) { return prefix + Date.now().toString(36) + Math.floor(Math.random() * 1000); }
  var YEAR = CFG.year || new Date().getFullYear();

  // ---------- Burtai: atskiriant to paties klubo dalyvius ----------
  function seedOrder(size) {
    var order = [1, 2];
    while (order.length < size) {
      var n = order.length * 2;
      order = order.reduce(function (acc, s) { acc.push(s, n + 1 - s); return acc; }, []);
    }
    return order;
  }
  function shuffle(a) { for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
  function makeDraw(c, system) {
    if (system === 'rr' || system === 'score') {
      return { system: system, order: shuffle(c.competitors.map(function (p) { return p.id; })), results: {}, scores: {}, final: {}, locked: false };
    }
    var n = c.competitors.length, size = 1;
    while (size < n) size *= 2;
    var byClub = {};
    shuffle(c.competitors.slice()).forEach(function (p) { (byClub[p.club] = byClub[p.club] || []).push(p.id); });
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

  // ---------- Lentelė: kovos apskaičiuojamos iš burtų ir rezultatų ----------
  function roundName(r, R) {
    var left = R - r;
    return left === 1 ? 'Finalas' : left === 2 ? 'Pusfinalis' : left === 3 ? 'Ketvirtfinalis' : '1/' + Math.pow(2, left - 1);
  }
  function play(m, res) {
    if (!m.wait && m.a && !m.b) { m.winner = m.a; m.bye = true; }
    else if (!m.wait && !m.a && m.b) { m.winner = m.b; m.bye = true; }
    else if (m.a && m.b && (res[m.id] === m.a || res[m.id] === m.b)) { m.winner = res[m.id]; m.loser = m.winner === m.a ? m.b : m.a; }
    return m;
  }
  function bracket(c) {
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
  function allMatches(b) {
    var all = [];
    b.rounds.forEach(function (rd) { rd.forEach(function (m) { if (m.no) all.push(m); }); });
    b.rep.forEach(function (g) { g.matches.forEach(function (m) { if (m.no) all.push(m); }); });
    return all;
  }
  function matchCount(c) {
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
  function doneCount(c) {
    if (!c.draw) return 0;
    if (c.draw.system === 'rr') return rrMatches(c).filter(function (m) { return m.winner; }).length;
    if (c.draw.system === 'score') return scoreDone(c);
    var b = bracket(c); return allMatches(b).filter(function (m) { return m.winner && m.loser; }).length; }
  function prune(c) {
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
  function drawStale(c) {
    if (!c.draw) return true;
    var ids = (isElim(c.draw.system) ? c.draw.slots : c.draw.order || []).filter(Boolean).slice().sort().join(','), now = c.competitors.map(function (p) { return p.id; }).sort().join(',');
    return ids !== now;
  }

  // ---------- Ratų sistema: visi su visais (ratų tvarka - „apskritimo“ metodu) ----------
  function rrMatches(c) {
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
  function parseScore(t) { var m = String(t || '').match(/(\d+(?:[.,]\d+)?)\s*[:\-]\s*(\d+(?:[.,]\d+)?)/); return m ? [parseFloat(m[1].replace(',', '.')), parseFloat(m[2].replace(',', '.'))] : null; }
  function rrStandings(c) {
    var ms = rrMatches(c), st = {};
    c.competitors.forEach(function (p) { st[p.id] = { id: p.id, w: 0, l: 0, diff: 0, played: 0 }; });
    ms.forEach(function (m) {
      if (!m.winner) return;
      st[m.winner].w++; st[m.loser].l++; st[m.a].played++; st[m.b].played++;
      var sc = parseScore((c.draw.scores || {})[m.id]);
      if (sc) { st[m.a].diff += sc[0] - sc[1]; st[m.b].diff += sc[1] - sc[0]; }
    });
    var head = function (x, y) { var m = ms.find(function (q) { return q.id === [x, y].sort().join('~'); }); return m && m.winner ? (m.winner === x ? -1 : 1) : 0; };
    return Object.keys(st).map(function (k) { return st[k]; }).sort(function (x, y) {
      return y.w - x.w || head(x.id, y.id) || y.diff - x.diff || (person(c, x.id) || {}).name.localeCompare((person(c, y.id) || {}).name, 'lt');
    });
  }
  // ---------- Balai: teisėjų balai, kraštiniai atmetami (nebūtina), 1 arba 2 ratai ----------
  function scoreTotal(c, arr) {
    var f = cfg(c), v = (arr || []).slice(0, f.judges).map(function (x) { return parseFloat(x); });
    if (v.length < f.judges || v.some(function (x) { return isNaN(x); })) return null;
    var sum = v.reduce(function (a, b) { return a + b; }, 0);
    if (f.drop && f.judges >= 5) sum -= Math.max.apply(null, v) + Math.min.apply(null, v);
    return Math.round(sum * 100) / 100;
  }
  function scoreRank(c, which, ids) {
    var d = c.draw, src = which === 'final' ? d.final : d.scores;
    return ids.map(function (id) {
      var arr = (src || {})[id], t = scoreTotal(c, arr), all = (arr || []).reduce(function (a, b) { return a + (parseFloat(b) || 0); }, 0);
      return { id: id, total: t, all: all, r1: which === 'final' ? scoreTotal(c, (d.scores || {})[id]) : null };
    }).sort(function (x, y) {
      if (x.total === null || y.total === null) return (x.total === null) - (y.total === null);
      return y.total - x.total || (y.r1 || 0) - (x.r1 || 0) || y.all - x.all;
    });
  }
  function scoreFinalists(c) {
    var r1 = scoreRank(c, 'scores', (c.draw.order || []).filter(function (id) { return person(c, id); }));
    if (r1.some(function (x) { return x.total === null; })) return [];
    return r1.slice(0, Math.min(cfg(c).finalists, r1.length)).map(function (x) { return x.id; });
  }
  function scoreDone(c) {
    var ids = (c.draw.order || []).filter(function (id) { return person(c, id); });
    var n = ids.filter(function (id) { return scoreTotal(c, (c.draw.scores || {})[id]) !== null; }).length;
    if (cfg(c).rounds === 2) n += scoreFinalists(c).filter(function (id) { return scoreTotal(c, (c.draw.final || {})[id]) !== null; }).length;
    return n;
  }
  // Vietos kategorijoje: [[vieta, [id...]], ...] - tik kai kategorija baigta (olimpinėje - kai aiškios)
  function catPlaces(c) {
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
        .map(function (x) { return { id: x.id, key: (fin.indexOf(x.id) >= 0 ? 'F' : '') + x.total + '|' + (x.r1 || 0) + '|' + x.all }; });
    }
    var out = [], place = 0;
    ranked.forEach(function (x, i) { if (!i || x.key !== ranked[i - 1].key) place = i + 1; out.push([place, [x.id]]); });
    return out;
  }

  // ---------- Skirtukai ----------
  var tabs = ['klubai', 'dalyviai', 'komandos', 'kategorijos', 'lenteles', 'tvarkarastis', 'rezultatai'];
  function showTab(t) {
    tabs.forEach(function (x) {
      document.getElementById('tab-' + x).setAttribute('aria-selected', x === t ? 'true' : 'false');
      document.getElementById('v-' + x).hidden = x !== t;
    });
    try { sessionStorage.setItem(KEY + '-tab', t); } catch (e) {}
    render();
  }
  tabs.forEach(function (t) { document.getElementById('tab-' + t).addEventListener('click', function () { showTab(t); }); });

  // ---------- Kategorijos: sąrašas | kategorija su nustatymais | dalyvių sąrašas (tempti) ----------
  var poolFilter = 'fit', poolQuery = '';
  function crit(c) { return c.crit || {}; }
  function fits(c, p) {
    var k = crit(c), y = p.birth;
    if (!p || (k.g && k.g !== 'X' && p.gender && p.gender !== k.g)) return false;
    if (k.y0 && y && y < k.y0) return false;
    if (k.y1 && y && y > k.y1) return false;
    if (k.b0 && p.belt && p.belt < k.b0) return false;
    if (k.b1 && p.belt && p.belt > k.b1) return false;
    return true;
  }
  function critName(c) {
    var k = crit(c), parts = [DISC[c.discipline] || c.discipline, k.g === 'F' ? 'F' : k.g === 'M' ? 'M' : 'Mix'];
    if (k.y0 || k.y1) parts.push(k.y0 && k.y1 ? (k.y1 === k.y0 ? k.y0 + ' m.' : k.y1 + '–' + k.y0 + ' m.') : k.y1 ? k.y1 + ' m. ir vyr.' : k.y0 + ' m. ir jaun.');
    var name = parts.join(' ');
    if (k.b0 || k.b1) name += ' · ' + (k.b0 && k.b1 ? kyu(k.b0).replace(' kyu', '') + '–' + kyu(k.b1) : k.b0 ? kyu(k.b0) + '+' : 'iki ' + kyu(k.b1));
    if (k.w) name += ' · ' + k.w;
    return name;
  }
  function sysBadge(c) { var s = c.draw ? c.draw.system : defaultSystem(c); return s === 'rr' ? 'Ratai' : s === 'score' ? 'Balai' : s === 'bronze' ? '1 trečia' : s === 'plain' ? '2 trečios' : s === 'fukugo' ? 'Fukugo' : 'Paguoda'; }
  function renderCats() {
    var ul = document.getElementById('cat-list');
    ul.innerHTML = S.categories.map(function (c) {
      var bad = c.competitors.length === 1 ? ' one' : '';
      return '<li data-drop-cat="' + c.id + '"' + (c.id === S.selected ? ' aria-current="true"' : '') + '><button type="button" data-cat="' + c.id + '"><span class="cl-name"><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) +
        (sysBadge(c) ? ' <span class="sys-mini">' + sysBadge(c) + '</span>' : '') + '</span><span class="cnt' + bad + '">' + c.competitors.length + '</span></button></li>';
    }).join('') || '<li class="muted small" style="padding:12px;">Kategorijų dar nėra. Spauskite „⚡ Automatiškai“ arba „+ Nauja“.</li>';
    var c = cat(S.selected) || S.categories[0];
    renderPool(c);
    if (!c) { document.getElementById('cat-detail').innerHTML = '<h2>Kategorijų nėra</h2><p class="muted" style="padding:14px 16px;">Sudarykite kategorijas automatiškai pagal dalyvių amžių, lytį ir diržą arba sukurkite naują.</p>'; return; }
    S.selected = c.id;
    var others = S.categories.filter(function (x) { return x.id !== c.id; });
    var opts = others.map(function (x) { return '<option value="' + x.id + '">' + esc(x.name) + '</option>'; }).join('');
    var stale = drawStale(c) && c.competitors.length > 1, k = crit(c), sys = c.draw ? c.draw.system : defaultSystem(c), f = cfg(c);
    var started = c.draw && Object.keys(c.draw.results || {}).length;
    var fitCount = Object.keys(S.people).filter(function (id) { return !person(c, id) && fits(c, S.people[id]) && (crit(c).g || crit(c).y0 || crit(c).y1 || crit(c).b0 || crit(c).b1); }).length;
    var beltSel = function (key, cur) { return '<select class="sel" data-crit="' + key + '">' + beltOpts(cur) + '</select>'; };
    document.getElementById('cat-detail').innerHTML =
      '<h2><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + ' <span class="pill">' + c.competitors.length + ' dalyv.</span>' + (stale ? ' <span class="pill warn">Lentelė pasenusi</span>' : '') +
        '<span class="spacer"></span><button class="btn btn-primary" type="button" id="cat-draw">Į lentelę →</button></h2>' +
      '<div class="cat-scroll">' +
      '<div class="fmt">' +
        '<div class="fmt-row"><span class="fmt-label">Pavadinimas</span><div class="fmt-ctl"><input type="text" class="sel wide" id="cat-name" value="' + esc(c.name) + '" aria-label="Kategorijos pavadinimas"><button class="btn" type="button" id="cat-autoname" title="Pagal rungtį ir kriterijus">↻ Pagal kriterijus</button></div></div>' +
        '<div class="fmt-row"><span class="fmt-label">Rungtis</span><div class="fmt-ctl seg">' + Object.keys(DISC).map(function (d) {
          return '<button type="button" class="seg-btn' + (d === c.discipline ? ' on' : '') + '" data-disc="' + d + '"><span class="disc d-' + d + '"></span>' + DISC[d] + '</button>';
        }).join('') + '<label class="small team-chk"><input type="checkbox" id="cat-team"' + (c.team ? ' checked' : '') + '> Komandinė</label></div></div>' +
        '<div class="fmt-row"><span class="fmt-label">Sistema</span><div class="fmt-ctl sys-cards">' + Object.keys(SYSTEMS).map(function (k2) {
          return '<button type="button" class="sys-card' + (k2 === sys ? ' on' : '') + '" data-sys="' + k2 + '"><span class="sys-ico sys-' + k2 + '" aria-hidden="true"></span><b>' + SYSTEMS[k2][0] + '</b><span>' + SYSTEMS[k2][1] + '</span></button>';
        }).join('') + '</div></div>' +
        '<div class="fmt-row"><span class="fmt-label">Kova</span><div class="fmt-ctl crit">' +
          '<label>Teisėjai <select class="sel" data-cfg="judges">' + [3, 5, 7].map(function (n) { return '<option' + (n === f.judges ? ' selected' : '') + '>' + n + '</option>'; }).join('') + '</select></label>' +
          (c.discipline === 'kumite' || c.discipline === 'fukugo' || c.discipline === 'sumo' ? '<label>Kovos laikas <select class="sel" data-cfg="time">' + [60, 90, 120, 150, 180].map(function (n) { return '<option value="' + n + '"' + (n === f.time ? ' selected' : '') + '>' + Math.floor(n / 60) + ':' + (n % 60 ? '30' : '00') + '</option>'; }).join('') + '</select></label>' : '') +
          (c.discipline === 'sumo' ? '<label>Laimi surinkęs <input class="sel num-in" type="number" min="1" max="20" data-cfg="sumoTo" value="' + f.sumoTo + '"> tšk.</label>' : '') +
          (c.team && c.discipline !== 'kumite' ? '<label><input type="checkbox" data-cfg="bunkai"' + (f.bunkai ? ' checked' : '') + '> Finale ir dėl bronzos: Kata + Pritaikymas (balai)</label>' : '') +
          '<span class="muted small">' + MODE_NAME[boutMode(c, { left: 3 })] + (c.discipline === 'fukugo' ? ' / Kitei' : '') + '</span>' +
        '</div></div>' +
        '<div class="fmt-row"><span class="fmt-label">Kriterijai</span><div class="fmt-ctl crit">' +
          '<label>Lytis <select class="sel" data-crit="g"><option value="">—</option><option value="M"' + (k.g === 'M' ? ' selected' : '') + '>M</option><option value="F"' + (k.g === 'F' ? ' selected' : '') + '>F</option><option value="X"' + (k.g === 'X' ? ' selected' : '') + '>Mix</option></select></label>' +
          '<label>Gimę nuo <input class="sel num-in" type="number" data-crit="y0" value="' + (k.y0 || '') + '" placeholder="metai"></label>' +
          '<label>iki <input class="sel num-in" type="number" data-crit="y1" value="' + (k.y1 || '') + '" placeholder="metai"></label>' +
          '<label>Diržas nuo ' + beltSel('b0', k.b0) + '</label><label>iki ' + beltSel('b1', k.b1) + '</label>' +
          '<label>Svoris <input class="sel" style="width:90px" data-crit="w" value="' + esc(k.w || '') + '" placeholder="-45 kg"></label>' +
          (fitCount ? '<button class="btn" type="button" id="cat-addfit">+ Pridėti tinkamus (' + fitCount + ')</button>' : '') +
        '</div></div>' +
        '<div class="fmt-row"><span class="fmt-label">Veiksmai</span><div class="fmt-ctl">' +
          '<button class="btn" type="button" id="cat-split">Padalinti per pusę</button>' +
          '<select class="sel" id="cat-merge-into" aria-label="Sujungti su"><option value="">Sujungti su…</option>' + opts + '</select>' +
          '<button class="btn btn-danger" type="button" id="cat-del">Ištrinti kategoriją</button>' +
        '</div></div>' +
      '</div>' +
      '<div class="table-scroll drop-zone" data-drop-cat="' + c.id + '"><table><thead><tr><th></th><th>Dalyvis</th><th>Klubas</th><th class="num">Gim.</th><th>Diržas</th><th>Perkelti į</th><th></th></tr></thead><tbody>' +
      c.competitors.map(function (p) {
        var pp = S.people[p.id] || {}, ok = p.team || fits(c, pp);
        return '<tr draggable="true" data-drag="' + p.id + '" data-from="' + c.id + '"' + (ok ? '' : ' class="warn-row" title="Neatitinka kategorijos kriterijų"') + '><td class="grip" aria-hidden="true">⋮⋮</td><td><b>' + esc(p.name) + '</b>' + (pp.member ? ' <span class="home-mark" title="Karateka narys">K</span>' : '') + (pp.noPhoto ? ' <span class="nophoto">be nuotraukų</span>' : '') + (ok ? '' : ' <span class="pill warn">netinka</span>') + '</td><td>' + esc(p.club) + '</td>' +
          '<td class="num">' + (pp.birth || '') + '</td><td>' + beltDot(pp.belt) + esc(beltName(pp.belt)) + '</td>' +
          '<td><select class="sel add-cat" data-move="' + p.id + '" aria-label="Perkelti ' + esc(p.name) + '"><option value="">—</option>' + opts + '</select></td>' +
          '<td><button class="btn btn-danger btn-xs" type="button" data-remove="' + p.id + '" aria-label="Pašalinti ' + esc(p.name) + '">×</button></td></tr>';
      }).join('') + '</tbody></table>' + (c.competitors.length ? '' : '<p class="drop-hint">Tempkite dalyvius čia iš dešinės</p>') + '</div>' +
      '<div class="add-row"><input type="text" id="new-name" placeholder="Vardas Pavardė" aria-label="Naujo dalyvio vardas">' +
        '<select class="sel" id="new-club" aria-label="Klubas">' + S.clubs.map(function (x) { return '<option>' + esc(x) + '</option>'; }).join('') + '</select>' +
        '<button class="btn" type="button" id="add-person">+ Naujas dalyvis</button>' +
        '<span class="muted small">Dalyvius tempkite: iš dešinės - į kategoriją, iš lentelės - į kitą kategoriją kairėje.</span></div>' +
      '</div>';
  }
  function renderPool(c) {
    var box = document.getElementById('cat-pool'); if (!box) return;
    var list = Object.keys(S.people).map(function (id) { return S.people[id]; }).filter(function (p) {
      if (poolQuery && p.name.toLowerCase().indexOf(poolQuery) === -1 && String(p.club).toLowerCase().indexOf(poolQuery) === -1) return false;
      if (poolFilter === 'none') return !catsOf(p.id).length;
      if (poolFilter === 'fit') return c && !person(c, p.id) && fits(c, p);
      return true;
    }).sort(function (a, b) { return a.name.localeCompare(b.name, 'lt'); });
    box.querySelector('.pool-list').innerHTML = list.slice(0, 400).map(function (p) {
      var n = catsOf(p.id).length;
      return '<div class="pool-item" draggable="true" data-drag="' + p.id + '">' + beltDot(p.belt) + '<span class="pi-name">' + esc(p.name) + '<small>' + esc(p.club) + ' · ' + (p.birth || '?') + ' · ' + (p.gender || '?') + '</small></span>' +
        '<span class="pi-n' + (n ? '' : ' zero') + '" title="Kategorijų">' + n + '</span>' + (c && !person(c, p.id) ? '<button type="button" class="pi-add" data-pool-add="' + p.id + '" title="Pridėti į šią kategoriją">+</button>' : '') + '</div>';
    }).join('') || '<p class="muted small" style="padding:10px;">Nėra.</p>';
    box.querySelectorAll('[data-pool]').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.pool === poolFilter ? 'true' : 'false'); });
  }
  document.getElementById('cat-pool').addEventListener('click', function (e) {
    var t = e.target;
    if (t.dataset.pool) { poolFilter = t.dataset.pool; renderCats(); return; }
    if (t.dataset.poolAdd) { var c = cat(S.selected); if (!c) return; snapshot(); addToCat(t.dataset.poolAdd, c.id); save(); render(); }
  });
  document.getElementById('cat-pool').addEventListener('input', function (e) { if (e.target.id === 'pool-search') { poolQuery = e.target.value.trim().toLowerCase(); renderPool(cat(S.selected)); } });

  // Tempimas: dalyvis iš sąrašo dešinėje → į kategoriją; iš kategorijos → į kitą kategoriją (su Ctrl - nukopijuoti); atgal į sąrašą - pašalinti
  var pDrag = null;
  var catsTab = document.getElementById('v-kategorijos');
  catsTab.addEventListener('dragstart', function (e) {
    var el = e.target.closest && e.target.closest('[data-drag]'); if (!el) return;
    pDrag = { id: el.dataset.drag, from: el.dataset.from || null };
    e.dataTransfer.effectAllowed = 'copyMove'; try { e.dataTransfer.setData('text/plain', pDrag.id); } catch (x) {}
    el.classList.add('dragging'); catsTab.classList.add('is-dragging');
  });
  catsTab.addEventListener('dragend', function () { pDrag = null; catsTab.classList.remove('is-dragging'); catsTab.querySelectorAll('.drop, .dragging').forEach(function (x) { x.classList.remove('drop', 'dragging'); }); });
  catsTab.addEventListener('dragover', function (e) {
    if (!pDrag) return; var t = e.target.closest('[data-drop-cat], [data-drop-pool]'); if (!t) return;
    if (t.dataset.dropCat && t.dataset.dropCat === pDrag.from) return;
    e.preventDefault(); e.dataTransfer.dropEffect = e.ctrlKey || e.altKey || !pDrag.from ? 'copy' : 'move';
    catsTab.querySelectorAll('.drop').forEach(function (x) { if (x !== t) x.classList.remove('drop'); }); t.classList.add('drop');
  });
  catsTab.addEventListener('drop', function (e) {
    if (!pDrag) return; var t = e.target.closest('[data-drop-cat], [data-drop-pool]'); if (!t) return; e.preventDefault();
    var d = pDrag; pDrag = null; snapshot();
    if (t.dataset.dropPool !== undefined) { if (d.from) removeFromCat(d.id, d.from); }
    else {
      var to = cat(t.dataset.dropCat), src = d.from && cat(d.from), comp = src ? person(src, d.id) : null;
      if (comp && comp.team) { if (!person(to, d.id)) to.competitors.push(comp); }
      else addToCat(d.id, to.id);
      if (src && !(e.ctrlKey || e.altKey)) removeFromCat(d.id, src.id);
    }
    save(); render();
  });

  document.getElementById('cat-list').addEventListener('click', function (e) {
    var b = e.target.closest('[data-cat]'); if (!b) return;
    S.selected = b.dataset.cat; save(); render();
  });
  document.getElementById('cat-new').addEventListener('click', function () {
    snapshot();
    var id = newId('k');
    S.categories.push({ id: id, name: 'Nauja kategorija', discipline: 'kata', competitors: [], tatami: S.areas[0].id, draw: null, crit: {} });
    S.selected = id; save(); render();
    var inp = document.getElementById('cat-name'); if (inp) { inp.focus(); inp.select(); }
  });
  document.getElementById('cat-detail').addEventListener('change', function (e) {
    var c = cat(S.selected); if (!c) return;
    var t = e.target;
    if (t.id === 'cat-name') { c.name = t.value.trim() || c.name; }
    else if (t.id === 'cat-team') { snapshot(); c.team = t.checked; applyDefaultSystem(c); }
    else if (t.dataset.cfg) { snapshot(); var f0 = cfg(c); f0[t.dataset.cfg] = t.type === 'checkbox' ? t.checked : Math.max(1, parseInt(t.value, 10) || 1); }
    else if (t.dataset.crit) {
      snapshot(); c.crit = c.crit || {};
      var v = t.value.trim(); c.crit[t.dataset.crit] = t.dataset.crit === 'g' || t.dataset.crit === 'w' ? v : (+v || null);
    }
    else if (t.id === 'cat-merge-into' && t.value) {
      var into = cat(t.value); snapshot();
      c.competitors.forEach(function (p) { if (!into.competitors.some(function (x) { return x.id === p.id; })) into.competitors.push(p); });
      S.categories = S.categories.filter(function (x) { return x.id !== c.id; });
      S.selected = into.id;
    } else if (t.dataset.move && t.value) {
      var to = cat(t.value), p = person(c, t.dataset.move); snapshot();
      c.competitors = c.competitors.filter(function (x) { return x !== p; });
      if (!to.competitors.some(function (x) { return x.id === p.id; })) to.competitors.push(p);
    } else return;
    save(); renderSoon();
  });
  document.getElementById('cat-detail').addEventListener('click', function (e) {
    var c = cat(S.selected); if (!c) return;
    var t = e.target.closest('button') || e.target;
    if (t.dataset.remove) { snapshot(); c.competitors = c.competitors.filter(function (p) { return p.id !== t.dataset.remove; }); }
    else if (t.dataset.disc) { snapshot(); c.discipline = t.dataset.disc; delete cfg(c).time; cfg(c); applyDefaultSystem(c); }
    else if (t.dataset.sys) { setSystem(c, t.dataset.sys); return; }
    else if (t.id === 'cat-autoname') { snapshot(); c.name = critName(c); }
    else if (t.id === 'cat-addfit') {
      snapshot(); Object.keys(S.people).forEach(function (id) { if (!person(c, id) && fits(c, S.people[id])) addToCat(id, c.id); });
    }
    else if (t.id === 'add-person') {
      var name = document.getElementById('new-name').value.trim(); if (!name) { document.getElementById('new-name').focus(); return; }
      snapshot();
      var np = { id: newId('c'), name: name, club: document.getElementById('new-club').value, birth: null, gender: null, belt: null, member: null };
      S.people[np.id] = np; addToCat(np.id, c.id);
    } else if (t.id === 'cat-split') {
      if (c.competitors.length < 2) return; snapshot();
      var half = Math.ceil(c.competitors.length / 2);
      var nc = { id: newId('k'), name: c.name + ' (2)', discipline: c.discipline, competitors: c.competitors.slice(half), tatami: c.tatami, draw: null, crit: JSON.parse(JSON.stringify(c.crit || {})), cfg: c.cfg };
      c.competitors = c.competitors.slice(0, half); c.name = c.name + ' (1)';
      S.categories.splice(S.categories.indexOf(c) + 1, 0, nc);
    } else if (t.id === 'cat-del') {
      if (c.competitors.length && !confirm('Ištrinti kategoriją „' + c.name + '“? Dalyviai liks varžybose.')) return;
      snapshot(); S.categories = S.categories.filter(function (x) { return x !== c; }); S.selected = S.categories[0] && S.categories[0].id;
    } else if (t.id === 'cat-draw') { showTab('lenteles'); return; }
    else return;
    save(); render();
  });

  // ---------- Klubai (kaip Kihapp „Schools“) ----------
  function clubStats() {
    var st = {};
    S.clubs.forEach(function (k) { st[k] = { people: 0, entries: 0, teams: 0 }; });
    Object.keys(S.teams).forEach(function (id) { var k = S.teams[id].club; st[k] = st[k] || { people: 0, entries: 0, teams: 0 }; st[k].teams++; });
    Object.keys(S.people).forEach(function (id) { var p = S.people[id]; st[p.club] = st[p.club] || { people: 0, entries: 0, teams: 0 }; st[p.club].people++; });
    S.categories.forEach(function (c) { c.competitors.forEach(function (p) { if (p.team) return; st[p.club] = st[p.club] || { people: 0, entries: 0, teams: 0 }; st[p.club].entries++; }); });
    return st;
  }
  function renderClubs() {
    var st = clubStats(), names = Object.keys(st).sort(function (a, b) { return a.localeCompare(b, 'lt'); }), tp = 0, te = 0, tt = 0;
    document.getElementById('clubs-view').innerHTML = '<table><thead><tr><th>Klubas</th><th class="num">Dalyviai</th><th class="num">Kategorijų įrašai</th><th class="num">Komandos</th><th></th></tr></thead><tbody>' +
      names.map(function (k) {
        tp += st[k].people; te += st[k].entries; tt += st[k].teams;
        return '<tr><td><input class="inline" value="' + esc(k) + '" data-club-rename="' + esc(k) + '" aria-label="Klubo pavadinimas"></td><td class="num">' + st[k].people + '</td><td class="num">' + st[k].entries + '</td><td class="num">' + st[k].teams + '</td>' +
          '<td><button class="btn" type="button" data-club-show="' + esc(k) + '">Dalyviai →</button>' + (st[k].people ? '' : ' <button class="btn btn-danger" type="button" data-club-del="' + esc(k) + '">Pašalinti</button>') + '</td></tr>';
      }).join('') + '</tbody><tfoot><tr><td>Iš viso</td><td class="num">' + tp + '</td><td class="num">' + te + '</td><td class="num">' + tt + '</td><td></td></tr></tfoot></table>';
  }
  document.getElementById('club-add').addEventListener('click', function () {
    var inp = document.getElementById('club-new-name'), n = inp.value.trim(); if (!n) { inp.focus(); return; }
    if (S.clubs.indexOf(n) === -1) { snapshot(); S.clubs.push(n); save(); }
    inp.value = ''; render();
  });
  document.getElementById('clubs-view').addEventListener('change', function (e) {
    var old = e.target.dataset.clubRename, n = e.target.value.trim(); if (!old || !n || n === old) return;
    snapshot();
    S.clubs = S.clubs.map(function (k) { return k === old ? n : k; }); if (S.clubs.indexOf(n) === -1) S.clubs.push(n);
    Object.keys(S.people).forEach(function (id) { if (S.people[id].club === old) updatePerson(id, { club: n }); });
    Object.keys(S.teams).forEach(function (id) { if (S.teams[id].club === old) updateTeam(id, { club: n }); });
    save(); renderSoon();
  });
  var pplClub = '';
  document.getElementById('clubs-view').addEventListener('click', function (e) {
    if (e.target.dataset.clubShow) { pplClub = e.target.dataset.clubShow; showTab('dalyviai'); }
    else if (e.target.dataset.clubDel) { snapshot(); S.clubs = S.clubs.filter(function (k) { return k !== e.target.dataset.clubDel; }); save(); render(); }
  });

  // ---------- Dalyviai (kaip Kihapp „Individuals“) ----------
  function renderPeople() {
    var clubSel = document.getElementById('ppl-club'), newClub = document.getElementById('ppl-new-club');
    clubSel.innerHTML = '<option value="">Visi klubai</option>' + S.clubs.map(function (k) { return '<option' + (k === pplClub ? ' selected' : '') + '>' + esc(k) + '</option>'; }).join('');
    newClub.innerHTML = S.clubs.map(function (k) { return '<option' + (k === pplClub ? ' selected' : '') + '>' + esc(k) + '</option>'; }).join('');
    var q = document.getElementById('ppl-search').value.trim().toLowerCase(), noCat = document.getElementById('ppl-nocat').checked;
    var list = Object.keys(S.people).map(function (id) { return S.people[id]; })
      .filter(function (p) { return (!q || p.name.toLowerCase().indexOf(q) !== -1) && (!pplClub || p.club === pplClub); })
      .map(function (p) { return { p: p, cats: catsOf(p.id) }; })
      .filter(function (x) { return !noCat || !x.cats.length; })
      .sort(function (a, b) { return a.p.name.localeCompare(b.p.name, 'lt'); });
    var catOpts = S.categories.map(function (c) { return '<option value="' + c.id + '">' + esc(c.name) + '</option>'; }).join('');
    var clubOpts = function (cur) { return S.clubs.map(function (k) { return '<option' + (k === cur ? ' selected' : '') + '>' + esc(k) + '</option>'; }).join(''); };
    var noneCount = Object.keys(S.people).filter(function (id) { return !catsOf(id).length; }).length;
    document.getElementById('people-view').innerHTML =
      '<h2>Dalyviai (' + list.length + ')' + (noneCount ? ' <span class="pill warn">' + noneCount + ' be kategorijos</span>' : '') + '</h2>' +
      '<div class="table-scroll"><table><thead><tr><th>Vardas, pavardė</th><th>Klubas</th><th class="num">Gim. metai</th><th>Lytis</th><th>Diržas</th><th>Kategorijos</th><th class="num">Įrašai</th><th></th></tr></thead><tbody>' +
      list.map(function (x) {
        var p = x.p;
        return '<tr' + (x.cats.length ? '' : ' class="warn-row"') + '><td><div class="name-cell">' + (p.member ? '<span class="home-mark" title="Karateka narys">K</span>' : '') + '<input class="inline" value="' + esc(p.name) + '" data-p-name="' + p.id + '" aria-label="Vardas">' + (p.noPhoto ? '<span class="nophoto" title="Nesutinka būti fotografuojamas ir filmuojamas">be nuotraukų</span>' : '') + '</div></td>' +
          '<td><select class="sel" data-p-club="' + p.id + '" aria-label="Klubas">' + clubOpts(p.club) + '</select></td>' +
          '<td class="num"><input class="inline num-in" type="number" min="1940" max="' + YEAR + '" value="' + (p.birth || '') + '" data-p-birth="' + p.id + '" aria-label="Gimimo metai"></td>' +
          '<td><select class="sel" data-p-gender="' + p.id + '" aria-label="Lytis"><option value="">—</option><option value="M"' + (p.gender === 'M' ? ' selected' : '') + '>M</option><option value="F"' + (p.gender === 'F' ? ' selected' : '') + '>F</option></select></td>' +
          '<td class="belt-cell">' + beltDot(p.belt) + '<select class="sel" data-p-belt="' + p.id + '" aria-label="Diržas">' + beltOpts(p.belt) + '</select></td>' +
          '<td>' + x.cats.map(function (c) { return '<span class="cchip"><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + '<button type="button" data-p-rm="' + p.id + '" data-c="' + c.id + '" aria-label="Išimti iš ' + esc(c.name) + '">×</button></span>'; }).join('') +
          '<select class="sel add-cat" data-p-add="' + p.id + '" aria-label="Pridėti į kategoriją"><option value="">+ kategorija…</option>' + catOpts + '</select></td>' +
          '<td class="num">' + x.cats.length + '</td><td><button class="btn btn-danger btn-xs" type="button" data-p-del="' + p.id + '" aria-label="Ištrinti ' + esc(p.name) + '">×</button></td></tr>';
      }).join('') + '</tbody></table></div>' + (list.length ? '' : '<p class="muted" style="padding:12px 14px;">Pagal filtrą dalyvių nėra.</p>');
  }
  ['ppl-search', 'ppl-nocat'].forEach(function (id) { document.getElementById(id).addEventListener('input', function () { renderPeople(); }); });
  document.getElementById('ppl-club').addEventListener('change', function (e) { pplClub = e.target.value; renderPeople(); });
  document.getElementById('ppl-add').addEventListener('click', function () {
    var inp = document.getElementById('ppl-new-name'), n = inp.value.trim(); if (!n) { inp.focus(); return; }
    snapshot(); var id = newId('c'); S.people[id] = { id: id, name: n, club: document.getElementById('ppl-new-club').value, birth: null, gender: null, belt: null, member: null };
    inp.value = ''; save(); render();
  });
  document.getElementById('people-view').addEventListener('change', function (e) {
    var t = e.target;
    if (t.dataset.pName && t.value.trim()) { snapshot(); updatePerson(t.dataset.pName, { name: t.value.trim() }); }
    else if (t.dataset.pClub) { snapshot(); updatePerson(t.dataset.pClub, { club: t.value }); }
    else if (t.dataset.pBirth) { snapshot(); S.people[t.dataset.pBirth].birth = +t.value || null; toastPlaced([t.dataset.pBirth]); }
    else if (t.dataset.pGender) { snapshot(); S.people[t.dataset.pGender].gender = t.value || null; toastPlaced([t.dataset.pGender]); }
    else if (t.dataset.pBelt) { snapshot(); S.people[t.dataset.pBelt].belt = +t.value || null; toastPlaced([t.dataset.pBelt]); }
    else if (t.dataset.pAdd && t.value) { snapshot(); addToCat(t.dataset.pAdd, t.value); }
    else return;
    save(); renderSoon();
  });
  document.getElementById('people-view').addEventListener('click', function (e) {
    var t = e.target;
    if (t.dataset.pDel) {
      var p = S.people[t.dataset.pDel];
      if (!p || !confirm('Ištrinti dalyvį ' + p.name + ' iš varžybų (ir visų kategorijų)?')) return;
      snapshot();
      S.categories.forEach(function (c) { c.competitors = c.competitors.filter(function (x) { return x.id !== p.id; }); });
      Object.keys(S.teams).forEach(function (k) { S.teams[k].members = S.teams[k].members.filter(function (m) { return m !== p.id; }); });
      delete S.people[p.id];
      save(); render(); return;
    }
    if (!t.dataset.pRm) return;
    snapshot(); removeFromCat(t.dataset.pRm, t.dataset.c); save(); render();
  });

  // ---------- Dalyvis - į tinkamas grupes (pakeitus gimimo metus, lytį ar diržą, pridėjus naują) ----------
  // Jau esantis grupėse perkeliamas tos pačios rungties tinkamiausią grupę; be grupių - pridedamas į tinkamas visų rungčių grupes.
  // Grupės, kuriose kovos jau vyksta, nekeičiamos.
  function hasCrit(c) { var k = crit(c); return !!(k.g || k.y0 || k.y1 || k.b0 || k.b1); }
  function catStarted(c) { return !!(c.draw && (Object.keys(c.draw.results || {}).length || (!isElim(c.draw.system) && scoreDone(c)))); }
  function catSpan(c) { var k = crit(c); return ((k.y1 || YEAR) - (k.y0 || 1900)) * 10 + ((k.b1 || 20) - (k.b0 || 0)); }
  function autoPlace(id) {
    var p = S.people[id]; if (!p || !p.birth || !p.gender) return [];   // be gimimo metų ir lyties - nespėliojama
    var mine = catsOf(id).filter(function (c) { return !c.team; }), out = [], discs = {};
    if (mine.length) mine.forEach(function (c) { discs[c.discipline] = true; });
    else S.categories.forEach(function (c) { if (!c.team && hasCrit(c) && fits(c, p)) discs[c.discipline] = true; });
    Object.keys(discs).forEach(function (d) {
      var inD = mine.filter(function (c) { return c.discipline === d; });
      if (inD.some(function (c) { return fits(c, p); }) || inD.some(catStarted)) return;
      var target = S.categories.filter(function (c) { return !c.team && c.discipline === d && hasCrit(c) && fits(c, p) && !catStarted(c); })
        .sort(function (x, y) { return catSpan(x) - catSpan(y); })[0];
      if (!target) { if (inD.length) out.push('tinkamos ' + (DISC[d] || d) + ' grupės nėra - liko ' + inD.map(function (c) { return c.name; }).join(', ')); return; }
      inD.forEach(function (c) { removeFromCat(id, c.id); });
      addToCat(id, target.id);
      out.push((inD.length ? inD.map(function (c) { return c.name; }).join(', ') + ' → ' : '+ ') + target.name);
    });
    return out;
  }
  var toastEl = null, toastTimer = null;
  function toast(msg) {
    if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'vz-toast'; toastEl.setAttribute('role', 'status'); document.body.appendChild(toastEl); }
    toastEl.innerHTML = msg; toastEl.hidden = false;
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { toastEl.hidden = true; }, 6000);
  }
  function toastPlaced(ids) {
    var lines = [];
    ids.forEach(function (id) { autoPlace(id).forEach(function (x) { lines.push('<b>' + esc(S.people[id].name) + '</b>: ' + esc(x)); }); });
    if (lines.length) toast('Grupės pakeistos automatiškai (atšaukti - „Atšaukti“ Lentelėse):<br>' + lines.slice(0, 6).join('<br>') + (lines.length > 6 ? '<br>… ir dar ' + (lines.length - 6) : ''));
  }

  // ---------- Komandos (kaip Kihapp „Teams“) ----------
  function updateTeam(id, fields) {
    Object.assign(S.teams[id], fields);
    S.categories.forEach(function (c) { c.competitors.forEach(function (p) { if (p.id === id) Object.assign(p, { name: S.teams[id].name, club: S.teams[id].club }); }); });
  }
  var teamClub = '';
  function teamCats() { return S.categories.filter(function (c) { return c.team; }); }
  function renderTeams() {
    if (teamClub !== null) teamClub = teamClub || S.clubs[0] || '';
    var tc = teamClub || '';   // '' - visi klubai (mišri komanda)
    document.getElementById('team-club').innerHTML = '<option value=""' + (tc === '' ? ' selected' : '') + '>Visi klubai (mišri komanda)</option>' +
      S.clubs.map(function (k) { return '<option' + (k === tc ? ' selected' : '') + '>' + esc(k) + '</option>'; }).join('');
    document.getElementById('team-cat').innerHTML = teamCats().map(function (c) { return '<option value="' + c.id + '">' + esc(c.name) + '</option>'; }).join('') || '<option value="">Nėra komandinių kategorijų</option>';
    var members = Object.keys(S.people).map(function (id) { return S.people[id]; }).filter(function (p) { return !tc || p.club === tc; })
      .sort(function (a, b) { return (tc ? 0 : a.club.localeCompare(b.club, 'lt')) || a.name.localeCompare(b.name, 'lt'); });
    document.getElementById('team-pick').innerHTML = '<h2>Pasirinkite narius (' + esc(tc || 'visi klubai') + ')<span class="spacer"></span><span class="muted small">Pažymėjimo tvarka = kovų tvarka</span></h2><div style="display:flex; flex-wrap:wrap; gap:6px 14px; padding:12px 14px;">' +
      (members.map(function (p) { return '<label class="small"><input type="checkbox" class="team-member" value="' + p.id + '"> ' + esc(p.name) + (tc ? '' : ' <span class="muted">· ' + esc(p.club) + '</span>') + '</label>'; }).join('') || '<span class="muted">Šiame klube dalyvių nėra.</span>') + '</div>';
    var list = Object.keys(S.teams).map(function (id) { return S.teams[id]; }).sort(function (a, b) { return a.club.localeCompare(b.club, 'lt') || a.name.localeCompare(b.name, 'lt'); });
    var catOpts = teamCats().map(function (c) { return '<option value="' + c.id + '">' + esc(c.name) + '</option>'; }).join('');
    document.getElementById('teams-view').innerHTML = '<h2>Komandos (' + list.length + ')</h2><table><thead><tr><th>Pavadinimas</th><th>Klubas</th><th>Nariai</th><th>Kategorijos</th><th></th></tr></thead><tbody>' +
      list.map(function (t) {
        var cats = S.categories.filter(function (c) { return c.competitors.some(function (x) { return x.id === t.id; }); });
        return '<tr><td><input class="inline" value="' + esc(t.name) + '" data-t-name="' + t.id + '" aria-label="Komandos pavadinimas"></td><td>' + esc(t.club) + '</td>' +
          '<td>' + t.members.map(function (m) { return '<span class="cchip">' + esc(S.people[m] ? S.people[m].name : '?') + '</span>'; }).join('') + '</td>' +
          '<td>' + cats.map(function (c) { return '<span class="cchip">' + esc(c.name) + '<button type="button" data-t-rm="' + t.id + '" data-c="' + c.id + '" aria-label="Išimti">×</button></span>'; }).join('') +
          '<select class="sel" data-t-add="' + t.id + '" aria-label="Pridėti į kategoriją"><option value="">+ kategorija…</option>' + catOpts + '</select></td>' +
          '<td><button class="btn btn-danger" type="button" data-t-del="' + t.id + '">Ištrinti</button></td></tr>';
      }).join('') + '</tbody></table>';
  }
  document.getElementById('team-club').addEventListener('change', function (e) { teamClub = e.target.value === '' ? null : e.target.value; renderTeams(); });
  // Pažymėjimo tvarka išsaugoma (komandiniame kumite - kas kovoja pirmas, antras, trečias)
  var teamPickOrder = [];
  document.getElementById('team-pick').addEventListener('change', function (e) {
    if (!e.target.classList.contains('team-member')) return;
    teamPickOrder = teamPickOrder.filter(function (x) { return x !== e.target.value; });
    if (e.target.checked) teamPickOrder.push(e.target.value);
  });
  document.getElementById('team-add').addEventListener('click', function () {
    var checked = [].slice.call(document.querySelectorAll('.team-member:checked')).map(function (x) { return x.value; });
    var ids = teamPickOrder.filter(function (x) { return checked.indexOf(x) !== -1; }).concat(checked.filter(function (x) { return teamPickOrder.indexOf(x) === -1; }));
    if (ids.length < 2) { alertBox('Pažymėkite bent 2 komandos narius.'); return; }
    snapshot();
    // Mišri komanda - klubų pavadinimai per „+“ (medalių lentelėje - kaip atskiras klubas)
    var clubs = []; ids.forEach(function (m) { if (clubs.indexOf(S.people[m].club) === -1) clubs.push(S.people[m].club); });
    var club = clubs.join(' + ');
    var id = newId('t'), name = document.getElementById('team-name').value.trim() || (clubs.length > 1 ? 'Mišri' : club) + ' (' + ids.map(function (m) { return S.people[m].name.split(' ')[0]; }).join(', ') + ')';
    S.teams[id] = { id: id, name: name, club: club, members: ids };
    teamPickOrder = [];
    var catId = document.getElementById('team-cat').value, c = cat(catId);
    if (c) c.competitors.push({ id: id, name: name, club: club, team: true });
    document.getElementById('team-name').value = '';
    save(); render();
  });
  document.getElementById('team-cat-new').addEventListener('click', function () {
    snapshot(); var id = 'k' + Date.now();
    S.categories.push({ id: id, name: 'Komandos (nauja kategorija)', discipline: 'kumite', team: true, competitors: [], tatami: S.areas[0].id, draw: null });
    save(); render();
  });
  document.getElementById('teams-view').addEventListener('change', function (e) {
    var t = e.target;
    if (t.dataset.tName && t.value.trim()) { snapshot(); updateTeam(t.dataset.tName, { name: t.value.trim() }); }
    else if (t.dataset.tAdd && t.value) { var c = cat(t.value), tm = S.teams[t.dataset.tAdd]; snapshot(); if (!c.competitors.some(function (x) { return x.id === tm.id; })) c.competitors.push({ id: tm.id, name: tm.name, club: tm.club, team: true }); }
    else return;
    save(); renderSoon();
  });
  document.getElementById('teams-view').addEventListener('click', function (e) {
    var t = e.target;
    if (t.dataset.tRm) { snapshot(); removeFromCat(t.dataset.tRm, t.dataset.c); }
    else if (t.dataset.tDel) { snapshot(); S.categories.forEach(function (c) { c.competitors = c.competitors.filter(function (x) { return x.id !== t.dataset.tDel; }); }); delete S.teams[t.dataset.tDel]; }
    else return;
    save(); render();
  });
  function alertBox(msg) { var el = document.getElementById('team-pick'); var p = document.createElement('p'); p.className = 'pill warn'; p.style.margin = '0 14px 12px'; p.textContent = msg; el.appendChild(p); }

  // ---------- Lentelės ----------
  var swapMode = false, picked = null;
  function slotHtml(m, side, c, clickable, firstRound, extra) {
    var id = side === 'a' ? m.a : m.b, p = id ? person(c, id) : null;
    var cls = 'slot ' + (side === 'b' ? 'shiro' : 'aka') + (m.winner && m.winner === id && !m.bye ? ' win' : '') + (!p ? ' empty' : '') + (picked && picked === id ? ' picked' : '');
    var label = p ? '<b>' + esc(p.name) + '</b><span class="club">' + esc(p.club) + '</span>' : '<b>' + (m.wait || m.rep ? 'Laukiama' : 'Laisva vieta') + '</b><span class="club">&nbsp;</span>';
    // Pirmame rate, kol nėra rezultatų ir neužrakinta - dalyvius galima sukeisti tempiant
    var si = firstRound ? c.draw.slots.indexOf(id) : -1;
    var dnd = firstRound && !c.draw.locked && !Object.keys(c.draw.results).length;
    var slotIdx = firstRound ? (side === 'a' ? 2 * +m.id.replace('r0m', '') : 2 * +m.id.replace('r0m', '') + 1) : -1;
    return '<div class="' + cls + (dnd ? ' dnd' : '') + (extra ? ' has-extra' : '') + '"' + (dnd ? ' data-drop-slot="' + slotIdx + '"' : '') + '><span class="stripe"></span><button type="button" data-m="' + m.id + '" data-p="' + (id || '') + '"' +
      (dnd && p ? ' draggable="true" data-drag-slot="' + si + '"' : '') + (clickable && p ? '' : ' disabled') + '>' + label + '</button>' + (extra || '') + '</div>';
  }
  // Vėliavėlės: kiek vėliavėlių gavo laimėjęs (3 teisėjai - 2 ar 3, 5 - 3, 4 ar 5); balas įrašomas „4:1“
  function flagChips(c, m, side) {
    var J = cfg(c).judges, sc = parseScore((c.draw.scores || {})[m.id]), mine = sc ? (side === 'a' ? sc[0] : sc[1]) : null, won = c.draw.results[m.id] === (side === 'a' ? m.a : m.b);
    var out = '<span class="flags" title="Keliomis vėliavėlėmis laimėjo">';
    for (var n = Math.floor(J / 2) + 1; n <= J; n++) out += '<button type="button" class="flag-chip' + (won && mine === n ? ' on' : '') + '" data-flag="' + m.id + '|' + side + '|' + n + '">' + n + '</button>';
    return out + '</span>';
  }
  function scoreInput(c, m) {
    var v = (c.draw.scores || {})[m.id] || '';
    return '<input class="m-score" data-score="' + m.id + '" value="' + esc(v) + '" placeholder="balai" title="Kovos rezultatas, pvz. 3:1 ar vėliavėlės 2:1" aria-label="Kovos rezultatas">';
  }
  function matchHtml(m, c, title, firstRound) {
    if (m.bye && !(swapMode && firstRound) && !firstRound) return '<div class="match ghost" aria-hidden="true"><div class="match-label"><span>&nbsp;</span></div>' + slotHtml(m, 'a', c, false) + slotHtml(m, 'b', c, false) + '</div>';
    if (m.bye && firstRound && (c.draw.locked || Object.keys(c.draw.results).length)) return '<div class="match ghost" aria-hidden="true"><div class="match-label"><span>&nbsp;</span></div>' + slotHtml(m, 'a', c, false) + slotHtml(m, 'b', c, false) + '</div>';
    var canWin = !swapMode && m.a && m.b;
    var canSwap = swapMode && firstRound && !c.draw.locked;
    var mode = boutMode(c, m), ready = !m.bye && m.a && m.b && !swapMode, flags = ready && mode === 'flags';
    var right = m.bye ? '<span>Laisvas ratas</span>' : !(m.a && m.b) ? '' : flags ? '<span class="m-flags-score">' + esc((c.draw.scores || {})[m.id] || 'vėliavėlės') + '</span>'
      : scoreInput(c, m) + '<button type="button" class="bout-open" data-bout="' + m.id + '" title="Kova: balai ir laikrodis' + (CFG.readOnly ? '' : ', ekranas') + '">▶</button>';
    var live = liveMatch && liveMatch.cat === c.id && liveMatch.mid === m.id;
    return '<div class="match' + (m.bye ? ' is-bye' : '') + (m.winner && !m.bye ? ' is-done' : '') + (live ? ' is-live' : '') + '"><div class="match-label"><span>' + (m.no ? m.no + ' · ' : '') + esc(title) + (mode === 'flags' && c.discipline === 'fukugo' ? ' · Kitei' : mode === 'kumite' && c.discipline === 'fukugo' ? ' · Kumite' : '') + '</span>' + right + '</div>' +
      slotHtml(m, 'a', c, canWin || canSwap, firstRound, flags ? flagChips(c, m, 'a') : '') + slotHtml(m, 'b', c, canWin || canSwap, firstRound, flags ? flagChips(c, m, 'b') : '') + '</div>';
  }
  function placesHtml(c, places, empty) {
    return '<div class="podium"><h3>Vietos</h3>' + (places.length ? places.map(function (pl) {
      return pl[1].map(function (id) { var p = person(c, id); return '<div class="place p' + Math.min(pl[0], 4) + '"><span>' + pl[0] + '</span><span><b>' + esc(p ? p.name : '?') + '</b> <span class="muted">' + esc(p ? p.club : '') + '</span></span></div>'; }).join('');
    }).join('') : '<p class="muted small">' + empty + '</p>') + '</div>';
  }
  function drawHead(c, done, all) {
    return '<div class="draw-head"><h2><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + '</h2><span class="pill">' + c.competitors.length + ' dalyv.</span>' +
      '<span class="pill sys">' + esc(SYSTEMS[c.draw.system][0]) + '</span><span class="pill ' + (done === all && all ? 'ok' : '') + '">' + (c.draw.system === 'score' ? 'Pasirodymai' : 'Kovos') + ': ' + done + ' / ' + all + '</span>' +
      (drawStale(c) ? '<span class="pill warn">Dalyviai pasikeitė – spauskite „Burti iš naujo“</span>' : '') + '</div>';
  }
  function renderElim(c, view) {
    var b = bracket(c), all = allMatches(b), done = all.filter(function (m) { return m.winner && m.loser; }).length;
    var html = drawHead(c, done, all.length);
    html += '<div class="bracket-wrap"><div class="bracket">' + b.rounds.map(function (rd, r) {
      var title = roundName(r, b.R);
      return '<div class="round"><h3>' + title + '</h3><div class="round-body">' + rd.map(function (m) { return matchHtml(m, c, title, r === 0); }).join('') + '</div></div>';
    }).join('') + '</div>';
    if (b.rep.length) {
      html += '<div class="rep-wrap">' + b.rep.map(function (g) {
        return '<div class="rep-col"><h3>' + esc(g.title) + '</h3>' + (g.ready ? (g.matches.length ? g.matches.map(function (m) { return matchHtml(m, c, g.title.indexOf('3') >= 0 ? 'Bronza' : 'Paguoda', false); }).join('') : '<p class="muted small">Paguodos kovų nereikia – bronza be kovos.</p>') : '<p class="muted small">Prasidės, kai paaiškės finalininkai.</p>') + '</div>';
      }).join('') + '</div>';
    }
    html += '</div><div class="draw-foot">' + placesHtml(c, b.places, 'Vietos atsiras, kai baigsis finalas.');
    html += '<p class="hint">' + (swapMode ? 'Sukeitimo režimas: spauskite du dalyvius pirmame rate – jie apsikeis vietomis.' : 'Spauskite dalyvio vardą kovoje – jis laimi (dar kartą – atšaukiama). Balus įrašykite kovos viršuje. Kol kovų nebuvo, dalyvius pirmame rate galima <b>sukeisti tempiant</b>. Raudonas – aka, baltas – shiro.') + '</p></div>';
    view.innerHTML = html;
  }
  function renderRR(c, view) {
    var ms = rrMatches(c), st = rrStandings(c), done = ms.filter(function (m) { return m.winner; }).length;
    var html = drawHead(c, done, ms.length) + '<div class="rr-layout"><div class="rr-matches">';
    var round = 0;
    ms.forEach(function (m) {
      if (m.round !== round) { round = m.round; html += '<h3 class="rr-round">' + round + ' ratas</h3>'; }
      html += matchHtml(m, c, 'Kova', false);
    });
    html += '</div><div class="panel rr-table"><h2>Lentelė</h2><table><thead><tr><th class="num">#</th><th>Dalyvis</th><th class="num">Perg.</th><th class="num">Pralaim.</th><th class="num">+/−</th></tr></thead><tbody>' +
      st.map(function (x, i) { var p = person(c, x.id); return '<tr><td class="num">' + (i + 1) + '</td><td><b>' + esc(p.name) + '</b> <span class="muted small">' + esc(p.club) + '</span></td><td class="num"><b>' + x.w + '</b></td><td class="num">' + x.l + '</td><td class="num">' + (x.diff > 0 ? '+' : '') + x.diff + '</td></tr>'; }).join('') +
      '</tbody></table></div></div>';
    html += '<div class="draw-foot">' + placesHtml(c, catPlaces(c), 'Vietos atsiras, kai įvyks visos kovos.') +
      '<p class="hint">Visi kovoja su visais. Vietos: pergalės → tarpusavio kova → balų skirtumas (iš kovos balų, pvz. 3:1).</p></div>';
    view.innerHTML = html;
  }
  function scoreTable(c, ids, which, title) {
    var f = cfg(c), src = which === 'final' ? (c.draw.final = c.draw.final || {}) : (c.draw.scores = c.draw.scores || {});
    var rank = scoreRank(c, which, ids), placeOf = {};
    rank.forEach(function (x, i) { if (x.total !== null) placeOf[x.id] = i + 1; });
    var head = '<tr><th class="num">Eil.</th><th>Dalyvis</th>';
    for (var j = 0; j < f.judges; j++) head += '<th class="num">T' + (j + 1) + '</th>';
    head += '<th class="num">Suma' + (f.drop && f.judges >= 5 ? '*' : '') + '</th><th class="num">Vieta</th></tr>';
    return '<div class="panel score-panel"><h2>' + esc(title) + '</h2><div class="table-scroll"><table class="score-table"><thead>' + head + '</thead><tbody>' +
      ids.map(function (id, i) {
        var p = person(c, id), arr = src[id] || [], t = scoreTotal(c, arr), drop = droppedIdx(c, arr), row = '<tr' + (t !== null ? ' class="scored"' : '') + '><td class="num">' + (i + 1) + '</td><td><b>' + esc(p.name) + '</b> <span class="muted small">' + esc(p.club) + '</span></td>';
        for (var j = 0; j < f.judges; j++) row += '<td class="num"><input class="j-in' + (drop.indexOf(j) >= 0 ? ' dropped' : '') + '" type="number" step="0.1" min="0" max="10" inputmode="decimal" data-j="' + j + '" data-sp="' + id + '" data-which="' + which + '" value="' + (arr[j] !== undefined && arr[j] !== null ? esc(arr[j]) : '') + '" aria-label="Teisėjas ' + (j + 1) + '"></td>';
        return row + '<td class="num"><b>' + (t === null ? '–' : t.toFixed(1)) + '</b></td><td class="num">' + (placeOf[id] ? '<span class="rank r' + Math.min(placeOf[id], 4) + '">' + placeOf[id] + '</span>' : '') + '</td></tr>';
      }).join('') + '</tbody></table></div></div>';
  }
  function renderScore(c, view) {
    var f = cfg(c), ids = (c.draw.order || []).filter(function (id) { return person(c, id); });
    var html = drawHead(c, scoreDone(c), matchCount(c));
    html += '<div class="score-cfg"><label>Teisėjai <select class="sel" data-cfg="judges">' + [3, 5, 7].map(function (n) { return '<option' + (n === f.judges ? ' selected' : '') + '>' + n + '</option>'; }).join('') + '</select></label>' +
      '<label><input type="checkbox" data-cfg="drop"' + (f.drop ? ' checked' : '') + (f.judges < 5 ? ' disabled' : '') + '> Atmesti aukščiausią ir žemiausią balą</label>' +
      '<label>Ratai <select class="sel" data-cfg="rounds"><option value="1"' + (f.rounds === 1 ? ' selected' : '') + '>1</option><option value="2"' + (f.rounds === 2 ? ' selected' : '') + '>2 (finalas)</option></select></label>' +
      (f.rounds === 2 ? '<label>Į finalą <input class="sel num-in" type="number" min="2" max="16" data-cfg="finalists" value="' + f.finalists + '"></label>' : '') + '</div>';
    html += scoreTable(c, ids, 'scores', f.rounds === 2 ? '1 ratas' : 'Balai');
    if (f.rounds === 2) {
      var fin = scoreFinalists(c);
      html += fin.length ? scoreTable(c, fin, 'final', 'Finalas (' + fin.length + ' geriausi)') : '<p class="muted small">Finalas atsiras, kai visi gaus 1 rato balus.</p>';
    }
    html += '<div class="draw-foot">' + placesHtml(c, catPlaces(c), 'Vietos atsiras, kai visi gaus balus.') +
      '<p class="hint">Įrašykite kiekvieno teisėjo balą (pvz. 7.5). ' + (f.drop && f.judges >= 5 ? '* Suma be aukščiausio ir žemiausio balo - jie lieka matomi perbraukti. ' : '') + 'Lygiųjų atveju lemia 1 rato suma, tada visų balų suma. Eilės tvarka - burtų; „Burti iš naujo“ ją sumaišo.</p></div>';
    view.innerHTML = html;
  }
  function renderDraw() {
    var sel = document.getElementById('draw-cat');
    sel.innerHTML = S.categories.map(function (c) { return '<option value="' + c.id + '"' + (c.id === S.selected ? ' selected' : '') + '>' + esc(c.name) + ' (' + c.competitors.length + ')</option>'; }).join('');
    var c = cat(S.selected), view = document.getElementById('draw-view');
    if (!c) { view.innerHTML = '<p class="muted">Kategorijų dar nėra.</p>'; return; }
    if (!c.draw || (drawStale(c) && !c.draw.locked)) {
      if (c.competitors.length < 2) { view.innerHTML = '<p class="muted">Kategorijoje per mažai dalyvių lentelei.</p>'; return; }
    }
    if (!c.draw) c.draw = makeDraw(c, defaultSystem(c));
    c.draw.scores = c.draw.scores || {};
    // Pasikeitė dalyviai, o kovų dar nebuvo - burtai traukiami iš naujo automatiškai
    var started = Object.keys(c.draw.results).length || (!isElim(c.draw.system) && scoreDone(c));
    if (drawStale(c) && !c.draw.locked && !started) { c.draw = makeDraw(c, c.draw.system); save(); }
    document.getElementById('draw-system').value = c.draw.system;
    document.getElementById('draw-swap').setAttribute('aria-pressed', swapMode ? 'true' : 'false');
    document.getElementById('draw-swap').hidden = !isElim(c.draw.system);
    document.getElementById('draw-lock').setAttribute('aria-pressed', c.draw.locked ? 'true' : 'false');
    document.getElementById('draw-lock').textContent = c.draw.locked ? 'Užrakinta' : 'Užrakinti';
    document.getElementById('draw-new').disabled = c.draw.locked;
    document.getElementById('draw-swap').disabled = c.draw.locked;
    document.getElementById('draw-undo').disabled = !S.history.length;
    if (c.draw.system === 'rr') renderRR(c, view);
    else if (c.draw.system === 'score') renderScore(c, view);
    else renderElim(c, view);
  }
  // Sistemos keitimas: olimpinės tarpusavyje - rezultatai lieka; į ratų ar balų - nauji burtai
  function setSystem(c, sys) {
    if (!c || !SYSTEMS[sys] || (c.draw && c.draw.system === sys)) return;
    var started = c.draw && (Object.keys(c.draw.results || {}).length || (!isElim(c.draw.system) && scoreDone(c)));
    var same = c.draw && isElim(c.draw.system) && isElim(sys);
    if (started && !same && !confirm('Pakeitus sistemą, jau įrašyti kovų rezultatai ar balai dings. Tęsti?')) { render(); return; }
    snapshot();
    if (same) { c.draw.system = sys; prune(c); } else if (c.competitors.length >= 2) c.draw = makeDraw(c, sys); else c.draw = makeDraw(c, sys);
    save(); render();
  }
  // Rungtis ar „komandinė“ pakeista: numatyta sistema, jei kovų dar nebuvo
  function applyDefaultSystem(c) {
    var sys = defaultSystem(c);
    var started = c.draw && (Object.keys(c.draw.results || {}).length || (!isElim(c.draw.system) && scoreDone(c)));
    if (started || (c.draw && c.draw.system === sys)) return;
    if (c.draw && isElim(c.draw.system) && isElim(sys)) { c.draw.system = sys; return; }
    c.draw = c.competitors.length >= 2 ? makeDraw(c, sys) : null;
  }
  document.getElementById('draw-cat').addEventListener('change', function (e) { S.selected = e.target.value; picked = null; save(); render(); });
  document.getElementById('draw-system').addEventListener('change', function (e) { setSystem(cat(S.selected), e.target.value); });
  document.getElementById('draw-new').addEventListener('click', function () { var c = cat(S.selected); if (c.competitors.length < 2) return; snapshot(); c.draw = makeDraw(c, c.draw ? c.draw.system : defaultSystem(c)); picked = null; save(); render(); });
  document.getElementById('draw-swap').addEventListener('click', function () { swapMode = !swapMode; picked = null; render(); });
  document.getElementById('draw-lock').addEventListener('click', function () { var c = cat(S.selected); c.draw.locked = !c.draw.locked; swapMode = false; save(); render(); });
  document.getElementById('draw-clear').addEventListener('click', function () {
    var c = cat(S.selected); if (!confirm('Išvalyti visus šios kategorijos rezultatus ir balus?')) return;
    snapshot(); c.draw.results = {}; c.draw.scores = {}; c.draw.final = {}; save(); render();
  });
  document.getElementById('draw-undo').addEventListener('click', function () { undo(); save(); render(); });
  document.getElementById('draw-view').addEventListener('click', function (e) {
    var fl = e.target.closest('[data-flag]');
    if (fl) {
      var c0 = cat(S.selected), parts = fl.dataset.flag.split('|'), m0 = findMatch(c0, parts[0]); if (!m0 || !m0.a || !m0.b) return;
      var J = cfg(c0).judges, n = +parts[2], wid = parts[1] === 'a' ? m0.a : m0.b, txt = parts[1] === 'a' ? n + ':' + (J - n) : (J - n) + ':' + n;
      snapshot();
      if (c0.draw.results[m0.id] === wid && c0.draw.scores[m0.id] === txt) { delete c0.draw.results[m0.id]; delete c0.draw.scores[m0.id]; }
      else { c0.draw.results[m0.id] = wid; c0.draw.scores[m0.id] = txt; }
      prune(c0); save(); render(); return;
    }
    var bo = e.target.closest('[data-bout]');
    if (bo) { openBout(S.selected, bo.dataset.bout); return; }
    var btn = e.target.closest('button[data-m]'); if (!btn || btn.disabled) return;
    var c = cat(S.selected), pid = btn.dataset.p, mid = btn.dataset.m;
    if (swapMode) {
      if (!picked) { picked = pid; render(); return; }
      if (picked === pid) { picked = null; render(); return; }
      snapshot();
      var s = c.draw.slots, i = s.indexOf(picked), j = s.indexOf(pid);
      s[i] = pid; s[j] = picked; picked = null; c.draw.results = {};
      save(); render(); return;
    }
    snapshot();
    if (c.draw.results[mid] === pid) delete c.draw.results[mid]; else c.draw.results[mid] = pid;
    prune(c); save(); render();
  });
  document.getElementById('draw-view').addEventListener('change', function (e) {
    var t = e.target, c = cat(S.selected); if (!c) return;
    if (t.dataset.score) { c.draw.scores = c.draw.scores || {}; if (t.value.trim()) c.draw.scores[t.dataset.score] = t.value.trim(); else delete c.draw.scores[t.dataset.score]; save(); if (c.draw.system === 'rr') render(); return; }
    if (t.dataset.sp) {
      var src = t.dataset.which === 'final' ? (c.draw.final = c.draw.final || {}) : (c.draw.scores = c.draw.scores || {});
      var arr = src[t.dataset.sp] = src[t.dataset.sp] || [], v = t.value.replace(',', '.');
      arr[+t.dataset.j] = v === '' ? null : Math.max(0, Math.min(10, parseFloat(v)));
      save(); render();
      var next = document.querySelector('[data-sp="' + t.dataset.sp + '"][data-which="' + t.dataset.which + '"][data-j="' + (+t.dataset.j + 1) + '"]');
      if (next) next.focus();
      return;
    }
    if (t.dataset.cfg) {
      var f = cfg(c), k = t.dataset.cfg;
      snapshot();
      if (k === 'drop') f.drop = t.checked; else f[k] = Math.max(1, parseInt(t.value, 10) || 1);
      save(); render();
    }
  });
  // Tempimas pirmame rate: sukeičiami du dalyviai (ar dalyvis perkeliamas į laisvą vietą)
  var slotDrag = null;
  document.getElementById('draw-view').addEventListener('dragstart', function (e) {
    var b = e.target.closest && e.target.closest('[data-drag-slot]'); if (!b) return;
    slotDrag = +b.dataset.dragSlot; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', 'slot'); } catch (x) {}
    b.closest('.slot').classList.add('dragging');
  });
  document.getElementById('draw-view').addEventListener('dragover', function (e) {
    if (slotDrag === null) return; var t = e.target.closest('[data-drop-slot]'); if (!t) return;
    e.preventDefault(); document.querySelectorAll('.slot.drop').forEach(function (x) { x.classList.remove('drop'); }); t.classList.add('drop');
  });
  document.getElementById('draw-view').addEventListener('drop', function (e) {
    var t = e.target.closest('[data-drop-slot]'); if (slotDrag === null || !t) return; e.preventDefault();
    var c = cat(S.selected), s = c.draw.slots, j = +t.dataset.dropSlot, i = slotDrag;
    slotDrag = null;
    if (i === j) { render(); return; }
    snapshot(); var x = s[i]; s[i] = s[j]; s[j] = x; c.draw.results = {}; save(); render();
  });
  document.getElementById('draw-view').addEventListener('dragend', function () { slotDrag = null; document.querySelectorAll('.slot.drop, .slot.dragging').forEach(function (x) { x.classList.remove('drop', 'dragging'); }); });

  // ---------- Kova: balai, laikrodis, transliacija į ekraną ----------
  // Atidaroma ▶ prie kovos. Kumite: +1/+2/+3/−1 ir laikrodis; komandinis kumite - 3 kovos, balai sumuojasi;
  // sumo - kas pirmas surenka N taškų; komandinės katos finalas - Kata ir Pritaikymas balais (kraštiniai atmetami, matosi perbraukti).
  var liveMatch = null, B = null;
  function findMatch(c, mid) {
    if (!c || !c.draw) return null;
    var list = c.draw.system === 'rr' ? rrMatches(c) : isElim(c.draw.system) ? allMatches(bracket(c)) : [];
    return list.find(function (m) { return m.id === mid; }) || null;
  }
  function leftName(L) { return L === 1 ? 'Finalas' : L === 2 ? 'Pusfinalis' : L === 3 ? 'Ketvirtfinalis' : '1/' + Math.pow(2, L - 1); }
  function matchTitle(c, m) {
    var t = m.left ? leftName(m.left) : m.id === 'bronze' ? 'Kova dėl 3 vietos' : m.rep ? 'Paguoda' : m.round ? m.round + ' ratas' : 'Kova';
    var mode = boutMode(c, m);
    return t + (c.discipline === 'fukugo' ? (mode === 'flags' ? ' · Kitei' : ' · Kumite') : '');
  }
  // Viešai (ekranas, žiūrovų nuoroda): vaikų ir jaunimo - vardas ir pavardės raidė
  function pubName(id) {
    if (S.teams[id]) return S.teams[id].name;
    var p = S.people[id]; if (!p) return '';
    if (!(p.birth && YEAR - p.birth < 18)) return p.name;
    var w = p.name.split(' '); return w[0] + (w[1] ? ' ' + w[1].charAt(0) + '.' : '');
  }
  function areaOf(c) { return S.areas.find(function (a) { return a.id === c.tatami; }) || S.areas[0]; }
  function judgeTotal(c, arr) {
    var J = cfg(c).judges, v = (arr || []).slice(0, J).map(function (x) { return parseFloat(x); });
    if (v.length < J || v.some(function (x) { return isNaN(x); })) return null;
    var sum = v.reduce(function (a, b) { return a + b; }, 0);
    if (cfg(c).drop && J >= 5) sum -= Math.max.apply(null, v) + Math.min.apply(null, v);
    return Math.round(sum * 100) / 100;
  }
  // Kurie teisėjų balai atmesti (aukščiausias ir žemiausias) - jie rodomi perbraukti, bet nedingsta
  function droppedIdx(c, arr) {
    var J = cfg(c).judges, v = (arr || []).slice(0, J).map(function (x) { return parseFloat(x); });
    if (!cfg(c).drop || J < 5 || v.length < J || v.some(function (x) { return isNaN(x); })) return [];
    var hi = v.indexOf(Math.max.apply(null, v)), lo = v.indexOf(Math.min.apply(null, v));
    if (lo === hi) lo = v.indexOf(Math.min.apply(null, v), hi + 1);
    return [hi, lo];
  }
  function teamMembers(id) { return S.teams[id] ? S.teams[id].members.filter(function (x) { return S.people[x]; }) : []; }

  var boutEl = document.createElement('div');
  boutEl.className = 'bout'; boutEl.id = 'bout'; boutEl.hidden = true;
  boutEl.setAttribute('role', 'dialog'); boutEl.setAttribute('aria-modal', 'true'); boutEl.setAttribute('aria-label', 'Kova');
  document.body.appendChild(boutEl);

  function openBout(catId, mid) {
    var c = cat(catId), m = findMatch(c, mid); if (!m || !m.a || !m.b) return;
    var mode = boutMode(c, m), f = cfg(c), saved = (c.draw.bouts || {})[mid] || {};
    B = { cat: c.id, mid: mid, mode: mode, a: m.a, b: m.b, title: matchTitle(c, m), no: m.no || '',
      sa: saved.sa || 0, sb: saved.sb || 0, log: saved.log || [], i: saved.i || 0,
      ma: saved.ma || teamMembers(m.a), mb: saved.mb || teamMembers(m.b),
      parts: saved.parts || { a: { k: [], p: [] }, b: { k: [], p: [] } },
      timer: { total: f.time * 1000, remaining: saved.remaining !== undefined ? saved.remaining : f.time * 1000, running: false, at: Date.now() }, winner: null };
    liveMatch = { cat: c.id, mid: mid };
    boutEl.hidden = false; document.body.classList.add('bout-open-body');
    renderBout(); pushLive(); render();
  }
  function keepBout() {
    // Nebaigtos kovos balai išsaugomi (perkrovus puslapį - tęsiama)
    var c = cat(B.cat); c.draw.bouts = c.draw.bouts || {};
    c.draw.bouts[B.mid] = { sa: B.sa, sb: B.sb, log: B.log, i: B.i, ma: B.ma, mb: B.mb, parts: B.parts, remaining: timeLeft() };
    save();
  }
  function closeBout() {
    if (!B) return;
    if (B.timer.running) toggleTimer();
    var area = areaOf(cat(B.cat));
    B = null; liveMatch = null; boutEl.hidden = true; document.body.classList.remove('bout-open-body');
    pushLive(area); render();
  }
  function timeLeft() { var t = B.timer; return Math.max(0, t.running ? t.remaining - (Date.now() - t.at) : t.remaining); }
  function fmtTime(ms) { var s = Math.ceil(ms / 1000), m = Math.floor(s / 60); s = s % 60; return m + ':' + (s < 10 ? '0' : '') + s; }
  var tickTimer = null, beeped = false;
  function toggleTimer() {
    var t = B.timer;
    if (t.running) { t.remaining = timeLeft(); t.running = false; clearInterval(tickTimer); }
    else { if (timeLeft() <= 0) return; t.at = Date.now(); t.running = true; beeped = false; tickTimer = setInterval(tick, 200); }
    t.at = Date.now(); renderTimer(); pushLive();
  }
  function setTime(ms) { var t = B.timer; t.remaining = Math.max(0, Math.min(ms, 30 * 60000)); t.at = Date.now(); if (t.running) { clearInterval(tickTimer); tickTimer = setInterval(tick, 200); } renderTimer(); pushLive(); }
  function tick() {
    if (!B) { clearInterval(tickTimer); return; }
    if (timeLeft() <= 0 && B.timer.running) { B.timer.remaining = 0; B.timer.running = false; clearInterval(tickTimer); if (!beeped) { beep(); beeped = true; } pushLive(); }
    renderTimer();
  }
  function beep() {
    try { var a = new (window.AudioContext || window.webkitAudioContext)(), o = a.createOscillator(), g = a.createGain(); o.frequency.value = 880; o.connect(g); g.connect(a.destination); g.gain.value = 0.25; o.start(); setTimeout(function () { o.stop(); a.close(); }, 900); } catch (e) {}
  }
  function renderTimer() {
    var el = document.getElementById('bt-timer'); if (!el || !B || el.tagName === 'INPUT') return;
    var left = timeLeft();
    el.textContent = fmtTime(left);
    el.className = 'bt-timer' + (left <= 0 ? ' over' : left <= 15000 ? ' last' : '') + (B.timer.running ? ' running' : '');
    var tb = document.getElementById('bt-start'); if (tb) tb.textContent = B.timer.running ? '⏸ Sustabdyti' : '▶ Pradėti';
  }
  function sideHtml(side) {
    var c = cat(B.cat), id = side === 'a' ? B.a : B.b, p = person(c, id) || {}, sc = side === 'a' ? B.sa : B.sb, mode = B.mode;
    var team = mode === 'team', list = side === 'a' ? B.ma : B.mb, cur = list[B.i] || list[list.length - 1];
    var html = '<div class="bt-side ' + (side === 'a' ? 'aka' : 'shiro') + '"><div class="bt-tag">' + (side === 'a' ? 'AKA' : 'SHIRO') + '</div>' +
      '<div class="bt-name">' + esc(p.name || '') + '</div><div class="bt-club">' + esc(p.club || '') + '</div>';
    if (team) html += '<label class="bt-member">Kovoja <select class="sel" data-tm="' + side + '">' + list.map(function (x, j) { return '<option value="' + j + '"' + (j === B.i ? ' selected' : '') + '>' + esc(S.people[x].name) + '</option>'; }).join('') + '</select></label>';
    // Didelį balą galima ir įrašyti (paspausti ant skaičiaus)
    html += '<input class="bt-score" type="number" min="0" max="99" inputmode="numeric" data-sc="' + side + '" value="' + sc + '" aria-label="' + (side === 'a' ? 'AKA' : 'SHIRO') + ' balai" title="Paspauskite ir įrašykite balą">';
    if (mode === 'sumo') html += '<div class="bt-btns"><button type="button" class="btn bt-big" data-pt="' + side + '|1">+1 taškas</button><button type="button" class="btn" data-pt="' + side + '|-1">−1</button></div>' +
      '<div class="muted small">Laimi surinkęs ' + cfg(c).sumoTo + '</div>';
    else html += '<div class="bt-btns">' + [1, 2, 3].map(function (n) { return '<button type="button" class="btn bt-big" data-pt="' + side + '|' + n + '">+' + n + '</button>'; }).join('') + '<button type="button" class="btn" data-pt="' + side + '|-1">−1</button></div>';
    return html + '</div>';
  }
  function partsTable() {
    var c = cat(B.cat), J = cfg(c).judges, side = function (sd) {
      var id = sd === 'a' ? B.a : B.b, p = person(c, id) || {}, pr = B.parts[sd], rows = '';
      [['k', 'Kata'], ['p', 'Pritaikymas']].forEach(function (x) {
        var part = x[0], drop = droppedIdx(c, pr[part]), cells = '', t = judgeTotal(c, pr[part]);
        for (var j = 0; j < J; j++) cells += '<input class="j-in' + (drop.indexOf(j) >= 0 ? ' dropped' : '') + '" type="number" step="0.1" min="0" max="10" inputmode="decimal" aria-label="' + x[1] + ' T' + (j + 1) + '" data-part="' + sd + '|' + part + '|' + j + '" value="' + (pr[part][j] !== undefined && pr[part][j] !== null ? esc(pr[part][j]) : '') + '">';
        rows += '<div class="bp-row"><span class="bp-label">' + x[1] + '</span><span class="bp-cells">' + cells + '</span><b class="bp-sum">' + (t === null ? '–' : t.toFixed(1)) + '</b></div>';
      });
      var tk = judgeTotal(c, pr.k), tp = judgeTotal(c, pr.p);
      return '<div class="bt-pside ' + (sd === 'a' ? 'aka' : 'shiro') + '"><div class="bt-tag">' + (sd === 'a' ? 'AKA' : 'SHIRO') + '</div><div class="bt-name">' + esc(p.name || '') + '</div>' + rows +
        '<div class="bp-total">Iš viso <b>' + (tk === null || tp === null ? '–' : (tk + tp).toFixed(1)) + '</b></div></div>';
    };
    return '<div class="bt-psides">' + side('a') + side('b') + '</div>' +
      '<p class="hint">' + (cfg(c).drop && J >= 5 ? 'Aukščiausias ir žemiausias kiekvienos dalies balas atmetamas - jie lieka matomi perbraukti. ' : '') + 'Iš viso = Kata + Pritaikymas.</p>';
  }
  function renderBout() {
    if (!B) return;
    var c = cat(B.cat), area = areaOf(c), body, mode = B.mode, f = cfg(c);
    var head = '<div class="bout-top"><div class="bout-info"><span class="disc d-' + c.discipline + '"></span><b>' + esc(c.name) + '</b><span>' + (B.no ? B.no + ' · ' : '') + esc(B.title) + '</span><span class="pill sys">' + MODE_NAME[mode] + '</span><span class="pill">' + esc(area.name) + '</span></div>' +
      '<span class="spacer"></span>' + (CFG.screenUrl ? '<button type="button" class="btn" id="bt-screen" title="Atidaryti ekrano langą šiam tatamiui (antram monitoriui ar televizoriui)">📺 Ekranas</button>' : '') +
      '<button type="button" class="btn" id="bt-close">✕ Uždaryti</button></div>';
    if (mode === 'score2') {
      body = partsTable();
    } else {
      var mid = '<div class="bt-mid">' + (mode === 'team' ? '<div class="bt-round">Kova ' + (B.i + 1) + ' / ' + Math.max(B.ma.length, B.mb.length, 1) + '</div>' : '') +
        '<button type="button" class="bt-timer" id="bt-timer" title="Paspauskite ir įrašykite laiką (pvz. 1:30)"></button><div class="bt-tbtns"><button type="button" class="btn btn-primary" id="bt-start"></button><button type="button" class="btn" id="bt-reset" title="Iš naujo">↺ ' + fmtTime(B.timer.total) + '</button>' +
        '<button type="button" class="btn" data-dt="-10000">−10 s</button><button type="button" class="btn" data-dt="10000">+10 s</button></div>' +
        (mode === 'team' && B.log.length ? '<ol class="bt-log">' + B.log.map(function (l, j) { return '<li>' + (j + 1) + ' kova: ' + esc(l.an) + ' <b>' + l.a + ':' + l.b + '</b> ' + esc(l.bn) + '</li>'; }).join('') + '</ol>' : '') +
        '<p class="muted small">Tarpas - laikrodis</p></div>';
      body = '<div class="bt-sides">' + sideHtml('a') + mid + sideHtml('b') + '</div>';
    }
    var tie = mode === 'score2' ? (function () { var a = judgeTotal(c, B.parts.a.k), b = judgeTotal(c, B.parts.a.p), x = judgeTotal(c, B.parts.b.k), y = judgeTotal(c, B.parts.b.p); return a !== null && b !== null && x !== null && y !== null && a + b === x + y; })() : B.sa === B.sb;
    var lastTeamBout = mode !== 'team' || B.i >= Math.max(B.ma.length, B.mb.length, 1) - 1;
    var foot = '<div class="bt-foot">' + (mode === 'team' && !lastTeamBout ? '<button type="button" class="btn btn-primary" id="bt-next">Kita kova →</button>' : '') +
      (lastTeamBout ? '<button type="button" class="btn btn-primary" id="bt-finish">✓ Baigti kovą</button>' : '') +
      '<span class="spacer"></span><span class="muted small">' + (tie ? 'Lygiosios - nugalėtoją pasirinkite:' : 'Arba sprendimu (hantei):') + '</span><button type="button" class="btn" data-win="a">Laimėjo AKA</button><button type="button" class="btn" data-win="b">Laimėjo SHIRO</button></div>';
    boutEl.innerHTML = '<div class="bout-card">' + head + '<div class="bout-body">' + body + '</div>' + foot + '</div>';
    renderTimer();
  }
  function finishBout(forced) {
    var c = cat(B.cat), winner, txt, mode = B.mode;
    if (mode === 'team' && (B.log.length <= B.i)) B.log.push({ a: B.sa - B.log.reduce(function (s, l) { return s + l.a; }, 0), b: B.sb - B.log.reduce(function (s, l) { return s + l.b; }, 0), an: (S.people[B.ma[B.i]] || {}).name || '', bn: (S.people[B.mb[B.i]] || {}).name || '' });
    if (mode === 'score2') {
      var ta = (judgeTotal(c, B.parts.a.k) || 0) + (judgeTotal(c, B.parts.a.p) || 0), tb = (judgeTotal(c, B.parts.b.k) || 0) + (judgeTotal(c, B.parts.b.p) || 0);
      if ([B.parts.a.k, B.parts.a.p, B.parts.b.k, B.parts.b.p].some(function (x) { return judgeTotal(c, x) === null; }) && !forced) { alert('Įrašykite visų teisėjų balus abiem komandoms (Kata ir Pritaikymas).'); return; }
      winner = forced || (ta > tb ? 'a' : tb > ta ? 'b' : null); txt = ta.toFixed(1) + ':' + tb.toFixed(1);
    } else {
      winner = forced || (B.sa > B.sb ? 'a' : B.sb > B.sa ? 'b' : null); txt = B.sa + ':' + B.sb;
    }
    if (!winner) { alert('Lygiosios - pasirinkite nugalėtoją mygtuku „Laimėjo AKA“ arba „Laimėjo SHIRO“.'); return; }
    snapshot();
    keepBout();
    c.draw.results[B.mid] = winner === 'a' ? B.a : B.b;
    c.draw.scores[B.mid] = txt;
    B.winner = winner;
    if (B.timer.running) toggleTimer();
    pushLive();
    prune(c); save();
    var area = areaOf(c);
    setTimeout(function () { pushLive(area); }, 8000);   // ekrane nugalėtojas rodomas 8 s
    B = null; liveMatch = null; boutEl.hidden = true; document.body.classList.remove('bout-open-body'); render();
  }
  boutEl.addEventListener('click', function (e) {
    if (!B) return;
    var t = e.target.closest('button'); if (!t) return;
    var c = cat(B.cat);
    if (t.id === 'bt-close') { keepBout(); closeBout(); return; }
    if (t.id === 'bt-screen') { window.open(CFG.screenUrl + '&tatami=' + areaOf(c).id, 'taika-ekranas-' + areaOf(c).id); return; }
    if (t.id === 'bt-start') { toggleTimer(); return; }
    if (t.id === 'bt-timer') { editTimer(); return; }
    if (t.id === 'bt-reset') { if (B.timer.running) toggleTimer(); setTime(B.timer.total); return; }
    if (t.dataset.dt) { setTime(timeLeft() + +t.dataset.dt); return; }
    if (t.dataset.pt) {
      var q = t.dataset.pt.split('|'), side = q[0], n = +q[1];
      if (side === 'a') B.sa = Math.max(0, B.sa + n); else B.sb = Math.max(0, B.sb + n);
      keepBout();
      if (B.mode === 'sumo' && (B.sa >= cfg(c).sumoTo || B.sb >= cfg(c).sumoTo)) { finishBout(); return; }
      renderBout(); pushLive(); return;
    }
    if (t.id === 'bt-next') {
      // Komandinis kumite: kova baigta, nugalėtojas neskelbiamas - kovoja kiti; balai sumuojasi
      var prevA = B.log.reduce(function (s, l) { return s + l.a; }, 0), prevB = B.log.reduce(function (s, l) { return s + l.b; }, 0);
      B.log.push({ a: B.sa - prevA, b: B.sb - prevB, an: (S.people[B.ma[B.i]] || {}).name || '', bn: (S.people[B.mb[B.i]] || {}).name || '' });
      B.i++; if (B.timer.running) toggleTimer(); B.timer.remaining = B.timer.total;
      keepBout(); renderBout(); pushLive(); return;
    }
    if (t.id === 'bt-finish') { finishBout(); return; }
    if (t.dataset.win) { finishBout(t.dataset.win); return; }
  });
  // Laiko įrašymas: „1:30“, „2“ (minutės) arba „90“ (sekundės)
  function editTimer() {
    if (B.timer.running) toggleTimer();
    var el = document.getElementById('bt-timer'), inp = document.createElement('input'), done = false;
    inp.className = 'bt-timer bt-timer-in'; inp.value = fmtTime(timeLeft()); inp.setAttribute('aria-label', 'Laikas (min:sek)');
    el.replaceWith(inp); inp.focus(); inp.select();
    var commit = function (save) {
      if (done) return; done = true;
      var m = inp.value.trim().match(/^(\d{1,3})(?:[:.,](\d{1,2}))?$/);
      if (save && m) setTime(m[2] !== undefined ? (+m[1] * 60 + +m[2]) * 1000 : (+m[1] <= 10 ? +m[1] * 60000 : +m[1] * 1000));
      setTimeout(renderBout, 0);
    };
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); commit(true); } else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); commit(false); } });
    inp.addEventListener('blur', function () { commit(true); });
  }
  boutEl.addEventListener('change', function (e) {
    if (!B) return;
    var t = e.target;
    if (t.dataset.sc) {
      var v = Math.max(0, Math.min(99, parseInt(t.value, 10) || 0));
      if (t.dataset.sc === 'a') B.sa = v; else B.sb = v;
      keepBout(); pushLive();
      if (B.mode === 'sumo' && (B.sa >= cfg(cat(B.cat)).sumoTo || B.sb >= cfg(cat(B.cat)).sumoTo)) { finishBout(); return; }
      setTimeout(function () { if (B) renderBout(); }, 0);
      return;
    }
    if (t.dataset.tm) {
      // Kuris komandos narys kovoja šią kovą - sukeičiama su tuo, kuris buvo šioje vietoje
      var list = t.dataset.tm === 'a' ? B.ma : B.mb, j = +t.value, x = list[B.i]; list[B.i] = list[j]; list[j] = x;
      keepBout(); renderBout(); pushLive(); return;
    }
    if (t.dataset.part) {
      var q = t.dataset.part.split('|'), arr = B.parts[q[0]][q[1]], v = t.value.replace(',', '.');
      arr[+q[2]] = v === '' ? null : Math.max(0, Math.min(10, parseFloat(v)));
      keepBout(); pushLive();
      // Perpiešiama po „blur“, kad nedingtų laukelis, iš kurio išeinama
      setTimeout(function () {
        if (!B) return;
        renderBout();
        var next = boutEl.querySelector('[data-part="' + q[0] + '|' + q[1] + '|' + (+q[2] + 1) + '"]') || (q[1] === 'k' ? boutEl.querySelector('[data-part="' + q[0] + '|p|0"]') : null);
        if (next) next.focus();
      }, 0);
    }
  });
  document.addEventListener('keydown', function (e) {
    if (!B || boutEl.hidden) return;
    if (e.key === 'Escape') { keepBout(); closeBout(); return; }
    if ((e.key === ' ' || e.code === 'Space') && !/INPUT|SELECT|TEXTAREA/.test((e.target.tagName || '')) && B.mode !== 'score2') {
      e.preventDefault();
      if (document.activeElement && document.activeElement.tagName === 'BUTTON') document.activeElement.blur();   // kad tarpas nepaspaustų ir mygtuko
      toggleTimer();
    }
  });
  document.addEventListener('keyup', function (e) {
    if (B && !boutEl.hidden && (e.key === ' ' || e.code === 'Space') && !/INPUT|SELECT|TEXTAREA/.test((e.target.tagName || ''))) e.preventDefault();
  });

  // Transliacija: ekrano langui tame pačiame kompiuteryje (iškart) ir per serverį (kiti įrenginiai, žiūrovų nuoroda)
  var chan = null; try { chan = new BroadcastChannel('taika-' + (CFG.id || 0)); } catch (e) {}
  var liveTimer = null, livePending = {};
  function liveState(area) {
    if (!B) return { area: area.id, areaName: area.name, idle: true, at: Date.now() };
    var c = cat(B.cat), st = { area: areaOf(c).id, areaName: areaOf(c).name, cat: c.name, catId: c.id, disc: c.discipline, round: (B.no ? B.no + ' · ' : '') + B.title, mode: B.mode,
      aka: { name: pubName(B.a), club: (person(c, B.a) || {}).club || '' }, shiro: { name: pubName(B.b), club: (person(c, B.b) || {}).club || '' },
      sa: B.sa, sb: B.sb, winner: B.winner, at: Date.now(),
      timer: { total: B.timer.total, remaining: timeLeft(), running: B.timer.running } };
    if (B.mode === 'team') { st.bout = (B.i + 1) + ' / ' + Math.max(B.ma.length, B.mb.length, 1); st.akaNow = pubName(B.ma[B.i]); st.shiroNow = pubName(B.mb[B.i]); }
    if (B.mode === 'sumo') st.to = cfg(c).sumoTo;
    if (B.mode === 'score2') {
      st.parts = {};
      ['a', 'b'].forEach(function (sd) { st.parts[sd] = { k: B.parts[sd].k, p: B.parts[sd].p, dk: droppedIdx(c, B.parts[sd].k), dp: droppedIdx(c, B.parts[sd].p), tk: judgeTotal(c, B.parts[sd].k), tp: judgeTotal(c, B.parts[sd].p) }; });
    }
    return st;
  }
  function pushLive(area) {
    var st = liveState(area || (B ? areaOf(cat(B.cat)) : S.areas[0]));
    if (chan) try { chan.postMessage(st); } catch (e) {}
    if (!CFG.liveApi || CFG.readOnly) return;
    livePending[st.area] = st;
    clearTimeout(liveTimer);
    liveTimer = setTimeout(function () {
      var send = livePending; livePending = {};
      Object.keys(send).forEach(function (k) {
        fetch(CFG.liveApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf },
          body: JSON.stringify({ area: +k, data: send[k] }) }).catch(function () {});
      });
    }, 250);
  }

  // ---------- Tvarkaraštis ----------
  function minutesFor(c) { return Math.round(matchCount(c) * (S.minutes[c.discipline] || 2) * (c.team ? 3 : 1) + 2); }   // komandoje - 3 kovos
  function hhmm(min) { var h = Math.floor(min / 60) % 24, m = Math.round(min % 60); return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m; }
  function toMin(t) { var p = String(t || '09:00').split(':'); return (+p[0]) * 60 + (+p[1] || 0); }
  function renderSched() {
    Object.keys(DISC).forEach(function (k) { document.getElementById('m-' + k).value = S.minutes[k]; });
    var wrap = document.getElementById('sched');
    wrap.innerHTML = S.areas.map(function (a, ai) {
      var t = toMin(a.start), list = S.categories.filter(function (c) { return c.tatami === a.id; });
      var items = list.map(function (c, idx) {
        var start = t, dur = minutesFor(c); t += dur;
        var total = matchCount(c), done = Math.min(doneCount(c), total), pct = total ? Math.round(done / total * 100) : 0;
        var moveOpts = S.areas.filter(function (x) { return x.id !== a.id; }).map(function (x) { return '<button class="btn" type="button" data-to="' + x.id + '" data-c="' + c.id + '" title="Perkelti į ' + esc(x.name) + '">→ ' + esc(x.name.replace(' TATAMIS', '')) + '</button>'; }).join('');
        return '<li class="card" draggable="true" data-c="' + c.id + '"><div class="card-top"><span class="card-time">' + hhmm(start) + '</span><button type="button" class="card-name" data-open-cat="' + c.id + '" title="Atidaryti lentelę"><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + '</button></div>' +
          '<div class="prog' + (pct === 100 ? ' done' : '') + '"><i style="width:' + pct + '%"></i><span>' + done + ' / ' + total + '</span></div>' +
          '<div class="card-actions"><span class="muted small">' + c.competitors.length + ' dalyv. · ~' + dur + ' min</span><span class="spacer"></span>' +
          '<button class="btn" type="button" data-up="' + c.id + '"' + (idx === 0 ? ' disabled' : '') + ' aria-label="Aukštyn">↑</button><button class="btn" type="button" data-down="' + c.id + '"' + (idx === list.length - 1 ? ' disabled' : '') + ' aria-label="Žemyn">↓</button>' + moveOpts + '</div></li>';
      }).join('');
      return '<div class="area" data-area="' + a.id + '"><div class="area-head"><input class="name" value="' + esc(a.name) + '" data-rename="' + a.id + '" aria-label="Tatamio pavadinimas">' +
        '<label>Pradžia <input type="time" value="' + esc(a.start) + '" data-start="' + a.id + '"></label></div>' +
        '<ul class="area-list" data-drop="' + a.id + '">' + (items || '<li class="muted small">Tempkite kategoriją čia</li>') + '</ul>' +
        '<div class="area-foot">Pabaiga apie <b>' + hhmm(t) + '</b> · ' + list.length + ' kateg.' + (S.areas.length > 1 ? ' · <button class="btn btn-danger" type="button" data-del-area="' + a.id + '">Pašalinti tatamį</button>' : '') + '</div></div>';
    }).join('');
  }
  function moveCat(id, areaId, beforeId) {
    var c = cat(id); if (!c) return;
    snapshot();
    S.categories.splice(S.categories.indexOf(c), 1);
    c.tatami = areaId;
    var idx = beforeId ? S.categories.findIndex(function (x) { return x.id === beforeId; }) : -1;
    if (idx === -1) {
      var last = -1; S.categories.forEach(function (x, i) { if (x.tatami === areaId) last = i; });
      S.categories.splice(last + 1, 0, c);
    } else S.categories.splice(idx, 0, c);
    save(); render();
  }
  var sched = document.getElementById('sched'), dragId = null;
  sched.addEventListener('dragstart', function (e) { var li = e.target.closest('.card'); if (!li) return; dragId = li.dataset.c; li.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', dragId); } catch (x) {} });
  sched.addEventListener('dragend', function () { dragId = null; render(); });
  sched.addEventListener('dragover', function (e) { var ul = e.target.closest('[data-drop]'); if (!ul || !dragId) return; e.preventDefault(); sched.querySelectorAll('.drop').forEach(function (x) { x.classList.remove('drop'); }); ul.classList.add('drop'); });
  sched.addEventListener('drop', function (e) {
    var ul = e.target.closest('[data-drop]'); if (!ul || !dragId) return; e.preventDefault();
    var over = e.target.closest('.card'); var before = over && over.dataset.c !== dragId ? over.dataset.c : null;
    moveCat(dragId, +ul.dataset.drop, before);
  });
  sched.addEventListener('click', function (e) {
    var t = e.target, c, list, i;
    var oc = t.closest && t.closest('[data-open-cat]');
    if (oc) { S.selected = oc.dataset.openCat; save(); showTab('lenteles'); return; }
    if (t.dataset.to) { moveCat(t.dataset.c, +t.dataset.to, null); return; }
    if (t.dataset.up || t.dataset.down) {
      c = cat(t.dataset.up || t.dataset.down);
      list = S.categories.filter(function (x) { return x.tatami === c.tatami; }); i = list.indexOf(c);
      var other = t.dataset.up ? list[i - 1] : list[i + 1]; if (!other) return;
      snapshot();
      var a = S.categories.indexOf(c), b = S.categories.indexOf(other); S.categories[a] = other; S.categories[b] = c;
      save(); render(); return;
    }
    if (t.dataset.delArea) {
      var id = +t.dataset.delArea, to = S.areas.find(function (x) { return x.id !== id; }).id;
      snapshot(); S.categories.forEach(function (x) { if (x.tatami === id) x.tatami = to; });
      S.areas = S.areas.filter(function (x) { return x.id !== id; }); save(); render();
    }
  });
  sched.addEventListener('change', function (e) {
    var t = e.target;
    if (t.dataset.start) { S.areas.find(function (a) { return a.id === +t.dataset.start; }).start = t.value || '09:00'; }
    else if (t.dataset.rename) { S.areas.find(function (a) { return a.id === +t.dataset.rename; }).name = t.value.trim() || 'TATAMIS'; }
    else return;
    save(); render();
  });
  document.getElementById('area-new').addEventListener('click', function () {
    var id = Math.max.apply(null, S.areas.map(function (a) { return a.id; })) + 1;
    S.areas.push({ id: id, name: id + ' TATAMIS', start: S.areas[0] ? S.areas[0].start : '09:30' }); save(); render();
  });
  Object.keys(DISC).forEach(function (k) {
    document.getElementById('m-' + k).addEventListener('change', function (e) { var v = parseFloat(e.target.value); if (v > 0) { S.minutes[k] = v; save(); render(); } });
  });
  // Kategorijos paskirstomos tatamiams taip, kad pabaigos laikai būtų kuo artimesni (ilgiausios - pirmos)
  function balance() {
    var load = {}; S.areas.forEach(function (a) { load[a.id] = toMin(a.start); });
    S.categories.slice().sort(function (a, b) { return minutesFor(b) - minutesFor(a); }).forEach(function (c) {
      var best = S.areas[0].id; S.areas.forEach(function (a) { if (load[a.id] < load[best]) best = a.id; });
      c.tatami = best; load[best] += minutesFor(c);
    });
  }
  document.getElementById('auto-balance').addEventListener('click', function () { snapshot(); balance(); save(); render(); });


  var renderPending = false;
  function renderSoon() { if (renderPending) return; renderPending = true; setTimeout(function () { renderPending = false; render(); }, 0); }
  // ---------- Rezultatai: prizininkai ir klubų medaliai ----------
  function results() {
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
  function isMinor(id) { var p = S.people[id]; return !S.teams[id] && !!(p && p.birth && YEAR - p.birth < 18); }
  function resultsPayload() {
    var rs = results();
    return {
      categories: rs.map(function (r) {
        return { id: r.c.id, name: r.c.name, discipline: r.c.discipline, team: !!r.c.team, count: r.c.competitors.length, total: r.total, done: r.done, finished: r.finished, places: r.places };
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
  function catBracket(c) {
    var club = function (id) { return id ? (person(c, id) || {}).club || '' : ''; };
    var mm = function (m, t) {
      return { no: m.no || '', t: t, a: m.a ? pubName(m.a) : '', ac: club(m.a), b: m.b ? pubName(m.b) : '', bc: club(m.b),
        w: m.winner && !m.bye ? (m.winner === m.a ? 'a' : 'b') : '', s: (c.draw.scores || {})[m.id] || '' };
    };
    var out = { id: c.id, name: c.name, disc: c.discipline, sys: c.draw && SYSTEMS[c.draw.system] ? SYSTEMS[c.draw.system][0] : '', count: c.competitors.length, groups: [], places: [] };
    if (!c.draw || drawStale(c) || c.competitors.length < 2) {
      out.people = c.competitors.map(function (p) { return { n: pubName(p.id), c: p.club }; });
      return out;
    }
    var sys = c.draw.system;
    if (sys === 'score') {
      var ids = (c.draw.order || []).filter(function (id) { return person(c, id); });
      out.groups.push({ t: 'Balai', score: true, rows: scoreRank(c, 'scores', ids).map(function (x) { return { n: pubName(x.id), c: club(x.id), s: x.total === null ? '' : x.total.toFixed(1) }; }) });
    } else if (sys === 'rr') {
      out.groups.push({ t: 'Ratų sistema', matches: rrMatches(c).map(function (m) { return mm(m, m.round + ' ratas'); }) });
    } else {
      var b = bracket(c);
      b.rounds.forEach(function (rd, r) { var ms = rd.filter(function (m) { return m.no; }); if (ms.length) out.groups.push({ t: roundName(r, b.R), matches: ms.map(function (m) { return mm(m, matchTitle(c, m)); }) }); });
      b.rep.forEach(function (g) { if (g.matches.length) out.groups.push({ t: g.title, matches: g.matches.map(function (m) { return mm(m, g.title); }) }); });
    }
    catPlaces(c).forEach(function (pl) { pl[1].forEach(function (id) { if (pl[0] <= 3) out.places.push({ p: pl[0], n: pubName(id), c: club(id) }); }); });
    return out;
  }
  // Žiūrovams ir ekranams: ant kiekvieno tatamio - kategorijos su laikais ir kas eina toliau (vaikų vardai sutrumpinti)
  function upcoming(c) {
    if (!c.draw || drawStale(c) || c.competitors.length < 2) return [];
    if (c.draw.system === 'score') {
      return (c.draw.order || []).filter(function (id) { return person(c, id) && scoreTotal(c, (c.draw.scores || {})[id]) === null; })
        .map(function (id) { return { title: 'Pasirodymas', a: id, b: null }; });
    }
    var list = c.draw.system === 'rr' ? rrMatches(c) : allMatches(bracket(c));
    return list.filter(function (m) { return m.a && m.b && !m.winner; }).sort(function (x, y) { return (x.no || 0) - (y.no || 0); });
  }
  function areasSummary() {
    return S.areas.map(function (a) {
      var t = toMin(a.start), cats = [], next = [], cur = false;
      S.categories.filter(function (c) { return c.tatami === a.id; }).forEach(function (c) {
        var start = t, total = matchCount(c), done = Math.min(doneCount(c), total); t += minutesFor(c);
        // Dabartinė grupė - pirma nebaigta tatamio eilėje (ekrane ji didesnė)
        var isCur = !cur && total > 0 && done < total; if (isCur) cur = true;
        cats.push({ id: c.id, name: c.name, disc: c.discipline, start: hhmm(start), done: done, total: total, cur: isCur });
        upcoming(c).slice(0, Math.max(0, 6 - next.length)).forEach(function (m) {
          next.push({ catId: c.id, cat: c.name, disc: c.discipline, no: m.no || '', title: m.title || matchTitle(c, m), a: pubName(m.a), ac: (person(c, m.a) || {}).club || '',
            b: m.b ? pubName(m.b) : '', bc: m.b ? (person(c, m.b) || {}).club || '' : '' });
        });
      });
      return { id: a.id, name: a.name, end: hhmm(t), cats: cats, next: next };
    });
  }
  function medalTable(rs) {
    var med = {};
    rs.forEach(function (r) {
      r.places.forEach(function (p) { var m = med[p.club] = med[p.club] || { g: 0, s: 0, b: 0, pts: 0 }; m[['g', 's', 'b'][p.place - 1]]++; m.pts += 4 - p.place; });
    });
    return Object.keys(med).map(function (k) { return { club: k, m: med[k] }; }).sort(function (a, b) {
      return b.m.g - a.m.g || b.m.s - a.m.s || b.m.b - a.m.b || a.club.localeCompare(b.club, 'lt');
    });
  }
  function shownResults() {
    var rs = results(), onlyDone = document.getElementById('res-done').checked;
    return { all: rs, list: onlyDone ? rs.filter(function (r) { return r.finished; }) : rs };
  }
  var MEDAL = { 1: '<span class="medal g">1</span>', 2: '<span class="medal s">2</span>', 3: '<span class="medal b">3</span>' };
  function renderResults() {
    var sr = shownResults(), rs = sr.list, table = medalTable(rs), sum = { g: 0, s: 0, b: 0, pts: 0 };
    document.getElementById('res-count').textContent = 'Baigta ' + sr.all.filter(function (r) { return r.finished; }).length + ' iš ' + sr.all.length + ' kategorijų';
    document.getElementById('medals-view').innerHTML = table.length ? '<table><thead><tr><th class="num">#</th><th>Klubas</th><th class="num">' + MEDAL[1] + '</th><th class="num">' + MEDAL[2] + '</th><th class="num">' + MEDAL[3] + '</th><th class="num">Taškai</th></tr></thead><tbody>' +
      table.map(function (t, i) {
        ['g', 's', 'b', 'pts'].forEach(function (k) { sum[k] += t.m[k]; });
        return '<tr><td class="num">' + (i + 1) + '</td><td>' + esc(t.club) + '</td><td class="num">' + t.m.g + '</td><td class="num">' + t.m.s + '</td><td class="num">' + t.m.b + '</td><td class="num"><b>' + t.m.pts + '</b></td></tr>';
      }).join('') + '</tbody><tfoot><tr><td></td><td>Iš viso</td><td class="num">' + sum.g + '</td><td class="num">' + sum.s + '</td><td class="num">' + sum.b + '</td><td class="num">' + sum.pts + '</td></tr></tfoot></table>'
      : '<p class="muted" style="padding:12px 14px;margin:0;">Dar nėra prizininkų. Pažymėkite kovų nugalėtojus skirtuke „Lentelės“.</p>';
    document.getElementById('podium-view').innerHTML = rs.length ? '<div class="res-cats">' + rs.map(function (r) {
      var state = r.finished ? '<span class="pill ok">Baigta</span>' : r.done ? '<span class="pill warn">Vyksta ' + r.done + '/' + r.total + '</span>' : '<span class="pill">Nepradėta</span>';
      return '<div class="res-cat"><div class="res-cat-top"><button type="button" data-res-open="' + r.c.id + '" title="Atidaryti lentelę"><span class="disc d-' + r.c.discipline + '"></span>' + esc(r.c.name) + '</button>' + state + '</div>' +
        (r.places.length ? r.places.map(function (p) { return '<div class="rp">' + MEDAL[p.place] + '<span>' + esc(p.name) + ' <span class="club">' + esc(p.club) + '</span></span></div>'; }).join('') : '<span class="muted small">Prizininkų dar nėra</span>') + '</div>';
    }).join('') + '</div>' : '<p class="muted" style="padding:12px 14px;margin:0;">Nėra baigtų kategorijų.</p>';
  }
  document.getElementById('res-done').addEventListener('change', renderResults);
  document.getElementById('podium-view').addEventListener('click', function (e) {
    var b = e.target.closest('[data-res-open]'); if (!b) return;
    S.selected = b.dataset.resOpen; save(); showTab('lenteles');
  });
  document.getElementById('res-print').addEventListener('click', function () { window.print(); });
  document.getElementById('draw-print').addEventListener('click', function () { window.print(); });
  document.getElementById('res-csv').addEventListener('click', function () {
    var q = function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; };
    var lines = [['Kategorija', 'Rungtis', 'Vieta', 'Vardas', 'Klubas'].map(q).join(';')];
    shownResults().list.forEach(function (r) { r.places.forEach(function (p) { lines.push([r.c.name, DISC[r.c.discipline] || r.c.discipline, p.place, p.name, p.club].map(q).join(';')); }); });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
    // Failo pavadinimas be lietuviškų raidžių - kitaip kai kurios naršyklės jį pakeičia į „download“
    a.download = (S.tournament || 'varzybos').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '') + '-rezultatai.csv';
    document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
  });

  // ---------- Kategorijos automatiškai: rungtis × lytis × amžius × diržas, mažos sujungiamos su artimiausiomis ----------
  var AGE_GROUPS = [[0, 7], [8, 9], [10, 11], [12, 13], [14, 15], [16, 17], [18, 34], [35, 99]];
  var BELT_GROUPS = [[1, 3], [4, 6], [7, 99]];
  var DISC_ORDER = { kihon: 0, kata: 1, kumite: 2, fukugo: 3 };
  function kyu(l) { return l <= 9 ? (10 - l) + ' kyu' : (l - 9) + ' dan'; }
  function catName(b) {
    var g = b.gs.F && b.gs.M || b.gs.X ? 'Mix' : b.gs.F ? 'F' : 'M';
    var yMin = AGE_GROUPS[b.a0][0], yMax = AGE_GROUPS[b.a1][1];
    var ages = yMax >= 99 ? (YEAR - yMin) + ' m. ir vyr.' : yMin === 0 ? (YEAR - yMax) + ' m. ir jaun.' : (YEAR - yMin) + '–' + (YEAR - yMax) + ' m.';
    var lo = BELT_GROUPS[b.b0][0], hi = BELT_GROUPS[b.b1][1];
    var belts = hi >= 99 ? kyu(lo) + '+' : kyu(lo).replace(' kyu', '') + '–' + kyu(hi);
    if (b.b0 === 0 && b.b1 === BELT_GROUPS.length - 1) belts = 'visi diržai';
    return DISC[b.d] + ' ' + g + ' ' + ages + ' · ' + belts;
  }
  function autoCategories() {
    var buckets = {};
    Object.keys(S.people).forEach(function (id) {
      var p = S.people[id]; if (!p.birth) return;
      var a = YEAR - p.birth, ai = AGE_GROUPS.findIndex(function (g) { return a >= g[0] && a <= g[1]; });
      if (ai < 0) return;
      var bl = p.belt || 1, bi = BELT_GROUPS.findIndex(function (g) { return bl >= g[0] && bl <= g[1]; });
      var g = a < 8 ? 'X' : (p.gender === 'F' ? 'F' : 'M');
      var discs = a < 8 ? ['kihon', 'kata'] : ['kata', 'kumite'];
      if (a >= 16 && bl >= 7) discs.push('fukugo');
      discs.forEach(function (d) {
        var k = d + '|' + g + '|' + ai + '|' + bi, gs = {}; gs[g] = true;
        (buckets[k] = buckets[k] || { d: d, gs: gs, a0: ai, a1: ai, b0: bi, b1: bi, people: [] }).people.push(p);
      });
    });
    var list = Object.keys(buckets).map(function (k) { return buckets[k]; });
    function cost(x, y) {
      if (x.d !== y.d) return Infinity;
      var ga = Math.max(0, Math.max(x.a0, y.a0) - Math.min(x.a1, y.a1)), gb = Math.max(0, Math.max(x.b0, y.b0) - Math.min(x.b1, y.b1));
      var mixG = (x.gs.M && y.gs.F) || (x.gs.F && y.gs.M) ? 1 : 0;
      if (mixG && Math.min(x.a0, y.a0) >= 4) return Infinity;   // nuo 14 m. vaikinai ir merginos atskirai
      return ga * 3 + gb * 2 + mixG * 4 + (ga && gb ? 3 : 0);
    }
    // Kol yra kategorija su vienu dalyviu - jungiama su pigiausia (artimiausia) tos pačios rungties
    for (var guard = 0; guard < 500; guard++) {
      var small = list.filter(function (b) { return b.people.length < 2; }).sort(function (a, b) { return a.people.length - b.people.length; });
      var merged = false;
      for (var i = 0; i < small.length && !merged; i++) {
        var x = small[i], best = null, bc = Infinity;
        list.forEach(function (y) { if (y !== x) { var c = cost(x, y); if (c < bc || (c === bc && best && y.people.length < best.people.length)) { bc = c; best = y; } } });
        if (best && bc < 12) {
          best.a0 = Math.min(best.a0, x.a0); best.a1 = Math.max(best.a1, x.a1); best.b0 = Math.min(best.b0, x.b0); best.b1 = Math.max(best.b1, x.b1);
          Object.keys(x.gs).forEach(function (k) { best.gs[k] = true; });
          x.people.forEach(function (p) { if (best.people.indexOf(p) === -1) best.people.push(p); });
          list.splice(list.indexOf(x), 1);
          merged = true;
        }
      }
      if (!merged) break;
    }
    list.sort(function (a, b) { return a.a0 - b.a0 || DISC_ORDER[a.d] - DISC_ORDER[b.d] || a.b0 - b.b0 || (a.gs.F ? 1 : 0) - (b.gs.F ? 1 : 0); });
    var teams = S.categories.filter(function (c) { return c.team; });
    S.categories = list.map(function (b, i) {
      var lo = BELT_GROUPS[b.b0][0], hi = BELT_GROUPS[b.b1][1], yMin = AGE_GROUPS[b.a0][0], yMax = AGE_GROUPS[b.a1][1];
      var c = { id: 'k' + (i + 1) + Date.now().toString(36), name: catName(b), discipline: b.d, competitors: b.people.map(function (p) { return { id: p.id, name: p.name, club: p.club }; }), tatami: S.areas[0].id, draw: null,
        crit: { g: b.gs.F && b.gs.M || b.gs.X ? 'X' : b.gs.F ? 'F' : 'M', y0: yMax >= 99 ? null : YEAR - yMax, y1: yMin === 0 ? null : YEAR - yMin, b0: lo > 1 ? lo : null, b1: hi < 99 ? hi : null } };
      if (c.competitors.length >= 2) c.draw = makeDraw(c, defaultSystem(c));
      return c;
    }).concat(teams);
    teams.forEach(function (c) { if (c.competitors.length >= 2 && !c.draw) c.draw = makeDraw(c, defaultSystem(c)); });
    balance();
    S.selected = S.categories[0] && S.categories[0].id;
  }
  document.getElementById('cat-auto').addEventListener('click', function () {
    var played = S.categories.some(function (c) { return c.draw && Object.keys(c.draw.results).length; });
    if (!confirm((played ? 'Dalis kovų jau įvyko - jų rezultatai dings. ' : '') + 'Sudaryti kategorijas iš naujo pagal dalyvių amžių, lytį ir diržą? Komandinės kategorijos liks.')) return;
    snapshot(); autoCategories(); save(); render();
  });

  // ---------- Testui: atsitiktiniai kovų nugalėtojai ----------
  function playCategory(c, limit) {
    if (c.competitors.length < 2) return;
    if (!c.draw || drawStale(c)) c.draw = makeDraw(c, c.draw ? c.draw.system : defaultSystem(c));
    var rnd = function () { return Math.round((6 + Math.random() * 3) * 10) / 10; };
    if (c.draw.system === 'rr') {
      rrMatches(c).slice(0, limit * 3).forEach(function (m) { if (!m.winner) c.draw.results[m.id] = Math.random() < 0.5 ? m.a : m.b; });
      return;
    }
    if (c.draw.system === 'score') {
      var f = cfg(c), ids = (c.draw.order || []).slice(0, limit), j;
      ids.forEach(function (id) { if (!c.draw.scores[id]) { c.draw.scores[id] = []; for (j = 0; j < f.judges; j++) c.draw.scores[id].push(rnd()); } });
      if (f.rounds === 2 && limit >= ids.length) scoreFinalists(c).forEach(function (id) { c.draw.final = c.draw.final || {}; if (!c.draw.final[id]) { c.draw.final[id] = []; for (j = 0; j < f.judges; j++) c.draw.final[id].push(rnd()); } });
      return;
    }
    for (var n = 0; n < limit; n++) {
      var m = allMatches(bracket(c)).find(function (x) { return x.a && x.b && !x.winner; });
      if (!m) break;
      c.draw.results[m.id] = Math.random() < 0.5 ? m.a : m.b;
    }
  }
  function simulate(frac) {
    var cats = S.categories.filter(function (c) { return c.competitors.length >= 2; }), full = Math.round(cats.length * frac);
    cats.forEach(function (c, i) { playCategory(c, i < full ? 999 : i === full ? 2 : 0); });
  }
  if (CFG.isTest) {
    document.getElementById('draw-sim').addEventListener('click', function () { var c = cat(S.selected); if (!c) return; snapshot(); playCategory(c, 999); save(); render(); });
    document.getElementById('res-sim').addEventListener('click', function () { snapshot(); simulate(1); save(); render(); });
  }

  // ---------- Karateka nariai į varžybas ----------
  var pickGroup = '';
  function renderMembersPick() {
    var box = document.getElementById('members-pick'); if (box.hidden) return;
    var members = CFG.members || [], q = (document.getElementById('mp-search') || {}).value || '';
    var groups = []; members.forEach(function (m) { if (m.group && groups.indexOf(m.group) === -1) groups.push(m.group); });
    var shown = members.filter(function (m) { return (!pickGroup || m.group === pickGroup) && (!q || m.name.toLowerCase().indexOf(q.toLowerCase()) !== -1); });
    var free = shown.filter(function (m) { return !S.people['m' + m.id]; });
    box.querySelector('.mp-groups').innerHTML = '<button type="button" class="fchip" data-g="" aria-pressed="' + (!pickGroup) + '">Visi (' + members.length + ')</button>' +
      groups.map(function (g) { return '<button type="button" class="fchip" data-g="' + esc(g) + '" aria-pressed="' + (g === pickGroup) + '">' + esc(g) + '</button>'; }).join('');
    box.querySelector('.mp-list').innerHTML = shown.map(function (m) {
      var added = !!S.people['m' + m.id];
      return '<label class="mp-item' + (added ? ' added' : '') + '"><input type="checkbox" value="' + m.id + '"' + (added ? ' checked disabled' : '') + '>' +
        beltDot(m.belt) + '<span class="mp-name">' + esc(m.name) + '</span><span class="mp-meta">' + (m.birth || '?') + ' · ' + (m.gender || '?') + (m.belt ? ' · ' + esc(beltName(m.belt)) : '') + (added ? ' · jau pridėtas' : '') + '</span></label>';
    }).join('') || '<p class="muted small">Narių nerasta.</p>';
    box.querySelector('#mp-all').textContent = 'Pažymėti visus (' + free.length + ')';
  }
  document.getElementById('ppl-members').addEventListener('click', function () {
    var box = document.getElementById('members-pick'); box.hidden = !box.hidden; renderMembersPick();
    if (!box.hidden) document.getElementById('mp-search').focus();
  });
  document.getElementById('members-pick').addEventListener('click', function (e) {
    var t = e.target;
    if (t.dataset && t.dataset.g !== undefined && t.classList.contains('fchip')) { pickGroup = t.dataset.g; renderMembersPick(); }
    else if (t.id === 'mp-all') { this.querySelectorAll('.mp-list input:not(:disabled)').forEach(function (i) { i.checked = true; }); }
    else if (t.id === 'mp-close') { this.hidden = true; }
    else if (t.id === 'mp-add') {
      var ids = [].slice.call(this.querySelectorAll('.mp-list input:checked:not(:disabled)')).map(function (i) { return +i.value; });
      if (!ids.length) return;
      snapshot();
      if (S.clubs.indexOf(HOME) === -1) S.clubs.unshift(HOME);
      (CFG.members || []).forEach(function (m) {
        if (ids.indexOf(m.id) === -1 || S.people['m' + m.id]) return;
        S.people['m' + m.id] = { id: 'm' + m.id, name: m.name, club: HOME, birth: m.birth, gender: m.gender, belt: m.belt, member: m.id, noPhoto: m.photo === 0 };
      });
      toastPlaced(ids.map(function (x) { return 'm' + x; }));
      save(); render(); renderMembersPick();
    }
  });
  document.getElementById('members-pick').addEventListener('input', function (e) { if (e.target.id === 'mp-search') renderMembersPick(); });

  // ---------- Transliacija ir registracija: nuorodos, ekranai ----------
  var shareBtn = document.getElementById('share-toggle'), sharePanel = document.getElementById('share-panel');
  function renderScreens() {
    var box = document.getElementById('sp-screens'); if (!box) return;
    box.innerHTML = S.areas.map(function (a) { return '<button type="button" class="btn" data-screen="' + a.id + '">📺 ' + esc(a.name) + '</button>'; }).join('');
  }
  if (shareBtn) {
    shareBtn.addEventListener('click', function () { sharePanel.hidden = !sharePanel.hidden; shareBtn.setAttribute('aria-expanded', sharePanel.hidden ? 'false' : 'true'); renderScreens(); });
    sharePanel.addEventListener('click', function (e) {
      var t = e.target.closest('button'); if (!t) return;
      if (t.dataset.screen) { window.open(CFG.screenUrl + '&tatami=' + t.dataset.screen, 'taika-ekranas-' + t.dataset.screen); return; }
      if (t.dataset.copy) {
        var done = function () { var o = t.textContent; t.textContent = 'Nukopijuota ✓'; setTimeout(function () { t.textContent = o; }, 1500); };
        if (navigator.clipboard) navigator.clipboard.writeText(t.dataset.copy).then(done, function () { t.previousElementSibling.select(); });
        else { t.previousElementSibling.select(); document.execCommand('copy'); done(); }
      }
    });
    document.getElementById('reg-open').addEventListener('change', function (e) {
      fetch(CFG.regApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf }, body: JSON.stringify({ open: e.target.checked }) })
        .then(function () { setStatus(e.target.checked ? 'Registracija atidaryta ✓' : 'Registracija uždaryta', 'ok'); });
    });
  }
  // Užsiregistravę per nuorodą: Denis peržiūri ir prideda (arba atmeta)
  var entries = CFG.entries || [];
  function entryPersonId(en) { return 'r' + en.id; }
  function renderEntries() {
    var box = document.getElementById('entries-box'); if (!box) return;
    box.hidden = !entries.length && !CFG.regUrl;
    if (box.hidden) return;
    var byClub = {};
    entries.forEach(function (en) { (byClub[en.club] = byClub[en.club] || []).push(en); });
    box.innerHTML = '<h2>Užsiregistravę per nuorodą' + (entries.length ? ' <span class="pill warn">' + entries.length + ' nauji</span>' : '') + '<span class="spacer"></span>' +
      '<button type="button" class="btn" id="en-refresh" title="Patikrinti, ar yra naujų">↻ Atnaujinti</button>' + (entries.length ? '<button type="button" class="btn btn-primary" id="en-add-all">Pridėti visus (' + entries.length + ')</button>' : '') + '</h2>' +
      (entries.length ? '<div class="table-scroll"><table><thead><tr><th>Klubas</th><th>Vardas, pavardė</th><th class="num">Gim.</th><th>Lytis</th><th>Diržas</th><th>Nuotraukos</th><th>Tėvų tel.</th><th>Kontaktas</th><th></th></tr></thead><tbody>' +
        Object.keys(byClub).sort().map(function (k) {
          return byClub[k].map(function (en) {
            return '<tr><td>' + esc(en.club) + '</td><td><b>' + esc(en.name) + '</b></td><td class="num">' + (en.birth_year || '') + '</td><td>' + esc(en.gender || '') + '</td><td>' + beltDot(+en.belt) + esc(beltName(+en.belt)) + '</td>' +
              '<td>' + (en.photo_ok === null || en.photo_ok === undefined ? '<span class="muted">?</span>' : +en.photo_ok ? '✓ sutinka' : '<span class="nophoto">be nuotraukų</span>') + '</td>' +
              '<td class="small">' + esc(en.parent_phone || '') + '</td><td class="small">' + esc(en.contact || '') + '</td>' +
              '<td><button type="button" class="btn btn-xs" data-en-add="' + en.id + '">Pridėti</button> <button type="button" class="btn btn-danger btn-xs" data-en-rej="' + en.id + '" title="Atmesti">✕</button></td></tr>';
          }).join('');
        }).join('') + '</tbody></table></div>'
        : '<p class="muted small" style="padding:10px 14px; margin:0;">Naujų nėra. Registracijos nuorodą rasite „📡 Transliacija ir registracija“.</p>');
  }
  function markEntries(ids, status) {
    fetch(CFG.entriesApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf }, body: JSON.stringify({ ids: ids, status: status }) }).catch(function () {});
    entries = entries.filter(function (en) { return ids.indexOf(+en.id) === -1; });
  }
  function addEntries(list) {
    if (!list.length) return;
    snapshot();
    list.forEach(function (en) {
      if (S.clubs.indexOf(en.club) === -1) S.clubs.push(en.club);
      // Tas pats žmogus (vardas, gimimo metai, klubas) nepridedamas antrą kartą
      var dup = Object.keys(S.people).some(function (id) { var p = S.people[id]; return p.name === en.name && p.club === en.club && (+p.birth || 0) === (+en.birth_year || 0); });
      if (!dup) S.people[entryPersonId(en)] = { id: entryPersonId(en), name: en.name, club: en.club, birth: +en.birth_year || null, gender: en.gender || null, belt: +en.belt || null, member: null,
        noPhoto: en.photo_ok !== null && en.photo_ok !== undefined && !+en.photo_ok };
    });
    markEntries(list.map(function (en) { return +en.id; }), 'added');
    toastPlaced(list.map(entryPersonId).filter(function (id) { return S.people[id]; }));
    save(); render();
  }
  document.getElementById('entries-box').addEventListener('click', function (e) {
    var t = e.target.closest('button'); if (!t) return;
    if (t.id === 'en-refresh') {
      fetch(CFG.entriesApi, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); })
        .then(function (d) { entries = d.entries || []; renderEntries(); }).catch(function () {});
    } else if (t.id === 'en-add-all') addEntries(entries.slice());
    else if (t.dataset.enAdd) addEntries(entries.filter(function (en) { return +en.id === +t.dataset.enAdd; }));
    else if (t.dataset.enRej) { markEntries([+t.dataset.enRej], 'rejected'); renderEntries(); }
  });

  function render() {
    document.getElementById('t-name').textContent = S.tournament;
    var tab = tabs.find(function (t) { return !document.getElementById('v-' + t).hidden; });
    if (tab === 'klubai') renderClubs(); else if (tab === 'dalyviai') { renderEntries(); renderPeople(); } else if (tab === 'komandos') renderTeams(); else if (tab === 'kategorijos') renderCats(); else if (tab === 'lenteles') renderDraw(); else if (tab === 'rezultatai') renderResults(); else renderSched();
  }
  load();
  // Naujos test varžybos: kategorijos sudaromos ir dalis kovų „sužaidžiama“ pirmą kartą atidarius
  if (S.autoCats) { delete S.autoCats; autoCategories(); save(); }
  if (S.simulate) { var fr = +S.simulate; delete S.simulate; simulate(fr); save(); }
  var startTab = 'dalyviai';
  try { startTab = location.hash.replace('#', '') || sessionStorage.getItem(KEY + '-tab') || 'dalyviai'; } catch (e) {}
  showTab(tabs.indexOf(startTab) >= 0 ? startTab : 'dalyviai');
})();
