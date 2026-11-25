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
  // Sistemos pavadinimas pagal rungtį (Kata vėliavėlėmis, Kata balais, Enbu balais, Fukugo...); medalių variantas - atskiras nustatymas
  var MEDALS = { plain: '2 bronzos be kovos', bronze: 'Kova dėl 3 vietos', wkf: 'Paguoda, 2 bronzos (WKF)' };
  function methodOf(sys) { return sys === 'score' || sys === 'rr' || sys === 'fukugo' ? sys : 'elim'; }
  function discName(c) {
    var d = DISC[c.discipline] || c.discipline;
    if (!c.team || c.discipline === 'enbu') return d;
    return (c.discipline === 'kumite' || c.discipline === 'kihon' ? 'Komandinis ' : 'Komandinė ') + d.toLowerCase();
  }
  function methodList(c) {
    var d = c.discipline, D = discName(c);
    if (d === 'fukugo') return [['fukugo', 'Fukugo', 'Kumite ir kitei (vėliavėlės) pakaitomis, finalas - kumite.'], ['rr', 'Fukugo - ratų sistema', 'Visi su visais.']];
    if (d === 'kumite' || d === 'sumo') return [['elim', D + ' - olimpinė', 'Lentelė, kovos su balais ir laiku.'], ['rr', D + ' - ratų sistema', 'Visi su visais.']];
    return [['elim', D + ' vėliavėlėmis', 'Lentelė: laimėtoją teisėjai rodo vėliavėlėmis.'], ['score', D + ' balais', 'Teisėjai vertina balais, kraštiniai atmetami.'], ['rr', D + ' - ratų sistema', 'Visi su visais (vėliavėlės).']];
  }
  function sysName(c, sys) {
    var m = methodOf(sys), it = methodList(c).filter(function (x) { return x[0] === m; })[0];
    return (it ? it[1] : SYSTEMS[sys][0]) + (m === 'elim' ? ' · ' + MEDALS[sys] : '');
  }
  function elimDefault(c) { var f = cfg(c); return MEDALS[f.medals] ? f.medals : (c.team ? 'bronze' : 'plain'); }
  function methodToSys(c, m) { return m === 'elim' ? elimDefault(c) : m; }
  // Numatyta sistema pagal rungtį (pasirinkus rungtį - nustatoma pati, kol kovų dar nebuvo)
  function defaultSystem(c) {
    if (c.discipline === 'enbu') return 'score';
    if (c.discipline === 'fukugo') return 'fukugo';
    return c.team ? 'bronze' : 'plain';
  }
  function cfg(c) {
    c.cfg = c.cfg || {}; var f = c.cfg; f.judges = f.judges || 5; f.rounds = f.rounds || 1; f.finalists = f.finalists || 8; if (f.drop === undefined) f.drop = true;
    f.time = f.time || (c.discipline === 'sumo' ? 60 : 120); f.sumoTo = f.sumoTo || 3; if (f.bunkaiFinal === undefined) f.bunkaiFinal = false;
    f.avg = false;   // balų sistemoje: rato balas - teisėjų balų suma (be kraštinių); vidurkio nebėra (jis apvalindavo)
    return f;
  }
  // Kaip vyksta kova: kumite (balai +/- ir laikrodis), komandinis kumite (3 kovos, balai sumuojasi), sumo (iki N taškų),
  // vėliavėlės (kata, kihon, kitei), komandinės katos finalas (Kata + Pritaikymas balais)
  function boutMode(c, m) {
    var d = c.discipline, sys = c.draw ? c.draw.system : defaultSystem(c);
    if (d === 'sumo') return 'sumo';
    if (sys === 'fukugo' || d === 'fukugo') return m.left && m.left % 2 === 0 ? 'flags' : 'kumite';
    if (d === 'kumite') return c.team ? 'team' : 'kumite';
    if (c.team && cfg(c).bunkaiFinal && (m.left === 1 || m.id === 'bronze') && isElim(sys)) return 'score2';   // tik jei įjungta nustatymuose
    return 'flags';
  }
  var MODE_NAME = { kumite: 'Kumite', team: 'Komandinis kumite', sumo: 'Sumo', flags: 'Vėliavėlės', score2: 'Kata + Pritaikymas' };
  var HOME = CFG.homeClub || 'Karateka';
  var BELTS = CFG.belts || {};

  var S;
  // PHP tuščią žemėlapį {} grąžina kaip sąrašą []. Į sąrašą įrašytas rezultatas (results['r0m0'] = ...) ekrane matomas,
  // bet JSON.stringify jo neperduoda - po perkrovimo dingdavo. Todėl visi būsenos žemėlapiai užkraunant verčiami objektais.
  function asMap(x) {
    if (x && typeof x === 'object' && !Array.isArray(x)) return x;
    var o = {};
    if (Array.isArray(x)) x.forEach(function (v, i) { if (v !== null && v !== undefined) o[i] = v; });
    return o;
  }
  function fixMaps(c) {
    if (Array.isArray(c.crit)) c.crit = asMap(c.crit);
    if (Array.isArray(c.cfg)) c.cfg = asMap(c.cfg);
    var d = c.draw;
    if (!d || typeof d !== 'object') return;
    d.results = asMap(d.results); d.scores = asMap(d.scores);
    ['final', 'bouts', 'kata'].forEach(function (k) { if (Array.isArray(d[k])) d[k] = asMap(d[k]); });
    if (d.kata) ['scores', 'final'].forEach(function (k) { if (Array.isArray(d.kata[k])) d.kata[k] = asMap(d.kata[k]); });
  }
  function load() {
    S = CFG.state && typeof CFG.state === 'object' ? CFG.state : {};
    S.categories = Array.isArray(S.categories) ? S.categories : [];
    S.categories.forEach(fixMaps);
    S.people = S.people && !Array.isArray(S.people) ? S.people : {};
    S.teams = S.teams && !Array.isArray(S.teams) ? S.teams : {};
    S.categories.forEach(function (c) { c.competitors.forEach(function (p) { if (!p.team && !S.people[p.id]) S.people[p.id] = { id: p.id, name: p.name, club: p.club }; }); });
    S.clubs = Array.isArray(S.clubs) && S.clubs.length ? S.clubs : [HOME];
    S.areas = Array.isArray(S.areas) && S.areas.length ? S.areas : [{ id: 1, name: '1 TATAMIS', start: '09:30' }];
    S.clubInfo = S.clubInfo && !Array.isArray(S.clubInfo) ? S.clubInfo : {};   // klubų kontaktai: { klubas: { person, phone, email, note } }
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
  // Keli langai (pvz. atkabinta rungtis): išsaugojus - nauja būsena kitiems langams; jie ją perima, jei patys neturi neišsaugotų pakeitimų
  var POPUP = CFG.popup || null, stateCh = null;
  try { stateCh = new BroadcastChannel('taika-state-' + CFG.id); } catch (e) {}
  if (stateCh) stateCh.onmessage = function (ev) {
    var d = ev.data || {}; if (!d.state || d.v <= version || dirty || saving) return;
    var sel = S.selected;
    CFG.state = d.state; load(); S.selected = sel; version = d.v; markSaved(stateObj());
    setStatus('Išsaugota ✓', 'ok'); render();
  };
  function flush(keepalive) {
    if (!dirty || saving || conflict || !CFG.api) return;
    clearTimeout(saveTimer);
    saving = true; dirty = false;
    setStatus('Saugoma…', 'busy');
    var body = payload();
    fetch(CFG.api, { method: 'POST', credentials: 'same-origin', keepalive: !!keepalive,
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf, Accept: 'application/json' }, body: body })
      .then(function (r) { return r.json().then(function (d) { return { status: r.status, d: d }; }); })
      .then(function (x) {
        saving = false;
        if (x.status === 409) { resolveConflict(); return; }
        if (!x.d.ok) { dirty = true; setStatus('Neišsaugota: ' + (x.d.error || 'klaida'), 'err'); return; }
        version = x.d.version;
        markSaved(JSON.parse(body).state);
        setStatus('Išsaugota ✓', 'ok');
        // Baigtos rungtys automatiškai pažymimos „apdovanojama“ (#122) - būsena atnaujinama iš karto
        if (x.d.awards && !Array.isArray(x.d.awards) && JSON.stringify(x.d.awards) !== JSON.stringify(AW)) {
          AW = x.d.awards;
          if (!typingNow() && !dragId) render(); else awPending = true;
        }
        if (stateCh) { try { stateCh.postMessage({ v: version, state: JSON.parse(body).state }); } catch (e) {} }
        if (dirty) flush(); else dropPending();
      })
      .catch(function () { saving = false; dirty = true; setStatus('Nėra ryšio - bandysime dar kartą', 'err'); saveTimer = setTimeout(flush, 5000); });
  }
  // ---------- Kelios planšetės (D4): kas išsaugota paskutinį kartą - kad konflikto atveju būtų galima sujungti, o ne prašyti perkrauti ----------
  var savedSnap = null;
  function stateObj() { var st = {}; Object.keys(S).forEach(function (k) { if (k !== 'history' && k !== 'tournament') st[k] = S[k]; }); return JSON.parse(JSON.stringify(st)); }
  function markSaved(st) {
    var o = { cats: {}, people: {}, other: {} };
    (st.categories || []).forEach(function (c) { o.cats[c.id] = JSON.stringify(c); });
    Object.keys(st.people || {}).forEach(function (id) { o.people[id] = JSON.stringify(st.people[id]); });
    Object.keys(st).forEach(function (k) { if (k !== 'categories' && k !== 'people' && k !== 'selected') o.other[k] = JSON.stringify(st[k]); });
    savedSnap = o;
  }
  // Serverio būsena + šios planšetės pakeitimai (tik tos grupės, dalyviai ir nustatymai, kuriuos čia keitėme po paskutinio išsaugojimo)
  function rebase(server) {
    var local = stateObj(), snap = savedSnap || { cats: {}, people: {}, other: {} }, out = [], onServer = {}, inLocal = {};
    local.categories.forEach(function (c) { inLocal[c.id] = c; });
    (server.categories || []).forEach(function (c) {
      onServer[c.id] = true;
      var lc = inLocal[c.id];
      if (!lc) { if (snap.cats[c.id] === undefined) out.push(c); return; }   // čia ištrinta - neliko; kitur sukurta - lieka
      out.push(JSON.stringify(lc) !== snap.cats[c.id] ? lc : c);
    });
    local.categories.forEach(function (c) { if (!onServer[c.id] && snap.cats[c.id] === undefined) out.push(c); });   // čia sukurtos naujos
    server.categories = out;
    server.people = server.people && !Array.isArray(server.people) ? server.people : {};
    Object.keys(local.people || {}).forEach(function (id) { if (JSON.stringify(local.people[id]) !== snap.people[id]) server.people[id] = local.people[id]; });
    Object.keys(snap.people).forEach(function (id) { if (!(local.people || {})[id]) delete server.people[id]; });
    Object.keys(local).forEach(function (k) {
      if (k === 'categories' || k === 'people') return;
      if (k === 'selected' || snap.other[k] === undefined || JSON.stringify(local[k]) !== snap.other[k]) server[k] = local[k];
    });
    return server;
  }
  function applyServer(st, ver) {
    var sel = S.selected;
    CFG.state = st; load(); if (cat(sel)) S.selected = sel; version = ver;
  }
  // Konfliktas: kita planšetė ką tik išsaugojo - sujungiama automatiškai ir išsaugoma iš naujo (be perkrovimo)
  function resolveConflict() {
    setStatus('Sujungiama su kitų planšečių pakeitimais…', 'busy');
    fetch(CFG.api + '&state=1&v=-1', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok || !d.state) throw new Error('state');
        var serverSnapState = JSON.parse(JSON.stringify(d.state));
        var merged = rebase(d.state);
        applyServer(merged, d.version);
        markSaved(serverSnapState);   // tai, kas jau serveryje; mūsų pakeitimai - dar neišsaugoti
        dirty = true; conflict = false;
        if (!typingNow() && !dragId) render(); else awPending = true;
        flush();
      })
      .catch(function () { conflict = true; setStatus('Kitas žmogus pakeitė varžybas - perkraukite puslapį', 'err'); showConflict(); });
  }
  // Laisva planšetė kas 8 s pasiima kitų pakeitimus (kai niekas nerašoma ir neatidarytas kovos langas)
  function pullServer() {
    if (!CFG.api || CFG.readOnly || document.hidden || dirty || saving || conflict || typingNow() || dragId) return;
    fetch(CFG.api + '&state=1&v=' + version, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok || !d.state || d.version <= version || dirty || saving || typingNow() || dragId) return;
        applyServer(d.state, d.version); markSaved(stateObj()); render();
      }).catch(function () {});
  }
  setInterval(pullServer, 8000);
  // A1: neišsaugotų pakeitimų kopija šiame įrenginyje - jei skirtukas uždaromas, kol saugoma, po perkrovimo sujungiama ir išsaugoma
  var PEND = 'taika-pend-' + CFG.id;
  function keepPending() {
    if (CFG.readOnly || !CFG.api) return;
    try { localStorage.setItem(PEND, JSON.stringify({ t: Date.now(), v: version, snap: savedSnap, state: stateObj() })); } catch (e) {}
  }
  function dropPending() { try { localStorage.removeItem(PEND); } catch (e) {} }
  function save() {
    if (CFG.readOnly) return;
    dirty = true;
    keepPending();
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
  function hasBelt(l) { return l !== null && l !== undefined && l !== '' && !!BELTS[l]; }   // 0 = 10 kyu (ne „nėra“)
  function beltDot(l) { return hasBelt(l) ? '<span class="belt-dot" style="background:' + BELTS[l][2] + '" title="' + esc(BELTS[l][0]) + '"></span>' : ''; }
  function beltName(l) { return hasBelt(l) ? BELTS[l][0] : ''; }
  function beltOpts(cur) { return '<option value="">—</option>' + Object.keys(BELTS).map(function (k) { return '<option value="' + k + '"' + (cur !== null && cur !== undefined && cur !== '' && +k === +cur ? ' selected' : '') + '>' + esc(BELTS[k][0]) + '</option>'; }).join(''); }
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
  function shuffle(a, rnd) { rnd = rnd || Math.random; for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(rnd() * (i + 1)); var t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
  // Burtai, kurių dar niekas neprašė (grupė tik atidaryta), neišsaugomi iki pirmo veiksmo. Kad perkrovus puslapį ar kitoje
  // planšetėje poros būtų tos pačios, jie traukiami pagal pastovų skaičių iš varžybų, rungties ir dalyvių. „Burti iš naujo“ - tikrai atsitiktinai.
  function seededRnd(str) {
    var h = 1779033703 ^ str.length;
    for (var i = 0; i < str.length; i++) { h = Math.imul(h ^ str.charCodeAt(i), 3432918353); h = (h << 13) | (h >>> 19); }
    return function () {
      h = Math.imul(h ^ (h >>> 16), 2246822507); h = Math.imul(h ^ (h >>> 13), 3266489909); h ^= h >>> 16;
      var a = (h >>> 0) + 0x6D2B79F5; a = Math.imul(a ^ (a >>> 15), a | 1); a ^= a + Math.imul(a ^ (a >>> 7), a | 61);
      return ((a ^ (a >>> 14)) >>> 0) / 4294967296;
    };
  }
  function drawSeed(c, system) { return [CFG.id || 0, c.id, system, c.competitors.map(function (p) { return p.id; }).sort().join(',')].join('|'); }
  function makeDraw(c, system, rnd) {
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
  // ---------- Balai: teisėjų balai, kraštiniai atmetami (nebūtina), 1 arba 2 ratai ----------
  // Rato balas: teisėjų balų vidurkis (ar suma), kraštiniai (aukščiausias ir žemiausias) atmetami, kai teisėjų 5 ir daugiau
  function scoreTotal(c, arr) {
    var f = cfg(c), v = (arr || []).slice(0, f.judges).map(function (x) { return parseFloat(x); });
    if (v.length < f.judges || v.some(function (x) { return isNaN(x); })) return null;
    var sum = v.reduce(function (a, b) { return a + b; }, 0), n = v.length;
    if (f.drop && f.judges >= 5) { sum -= Math.max.apply(null, v) + Math.min.apply(null, v); n -= 2; }
    return f.avg ? Math.round(sum / n * 1000) / 1000 : Math.round(sum * 100) / 100;
  }
  function fmtScore(c, v) { return v === null || v === undefined ? '–' : cfg(c).avg ? (Math.round(v * 100) / 100).toFixed(2) : v.toFixed(1); }
  // Finale vieta - pusfinalio (1 rato) ir finalo balų suma (sum); lygiųjų atveju - finalo balas, tada visų balų suma
  function scoreRank(c, which, ids) {
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
  function scoreFinalists(c) {
    var r1 = scoreRank(c, 'scores', (c.draw.order || []).filter(function (id) { return person(c, id); }));
    if (r1.some(function (x) { return x.total === null; })) return [];
    // Finale pasirodymo eilė: nuo žemiausio pusfinalio balo iki aukščiausio (geriausias - paskutinis)
    return r1.slice(0, Math.min(cfg(c).finalists, r1.length)).map(function (x) { return x.id; }).reverse();
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
        .map(function (x) { return { id: x.id, key: (fin.indexOf(x.id) >= 0 ? 'F' : '') + x.sum + '|' + x.total + '|' + x.all }; });
    }
    var out = [], place = 0;
    ranked.forEach(function (x, i) { if (!i || x.key !== ranked[i - 1].key) place = i + 1; out.push([place, [x.id]]); });
    return out;
  }

  // ---------- Skirtukai ----------
  // Organizacinė pusė (klubai, komandos, dalyviai, kategorijos) ir varžybų diena (live tvarkaraštis, rezultatai).
  // Live tvarkaraštis: tatamių lenta; atidarius rungtį - jos valdymas (buvusios „Lentelės“), „Toliau →“ - kita rungtis.
  var tabs = ['klubai', 'komandos', 'dalyviai', 'kategorijos', 'tinklai', 'live', 'rezultatai'];
  // Viršutinis meniu: pagrindinė vieta - tvarkaraštis; paruošimo skirtukai (klubai … kategorijos) - „Administracijoje“
  var TOP = ['live', 'rezultatai', 'admin'], SUB = ['klubai', 'komandos', 'dalyviai', 'kategorijos', 'tinklai'];   // Tinklai - Administracijoje (#81)
  var lastSub = 'dalyviai';
  try { lastSub = sessionStorage.getItem(KEY + '-sub') || 'dalyviai'; } catch (e) {}
  // Išsaugota meniu tvarka be naujų skirtukų - jie įterpiami po numatyto kaimyno
  function menuOrder(o) {
    if (!o || !o.length) return TOP;
    o = o.filter(function (t, i) { return TOP.indexOf(t) >= 0 && o.indexOf(t) === i; });
    TOP.forEach(function (t, i) { if (o.indexOf(t) < 0) { var p = i ? o.indexOf(TOP[i - 1]) : -1; o.splice(p < 0 && i ? o.length : p + 1, 0, t); } });
    return o.join() === TOP.join() ? TOP : o;
  }
  var runOpen = false;
  try { runOpen = sessionStorage.getItem(KEY + '-run') === '1'; } catch (e) {}
  function setRun(open) { runOpen = !!open; try { sessionStorage.setItem(KEY + '-run', runOpen ? '1' : ''); } catch (e) {} }
  function openRun(id) {
    if (id && isDetached(id)) { toast('Ši rungtis atidaryta <b>atskirame lange</b> - ten ir valdykite. Uždarius tą langą, ji grįš čia.'); return; }
    if (id && S.selected !== id) { S.selected = id; save(); }
    var wasRun = runOpen && curTab() === 'live';
    setRun(true); showTab('live', true);
    if (!POPUP && !wasRun) history.pushState({ vz: 1, tab: 'live', run: S.selected }, '');
    var r = document.getElementById('run'); if (r && r.getBoundingClientRect().top < 0) r.scrollIntoView({ block: 'start' });
  }
  function curTab() { return tabs.find(function (t) { return !document.getElementById('v-' + t).hidden; }); }
  function showTab(t, noHist) {
    if (t === 'lenteles') { setRun(true); t = 'live'; } else if (t === 'tvarkarastis') t = 'live';   // seni pavadinimai (nuorodos, #)
    if (t === 'admin') t = SUB.indexOf(lastSub) >= 0 ? lastSub : 'dalyviai';
    var prev = curTab(), sub = SUB.indexOf(t) >= 0;
    if (sub) { lastSub = t; try { sessionStorage.setItem(KEY + '-sub', t); } catch (e) {} }
    tabs.forEach(function (x) {
      document.getElementById('tab-' + x).setAttribute('aria-selected', x === t ? 'true' : 'false');
      document.getElementById('v-' + x).hidden = x !== t;
    });
    var ta = document.getElementById('tab-admin'); if (ta) ta.setAttribute('aria-selected', sub ? 'true' : 'false');
    var sb = document.getElementById('tabs-sub'); if (sb) sb.hidden = !sub;
    try { sessionStorage.setItem(KEY + '-tab', t); } catch (e) {}
    // Naršyklės „Atgal“ grįžta į ankstesnį skirtuką ar tvarkaraštį, o ne į visų varžybų sąrašą
    if (!noHist && prev && prev !== t && !POPUP) history.pushState({ vz: 1, tab: t, run: t === 'live' && runOpen ? S.selected : null }, '');
    render();
  }
  tabs.forEach(function (t) { document.getElementById('tab-' + t).addEventListener('click', function () { if (t === 'live' && runOpen && curTab() === 'live') { setRun(false); render(); return; } showTab(t); }); });
  var tabAdmin = document.getElementById('tab-admin'); if (tabAdmin) tabAdmin.addEventListener('click', function () { showTab('admin'); });
  if (!POPUP) {
    history.replaceState({ vz: 1, tab: null, run: null }, '');
    window.addEventListener('popstate', function (e) {
      var st = e.state || {};
      // #62: naršyklės „Atgal“ su atidarytu kovos skydeliu - pirmiausia uždaro skydelį (balai išsaugomi), grupė lieka atidaryta
      if (B) { boutHist = false; keepBout(); closeBout(); return; }
      if (!st.vz) return;
      if (st.run && cat(st.run)) { S.selected = st.run; setRun(true); showTab('live', true); return; }
      setRun(false);
      showTab(st.tab || 'live', true);
    });
  }

  // ---------- Kategorijos: sąrašas | kategorija su nustatymais | dalyvių sąrašas (tempti) ----------
  var poolFilter = 'fit', poolQuery = '';
  function crit(c) { return c.crit || {}; }
  function fits(c, p) {
    var k = crit(c), y = p.birth;
    if (!p || (k.g && k.g !== 'X' && p.gender && p.gender !== k.g)) return false;
    if (k.y0 && y && y < k.y0) return false;
    if (k.y1 && y && y > k.y1) return false;
    if (k.b0 && hasBelt(p.belt) && p.belt < k.b0) return false;
    if (k.b1 && hasBelt(p.belt) && p.belt > k.b1) return false;
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
  // ---------- Kategorijų struktūra (kaip Kihapp „Divisions“): visos rungtys stulpeliais, blokai - metai, lytis, kyu ----------
  var catView = 'ov', ovQuery = '', ovGender = '', ovProblems = false, ovSort = 'order', ovOpen = {}, bDrag = null;
  try { catView = sessionStorage.getItem(KEY + '-catview') || 'ov'; } catch (e) {}
  function setCatView(v) { catView = v; try { sessionStorage.setItem(KEY + '-catview', v); } catch (e) {} renderCats(); }
  function kyuOpts(cur) { return '<option value="">—</option>' + Object.keys(BELTS).filter(function (k) { return +k > 0; }).map(function (k) { return '<option value="' + k + '"' + (+k === +cur ? ' selected' : '') + '>' + kyu(+k).replace(' kyu', '') + '</option>'; }).join(''); }   // ribose 10 kyu nereikia - tai „nuo pradžios“
  function catUnfit(c) { return c.competitors.filter(function (p) { return !p.team && S.people[p.id] && !fits(c, S.people[p.id]); }).length; }
  function catProblems(c) { return c.competitors.length < 2 || catUnfit(c) > 0; }
  // Rikiavimas didėjančia tvarka (gimimo metai, diržas, dalyvių sk.); ovDir: 1 - didėjančia, -1 - mažėjančia
  var OV_SORT = {
    years: function (a, b) { return (crit(a).y0 || crit(a).y1 || 0) - (crit(b).y0 || crit(b).y1 || 0) || (crit(a).y1 || 0) - (crit(b).y1 || 0); },
    belt: function (a, b) { return (crit(a).b0 || 0) - (crit(b).b0 || 0) || (crit(a).b1 || 0) - (crit(b).b1 || 0); },
    count: function (a, b) { return a.competitors.length - b.competitors.length; }
  };
  var ovDir = { years: -1, belt: 1, count: -1 };
  function renderOvDir() {
    var b = document.getElementById('ov-dir'); if (!b) return;
    b.hidden = !OV_SORT[ovSort];
    b.textContent = ovDir[ovSort] === 1 ? '↑ Didėjančia' : '↓ Mažėjančia';
    b.title = ovSort === 'years' ? (ovDir.years === 1 ? 'Vyriausi (seniausi gimimo metai) pirma' : 'Jauniausi pirma') : ovSort === 'belt' ? (ovDir.belt === 1 ? 'Žemesni diržai pirma' : 'Aukštesni diržai pirma') : (ovDir.count === 1 ? 'Mažiausiai dalyvių pirma' : 'Daugiausiai dalyvių pirma');
  }
  function ovMatch(c) {
    var g = crit(c).g || 'X', q = ovQuery;
    if (ovGender && g !== ovGender) return false;
    if (ovProblems && !catProblems(c)) return false;
    return !q || c.name.toLowerCase().indexOf(q) !== -1 || c.competitors.some(function (p) { return p.name.toLowerCase().indexOf(q) !== -1; });
  }
  function ovBlock(c) {
    var k = crit(c), n = c.competitors.length, unfit = catUnfit(c), custom = !!c.customName, g = k.g === 'M' || k.g === 'F' ? k.g : 'X';
    var people = ovOpen[c.id] ? '<div class="ovb-people">' + (c.competitors.map(function (p) {
      var pp = S.people[p.id] || {}, ok = p.team || fits(c, pp);
      return '<span class="ovp' + (ok ? '' : ' unfit') + '" draggable="true" data-drag="' + p.id + '" data-from="' + c.id + '" title="' + esc(p.club) + (ok ? '' : ' - netinka pagal kriterijus') + '">' + beltDot(pp.belt) + esc(p.name) + (pp.birth ? ' <i>' + pp.birth + '</i>' : '') + '</span>';
    }).join('') || '<span class="muted small">Tempkite dalyvius čia</span>') + '</div>' : '';
    return '<div class="ovb' + (n < 2 ? ' few' : '') + (unfit ? ' unfit' : '') + (c.team ? ' team' : '') + (catStarted(c) ? ' started' : '') + '" data-ovb="' + c.id + '" data-drop-cat="' + c.id + '">' +
      '<div class="ovb-row"><span class="ovb-grip" draggable="true" title="Tempkite - perkelti">⋮⋮</span>' +
      '<span class="ovb-years" title="Gimimo metai: jauniausi - vyriausi"><input class="ov-in" type="number" data-ov="y1" data-c="' + c.id + '" value="' + (k.y1 || '') + '" placeholder="−" title="' + (k.y1 ? 'Jauniausi gimę' : 'Be ribos (ir jaunesni) - paspauskite ir įrašykite metus') + '" aria-label="Gimę iki (jauniausi)">–<input class="ov-in" type="number" data-ov="y0" data-c="' + c.id + '" value="' + (k.y0 || '') + '" placeholder="+" title="' + (k.y0 ? 'Vyriausi gimę' : 'Be ribos (ir vyresni) - paspauskite ir įrašykite metus') + '" aria-label="Gimę nuo (vyriausi)"></span>' +
      '<select class="ov-sel" data-ov="g" data-c="' + c.id + '" aria-label="Lytis"><option value="M"' + (g === 'M' ? ' selected' : '') + '>M</option><option value="F"' + (g === 'F' ? ' selected' : '') + '>F</option><option value="X"' + (g === 'X' ? ' selected' : '') + '>Mix</option></select>' +
      '<span class="ovb-kyu" title="Kyu nuo - iki"><select class="ov-sel" data-ov="b0" data-c="' + c.id + '" aria-label="Diržas nuo">' + kyuOpts(k.b0) + '</select>–<select class="ov-sel" data-ov="b1" data-c="' + c.id + '" aria-label="Diržas iki">' + kyuOpts(k.b1) + '</select></span>' +
      '<button type="button" class="ovb-n" data-ov-open="' + c.id + '" aria-expanded="' + !!ovOpen[c.id] + '" title="' + (n < 2 ? 'Per mažai dalyvių' : 'Rodyti dalyvius') + (unfit ? ' · netinka: ' + unfit : '') + '">' + n + (unfit ? '<b>!</b>' : '') + '</button>' +
      '<button type="button" class="ovb-edit" data-ov-edit="' + c.id + '" title="Atidaryti kategoriją: sistema, kova, dalyviai" aria-label="Atidaryti kategoriją">→</button></div>' +
      (custom || c.team ? '<div class="ovb-name">' + (c.team ? '<span class="pill">komandos</span> ' : '') + esc(c.name) + '</div>' : '') + people + '</div>';
  }
  function renderOverview() {
    var ae = document.activeElement, keep = ae && ae.dataset && ae.dataset.c && ae.dataset.ov ? '[data-c="' + ae.dataset.c + '"][data-ov="' + ae.dataset.ov + '"]' : null;
    renderOverviewNow();
    if (keep) { var el = document.querySelector('#ov-cols ' + keep); if (el) el.focus(); }   // perpiešus - žymeklis lieka ten, kur buvo
  }
  function renderOverviewNow() {
    var discs = Object.keys(DISC).filter(function (d) { return S.categories.some(function (c) { return c.discipline === d; }); });
    var entries = 0, probs = 0;
    S.categories.forEach(function (c) { entries += c.competitors.length; if (catProblems(c)) probs++; });
    var none = Object.keys(S.people).filter(function (id) { return !catsOf(id).length; }).length;
    document.getElementById('ov-sum').innerHTML = S.categories.length + ' kategorijų · ' + entries + ' įrašų' + (probs ? ' · <b class="warn-t">' + probs + ' su problemomis</b>' : '') +
      (none ? ' · <button type="button" class="linkish" id="ov-none">' + none + ' dalyvių be kategorijos →</button>' : '');
    var others = function (d) { return Object.keys(DISC).filter(function (x) { return x !== d; }).map(function (x) { return '<option value="' + x + '">→ ' + DISC[x] + '</option>'; }).join(''); };
    document.getElementById('ov-cols').innerHTML = discs.map(function (d) {
      var list = S.categories.filter(function (c) { return c.discipline === d; }), shown = list.filter(ovMatch), n = 0;
      list.forEach(function (c) { n += c.competitors.length; });
      if (OV_SORT[ovSort]) shown = shown.slice().sort(function (x, y) { return OV_SORT[ovSort](x, y) * ovDir[ovSort]; });
      return '<div class="ov-col" data-ov-col="' + d + '"><div class="ov-head"><span class="disc d-' + d + '"></span><b>' + DISC[d] + '</b><span class="muted small">' + list.length + ' kat. · ' + n + ' dal.</span><span class="spacer"></span>' +
        '<button type="button" class="btn btn-xs" data-ov-redo="' + d + '" title="Netinkančius dalyvius perkelti į tinkamus blokus">⟳</button>' +
        '<select class="sel ov-copy" data-ov-copy="' + d + '" title="Nukopijuoti visus blokus į kitą rungtį" aria-label="Kopijuoti į"><option value="">⧉</option>' + others(d) + '</select>' +
        '<button type="button" class="btn btn-xs" data-ov-new="' + d + '" title="Naujas blokas">+</button></div>' +
        '<div class="ov-labels"><span></span><span>Gimę</span><span>Lytis</span><span>Kyu</span><span>Dalyv.</span><span></span></div>' +
        '<div class="ov-list">' + (shown.map(ovBlock).join('') || '<p class="muted small" style="padding:8px;">Nieko nerasta</p>') + '</div></div>';
    }).join('') + '<div class="ov-col ov-add"><select class="sel" id="ov-new-disc" aria-label="Nauja rungtis"><option value="">+ Rungtis…</option>' +
      Object.keys(DISC).filter(function (d) { return discs.indexOf(d) === -1; }).map(function (d) { return '<option value="' + d + '">' + DISC[d] + '</option>'; }).join('') + '</select></div>';
  }
  function newCatIn(d, after) {
    var c = { id: newId('k'), name: '', discipline: d, competitors: [], tatami: (after && after.tatami) || S.areas[0].id, draw: null, crit: after ? JSON.parse(JSON.stringify(after.crit || {})) : {} };
    c.name = critName(c);
    var last = -1; S.categories.forEach(function (x, i) { if (x.discipline === d) last = i; });
    S.categories.splice(last + 1, 0, c);
    return c;
  }
  // Netinkantys dalyviai perkeliami į tinkamiausią (siauriausią) tos rungties bloką; pradėtos kategorijos nekeičiamos
  function redistribute(d) {
    var movable = S.categories.filter(function (c) { return c.discipline === d && !c.team && !catStarted(c) && hasCrit(c); }), moved = 0, left = [];
    movable.forEach(function (c) {
      c.competitors.slice().forEach(function (p) {
        var pp = S.people[p.id]; if (!pp || fits(c, pp)) return;
        var target = movable.filter(function (x) { return x !== c && fits(x, pp); }).sort(function (x, y) { return catSpan(x) - catSpan(y); })[0];
        if (!target) { left.push(pp.name); return; }
        removeFromCat(p.id, c.id); addToCat(p.id, target.id); moved++;
      });
    });
    toast('Perkelta dalyvių: <b>' + moved + '</b>' + (left.length ? '<br>Tinkamo bloko nėra: ' + esc(left.slice(0, 6).join(', ')) + (left.length > 6 ? '…' : '') : ''));
  }
  // Visa rungties struktūra į kitą rungtį (pvz. Kata → Kumite): tie patys metai, lytis, kyu ir dalyviai; jau esantys tokie pat blokai praleidžiami
  function copyDiscipline(from, to) {
    var have = S.categories.filter(function (c) { return c.discipline === to; }).map(function (c) { return JSON.stringify(crit(c)); }), n = 0;
    S.categories.filter(function (c) { return c.discipline === from && !c.team; }).forEach(function (c) {
      if (have.indexOf(JSON.stringify(crit(c))) !== -1) return;
      var nc = newCatIn(to, c); nc.competitors = c.competitors.map(function (p) { return { id: p.id, name: p.name, club: p.club }; }); nc.name = critName(nc); n++;
    });
    toast(DISC[from] + ' → ' + DISC[to] + ': nukopijuota blokų <b>' + n + '</b>. Pakeiskite, kas skiriasi.');
  }
  // Iššokantys meniu (↺ Atstatyti, 📋 Šablonas, 🎓 Diplomai) - neišlenda už ekrano krašto
  document.addEventListener('toggle', function (e) {
    var d = e.target; if (!d.classList || !d.classList.contains('tool-menu') || !d.open) return;
    var body = d.querySelector('.tool-menu-body'); if (!body) return;
    body.style.left = ''; body.style.right = '';
    var r = body.getBoundingClientRect();
    if (r.left < 8) { body.style.left = '0'; body.style.right = 'auto'; }
  }, true);
  // ---------- Amžiaus kategorijų šablonas (📋): saugomas pagal amžių - tinka ir kitais metais; taikomas iš karto kelioms rungtims ----------
  var INDIV = ['kata', 'kumite', 'fukugo', 'kihon'];
  function critKey(k) { return [k.y0 || '', k.y1 || '', k.g === 'M' || k.g === 'F' ? k.g : '', k.b0 || '', k.b1 || ''].join('|'); }
  function tplFrom(d) {
    var Y = CFG.year;
    return S.categories.filter(function (c) { return c.discipline === d && !c.team; }).map(function (c) {
      var k = crit(c); return { a0: k.y1 ? Y - k.y1 : null, a1: k.y0 ? Y - k.y0 : null, g: k.g === 'M' || k.g === 'F' ? k.g : '', b0: k.b0 || null, b1: k.b1 || null };
    });
  }
  function tplApply(list, discs) {
    var Y = CFG.year, n = 0;
    discs.forEach(function (d) {
      var have = S.categories.filter(function (c) { return c.discipline === d; }).map(function (c) { return critKey(crit(c)); });
      list.forEach(function (b) {
        var k = {};
        if (b.a1 !== null && b.a1 !== undefined) k.y0 = Y - b.a1;
        if (b.a0 !== null && b.a0 !== undefined) k.y1 = Y - b.a0;
        if (b.g) k.g = b.g;
        if (b.b0) k.b0 = b.b0;
        if (b.b1) k.b1 = b.b1;
        if (have.indexOf(critKey(k)) !== -1) return;
        var nc = newCatIn(d, null); nc.crit = k; nc.name = critName(nc); have.push(critKey(k)); n++;
      });
      redistribute(d);   // jau esantys dalyviai - į tinkamus naujus blokus
    });
    return n;
  }
  function renderTpl() {
    var box = document.getElementById('ov-tpl-body'), tpl = UI.tpl || [];
    var discs = Object.keys(DISC).filter(function (d) { return S.categories.some(function (c) { return c.discipline === d && !c.team; }); });
    box.innerHTML = '<b>Amžiaus kategorijų šablonas</b><p class="small muted">' + (tpl.length
      ? 'Išsaugota blokų: <b>' + tpl.length + '</b> (pagal amžių - tinka ir kitais metais). Pažymėkite rungtis - blokai bus sukurti visiems iš karto.'
      : 'Sudėliokite vienos rungties blokus (metai, lytis, kyu) ir išsaugokite. Tada - viena paspauda visoms rungtims ir kitoms varžyboms.') + '</p>' +
      (tpl.length ? '<div class="tpl-discs">' + INDIV.map(function (d) { return '<label class="small"><input type="checkbox" data-tpl-disc="' + d + '" checked> ' + DISC[d] + '</label>'; }).join('') + '</div>' +
        '<button type="button" class="btn btn-primary btn-sm" id="tpl-apply">Sukurti blokus pažymėtoms rungtims</button>' : '') +
      (discs.length ? '<div class="tpl-save"><span class="small muted">' + (tpl.length ? 'Pakeisti šabloną pagal:' : 'Išsaugoti šabloną pagal:') + '</span>' +
        discs.map(function (d) { return '<button type="button" class="btn btn-sm" data-tpl-save="' + d + '">' + DISC[d] + '</button>'; }).join('') + '</div>' : '<p class="small muted">Pirma sukurkite bent vieną bloką.</p>');
  }
  document.getElementById('ov-tpl').addEventListener('toggle', function (e) { if (e.target.open) renderTpl(); });
  document.getElementById('ov-tpl-body').addEventListener('click', function (e) {
    var sv = e.target.closest('[data-tpl-save]');
    if (sv) {
      var list = tplFrom(sv.dataset.tplSave);
      if (!list.length) return;
      if ((UI.tpl || []).length && !confirm('Pakeisti išsaugotą šabloną (' + UI.tpl.length + ' blokų) rungties „' + DISC[sv.dataset.tplSave] + '“ blokais (' + list.length + ')?')) return;
      postUi({ tpl: list }).then(function () { if (CFG.demo) UI.tpl = list; renderTpl(); toast('Šablonas išsaugotas: <b>' + list.length + '</b> blokų.'); });
      return;
    }
    if (e.target.id === 'tpl-apply') {
      var discs = [].slice.call(document.querySelectorAll('[data-tpl-disc]:checked')).map(function (x) { return x.dataset.tplDisc; });
      if (!discs.length) return;
      snapshot();
      var n = tplApply(UI.tpl || [], discs);
      document.getElementById('ov-tpl').open = false;
      save(); render();
      toast('Pagal šabloną sukurta blokų: <b>' + n + '</b> (' + discs.map(function (d) { return DISC[d]; }).join(', ') + '). Jau buvę tokie pat - praleisti.');
    }
  });
  var ovBox = document.getElementById('cat-overview');
  document.getElementById('v-kategorijos').addEventListener('click', function (e) {
    var b = e.target.closest('[data-catview]'); if (b) { setCatView(b.dataset.catview); return; }
    var g = e.target.closest('[data-ovg]'); if (g) { ovGender = g.dataset.ovg; document.querySelectorAll('[data-ovg]').forEach(function (x) { x.classList.toggle('on', x === g); }); renderOverview(); return; }
    if (e.target.id === 'ov-auto') { document.getElementById('cat-auto').click(); return; }
  });
  document.getElementById('ov-search').addEventListener('input', function (e) { ovQuery = e.target.value.trim().toLowerCase(); renderOverview(); });
  document.getElementById('ov-problems').addEventListener('change', function (e) { ovProblems = e.target.checked; renderOverview(); });
  document.getElementById('ov-sort').addEventListener('click', function (e) {
    var b = e.target.closest('[data-sort]'); if (!b) return;
    ovSort = b.dataset.sort;
    this.querySelectorAll('[data-sort]').forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', x === b); });
    renderOvDir(); renderOverview();
  });
  document.getElementById('ov-dir').addEventListener('click', function () { if (!OV_SORT[ovSort]) return; ovDir[ovSort] = -ovDir[ovSort]; renderOvDir(); renderOverview(); });
  ovBox.addEventListener('click', function (e) {
    var t = e.target.closest('button'); if (!t) return;
    if (t.dataset.ovOpen) { ovOpen[t.dataset.ovOpen] = !ovOpen[t.dataset.ovOpen]; renderOverview(); return; }
    if (t.dataset.ovEdit) { S.selected = t.dataset.ovEdit; save(); setCatView('edit'); window.scrollTo(0, 0); return; }
    if (t.id === 'ov-none') { poolFilter = 'none'; setCatView('edit'); return; }
    if (t.dataset.ovNew) { snapshot(); var nc = newCatIn(t.dataset.ovNew, null); save(); renderOverview(); var inp = ovBox.querySelector('[data-c="' + nc.id + '"][data-ov="y1"]'); if (inp) inp.focus(); return; }
    if (t.dataset.ovRedo) { snapshot(); redistribute(t.dataset.ovRedo); save(); render(); }
  });
  ovBox.addEventListener('change', function (e) {
    var t = e.target;
    if (t.dataset.ovCopy !== undefined) { if (t.value) { snapshot(); copyDiscipline(t.dataset.ovCopy, t.value); save(); render(); } return; }
    if (t.id === 'ov-new-disc') { if (t.value) { snapshot(); newCatIn(t.value, null); save(); render(); } return; }
    var c = cat(t.dataset.c); if (!c || !t.dataset.ov) return;
    snapshot();
    var v = t.value.trim();
    c.crit = c.crit || {};
    c.crit[t.dataset.ov] = t.dataset.ov === 'g' ? v : (+v || null);
    if (!c.customName) c.name = critName(c);   // pavadinimo niekas nekeitė ranka - atsinaujina pats
    save(); renderSoon();
  });
  // Bloko tempimas: į kitą vietą tame pačiame ar kitame stulpelyje (rungtis pasikeičia)
  ovBox.addEventListener('dragstart', function (e) {
    var g = e.target.closest && e.target.closest('.ovb-grip'); if (!g) return;
    var blk = g.closest('[data-ovb]'); bDrag = blk.dataset.ovb;
    e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', bDrag); e.dataTransfer.setDragImage(blk, 16, 16); } catch (x) {}
    blk.classList.add('dragging');
  });
  ovBox.addEventListener('dragend', function () { bDrag = null; ovBox.querySelectorAll('.dragging, .drop-before').forEach(function (x) { x.classList.remove('dragging', 'drop-before'); }); });
  ovBox.addEventListener('dragover', function (e) {
    if (!bDrag) return; var col = e.target.closest('[data-ov-col]'); if (!col) return;
    e.preventDefault();
    ovBox.querySelectorAll('.drop-before').forEach(function (x) { x.classList.remove('drop-before'); });
    var over = e.target.closest('[data-ovb]'); if (over && over.dataset.ovb !== bDrag) over.classList.add('drop-before');
  });
  ovBox.addEventListener('drop', function (e) {
    if (!bDrag) return; var col = e.target.closest('[data-ov-col]'); if (!col) return; e.preventDefault();
    var c = cat(bDrag), over = e.target.closest('[data-ovb]'), d = col.dataset.ovCol; bDrag = null;
    if (!c || (over && over.dataset.ovb === c.id)) return;
    snapshot();
    S.categories.splice(S.categories.indexOf(c), 1);
    if (c.discipline !== d) { c.discipline = d; delete cfg(c).time; cfg(c); applyDefaultSystem(c); if (!c.customName) c.name = critName(c); }
    var idx = over ? S.categories.indexOf(cat(over.dataset.ovb)) : -1;
    if (idx === -1) { var last = -1; S.categories.forEach(function (x, i) { if (x.discipline === d) last = i; }); S.categories.splice(last + 1, 0, c); }
    else S.categories.splice(idx, 0, c);
    save(); render();
  });

  function renderCats() {
    document.querySelectorAll('[data-catview]').forEach(function (b) { b.classList.toggle('on', b.dataset.catview === catView); b.setAttribute('aria-pressed', b.dataset.catview === catView); });
    document.querySelectorAll('.ov-only').forEach(function (x) { x.hidden = catView !== 'ov'; });
    document.getElementById('cat-overview').hidden = catView !== 'ov';
    document.getElementById('cat-edit').hidden = catView === 'ov';
    if (catView === 'ov') { renderOverview(); return; }
    var ul = document.getElementById('cat-list');
    ul.innerHTML = S.categories.map(function (c) {
      var bad = c.competitors.length === 1 ? ' one' : '';
      return '<li data-drop-cat="' + c.id + '"' + (c.id === S.selected ? ' aria-current="true"' : '') + '><button type="button" data-cat="' + c.id + '"><span class="cl-name"><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) +
        (sysBadge(c) ? ' <span class="sys-mini">' + sysBadge(c) + '</span>' : '') + '</span><span class="cnt' + bad + '">' + c.competitors.length + '</span></button></li>';
    }).join('') || '<li class="muted small" style="padding:12px;">Kategorijų dar nėra. Spauskite „⚡ Automatiškai“ arba „+ Nauja“.</li>';
    var c = cat(S.selected) || S.categories[0];
    var curLi = ul.querySelector('[aria-current]'); if (curLi && curLi.scrollIntoView) curLi.scrollIntoView({ block: 'nearest' });
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
      '<div class="cat-back"><button type="button" class="btn btn-sm" id="cat-back" title="Atgal į visų kategorijų struktūrą (ten, kur buvote)">← Grįžti į kategorijas</button></div>' +
      '<h2><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + ' <span class="pill">' + c.competitors.length + ' dalyv.</span>' + (stale ? ' <span class="pill warn">Lentelė pasenusi</span>' : '') +
        '<span class="spacer"></span><button class="btn btn-primary" type="button" id="cat-draw" title="Atidaryti Live tvarkaraštyje: lentelė, kovos, balai">Valdyti →</button></h2>' +
      '<div class="cat-scroll">' +
      '<div class="fmt">' +
        '<div class="fmt-row"><span class="fmt-label">Pavadinimas</span><div class="fmt-ctl"><input type="text" class="sel wide" id="cat-name" value="' + esc(c.name) + '" aria-label="Kategorijos pavadinimas"><button class="btn" type="button" id="cat-autoname" title="Pagal rungtį ir kriterijus">↻ Pagal kriterijus</button></div></div>' +
        '<div class="fmt-row"><span class="fmt-label">Rungtis</span><div class="fmt-ctl seg">' + Object.keys(DISC).map(function (d) {
          return '<button type="button" class="seg-btn' + (d === c.discipline ? ' on' : '') + '" data-disc="' + d + '"><span class="disc d-' + d + '"></span>' + DISC[d] + '</button>';
        }).join('') + '<label class="small team-chk"><input type="checkbox" id="cat-team"' + (c.team ? ' checked' : '') + '> Komandinė</label></div></div>' +
        '<div class="fmt-row"><span class="fmt-label">Sistema</span><div class="fmt-ctl"><div class="sys-cards">' + methodList(c).map(function (x) {
          var key = methodToSys(c, x[0]);
          return '<button type="button" class="sys-card' + (methodOf(sys) === x[0] ? ' on' : '') + '" data-sys="' + key + '"><span class="sys-ico sys-' + key + '" aria-hidden="true"></span><b>' + esc(x[1]) + '</b><span>' + esc(x[2]) + '</span></button>';
        }).join('') + '</div>' +
        (methodOf(sys) === 'elim' ? '<label class="sys-medals">Medaliai <select class="sel" data-medals="1">' + Object.keys(MEDALS).map(function (k3) { return '<option value="' + k3 + '"' + (k3 === sys ? ' selected' : '') + '>' + MEDALS[k3] + '</option>'; }).join('') + '</select></label>' : '') +
        '</div></div>' +
        '<div class="fmt-row"><span class="fmt-label">Kova</span><div class="fmt-ctl crit">' +
          '<label>Teisėjai <input class="sel num-in" type="number" min="1" max="15" data-cfg="judges" value="' + f.judges + '" title="Kiek teisėjų vertina (bet koks skaičius)"></label>' +
          (c.discipline === 'kumite' || c.discipline === 'fukugo' || c.discipline === 'sumo' ? '<label>Kovos laikas <select class="sel" data-cfg="time">' + [10, 30, 60, 90, 120, 150, 180].map(function (n) { return '<option value="' + n + '"' + (n === f.time ? ' selected' : '') + '>' + Math.floor(n / 60) + ':' + (n % 60 < 10 ? '0' : '') + (n % 60) + (n === 10 ? ' (Jiyu ippon, Ko-Go)' : '') + '</option>'; }).join('') + '</select></label>' : '') +
          (c.discipline === 'sumo' ? '<label>Laimi surinkęs <input class="sel num-in" type="number" min="1" max="20" data-cfg="sumoTo" value="' + f.sumoTo + '"> tšk.</label>' : '') +
          (c.team && c.discipline !== 'kumite' && isElim(sys) ? '<label><input type="checkbox" data-cfg="bunkaiFinal"' + (f.bunkaiFinal ? ' checked' : '') + '> Finale ir dėl bronzos: Kata + Pritaikymas (balai)</label>' : '') +
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
    if (t.id === 'cat-name') { c.name = t.value.trim() || c.name; c.customName = c.name !== critName(c); }
    else if (t.id === 'cat-team') { snapshot(); c.team = t.checked; applyDefaultSystem(c); }
    else if (t.dataset.medals) { cfg(c).medals = t.value; setSystem(c, t.value); return; }
    else if (t.dataset.cfg) { snapshot(); var f0 = cfg(c); f0[t.dataset.cfg] = t.type === 'checkbox' ? t.checked : Math.max(1, parseInt(t.value, 10) || 1); if (f0.judges > 15) f0.judges = 15; }
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
    if (t.id === 'cat-back') {   // atgal į struktūrą - prie tos pačios kategorijos, trumpai paryškinta
      setCatView('ov');
      var blk = document.querySelector('#ov-cols [data-ovb="' + c.id + '"]');
      if (blk) { blk.scrollIntoView({ block: 'center' }); blk.classList.add('flash'); setTimeout(function () { blk.classList.remove('flash'); }, 1600); }
      return;
    }
    if (t.dataset.remove) { snapshot(); c.competitors = c.competitors.filter(function (p) { return p.id !== t.dataset.remove; }); }
    else if (t.dataset.disc) { snapshot(); c.discipline = t.dataset.disc; delete cfg(c).time; cfg(c); applyDefaultSystem(c); }
    else if (t.dataset.sys) { setSystem(c, t.dataset.sys); return; }
    else if (t.id === 'cat-autoname') { snapshot(); c.name = critName(c); delete c.customName; }
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
    } else if (t.id === 'cat-draw') { openRun(S.selected); return; }
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
          '<td class="club-btns"><button class="btn' + (openContacts === k ? ' on' : '') + '" type="button" data-club-contacts="' + esc(k) + '" aria-expanded="' + (openContacts === k) + '">☎ Kontaktai</button> <button class="btn" type="button" data-club-show="' + esc(k) + '">Dalyviai →</button>' + (st[k].people ? '' : ' <button class="btn btn-danger" type="button" data-club-del="' + esc(k) + '">Pašalinti</button>') + '</td></tr>' +
          (openContacts === k ? clubContactsRow(k) : '');
      }).join('') + '</tbody><tfoot><tr><td>Iš viso</td><td class="num">' + tp + '</td><td class="num">' + te + '</td><td class="num">' + tt + '</td><td></td></tr></tfoot></table>';
  }
  // Klubo kontaktai: atsakingas asmuo, telefonas, el. paštas, pastaba (saugoma varžybose); LTKF sąrašo duomenys - kaip pradžia;
  // žemiau - telefonai, kuriuos klubas nurodė registruodamasis per nuorodą
  var openContacts = '';
  function clubContactsRow(k) {
    var info = S.clubInfo[k] || {}, dir = (CFG.clubDirectory || {})[k] || {}, regs = (CFG.clubContacts || {})[k] || [];
    var field = function (f, label, type) {
      var v = info[f] !== undefined ? info[f] : (dir[f] || '');
      var link = v && f === 'phone' ? ' <a class="small" href="tel:' + esc(v.replace(/[^\d+]/g, '')) + '">skambinti</a>' : v && f === 'email' ? ' <a class="small" href="mailto:' + esc(v) + '">rašyti</a>' : '';
      return '<label class="ci-field"><span>' + label + link + '</span><input class="sel" type="' + type + '" value="' + esc(v) + '" data-ci-club="' + esc(k) + '" data-ci-field="' + f + '"' + (CFG.readOnly ? ' readonly' : '') + '></label>';
    };
    return '<tr class="club-contacts"><td colspan="5"><div class="ci-grid">' + field('person', 'Atsakingas asmuo (treneris)', 'text') + field('phone', 'Telefonas', 'tel') + field('email', 'El. paštas', 'email') + field('note', 'Pastaba', 'text') + '</div>' +
      (regs.length ? '<div class="ci-regs small"><b>Registravosi per nuorodą:</b> ' + regs.map(function (r) { return '<a href="tel:' + esc(String(r.phone).replace(/[^\d+]/g, '')) + '">' + esc(r.phone) + '</a> <span class="muted">(' + r.n + ' dal.)</span>'; }).join(', ') + '</div>'
        : '<div class="ci-regs small muted">Per registracijos nuorodą šis klubas dar nesiregistravo.</div>') + '</td></tr>';
  }
  document.getElementById('clubs-view').addEventListener('input', function (e) {
    var k = e.target.dataset.ciClub, f = e.target.dataset.ciField; if (!k || !f) return;
    S.clubInfo[k] = S.clubInfo[k] || {};
    S.clubInfo[k][f] = e.target.value.trim();
    save();
  });
  // Kontaktų Excel - pirmiausia išsaugoma, kad faile būtų ką tik įrašyti kontaktai
  document.getElementById('clubs-xlsx').addEventListener('click', function (e) {
    if (!dirty && !saving) return;
    e.preventDefault(); var href = this.href; flush();
    var wait = setInterval(function () { if (!dirty && !saving) { clearInterval(wait); location.href = href; } }, 200);
    setTimeout(function () { clearInterval(wait); location.href = href; }, 4000);
  });
  document.getElementById('club-add').addEventListener('click', function () {
    var inp = document.getElementById('club-new-name'), n = inp.value.trim(); if (!n) { inp.focus(); return; }
    if (S.clubs.indexOf(n) === -1) { snapshot(); S.clubs.push(n); save(); }
    inp.value = ''; render();
  });
  document.getElementById('clubs-view').addEventListener('change', function (e) {
    var old = e.target.dataset.clubRename, n = e.target.value.trim(); if (!old || !n || n === old) return;
    snapshot();
    if (S.clubInfo[old]) { S.clubInfo[n] = S.clubInfo[old]; delete S.clubInfo[old]; }
    if (openContacts === old) openContacts = n;
    S.clubs = S.clubs.map(function (k) { return k === old ? n : k; }); if (S.clubs.indexOf(n) === -1) S.clubs.push(n);
    Object.keys(S.people).forEach(function (id) { if (S.people[id].club === old) updatePerson(id, { club: n }); });
    Object.keys(S.teams).forEach(function (id) { if (S.teams[id].club === old) updateTeam(id, { club: n }); });
    save(); renderSoon();
  });
  var pplClub = '';
  document.getElementById('clubs-view').addEventListener('click', function (e) {
    if (e.target.dataset.clubContacts !== undefined) { openContacts = openContacts === e.target.dataset.clubContacts ? '' : e.target.dataset.clubContacts; renderClubs(); }
    else if (e.target.dataset.clubShow) { pplClub = e.target.dataset.clubShow; showTab('dalyviai'); }
    else if (e.target.dataset.clubDel) { snapshot(); S.clubs = S.clubs.filter(function (k) { return k !== e.target.dataset.clubDel; }); save(); render(); }
  });

  // ---------- Dalyviai (kaip Kihapp „Individuals“) ----------
  function renderPeople() {
    testButtons();
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
    document.getElementById('people-view').innerHTML = findStrip(q) +
      '<h2>Dalyviai (' + list.length + ')' + (noneCount ? ' <span class="pill warn">' + noneCount + ' be kategorijos</span>' : '') + '</h2>' +
      '<div class="table-scroll"><table class="ppl-table"><thead><tr><th>Vardas, pavardė</th><th>Klubas</th><th class="num">Gim. metai</th><th>Lytis</th><th>Diržas</th><th>Kategorijos</th><th class="num">Įrašai</th><th></th></tr></thead><tbody>' +
      list.map(function (x) {
        var p = x.p;
        return '<tr' + (x.cats.length ? '' : ' class="warn-row"') + '><td><div class="name-cell">' + (p.member ? '<span class="home-mark" title="Karateka narys">K</span>' : '') + '<input class="inline" value="' + esc(p.name) + '" data-p-name="' + p.id + '" aria-label="Vardas">' + (p.noPhoto ? '<span class="nophoto" title="Nesutinka būti fotografuojamas ir filmuojamas">be nuotraukų</span>' : '') + '</div></td>' +
          '<td data-label="Klubas"><select class="sel" data-p-club="' + p.id + '" aria-label="Klubas">' + clubOpts(p.club) + '</select></td>' +
          '<td class="num" data-label="Gim. metai"><input class="inline num-in" type="number" min="1940" max="' + YEAR + '" value="' + (p.birth || '') + '" data-p-birth="' + p.id + '" aria-label="Gimimo metai"></td>' +
          '<td data-label="Lytis"><select class="sel" data-p-gender="' + p.id + '" aria-label="Lytis"><option value="">—</option><option value="M"' + (p.gender === 'M' ? ' selected' : '') + '>M</option><option value="F"' + (p.gender === 'F' ? ' selected' : '') + '>F</option></select></td>' +
          '<td class="belt-cell" data-label="Diržas">' + beltDot(p.belt) + '<select class="sel" data-p-belt="' + p.id + '" aria-label="Diržas">' + beltOpts(p.belt) + '</select></td>' +
          '<td class="cats-cell" data-label="Kategorijos">' + x.cats.map(function (c) { return '<span class="cchip"><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + '<button type="button" data-p-rm="' + p.id + '" data-c="' + c.id + '" aria-label="Išimti iš ' + esc(c.name) + '">×</button></span>'; }).join('') +
          '<select class="sel add-cat" data-p-add="' + p.id + '" aria-label="Pridėti į kategoriją"><option value="">+ kategorija…</option>' + catOpts + '</select></td>' +
          '<td class="num cnt-cell">' + x.cats.length + '</td><td class="del-cell"><button class="btn btn-danger btn-xs" type="button" data-p-del="' + p.id + '" aria-label="Ištrinti ' + esc(p.name) + '">×</button></td></tr>';
      }).join('') + '</tbody></table></div>' + (list.length ? '' : '<p class="muted" style="padding:12px 14px;">' + (q || pplClub || noCat ? 'Pagal filtrą dalyvių nėra.' : 'Dalyvių dar nėra - įrašykite vardą paieškoje ar „Naujas“ laukelyje arba spauskite „+ Karateka nariai“.') + '</p>');
  }
  // Paieška ir kartu pridėjimas: randami dar nepridėti klubo nariai (vienu paspaudimu) arba pridedamas naujas žmogus tokiu vardu
  function findStrip(q) {
    if (!q || CFG.readOnly) return '';
    var mem = (CFG.members || []).filter(function (m) { return !S.people['m' + m.id] && m.name.toLowerCase().indexOf(q) !== -1; }).slice(0, 12);
    var raw = document.getElementById('ppl-search').value.trim(), exists = Object.keys(S.people).some(function (id) { return S.people[id].name.toLowerCase() === q; });
    return '<div class="find-strip">' + (mem.length ? '<span class="small muted">Klubo nariai:</span>' + mem.map(function (m) {
      return '<button type="button" class="btn btn-sm" data-find-member="' + m.id + '">+ ' + esc(m.name) + (m.birth ? ' <span class="muted">' + m.birth + '</span>' : '') + '</button>'; }).join('') : '') +
      (!exists && raw.length > 2 ? '<button type="button" class="btn btn-sm btn-ghost" data-find-new="1">+ Pridėti naują: <b>' + esc(raw) + '</b></button>' : '') + '</div>';
  }
  function addMemberPeople(ids) {
    if (S.clubs.indexOf(HOME) === -1) S.clubs.unshift(HOME);
    (CFG.members || []).forEach(function (m) {
      if (ids.indexOf(m.id) === -1 || S.people['m' + m.id]) return;
      S.people['m' + m.id] = { id: 'm' + m.id, name: m.name, club: HOME, birth: m.birth, gender: m.gender, belt: m.belt, member: m.id, noPhoto: m.photo === 0 };
    });
  }
  function addNewPeople(names, club) {
    var added = [];
    names.forEach(function (n) {
      n = n.replace(/\s+/g, ' ').trim(); if (!n) return;
      if (Object.keys(S.people).some(function (id) { return S.people[id].name.toLowerCase() === n.toLowerCase(); })) return;
      var id = newId('c'); S.people[id] = { id: id, name: n, club: club || HOME, birth: null, gender: null, belt: null, member: null }; added.push(id);
    });
    if (club && S.clubs.indexOf(club) === -1) S.clubs.push(club);
    return added;
  }
  document.getElementById('people-view').addEventListener('click', function (e) {
    var fm = e.target.closest('[data-find-member]'), fn = e.target.closest('[data-find-new]');
    if (!fm && !fn) return;
    snapshot();
    if (fm) { addMemberPeople([+fm.dataset.findMember]); toastPlaced(['m' + fm.dataset.findMember]); }
    else { addNewPeople([document.getElementById('ppl-search').value], document.getElementById('ppl-new-club').value || HOME); }
    var s = document.getElementById('ppl-search'); s.value = ''; save(); render(); s.focus();
  });
  ['ppl-search', 'ppl-nocat'].forEach(function (id) { document.getElementById(id).addEventListener('input', function () { renderPeople(); }); });
  document.getElementById('ppl-club').addEventListener('change', function (e) { pplClub = e.target.value; renderPeople(); });
  document.getElementById('ppl-add').addEventListener('click', function () {
    var inp = document.getElementById('ppl-new-name'), n = inp.value.trim(); if (!n) { inp.focus(); return; }
    snapshot(); addNewPeople(n.split(/\n|;/), document.getElementById('ppl-new-club').value);
    inp.value = ''; save(); render(); inp.focus();
  });
  // Enter - pridėti iškart; įklijavus kelias eilutes - pridedami visi
  document.getElementById('ppl-new-name').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('ppl-add').click(); } });
  document.getElementById('ppl-new-name').addEventListener('paste', function (e) {
    var tx = (e.clipboardData || window.clipboardData).getData('text'); if (!/\n/.test(tx)) return;
    e.preventDefault(); snapshot(); var n = addNewPeople(tx.split(/\n/), document.getElementById('ppl-new-club').value).length;
    save(); render(); if (n) setStatus('Pridėta dalyvių: ' + n, 'ok');
  });
  document.getElementById('ppl-search').addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return; e.preventDefault();
    var b = document.querySelector('#people-view [data-find-member], #people-view [data-find-new]'); if (b) b.click();
  });
  document.getElementById('people-view').addEventListener('change', function (e) {
    var t = e.target;
    if (t.dataset.pName && t.value.trim()) { snapshot(); updatePerson(t.dataset.pName, { name: t.value.trim() }); }
    else if (t.dataset.pClub) { snapshot(); updatePerson(t.dataset.pClub, { club: t.value }); }
    else if (t.dataset.pBirth) { snapshot(); S.people[t.dataset.pBirth].birth = +t.value || null; toastPlaced([t.dataset.pBirth]); }
    else if (t.dataset.pGender) { snapshot(); S.people[t.dataset.pGender].gender = t.value || null; toastPlaced([t.dataset.pGender]); }
    else if (t.dataset.pBelt) { snapshot(); S.people[t.dataset.pBelt].belt = t.value === '' ? null : +t.value; toastPlaced([t.dataset.pBelt]); }
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

  // ---------- 🧪 +100 testinių dalyvių: išgalvoti vaikai ir suaugę iš kitų klubų (pažymėti test: true, ištrinami vienu mygtuku) ----------
  var TEST_FIRST = { M: ['Jonas', 'Lukas', 'Matas', 'Domas', 'Tomas', 'Paulius', 'Rokas', 'Kajus', 'Nojus', 'Augustas', 'Ignas', 'Dovydas', 'Herkus', 'Benas', 'Jokūbas', 'Adomas'],
    F: ['Ona', 'Ieva', 'Gabija', 'Emilija', 'Austėja', 'Kamilė', 'Urtė', 'Gustė', 'Liepa', 'Saulė', 'Rugilė', 'Smiltė', 'Viltė', 'Elzė', 'Luknė', 'Goda'] };
  var TEST_LAST = ['Kazlauskas', 'Petrauskas', 'Jankauskas', 'Stankevičius', 'Vasiliauskas', 'Žukauskas', 'Butkus', 'Paulauskas', 'Urbonas', 'Kavaliauskas',
    'Navickas', 'Rimkus', 'Balčiūnas', 'Mockus', 'Šimkus', 'Lukoševičius', 'Morkūnas', 'Grigas', 'Sakalauskas', 'Vaitkus'];
  function testFemale(l) { return /ius$/.test(l) ? l.replace(/ius$/, 'iūtė') : l.replace(/as$/, 'aitė').replace(/us$/, 'utė').replace(/is$/, 'ytė'); }
  function rnd(a) { return a[Math.floor(Math.random() * a.length)]; }
  function testCount() { return S && S.people ? Object.keys(S.people).filter(function (k) { return S.people[k].test; }).length : 0; }
  function testButtons() { var n = testCount(), d = document.getElementById('ppl-test-del'); if (d) { d.hidden = !n; d.textContent = 'Ištrinti testinius (' + n + ')'; } }
  document.getElementById('ppl-test100').addEventListener('click', function () {
    if (!confirm('Pridėti 100 išgalvotų dalyvių iš kitų klubų? Jie pažymėti kaip testiniai - vėliau ištrinsite vienu mygtuku.')) return;
    snapshot();
    var clubs = S.clubs.filter(function (c) { return c !== HOME; });
    if (!clubs.length) { clubs = ['Testo klubas A', 'Testo klubas B', 'Testo klubas C']; clubs.forEach(function (c) { if (S.clubs.indexOf(c) === -1) S.clubs.push(c); }); }
    var used = {}, ids = [];
    Object.keys(S.people).forEach(function (k) { used[S.people[k].name] = true; });
    for (var i = 0; i < 100; i++) {
      var g = Math.random() < 0.5 ? 'M' : 'F', name = '';
      for (var guard = 0; guard < 40; guard++) { var l = rnd(TEST_LAST); name = rnd(TEST_FIRST[g]) + ' ' + (g === 'F' ? testFemale(l) : l); if (!used[name]) break; }
      if (used[name]) name += ' ' + (i + 1);
      used[name] = true;
      var age = rnd([6, 7, 8, 8, 9, 9, 10, 10, 11, 11, 12, 12, 13, 13, 14, 15, 16, 17, 19, 24, 31, 38]);
      var belt = Math.max(1, Math.min(12, Math.round((age - 5) / 1.6) + Math.floor(Math.random() * 4) - 1));
      var id = newId('x') + i;
      S.people[id] = { id: id, name: name, club: rnd(clubs), birth: YEAR - age, gender: g, belt: belt, member: null, test: true };
      ids.push(id);
    }
    if (!S.categories.some(function (c) { return !c.team; })) autoCategories();   // kategorijų dar nėra - sudaromos
    else toastPlaced(ids);                                                     // yra - kiekvienas į tinkamas
    save(); render(); testButtons();
    toast('Pridėta 100 testinių dalyvių. Ištrinti - „Ištrinti testinius“.');
  });
  document.getElementById('ppl-test-del').addEventListener('click', function () {
    var n = testCount(); if (!n || !confirm('Ištrinti visus testinius dalyvius (' + n + ') iš varžybų ir kategorijų?')) return;
    snapshot();
    var gone = {};
    Object.keys(S.people).forEach(function (k) { if (S.people[k].test) { gone[k] = true; delete S.people[k]; } });
    S.categories.forEach(function (c) { c.competitors = c.competitors.filter(function (x) { return !gone[x.id]; }); });
    Object.keys(S.teams).forEach(function (k) { S.teams[k].members = S.teams[k].members.filter(function (m) { return !gone[m]; }); });
    save(); render(); testButtons();
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
    else S.categories.forEach(function (c) { if (!c.team && hasCrit(c) && fits(c, p) && (!p.discs || p.discs.indexOf(c.discipline) !== -1)) discs[c.discipline] = true; });
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
    var dnd = firstRound && !c.draw.locked && (swapMode || !Object.keys(c.draw.results).length);
    var slotIdx = firstRound ? (side === 'a' ? 2 * +m.id.replace('r0m', '') : 2 * +m.id.replace('r0m', '') + 1) : -1;
    return '<div class="' + cls + (dnd ? ' dnd' : '') + (extra ? ' has-extra' : '') + '"' + (dnd ? ' data-drop-slot="' + slotIdx + '"' : '') + '><span class="stripe"></span><button type="button" data-m="' + m.id + '" data-p="' + (id || '') + '"' +
      (dnd && p ? ' data-drag-slot="' + si + '" title="Tempkite ant kito dalyvio - apsikeis vietomis"' : '') + (clickable && p ? '' : ' disabled') + '>' + label + '</button>' + (extra || '') + '</div>';
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
  function diplomaUrl(catId, html) { return CFG.diplomaApi + (catId ? '&cat=' + encodeURIComponent(catId) : '') + (html ? '&html=1' : ''); }
  function placesHtml(c, places, empty) {
    return '<div class="podium"><h3>Vietos' + (places.length && CFG.diplomaApi && !CFG.readOnly ? ' <a class="btn btn-xs dipl-btn" href="' + esc(diplomaUrl(c.id, false)) + '" target="_blank" rel="noopener" title="' +
      (CFG.diplomaTpl ? 'Diplomai šios kategorijos prizininkams - Excel pagal jūsų šabloną' : 'Spausdinti diplomus šios kategorijos prizininkams') + '">🎓 Diplomai</a>' : '') + '</h3>' + (places.length ? places.map(function (pl) {
      return pl[1].map(function (id) { var p = person(c, id); return '<div class="place p' + Math.min(pl[0], 4) + '"><span>' + pl[0] + '</span><span><b>' + esc(p ? p.name : '?') + '</b> <span class="muted">' + esc(p ? p.club : '') + '</span></span></div>'; }).join('');
    }).join('') : '<p class="muted small">' + empty + '</p>') + '</div>';
  }
  function drawHead(c, done, all) {
    return '<div class="draw-head"><h2><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + '</h2><span class="pill">' + c.competitors.length + ' dalyv.</span>' +
      '<span class="pill sys">' + esc(sysName(c, c.draw.system)) + '</span><span class="pill ' + (done === all && all ? 'ok' : '') + '">' + (c.draw.system === 'score' ? 'Pasirodymai' : 'Kovos') + ': ' + done + ' / ' + all + '</span>' +
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
    html += '<p class="hint">' + (swapMode ? '⇄ Vietų keitimas: <b>tempkite vardą ant kito</b> (ar spauskite du vardus) pirmame rate - jie apsikeis vietomis. Baigę - „✓ Baigti keitimą“.' : 'Spauskite dalyvio vardą kovoje – jis laimi (dar kartą – atšaukiama). Balus įrašykite kovos viršuje. Kol kovų nebuvo, dalyvius pirmame rate galima <b>tempti</b> - apsikeis vietomis. Raudonas – aka, baltas – shiro.') + '</p></div>';
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
    html += '</div><div class="panel rr-table"><h2>Lentelė</h2><table><thead><tr><th class="num">#</th><th>Dalyvis</th><th class="num">Perg.</th><th class="num">Pralaim.</th><th class="num" title="Visų kovų taškai (vėliavėlės) kartu">Taškai</th></tr></thead><tbody>' +
      st.map(function (x, i) { var p = person(c, x.id); return '<tr><td class="num">' + (i + 1) + '</td><td><b>' + esc(p.name) + '</b> <span class="muted small">' + esc(p.club) + '</span></td><td class="num"><b>' + x.w + '</b></td><td class="num">' + x.l + '</td><td class="num" title="Skirtumas ' + (x.diff > 0 ? '+' : '') + x.diff + '"><b>' + x.pts + '</b></td></tr>'; }).join('') +
      '</tbody></table></div></div>';
    html += '<div class="draw-foot">' + placesHtml(c, catPlaces(c), 'Vietos atsiras, kai įvyks visos kovos.') +
      '<p class="hint">Visi kovoja su visais. Vietos: pergalės → tarpusavio kova → balų skirtumas (iš kovos balų, pvz. 3:1).</p></div>';
    view.innerHTML = html;
  }
  // Katos pavadinimas kiekvienam dalyviui kiekviename rate (pasirinkti iš sąrašo ar įrašyti); finale ta pati kata kaip pusfinalyje - pažymima
  var KATA_LIST = ['Taikyoku Shodan', 'Heian Shodan', 'Heian Nidan', 'Heian Sandan', 'Heian Yondan', 'Heian Godan', 'Tekki Shodan', 'Tekki Nidan', 'Tekki Sandan',
    'Bassai Dai', 'Bassai Sho', 'Kanku Dai', 'Kanku Sho', 'Enpi', 'Jion', 'Jitte', 'Jiin', 'Hangetsu', 'Gankaku', 'Chinte', 'Sochin', 'Nijushiho', 'Meikyo',
    'Unsu', 'Wankan', 'Gojushiho Dai', 'Gojushiho Sho', 'Rohai', 'Seienchin', 'Saifa', 'Seipai', 'Seisan', 'Kururunfa', 'Suparinpei', 'Sanchin', 'Tensho'];
  function kataOf(c, which, id) { var k = c.draw && c.draw.kata && c.draw.kata[which]; return (k && k[id]) || ''; }
  function kataDatalist() {
    var el = document.getElementById('kata-list'), have = {};
    if (!el) { el = document.createElement('datalist'); el.id = 'kata-list'; document.body.appendChild(el); }
    var names = KATA_LIST.slice();
    S.categories.forEach(function (c) { var k = c.draw && c.draw.kata; if (k) ['scores', 'final'].forEach(function (w) { Object.keys(k[w] || {}).forEach(function (id) { var n = k[w][id]; if (n && names.indexOf(n) === -1) names.push(n); }); }); });
    var html = names.map(function (n) { return have[n] ? '' : (have[n] = 1, '<option value="' + esc(n) + '">'); }).join('');
    if (el.innerHTML !== html) el.innerHTML = html;
  }
  function kataInput(c, which, id, cls) {
    var v = kataOf(c, which, id), dup = which === 'final' && v && v.toLowerCase() === kataOf(c, 'scores', id).toLowerCase();
    return '<input class="kata-in' + (cls ? ' ' + cls : '') + (dup ? ' dup' : '') + '" list="kata-list" maxlength="40" placeholder="Kata…" data-kata="' + id + '" data-which="' + which + '" value="' + esc(v) + '" aria-label="Kata"' + (dup ? ' title="Ši kata jau buvo pusfinalyje"' : '') + '>';
  }
  function scoreTable(c, ids, which, title) {
    var f = cfg(c), src = which === 'final' ? (c.draw.final = c.draw.final || {}) : (c.draw.scores = c.draw.scores || {});
    var rank = scoreRank(c, which, ids), placeOf = {}, kata = c.discipline === 'kata', fin = which === 'final';
    rank.forEach(function (x, i) { if (x.sum !== null) placeOf[x.id] = i + 1; });
    var sumOf = {}; rank.forEach(function (x) { sumOf[x.id] = x; });
    var head = '<tr><th class="num">Eil.</th><th>Dalyvis</th>' + (kata ? '<th>Kata</th>' : '');
    for (var j = 0; j < f.judges; j++) head += '<th class="num">T' + (j + 1) + '</th>';
    var lbl = (f.avg ? 'Vidurkis' : 'Suma') + (f.drop && f.judges >= 5 ? '*' : '');
    head += fin ? '<th class="num">Finalas</th><th class="num">Pusfinalis</th><th class="num">Iš viso</th>' : '<th class="num">' + lbl + '</th>';
    head += '<th class="num">Vieta</th></tr>';
    return '<div class="panel score-panel"><h2>' + esc(title) + '</h2><div class="table-scroll"><table class="score-table"><thead>' + head + '</thead><tbody>' +
      ids.map(function (id, i) {
        var p = person(c, id), arr = src[id] || [], t = scoreTotal(c, arr), drop = droppedIdx(c, arr), x = sumOf[id] || {};
        var canOrd = which === 'scores' && (swapMode || !scoreDone(c));
        // Klasės sc-*: telefone eilutė tampa kortele (vardas, balas ir vieta viršuje, kata, teisėjų langeliai eilute)
        var row = '<tr' + (t !== null ? ' class="scored"' : '') + (canOrd ? ' data-drop-ord="' + id + '"' : '') + '><td class="num sc-eil">' + (canOrd ? '<span class="ord-grip" data-drag-ord="' + id + '" title="Tempkite - keisti eilę">⠿</span>' : '') + (i + 1) + '</td><td class="sc-name"><b>' + esc(p.name) + '</b> <span class="muted small">' + esc(p.club) + '</span></td>' + (kata ? '<td class="sc-kata">' + kataInput(c, which, id) + '</td>' : '');
        for (var j = 0; j < f.judges; j++) row += '<td class="num sc-j"><input class="j-in' + (drop.indexOf(j) >= 0 ? ' dropped' : '') + '" type="number" step="0.1" min="0" max="10" inputmode="decimal" placeholder="T' + (j + 1) + '" data-j="' + j + '" data-sp="' + id + '" data-which="' + which + '" value="' + (arr[j] !== undefined && arr[j] !== null ? esc(arr[j]) : '') + '" aria-label="Teisėjas ' + (j + 1) + '"></td>';
        row += fin ? '<td class="num sc-sub" data-l="Finalas">' + fmtScore(c, t) + '</td><td class="num sc-sub" data-l="Pusfinalis">' + fmtScore(c, x.r1) + '</td><td class="num sc-sum" data-l="Iš viso"><b>' + fmtScore(c, x.sum) + '</b></td>' : '<td class="num sc-sum" data-l="' + lbl + '"><b>' + fmtScore(c, t) + '</b></td>';
        return row + '<td class="num sc-place">' + (placeOf[id] ? '<span class="rank r' + Math.min(placeOf[id], 4) + '">' + placeOf[id] + '</span>' : '') + '</td></tr>';
      }).join('') + '</tbody></table></div></div>';
  }
  function renderScore(c, view) {
    var f = cfg(c), ids = (c.draw.order || []).filter(function (id) { return person(c, id); });
    var html = drawHead(c, scoreDone(c), matchCount(c));
    html += '<div class="score-cfg"><label>Teisėjai <input class="sel num-in" type="number" min="1" max="15" data-cfg="judges" value="' + f.judges + '" title="Kiek teisėjų vertina (bet koks skaičius)"></label>' +
      '<label><input type="checkbox" data-cfg="drop"' + (f.drop ? ' checked' : '') + (f.judges < 5 ? ' disabled' : '') + '> Atmesti aukščiausią ir žemiausią balą</label>' +
      '<label>Ratai <select class="sel" data-cfg="rounds"><option value="1"' + (f.rounds === 1 ? ' selected' : '') + '>1</option><option value="2"' + (f.rounds === 2 ? ' selected' : '') + '>2 (finalas)</option></select></label>' +
      (f.rounds === 2 ? '<label>Į finalą <input class="sel num-in" type="number" min="2" max="16" data-cfg="finalists" value="' + f.finalists + '"></label>' : '') + '</div>';
    kataDatalist();
    html += scoreTable(c, ids, 'scores', f.rounds === 2 ? 'Pusfinalis (1 ratas)' : 'Balai');
    if (f.rounds === 2) {
      var fin = scoreFinalists(c);
      html += fin.length ? scoreTable(c, fin, 'final', 'Finalas (' + fin.length + ' geriausi)') : '<p class="muted small">Finalas atsiras, kai visi gaus pusfinalio balus.</p>';
    }
    var what = f.avg ? 'vidurkis' : 'suma';
    html += '<div class="draw-foot">' + placesHtml(c, catPlaces(c), 'Vietos atsiras, kai visi gaus balus.') +
      '<p class="hint">Įrašykite kiekvieno teisėjo balą be taško: 75 → 7.5. ' + (f.drop && f.judges >= 5 ? '* ' + (f.avg ? 'Vidurkis' : 'Suma') + ' be aukščiausio ir žemiausio balo - jie lieka matomi perbraukti. ' : '') +
      (f.rounds === 2 ? 'Finalininkų vietos - pusfinalio ir finalo ' + (f.avg ? 'vidurkių' : 'sumų') + ' suma; lygiųjų atveju - finalo ' + what + ', tada visų balų suma. ' : 'Lygiųjų atveju lemia visų balų suma. ') +
      (c.discipline === 'kata' ? 'Kata - pasirinkite iš sąrašo ar įrašykite. ' : '') + 'Eilės tvarka - burtų; „Burti iš naujo“ ją sumaišo.' +
      (f.rounds === 2 ? ' <b>Finale pasirodo nuo žemiausio pusfinalio balo iki aukščiausio.</b>' : '') + '</p></div>';
    var y = window.scrollY; view.style.minHeight = view.offsetHeight + 'px';
    // v1.94 (#175): lentelė atnaujinama vietoje - langelis, į kurį rašoma, nepakeičiamas nauju, todėl planšetėje
    // klaviatūra neužsidaro ir ekranas nebeperšoka į viršų
    if (view.querySelector('.score-panel')) morphHtml(view, html); else view.innerHTML = html;
    view.style.minHeight = ''; if (window.scrollY !== y) window.scrollTo(window.scrollX, y);
  }
  // Paprastas DOM atnaujinimas vietoje: tie patys elementai lieka (su fokusu), keičiami tik tekstai, atributai ir tai, kas skiriasi
  function morphHtml(el, html) {
    var tpl = document.createElement('div'); tpl.innerHTML = html;
    morphKids(el, tpl);
  }
  function morphKids(a, b) {
    var bn = Array.prototype.slice.call(b.childNodes);
    for (var i = 0; i < bn.length; i++) {
      var x = a.childNodes[i], y = bn[i];
      if (!x) { a.appendChild(y); continue; }
      if (x.nodeType !== y.nodeType || x.nodeName !== y.nodeName) { a.replaceChild(y, x); continue; }
      if (x.nodeType !== 1) { if (x.nodeValue !== y.nodeValue) x.nodeValue = y.nodeValue; continue; }
      var active = x === document.activeElement;
      Array.prototype.slice.call(x.attributes).forEach(function (at) { if (!y.hasAttribute(at.name)) x.removeAttribute(at.name); });
      Array.prototype.slice.call(y.attributes).forEach(function (at) { if (!(active && at.name === 'value') && x.getAttribute(at.name) !== at.value) x.setAttribute(at.name, at.value); });
      if (x.tagName === 'INPUT') {
        if (x.type === 'checkbox' || x.type === 'radio') x.checked = y.hasAttribute('checked');
        else if (!active && x.value !== (y.getAttribute('value') || '')) x.value = y.getAttribute('value') || '';
        continue;
      }
      if (x.tagName === 'SELECT' && !active) { x.innerHTML = y.innerHTML; continue; }
      if (x.tagName === 'TEXTAREA') { if (!active) x.value = y.value; continue; }
      morphKids(x, y);
    }
    while (a.childNodes.length > bn.length) a.removeChild(a.lastChild);
  }
  function renderDraw() {
    var sel = document.getElementById('draw-cat');
    sel.innerHTML = S.categories.map(function (c) { return '<option value="' + c.id + '"' + (c.id === S.selected ? ' selected' : '') + '>' + esc(c.name) + ' (' + c.competitors.length + ')</option>'; }).join('');
    var c = cat(S.selected), view = document.getElementById('draw-view');
    if (!c) { view.innerHTML = '<p class="muted">Kategorijų dar nėra.</p>'; return; }
    if (!c.draw || (drawStale(c) && !c.draw.locked)) {
      if (c.competitors.length < 2) { view.innerHTML = '<p class="muted">Kategorijoje per mažai dalyvių lentelei.</p>'; return; }
    }
    if (!c.draw) { var sys0 = defaultSystem(c); c.draw = makeDraw(c, sys0, seededRnd(drawSeed(c, sys0))); }
    c.draw.scores = c.draw.scores || {};
    // Pasikeitė dalyviai, o kovų dar nebuvo - burtai traukiami iš naujo automatiškai
    var started = Object.keys(c.draw.results).length || (!isElim(c.draw.system) && scoreDone(c));
    if (drawStale(c) && !c.draw.locked && !started) { c.draw = makeDraw(c, c.draw.system); clearAward(c); save(); }
    var dsel = document.getElementById('draw-system'), msel = document.getElementById('draw-medals');
    dsel.innerHTML = methodList(c).map(function (x) { return '<option value="' + x[0] + '">' + esc(x[1]) + '</option>'; }).join('');
    dsel.value = methodOf(c.draw.system);
    if (msel) { msel.closest('label').style.display = methodOf(c.draw.system) === 'elim' ? '' : 'none'; msel.value = MEDALS[c.draw.system] ? c.draw.system : elimDefault(c); }
    var sw = document.getElementById('draw-swap'); sw.setAttribute('aria-pressed', swapMode ? 'true' : 'false'); sw.textContent = swapMode ? '✓ Baigti keitimą' : '⇄ Keisti vietas';
    document.getElementById('draw-view').classList.toggle('swap-mode', swapMode);
    document.getElementById('draw-swap').hidden = !isElim(c.draw.system);
    document.getElementById('draw-lock').setAttribute('aria-pressed', c.draw.locked ? 'true' : 'false');
    document.getElementById('draw-lock').textContent = c.draw.locked ? '🔒 Užrakinta' : '🔓 Užrakinti';
    document.getElementById('draw-new').disabled = c.draw.locked;
    document.getElementById('draw-swap').disabled = c.draw.locked;
    document.getElementById('draw-undo').disabled = !S.history.length;
    fillReset(c);
    if (runView === 'simple' && runOpen && !document.getElementById('run').hidden) renderSimple(c, view);
    else if (c.draw.system === 'rr') renderRR(c, view);
    else if (c.draw.system === 'score') renderScore(c, view);
    else renderElim(c, view);
  }
  // ---------- Paprastas vaizdas (kaip Kihapp): kairėje AKA, dešinėje SHIRO, diržai, kas ruošiasi; balų sistemoje - vienas dalyvis ----------
  var runView = 'lines';
  try { runView = sessionStorage.getItem(KEY + '-runview') || 'lines'; } catch (e) {}
  function svPerson(c, id, side) {
    var p = person(c, id) || {}, pp = S.people[id] || {}, belt = pp.belt;
    return '<div class="sv-who"><div class="sv-label">' + (side === 'a' ? 'AKA' : side === 'b' ? 'SHIRO' : '') + '</div>' +
      '<div class="sv-name">' + esc(p.name || '—') + '</div><div class="sv-club">' + esc(p.club || '') + (pp.birth ? ' · ' + pp.birth : '') + '</div>' +
      (hasBelt(belt) ? '<div class="sv-belt"><span style="background:' + BELTS[belt][2] + '"></span>' + esc(kyu(+belt)) + '</div>' : '') + '</div>';
  }
  // Kai kita pora dar nežinoma (laukia šios kovos nugalėtojo) - rodoma, kas ruošiasi: žinomas dalyvis ir „šios kovos nugalėtojas“, arba kita grupė
  function prepLater(c, cur) {
    var nm = function (id) { return esc((person(c, id) || {}).name || ''); };
    var all = isElim(c.draw.system) ? allMatches(bracket(c)) : [];
    var nx = all.filter(function (x) { return x.id !== cur.id && !x.winner && !x.bye && (x.a || x.b); }).sort(function (x, y) { return (x.no || 99) - (y.no || 99); })[0];
    if (nx) {
      var one = nx.a || nx.b;
      return '<div class="sv-prep"><b>Ruošiasi:</b> ' + esc(nx.title || matchTitle(c, nx)) + ' - <span class="sv-dot ' + (nx.a ? 'a' : 'b') + '"></span>' + nm(one) + ' <span class="muted">vs</span> <i>šios kovos nugalėtojas</i></div>';
    }
    var ng = nextGroup(c);
    if (!ng) return '';
    var up = upcoming(ng)[0], pair = up ? (up.b ? esc((person(ng, up.a) || {}).name || '') + ' <span class="muted">vs</span> ' + esc((person(ng, up.b) || {}).name || '') : esc((person(ng, up.a) || {}).name || '')) : '';
    return '<div class="sv-prep"><b>Ruošiasi kita grupė:</b> ' + esc(ng.name) + (pair ? ' - ' + pair : '') + '</div>';
  }
  function renderSimple(c, view) {
    var list = upcoming(c), m = list[0], prep = list[1];
    var nb = runNeighbours(c);
    if (!m) {
      var pl = catPlaces(c).filter(function (x) { return x[0] <= 3; });
      view.innerHTML = '<div class="sv-done"><div class="sv-done-mark">✓</div><h2>Kategorija baigta</h2>' +
        (pl.length ? '<ol class="sv-places">' + pl.map(function (x) { return x[1].map(function (id) { return '<li><b>' + x[0] + '</b> ' + esc((person(c, id) || {}).name || '') + ' <span class="muted">' + esc((person(c, id) || {}).club || '') + '</span></li>'; }).join(''); }).join('') + '</ol>' : '') +
        (function () { var ng = nextGroup(c); return ng ? '<button type="button" class="btn btn-primary sv-next-cat" data-run-go="' + ng.id + '">Sekanti grupė: ' + esc(ng.name) + ' →</button>' : ''; })() + '</div>';
      return;
    }
    if (c.draw.system === 'score') {
      var w = m.which || 'scores', arr = ((w === 'final' ? c.draw.final : c.draw.scores) || {})[m.a] || [], J = cfg(c).judges, cells = '';
      for (var j = 0; j < J; j++) cells += '<input class="j-in sv-j" type="number" step="0.1" min="0" max="10" inputmode="decimal" data-j="' + j + '" data-sp="' + m.a + '" data-which="' + w + '" value="' + (arr[j] !== undefined && arr[j] !== null ? esc(arr[j]) : '') + '" aria-label="Teisėjas ' + (j + 1) + '">';
      if (c.discipline === 'kata') kataDatalist();
      pushScoreLive(c, m.a, w);
      view.innerHTML = '<div class="sv-meta"><b>' + esc(m.title) + '</b></div><div class="sv sv-score"><div class="sv-side sv-a">' + svPerson(c, m.a, '') +
        (c.discipline === 'kata' ? '<label class="sv-kata">Kata ' + kataInput(c, w, m.a) + '</label>' : '') +
        '<div class="sv-judges">' + cells + '</div><p class="muted small">Balai be taško: 75 → 7.5. Enter - kitas teisėjas, po paskutinio - kitas dalyvis.</p></div></div>' +
        (prep ? '<div class="sv-prep"><b>Ruošiasi:</b> ' + esc((person(c, prep.a) || {}).name || '') + ' <span class="muted">' + esc((person(c, prep.a) || {}).club || '') + '</span></div>' : '');
      return;
    }
    var mode = boutMode(c, m), flags = mode === 'flags';
    var winBtn = function (side) { var id = side === 'a' ? m.a : m.b; return '<button type="button" class="sv-win" data-m="' + m.id + '" data-p="' + id + '">Laimėjo ' + (side === 'a' ? 'AKA' : 'SHIRO') + '</button>' + (flags ? '<div class="sv-flags" title="Kiek vėliavėlių gavo laimėjęs">' + flagChips(c, m, side) + '</div>' : ''); };
    view.innerHTML = '<div class="sv-meta"><b>' + esc(m.title || matchTitle(c, m)) + '</b>' + (m.no ? ' · kova ' + m.no : '') + ' · ' + MODE_NAME[mode] +
        (flags ? ' · <span class="muted">pažymėkite, kiek vėliavėlių gavo laimėjęs</span>' : '') +
        (mode !== 'flags' ? '<span class="spacer"></span><button type="button" class="btn" data-bout="' + m.id + '">▶ Kovos skydelis (balai, laikas)</button>' : '') + '</div>' +
      '<div class="sv"><div class="sv-side sv-a">' + svPerson(c, m.a, 'a') + winBtn('a') + '</div><div class="sv-vs">VS</div><div class="sv-side sv-b">' + svPerson(c, m.b, 'b') + winBtn('b') + '</div></div>' +
      (prep ? '<div class="sv-prep"><b>Ruošiasi:</b> <span class="sv-dot a"></span>' + esc((person(c, prep.a) || {}).name || '') + ' <span class="muted">vs</span> <span class="sv-dot b"></span>' + esc((person(c, prep.b) || {}).name || '') +
        (list.length > 2 ? ' <span class="muted">· dar ' + (list.length - 2) + ' kov.</span>' : '') + '</div>' : prepLater(c, m));
  }
  document.getElementById('draw-view').addEventListener('click', function (e) { var g = e.target.closest('[data-run-go]'); if (g) goRun(g.dataset.runGo); });
  // Sistemos keitimas: olimpinės tarpusavyje - rezultatai lieka; į ratų ar balų - nauji burtai
  function setSystem(c, sys) {
    if (!c || !SYSTEMS[sys] || (c.draw && c.draw.system === sys)) return;
    var started = c.draw && (Object.keys(c.draw.results || {}).length || (!isElim(c.draw.system) && scoreDone(c)));
    var same = c.draw && isElim(c.draw.system) && isElim(sys);
    if (started && !same && !confirm('Pakeitus sistemą, jau įrašyti kovų rezultatai ar balai dings. Tęsti?')) { render(); return; }
    snapshot();
    if (same) { c.draw.system = sys; prune(c); } else { c.draw = makeDraw(c, sys); clearAward(c); }
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
  document.getElementById('draw-system').addEventListener('change', function (e) { var c = cat(S.selected); setSystem(c, methodToSys(c, e.target.value)); });
  var drawMedals = document.getElementById('draw-medals');
  if (drawMedals) drawMedals.addEventListener('change', function (e) { var c = cat(S.selected); cfg(c).medals = e.target.value; setSystem(c, e.target.value); });
  function clearAward(c) { if (AW[c.id]) setAward([c.id], ''); }
  document.getElementById('draw-new').addEventListener('click', function () { var c = cat(S.selected); if (c.competitors.length < 2) return; snapshot(); c.draw = makeDraw(c, c.draw ? c.draw.system : defaultSystem(c)); clearAward(c); picked = null; save(); render(); });
  document.getElementById('draw-swap').addEventListener('click', function () { swapMode = !swapMode; picked = null; render(); });
  document.getElementById('draw-lock').addEventListener('click', function () { var c = cat(S.selected); c.draw.locked = !c.draw.locked; swapMode = false; save(); render(); });
  document.getElementById('draw-undo').addEventListener('click', function () { undo(); save(); render(); });
  document.getElementById('draw-view').addEventListener('click', function (e) {
    var fl = e.target.closest('[data-flag]');
    if (fl) {
      var c0 = cat(S.selected), parts = fl.dataset.flag.split('|'), m0 = findMatch(c0, parts[0]); if (!m0 || !m0.a || !m0.b) return;
      var J = cfg(c0).judges, n = +parts[2], wid = parts[1] === 'a' ? m0.a : m0.b, txt = parts[1] === 'a' ? n + ':' + (J - n) : (J - n) + ':' + n;
      snapshot();
      var wasDone0 = catFinished(c0);
      if (c0.draw.results[m0.id] === wid && c0.draw.scores[m0.id] === txt) { delete c0.draw.results[m0.id]; delete c0.draw.scores[m0.id]; }
      else { c0.draw.results[m0.id] = wid; c0.draw.scores[m0.id] = txt; }
      prune(c0); autoLock(c0, wasDone0); save(); render(); return;
    }
    var bo = e.target.closest('[data-bout]');
    if (bo) { openBout(S.selected, bo.dataset.bout); return; }
    var btn = e.target.closest('button[data-m]'); if (!btn || btn.disabled) return;
    var c = cat(S.selected), pid = btn.dataset.p, mid = btn.dataset.m;
    if (swapMode) {
      if (!picked) { picked = pid; render(); return; }
      if (picked === pid) { picked = null; render(); return; }
      var pk = picked; picked = null;
      if (isElim(c.draw.system)) swapSlots(c, c.draw.slots.indexOf(pk), c.draw.slots.indexOf(pid)); else render();
      return;
    }
    snapshot();
    var wasDone = catFinished(c);
    if (c.draw.results[mid] === pid) delete c.draw.results[mid]; else c.draw.results[mid] = pid;
    prune(c); autoLock(c, wasDone); save(); render();
  });
  document.getElementById('draw-view').addEventListener('change', function (e) {
    var t = e.target, c = cat(S.selected); if (!c) return;
    if (t.dataset.score) { c.draw.scores = c.draw.scores || {}; if (t.value.trim()) c.draw.scores[t.dataset.score] = t.value.trim(); else delete c.draw.scores[t.dataset.score]; save(); if (c.draw.system === 'rr') render(); return; }
    if (t.dataset.kata) {
      var kw = t.dataset.which === 'final' ? 'final' : 'scores';
      c.draw.kata = c.draw.kata || {}; c.draw.kata[kw] = c.draw.kata[kw] || {};
      var kv = t.value.trim().slice(0, 40);
      if (kv) c.draw.kata[kw][t.dataset.kata] = kv; else delete c.draw.kata[kw][t.dataset.kata];
      save(); kataDatalist(); pushScoreLive(c, t.dataset.kata, kw);
      // Ta pati kata finale kaip pusfinalyje - pažymima vietoje (be perpiešimo, kad fokusas liktų)
      var fk = document.querySelector('.kata-in[data-kata="' + t.dataset.kata + '"][data-which="final"]');
      if (fk) { var dup = !!fk.value.trim() && fk.value.trim().toLowerCase() === kataOf(c, 'scores', t.dataset.kata).toLowerCase(); fk.classList.toggle('dup', dup); fk.title = dup ? 'Ši kata jau buvo pusfinalyje' : ''; }
      return;
    }
    if (t.dataset.sp) {
      var src = t.dataset.which === 'final' ? (c.draw.final = c.draw.final || {}) : (c.draw.scores = c.draw.scores || {});
      var arr = src[t.dataset.sp] = src[t.dataset.sp] || [], v = t.value.replace(',', '.');
      arr[+t.dataset.j] = v === '' ? null : Math.max(0, Math.min(10, parseFloat(v)));
      save(); pushScoreLive(c, t.dataset.sp, t.dataset.which);
      // Perpiešiama, kai fokusas jau persikėlė (Tab ar paspaustas kitas langelis) - jis grąžinamas į tą patį langelį
      // Planšetėje vaizdas lieka toje pačioje vietoje (nebeperšoka į viršų)
      setTimeout(function () {
        var key = focusKey(document.activeElement), y = window.scrollY;
        render();
        var el = key && document.querySelector(key);
        if (el) { focusNoScroll(el); }
        window.scrollTo(window.scrollX, y);
      }, 0);
      return;
    }
    if (t.dataset.cfg) {
      var f = cfg(c), k = t.dataset.cfg;
      snapshot();
      if (k === 'drop' || k === 'avg') f[k] = t.checked; else f[k] = Math.max(1, parseInt(t.value, 10) || 1);
      if (f.judges > 15) f.judges = 15;
      save(); render();
    }
  });
  // Balai rašomi be taško: įvedus antrą skaitmenį taškas įdedamas pats (75 → 7.5, 80 → 8.0); 10 lieka 10.
  // Daugiausia vienas skaitmuo po kablelio; įvedus pilną balą (7.7 ar 10) - iškart į kitą langelį.
  document.addEventListener('input', function (e) {
    var t = e.target;
    if (!t.classList || !t.classList.contains('j-in')) return;
    var v = String(t.value), m = v.match(/^(\d)(\d)$/);
    if (m && v !== '10') v = t.value = m[1] + '.' + m[2];
    var long = v.match(/^(\d{1,2})[.,](\d)\d+$/);
    if (long) v = t.value = long[1] + '.' + long[2];
    if (/^\d{3,}$/.test(v)) v = t.value = v.slice(0, 2) === '10' ? '10' : v[0] + '.' + v[1];
    if (/^\d[.,]\d$/.test(v) || v === '10') moveJ(t, false);
  });
  function moveJ(t, back) {
    var scope = t.closest('#draw-view') || boutEl || document;
    var sel = t.dataset.part ? '.j-in[data-part]' : '.j-in[data-sp][data-which="' + t.dataset.which + '"]';
    var list = Array.prototype.slice.call(scope.querySelectorAll(sel)), nx = list[list.indexOf(t) + (back ? -1 : 1)];
    var key = !nx ? null : nx.dataset.part ? '[data-part="' + nx.dataset.part + '"]' : '[data-sp="' + nx.dataset.sp + '"][data-which="' + nx.dataset.which + '"][data-j="' + nx.dataset.j + '"]';
    var y = window.scrollY;
    // v1.94 (#175): fokusas perkeliamas iškart, be blur() - planšetėje klaviatūra neužsidaro ir vaizdas nešokinėja
    // (pasikeitęs balas išsaugomas „change“ įvykiu, kai langelis netenka fokuso)
    if (nx) { focusNoScroll(nx); return; }
    t.blur();
    setTimeout(function () {
      var el = key && scope.querySelector(key);
      if (el) focusNoScroll(el);
      if (Math.abs(window.scrollY - y) > 40) window.scrollTo(window.scrollX, y);
    }, 40);
  }
  // Fokusas be peršokimo: jei langelis matomas - vaizdas nejuda; jei ne - tik tiek, kad jis matytųsi
  function focusNoScroll(el) {
    try { el.focus({ preventScroll: true }); } catch (ex) { el.focus(); }
    try { el.select(); } catch (ex) {}
    var r = el.getBoundingClientRect(), vh = window.visualViewport ? window.visualViewport.height : window.innerHeight;
    if (r.top < 80 || r.bottom > vh - 20) window.scrollBy(0, r.top - Math.max(100, vh / 3));
    // Į šoną - tik tiek, kad langelis matytųsi savo slenkamame rėmelyje (ne į pradžią)
    var sc = el.closest('.table-scroll, .bracket-wrap');
    if (sc && sc.scrollWidth > sc.clientWidth) {
      var er = el.getBoundingClientRect(), cr = sc.getBoundingClientRect();
      if (er.left < cr.left + 4) sc.scrollLeft -= cr.left - er.left + 30;
      else if (er.right > cr.right - 4) sc.scrollLeft += er.right - cr.right + 30;
    }
  }
  function focusKey(el) {
    if (!el || !el.dataset) return null;
    if (el.dataset.sp) return '.j-in[data-sp="' + el.dataset.sp + '"][data-which="' + el.dataset.which + '"][data-j="' + el.dataset.j + '"]';
    if (el.dataset.kata) return '.kata-in[data-kata="' + el.dataset.kata + '"][data-which="' + el.dataset.which + '"]';
    return null;
  }
  // Kata: Enter - to paties dalyvio pirmo teisėjo balas
  document.addEventListener('keydown', function (e) {
    var t = e.target;
    if (e.key !== 'Enter' || !t.classList || !t.classList.contains('kata-in')) return;
    e.preventDefault();
    var sel = '.j-in[data-sp="' + t.dataset.kata + '"][data-which="' + t.dataset.which + '"][data-j="0"]';
    t.blur();
    setTimeout(function () { var el = document.querySelector(sel); if (el) { el.focus(); try { el.select(); } catch (ex) {} } }, 40);
  });
  // Teisėjų balai: Enter - kitas teisėjas, po paskutinio - kito dalyvio pirmas teisėjas (Shift+Enter - atgal).
  // Lentelė perpiešiama pasikeitus balui, todėl kitas laukelis surandamas pagal jo žymes ir pažymimas po perpiešimo.
  document.addEventListener('keydown', function (e) {
    var t = e.target;
    if (e.key !== 'Enter' || !t.classList || !t.classList.contains('j-in')) return;
    e.preventDefault();
    moveJ(t, e.shiftKey);
  });
  // Vietų keitimas tempiant (pele ir pirštu): pirmame rate - du dalyviai apsikeičia vietomis (ar į laisvą vietą),
  // balų sistemoje - pasirodymo eilė. Kol rezultatų nėra - galima visada; po to - tik „⇄ Keisti vietas“ režime (rezultatai išvalomi).
  var pd = null, skipClick = false;
  function dropTarget(x, y, kind) {
    var el = document.elementFromPoint(x, y);
    return el && el.closest(kind === 'slot' ? '[data-drop-slot]' : '[data-drop-ord]');
  }
  function swapSlots(c, i, j) {
    var s = c.draw.slots;
    if (i === j || i < 0 || j < 0) return;
    if (Object.keys(c.draw.results || {}).length && !confirm('Jau yra kovų rezultatų. Sukeitus vietas, šios rungties rezultatai bus išvalyti. Tęsti?')) return;
    snapshot(); var x = s[i]; s[i] = s[j]; s[j] = x; c.draw.results = {}; c.draw.scores = c.draw.system === 'rr' ? c.draw.scores : {}; save(); render();
  }
  function moveOrder(c, id, beforeId) {
    var o = (c.draw.order || []).slice(), from = o.indexOf(id), to = o.indexOf(beforeId);
    if (from < 0 || to < 0 || from === to) return;
    snapshot(); o.splice(from, 1); o.splice(o.indexOf(beforeId) + (from < to ? 1 : 0), 0, id); c.draw.order = o; save(); render();
  }
  document.getElementById('draw-view').addEventListener('pointerdown', function (e) {
    if (e.button !== 0) return;
    var b = e.target.closest('[data-drag-slot], [data-drag-ord]'); if (!b) return;
    pd = { el: b, kind: b.hasAttribute('data-drag-slot') ? 'slot' : 'ord', from: b.dataset.dragSlot !== undefined ? +b.dataset.dragSlot : b.dataset.dragOrd, x: e.clientX, y: e.clientY, on: false, ghost: null };
  });
  document.addEventListener('pointermove', function (e) {
    if (!pd) return;
    if (!pd.on) {
      if (Math.abs(e.clientX - pd.x) + Math.abs(e.clientY - pd.y) < 8) return;
      pd.on = true;
      pd.ghost = document.createElement('div'); pd.ghost.className = 'drag-ghost';
      pd.ghost.textContent = (pd.el.querySelector('b') || pd.el).textContent;
      document.body.appendChild(pd.ghost); document.body.classList.add('is-dragging-name');
      (pd.el.closest('.slot, tr') || pd.el).classList.add('dragging');
    }
    e.preventDefault();
    pd.ghost.style.left = e.clientX + 'px'; pd.ghost.style.top = e.clientY + 'px';
    document.querySelectorAll('.drop').forEach(function (x) { x.classList.remove('drop'); });
    var t = dropTarget(e.clientX, e.clientY, pd.kind); if (t) t.classList.add('drop');
  }, { passive: false });
  function endDrag(e, cancel) {
    if (!pd) return;
    var d = pd; pd = null;
    if (!d.on) return;
    skipClick = true; setTimeout(function () { skipClick = false; }, 0);
    if (d.ghost) d.ghost.remove();
    document.body.classList.remove('is-dragging-name');
    document.querySelectorAll('.drop, .dragging').forEach(function (x) { x.classList.remove('drop', 'dragging'); });
    if (cancel) return;
    var t = dropTarget(e.clientX, e.clientY, d.kind), c = cat(S.selected); if (!t || !c || !c.draw) return;
    if (d.kind === 'slot') swapSlots(c, d.from, +t.dataset.dropSlot); else moveOrder(c, d.from, t.dataset.dropOrd);
  }
  document.addEventListener('pointerup', function (e) { endDrag(e, false); });
  document.addEventListener('pointercancel', function (e) { endDrag(e, true); });
  document.getElementById('draw-view').addEventListener('click', function (e) { if (skipClick) { e.stopPropagation(); e.preventDefault(); } }, true);

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
  function pubNameShort(id) { return pubName(id); }
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
  boutEl.addEventListener('toggle', function (e) { if (e.target.classList && e.target.classList.contains('bt-more')) boutMore = e.target.open; }, true);
  boutEl.setAttribute('role', 'dialog'); boutEl.setAttribute('aria-modal', 'true'); boutEl.setAttribute('aria-label', 'Kova');
  document.body.appendChild(boutEl);

  // v1.97: telefono vaizdas kovos skydelyje (vdo.ninja, srautas „kt<raktas>t<tatamis>“ - kaip admin/varzybu-transliacija.php).
  // Atskiras langas šalia kortelės, kad balų perpiešimas vaizdo nepersikrautų; įjungta / išjungta - prisimenama šiame įrenginyje.
  var vidOn = false, vidEl = null;
  try { vidOn = localStorage.getItem('vz-bout-vid') === '1'; } catch (e) {}
  var CAMS = CFG.cams && typeof CFG.cams === 'object' ? CFG.cams : {};
  // ± ant vaizdo (v1.99): tie patys mygtukai kaip kovos skydelyje (+1 / −1), todėl balai keičiasi ten pat
  function vpm(side) { return '<span class="bv-pmv"><button type="button" class="bv-pm" data-vpt="' + side + '|1" aria-label="+1">+</button><button type="button" class="bv-pm" data-vpt="' + side + '|-1" aria-label="−1">−</button></span>'; }   // viršuje +, apačioje −
  function syncVid() {
    if (!Object.keys(CAMS).length) return;
    syncLive();
    var show = !!B && vidOn && !liveShown();
    document.body.classList.toggle('bout-vid-on', show);
    if (!show) { if (vidEl) vidEl.hidden = true; return; }
    var a = areaOf(cat(B.cat)), src = CAMS[a.id] ? CAMS[a.id] + '&noaudio' : '';   // be garso - salėje nebus aido
    if (!vidEl) {
      vidEl = document.createElement('div'); vidEl.className = 'bout-vid'; vidEl.id = 'bout-vid';
      vidEl.innerHTML = '<div class="bv-head"><b></b><span class="spacer"></span><button type="button" class="btn" data-bv="big" title="Didesnis / mažesnis">⤢</button>' +
        '<button type="button" class="btn" data-bv="off" aria-label="Uždaryti vaizdą">✕</button></div><div class="bv-frame"><iframe title="Telefono vaizdas" allow="autoplay; fullscreen" allowfullscreen></iframe>' +
        '<div class="bv-score"></div></div>' +
        '<p class="bv-hint">Juodas langas - šiam tatamiui nepriskirtas telefonas: <a target="_blank" rel="noopener"></a></p>';
      document.body.appendChild(vidEl);
      vidEl.addEventListener('click', function (e) {
        var p = e.target.closest('[data-vpt]');
        if (p) { var t = boutEl.querySelector('[data-pt="' + p.dataset.vpt + '"]'); if (t) t.click(); return; }
        var b = e.target.closest('[data-bv]'); if (!b) return;
        if (b.dataset.bv === 'big') document.body.classList.toggle('bout-vid-big');
        else setVid(false);
      });
    }
    vidEl.querySelector('.bv-head b').textContent = '📹 ' + a.name;
    var link = vidEl.querySelector('.bv-hint a');
    link.textContent = '🎥 Prijungti telefoną'; link.href = CFG.streamUrl;
    var fr = vidEl.querySelector('iframe');
    if ((fr.getAttribute('src') || '') !== src) { if (src) fr.setAttribute('src', src); else fr.removeAttribute('src'); }
    // Balai ant vaizdo: AKA [+/−] balai | balai [+/−] SHIRO (tik ten, kur kovos skydelyje yra +1 / −1)
    var sc = vidEl.querySelector('.bv-score'), can = !!boutEl.querySelector('[data-pt="a|1"]') && !!boutEl.querySelector('[data-pt="a|-1"]');
    sc.hidden = !can;
    if (can) {
      var h = '<span class="bv-n aka">' + esc(pubName(B.a)) + '</span>' + vpm('a') + '<b class="bv-s aka">' + esc(B.sa) + '</b>' +
        '<b class="bv-s shiro">' + esc(B.sb) + '</b>' + vpm('b') + '<span class="bv-n shiro">' + esc(pubName(B.b)) + '</span>';
      if (sc.dataset.h !== h) { sc.dataset.h = h; sc.innerHTML = h; }
    }
    vidEl.hidden = false;
  }
  function setVid(on) {
    vidOn = on;
    try { localStorage.setItem('vz-bout-vid', on ? '1' : '0'); } catch (e) {}
    if (!on && vidEl) vidEl.querySelector('iframe').removeAttribute('src');   // išjungus - telefono internetas nebenaudojamas
    if (B) renderBout(); else syncVid();
  }

  // ---------- 📹 Live kova (v2.00): sekretoriato langas - tatamio telefono vaizdas per visą ekraną; kova, balai [+/−], laikas ir „Baigti“ ant vaizdo ----------
  // Atidaroma Tvarkaraštyje („📹 Live kova“) arba kovos skydelyje („📹 Live“). Mygtukai ant vaizdo spaudžia tuos pačius kovos skydelio mygtukus,
  // todėl balai, laikas ir rezultatas - tie patys. Kai kovos nėra - rodoma kita šio tatamio kova ir „▶ Pradėti kovą“.
  var liveArea = null, liveEl = null;
  try { liveArea = localStorage.getItem(KEY + '-live') || null; } catch (e) {}
  function liveShown() { var a = liveArea && areaById(liveArea); return !!(a && CAMS[a.id]); }
  function setLive(aid) {
    liveArea = aid ? String(aid) : null;
    try { if (liveArea) localStorage.setItem(KEY + '-live', liveArea); else localStorage.removeItem(KEY + '-live'); } catch (e) {}
    syncVid(); syncLive();
  }
  function liveNext(a) {
    var it = areaPlan(a).items.find(function (x) { return x.st === 'now'; }); if (!it) return null;
    var m = upcoming(it.c).find(function (x) { return x.id && x.a && x.b; });
    return { c: it.c, m: m || null };
  }
  function lvpm(side) { return '<span class="lv-pmv"><button type="button" class="lv-pm" data-vpt="' + side + '|1" aria-label="+1">+</button><button type="button" class="lv-pm" data-vpt="' + side + '|-1" aria-label="−1">−</button></span>'; }
  function syncLive() {
    var a = liveArea && areaById(liveArea), show = !!(a && CAMS[a.id]);
    document.body.classList.toggle('live-on', show);
    if (!show) { if (liveEl) { liveEl.hidden = true; liveEl.querySelector('iframe').removeAttribute('src'); } return; }
    if (!liveEl) {
      liveEl = document.createElement('div'); liveEl.className = 'live-win'; liveEl.id = 'live-win';
      liveEl.setAttribute('role', 'dialog'); liveEl.setAttribute('aria-label', 'Live kova');
      liveEl.innerHTML = '<div class="lv-head"><b class="lv-title"></b><span class="lv-areas"></span><span class="spacer"></span>' +
        '<button type="button" class="btn" data-lv="close">📋 Tvarkaraštis</button></div>' +
        '<div class="lv-stage"><iframe title="Telefono vaizdas" allow="autoplay; fullscreen" allowfullscreen></iframe><div class="lv-bar"></div></div>' +
        '<div class="lv-foot"></div>';
      document.body.appendChild(liveEl);
      liveEl.addEventListener('click', function (e) {
        var p = e.target.closest('[data-vpt]');
        if (p) { var t = boutEl.querySelector('[data-pt="' + p.dataset.vpt + '"]'); if (t) t.click(); return; }
        var b = e.target.closest('[data-lv]'); if (!b) return;
        var act = b.dataset.lv;
        if (act === 'close') { setLive(null); return; }
        if (act === 'area') { setLive(b.dataset.area); return; }
        if (act === 'open') { var q = b.dataset.open.split('|'); openBout(q[0], q[1]); return; }
        if (act === 'run') { setLive(null); goRun(b.dataset.cat); return; }
        if (act === 'panel') { setLive(null); return; }
        var map = { start: '#bt-start', finish: '#bt-finish', wina: '[data-win="a"]', winb: '[data-win="b"]' }, t2 = map[act] && boutEl.querySelector(map[act]);
        if (t2) t2.click();
      });
    }
    liveEl.querySelector('.lv-title').textContent = '📹 ' + a.name + ' · Live kova';
    var ar = S.areas.filter(function (x) { return CAMS[x.id]; });
    var areasHtml = ar.length > 1 ? ar.map(function (x) { return '<button type="button" class="seg-btn' + (String(x.id) === String(a.id) ? ' on' : '') + '" data-lv="area" data-area="' + x.id + '">' + esc(x.name) + '</button>'; }).join('') : '';
    var ah = liveEl.querySelector('.lv-areas'); if (ah.dataset.h !== areasHtml) { ah.dataset.h = areasHtml; ah.innerHTML = areasHtml; }
    var fr = liveEl.querySelector('iframe'), src = CAMS[a.id] + '&noaudio';
    if ((fr.getAttribute('src') || '') !== src) fr.setAttribute('src', src);
    var inBout = !!B && String(areaOf(cat(B.cat)).id) === String(a.id), bar = '', foot = '';
    if (inBout) {
      var c = cat(B.cat), can = !!boutEl.querySelector('[data-pt="a|1"]') && !!boutEl.querySelector('[data-pt="a|-1"]');
      bar = '<div class="lv-cat">' + esc(c.name) + ' · ' + esc(B.title) + '</div><div class="lv-row">' +
        '<span class="lv-n aka">' + esc(pubName(B.a)) + '</span>' + (can ? lvpm('a') : '') + '<b class="lv-s aka">' + esc(B.sa) + '</b>' +
        '<span class="lv-t" data-lv-timer></span>' +
        '<b class="lv-s shiro">' + esc(B.sb) + '</b>' + (can ? lvpm('b') : '') + '<span class="lv-n shiro">' + esc(pubName(B.b)) + '</span></div>';
      foot = '<button type="button" class="btn btn-primary lv-big" data-lv="start" data-lv-start></button>' +
        (boutEl.querySelector('#bt-finish') ? '<button type="button" class="btn lv-big" data-lv="finish">✓ Baigti kovą</button>' : '') +
        (boutEl.querySelector('[data-win="a"]') ? '<button type="button" class="btn" data-lv="wina">Laimėjo AKA</button><button type="button" class="btn" data-lv="winb">Laimėjo SHIRO</button>' : '') +
        '<span class="spacer"></span><button type="button" class="btn" data-lv="panel" title="Kovos skydelis: laikas, šablonai, įspėjimai">📋 Kovos skydelis</button>';
    } else {
      var nx = liveNext(a);
      bar = '<div class="lv-cat">' + esc(a.name) + (nx ? ' · ' + esc(nx.c.name) : '') + '</div>' +
        (nx && nx.m ? '<div class="lv-row lv-next"><span class="lv-n aka">' + esc(pubName(nx.m.a)) + '</span><span class="lv-vs">vs</span><span class="lv-n shiro">' + esc(pubName(nx.m.b)) + '</span></div>'
          : '<div class="lv-row lv-next"><span class="lv-n">' + (nx ? 'Kitą kovą pradėkite grupės lange.' : 'Šio tatamio grupės baigtos.') + '</span></div>');
      foot = nx && nx.m ? '<button type="button" class="btn btn-primary lv-big" data-lv="open" data-open="' + esc(nx.c.id) + '|' + esc(nx.m.id) + '">▶ Pradėti kovą</button>'
        : nx ? '<button type="button" class="btn lv-big" data-lv="run" data-cat="' + esc(nx.c.id) + '">Atidaryti grupę</button>' : '';
    }
    var bl = liveEl.querySelector('.lv-bar'), fl = liveEl.querySelector('.lv-foot');
    if (bl.dataset.h !== bar) { bl.dataset.h = bar; bl.innerHTML = bar; }
    if (fl.dataset.h !== foot) { fl.dataset.h = foot; fl.innerHTML = foot; }
    liveTick();
    liveEl.hidden = false;
  }
  // Laikas ir ▶/⏸ - kaip kovos skydelyje (perrašoma kas tiksėjimą)
  function liveTick() {
    if (!liveEl || liveEl.hidden) return;
    var lt = liveEl.querySelector('[data-lv-timer]'), bt = boutEl.querySelector('#bt-timer');
    if (lt && bt) { lt.textContent = bt.textContent; lt.className = 'lv-t ' + bt.className.replace('bt-timer', ''); }
    var ls = liveEl.querySelector('[data-lv-start]'), bs = boutEl.querySelector('#bt-start');
    if (ls && bs) ls.textContent = bs.textContent;
  }

  function openBout(catId, mid) {

    var c = cat(catId), m = findMatch(c, mid); if (!m || !m.a || !m.b) return;
    var mode = boutMode(c, m), f = cfg(c), saved = (c.draw.bouts || {})[mid] || {};
    SL = null; slKey = '';
    B = { cat: c.id, mid: mid, mode: mode, a: m.a, b: m.b, title: matchTitle(c, m), no: m.no || '',
      sa: saved.sa || 0, sb: saved.sb || 0, log: saved.log || [], i: saved.i || 0,
      ma: saved.ma || teamMembers(m.a), mb: saved.mb || teamMembers(m.b),
      parts: saved.parts || { a: { k: [], p: [] }, b: { k: [], p: [] } },
      timer: null, winner: null };
    var kum = mode === 'kumite' || mode === 'team', t0 = saved.tTotal !== undefined ? saved.tTotal : kum ? 10000 : f.time * 1000;
    B.timer = { total: t0, remaining: saved.remaining !== undefined ? saved.remaining : t0, running: false, at: Date.now(), up: !!saved.tUp, preset: saved.tPreset || (kum ? 'Jiyu ippon' : '') };
    liveMatch = { cat: c.id, mid: mid };
    boutEl.hidden = false; document.body.classList.add('bout-open-body');
    renderBout(); pushLive(); render();
    if (!POPUP) { try { history.pushState({ vz: 1, tab: 'live', run: S.selected, bout: mid }, ''); boutHist = true; } catch (e) {} }
  }
  var boutHist = false;
  function keepBout() {
    // Nebaigtos kovos balai išsaugomi (perkrovus puslapį - tęsiama)
    var c = cat(B.cat); c.draw.bouts = c.draw.bouts || {};
    c.draw.bouts[B.mid] = { sa: B.sa, sb: B.sb, log: B.log, i: B.i, ma: B.ma, mb: B.mb, parts: B.parts, remaining: timeLeft(), tTotal: B.timer.total, tUp: B.timer.up || undefined, tPreset: B.timer.preset || undefined };
    save();
  }
  function closeBout() {
    if (!B) return;
    if (B.timer.running) toggleTimer();
    var area = areaOf(cat(B.cat));
    B = null; liveMatch = null; boutEl.hidden = true; document.body.classList.remove('bout-open-body'); syncVid();
    pushLive(area); render();
    if (boutHist && history.state && history.state.bout) { boutHist = false; history.back(); }
    boutHist = false;
  }
  // Enbu (t.up): laikas skaičiuojamas nuo 0 į priekį; „remaining“ tada - jau praėjęs laikas
  function timeLeft() { var t = B.timer; if (t.up) return Math.min(30 * 60000, t.running ? t.remaining + (Date.now() - t.at) : t.remaining); return Math.max(0, t.running ? t.remaining - (Date.now() - t.at) : t.remaining); }
  // Atgalinis laikas apvalinamas į viršų (0:01 rodoma iki pat galo), laikmatis nuo 0 (Enbu) - į apačią, kad prasidėtų nuo 0:00 (#134)
  function fmtTime(ms, up) { var s = up ? Math.floor(ms / 1000) : Math.ceil(ms / 1000), m = Math.floor(s / 60); s = s % 60; return m + ':' + (s < 10 ? '0' : '') + s; }
  var tickTimer = null, beeped = false;
  function toggleTimer() {
    var t = B.timer;
    if (t.running) { t.remaining = timeLeft(); t.running = false; clearInterval(tickTimer); }
    else { if (!t.up && timeLeft() <= 0) return; audio(); t.at = Date.now(); t.running = true; beeped = false; warned30 = timeLeft() <= 30000; tickTimer = setInterval(tick, 200); }
    t.at = Date.now(); renderTimer(); pushLive();
  }
  function setTime(ms) { var t = B.timer; t.remaining = Math.max(0, Math.min(ms, 30 * 60000)); t.at = Date.now(); if (t.running) { clearInterval(tickTimer); tickTimer = setInterval(tick, 200); } renderTimer(); pushLive(); }
  function tick() {
    if (!B) { clearInterval(tickTimer); return; }
    if (B.timer.running && !B.timer.up && isJiyu() && !warned30 && timeLeft() <= 30000 && timeLeft() > 0) { warned30 = true; beep(); }
    if (!B.timer.up && timeLeft() <= 0 && B.timer.running) {
      B.timer.remaining = 0; B.timer.running = false; clearInterval(tickTimer);
      if (!beeped) { beep(); beeped = true; if (isJiyu()) setTimeout(function () { beep(); }, 380); }   // #152: dvigubas gongas - antras iškart po pirmo
      pushLive();
      // Laikas baigėsi pats - po 3 s laikmatis atsistato iš naujo (kitai kovai / pratęsimui), jei niekas jo nepalietė
      var bt = B;
      setTimeout(function () { if (B === bt && !B.timer.running && !B.timer.up && timeLeft() <= 0) setTime(B.timer.total); }, 3000);
    }
    renderTimer();
  }
  // Gongas, kai baigiasi laikas: keli nedarnūs dažniai su ilgu nuslopimu + kompresorius, kad būtų garsu.
  var actx = null;
  function audio() {
    try { if (!actx) actx = new (window.AudioContext || window.webkitAudioContext)(); if (actx.state === 'suspended') actx.resume(); } catch (e) { actx = null; }
    return actx;
  }
  // #151: gongo garsai - pasirenkama kovos skydelyje (šiam įrenginiui)
  var GONGS = { deep: 'Gilus gongas', bright: 'Šviesus gongas', bell: 'Varpas' };
  function gongKind() { try { var k = localStorage.getItem('budo-gong'); return GONGS[k] ? k : 'deep'; } catch (e) { return 'deep'; } }
  function beep(kind) {
    kind = kind || gongKind();
    if (kind !== 'deep') { gongTone(kind); return; }
    var a = audio(); if (!a) return;
    try {
      var t = a.currentTime + 0.02, comp = a.createDynamicsCompressor(), out = a.createGain();
      comp.threshold.value = -20; comp.ratio.value = 6; out.gain.value = 1.6;
      comp.connect(out); out.connect(a.destination);
      [[98, 1], [147, 0.7], [196, 0.55], [271, 0.45], [383, 0.3], [502, 0.22], [661, 0.14]].forEach(function (pt, i) {
        var o = a.createOscillator(), g = a.createGain(), dur = 4.2 - i * 0.35;
        o.type = i ? 'sine' : 'triangle'; o.frequency.setValueAtTime(pt[0] * 1.012, t); o.frequency.exponentialRampToValueAtTime(pt[0], t + 0.4);
        g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(pt[1], t + 0.015); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
        o.connect(g); g.connect(comp); o.start(t); o.stop(t + dur + 0.05);
      });
      var n = a.createBufferSource(), buf = a.createBuffer(1, a.sampleRate * 0.12, a.sampleRate), d = buf.getChannelData(0);
      for (var i = 0; i < d.length; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / d.length, 3);
      var ng = a.createGain(); ng.gain.value = 0.5; n.buffer = buf; n.connect(ng); ng.connect(comp); n.start(t);
    } catch (e) {}
  }
  // Šviesus gongas ir varpas: kiti dažniai ir nuslopimas (be failų - sugeneruojama naršyklėje)
  function gongTone(kind) {
    var a = audio(); if (!a) return;
    try {
      var t = a.currentTime + 0.02, comp = a.createDynamicsCompressor(), out = a.createGain();
      comp.threshold.value = -20; comp.ratio.value = 6; out.gain.value = 1.5; comp.connect(out); out.connect(a.destination);
      var parts = kind === 'bell' ? [[523, 1, 2.6], [1046, .5, 2.0], [1318, .35, 1.6], [1568, .25, 1.3], [2093, .18, 1.0], [2637, .1, .8]]
        : [[196, 1, 3.4], [294, .7, 3.0], [392, .6, 2.6], [587, .45, 2.2], [784, .3, 1.8], [1175, .18, 1.2]];
      parts.forEach(function (pt, i) {
        var o = a.createOscillator(), g = a.createGain();
        o.type = 'sine'; o.frequency.setValueAtTime(pt[0] * (kind === 'bell' ? 1 : 1.01), t); o.frequency.exponentialRampToValueAtTime(pt[0], t + 0.3);
        g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(pt[1], t + 0.01); g.gain.exponentialRampToValueAtTime(0.0001, t + pt[2]);
        o.connect(g); g.connect(comp); o.start(t); o.stop(t + pt[2] + 0.05);
      });
    } catch (e) {}
  }
  // „Jiyu / Pratęsimas“: likus 30 s - vienas gongas, laikui baigiantis - du (#151)
  function isJiyu() { return B && B.timer && /Jiyu \/ Pratęsimas/.test(B.timer.preset || ''); }
  var warned30 = false;
  function renderTimer() {
    var el = document.getElementById('bt-timer'); if (!el || !B || el.tagName === 'INPUT') return;
    var left = timeLeft();
    el.textContent = fmtTime(left, B.timer.up);
    el.className = 'bt-timer' + (B.timer.up ? ' up' : left <= 0 ? ' over' : left <= 15000 ? ' last' : '') + (B.timer.running ? ' running' : '');
    var tb = document.getElementById('bt-start'); if (tb) tb.textContent = B.timer.running ? '⏸ Sustabdyti' : '▶ Pradėti';
    liveTick();
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
    else html += '<div class="bt-btns">' + [1, 2, 4].map(function (n) { return '<button type="button" class="btn bt-big" data-pt="' + side + '|' + n + '">+' + n + '</button>'; }).join('') + '<button type="button" class="btn" data-pt="' + side + '|-1">−1</button></div>';
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
  // Kumite laiko šablonai: Jiyu ippon ir Ko-Go - 10 s (pradinis), Jiyu / Pratęsimas - 1:30, Enbu - paprastas laikmatis nuo 0 (skaičiuoja į priekį)
  var TIME_PRESETS = [['Jiyu ippon', 10], ['Ko-Go', 10], ['Jiyu / Pratęsimas', 90], ['Enbu', 0, 1]];
  function timePresets() { return TIME_PRESETS; }
  var TOUCH = window.matchMedia && matchMedia('(pointer: coarse)').matches;
  // B3: „⏱ laikas ir gongas“ - kompiuteryje atidaryta, telefone suskleista; atsimenama, kol kova atidaryta (perpiešiant)
  var boutMore = null;
  function boutMoreOpen() { return boutMore !== null ? boutMore : window.innerWidth > 600; }
  // #126: kovos skydelyje matosi, kas ruošiasi (kita pora šioje grupėje arba kita grupė)
  function boutNextHtml(c) {
    var nx = upcoming(c).filter(function (x) { return x.id !== B.mid; })[0];
    var nm = function (id) { return esc((person(c, id) || {}).name || ''); };
    if (nx && nx.a && nx.b) return '<div class="sv-prep bt-next"><b>Ruošiasi:</b> <span class="sv-dot a"></span>' + nm(nx.a) + ' <span class="muted">vs</span> <span class="sv-dot b"></span>' + nm(nx.b) + '</div>';
    var later = prepLater(c, { id: B.mid });
    return later ? later.replace('class="sv-prep"', 'class="sv-prep bt-next"') : '';
  }
  function renderBout() {
    if (!B) return;
    var c = cat(B.cat), area = areaOf(c), body, mode = B.mode, f = cfg(c);
    var head = '<div class="bout-top"><div class="bout-info"><span class="disc d-' + c.discipline + '"></span><b>' + esc(c.name) + '</b><span>' + (B.no ? B.no + ' · ' : '') + esc(B.title) + '</span><span class="pill sys">' + MODE_NAME[mode] + '</span><span class="pill">' + esc(area.name) + '</span></div>' +
      '<span class="spacer"></span>' + (CAMS[area.id] ? '<button type="button" class="btn" id="bt-live" title="Live kova: telefono vaizdas per visą ekraną, balai ant vaizdo">📹<span class="bt-lbl"> Live</span></button>' : '') +
      (Object.keys(CAMS).length ? '<button type="button" class="btn' + (vidOn ? ' on' : '') + '" id="bt-vid" aria-pressed="' + vidOn + '" title="Telefono vaizdas šalia balų (🎥 Transliacija)">📹<span class="bt-lbl"> Vaizdas</span></button>' : '') +
      (CFG.screenUrl ? '<button type="button" class="btn" id="bt-screen" title="Atidaryti ekrano langą šiam tatamiui (antram monitoriui ar televizoriui)" aria-label="Ekranas">📺<span class="bt-lbl"> Ekranas</span></button>' : '') +
      '<button type="button" class="btn" id="bt-close" aria-label="Uždaryti">✕<span class="bt-lbl"> Uždaryti</span></button></div>';
    if (mode === 'score2') {
      body = partsTable();
    } else {
      var mid = '<div class="bt-mid">' + (mode === 'team' ? '<div class="bt-round">Kova ' + (B.i + 1) + ' / ' + Math.max(B.ma.length, B.mb.length, 1) + '</div>' : '') +
        '<button type="button" class="bt-timer" id="bt-timer" title="Paspauskite ir įrašykite laiką (pvz. 1:30)"></button><div class="bt-tbtns"><button type="button" class="btn btn-primary" id="bt-start"></button><button type="button" class="btn" id="bt-reset" title="Iš naujo">↺ ' + fmtTime(B.timer.total) + '</button></div>' +
        // B3: ±10 s, laiko šablonai ir gongas - viename skyriuje; telefone suskleistas (pora ir balai lieka ekrane)
        '<details class="bt-more"' + (boutMoreOpen() ? ' open' : '') + '><summary>⏱ ' + (B.timer.preset ? esc(B.timer.preset) + ' · ' : '') + 'laikas ir gongas</summary>' +
        '<div class="bt-tbtns"><button type="button" class="btn" data-dt="-10000">−10 s</button><button type="button" class="btn" data-dt="10000">+10 s</button></div>' +
        (mode === 'kumite' || mode === 'team' ? '<div class="bt-presets" role="group" aria-label="Laiko šablonai">' + timePresets().map(function (x) {
          var on = B.timer.preset === x[0];
          return '<button type="button" class="btn btn-sm' + (on ? ' on' : '') + '" data-preset="' + x[1] + '" data-preset-name="' + esc(x[0]) + '"' + (x[2] ? ' data-up="1" title="Paprastas laikmatis nuo 0"' : '') + '>' + esc(x[0]) + ' <b>' + fmtTime(x[1] * 1000) + (x[2] ? ' ↑' : '') + '</b></button>';
        }).join('') + '</div>' : '') +
        '<label class="bt-gong small">🔔 <select id="bt-gong" aria-label="Gongo garsas">' + Object.keys(GONGS).map(function (k) { return '<option value="' + k + '"' + (k === gongKind() ? ' selected' : '') + '>' + GONGS[k] + '</option>'; }).join('') + '</select>' +
        (isJiyu() ? ' <span class="muted">likus 30 s - 1 gongas, pabaigoje - 2</span>' : '') + '</label></details>' +
        (mode === 'team' && B.log.length ? '<ol class="bt-log">' + B.log.map(function (l, j) { return '<li>' + (j + 1) + ' kova: ' + esc(l.an) + ' <b>' + l.a + ':' + l.b + '</b> ' + esc(l.bn) + '</li>'; }).join('') + '</ol>' : '') +
        (TOUCH ? '' : '<p class="muted small">Tarpas - laikrodis</p>') + '</div>';
      body = '<div class="bt-sides">' + sideHtml('a') + mid + sideHtml('b') + '</div>';
    }
    var tie = mode === 'score2' ? (function () { var a = judgeTotal(c, B.parts.a.k), b = judgeTotal(c, B.parts.a.p), x = judgeTotal(c, B.parts.b.k), y = judgeTotal(c, B.parts.b.p); return a !== null && b !== null && x !== null && y !== null && a + b === x + y; })() : B.sa === B.sb;
    var lastTeamBout = mode !== 'team' || B.i >= Math.max(B.ma.length, B.mb.length, 1) - 1;
    var foot = '<div class="bt-foot">' + (mode === 'team' && !lastTeamBout ? '<button type="button" class="btn btn-primary" id="bt-next">Kita kova →</button>' : '') +
      (lastTeamBout ? '<button type="button" class="btn btn-primary" id="bt-finish">✓ Baigti kovą</button>' : '') +
      '<span class="spacer"></span><span class="muted small">' + (tie ? 'Lygiosios - nugalėtoją pasirinkite:' : 'Arba sprendimu (hantei):') + '</span><button type="button" class="btn" data-win="a">Laimėjo AKA</button><button type="button" class="btn" data-win="b">Laimėjo SHIRO</button></div>';
    // Telefone: perpiešus (pvz. +1 taškas) lieka toje pačioje slinkimo vietoje
    var oldCard = boutEl.querySelector('.bout-card'), keepY = oldCard ? oldCard.scrollTop : 0, keepX = oldCard ? oldCard.scrollLeft : 0, keepWin = window.scrollY;
    var oldTs = oldCard && oldCard.querySelector('.table-scroll'), keepTs = oldTs ? oldTs.scrollLeft : 0;
    boutEl.innerHTML = '<div class="bout-card">' + head + boutNextHtml(c) + '<div class="bout-body">' + body + '</div>' + foot + '</div>';
    renderTimer();
    var nc = boutEl.querySelector('.bout-card');
    if (keepY) nc.scrollTop = keepY;
    if (keepX) nc.scrollLeft = keepX;
    if (keepTs && nc.querySelector('.table-scroll')) nc.querySelector('.table-scroll').scrollLeft = keepTs;
    if (window.scrollY !== keepWin) window.scrollTo(window.scrollX, keepWin);
    syncVid();
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
    var wasDone = catFinished(c);
    c.draw.results[B.mid] = winner === 'a' ? B.a : B.b;
    c.draw.scores[B.mid] = txt;
    B.winner = winner;
    if (B.timer.running) toggleTimer();
    pushLive();
    prune(c); autoLock(c, wasDone); save();
    var area = areaOf(c);
    setTimeout(function () { pushLive(area); }, 8000);   // ekrane nugalėtojas rodomas 8 s
    B = null; liveMatch = null; boutEl.hidden = true; document.body.classList.remove('bout-open-body'); syncVid(); render();
  }
  boutEl.addEventListener('click', function (e) {
    if (!B) return;
    var t = e.target.closest('button'); if (!t) return;
    var c = cat(B.cat);
    if (t.id === 'bt-close') { keepBout(); closeBout(); return; }
    if (t.id === 'bt-screen') { window.open(CFG.screenUrl + '&tatami=' + areaOf(c).id, 'taika-ekranas-' + areaOf(c).id); return; }
    if (t.id === 'bt-vid') { setVid(!vidOn); return; }
    if (t.id === 'bt-live') { setLive(areaOf(c).id); return; }
    if (t.id === 'bt-start') { toggleTimer(); return; }
    if (t.id === 'bt-timer') { editTimer(); return; }
    if (t.id === 'bt-reset') { if (B.timer.running) toggleTimer(); setTime(B.timer.total); return; }
    if (t.dataset.dt) { setTime(timeLeft() + +t.dataset.dt); return; }
    if (t.dataset.preset !== undefined) {
      if (B.timer.running) toggleTimer();
      B.timer.total = +t.dataset.preset * 1000; B.timer.preset = t.dataset.presetName; B.timer.up = !!t.dataset.up; setTime(B.timer.total); keepBout(); renderBout(); return;
    }
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
    inp.className = 'bt-timer bt-timer-in'; inp.value = fmtTime(timeLeft(), B.timer.up); inp.setAttribute('aria-label', 'Laikas (min:sek)');
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
  // Balų sistema (kata balais) ekrane (#103): dabartinis dalyvis, kata, teisėjų balai (atmesti - perbraukti), rezultatas ir geriausių lentelė
  var SL = null, slKey = '';
  function scoreLive(c, id, which) {
    var w = which === 'final' ? 'final' : 'scores', f = cfg(c), src = (w === 'final' ? c.draw.final : c.draw.scores) || {}, arr = src[id] || [], t = scoreTotal(c, arr);
    var ids = w === 'final' ? scoreFinalists(c) : (c.draw.order || []).filter(function (x) { return person(c, x); });
    var board = scoreRank(c, w, ids).filter(function (x) { return x.sum !== null; }).slice(0, 6).map(function (x, i) { var p = person(c, x.id) || {}; return { n: pubName(x.id), c: p.club || '', s: fmtScore(c, x.sum), pl: i + 1 }; });
    var p = person(c, id) || {}, a = areaOf(c), sc = [];
    for (var j = 0; j < f.judges; j++) sc.push(arr[j] !== undefined && arr[j] !== null ? arr[j] : null);
    return { area: a.id, areaName: a.name, cat: c.name, catId: c.id, disc: c.discipline, round: w === 'final' ? 'Finalas' : (f.rounds === 2 ? 'Pusfinalis' : 'Balai'), mode: 'score',
      aka: { name: pubName(id), club: p.club || '' }, shiro: { name: '', club: '' }, sa: fmtScore(c, t), sb: '', at: Date.now(),
      perf: { kata: kataOf(c, w, id), scores: sc, dropped: droppedIdx(c, arr), total: t === null ? null : fmtScore(c, t), label: f.avg ? 'Vidurkis' : 'Suma' }, board: board };
  }
  function pushScoreLive(c, id, which) {
    if (B || !c || !c.draw || !id || CFG.readOnly) return;
    var st = scoreLive(c, id, which), k = JSON.stringify([st.catId, st.aka.name, st.perf, st.board]);
    if (k === slKey) return;
    slKey = k; SL = st; pushLive(areaOf(c));
  }
  function liveState(area) {
    if (!B && SL && SL.area === area.id) { SL.at = Date.now(); return SL; }
    if (!B) return { area: area.id, areaName: area.name, idle: true, at: Date.now() };
    var c = cat(B.cat), st = { area: areaOf(c).id, areaName: areaOf(c).name, cat: c.name, catId: c.id, disc: c.discipline, round: (B.no ? B.no + ' · ' : '') + B.title, mode: B.mode,
      aka: { name: pubName(B.a), club: (person(c, B.a) || {}).club || '' }, shiro: { name: pubName(B.b), club: (person(c, B.b) || {}).club || '' },
      sa: B.sa, sb: B.sb, winner: B.winner, at: Date.now(),
      timer: { total: B.timer.total, remaining: timeLeft(), running: B.timer.running, up: B.timer.up || undefined } };
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
  // Varžybų diena: ar šiandien (tada rodoma, ar tatamis vėluoja)
  function pad2(n) { return (n < 10 ? '0' : '') + n; }
  function liveDay() { var d = new Date(), s = d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); return !!CFG.date && s >= CFG.date && s <= (CFG.dateEnd || CFG.date); }
  function nowMin() { var d = new Date(); return d.getHours() * 60 + d.getMinutes() + d.getSeconds() / 60; }
  // Tatamio planas: rungtys su laikais ir būsena (done - baigta, now - vyksta dabar, next - toliau, later); vėlavimas min. (tik varžybų dieną)
  // ---------- Grupių būsenos ir apdovanojimai (db/033) ----------
  // Laukiama → Vyksta → Užbaigta → „Siųsti apdovanojimams“ → Užbaigta · apdovanojama → (planšetėje „Užbaigti“) → Užbaigta ir apdovanota.
  // Amžiaus grupė (tie patys gimimo metai: ir kata, ir kumite) apdovanojimų planšetėje žalia, kai visos jos rungtys išsiųstos.
  var AW = CFG.awards && typeof CFG.awards === 'object' && !Array.isArray(CFG.awards) ? CFG.awards : {}, awPending = false;
  var ST_LABEL = { wait: 'Laukiama', run: 'Vyksta', fin: 'Užbaigta', sent: 'Užbaigta · apdovanojama', awarded: 'Užbaigta ir apdovanota' };
  // #182 (v1.99): rungtis užsirakina pati, kai baigiasi paskutinė kova; pataisyti - paspausti „🔒 Užrakinta“ (atsirakina)
  function autoLock(c, wasDone) { if (c && c.draw && !wasDone && catFinished(c)) c.draw.locked = true; }
  function catFinished(c) {
    if (!c.draw || drawStale(c)) return false;
    var total = matchCount(c), pl = catPlaces(c);
    return total > 0 ? doneCount(c) >= total && pl.some(function (x) { return x[0] === 1; }) : pl.length > 0;
  }
  function catStatus(c, isNow) {
    var aw = AW[c.id] && AW[c.id].st;
    if (aw === 'done') return 'awarded';
    if (aw === 'sent') return 'sent';
    if (catFinished(c)) return 'fin';
    return isNow || (c.draw && !drawStale(c) && doneCount(c) > 0) ? 'run' : 'wait';
  }
  function stChip(st) { return '<span class="aw-chip aw-' + st + '">' + (st === 'sent' ? '🏅 ' : st === 'awarded' ? '✓ ' : '') + ST_LABEL[st] + '</span>'; }
  function ageKey(c) { var k = crit(c); return k.y0 || k.y1 ? 'y' + (k.y0 || '') + '-' + (k.y1 || '') : 'c' + c.id; }
  function ageName(c) {
    var k = crit(c); if (!k.y0 && !k.y1) return c.name;
    return k.y0 && k.y1 ? (k.y1 === k.y0 ? k.y0 + ' m.' : k.y1 + '–' + k.y0 + ' m.') : k.y1 ? k.y1 + ' m. ir vyr.' : k.y0 + ' m. ir jaun.';
  }
  function ageGroup(c) { var key = ageKey(c); return S.categories.filter(function (x) { return x.competitors.length && ageKey(x) === key; }); }
  function setAward(ids, st) {
    ids.forEach(function (id) { if (st) AW[id] = { st: st }; else delete AW[id]; });
    render();
    if (!CFG.awardsApi) { toast('Apdovanojimai veiks, kai bus atnaujinta duomenų bazė (Šiandien → „Atnaujinkite duomenų bazę“).'); return; }
    fetch(CFG.awardsApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf }, body: JSON.stringify({ cats: ids, status: st || '' }) })
      .then(function (r) { return r.json(); }).then(function (d) { if (d.awards) { AW = d.awards; render(); } })
      .catch(function () { toast('Nepavyko išsiųsti apdovanojimams - nėra ryšio. Bandykite dar kartą.'); });
  }
  function typingNow() { var el = document.activeElement; return !!(el && /INPUT|SELECT|TEXTAREA/.test(el.tagName) && document.querySelector('main').contains(el)) || !!B; }
  // Apdovanojimų būsenos iš serverio (planšetėje paspaudus „Užbaigti“) - kas 10 s
  function loadAwards() {
    if (!CFG.awardsApi || document.hidden) return;
    fetch(CFG.awardsApi, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.awards) return;
      if (JSON.stringify(d.awards) !== JSON.stringify(AW)) { AW = d.awards; awPending = true; }
      if (awPending && !typingNow() && !dragId && (!document.getElementById('v-live').hidden || !document.getElementById('v-rezultatai').hidden)) { awPending = false; render(); }
    }).catch(function () {});
  }
  setInterval(loadAwards, 10000);

  // ---------- Mano tatamis: šiame kompiuteryje (planšetėje) rodomas tik pasirinktas tatamis; kitą pažiūrėti - vienu paspaudimu ----------
  var myArea = null;
  try { myArea = localStorage.getItem(KEY + '-myarea'); } catch (e) {}
  function areaById(id) { return S.areas.find(function (a) { return String(a.id) === String(id); }) || null; }
  function shownAreas() { var a = areaById(myArea); return a ? [a] : S.areas; }
  function setMyArea(v) { myArea = v; try { localStorage.setItem(KEY + '-myarea', v); } catch (e) {} render(); }
  function renderAreaPick() {
    var seg = document.getElementById('area-seg'), box = document.getElementById('area-pick'), cur = areaById(myArea) ? String(myArea) : 'all';
    var opts = S.areas.map(function (a) { return [String(a.id), a.name]; }).concat([['all', 'Visi']]);
    seg.hidden = S.areas.length < 2;
    seg.innerHTML = opts.map(function (o) { return '<button type="button" class="seg-btn' + (o[0] === cur ? ' on' : '') + '" aria-pressed="' + (o[0] === cur) + '" data-my-area="' + o[0] + '">' + esc(o[1]) + '</button>'; }).join('');
    // Pirmą kartą šiame kompiuteryje - paklausiama (dideli mygtukai)
    var ask = S.areas.length > 1 && !myArea;
    box.hidden = !ask;
    box.classList.toggle('ask', ask);
    if (ask) box.innerHTML = '<div class="ap-q"><b>▶ Start: prie kurio tatamio dirbate?</b><span class="muted small">Šiame kompiuteryje bus rodomas tik jis, kitą - vienu paspaudimu viršuje.</span></div><div class="ap-btns">' +
      opts.map(function (o) { return '<button type="button" class="btn' + (o[0] === 'all' ? '' : ' btn-primary') + '" data-my-area="' + o[0] + '">' + (o[0] === 'all' ? 'Visi tatamiai (vyr. sekretoriatas)' : esc(o[1])) + '</button>'; }).join('') + '</div>';
  }
  ['area-seg', 'area-pick'].forEach(function (id) {
    document.getElementById(id).addEventListener('click', function (e) { var b = e.target.closest('[data-my-area]'); if (b) setMyArea(b.dataset.myArea); });
  });
  // 📹 Live kova (v2.00): pasirinkto tatamio (arba pirmo su telefonu) vaizdas per visą ekraną
  var liveCamBtn = document.getElementById('live-cam');
  if (liveCamBtn) liveCamBtn.addEventListener('click', function () {
    var a = areaById(myArea);
    if (!a || !CAMS[a.id]) a = S.areas.find(function (x) { return CAMS[x.id]; });
    if (a) setLive(a.id);
  });

  // ---------- Sekanti grupė: pirma nebaigta po šios tame pačiame tatamyje ----------
  function nextGroup(c) {
    var a = areaById(c.tatami) || S.areas[0], items = areaPlan(a).items, i = items.findIndex(function (x) { return x.c === c; });
    var after = items.slice(i + 1).find(function (x) { return x.st !== 'done'; }) || items.find(function (x) { return x.st !== 'done' && x.c !== c; });
    return after ? after.c : null;
  }
  function goRun(id) {
    if (POPUP) { S.selected = id; save(); render(); if (winCh) winCh.postMessage({ t: 'open', cat: id }); window.scrollTo(0, 0); return; }
    openRun(id); window.scrollTo(0, 0);
  }
  function placesShort(c) {
    var pl = catPlaces(c).filter(function (x) { return x[0] <= 3; });
    return pl.map(function (x) { return x[1].map(function (id) { var p = person(c, id) || {}; return '<span class="rd-p"><span class="medal ' + ['', 'g', 's', 'b'][x[0]] + '">' + x[0] + '</span>' + esc(p.name || '') + ' <span class="muted">' + esc(p.club || '') + '</span></span>'; }).join(''); }).join('');
  }
  // Kai grupė baigta: „Siųsti apdovanojimams“ ir „Sekanti grupė →“
  function renderRunDone(c) {
    var box = document.getElementById('run-done'), st = catStatus(c);
    if (st === 'wait' || st === 'run') { box.hidden = true; box.innerHTML = ''; box.classList.remove('rd-float'); return; }
    var nx = nextGroup(c), others = ageGroup(c).filter(function (x) { return x !== c; });
    var pending = others.filter(function (x) { var s2 = catStatus(x); return s2 !== 'sent' && s2 !== 'awarded'; });
    var html = '<div class="rd-main"><span class="rd-mark">✓</span><div class="rd-txt"><div class="rd-title"><b>' + ({ sent: 'Baigta · išsiųsta apdovanojimams', awarded: 'Baigta ir apdovanota' }[st] || 'Grupė baigta') + '</b></div><div class="rd-places">' + placesShort(c) + '</div></div></div><div class="rd-btns">';
    if (st === 'fin') html += '<button type="button" class="btn btn-award" data-award-send="' + c.id + '">🏅 Siųsti apdovanojimams</button>';
    else if (st === 'sent') html += '<button type="button" class="btn btn-sm" data-award-undo="' + c.id + '" title="Grąžinti - dar nesiųsta">Atšaukti siuntimą</button>';
    html += nx ? '<button type="button" class="btn btn-primary rd-next" data-run-go="' + nx.id + '">Sekanti grupė: ' + esc(nx.name) + ' →</button>' : '<span class="muted small">Šio tatamio grupių daugiau nėra.</span>';
    if (CFG.diplomaApi && !CFG.readOnly) html += '<a class="btn btn-sm rd-dipl" href="' + esc(diplomaUrl(c.id, false)) + '" target="_blank" rel="noopener" title="Diplomai šios kategorijos prizininkams">🎓 Diplomai</a>';
    html += '</div>';
    if (others.length) {
      html += '<p class="rd-age small">Amžiaus grupė <b>' + esc(ageName(c)) + '</b>: ' + (pending.length
        ? 'apdovanojimų planšetėje ji užsidegs žaliai, kai bus išsiųsta ir ' + pending.map(function (x) { return esc(x.name) + ' <span class="muted">(' + ST_LABEL[catStatus(x)].toLowerCase() + ')</span>'; }).join(', ') + '.'
        : (st === 'fin' ? 'kitos rungtys jau išsiųstos - išsiuntus šią, planšetėje užsidegs žaliai.' : 'visos rungtys išsiųstos - planšetėje ji žalia.')) + '</p>';
    }
    // Dešinėje ekrano pusėje (nereikia slinkti į viršų pasibaigus paskutinei kovai); „▾“ - suskleisti
    html = '<button type="button" class="rd-min-btn" data-rd-min title="' + (rdMin ? 'Išskleisti' : 'Suskleisti') + '">' + (rdMin ? '▴' : '▾') + '</button>' + html;
    box.innerHTML = html; box.hidden = false; box.classList.add('rd-float'); box.classList.toggle('rd-min', rdMin);
  }
  var rdMin = false;
  document.addEventListener('click', function (e) { var b = e.target.closest && e.target.closest('[data-rd-min]'); if (!b) return; rdMin = !rdMin; var bx = document.getElementById('run-done'); bx.classList.toggle('rd-min', rdMin); b.textContent = rdMin ? '▴' : '▾'; b.title = rdMin ? 'Išskleisti' : 'Suskleisti'; });
  // Pačioje apačioje - visos šio tatamio grupės: kiek liko, kas toliau, būsenos
  function renderRunList(c, a, plan) {
    var left = plan.items.filter(function (x) { return x.st !== 'done'; }).length;
    document.getElementById('run-list').innerHTML = '<h2>' + esc(a.name) + ' - visos grupės <span class="muted small">liko ' + left + ' iš ' + plan.items.length + ' · pabaiga apie ' + hhmm(plan.end) + '</span></h2>' +
      '<ol class="rl">' + plan.items.map(function (x) {
        var st = catStatus(x.c, x.st === 'now');
        return '<li class="rl-row st-' + x.st + (x.c === c ? ' on' : '') + (isDetached(x.c.id) ? ' detached' : '') + '"><button type="button" data-run="' + x.c.id + '"><span class="rl-time">' + hhmm(x.start) + '</span>' +
          '<span class="rl-name"><span class="disc d-' + x.c.discipline + '"></span>' + esc(x.c.name) + (x.c === c ? ' <span class="rl-here">← atidaryta</span>' : '') + '</span>' +
          '<span class="rl-prog">' + x.done + '/' + x.total + '</span><span class="rl-badge">' + (x.st === 'next' ? ST_BADGE.next : '') + '</span>' + stChip(st) + '</button></li>';
      }).join('') + '</ol>';
  }
  function areaPlan(a) {
    var t = toMin(a.start), out = [], cur = null;
    S.categories.filter(function (c) { return c.tatami === a.id; }).forEach(function (c) {
      if (c.at) t = Math.max(t, toMin(c.at));   // rungčiai nustatytas laikas „ne anksčiau kaip“
      var dur = minutesFor(c), total = matchCount(c), done = Math.min(doneCount(c), total), st;
      if ((total && done >= total) || (!total && c.competitors.length < 2)) st = 'done';
      else if (!cur) st = 'now';
      else st = out.length && out[out.length - 1].st === 'now' ? 'next' : 'later';
      var it = { c: c, start: t, dur: dur, total: total, done: done, pct: total ? Math.round(done / total * 100) : 0, st: st };
      if (st === 'now') cur = it;
      out.push(it); t += dur;
    });
    var delay = null;
    if (cur && liveDay()) {
      var left = cur.dur * (1 - cur.done / Math.max(1, cur.total)), rest = 0;
      out.forEach(function (x) { if (x.st !== 'done' && x !== cur) rest += x.dur; });
      delay = Math.round(nowMin() + left + rest - t);
    }
    return { items: out, end: t, delay: delay, cur: cur };
  }
  function delayChip(d) {
    if (d === null) return '';
    if (d > 5) return '<span class="delay late" title="Pagal dabartinį tempą tatamis baigs vėliau nei planuota">vėluoja ~' + d + ' min</span>';
    if (d < -5) return '<span class="delay early" title="Tatamis lenkia planą">lenkia ' + (-d) + ' min</span>';
    return '<span class="delay ok">laiku</span>';
  }
  var editName = null, peopleOpen = {};
  var ST_BADGE = { now: '<span class="st-badge now">DABAR</span>', next: '<span class="st-badge next">toliau</span>', done: '<span class="st-badge done">✓</span>', later: '' };
  // Paieška tvarkaraštyje: dalyvis, klubas ar kategorija → kur ir kada (be lietuviškų raidžių skirtumo)
  var schedQ = '';
  function fold(x) { return String(x || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }
  function schedHits(c, q) { return q ? c.competitors.filter(function (p) { return fold(p.name + ' ' + p.club).indexOf(q) >= 0; }) : []; }
  function renderFound(q) {
    var box = document.getElementById('sched-found'); if (!box) return;
    if (!q) { box.hidden = true; box.innerHTML = ''; return; }
    var rows = [];
    S.areas.forEach(function (a) {
      areaPlan(a).items.forEach(function (x) {
        var c = x.c, hits = schedHits(c, q), catHit = fold(c.name).indexOf(q) >= 0;
        (hits.length ? hits : catHit ? [null] : []).forEach(function (p) { rows.push({ p: p, c: c, a: a, start: x.start, st: x.st }); });
      });
    });
    box.hidden = false;
    box.innerHTML = '<div class="sf-head"><b>Rasta: ' + rows.length + '</b> <span class="muted small">Paspauskite - atsidarys kategorija.</span></div>' + (rows.length ? '<div class="sf-list">' + rows.slice(0, 60).map(function (r) {
      return '<button type="button" class="sf-row" data-sq-open="' + r.c.id + '">' + (r.p ? '<b>' + esc(r.p.name) + '</b> <span class="muted">' + esc(r.p.club) + '</span>' : '<b>' + esc(r.c.name) + '</b>') +
        '<span class="sf-where"><span class="disc d-' + r.c.discipline + '"></span>' + (r.p ? esc(r.c.name) + ' · ' : '') + esc(r.a.name) + ' · ~' + hhmm(r.start) + '</span>' + (ST_BADGE[r.st] || (r.st === 'done' ? '<span class="st-badge">baigta</span>' : '')) + '</button>';
    }).join('') + '</div>' : '<p class="muted small">Nieko nerasta - patikrinkite vardą ar klubą.</p>');
  }
  var sqIn = document.getElementById('sched-q');
  if (sqIn) {
    sqIn.addEventListener('input', function () { schedQ = sqIn.value; renderSched(); });
    document.getElementById('sched-found').addEventListener('click', function (e) { var b = e.target.closest('[data-sq-open]'); if (b) openRun(b.dataset.sqOpen); });
  }
  function renderSched() {
    Object.keys(DISC).forEach(function (k) { document.getElementById('m-' + k).value = S.minutes[k]; });
    var q = fold(schedQ.trim());
    renderFound(q);
    var clock = document.getElementById('live-clock'); if (clock) clock.textContent = hhmm(nowMin());
    var wrap = document.getElementById('sched');
    renderAreaPick();
    var areas = shownAreas();
    wrap.classList.toggle('one', areas.length === 1);
    wrap.innerHTML = areas.map(function (a, ai) {
      var plan = areaPlan(a), list = plan.items.map(function (x) { return x.c; }), t = plan.end;
      var items = plan.items.map(function (x, idx) {
        var c = x.c, start = x.start, dur = x.dur, total = x.total, done = x.done, pct = x.pct;
        var moveOpts = S.areas.filter(function (y) { return y.id !== a.id; }).map(function (y) { return '<button class="btn" type="button" data-to="' + y.id + '" data-c="' + c.id + '" title="Perkelti į ' + esc(y.name) + '">→ ' + esc(y.name.replace(' TATAMIS', '')) + '</button>'; }).join('');
        var det = isDetached(c.id);
        var nameEl = editName === c.id ? '<input class="sel card-name-in" data-cname="' + c.id + '" value="' + esc(c.name) + '" aria-label="Rungties pavadinimas">'
          : '<button type="button" class="card-name" data-open-cat="' + c.id + '" title="Atidaryti: lentelė, kovos, balai"><span class="disc d-' + c.discipline + '"></span>' + esc(c.name) + '</button>';
        var hits = schedHits(c, q), hit = q && (hits.length || fold(c.name).indexOf(q) >= 0);
        return '<li class="card st-' + x.st + (det ? ' detached' : '') + (x.st === 'done' && catStatus(c) === 'fin' ? ' needs-send' : '') + (q ? (hit ? ' sq-hit' : ' sq-dim') : '') + '" draggable="true" data-c="' + c.id + '"><div class="card-top">' +
          '<span class="card-grip" data-card-grip title="Tempkite - pakeisti eilę ar tatamį (ir pirštu)" aria-hidden="true">⠿</span>' +
          '<input class="card-time-in" type="text" inputmode="numeric" maxlength="5" value="' + hhmm(start) + '" data-cat-at="' + c.id + '" title="' + (c.at ? 'Nustatytas laikas (ištrinkite - vėl skaičiuosis pats)' : 'Pakeiskite - rungtis prasidės ne anksčiau') + '"' + (c.at ? ' data-fixed="1"' : '') + '>' +
          nameEl + (det ? '<span class="st-badge det">atskirame lange</span>' : x.st === 'done' ? '' : ST_BADGE[x.st]) + (function () {
            var st = catStatus(c, x.st === 'now');
            return st === 'wait' ? '' : st === 'fin' ? '<button type="button" class="btn btn-award btn-xs" data-award-send="' + c.id + '" title="Siųsti apdovanojimams">🏅 Siųsti</button>' : stChip(st);
          })() + '</div>' +
          (peopleOpen[c.id] || hits.length ? '<div class="card-people">' + c.competitors.map(function (p) { return '<span' + (hits.indexOf(p) >= 0 ? ' class="sq-me"' : '') + '>' + esc(p.name) + ' <i>' + esc(p.club) + '</i></span>'; }).join('') + '</div>' : '') +
          '<div class="prog' + (pct === 100 ? ' done' : '') + '"><i style="width:' + pct + '%"></i><span>' + done + ' / ' + total + '</span></div>' +
          '<div class="card-actions"><button type="button" class="btn" data-people="' + c.id + '" aria-expanded="' + !!peopleOpen[c.id] + '" title="Dalyviai">👥 ' + c.competitors.length + '</button><span class="muted small">~' + dur + ' min</span>' +
          '<span class="card-tools"><button type="button" class="btn" data-rename="' + c.id + '" title="Pervadinti">✎</button><button type="button" class="btn" data-pin-cat="' + c.id + '" title="Prisegti langelį ekrane (ar nutempkite kortelę už tatamių)">📌</button><span class="spacer"></span>' +
          '<button class="btn" type="button" data-up="' + c.id + '"' + (idx === 0 ? ' disabled' : '') + ' aria-label="Aukštyn" title="Aukštyn">↑</button><button class="btn" type="button" data-down="' + c.id + '"' + (idx === list.length - 1 ? ' disabled' : '') + ' aria-label="Žemyn" title="Žemyn">↓</button>' + moveOpts + '</span></div></li>';
      }).join('');
      return '<div class="area' + (plan.delay !== null && plan.delay > 5 ? ' late' : '') + '" data-area="' + a.id + '"><div class="area-head"><input class="name" value="' + esc(a.name) + '" data-rename="' + a.id + '" aria-label="Tatamio pavadinimas">' +
        '<div class="area-meta"><label>Pradžia <input type="time" value="' + esc(a.start) + '" data-start="' + a.id + '"></label>' + delayChip(plan.delay) +
          (CFG.admin ? '<label class="area-vid" title="Telefonu filmuojamas tatamis: YouTube programėlėje „Transliuoti“ ir čia įklijuokite nuorodą - žiūrovai matys vaizdą su kovos rezultatu">📹 <input type="url" value="' + esc(a.stream || '') + '" data-stream="' + a.id + '" placeholder="YouTube Live nuoroda" aria-label="Tatamio vaizdo nuoroda (YouTube Live)"></label>' : '') + '</div>' +
        (plan.items.length ? '<button type="button" class="btn btn-primary area-go" data-start-run="' + a.id + '">▶ ' + (plan.cur ? 'Dabar: ' + esc(plan.cur.c.name) : 'Visos grupės baigtos') + '</button>' : '') + '</div>' +
        '<ul class="area-list" data-drop="' + a.id + '">' + (items || '<li class="muted small">Tempkite kategoriją čia</li>') + '</ul>' +
        '<div class="area-foot">Pabaiga apie <b>' + hhmm(t) + '</b> · ' + list.length + ' kateg.' + (S.areas.length > 1 && CFG.admin ? ' · <button class="area-del" type="button" data-del-area="' + a.id + '" title="Pašalinti šį tatamį (jo rungtys pereis į kitus)">Pašalinti tatamį</button>' : '') + '</div></div>';
    }).join('');
  }
  // ---------- Atkabinta rungtis (atskiras naršyklės langas): tvarkaraštyje ji pilka, kol langas atidarytas ----------
  var detached = {}, winCh = null;
  try { winCh = new BroadcastChannel('taika-win-' + CFG.id); } catch (e) {}
  function isDetached(id) { return !POPUP && detached[id] && Date.now() - detached[id] < 8000; }
  if (winCh) {
    winCh.onmessage = function (ev) {
      var d = ev.data || {}; if (!d.cat || POPUP) return;
      var was = isDetached(d.cat);
      if (d.t === 'open') detached[d.cat] = Date.now(); else delete detached[d.cat];
      if (was !== isDetached(d.cat)) render();
    };
    if (POPUP) {
      var ping = function () { if (S && S.selected) winCh.postMessage({ t: 'open', cat: S.selected }); };
      setTimeout(ping, 0); setInterval(ping, 3000);
      window.addEventListener('pagehide', function () { if (S && S.selected) winCh.postMessage({ t: 'close', cat: S.selected }); });
    }
  }
  setInterval(function () { Object.keys(detached).forEach(function (k) { if (Date.now() - detached[k] >= 8000) { delete detached[k]; render(); } }); }, 4000);
  function detach(c) {
    // Langas atidaromas iš karto (kitaip naršyklė jį užblokuotų), adresas - kai pakeitimai jau išsaugoti
    var w = window.open('', 'taika-' + CFG.id + '-' + c.id, 'width=1100,height=760');
    if (!w) { toast('Naršyklė neleido atidaryti lango - leiskite iššokančius langus šiam puslapiui.'); return; }
    var url = location.pathname + '?id=' + CFG.id + '&langas=1&cat=' + encodeURIComponent(c.id), tries = 0;
    flush();
    (function go() {
      if ((dirty || saving) && tries++ < 40) { setTimeout(go, 100); return; }
      w.location.href = url;
    })();
    detached[c.id] = Date.now(); setRun(false); render();
  }

  // ---------- Live: lenta arba atidarytos rungties valdymas ----------
  // ---------- Prisegta kategorija: mažas langelis bet kurioje ekrano vietoje (matosi ir kituose skirtukuose) ----------
  // Tvarkaraštyje nutempkite kategoriją už tatamių stulpelių (ar spauskite 📌) - langelis lieka, kur paleidote; jį galima tempti už antraštės.
  var pins = [];
  try { pins = JSON.parse(localStorage.getItem(KEY + '-pins') || '[]') || []; } catch (e) {}
  var pinBox = document.createElement('div'); pinBox.className = 'pins'; document.body.appendChild(pinBox);
  function savePins() { try { localStorage.setItem(KEY + '-pins', JSON.stringify(pins)); } catch (e) {} }
  function pinCat(id, x, y) {
    if (!cat(id)) return;
    pins = pins.filter(function (p) { return p.id !== id; });
    var w = Math.min(300, window.innerWidth - 24);
    pins.push({ id: id, x: x === undefined ? window.innerWidth - w - 16 : x - w / 2, y: y === undefined ? 90 + pins.length * 30 : y - 20, min: false });
    if (pins.length > 4) pins.shift();
    savePins(); renderPins();
    toast('📌 Prisegta: <b>' + esc(cat(id).name) + '</b>. Tempkite už antraštės, ✕ - atsegti.');
  }
  // Prisegtame langelyje - visa lentelė: paspaudus vardą pažymimas nugalėtojas (dar kartą - atšaukiama)
  function pinTree(c) {
    if (!c.draw || drawStale(c) || c.competitors.length < 2) return '<p class="muted small">Lentelė dar nesudaryta.</p>';
    var b = catBracket(c, true), h = '';
    (b.groups || []).forEach(function (g) {
      h += '<h4>' + esc(g.t) + '</h4>';
      if (g.score) { h += '<ol class="gv-score">' + g.rows.map(function (r) { return '<li><span>' + esc(r.n) + '</span><b>' + esc(r.s || '–') + '</b></li>'; }).join('') + '</ol>'; return; }
      h += '<div class="pin-ms">' + g.matches.map(function (m) {
        var side = function (k) {
          var id = m[k + 'i'], nm = m[k], won = m.w === k;
          return id && m.ai && m.bi ? '<button type="button" class="pin-p' + (won ? ' won' : '') + (k === 'a' ? ' aka-t' : '') + '" data-pin-res="' + esc(c.id + '|' + m.id + '|' + id) + '" title="Pažymėti nugalėtoju">' + esc(nm) + '</button>'
            : '<span class="pin-p' + (won ? ' won' : '') + '">' + esc(nm || '…') + '</span>';
        };
        return '<div class="pin-m' + (m.w ? ' done' : '') + '"><span class="muted">' + esc(m.no) + '</span>' + side('a') + '<span class="muted">' + esc(m.s || 'vs') + '</span>' + side('b') + '</div>';
      }).join('') + '</div>';
    });
    return h;
  }
  function pinBody(c, view) {
    if (view === 'tree') return pinTree(c);
    var a = areaById(c.tatami) || S.areas[0], plan = areaPlan(a), it = plan.items.find(function (x) { return x.c === c; }) || { start: toMin(a.start), done: 0, total: matchCount(c), st: '' };
    var st = catStatus(c, it.st === 'now'), html = '<div class="pin-meta">' + esc(a.name) + ' · ' + hhmm(it.start) + ' ' + stChip(st) + '</div>' +
      '<div class="prog' + (it.total && it.done >= it.total ? ' done' : '') + '"><i style="width:' + (it.total ? Math.round(it.done / it.total * 100) : 0) + '%"></i><span>' + it.done + ' / ' + it.total + '</span></div>';
    if (st === 'fin' || st === 'sent' || st === 'awarded') return html + '<div class="pin-places">' + placesShort(c) + '</div>';
    var up = upcoming(c), nm = function (id) { return esc((person(c, id) || {}).name || '—'); };
    if (up[0]) html += '<div class="pin-now"><span class="muted small">Dabar:</span> <b class="aka-t">' + nm(up[0].a) + '</b>' + (up[0].b ? ' <span class="muted">–</span> <b>' + nm(up[0].b) + '</b>' : '') + '</div>';
    if (up[1]) html += '<div class="pin-next small muted">Toliau: ' + nm(up[1].a) + (up[1].b ? ' – ' + nm(up[1].b) : '') + '</div>';
    return html;
  }
  function renderPins() {
    if (POPUP) return;
    pins = pins.filter(function (p) { return cat(p.id); });
    pinBox.innerHTML = pins.map(function (p) {
      var c = cat(p.id), w = Math.max(220, Math.min(p.w || (p.view === 'tree' ? 380 : 300), window.innerWidth - 24));
      var x = Math.max(8, Math.min(p.x, window.innerWidth - w - 8)), y = Math.max(8, Math.min(p.y, window.innerHeight - 60));
      return '<section class="pin-win' + (p.min ? ' min' : '') + (p.view === 'tree' ? ' tree' : '') + '" data-pin="' + esc(p.id) + '" style="left:' + x + 'px;top:' + y + 'px;width:' + w + 'px' + (p.h && !p.min ? ';height:' + Math.min(p.h, window.innerHeight - 16) + 'px' : '') + '" aria-label="Prisegta: ' + esc(c.name) + '">' +
        '<header class="pin-head" data-pin-drag title="Tempkite - perkelti"><span class="disc d-' + c.discipline + '"></span><b>' + esc(c.name) + '</b>' +
        '<button type="button" data-pin-view title="' + (p.view === 'tree' ? 'Rodyti: kas kovoja dabar' : 'Rodyti visą lentelę (galima žymėti nugalėtojus)') + '">' + (p.view === 'tree' ? '◉' : '▦') + '</button>' +
        '<button type="button" data-pin-open title="Atidaryti">↗</button><button type="button" data-pin-min title="' + (p.min ? 'Išskleisti' : 'Suskleisti') + '">' + (p.min ? '▢' : '–') + '</button><button type="button" data-pin-x title="Atsegti">✕</button></header>' +
        (p.min ? '' : '<div class="pin-body">' + pinBody(c, p.view) + '</div><span class="pin-resize" data-pin-resize title="Tempkite - keisti dydį" aria-hidden="true"></span>') + '</section>';
    }).join('');
  }
  // Langelio tempimas už antraštės (pele ir pirštu); prie ekrano krašto - prilimpa
  var pinMove = null;
  pinBox.addEventListener('pointerdown', function (e) {
    var h = e.target.closest('[data-pin-drag]'); if (!h || e.target.closest('button')) return;
    var win = h.closest('.pin-win'), r = win.getBoundingClientRect();
    pinMove = { id: win.dataset.pin, win: win, dx: e.clientX - r.left, dy: e.clientY - r.top };
    win.classList.add('moving'); e.preventDefault();
  });
  var pinSize = null;
  pinBox.addEventListener('pointerdown', function (e) {
    var g = e.target.closest('[data-pin-resize]'); if (!g) return;
    var win = g.closest('.pin-win'), r = win.getBoundingClientRect();
    pinSize = { id: win.dataset.pin, win: win, x: e.clientX, y: e.clientY, w: r.width, h: r.height }; e.preventDefault(); e.stopPropagation();
  }, true);
  document.addEventListener('pointermove', function (e) {
    if (!pinSize) return;
    pinSize.win.style.width = Math.max(220, pinSize.w + e.clientX - pinSize.x) + 'px';
    pinSize.win.style.height = Math.max(120, pinSize.h + e.clientY - pinSize.y) + 'px';
  });
  document.addEventListener('pointerup', function () {
    if (!pinSize) return;
    var m = pinSize, p = pins.find(function (x) { return x.id === m.id; }); pinSize = null;
    if (p) { p.w = Math.round(m.win.offsetWidth); p.h = Math.round(m.win.offsetHeight); savePins(); }
  });
  document.addEventListener('pointermove', function (e) {
    if (!pinMove) return;
    pinMove.win.style.left = (e.clientX - pinMove.dx) + 'px'; pinMove.win.style.top = (e.clientY - pinMove.dy) + 'px';
  });
  document.addEventListener('pointerup', function () {
    if (!pinMove) return;
    var m = pinMove, r = m.win.getBoundingClientRect(), p = pins.find(function (x) { return x.id === m.id; });
    pinMove = null; m.win.classList.remove('moving');
    if (!p) return;
    p.x = r.left < 24 ? 8 : r.right > window.innerWidth - 24 ? window.innerWidth - r.width - 8 : r.left;
    p.y = r.top < 24 ? 8 : r.bottom > window.innerHeight - 24 ? window.innerHeight - r.height - 8 : r.top;
    savePins(); renderPins();
  });
  pinBox.addEventListener('click', function (e) {
    var win = e.target.closest('.pin-win'); if (!win) return;
    var id = win.dataset.pin, p = pins.find(function (x) { return x.id === id; });
    if (e.target.closest('[data-pin-x]')) { pins = pins.filter(function (x) { return x.id !== id; }); savePins(); renderPins(); }
    else if (e.target.closest('[data-pin-min]') && p) { p.min = !p.min; savePins(); renderPins(); }
    else if (e.target.closest('[data-pin-view]') && p) { p.view = p.view === 'tree' ? '' : 'tree'; p.h = 0; savePins(); renderPins(); }
    else if (e.target.closest('[data-pin-res]')) {
      var pr = e.target.closest('[data-pin-res]').dataset.pinRes.split('|'), pc = cat(pr[0]); if (!pc || !pc.draw) return;
      snapshot();
      var wasDone = catFinished(pc);
      if (pc.draw.results[pr[1]] === pr[2]) delete pc.draw.results[pr[1]]; else pc.draw.results[pr[1]] = pr[2];
      prune(pc); autoLock(pc, wasDone); save(); render();
    }
    else if (e.target.closest('[data-pin-open]')) goRun(id);
  });
  window.addEventListener('resize', function () { renderPins(); });
  // Tvarkaraščio kortelė nutempta ne ant tatamio - prisegama ten, kur paleista
  document.addEventListener('dragover', function (e) { if (dragId && !e.target.closest('[data-drop]')) { e.preventDefault(); document.body.classList.add('pin-drop'); } });
  document.addEventListener('dragleave', function (e) { if (!e.relatedTarget) document.body.classList.remove('pin-drop'); });
  document.addEventListener('drop', function (e) {
    document.body.classList.remove('pin-drop');
    if (!dragId || e.target.closest('[data-drop]')) return;
    e.preventDefault(); var id = dragId; dragId = null; pinCat(id, e.clientX, e.clientY);
  });

  function renderLive() {
    var lc = document.getElementById('live-cam');
    if (lc) lc.hidden = !Object.keys(CAMS).length;
    syncLive();
    var c = runOpen ? cat(S.selected) : null;
    if (runOpen && !c) setRun(false);
    document.getElementById('live-board').hidden = !!c;
    document.getElementById('run').hidden = !c;
    if (!c) { renderSched(); return; }
    renderRunHead(c);
    renderDraw();
  }
  function runNeighbours(c) {
    var list = S.categories.filter(function (x) { return x.tatami === c.tatami; }), i = list.indexOf(c);
    return { prev: list[i - 1] || null, next: list[i + 1] || null };
  }
  function renderRunHead(c) {
    var a = S.areas.find(function (x) { return x.id === c.tatami; }) || S.areas[0], plan = areaPlan(a);
    var it = plan.items.find(function (x) { return x.c === c; }) || { start: toMin(a.start), done: 0, total: matchCount(c) };
    document.getElementById('run-name').innerHTML = '<span class="disc d-' + c.discipline + '"></span>' + esc(c.name);
    document.getElementById('run-meta').textContent = a.name + ' · ' + hhmm(it.start) + ' · ' + c.competitors.length + ' dalyv. · kovos ' + it.done + ' / ' + it.total;
    var nb = runNeighbours(c);
    document.querySelectorAll('[data-runview]').forEach(function (x) { x.classList.toggle('on', x.dataset.runview === runView); });
    document.getElementById('run-prev').disabled = !nb.prev;
    var nx = document.getElementById('run-next'); nx.disabled = !nb.next;
    nx.title = nb.next ? 'Kita: ' + nb.next.name : 'Šio tatamio grupių daugiau nėra';
    document.getElementById('run-prev').title = nb.prev ? 'Ankstesnė: ' + nb.prev.name : 'Tai pirma šio tatamio grupė';
    var idx = plan.items.findIndex(function (x) { return x.c === c; }), left = plan.items.filter(function (x) { return x.st !== 'done'; }).length;
    document.getElementById('run-strip').innerHTML =
      (S.areas.length > 1 && S.areas.length <= 4 ? '<span class="seg rs-area" role="group" aria-label="Tatamis">' + S.areas.map(function (x) { return '<button type="button" class="seg-btn' + (x.id === a.id ? ' on' : '') + '" data-rs-area="' + x.id + '" title="' + (x.id === a.id ? 'Šis tatamis' : 'Pažiūrėti: dabartinė ' + esc(x.name) + ' grupė') + '">' + esc(x.name) + '</button>'; }).join('') + '</span>'
        : S.areas.length > 4 ? '<select class="sel rs-area" id="rs-area" aria-label="Tatamis" title="Pažiūrėti kitą tatamį">' + S.areas.map(function (x) { return '<option value="' + x.id + '"' + (x.id === a.id ? ' selected' : '') + '>' + esc(x.name) + '</option>'; }).join('') + '</select>' : '<b class="rs-area">' + esc(a.name) + '</b>') +
      delayChip(plan.delay) + '<span class="rs-pos muted small">Grupė ' + (idx + 1) + ' iš ' + plan.items.length + ' · liko ' + left + '</span>' + stChip(catStatus(c, it.st === 'now')) +
      '<a class="rs-all small" href="#run-list">Visos grupės ↓</a>';
    // Iš pačios rungties: pradėti ją dabar (be eilės) arba perkelti į kitą tatamį
    var isCur = plan.cur && plan.cur.c === c, itSt = (plan.items.find(function (x) { return x.c === c; }) || {}).st;
    document.getElementById('run-move').innerHTML = (CFG.readOnly ? '' :
      (!isCur && itSt && itSt !== 'done' ? '<button type="button" class="btn btn-sm" data-run-start="' + c.id + '" title="Ši grupė taps dabartine šiame tatamyje (kitos pasislinks)">▶ Pradėti dabar</button>' : '') +
      '');
    renderRunDone(c);
    renderRunList(c, a, plan);
  }
  document.getElementById('run-move').addEventListener('click', function (e) {
    var c = cat(S.selected); if (!c) return;
    var st = e.target.closest('[data-run-start]');
    if (st) { var pl = areaPlan(areaById(c.tatami)); if (pl.cur && pl.cur.c !== c) moveCat(c.id, c.tatami, pl.cur.c.id); toast('„' + esc(c.name) + '“ - dabar šiame tatamyje.'); return; }
    var to = e.target.closest('[data-run-to]');
    if (to) { var ar = areaById(to.dataset.runTo); moveCat(c.id, +to.dataset.runTo, null); toast('Perkelta į <b>' + esc(ar ? ar.name : '') + '</b>.'); }
  });
  document.getElementById('run-back').addEventListener('click', function () { if (POPUP) { window.close(); return; } setRun(false); render(); });
  // Viršutinė „←“: iš atidarytos rungties - atgal į visą tvarkaraštį (ne į visų varžybų sąrašą)
  var vzBack = document.querySelector('.vz-back');
  if (vzBack) vzBack.addEventListener('click', function (e) { if (runOpen && curTab() === 'live') { e.preventDefault(); setRun(false); render(); } else if (curTab() !== 'live') { e.preventDefault(); showTab('live'); } });
  document.getElementById('run').addEventListener('click', function (e) {
    var v = e.target.closest('[data-runview]'); if (!v) return;
    runView = v.dataset.runview; try { sessionStorage.setItem(KEY + '-runview', runView); } catch (x) {}
    render();
  });
  document.getElementById('run-prev').addEventListener('click', function () {
    var nb = runNeighbours(cat(S.selected)); if (!nb.prev) return;
    if (POPUP) { S.selected = nb.prev.id; save(); render(); return; }
    openRun(nb.prev.id);
  });
  document.getElementById('run-next').addEventListener('click', function () {
    var nb = runNeighbours(cat(S.selected)); if (!nb.next) return;
    if (POPUP) { S.selected = nb.next.id; save(); render(); if (winCh) winCh.postMessage({ t: 'open', cat: nb.next.id }); return; }
    openRun(nb.next.id);
  });
  document.getElementById('run-detach').addEventListener('click', function () { var c = cat(S.selected); if (c) detach(c); });
  document.getElementById('run-pin').addEventListener('click', function () { if (S.selected) pinCat(S.selected); });
  document.getElementById('run-strip').addEventListener('click', function (e) {
    var b = e.target.closest('[data-run]'); if (b) openRun(b.dataset.run);
    var ra = e.target.closest('[data-rs-area]');
    if (ra) { var pl = areaPlan(areaById(ra.dataset.rsArea)), go = pl.cur || pl.items[0]; if (go) openRun(go.c.id); return; }
    var all = e.target.closest('.rs-all'); if (all) { e.preventDefault(); document.getElementById('run-list').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });
  document.getElementById('run-list').addEventListener('click', function (e) { var b = e.target.closest('[data-run]'); if (b) goRun(b.dataset.run); });
  document.getElementById('run-done').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    if (b.dataset.awardSend) setAward([b.dataset.awardSend], 'sent');
    else if (b.dataset.awardUndo) { if (confirm('Atšaukti siuntimą apdovanojimams? Planšetėje ši grupė vėl bus nebaigta.')) setAward([b.dataset.awardUndo], ''); }
    else if (b.dataset.runGo) goRun(b.dataset.runGo);
  });
  document.getElementById('run-strip').addEventListener('change', function (e) {
    if (e.target.id !== 'rs-area') return;
    var plan = areaPlan(S.areas.find(function (x) { return x.id === +e.target.value; }));
    var go = plan.cur || plan.items[0]; if (go) openRun(go.c.id);
  });
  // Laikrodis ir vėlavimas atsinaujina kas 30 s (jei niekas nevelkama ir nerašoma lentoje)
  setInterval(function () {
    var board = document.getElementById('live-board');
    if (document.getElementById('v-live').hidden || board.hidden || dragId) return;
    if (document.activeElement && board.contains(document.activeElement) && /INPUT|SELECT/.test(document.activeElement.tagName)) return;
    renderSched();
  }, 30000);
  var tSwitch = document.getElementById('t-switch');
  if (tSwitch) tSwitch.addEventListener('change', function () { flush(true); location.href = tSwitch.value; });
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
  // Liečiamame ekrane (planšetė) HTML5 tempimas neveikia - kortelė tempiama už ⠿: į kitą eilę, kitą tatamį arba už tatamių (prisegti)
  var cd = null;
  sched.addEventListener('pointerdown', function (e) {
    var g = e.target.closest('[data-card-grip]'); if (!g || e.pointerType === 'mouse') return;
    var li = g.closest('.card'); if (!li) return;
    e.preventDefault();
    var r = li.getBoundingClientRect(), gh = li.cloneNode(true);
    gh.className += ' drag-ghost'; gh.style.cssText = 'position:fixed;left:' + r.left + 'px;top:' + r.top + 'px;width:' + r.width + 'px;pointer-events:none;z-index:2000;opacity:.9;';
    document.body.appendChild(gh); li.classList.add('dragging');
    cd = { id: li.dataset.c, gh: gh, dx: e.clientX - r.left, dy: e.clientY - r.top };
  });
  document.addEventListener('pointermove', function (e) {
    if (!cd) return; e.preventDefault();
    cd.gh.style.left = (e.clientX - cd.dx) + 'px'; cd.gh.style.top = (e.clientY - cd.dy) + 'px';
    var el = document.elementFromPoint(e.clientX, e.clientY), ul = el && el.closest('[data-drop]');
    sched.querySelectorAll('.drop').forEach(function (x) { x.classList.remove('drop'); }); if (ul) ul.classList.add('drop');
    document.body.classList.toggle('pin-drop', !ul);
  }, { passive: false });
  function cardDrop(e, cancel) {
    if (!cd) return;
    var id = cd.id; cd.gh.remove(); cd = null;
    document.body.classList.remove('pin-drop'); sched.querySelectorAll('.drop, .dragging').forEach(function (x) { x.classList.remove('drop', 'dragging'); });
    if (cancel) return;
    var el = document.elementFromPoint(e.clientX, e.clientY), ul = el && el.closest('[data-drop]');
    if (ul) { var over = el.closest('.card'); moveCat(id, +ul.dataset.drop, over && over.dataset.c !== id ? over.dataset.c : null); }
    else if (el && !el.closest('#sched')) pinCat(id, e.clientX, e.clientY);
  }
  document.addEventListener('pointerup', function (e) { cardDrop(e, false); });
  document.addEventListener('pointercancel', function (e) { cardDrop(e, true); });
  sched.addEventListener('keydown', function (e) { if (e.target.dataset && e.target.dataset.cname && (e.key === 'Enter' || e.key === 'Escape')) { if (e.key === 'Escape') { editName = null; renderSched(); } else e.target.blur(); } });
  sched.addEventListener('dragstart', function (e) { var li = e.target.closest('.card'); if (!li || /INPUT/.test(e.target.tagName)) return; dragId = li.dataset.c; li.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', dragId); } catch (x) {} });
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
    if (oc) { openRun(oc.dataset.openCat); return; }
    var pc = t.closest && t.closest('[data-pin-cat]'); if (pc) { pinCat(pc.dataset.pinCat); return; }
    var sr = t.closest && t.closest('[data-start-run]');
    if (sr) { var pl = areaPlan(areaById(sr.dataset.startRun)), go = pl.cur || pl.items[pl.items.length - 1]; if (go) openRun(go.c.id); return; }
    var as = t.closest && t.closest('[data-award-send]'); if (as) { setAward([as.dataset.awardSend], 'sent'); return; }
    var pb = t.closest && t.closest('[data-people]'); if (pb) { peopleOpen[pb.dataset.people] = !peopleOpen[pb.dataset.people]; renderSched(); return; }
    var rb = t.closest && t.closest('[data-rename]'); if (rb) { editName = rb.dataset.rename; renderSched(); var ip = document.querySelector('[data-cname="' + editName + '"]'); if (ip) { ip.focus(); ip.select(); } return; }
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
    var t0 = e.target;
    if (t0.dataset.catAt) {
      var ca = cat(t0.dataset.catAt), tv = t0.value.trim().replace('.', ':'); if (!ca) return;
      if (tv && !/^\d{1,2}:\d{2}$/.test(tv)) { toast('Laiką įrašykite taip: 13:45'); renderSched(); return; }
      snapshot(); if (tv) ca.at = hhmm(toMin(tv)); else delete ca.at; save(); render(); return;
    }
    if (t0.dataset.cname) { var cn = cat(t0.dataset.cname); editName = null; if (!cn) return; var v = t0.value.trim(); if (v && v !== cn.name) { snapshot(); cn.name = v; cn.customName = v !== critName(cn); save(); } render(); return; }
    var t = e.target;
    if (t.dataset.start) { S.areas.find(function (a) { return a.id === +t.dataset.start; }).start = t.value || '09:00'; }
    else if (t.dataset.rename) { S.areas.find(function (a) { return a.id === +t.dataset.rename; }).name = t.value.trim() || 'TATAMIS'; }
    else if (t.dataset.stream) {   // 📹 tatamio vaizdas (YouTube Live) - žiūrovų puslapyje su kovos rezultatu ant vaizdo
      var v = t.value.trim();
      if (v && !ytEmbed(v)) { toast('Įklijuokite YouTube nuorodą (pvz. https://youtube.com/live/…)'); return; }
      S.areas.find(function (a) { return a.id === +t.dataset.stream; }).stream = v;
    }
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
  // Viešai trumpinama („Jonas P.“) iki 18 m. ir tada, kai gimimo metai nežinomi (įrašyta ranka) - saugiau (B3)
  function isMinor(id) { var p = S.people[id]; return !S.teams[id] && !!(p && (!p.birth || YEAR - p.birth < 18)); }
  function resultsPayload() {
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
  function catBracket(c, full) {
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
  // ---------- Tinklai (Draws): amžiaus grupės → visos tos grupės rungtys - kas su kuo kovoja, kas laimėjo (tik peržiūra) ----------
  var tkGroup = null;
  try { tkGroup = sessionStorage.getItem(KEY + '-tk'); } catch (e) {}
  function drawGroups() {
    var map = {}, list = [];
    S.categories.forEach(function (c) {
      if (!c.competitors.length) return;
      var k = ageKey(c), y = crit(c).y1 || crit(c).y0 || 0;
      if (!map[k]) { map[k] = { key: k, name: ageName(c), cats: [], y: y }; list.push(map[k]); }
      map[k].cats.push(c);
    });
    return list.sort(function (a, b) { return b.y - a.y || a.name.localeCompare(b.name); });   // jauniausi pirma
  }
  function bracketHtml(b) {
    var h = '';
    if (b.places && b.places.length) h += '<div class="gv-places">' + b.places.map(function (p) { return '<div class="rp"><span class="medal ' + ['', 'g', 's', 'b'][p.p] + '">' + p.p + '</span><span>' + esc(p.n) + ' <span class="club">' + esc(p.c) + '</span></span></div>'; }).join('') + '</div>';
    if (b.people) h += '<p class="muted small">Lentelė dar nesudaryta. Dalyviai:</p><ul class="gv-people">' + b.people.map(function (p) { return '<li>' + esc(p.n) + ' <span class="muted">' + esc(p.c) + '</span></li>'; }).join('') + '</ul>';
    (b.groups || []).forEach(function (g) {
      h += '<h4>' + esc(g.t) + '</h4>';
      if (g.score) h += '<ol class="gv-score">' + g.rows.map(function (r) { return '<li><span>' + esc(r.n) + ' <span class="muted">' + esc(r.c) + (r.k ? ' · ' + esc(r.k) : '') + '</span></span><b>' + esc(r.s || '–') + '</b></li>'; }).join('') + '</ol>';
      else h += '<div class="gv-matches">' + g.matches.map(function (m) {
        return '<div class="gv-m' + (m.w ? ' done' : '') + '"><span class="gv-mno">' + esc(m.no) + '</span>' +
          '<span class="gv-mp' + (m.w === 'a' ? ' won' : '') + '"><b class="aka-t">' + esc(m.a || '…') + '</b> <small>' + esc(m.ac) + '</small></span>' +
          '<span class="gv-ms">' + esc(m.s || (m.w ? '–' : 'vs')) + '</span>' +
          '<span class="gv-mp' + (m.w === 'b' ? ' won' : '') + '"><b>' + esc(m.b || '…') + '</b> <small>' + esc(m.bc) + '</small></span></div>';
      }).join('') + '</div>';
    });
    return h;
  }
  // Tinklai - medis: ratai stulpeliais (ketvirtfinalis → pusfinalis → finalas), laimėtojas paryškintas
  function treeHtml(b) {
    var rounds = (b.groups || []).filter(function (g) { return g.round !== undefined; });
    if (!rounds.length) return bracketHtml(b);
    var rest = { places: b.places, groups: (b.groups || []).filter(function (g) { return g.round === undefined; }) };
    var side = function (m, k) { var won = m.w === k; return '<div class="tk-p ' + (k === 'a' ? 'aka' : 'shiro') + (won ? ' won' : m.w ? ' lost' : '') + '"><b>' + esc(m[k] || '…') + '</b><small>' + esc(m[k + 'c']) + '</small>' + (won ? '<i>✓</i>' : '') + '</div>'; };
    var h = '<div class="tk-tree">' + rounds.map(function (g, i) {
      return '<div class="tk-col' + (i === rounds.length - 1 ? ' last' : '') + '"><h4>' + esc(g.t) + '</h4><div class="tk-col-body">' + g.matches.map(function (m) {
        return '<div class="tk-m' + (m.w ? ' done' : '') + '"><span class="tk-no">' + esc(m.no) + (m.s ? ' · ' + esc(m.s) : '') + '</span>' + side(m, 'a') + side(m, 'b') + '</div>';
      }).join('') + '</div></div>';
    }).join('') + '</div>';
    return (rest.places && rest.places.length ? bracketHtml({ places: rest.places }) : '') + h + bracketHtml({ groups: rest.groups });
  }
  function renderDraws() {
    var groups = drawGroups(), cur = groups.find(function (g) { return g.key === tkGroup; }) || groups[0];
    document.getElementById('tk-groups').innerHTML = groups.map(function (g) {
      var st = g.cats.every(function (c) { return catFinished(c); }) ? 'fin' : g.cats.some(function (c) { return catStatus(c) === 'run'; }) ? 'run' : 'wait', n = 0;
      g.cats.forEach(function (c) { n += c.competitors.length; });
      return '<button type="button" class="tk-g' + (cur && g.key === cur.key ? ' on' : '') + '" data-tk="' + esc(g.key) + '"><b>' + esc(g.name) + '</b><span class="tk-meta">' +
        g.cats.map(function (c) { return '<span class="disc d-' + c.discipline + '"></span>'; }).join('') + ' ' + g.cats.length + ' rungt. · ' + n + ' dalyv.</span>' + stChip(st) + '</button>';
    }).join('') || '<p class="muted small">Kategorijų su dalyviais dar nėra.</p>';
    document.getElementById('tk-view').innerHTML = cur ? '<h2 class="tk-title">' + esc(cur.name) + ' <span class="muted small">' + cur.cats.length + ' rungt.</span></h2><div class="tk-cats">' + cur.cats.map(function (c) {
      var a = areaOf(c);
      // #170: kategorijos pavadinimas irgi atidaro valdymą (kaip „Valdyti →“)
      return '<section class="panel tk-cat"><div class="tk-cat-head"><span class="disc d-' + c.discipline + '"></span>' +
        (CFG.readOnly ? '<b>' + esc(c.name) + '</b>' : '<button type="button" class="tk-cat-name" data-tk-open="' + c.id + '" title="Atidaryti Live tvarkaraštyje">' + esc(c.name) + '</button>') + '<span class="muted small">' + (a ? esc(a.name) + ' · ' : '') + c.competitors.length + ' dalyv.</span>' +
        '<span class="spacer"></span>' + stChip(catStatus(c)) + (CFG.readOnly ? '' : '<button type="button" class="btn btn-xs" data-tk-open="' + c.id + '" title="Atidaryti Live tvarkaraštyje">Valdyti →</button>') + '</div>' +
        '<div class="tk-body">' + treeHtml(catBracket(c, true)) + '</div></section>';
    }).join('') + '</div>' : '';
  }
  document.getElementById('v-tinklai').addEventListener('click', function (e) {
    var g = e.target.closest('[data-tk]');
    if (g) { tkGroup = g.dataset.tk; try { sessionStorage.setItem(KEY + '-tk', tkGroup); } catch (x) {} renderDraws(); if (window.innerWidth < 900) document.getElementById('tk-view').scrollIntoView({ block: 'start' }); return; }
    var o = e.target.closest('[data-tk-open]'); if (o) openRun(o.dataset.tkOpen);
  });
  // ---------- 🎓 Diplomai: šablono įkėlimas (bendras visoms varžyboms) ir atsisiuntimas / spausdinimas ----------
  function renderDiplomaMenu() {
    var box = document.getElementById('dipl-body'); if (!box) return;
    var tpl = CFG.diplomaTpl, n = 0;
    results().forEach(function (r) { n += r.places.length; });
    box.innerHTML = '<b>🎓 Diplomai prizininkams</b>' +
      (n ? '<p class="small muted">Prizininkų dabar: <b>' + n + '</b>. Diplomai - ir prie kiekvienos kategorijos „Vietos“.</p>' : '<p class="small muted">Prizininkų dar nėra - diplomai atsiras, kai kategorijos bus baigtos.</p>') +
      (tpl ? '<a class="btn btn-primary btn-sm" href="' + esc(diplomaUrl('', false)) + '" target="_blank" rel="noopener">⬇ Excel pagal šabloną (visi)</a>' : '') +
      '<a class="btn btn-sm" href="' + esc(diplomaUrl('', true)) + '" target="_blank" rel="noopener">🖨 Spausdinti paprastus (visi)</a>' +
      '<div class="dipl-tpl"><b class="small">Jūsų diplomo šablonas (Excel)</b>' +
      (tpl ? '<p class="small">📄 ' + esc(tpl.name) + ' <span class="muted">· ' + esc(tpl.at) + '</span>' + (tpl.keys && tpl.keys.length ? '<br><span class="muted">Rastos žymos: ' + esc(tpl.keys.join(' ')) + '</span>' : '<br><b class="warn-t">Žymų nerasta</b> - įrašykite jas į langelius (žr. žemiau).') + '</p>' : '<p class="small muted">Neįkeltas - spausdinami paprasti diplomai.</p>') +
      '<p class="small muted">Excel faile (pirmas lapas - vienas diplomas su jūsų dizainu) į tuščias vietas įrašykite: <code>{vardas}</code> <code>{vieta}</code> (1, 2, 3) <code>{vietaR}</code> (I, II, III) <code>{kategorija}</code> <code>{klubas}</code> <code>{varzybos}</code> <code>{data}</code> <code>{rungtis}</code>. ' +
      'Atsisiuntus - kiekvienas diplomas atskirame lape, spausdinkite visus iš karto.</p>' +
      '<div class="sp-row"><label class="btn btn-sm dipl-up">' + (tpl ? '↻ Pakeisti šabloną' : '⬆ Įkelti šabloną (.xlsx)') + '<input type="file" id="dipl-file" accept=".xlsx" hidden></label>' +
      (tpl ? '<button type="button" class="btn btn-sm btn-danger" id="dipl-del">Ištrinti šabloną</button>' : '') + '</div><span class="small" id="dipl-msg" role="status"></span></div>';
  }
  var diplMenu = document.getElementById('dipl-menu');
  if (diplMenu) {
    diplMenu.addEventListener('toggle', function () { if (diplMenu.open) renderDiplomaMenu(); });
    diplMenu.addEventListener('change', function (e) {
      if (e.target.id !== 'dipl-file' || !e.target.files[0]) return;
      var fd = new FormData(); fd.append('_csrf', CFG.csrf); fd.append('file', e.target.files[0]);
      document.getElementById('dipl-msg').textContent = 'Įkeliama…';
      fetch(CFG.diplomaApi, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok) { document.getElementById('dipl-msg').textContent = d.error || 'Nepavyko'; return; }
        CFG.diplomaTpl = d.tpl; renderDiplomaMenu(); toast('Diplomo šablonas įkeltas ✓');
      }).catch(function () { document.getElementById('dipl-msg').textContent = 'Nepavyko - patikrinkite ryšį.'; });
    });
    diplMenu.addEventListener('click', function (e) {
      if (e.target.id !== 'dipl-del' || !confirm('Ištrinti diplomo šabloną? Bus spausdinami paprasti diplomai.')) return;
      var fd = new FormData(); fd.append('_csrf', CFG.csrf); fd.append('action', 'delete');
      fetch(CFG.diplomaApi, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
        if (d.ok) { CFG.diplomaTpl = null; renderDiplomaMenu(); } else document.getElementById('dipl-msg').textContent = d.error || 'Nepavyko';
      });
    });
  }
  // Žiūrovams ir ekranams: ant kiekvieno tatamio - kategorijos su laikais ir kas eina toliau (vaikų vardai sutrumpinti)
  function upcoming(c) {
    if (!c.draw || drawStale(c) || c.competitors.length < 2) return [];
    if (c.draw.system === 'score') {
      var r1 = (c.draw.order || []).filter(function (id) { return person(c, id) && scoreTotal(c, (c.draw.scores || {})[id]) === null; })
        .map(function (id) { return { title: cfg(c).rounds === 2 ? 'Pusfinalis' : 'Pasirodymas', a: id, b: null, which: 'scores' }; });
      if (r1.length || cfg(c).rounds !== 2) return r1;
      return scoreFinalists(c).filter(function (id) { return scoreTotal(c, (c.draw.final || {})[id]) === null; })
        .map(function (id) { return { title: 'Finalas', a: id, b: null, which: 'final' }; });
    }
    var list = c.draw.system === 'rr' ? rrMatches(c) : allMatches(bracket(c));
    return list.filter(function (m) { return m.a && m.b && !m.winner; }).sort(function (x, y) { return (x.no || 0) - (y.no || 0); });
  }
  // 📹 YouTube nuoroda → įterpimo adresas (vaizdo įrašas / transliacija arba kanalo tiesioginė transliacija); kita - ''
  function ytEmbed(u) {
    u = String(u || '').trim(); if (!u) return '';
    var m = u.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|live\/|embed\/|shorts\/))([A-Za-z0-9_-]{11})/);
    if (m) return 'https://www.youtube-nocookie.com/embed/' + m[1] + '?autoplay=1&mute=1&playsinline=1';
    m = u.match(/youtube\.com\/channel\/(UC[A-Za-z0-9_-]{22})/);
    return m ? 'https://www.youtube.com/embed/live_stream?channel=' + m[1] + '&autoplay=1&mute=1' : '';
  }
  function areasSummary() {
    return S.areas.map(function (a) {
      var t = toMin(a.start), cats = [], next = [], cur = false;
      S.categories.filter(function (c) { return c.tatami === a.id; }).forEach(function (c) {
        if (c.at) t = Math.max(t, toMin(c.at));
        var start = t, total = matchCount(c), done = Math.min(doneCount(c), total); t += minutesFor(c);
        // Dabartinė grupė - pirma nebaigta tatamio eilėje (ekrane ji didesnė)
        var isCur = !cur && total > 0 && done < total; if (isCur) cur = true;
        cats.push({ id: c.id, name: c.name, disc: c.discipline, start: hhmm(start), done: done, total: total, cur: isCur });
        upcoming(c).slice(0, Math.max(0, 6 - next.length)).forEach(function (m) {
          next.push({ catId: c.id, cat: c.name, disc: c.discipline, no: m.no || '', title: m.title || matchTitle(c, m), a: pubName(m.a), ac: (person(c, m.a) || {}).club || '',
            b: m.b ? pubName(m.b) : '', bc: m.b ? (person(c, m.b) || {}).club || '' : '' });
        });
      });
      return { id: a.id, name: a.name, end: hhmm(t), cats: cats, next: next, video: ytEmbed(a.stream) };
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
  // #122 Apdovanojimai rezultatuose: viršuje - ką tik baigtos ir dar neapdovanotos rungtys (rezultatai suskaičiuoti patys),
  // „✓ Apdovanota“ vienu paspaudimu nukelia žemyn; žemiau - kas jau apdovanota (klaidoms atsekti), „↺ Grąžinti“
  var awDoneOpen = false;
  function awTime(ts) { var m = String(ts || '').match(/(\d{2}):(\d{2})/); return m ? m[1] + ':' + m[2] : ''; }
  function renderAwardQueue() {
    var box = document.getElementById('award-view'); if (!box) return;
    var cats = S.categories.filter(function (c) { return c.competitors.length; });
    var at = function (c, k) { return (AW[c.id] && AW[c.id][k]) || ''; };
    var todo = cats.filter(function (c) { var st = catStatus(c); return st === 'sent' || st === 'fin'; })
      .sort(function (a, b) { var x = at(a, 'sent') || '9', y = at(b, 'sent') || '9'; return x < y ? -1 : x > y ? 1 : 0; });
    var done = cats.filter(function (c) { return catStatus(c) === 'awarded'; })
      .sort(function (a, b) { var x = at(a, 'done'), y = at(b, 'done'); return x > y ? -1 : x < y ? 1 : 0; });
    var card = function (c) {
      var t = awTime(at(c, 'sent'));
      return '<div class="aw-card"><div class="aw-top"><span class="disc d-' + c.discipline + '"></span><b>' + esc(c.name) + '</b>' +
        '<span class="muted small">' + esc((areaOf(c) || {}).name || '') + (t ? ' · baigta ' + t : '') + '</span>' +
        '<button type="button" class="btn btn-xs" data-res-open="' + c.id + '" title="Atidaryti lentelę - patikrinti">Lentelė</button></div>' +
        '<div class="aw-places">' + (placesShort(c) || '<span class="muted small">Prizininkų nėra</span>') + '</div>' +
        '<button type="button" class="btn btn-primary aw-done-btn" data-award-done="' + c.id + '">✓ Apdovanota</button></div>';
    };
    var html = '<div class="aw-head"><h2>🏅 Apdovanoti dabar' + (todo.length ? ' <span class="aw-n">' + todo.length + '</span>' : '') + '</h2>' +
      '<span class="muted small">Baigta rungtis atsiranda čia pati - rezultatai jau suskaičiuoti. Pirmiausia - anksčiausiai baigtos.</span></div>' +
      (todo.length ? '<div class="aw-grid">' + todo.map(card).join('') + '</div>' : '<p class="muted aw-empty">Kol kas nėra ką apdovanoti - kai rungtis baigsis, ji čia atsiras pati.</p>');
    if (done.length) {
      html += '<details class="aw-donebox"' + (awDoneOpen ? ' open' : '') + '><summary>✓ Jau apdovanota (' + done.length + ') - patikrinti, ar viskas teisingai</summary><ul class="aw-donelist">' +
        done.map(function (c) {
          return '<li><span class="disc d-' + c.discipline + '"></span><b>' + esc(c.name) + '</b><span class="aw-dl-pl">' + placesShort(c) + '</span>' +
            '<span class="muted small">' + awTime(at(c, 'done')) + '</span><button type="button" class="btn btn-xs" data-award-back="' + c.id + '" title="Grąžinti į „Apdovanoti dabar“">↺ Grąžinti</button></li>';
        }).join('') + '</ul></details>';
    }
    box.innerHTML = html;
    var db = box.querySelector('.aw-donebox'); if (db) db.addEventListener('toggle', function () { awDoneOpen = db.open; });
  }
  if (document.getElementById('award-view')) document.getElementById('award-view').addEventListener('click', function (e) {
    var d = e.target.closest('[data-award-done]'); if (d) { setAward([d.dataset.awardDone], 'done'); return; }
    var bk = e.target.closest('[data-award-back]'); if (bk) { setAward([bk.dataset.awardBack], 'sent'); return; }
    var o = e.target.closest('[data-res-open]'); if (o) openRun(o.dataset.resOpen);
  });
  function renderResults() {
    renderAwardQueue();
    var sr = shownResults(), rs = sr.list, table = medalTable(rs), sum = { g: 0, s: 0, b: 0, pts: 0 };
    document.getElementById('res-count').textContent = 'Baigta ' + sr.all.filter(function (r) { return r.finished; }).length + ' iš ' + sr.all.length + ' kategorijų';
    document.getElementById('medals-view').innerHTML = table.length ? '<table><thead><tr><th class="num">#</th><th>Klubas</th><th class="num">' + MEDAL[1] + '</th><th class="num">' + MEDAL[2] + '</th><th class="num">' + MEDAL[3] + '</th><th class="num">Taškai</th></tr></thead><tbody>' +
      table.map(function (t, i) {
        ['g', 's', 'b', 'pts'].forEach(function (k) { sum[k] += t.m[k]; });
        return '<tr><td class="num">' + (i + 1) + '</td><td>' + esc(t.club) + '</td><td class="num">' + t.m.g + '</td><td class="num">' + t.m.s + '</td><td class="num">' + t.m.b + '</td><td class="num"><b>' + t.m.pts + '</b></td></tr>';
      }).join('') + '</tbody><tfoot><tr><td></td><td>Iš viso</td><td class="num">' + sum.g + '</td><td class="num">' + sum.s + '</td><td class="num">' + sum.b + '</td><td class="num">' + sum.pts + '</td></tr></tfoot></table>'
      : '<p class="muted" style="padding:12px 14px;margin:0;">Dar nėra prizininkų. Pažymėkite kovų nugalėtojus skirtuke „Lentelės“.</p>';
    document.getElementById('podium-view').innerHTML = rs.length ? '<div class="res-cats">' + rs.map(function (r) {
      var ast = AW[r.c.id] && AW[r.c.id].st;
      var state = (ast === 'done' ? stChip('awarded') : ast === 'sent' ? stChip('sent') : r.finished ? '<span class="pill ok">Baigta</span><button type="button" class="btn btn-award btn-xs" data-award-send="' + r.c.id + '" title="Siųsti apdovanojimams">🏅 Siųsti</button>' : '') +
        (r.finished || ast ? '' : r.done ? '<span class="pill warn">Vyksta ' + r.done + '/' + r.total + '</span>' : '<span class="pill">Nepradėta</span>');
      return '<div class="res-cat"><div class="res-cat-top"><button type="button" data-res-open="' + r.c.id + '" title="Atidaryti lentelę"><span class="disc d-' + r.c.discipline + '"></span>' + esc(r.c.name) + '</button><span class="res-cat-st">' + state + '</span></div>' +
        (r.places.length ? r.places.map(function (p) { return '<div class="rp">' + MEDAL[p.place] + '<span>' + esc(p.name) + ' <span class="club">' + esc(p.club) + '</span></span></div>'; }).join('') : '<span class="muted small">Prizininkų dar nėra</span>') + '</div>';
    }).join('') + '</div>' : '<p class="muted" style="padding:12px 14px;margin:0;">Nėra baigtų kategorijų.</p>';
  }
  document.getElementById('res-done').addEventListener('change', renderResults);
  document.getElementById('podium-view').addEventListener('click', function (e) {
    var as = e.target.closest('[data-award-send]'); if (as) { setAward([as.dataset.awardSend], 'sent'); return; }
    var b = e.target.closest('[data-res-open]'); if (!b) return;
    openRun(b.dataset.resOpen);
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
  var BELT_GROUPS = [[0, 3], [4, 6], [7, 99]];
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
      var bl = hasBelt(p.belt) ? p.belt : 1, bi = BELT_GROUPS.findIndex(function (g) { return bl >= g[0] && bl <= g[1]; });
      var g = a < 8 ? 'X' : (p.gender === 'F' ? 'F' : 'M');
      var discs = a < 8 ? ['kihon', 'kata'] : ['kata', 'kumite'];
      if (a >= 16 && bl >= 7) discs.push('fukugo');
      if (p.discs && p.discs.length) discs = p.discs.slice();   // pats pasirinko registruodamasis
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
      // Trūkstami teisėjų balai užpildomi (jau įrašyti lieka)
      var fill = function (src, id) { var arr = src[id] = src[id] || []; for (j = 0; j < f.judges; j++) if (arr[j] === undefined || arr[j] === null || isNaN(parseFloat(arr[j]))) arr[j] = rnd(); };
      c.draw.scores = c.draw.scores || {};
      ids.forEach(function (id) { fill(c.draw.scores, id); });
      if (f.rounds === 2 && limit >= ids.length) { c.draw.final = c.draw.final || {}; scoreFinalists(c).forEach(function (id) { fill(c.draw.final, id); }); }
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
        beltDot(m.belt) + '<span class="mp-name">' + esc(m.name) + '</span><span class="mp-meta">' + (m.birth || '?') + ' · ' + (m.gender || '?') + (hasBelt(m.belt) ? ' · ' + esc(beltName(m.belt)) : '') + (added ? ' · jau pridėtas' : '') + '</span></label>';
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
      addMemberPeople(ids);
      toastPlaced(ids.map(function (x) { return 'm' + x; }));
      save(); render(); renderMembersPick();
    }
  });
  document.getElementById('members-pick').addEventListener('input', function (e) { if (e.target.id === 'mp-search') renderMembersPick(); });

  // ---------- Išvaizda: tema ir meniu (pavadinimai, tvarka) - bendri visoms varžyboms ----------
  var UI = CFG.ui || { theme: 'light', menu: { order: [], names: {} } };
  var TAB_DEFAULT = {}; tabs.concat(['admin']).forEach(function (t) { var b = document.getElementById('tab-' + t); if (b) TAB_DEFAULT[t] = b.lastChild.textContent; });
  function postUi(data) {
    return fetch(CFG.uiApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf, Accept: 'application/json' }, body: JSON.stringify(data) })
      .then(function (r) { return r.json(); }).then(function (d) { if (d.ui) UI = d.ui; return d; });
  }
  function applyMenu() {
    var nav = document.querySelector('.tabs'), sep = nav.querySelector('.tabs-sep'), cfgBtn = document.getElementById('menu-cfg');
    var order = menuOrder(UI.menu && UI.menu.order);
    order.forEach(function (t) {
      var b = document.getElementById('tab-' + t); if (!b) return;
      if (t === 'admin' && order === TOP) nav.insertBefore(sep, cfgBtn);
      nav.insertBefore(b, cfgBtn);
    });
    tabs.concat(['admin']).forEach(function (t) { var b = document.getElementById('tab-' + t); if (b) b.lastChild.textContent = (UI.menu && UI.menu.names && UI.menu.names[t]) || TAB_DEFAULT[t]; });
    sep.hidden = order !== TOP;
  }
  applyMenu();
  var themePick = document.getElementById('theme-pick');
  if (themePick) themePick.addEventListener('change', function () { document.body.dataset.theme = themePick.value; postUi({ theme: themePick.value }); });
  var menuBtn = document.getElementById('menu-cfg'), menuPanel = document.getElementById('menu-panel'), menuDraft = null;
  function renderMenuPanel() {
    document.getElementById('menu-list').innerHTML = menuDraft.order.map(function (t, i) {
      return '<div class="menu-row"><span class="muted small">' + (i + 1) + '</span><input class="sel" data-menu-name="' + t + '" value="' + esc(menuDraft.names[t] || TAB_DEFAULT[t]) + '" aria-label="Pavadinimas: ' + esc(TAB_DEFAULT[t]) + '">' +
        '<button type="button" class="btn btn-xs" data-menu-up="' + i + '"' + (i ? '' : ' disabled') + ' aria-label="Aukštyn">↑</button><button type="button" class="btn btn-xs" data-menu-down="' + i + '"' + (i < menuDraft.order.length - 1 ? '' : ' disabled') + ' aria-label="Žemyn">↓</button>' +
        '<span class="muted small">' + esc(TAB_DEFAULT[t]) + '</span></div>';
    }).join('');
  }
  if (menuBtn) {
    menuBtn.addEventListener('click', function () {
      menuPanel.hidden = !menuPanel.hidden; menuBtn.setAttribute('aria-expanded', !menuPanel.hidden);
      if (!menuPanel.hidden) {
        menuDraft = { order: menuOrder(UI.menu.order).slice(), names: Object.assign({}, UI.menu.names || {}) };
        renderMenuPanel(); window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
    menuPanel.addEventListener('input', function (e) { var k = e.target.dataset.menuName; if (k) menuDraft.names[k] = e.target.value; });
    menuPanel.addEventListener('click', function (e) {
      var t = e.target.closest('button'); if (!t) return;
      var o = menuDraft.order, i;
      if (t.dataset.menuUp) { i = +t.dataset.menuUp; o.splice(i - 1, 0, o.splice(i, 1)[0]); renderMenuPanel(); }
      else if (t.dataset.menuDown) { i = +t.dataset.menuDown; o.splice(i + 1, 0, o.splice(i, 1)[0]); renderMenuPanel(); }
      else if (t.id === 'menu-reset') { menuDraft = { order: TOP.slice(), names: {} }; renderMenuPanel(); }
      else if (t.id === 'menu-save') {
        var names = {}; Object.keys(menuDraft.names).forEach(function (k) { var v = String(menuDraft.names[k]).trim(); if (v && v !== TAB_DEFAULT[k]) names[k] = v; });
        var same = menuDraft.order.join() === TOP.join();
        postUi({ menu: { order: same ? [] : menuDraft.order, names: names } }).then(function () { applyMenu(); menuPanel.hidden = true; toast('Meniu išsaugotas.'); });
      }
    });
  }

  // ---------- Istorija serveryje: „kaip prieš 5 min.“ ar bet kuri kopija ----------
  var histBtn = document.getElementById('hist-toggle'), histPanel = document.getElementById('hist-panel'), histList = [];
  function agoText(at, now) {
    var m = Math.round((new Date(now.replace(' ', 'T')) - new Date(at.replace(' ', 'T'))) / 60000);
    return m < 1 ? 'ką tik' : m < 60 ? 'prieš ' + m + ' min.' : m < 1440 ? 'prieš ' + Math.floor(m / 60) + ' val. ' + (m % 60) + ' min.' : at.slice(0, 16);
  }
  function loadHistory() {
    var box = document.getElementById('hist-list');
    fetch(CFG.historyApi, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      histList = (d.list || []).map(function (x) { x.ago = agoText(x.at, d.now); x.min = Math.round((new Date(d.now.replace(' ', 'T')) - new Date(x.at.replace(' ', 'T'))) / 60000); return x; });
      box.innerHTML = histList.length ? '<table class="hist-table"><tbody>' + histList.map(function (x) {
        return '<tr><td><b>' + x.at.slice(11, 16) + '</b> <span class="muted small">' + esc(x.ago) + '</span></td><td class="small">' + x.cats + ' kat., baigta ' + x.done + (x.note ? ' · <i>' + esc(x.note) + '</i>' : '') + '</td>' +
          '<td><button type="button" class="btn btn-xs" data-hist-id="' + x.id + '">Atkurti</button></td></tr>';
      }).join('') + '</tbody></table>' : '<p class="muted small">Kopijų dar nėra - jos atsiranda, kai kas nors pakeičiama.</p>';
    }).catch(function () { box.innerHTML = '<p class="muted small">Nepavyko įkelti istorijos.</p>'; });
  }
  function restoreSnap(id, label) {
    if (!confirm('Atkurti varžybas, kaip buvo ' + label + '? Dabartinė būsena bus išsaugota istorijoje.')) return;
    flush();
    fetch(CFG.restoreApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf, Accept: 'application/json' }, body: JSON.stringify({ id: id }) })
      .then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok) { toast(esc(d.error || 'Nepavyko atkurti.')); return; }
        var sel = S.selected; CFG.state = d.state; load(); if (cat(sel)) S.selected = sel;
        version = d.version; dirty = false; conflict = false; markSaved(stateObj());
        var cf = document.getElementById('conflict'); if (cf) cf.hidden = true;
        if (stateCh) { try { stateCh.postMessage({ v: version, state: d.state }); } catch (e) {} }
        setStatus('Atkurta ✓', 'ok'); toast('Atkurta: <b>' + esc(label) + '</b>. Jei ne tai - atkurkite kitą kopiją (ir „Prieš atkūrimą“).');
        histPanel.hidden = true; render();
      }).catch(function () { toast('Nepavyko atkurti - nėra ryšio.'); });
  }
  if (histBtn) {
    histBtn.addEventListener('click', function () { histPanel.hidden = !histPanel.hidden; histBtn.setAttribute('aria-expanded', !histPanel.hidden); if (!histPanel.hidden) { loadHistory(); window.scrollTo({ top: 0, behavior: 'smooth' }); } });
    histPanel.addEventListener('click', function (e) {
      var t = e.target.closest('button'); if (!t) return;
      if (t.dataset.histId) { var x = histList.find(function (h) { return h.id === +t.dataset.histId; }); restoreSnap(+t.dataset.histId, x ? x.at.slice(11, 16) + ' (' + x.ago + ')' : ''); }
      else if (t.dataset.histAgo) {
        var need = +t.dataset.histAgo, pick = histList.find(function (h) { return h.min >= need; });
        if (!pick) { toast('Tokios senos kopijos dar nėra.'); return; }
        restoreSnap(pick.id, pick.at.slice(11, 16) + ' (' + pick.ago + ')');
      }
    });
  }

  // ---------- Rungties atstatymas: visa arba nuo pasirinkto rato (vėlesni rezultatai ištrinami) ----------
  function fillReset(c) {
    var box = document.getElementById('draw-reset'); if (!box) return;
    var opt = function (v, t) { return '<button type="button" class="btn btn-sm" data-reset="' + v + '">' + t + '</button>'; };
    var html = opt('all', 'Visą rungtį (visi rezultatai)');
    if (c && c.draw && isElim(c.draw.system)) {
      var b = bracket(c);
      b.rounds.forEach(function (rd, i) { if (i > 0) html += opt('r' + i, 'Nuo: ' + esc(roundName(i, b.R))); });
      if (b.rep.length) html += opt('rep', 'Tik paguoda / dėl 3 vietos');
    }
    box.innerHTML = html;
  }
  document.getElementById('draw-reset').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-reset]'); if (!btn) return;
    document.getElementById('draw-reset-menu').open = false;
    var c = cat(S.selected), v = btn.dataset.reset; if (!c || !c.draw || !v) return;
    if (v === 'all') { if (!confirm('Atstatyti visą rungtį „' + c.name + '“ - ištrinti visus rezultatus ir balus?')) return; snapshot(); c.draw.results = {}; c.draw.scores = {}; c.draw.final = {}; }
    else {
      var b = bracket(c), ids = {};
      if (v === 'rep') b.rep.forEach(function (g) { g.matches.forEach(function (m) { ids[m.id] = 1; }); });
      else { var from = +v.slice(1); b.rounds.forEach(function (rd, i) { if (i >= from) rd.forEach(function (m) { ids[m.id] = 1; }); }); b.rep.forEach(function (g) { g.matches.forEach(function (m) { ids[m.id] = 1; }); }); }
      if (!confirm('Ištrinti ' + Object.keys(ids).filter(function (k) { return c.draw.results[k]; }).length + ' kovų rezultatus nuo pasirinkto rato?')) return;
      snapshot();
      Object.keys(ids).forEach(function (k) { delete c.draw.results[k]; if (c.draw.scores) delete c.draw.scores[k]; });
      prune(c);
    }
    clearAward(c);   // rezultatai ištrinti - nebe „apdovanota“
    save(); render();
  });

  // ---------- Transliacija ir registracija: nuorodos, ekranai ----------
  var shareBtn = document.getElementById('share-toggle'), sharePanel = document.getElementById('share-panel');
  function renderScreens() {
    var box = document.getElementById('sp-screens'); if (!box) return;
    box.innerHTML = S.areas.map(function (a) { return '<button type="button" class="btn" data-screen="' + a.id + '">📺 ' + esc(a.name) + '</button>'; }).join('');
  }
  if (shareBtn) {
    shareBtn.addEventListener('click', function () { sharePanel.hidden = !sharePanel.hidden; shareBtn.setAttribute('aria-expanded', sharePanel.hidden ? 'false' : 'true'); renderScreens(); if (!sharePanel.hidden) window.scrollTo({ top: 0, behavior: 'smooth' }); });
    sharePanel.addEventListener('click', function (e) {
      var t = e.target.closest('button'); if (!t) return;
      if (t.dataset.screen) { window.open(CFG.screenUrl + '&tatami=' + t.dataset.screen, 'taika-ekranas-' + t.dataset.screen); return; }
      if (t.dataset.copy) {
        var done = function () { var o = t.textContent; t.textContent = 'Nukopijuota ✓'; setTimeout(function () { t.textContent = o; }, 1500); };
        if (navigator.clipboard) navigator.clipboard.writeText(t.dataset.copy).then(done, function () { t.previousElementSibling.select(); });
        else { t.previousElementSibling.select(); document.execCommand('copy'); done(); }
      }
    });
    var ss = document.getElementById('stream-save');
    if (ss) ss.addEventListener('click', function () {
      fetch(CFG.regApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf },
        body: JSON.stringify({ stream_url: document.getElementById('stream-url').value, stream_at: document.getElementById('stream-at').value }) })
        .then(function (r) { return r.json(); }).then(function (d) { setStatus(d.ok ? 'Transliacija išsaugota ✓' : (d.error || 'Nepavyko'), d.ok ? 'ok' : 'err'); });
    });
    document.getElementById('reg-open').addEventListener('change', function (e) {
      fetch(CFG.regApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf }, body: JSON.stringify({ open: e.target.checked }) })
        .then(function () { setStatus(e.target.checked ? 'Registracija atidaryta ✓' : 'Registracija uždaryta', 'ok'); });
    });
  }
  // Užsiregistravę per nuorodą: Denis peržiūri ir prideda (arba atmeta)
  var entries = CFG.entries || [];
  function entryPersonId(en) { return 'r' + en.id; }
  function entryDiscs(en) { return String(en.disciplines || '').split(',').filter(function (d) { return INDIV.indexOf(d) !== -1; }); }
  function renderEntries() {
    var box = document.getElementById('entries-box'); if (!box) return;
    box.hidden = !entries.length && !CFG.regUrl;
    if (box.hidden) return;
    box.classList.toggle('compact', !entries.length);
    if (!entries.length) {
      box.innerHTML = '<div class="en-empty"><span><b>Užsiregistravę per nuorodą:</b> <span class="muted">naujų nėra</span></span><button type="button" class="btn btn-sm" id="en-refresh" title="Patikrinti, ar yra naujų">↻ Atnaujinti</button></div>';
      return;
    }
    var byClub = {};
    entries.forEach(function (en) { (byClub[en.club] = byClub[en.club] || []).push(en); });
    box.innerHTML = '<h2>Užsiregistravę per nuorodą' + (entries.length ? ' <span class="pill warn">' + entries.length + ' nauji</span>' : '') + '<span class="spacer"></span>' +
      '<button type="button" class="btn" id="en-refresh" title="Patikrinti, ar yra naujų">↻ Atnaujinti</button>' + (entries.length ? '<button type="button" class="btn btn-primary" id="en-add-all">Pridėti visus (' + entries.length + ')</button>' : '') + '</h2>' +
      (entries.length ? '<div class="table-scroll"><table><thead><tr><th>Klubas</th><th>Vardas, pavardė</th><th class="num">Gim.</th><th>Lytis</th><th>Diržas</th><th>Rungtys</th><th>Nuotraukos</th><th>Tėvų tel.</th><th>Kontaktas</th><th></th></tr></thead><tbody>' +
        Object.keys(byClub).sort().map(function (k) {
          return byClub[k].map(function (en) {
            return '<tr><td>' + esc(en.club) + '</td><td><b>' + esc(en.name) + '</b></td><td class="num">' + (en.birth_year || '') + '</td><td>' + esc(en.gender || '') + '</td><td>' + beltDot(+en.belt) + esc(beltName(+en.belt)) + '</td>' +
              '<td class="small">' + esc(entryDiscs(en).map(function (d) { return DISC[d] || d; }).join(', ')) + '</td>' +
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
      if (!dup) S.people[entryPersonId(en)] = { id: entryPersonId(en), name: en.name, club: en.club, birth: +en.birth_year || null, gender: en.gender || null, belt: en.belt === null || en.belt === undefined || en.belt === '' ? null : +en.belt, member: null,
        noPhoto: en.photo_ok !== null && en.photo_ok !== undefined && !+en.photo_ok };
      if (!dup && entryDiscs(en).length) S.people[entryPersonId(en)].discs = entryDiscs(en);   // registruojantis pasirinktos rungtys
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

  // Perpiešus vaizdas nebeperšoka į kairę / į pradžią / į viršų (telefone - balų lentelė, tinklas, AKA / SHIRO): išsaugoma ir grąžinama
  // kiekvieno slenkamo rėmelio ir lango padėtis
  var HSCROLL = '.table-scroll, .bracket-wrap, .tk-tree, .ov-cols, .sched, .rs-list, .rs-area, .cat-scroll, .cat-list, .pool-list, .mp-list';
  function scrollKey(el) { var host = el.parentElement && el.parentElement.closest('[id]'); return [host ? host.id : '', Array.prototype.indexOf.call((host || document).querySelectorAll(HSCROLL), el), el.className]; }
  function scrollSnap() {
    var o = { x: window.scrollX, y: window.scrollY, els: [] };
    document.querySelectorAll(HSCROLL).forEach(function (el) { if (el.scrollLeft || el.scrollTop) o.els.push({ k: scrollKey(el), l: el.scrollLeft, t: el.scrollTop }); });
    return o;
  }
  function scrollRestore(o) {
    o.els.forEach(function (s) {
      var host = s.k[0] ? document.getElementById(s.k[0]) : document, el = host && host.querySelectorAll(HSCROLL)[s.k[1]];
      if (el && el.className === s.k[2]) { el.scrollLeft = s.l; el.scrollTop = s.t; }
    });
    if (window.scrollX !== o.x || Math.abs(window.scrollY - o.y) > 1) window.scrollTo(o.x, o.y);
  }
  function render() { var sn = scrollSnap(); renderAll(); scrollRestore(sn); }
  function renderAll() {
    document.getElementById('t-name').textContent = S.tournament;
    var tab = curTab();
    if (tab === 'klubai') renderClubs(); else if (tab === 'dalyviai') { renderEntries(); renderPeople(); } else if (tab === 'komandos') renderTeams(); else if (tab === 'kategorijos') renderCats(); else if (tab === 'rezultatai') renderResults(); else if (tab === 'tinklai') renderDraws(); else renderLive();
    renderPins();
  }
  load(); markSaved(stateObj());
  (function restorePending() {
    var p = null;
    try { p = JSON.parse(localStorage.getItem(PEND) || 'null'); } catch (e) {}
    if (!p || !p.state || !p.snap || CFG.readOnly || !CFG.api) return;
    if (Date.now() - (p.t || 0) > 2 * 86400000) { dropPending(); return; }   // senesnė nei 2 d. - nebeaktuali
    // Mūsų paskutiniai pakeitimai (palyginus su tuo, kas tada buvo išsaugota) uždedami ant dabartinės serverio būsenos ir išsaugomi
    var server = stateObj(), before = JSON.stringify(server), serverCopy = JSON.parse(before);
    CFG.state = p.state; load(); savedSnap = p.snap;
    var merged = rebase(server);   // rebase keičia ir grąžina tą patį objektą - todėl palyginimui kopija
    CFG.state = merged; load(); markSaved(serverCopy);
    if (JSON.stringify(stateObj()) === before) { dropPending(); return; }   // viskas jau buvo išsaugota
    dirty = true; setStatus('Atkurti neišsaugoti pakeitimai - saugoma…', 'busy'); setTimeout(flush, 300);
  })();
  // Naujos test varžybos: kategorijos sudaromos ir dalis kovų „sužaidžiama“ pirmą kartą atidarius
  if (S.autoCats) { delete S.autoCats; autoCategories(); save(); }
  // Greitos varžybos: kuriant pažymėta „po vieną grupę visiems“ - grupė kiekvienai rungčiai su visais dalyviais
  if (Array.isArray(S.quickCats)) {
    var qc = S.quickCats; delete S.quickCats;
    qc.forEach(function (d) { if (!DISC[d]) return; var c = newCatIn(d, null); Object.keys(S.people).forEach(function (id) { addToCat(id, c.id); }); c.name = critName(c); });
    save(); render();
  }
  if (S.simulate) { var fr = +S.simulate; delete S.simulate; simulate(fr); save(); }
  var startTab = 'live';
  try { startTab = location.hash.replace('#', '') || sessionStorage.getItem(KEY + '-tab') || 'live'; } catch (e) {}
  if (startTab === 'lenteles') { setRun(true); startTab = 'live'; } else if (startTab === 'tvarkarastis') startTab = 'live';
  if (POPUP && cat(POPUP)) { S.selected = POPUP; runOpen = true; startTab = 'live'; document.title = cat(POPUP).name + ' · ' + document.title; }
  showTab(tabs.indexOf(startTab) >= 0 || startTab === 'admin' ? startTab : 'live', true);

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
})();
