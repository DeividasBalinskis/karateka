// Žaidimų arena: laukiamasis (kvietimai, botai, reitingai), karatė kova ėjimais ir „Greita kova“ realiu laiku.
(function () {
  var R = window.ARENA, root = document.getElementById('arena');
  if (!R || !root) return;
  var C = R.catalog, A = window.GameArt, T = window.tr || function (s) { return s; };
  window.GAME = { catalog: C };
  var me = R.hero;
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var $ = function (s, el) { return (el || root).querySelector(s); };
  var ZI = { jodan: '⬆️', chudan: '➡️', gedan: '⬇️', '': '🌀' };
  var ZN = { jodan: T('Galva'), chudan: T('Kūnas'), gedan: T('Kojos'), '': T('Išsisukti') };

  function api(data) {
    var post = !!data.action;
    var url = R.api + (post ? '' : ('?' + Object.keys(data).map(function (k) { return k + '=' + encodeURIComponent(data[k]); }).join('&') + '&h=' + me.id));
    if (post) data.h = me.id;
    return fetch(url, { method: post ? 'POST' : 'GET', credentials: 'same-origin', headers: post ? { 'Content-Type': 'application/json', 'X-CSRF-Token': R.csrf } : {}, body: post ? JSON.stringify(data) : undefined })
      .then(function (r) { return r.json(); }).then(function (d) { if (d && d.ok === false && d.error) toast(d.error); return d; })
      .catch(function () { return { ok: false }; });
  }
  function toast(msg) {
    var old = document.querySelector('.g-toast'); if (old) old.remove();
    var el = document.createElement('div'); el.className = 'g-toast'; el.innerHTML = '<span>' + msg + '</span>';
    document.body.appendChild(el); setTimeout(function () { el.remove(); }, 3800);
  }
  function heroSvg(art, cls) { return A.hero(art, { cls: cls || '' }); }
  function artOf(h) { return { species: h.species, gender: h.gender, look: h.look, equipped: h.equipped, belt: h.belt, name: h.name }; }
  function go(url) { location.href = url; }
  function matchUrl(id, game) { return (game === 'keturi' ? R.urls.keturiMatch : R.urls.match).replace('MID', id); }

  // =================== Laukiamasis ===================
  function lobby() {
    var html = '<div class="ar-head"><div class="ar-me live">' + heroSvg(artOf(me)) + '</div><div><h1 class="ar-title">🎮 ' + T('Žaidimai') + '</h1>' +
      '<div class="ar-sub">' + esc(me.publicName || me.name) + ' · ' + T('Lygis') + ' ' + me.level + ' · 🏆 ' + me.ratingPower + ' · 🎯 ' + me.ratingFair + '</div>' +
      (R.mine.length > 1 ? '<div class="dojo-heroes" style="margin-top:6px">' + R.mine.map(function (h) { return '<button type="button" data-me="' + h.id + '" class="' + (+h.id === +me.id ? 'active' : '') + '">' + esc(h.name) + '</button>'; }).join('') + '</div>' : '') +
      '</div><a class="g-btn" href="' + R.urls.dojo + '" style="margin-left:auto">🏯 ' + T('Mano dojo') + '</a></div>';
    html += '<div id="ar-inv"></div>';
    html += '<div class="ar-grid">';
    html += '<section class="g-card ar-modes"><h3>🥋 ' + T('Treniruotė su partneriu') + '</h3><p class="g-empty">' + T('Botas - treniruočių partneris. Reitingas nesikeičia, bet pergalės skaičiuojamos dovanai (3 per dieną).') + '</p>' +
      '<div class="ar-modes-grid">' +
      modeCard('power', '🏆', T('Jėgos kova'), T('Svarbūs lygis, savybės, daiktai ir gyvūnėlis. Stipresnis dažniau laimi, bet gudrumas irgi padeda.')) +
      modeCard('fair', '🎯', T('Gudrumo kova'), T('Visi lygūs: tos pačios gyvybės ir smūgiai, 3 kortos. Laimi tas, kuris geriau perpranta priešininką.')) +
      '</div><div class="ar-bots">' + ['easy', 'normal', 'hard'].map(function (l) { return '<div class="ar-bot"><b>' + { easy: '🐼 ' + T('Lengvas'), normal: '🐵 ' + T('Vidutinis'), hard: '🐲 ' + T('Sunkus') }[l] + '</b>' +
        '<button type="button" class="g-btn sm" data-bot="' + l + '" data-mode="power">🏆 ' + T('Jėgos') + '</button><button type="button" class="g-btn sm" data-bot="' + l + '" data-mode="fair">🎯 ' + T('Gudrumo') + '</button>' +
        '<button type="button" class="g-btn sm" data-bot="' + l + '" data-game="keturi">🔴 ' + T('Keturi') + '</button></div>'; }).join('') + '</div>' +
      '<a class="g-game greita" style="margin-top:14px" href="' + R.urls.greita + '"><span class="gi">' + A.icon('greita') + '</span><span>' + T('Greita kova') + '<small>' + T('Realiu laiku: matai, kur smūgiuos - blokuok laiku. Tobulas blokas = kontrataka!') + '</small></span></a>' +
      '<a class="g-game ritmas" style="margin-top:14px" href="' + R.urls.ritmas + '"><span class="gi">' + A.icon('ritmas') + '</span><span>' + T('Kata ritmas') + '<small>' + T('Muša būgnai - daryk techniką laiku') + '</small></span></a></section>';
    html += '<section class="g-card"><h3>🏅 ' + T('Reitingai') + '</h3><div class="ar-tops">' + topList('power', '🏆 ' + T('Jėgos')) + topList('fair', '🎯 ' + T('Gudrumo')) + '</div></section>';
    html += '</div>';
    html += '<section class="g-card" id="zaidejai" style="margin-top:12px"><div class="row" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px"><h3 style="margin:0">⚔️ ' + T('Iššūkis klubo draugams') + '</h3>' +
      '<input type="search" id="ar-q" placeholder="' + esc(T('Ieškoti vardo…')) + '" style="padding:7px 10px;border-radius:10px;border:1px solid rgba(23,20,15,.2);font:inherit;margin-left:auto"></div>' +
      '<p class="g-empty">' + T('Kvietimas galioja 3 minutes. Kai draugas priims - kova prasidės. Rašyti vieni kitiems negalima, bet kovos metu galima siųsti reakcijas 👏🔥.') + '</p><div class="g-heroes" id="ar-heroes"></div></section>';
    root.innerHTML = html;
    renderHeroes('');
    $('#ar-q').addEventListener('input', function () { renderHeroes(this.value); });
    root.addEventListener('click', lobbyClick);
    pollInvites();
  }
  function modeCard(k, ic, n, d) { return '<div class="ar-mode ' + k + '"><div class="ic">' + ic + '</div><b>' + n + '</b><span>' + d + '</span></div>'; }
  function topList(k, title) {
    var rows = (R.top && R.top[k]) || [];
    return '<div><div class="g-sub" style="margin-top:0">' + title + '</div>' + (rows.length ? '<ol class="ar-top">' + rows.map(function (r) { return '<li class="' + (+r.id === +me.id ? 'me' : '') + '"><a href="' + R.urls.view.replace('HID', r.id) + '">' + esc(r.name) + '</a><b>' + r.r + '</b><small>' + r.w + ':' + r.l + '</small></li>'; }).join('') + '</ol>' : '<p class="g-empty">' + T('Dar niekas nekovojo - būk pirmas!') + '</p>') + '</div>';
  }
  function renderHeroes(q) {
    q = (q || '').toLowerCase();
    var list = R.heroes.filter(function (h) { return !q || h.name.toLowerCase().indexOf(q) >= 0; }).slice(0, 60);
    $('#ar-heroes').innerHTML = list.length ? list.map(function (h) {
      return '<div class="g-hcard">' + heroSvg(artOf(h)) + '<div class="hn">' + esc(h.name) + '</div><div class="hl">' + T('Lygis') + ' ' + h.level + ' · 🏆 ' + h.ratingPower + ' · 🎯 ' + h.ratingFair + '</div>' +
        '<div class="acts">' + (h.invites ? '<button type="button" class="g-btn sm" data-inv="' + h.id + '" data-mode="power" title="' + esc(T('Jėgos kova')) + '">🏆</button><button type="button" class="g-btn sm" data-inv="' + h.id + '" data-mode="fair" title="' + esc(T('Gudrumo kova')) + '">🎯</button><button type="button" class="g-btn sm" data-inv="' + h.id + '" data-game="keturi" title="' + esc(T('Keturi iš eilės')) + '">🔴</button>' : '<span class="hl">🔕 ' + T('nepriima kvietimų') + '</span>') +
        '<a class="g-btn sm" href="' + R.urls.view.replace('HID', h.id) + '" title="' + esc(T('Žiūrėti dojo')) + '">👁</a></div></div>';
    }).join('') : '<p class="g-empty">' + T('Nieko nerasta.') + '</p>';
  }
  function lobbyClick(e) {
    var t = e.target.closest('button'); if (!t) return;
    if (t.dataset.me) { var u = new URL(location.href); u.searchParams.set('h', t.dataset.me); return go(u.toString()); }
    if (t.dataset.bot) { t.disabled = true; return api({ action: 'bot', level: t.dataset.bot, mode: t.dataset.mode || 'fair', game: t.dataset.game || 'kova' }).then(function (d) { if (d.ok) go(matchUrl(d.id, t.dataset.game)); else t.disabled = false; }); }
    if (t.dataset.inv) { t.disabled = true; return api({ action: 'invite', to: t.dataset.inv, mode: t.dataset.mode || 'fair', game: t.dataset.game || 'kova' }).then(function (d) { if (d.ok) { toast('📨 ' + T('Kvietimas išsiųstas - laukiame atsakymo…')); pollInvites(); } t.disabled = false; }); }
    if (t.dataset.acc) return api({ action: 'accept', id: t.dataset.acc }).then(function (d) { if (d.ok) go(matchUrl(d.id, t.dataset.game)); });
    if (t.dataset.dec) return api({ action: 'decline', id: t.dataset.dec }).then(pollInvites);
    if (t.dataset.can) return api({ action: 'cancel', id: t.dataset.can }).then(pollInvites);
  }
  var invT = null, waiting = {};
  function pollInvites() {
    clearTimeout(invT);
    api({ invites: 1 }).then(function (d) {
      var el = $('#ar-inv'); if (!el || !d.ok) return;
      var items = d.items || [];
      // Mano išsiųstas kvietimas priimtas - iškart į kovą
      var started = items.filter(function (i) { return i.status === 'active' && waiting[i.id]; })[0];
      if (started) return go(matchUrl(started.id, started.game));
      waiting = {}; items.forEach(function (i) { if (i.status === 'invited' && !i.incoming) waiting[i.id] = 1; });
      el.innerHTML = items.length ? '<div class="g-card ar-invites">' + items.map(function (i) {
        var g = i.game === 'keturi' ? '🔴 ' + T('Keturi iš eilės') : (i.mode === 'fair' ? '🎯 ' + T('Gudrumo kova') : '🏆 ' + T('Jėgos kova'));
        if (i.status === 'active') return '<div class="ar-inv"><span>⚔️ ' + esc(i.from) + ' vs ' + esc(i.to || T('treniruočių partneris')) + ' · ' + g + '</span><a class="g-btn sm primary" href="' + matchUrl(i.id, i.game) + '">' + T('Tęsti') + '</a></div>';
        return i.incoming ? '<div class="ar-inv in"><span>📨 <b>' + esc(i.from) + '</b> ' + T('kviečia:') + ' ' + g + '</span><button class="g-btn sm primary" data-acc="' + i.id + '" data-game="' + i.game + '">✓ ' + T('Priimti') + '</button><button class="g-btn sm" data-dec="' + i.id + '">' + T('Atmesti') + '</button></div>'
          : '<div class="ar-inv"><span>⏳ ' + T('Laukiama:') + ' <b>' + esc(i.to) + '</b> · ' + g + '</span><button class="g-btn sm" data-can="' + i.id + '">' + T('Atšaukti') + '</button></div>';
      }).join('') + '</div>' : '';
      invT = setTimeout(pollInvites, 4000);
    });
  }

  // =================== Kova ėjimais ===================
  var M = null, side = null, opp = null, seenTurn = 0, seenReact = 0, pick = { att: '', def: '', card: '' }, busy = false, animating = false, offset = 0, pollT = null, endShown = false;
  function fight() {
    // Viena arena: abu kovotojai tame pačiame fone (savas - kairėje), smūgių ir blokų lentelė - ant fono kaip žaidimo sąsaja,
    // dešinėje - gyva kovos eiga (kas ką padarė, kiek žalos).
    root.innerHTML = '<div class="fa pxui"><div class="fa-top"><a class="px-btn sm" href="' + location.pathname + '">◀ ' + T('Arena') + '</a><span class="fa-mode" id="fa-mode"></span><span class="sp"></span><span class="fa-timer" id="fa-timer"></span></div>' +
      '<div class="fa-wrap"><div class="fa-stage fa-arena" id="fa-stage" style="background-image:url(' + A.sceneUrl('dojo') + ')">' +
      '<div class="fa-hp a" id="hp-a"></div><div class="fa-turn" id="fa-turn"></div><div class="fa-hp b" id="hp-b"></div>' +
      '<div class="fa-f a" id="f-a"></div><div class="fa-f b" id="f-b"></div>' +
      '<div class="fa-ui"><div class="fa-panel" id="fa-panel"></div></div><div class="fa-fx" id="fa-fx"></div></div>' +
      '<aside class="fa-side"><div class="fa-side-h">' + A.icon('kova') + ' ' + T('Kovos eiga') + '</div><div class="fa-log" id="fa-log"></div><div class="fa-reveal" id="fa-reveal"></div>' +
      '<div class="fa-react" id="fa-react">' + R.reactions.map(function (e) { return '<button type="button" data-react="' + e + '">' + e + '</button>'; }).join('') + '</div></aside></div></div>';
    root.addEventListener('click', fightClick);
    document.addEventListener('keydown', fightKey);
    load(true);
  }
  function load(first) {
    clearTimeout(pollT);
    return api({ match: R.match }).then(function (d) {
      if (!d.ok) { root.innerHTML = '<p class="g-empty">' + T('Kova nerasta.') + '</p>'; return; }
      apply(d.match, first);
    });
  }
  function apply(m, first) {
    M = m; side = m.side; opp = side === 'b' ? 'a' : 'b';
    offset = m.now - Date.now() / 1000;
    if (m.status === 'invited') { $('#fa-panel').innerHTML = '<p class="g-empty" style="text-align:center">⏳ ' + T('Laukiama, kol priešininkas priims kvietimą…') + '</p>'; pollT = setTimeout(load, 2500); return; }
    if (m.status === 'declined' || m.status === 'cancelled') { $('#fa-panel').innerHTML = '<p class="g-empty" style="text-align:center">' + T('Kvietimas atmestas arba nebegalioja.') + ' <a href="' + location.pathname + '">' + T('Atgal į areną') + '</a></p>'; return; }
    var st = m.state;
    if (!side) { side = 'a'; opp = 'b'; }
    $('#fa-stage').classList.toggle('flip', side === 'b');
    if (first) {
      seenTurn = st.turn; seenReact = (st.reactions || []).reduce(function (x, r) { return Math.max(x, r.t); }, 0);
      drawFighters(); drawHp(); drawLog(); drawPanel();
      if (st.last) reveal(st.last);
    } else if (st.turn !== seenTurn || (st.end && !endShown)) {
      var resolved = (st.log || []).filter(function (l) { return l.n >= seenTurn && l.n < st.turn; });
      if (st.end && !resolved.length) resolved = (st.log || []).filter(function (l) { return l.k === 'end'; });
      seenTurn = st.turn;
      if (st.last) reveal(st.last);
      animate(resolved, function () { drawHp(); drawLog(); drawPanel(); });
    } else if (!animating) { drawPanel(true); }
    reactions(st.reactions || []);
    $('#fa-mode').textContent = (m.mode === 'fair' ? '🎯 ' + T('Gudrumo kova') : '🏆 ' + T('Jėgos kova')) + (st.sides.b.bot ? ' · ' + T('treniruotė') : '');
    if (m.status === 'active') pollT = setTimeout(load, st.sides[opp].bot ? 4000 : 1500);
  }
  function drawFighters() {
    var st = M.state;
    ['a', 'b'].forEach(function (x) {
      var s = st.sides[x], el = $('#f-' + x);
      el.innerHTML = '<div class="fa-body live">' + heroSvg(s.art) + '</div>' + (s.pet ? '<div class="fa-pet" title="' + esc(s.pet.name) + '">' + A.pet(s.pet.species, s.pet.color) + '</div>' : '');
      el.classList.toggle('me', x === side);
    });
  }
  function drawHp() {
    var st = M.state;
    ['a', 'b'].forEach(function (x) {
      var s = st.sides[x], hp = Math.max(0, st.hp[x]), mx = st.max[x], pct = Math.max(0, Math.round(hp / mx * 100));
      var pet = s.pet && C.pets[s.pet.species] ? '<div class="petab" title="' + esc(C.pets[s.pet.species].abn) + '">🐾 ' + esc(s.pet.name) + ': ' + esc(C.pets[s.pet.species].abn) + '</div>' : '';
      $('#hp-' + x).innerHTML = '<div class="nm">' + (x === side ? '★ ' : '') + esc(s.name) + ' <small>' + T('Lygis') + ' ' + s.level + '</small></div><div class="bar"><i class="' + (pct < 30 ? 'low' : '') + '" style="width:' + pct + '%"></i><span>' + hp + ' / ' + mx + '</span></div>' +
        (st.cards && st.cards[x] && Object.keys(st.cards[x]).length ? '<div class="cards">' + Object.keys(st.cards[x]).map(function (c) { return '<span class="' + (st.cards[x][c] ? '' : 'used') + '">' + esc(R.cards[c][0]) + '</span>'; }).join('') + '</div>' : '') + pet;
    });
    $('#fa-turn').textContent = M.status === 'done' ? '' : T('Ėjimas') + ' ' + st.turn;
  }
  // Kovos eiga (kaip pokalbis): naujausia - apačioje; žala raudonai, gydymas žaliai
  var LOG_IC = { hit: 'smugis', crit: 'kiai', block: 'blokas', miss: 'sabaki', end: 'kova' };
  function drawLog() {
    var log = (M.state.log || []).slice(-60), el = $('#fa-log'), last = 0;
    el.innerHTML = log.map(function (l) {
      var txt = esc(l.t).replace(/([−-]\d+)/g, '<b class="dmg">$1</b>').replace(/(\+\d+)/g, '<b class="heal">$1</b>');
      var head = l.n !== last ? '<div class="tn">' + T('Ėjimas') + ' ' + l.n + '</div>' : ''; last = l.n;
      return head + '<div class="ln k-' + l.k + (l.s === side ? ' mine' : ' opp') + '">' + (LOG_IC[l.k] ? A.icon(LOG_IC[l.k]) : '<span class="em">' + (l.k === 'pet' ? '🐾' : l.k === 'heal' ? '💚' : '•') + '</span>') + '<span>' + txt + '</span></div>';
    }).join('') || '<div class="g-empty">' + T('Pasirink smūgį ir gynybą, tada spausk „Kovoti!“.') + '</div>';
    el.scrollTop = el.scrollHeight;
  }
  function reveal(last) {
    var mk = function (x) { var p = last[x]; if (!p || p === true) return ''; var at = R.attacks[p.att] || {}, df = R.defenses[p.def] || {}; return '<span class="' + (x === side ? 'mine' : 'opp') + '">' + esc(M.state.sides[x].name) + ': ' + A.icon('smugis') + ' <b>' + esc(at.n) + '</b> · ' + A.icon('blokas') + ' <b>' + esc(df.n) + '</b>' + (p.card ? ' · ' + esc(R.cards[p.card][0]) : '') + '</span>'; };
    $('#fa-reveal').innerHTML = '<small>' + T('Praeitas ėjimas') + ' (' + last.turn + ')</small>' + mk(side) + mk(opp);
  }
  function drawPanel(timerOnly) {
    var st = M.state, el = $('#fa-panel');
    if (M.status === 'done') { el.innerHTML = ''; return showEnd(); }
    var mine = st.picks[side], waiting = mine && mine !== true;
    if (timerOnly && el.dataset.state === (waiting ? 'w' : 'p') + st.turn) return;
    el.dataset.state = (waiting ? 'w' : 'p') + st.turn;
    if (waiting) {
      el.innerHTML = '<div class="fa-wait">✓ ' + T('Ėjimas pasirinktas.') + ' ' + (st.picks[opp] ? '' : '⏳ ' + T('Laukiama priešininko…')) + '</div>';
      return;
    }
    // Lentelė ant arenos: kairėje - kur smūgiuoti, dešinėje - ką blokuoti. Pavadinimas su lygiu ir paaiškinimu: „Kizami tsuki jodan [smūgis į galvą]“.
    var atts = Object.keys(R.attacks), defs = Object.keys(R.defenses);
    var DA = { kizami: T('smūgis į galvą'), gyaku: T('smūgis į pilvą'), maegeri: T('spyris į pilvą'), mawashi: T('spyris į galvą'), barai: T('pakirtimas į kojas') };
    var DD = { age: T('blokas nuo smūgio į galvą'), soto: T('blokas nuo smūgio į pilvą'), gedan: T('blokas nuo smūgio į kojas'), sabaki: T('išsisukimas nuo bet kurio smūgio') };
    var full = function (n, z) { return z && n.toLowerCase().indexOf(z) < 0 ? n + ' ' + z : n; };
    var chk = function (kind, k, name, desc, zone, extra, key) {
      var on = pick[kind] === k;
      return '<button type="button" class="fa-chk ' + kind + (on ? ' sel' : '') + '" data-' + kind + '="' + k + '" aria-pressed="' + on + '"><i class="box" aria-hidden="true"></i>' +
        '<span class="tx"><b>' + esc(name) + '</b><small>[' + esc(desc) + ']' + (extra ? ' <em>' + extra + '</em>' : '') + '</small></span>' + A.icon(zone || 'sabaki', 'zi') + '<kbd>' + key + '</kbd></button>';
    };
    var html = '<div class="fa-board"><div class="fb-col"><div class="fb-h h-att">' + A.icon('smugis') + ' ' + T('Kur smūgiuoti?') + '</div>';
    atts.forEach(function (k, i) { var a = R.attacks[k]; html += chk('att', k, full(a.n, a.z), DA[k] || ZN[a.z], a.z, '×' + a.p + (a.acc ? ' ' + (a.acc > 0 ? '+' : '') + a.acc + '%' : ''), i + 1); });
    html += '</div><div class="fb-col"><div class="fb-h h-def">' + A.icon('blokas') + ' ' + T('Ką blokuoti?') + '</div>';
    defs.forEach(function (k, i) { var d = R.defenses[k]; html += chk('def', k, full(d.n, d.z), DD[k] || ZN[d.z], d.z, '', 'QWER'[i]); });
    html += '</div></div>';
    var cards = st.cards[side] || {}, CI = { kiai: 'kiai', zanshin: 'zanshin', kime: 'kime' };
    if (Object.keys(cards).length) html += '<div class="fa-cards"><span class="fc-h">' + T('Ypatingi veiksmai') + '</span>' + Object.keys(cards).map(function (c) { return '<button type="button" class="fa-card' + (pick.card === c ? ' sel' : '') + '" data-card="' + c + '"' + (cards[c] ? '' : ' disabled') + ' title="' + esc(R.cards[c][1]) + '">' + A.icon(CI[c] || 'kiai') + '<span><b>' + esc(R.cards[c][0]) + '</b><small>' + esc(R.cards[c][1]) + '</small></span></button>'; }).join('') + '</div>';
    html += '<div class="fa-go"><button type="button" class="px-btn go fa-fight" data-go="1"' + (pick.att && pick.def ? '' : ' disabled') + '>' + A.icon('kova') + ' ' + T('Kovoti!') + ' <kbd>Enter</kbd></button></div>';
    el.innerHTML = html;
  }
  function fightClick(e) {
    var t = e.target.closest('button'); if (!t || t.disabled) return;
    if (t.dataset.att) { pick.att = t.dataset.att; return drawPanel(); }
    if (t.dataset.def) { pick.def = t.dataset.def; return drawPanel(); }
    if (t.dataset.card) { pick.card = pick.card === t.dataset.card ? '' : t.dataset.card; return drawPanel(); }
    if (t.dataset.go) return submit();
    if (t.dataset.react) { api({ action: 'react', match: M.id, e: t.dataset.react }); floatEmoji(t.dataset.react, side); return; }
    if (t.dataset.again) return api({ action: 'bot', level: t.dataset.again, mode: M.mode, game: 'kova' }).then(function (d) { if (d.ok) go(matchUrl(d.id)); });
    if (t.dataset.forfeit) { if (confirm(T('Tikrai pasiduoti?'))) api({ action: 'forfeit', match: M.id }).then(function (d) { if (d.ok) apply(d.match); }); }
  }
  function fightKey(e) {
    if (!M || M.status !== 'active' || e.target.matches('input,textarea')) return;
    var atts = Object.keys(R.attacks), defs = Object.keys(R.defenses), k = e.key.toLowerCase();
    if (/^[1-5]$/.test(k)) { pick.att = atts[+k - 1]; drawPanel(); }
    else if ('qwer'.indexOf(k) >= 0 && k.length === 1) { pick.def = defs['qwer'.indexOf(k)]; drawPanel(); }
    else if (k === 'enter') submit();
  }
  function submit() {
    if (busy || !pick.att || !pick.def) return;
    busy = true;
    $('#fa-panel').innerHTML = '<div class="fa-wait">⚔️ ' + T('Kovojama…') + '</div>'; $('#fa-panel').dataset.state = '';
    api({ action: 'turn', match: M.id, pick: pick }).then(function (d) {
      busy = false;
      if (d.ok) { pick = { att: '', def: '', card: '' }; apply(d.match); }
    });
  }
  setInterval(function () {
    var el = document.getElementById('fa-timer'); if (!el || !M || M.status !== 'active') { if (el) el.textContent = ''; return; }
    var left = Math.max(0, Math.round(M.state.deadline - (Date.now() / 1000 + offset)));
    el.textContent = '⏱ ' + left + ' s'; el.classList.toggle('warn', left <= 8);
    if (left === 0 && !busy) { busy = true; setTimeout(function () { busy = false; load(); }, 1200); }
  }, 500);

  // Animacijos: smūgis, blokas, išsisukimas, žala
  function animate(list, done) {
    animating = true;
    var i = 0;
    (function step() {
      if (i >= list.length) { animating = false; return done(); }
      var l = list[i++], x = l.s, y = x === 'a' ? 'b' : 'a', fx = $('#fa-' + 'fx');
      var num = (l.t.match(/[−-](\d+)/) || [])[1];
      if (l.k === 'hit' || l.k === 'crit' || /−\d+/.test(l.t) && l.k !== 'block' && l.k !== 'heal' && l.k !== 'pet') {
        cls('#f-' + x, 'lunge'); setTimeout(function () { cls('#f-' + y, 'hit'); if (num) pop(y, '−' + num, 'dmg'); if (/Waza-ari/.test(l.t)) pop(y, 'Waza-ari!', 'big'); if (/KIAI/.test(l.t)) pop(x, 'KIAI!', 'big'); }, 180);
      } else if (l.k === 'block') {
        cls('#f-' + y, 'lunge'); setTimeout(function () { cls('#f-' + x, 'block'); pop(x, '🛡', 'icon'); if (num) setTimeout(function () { cls('#f-' + y, 'hit'); pop(y, '−' + num, 'dmg'); }, 250); }, 160);
      } else if (l.k === 'miss') {
        cls('#f-' + y, 'lunge'); cls('#f-' + x, 'dodge'); pop(x, T('Išsisuko!'), 'txt');
      } else if (l.k === 'pet') { cls('#f-' + x + ' .fa-pet', 'petjump'); pop(x, '🐾', 'icon'); }
      else if (l.k === 'heal') { pop(x, '+' + (l.t.match(/\+(\d+)/) || [0, ''])[1], 'heal'); }
      else if (l.k === 'end') { if (/IPPON/.test(l.t)) { var b = document.createElement('div'); b.className = 'fa-ippon'; b.textContent = 'IPPON!'; fx.appendChild(b); setTimeout(function () { b.remove(); }, 1800); } }
      var ln = document.createElement('div'); ln.className = 'fa-flash'; ln.textContent = l.t; fx.appendChild(ln); setTimeout(function () { ln.remove(); }, 1400);
      setTimeout(step, l.k === 'end' ? 1400 : 950);
    })();
  }
  function cls(sel, c) { var el = $(sel); if (!el) return; el.classList.remove(c); void el.offsetWidth; el.classList.add(c); setTimeout(function () { el.classList.remove(c); }, 700); }
  function pop(x, txt, kind) {
    var f = $('#f-' + x), st = $('#fa-stage'); if (!f) return;
    var r = f.getBoundingClientRect(), sr = st.getBoundingClientRect(), p = document.createElement('div');
    p.className = 'fa-pop ' + kind; p.textContent = txt;
    p.style.left = (r.left - sr.left + r.width / 2) + 'px'; p.style.top = (r.top - sr.top + r.height * 0.2) + 'px';
    st.appendChild(p); setTimeout(function () { p.remove(); }, 1300);
  }
  function floatEmoji(e, x) { pop(x, e, 'emoji'); }
  function reactions(list) {
    list.forEach(function (r) { if (r.t > seenReact) { if (r.s !== side) floatEmoji(r.e, r.s); seenReact = r.t; } });
  }
  function showEnd() {
    if (endShown) return; endShown = true;
    var st = M.state, w = M.winner, iWon = (w === 1 && side === 'a') || (w === 2 && side === 'b'), draw = w === 0;
    var rt = st.rating ? st.rating[side] : null, bot = st.sides[opp].bot;
    var html = '<div class="fa-end ' + (iWon ? 'win' : draw ? 'draw' : 'lose') + '"><div class="big">' + (iWon ? '🏆 IPPON! ' + T('Laimėjai!') : draw ? '🤝 ' + T('Lygiosios!') : '🙇 ' + T('Šįkart nepavyko. Oss - kitą kartą!')) + '</div>' +
      (rt !== null && rt !== undefined ? '<div>' + (M.mode === 'fair' ? '🎯 ' : '🏆 ') + T('Reitingas') + ' <b>' + (rt > 0 ? '+' : '') + rt + '</b></div>' : '') +
      (st.sides[side].pet ? '<div>🐾 ' + esc(st.sides[side].pet.name) + ' +' + (iWon ? 6 : 3) + ' XP</div>' : '') +
      '<div class="gm-foot" style="justify-content:center">' + (bot ? '<button class="g-btn gold" data-again="' + bot + '">🔁 ' + T('Revanšas') + '</button>' : '') + '<a class="g-btn" href="' + location.pathname + '">⚔️ ' + T('Į areną') + '</a><a class="g-btn" href="' + R.urls.dojo + '">🏯 ' + T('Į dojo') + '</a></div></div>';
    $('#fa-panel').innerHTML = html;
    if (iWon) confetti();
  }
  function confetti() {
    for (var i = 0; i < 40; i++) {
      var c = document.createElement('i'); c.className = 'confetti';
      c.style.left = (Math.random() * 100) + 'vw'; c.style.background = ['#F2C14E', '#C8372D', '#2E5FA8', '#2E8B57', '#A855F7'][i % 5];
      c.style.setProperty('--dx', (Math.random() * 200 - 100) + 'px'); c.style.animationDelay = (Math.random() * 0.5) + 's';
      document.body.appendChild(c); setTimeout(function (x) { return function () { x.remove(); }; }(c), 3000);
    }
  }

  // =================== Greita kova (realiu laiku) ===================
  var LV = {
    easy: { n: '🐼 ' + T('Lengvas'), wind: 1050, gap: [1500, 2600], block: 0.18, k: 0.75, sp: 'panda', name: T('Senpai Bambukas'), belt: 3, kim: 'k_white' },
    normal: { n: '🐵 ' + T('Vidutinis'), wind: 780, gap: [1050, 2000], block: 0.38, k: 0.95, sp: 'monkey', name: T('Sensei Makivara'), belt: 7, kim: 'k_white' },
    hard: { n: '🐲 ' + T('Sunkus'), wind: 560, gap: [750, 1500], block: 0.55, k: 1.15, sp: 'dragon', name: T('Meistras Drakonas'), belt: 12, kim: 'k_black_red' }
  };
  var Z3 = ['jodan', 'chudan', 'gedan'];
  var g = null;
  function greitaSetup() {
    var lv = localStorage.getItem('gr-lv') || 'normal', md = localStorage.getItem('gr-md') || 'power';
    try { lv = localStorage.getItem('gr-lv') || 'normal'; } catch (e) {}
    root.innerHTML = '<div class="fa gr"><div class="fa-top"><a class="g-btn sm" href="' + location.pathname + '">← ' + T('Arena') + '</a><span class="fa-mode">⚡ ' + T('Greita kova') + '</span></div>' +
      '<div class="g-card gr-intro"><h2 style="margin:0 0 6px">⚡ ' + T('Greita kova') + '</h2><p>' + T('Priešininkas parodo, kur smūgiuos (⬆️ galva, ➡️ kūnas, ⬇️ kojos). Spėk užblokuoti tą lygį, kol ratukas nesibaigė. Paskutinę akimirką užblokavus - TOBULAS blokas ir kontrataka!') + '</p>' +
      '<div class="gr-keys"><div><b>🛡 ' + T('Blokas') + '</b><kbd>↑</kbd> ' + T('galva') + ' <kbd>→</kbd> ' + T('kūnas') + ' <kbd>↓</kbd> ' + T('kojos') + '</div><div><b>👊 ' + T('Smūgis') + '</b><kbd>W</kbd> ' + T('galva') + ' <kbd>D</kbd> ' + T('kūnas') + ' <kbd>S</kbd> ' + T('kojos') + '</div></div>' +
      '<p class="g-empty">' + T('Smūgiuok, kai priešininkas ruošiasi smūgiui - nutrauksi jį! Bet kol smūgiuoji, pats nesiginate. Serija be klaidų - kombo.') + '</p>' +
      '<div class="g-sub">' + T('Priešininkas') + '</div><div class="ed-seg" id="gr-lv">' + Object.keys(LV).map(function (k) { return '<button type="button" data-lv="' + k + '" class="' + (k === lv ? 'sel' : '') + '">' + LV[k].n + '</button>'; }).join('') + '</div>' +
      '<div class="g-sub">' + T('Režimas') + '</div><div class="ed-seg" id="gr-md"><button type="button" data-md="power" class="' + (md === 'power' ? 'sel' : '') + '">🏆 ' + T('Jėgos (mano savybės)') + '</button><button type="button" data-md="fair" class="' + (md === 'fair' ? 'sel' : '') + '">🎯 ' + T('Lygūs') + '</button></div>' +
      '<div class="gm-foot" style="justify-content:flex-start"><button type="button" class="g-btn gold" id="gr-start" style="font-size:1.05rem;padding:10px 22px">▶ ' + T('Pradėti!') + '</button></div><div id="gr-top"></div></div></div>';
    var sel = { lv: lv, md: md };
    root.onclick = function (e) {
      var t = e.target.closest('button'); if (!t) return;
      if (t.dataset.lv) { sel.lv = t.dataset.lv; root.querySelectorAll('[data-lv]').forEach(function (b) { b.classList.toggle('sel', b === t); }); loadTop(sel.lv); }
      if (t.dataset.md) { sel.md = t.dataset.md; root.querySelectorAll('[data-md]').forEach(function (b) { b.classList.toggle('sel', b === t); }); }
      if (t.id === 'gr-start') { try { localStorage.setItem('gr-lv', sel.lv); localStorage.setItem('gr-md', sel.md); } catch (x) {} greitaStart(sel.lv, sel.md); }
    };
    loadTop(lv);
  }
  function loadTop(lv) {
    api({ top: 1, game: 'greita', song: lv }).then(function (d) {
      var el = document.getElementById('gr-top'); if (!el || !d.ok) return;
      el.innerHTML = d.top.length ? '<div class="g-sub">🏅 ' + T('Savaitės geriausi') + ' · ' + LV[lv].n + '</div><ol class="ar-top">' + d.top.map(function (r) { return '<li><span>' + esc(r.name) + '</span><b>' + r.score + '</b></li>'; }).join('') + '</ol>' : '';
    });
  }
  function greitaStart(lv, md) {
    var L = LV[lv], st = me.stats;
    var myS = md === 'fair' ? { hp: 70, dmg: [5, 8] } : { hp: st.hp, dmg: st.dmg };
    var botS = md === 'fair' ? { hp: 70, dmg: [5, 8] } : { hp: Math.round(st.hp * L.k), dmg: [Math.max(1, Math.round(st.dmg[0] * L.k)), Math.max(2, Math.round(st.dmg[1] * L.k))] };
    g = { lv: lv, md: md, L: L, hp: { me: myS.hp, bot: botS.hp }, max: { me: myS.hp, bot: botS.hp }, dmg: { me: myS.dmg, bot: botS.dmg }, t0: 0, dur: 60000,
      bot: { state: 'idle', next: 0, zone: '', windStart: 0, hist: [] }, guard: { zone: '', until: 0, at: 0 }, atk: { until: 0, zone: '', strikeAt: 0 }, cdAtk: 0, cdGuard: 0,
      combo: 0, maxCombo: 0, lastHit: 0, perfect: 0, blocks: 0, hits: 0, taken: 0, dealt: 0, reacts: [], interrupts: 0, over: false };
    var botArt = { species: L.sp, gender: 'm', look: { fur: { easy: '#E9D2A8', normal: '#C8703A', hard: '#3B3A4A' }[lv] || '#C8703A', flame: { easy: '#6EDC8C', normal: '#FF9A3C', hard: '#B46CFF' }[lv] || '#FF9A3C', eyes: '#2B2B2B' }, equipped: { kimono: lv === 'easy' ? 'k_emerald' : L.kim, belt: 'b_plain', head: lv === 'easy' ? 'h_band_white' : 'h_band_red' }, belt: L.belt, name: L.name };
    root.innerHTML = '<div class="fa gr"><div class="fa-top"><a class="g-btn sm" href="' + location.href + '">← ' + T('Atgal') + '</a><span class="fa-mode">⚡ ' + T('Greita kova') + ' · ' + L.n + '</span><span class="sp"></span><span class="fa-timer" id="gr-time">60 s</span></div>' +
      '<div class="fa-stage" id="fa-stage" style="background-image:url(' + A.sceneUrl('dojo') + ')"><div class="fa-hp a" id="hp-a"></div><div class="fa-hp b" id="hp-b"></div>' +
      '<div class="fa-f a me" id="f-a"><div class="fa-body live">' + heroSvg(artOf(me)) + '</div></div><div class="fa-f b" id="f-b"><div class="fa-body live">' + heroSvg(botArt) + '</div><div class="gr-tele" id="gr-tele"><svg viewBox="0 0 60 60"><circle cx="30" cy="30" r="26" class="trk"/><circle cx="30" cy="30" r="26" class="ring" id="gr-ring"/></svg><span id="gr-z"></span></div></div>' +
      '<div class="gr-combo" id="gr-combo"></div><div class="fa-fx" id="fa-fx"></div><div class="gr-count" id="gr-count"></div></div>' +
      '<div class="gr-pad"><div class="gr-row"><span>🛡</span>' + Z3.map(function (z) { return '<button type="button" class="gr-b blk" data-blk="' + z + '">' + ZI[z] + '<small>' + ZN[z] + '</small></button>'; }).join('') + '</div>' +
      '<div class="gr-row"><span>👊</span>' + Z3.map(function (z) { return '<button type="button" class="gr-b atk" data-atk="' + z + '">' + ZI[z] + '<small>' + ZN[z] + '</small></button>'; }).join('') + '</div></div></div>';
    g.names = { me: me.publicName || me.name, bot: L.name };
    grHp();
    root.onclick = null;
    root.onpointerdown = function (e) { var t = e.target.closest('button'); if (!t || !g || g.over) return; if (t.dataset.blk) { e.preventDefault(); doGuard(t.dataset.blk); } if (t.dataset.atk) { e.preventDefault(); doAttack(t.dataset.atk); } };
    document.onkeydown = function (e) {
      if (!g || g.over) return;
      var m = { ArrowUp: ['g', 'jodan'], ArrowRight: ['g', 'chudan'], ArrowLeft: ['g', 'chudan'], ArrowDown: ['g', 'gedan'], w: ['a', 'jodan'], d: ['a', 'chudan'], a: ['a', 'chudan'], s: ['a', 'gedan'] }[e.key.length === 1 ? e.key.toLowerCase() : e.key];
      if (!m) return; e.preventDefault();
      if (e.repeat) return;
      m[0] === 'g' ? doGuard(m[1]) : doAttack(m[1]);
    };
    // 3-2-1
    var n = 3, ce = document.getElementById('gr-count');
    (function cd() {
      ce.textContent = n > 0 ? n : T('Hajime!'); ce.className = 'gr-count show';
      if (n-- > 0) return setTimeout(cd, 700);
      setTimeout(function () { ce.className = 'gr-count'; }, 600);
      g.t0 = performance.now(); g.bot.next = g.t0 + 900; requestAnimationFrame(loop);
    })();
  }
  function rnd(a, b) { return a + Math.random() * (b - a); }
  function roll(d) { return Math.round(rnd(d[0], d[1] + 0.99) - 0.49); }
  function grHp() {
    [['a', 'me'], ['b', 'bot']].forEach(function (p) {
      var hp = Math.max(0, Math.round(g.hp[p[1]])), mx = g.max[p[1]], pct = Math.round(hp / mx * 100);
      document.getElementById('hp-' + p[0]).innerHTML = '<div class="nm">' + (p[1] === 'me' ? '⭐ ' : '') + esc(g.names[p[1]]) + '</div><div class="bar"><i class="' + (pct < 30 ? 'low' : '') + '" style="width:' + pct + '%"></i><span>' + hp + ' / ' + mx + '</span></div>';
    });
    var c = document.getElementById('gr-combo'); c.textContent = g.combo > 1 ? g.combo + '× ' + T('KOMBO') : ''; c.classList.toggle('on', g.combo > 1);
  }
  function grPop(who, txt, kind) { pop(who === 'me' ? 'a' : 'b', txt, kind); }
  function doGuard(z) {
    var now = performance.now();
    if (now < g.cdGuard || now < g.atk.until) return;
    g.guard = { zone: z, until: now + 420, at: now };
    g.cdGuard = now + 520;
    var b = root.querySelector('[data-blk="' + z + '"]'); b.classList.add('on'); setTimeout(function () { b.classList.remove('on'); }, 420);
    cls('#f-a', 'guard');
  }
  function doAttack(z) {
    var now = performance.now();
    if (now < g.cdAtk) return;
    g.atk = { zone: z, strikeAt: now + 200, until: now + 420 };
    g.cdAtk = now + 560;
    g.guard.until = 0;   // puolant - nesiginama
    var b = root.querySelector('[data-atk="' + z + '"]'); b.classList.add('on'); setTimeout(function () { b.classList.remove('on'); }, 300);
    cls('#f-a', 'lunge');
  }
  function loop(now) {
    if (!g || g.over) return;
    var el = now - g.t0, left = Math.max(0, g.dur - el), B = g.bot, L = g.L;
    document.getElementById('gr-time').textContent = Math.ceil(left / 1000) + ' s';
    // Mano smūgis
    if (g.atk.strikeAt && now >= g.atk.strikeAt) {
      var z = g.atk.zone; g.atk.strikeAt = 0;
      var interrupt = B.state === 'windup';
      var blocked = !interrupt && Math.random() < L.block * (B.hist.slice(-3).filter(function (x) { return x === z; }).length >= 2 ? 1.6 : 1);
      B.hist.push(z);
      if (blocked) {
        g.combo = 0; grPop('bot', '🛡', 'icon'); cls('#f-b', 'block');
        if (g.lv === 'hard' && Math.random() < 0.5) { var cd = Math.round(roll(g.dmg.bot) * 0.5); g.hp.me -= cd; g.taken += cd; grPop('me', '−' + cd, 'dmg'); cls('#f-a', 'hit'); }
      } else {
        g.combo = now - g.lastHit < 1600 ? Math.min(6, g.combo + 1) : 1; g.lastHit = now; g.maxCombo = Math.max(g.maxCombo, g.combo);
        var d = roll(g.dmg.me) * (1 + 0.15 * (g.combo - 1)) * ({ jodan: 1.15, chudan: 1, gedan: 0.85 }[z]) * (interrupt ? 1.3 : 1);
        d = Math.max(1, Math.round(d)); g.hp.bot -= d; g.dealt += d; g.hits++;
        cls('#f-b', 'hit'); grPop('bot', '−' + d, 'dmg');
        if (interrupt) { g.interrupts++; B.state = 'idle'; B.next = now + rnd(700, 1200); tele(''); grPop('bot', T('Nutraukei!'), 'big'); }
      }
      grHp();
    }
    // Boto smūgis: laukia -> rodo, kur smūgiuos (ratukas) -> smūgiuoja
    if (B.state === 'idle' && now >= B.next) {
      var pz = Z3[Math.floor(Math.random() * 3)];
      if (g.reacts.length > 3 && Math.random() < 0.35) { pz = g.lastFailZone || pz; }   // kartais smūgiuoja ten, kur praleidai
      B.state = 'windup'; B.zone = pz; B.windStart = now; B.wind = L.wind * rnd(0.85, 1.15);
      tele(pz);
    }
    if (B.state === 'windup') {
      var p = (now - B.windStart) / B.wind;
      var ring = document.getElementById('gr-ring'); if (ring) ring.style.strokeDashoffset = (163 * p).toFixed(1);
      if (p >= 1) {
        B.state = 'idle'; B.next = now + rnd(L.gap[0], L.gap[1]); tele('');
        cls('#f-b', 'lunge');
        var gd = g.guard;
        if (gd.zone === B.zone && now <= gd.until && gd.at >= B.windStart) {
          var react = Math.round(gd.at - B.windStart), perfect = B.windStart + B.wind - gd.at <= 200;
          g.reacts.push(react); g.blocks++;
          cls('#f-a', 'block');
          if (perfect) { g.perfect++; var cc = Math.max(1, Math.round(roll(g.dmg.me) * 1.1)); g.hp.bot -= cc; g.dealt += cc; grPop('me', T('TOBULAS!'), 'big'); setTimeout(function () { cls('#f-b', 'hit'); grPop('bot', '−' + cc, 'dmg'); grHp(); }, 180); flash(); }
          else grPop('me', '🛡 ' + react + ' ms', 'txt');
        } else {
          var dd = Math.max(1, Math.round(roll(g.dmg.bot) * ({ jodan: 1.15, chudan: 1, gedan: 0.85 }[B.zone])));
          if (gd.zone && gd.zone !== B.zone && now <= gd.until) dd = Math.round(dd * 0.75);   // blokavo, bet ne tą lygį
          g.hp.me -= dd; g.taken += dd; g.combo = 0; g.lastFailZone = B.zone;
          cls('#f-a', 'hit'); grPop('me', '−' + dd, 'dmg');
        }
        grHp();
      }
    }
    if (g.hp.me <= 0 || g.hp.bot <= 0 || left <= 0) return greitaEnd();
    requestAnimationFrame(loop);
  }
  function tele(z) {
    var t = document.getElementById('gr-tele'); if (!t) return;
    t.classList.toggle('on', !!z);
    document.getElementById('gr-z').textContent = z ? ZI[z] : '';
    root.querySelectorAll('.gr-b.blk').forEach(function (b) { b.classList.toggle('warn', b.dataset.blk === z); });
  }
  function flash() { var s = document.getElementById('fa-stage'); s.classList.remove('perfect'); void s.offsetWidth; s.classList.add('perfect'); }
  function greitaEnd() {
    g.over = true; tele('');
    var pm = g.hp.me / g.max.me, pb = g.hp.bot / g.max.bot, win = g.hp.bot <= 0 || (g.hp.me > 0 && pm > pb);
    var avg = g.reacts.length ? Math.round(g.reacts.reduce(function (a, b) { return a + b; }, 0) / g.reacts.length) : 0;
    var score = Math.max(0, Math.round(g.dealt * 10 + g.perfect * 150 + g.blocks * 50 + g.maxCombo * 100 + g.interrupts * 120 + (win ? 1000 : 0) + Math.max(0, g.hp.me) * 5));
    var mult = { easy: 1, normal: 1.35, hard: 1.8 }[g.lv], s2 = score / mult;
    var grade = s2 >= 3000 ? 'S' : s2 >= 2300 ? 'A' : s2 >= 1600 ? 'B' : s2 >= 900 ? 'C' : 'D';
    var html = '<div class="fa-end ' + (win ? 'win' : 'lose') + '"><div class="big">' + (win ? '🏆 ' + T('Laimėjai!') : '🙇 ' + T('Pralaimėjai - bandyk dar!')) + '</div>' +
      '<div class="gr-stats"><div><b>' + grade + '</b>' + T('Įvertinimas') + '</div><div><b>' + score + '</b>' + T('Taškai') + '</div><div><b>' + (avg || '–') + (avg ? ' ms' : '') + '</b>' + T('Reakcija') + '</div><div><b>' + g.perfect + '</b>' + T('Tobuli blokai') + '</div><div><b>' + g.blocks + '</b>' + T('Blokai') + '</div><div><b>' + g.maxCombo + '×</b>' + T('Didžiausias kombo') + '</div><div><b>' + g.interrupts + '</b>' + T('Nutraukti smūgiai') + '</div></div>' +
      '<div id="gr-prize"></div><div class="gm-foot" style="justify-content:center"><button class="g-btn gold" id="gr-again">🔁 ' + T('Dar kartą') + '</button><a class="g-btn" href="' + R.urls.greita + '">⚙️ ' + T('Keisti priešininką') + '</a><a class="g-btn" href="' + location.pathname + '">⚔️ ' + T('Į areną') + '</a></div></div>';
    var pad = root.querySelector('.gr-pad'); pad.outerHTML = html;
    document.getElementById('gr-again').onclick = function () { greitaStart(g.lv, g.md); };
    if (win) confetti();
    api({ action: 'score', game: 'greita', song: g.lv, mode: g.md, score: score, grade: grade, win: win ? 1 : 0 }).then(function (d) {
      if (d.ok && d.prize === 'box') document.getElementById('gr-prize').innerHTML = '<p style="font-weight:800">🎁 ' + T('3 pergalės šiandien - dovanų dėžė laukia tavo dojo!') + '</p>';
    });
  }

  if (R.view === 'match') fight();
  else if (R.view === 'greita') greitaSetup();
  else lobby();
})();
