// „Keturi iš eilės“ su gyvūnėlių kortomis: lenta, kortų naudojimas (taikinys -> stulpelis), ėjimai, reakcijos.
(function () {
  var K = window.K4, root = document.getElementById('k4');
  if (!K || !root) return;
  var A = window.GameArt, T = window.tr || function (s) { return s; };
  window.GAME = { catalog: K.catalog };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var $ = function (s) { return root.querySelector(s); };
  var M = null, side = 'a', opp = 'b', card = '', step = '', sel = {}, busy = false, pollT = null, seenReact = 0, lastKey = '', endShown = false;

  function api(data) {
    var post = !!data.action;
    var url = K.api + (post ? '' : ('?' + Object.keys(data).map(function (k) { return k + '=' + encodeURIComponent(data[k]); }).join('&') + '&h=' + K.hero.id));
    if (post) data.h = K.hero.id;
    return fetch(url, { method: post ? 'POST' : 'GET', credentials: 'same-origin', headers: post ? { 'Content-Type': 'application/json', 'X-CSRF-Token': K.csrf } : {}, body: post ? JSON.stringify(data) : undefined })
      .then(function (r) { return r.json(); }).then(function (d) { if (d && d.ok === false && d.error) hint(d.error, true); return d; }).catch(function () { return { ok: false }; });
  }
  function hint(t, bad) { var h = $('#k4-hint'); if (h) { h.textContent = t; h.classList.toggle('bad', !!bad); } }

  root.innerHTML = '<div class="fa-top"><a class="g-btn sm" href="' + K.urls.arena + '">← ' + T('Arena') + '</a><span class="fa-mode">🔴 ' + T('Keturi iš eilės') + '</span><span class="sp"></span></div>' +
    '<div class="k4-wrap"><div class="k4-side a" id="k4-a"></div><div class="k4-mid"><div class="k4-hint" id="k4-hint"></div><div class="k4-board" id="k4-board"></div><div class="k4-cards" id="k4-cards"></div></div><div class="k4-side b" id="k4-b"></div></div>' +
    '<div class="fa-bottom"><div class="fa-log" id="k4-log"></div><div class="fa-react">' + K.reactions.map(function (e) { return '<button type="button" data-react="' + e + '">' + e + '</button>'; }).join('') + '</div></div><div id="k4-end"></div>';

  function load() {
    clearTimeout(pollT);
    api({ match: K.match }).then(function (d) { if (d.ok) apply(d.match); });
  }
  function apply(m) {
    M = m; side = m.side || 'a'; opp = side === 'a' ? 'b' : 'a';
    if (m.status === 'invited') { hint('⏳ ' + T('Laukiama, kol draugas priims kvietimą…')); pollT = setTimeout(load, 2500); return; }
    if (m.status === 'declined' || m.status === 'cancelled') { hint(T('Kvietimas atmestas arba nebegalioja.')); return; }
    var st = m.state, key = JSON.stringify(st.board) + st.turn + (st.end ? 'e' : '');
    if (key !== lastKey) { lastKey = key; draw(); }
    (st.reactions || []).forEach(function (r) { if (r.t > seenReact) { if (r.s !== side && seenReact) bubble(r.s, r.e); seenReact = r.t; } });
    if (m.status === 'active' && st.turn !== side) pollT = setTimeout(load, 1500);
  }
  function sideHtml(x) {
    var s = M.state.sides[x], turn = M.state.turn === x && M.status === 'active';
    return '<div class="k4-hero' + (turn ? ' turn' : '') + '"><div class="live">' + A.hero(s.art) + '</div>' + (s.pet ? '<div class="k4-pet">' + A.pet(s.pet.species, s.pet.color) + '</div>' : '') +
      '<div class="k4-nm"><i class="k4-dot p' + (x === 'a' ? 1 : 2) + '"></i>' + esc(s.name) + (x === side ? ' (' + T('tu') + ')' : '') + '</div>' +
      (turn ? '<div class="k4-turn">' + (x === side ? '👉 ' + T('Tavo ėjimas!') : '⏳ ' + T('Galvoja…')) + '</div>' : '') +
      '<div class="k4-c">' + Object.keys(s.cards).map(function (c) { return '<span class="' + (s.cards[c] ? '' : 'used') + '" title="' + esc(K.cards[c][2]) + '">' + K.cards[c][0] + '</span>'; }).join('') + '</div></div>';
  }
  function draw() {
    var st = M.state, b = st.board, myTurn = M.status === 'active' && st.turn === side, myP = side === 'a' ? 1 : 2;
    $('#k4-a').innerHTML = sideHtml('a'); $('#k4-b').innerHTML = sideHtml('b');
    var line = {}; (st.line || []).forEach(function (p) { line[p[0] + '-' + p[1]] = 1; });
    var wallCol = st.wall && st.wall.side === side ? st.wall.col : -1;
    var html = '';
    for (var c = 0; c < K.w; c++) {
      html += '<div class="k4-col' + (myTurn ? ' can' : '') + (c === wallCol ? ' wall' : '') + (sel.col === c || sel.wall === c ? ' sel' : '') + '" data-col="' + c + '">';
      for (var r = 0; r < K.h; r++) {
        var v = b[r][c], last = st.last && st.last[0] === r && st.last[1] === c;
        var tgt = (step === 'target' && card === 'bomb' && v && v !== myP) || (step === 'target' && card === 'swap' && v === myP) || (step === 'target2' && v && v !== myP && sel.t && Math.max(Math.abs(sel.t[0] - r), Math.abs(sel.t[1] - c)) === 1);
        html += '<div class="k4-cell" data-r="' + r + '" data-c="' + c + '">' + (v ? '<i class="k4-tok p' + v + (last ? ' drop' : '') + (line[r + '-' + c] ? ' win' : '') + (tgt ? ' tgt' : '') + (sel.t && sel.t[0] === r && sel.t[1] === c ? ' picked' : '') + '" style="--r:' + r + '"></i>' : '') + '</div>';
      }
      html += (c === wallCol ? '<span class="k4-wall">🧱</span>' : '') + '</div>';
    }
    $('#k4-board').innerHTML = html;
    var my = st.sides[side].cards;
    $('#k4-cards').innerHTML = M.status === 'active' ? '<span class="g-sub" style="margin:0">🃏 ' + T('Tavo kortos:') + '</span>' + Object.keys(my).map(function (c) { return '<button type="button" class="fa-card' + (card === c ? ' sel' : '') + '" data-card="' + c + '"' + (my[c] && myTurn ? '' : ' disabled') + '><b>' + K.cards[c][0] + ' ' + esc(K.cards[c][1]) + '</b><small>' + esc(K.cards[c][2]) + '</small></button>'; }).join('') : '';
    $('#k4-log').innerHTML = (st.log || []).slice(-10).reverse().map(function (l) { return '<div class="ln">' + esc(l) + '</div>'; }).join('') || '<div class="g-empty">' + T('Sudėk 4 savo žetonus iš eilės - gulsčiai, stačiai ar įstrižai. Kortas gali panaudoti po vieną kartą.') + '</div>';
    if (M.status === 'done') return end();
    hint(myTurn ? stepHint() : '⏳ ' + T('Priešininko ėjimas…'));
  }
  function stepHint() {
    if (!card) return '👉 ' + T('Paspausk stulpelį, kur mesti žetoną') + (wallBlocked() ? ' (🧱 ' + T('vienas stulpelis užtvertas') + ')' : '');
    return { bomb: { target: T('💣 Pasirink priešininko žetoną, kurį susprogdinti'), col: T('Dabar - stulpelis savo žetonui') },
      swap: { target: T('🔄 Pasirink savo žetoną'), target2: T('…ir gretimą priešininko žetoną'), col: T('Dabar - stulpelis savo žetonui') },
      wall: { wall: T('🧱 Pasirink stulpelį, kurį užtverti priešininkui'), col: T('Dabar - stulpelis savo žetonui') },
      double: { col: T('⏩ Pirmas stulpelis'), col2: T('⏩ Antras stulpelis') } }[card][step] || '';
  }
  function wallBlocked() { var st = M.state; return st.wall && st.wall.side === side; }
  function startCard(c) {
    if (card === c) { card = ''; step = ''; sel = {}; return draw(); }
    card = c; sel = {}; step = { bomb: 'target', swap: 'target', wall: 'wall', double: 'col' }[c]; draw();
  }
  function send(mv) {
    if (busy) return; busy = true;
    api({ action: 'move', match: M.id, mv: mv }).then(function (d) {
      busy = false; card = ''; step = ''; sel = {};
      if (d.ok) apply(d.match); else draw();
    });
  }
  root.addEventListener('click', function (e) {
    var t = e.target;
    var cb = t.closest('[data-card]'); if (cb && !cb.disabled) return startCard(cb.dataset.card);
    var rb = t.closest('[data-react]'); if (rb) { api({ action: 'react', match: M.id, e: rb.dataset.react }); return bubble(side, rb.dataset.react); }
    if (t.closest('[data-again]')) return api({ action: 'bot', game: 'keturi', level: t.closest('[data-again]').dataset.again }).then(function (d) { if (d.ok) location.href = K.urls.match.replace('MID', d.id); });
    if (!M || M.status !== 'active' || M.state.turn !== side) return;
    var cell = t.closest('.k4-cell'), col = t.closest('.k4-col'); if (!col) return;
    var c = +col.dataset.col, r = cell ? +cell.dataset.r : -1, v = r >= 0 ? M.state.board[r][c] : 0, myP = side === 'a' ? 1 : 2;
    if (!card) return send({ col: c });
    if (step === 'target') {
      if (card === 'bomb' && v && v !== myP) { sel.t = [r, c]; step = 'col'; return draw(); }
      if (card === 'swap' && v === myP) { sel.t = [r, c]; step = 'target2'; return draw(); }
      return hint(stepHint(), true);
    }
    if (step === 'target2') { if (v && v !== myP && Math.max(Math.abs(sel.t[0] - r), Math.abs(sel.t[1] - c)) === 1) { sel.t2 = [r, c]; step = 'col'; return draw(); } return hint(stepHint(), true); }
    if (step === 'wall') { sel.wall = c; step = 'col'; return draw(); }
    if (step === 'col' && card === 'double') { sel.col = c; step = 'col2'; return draw(); }
    if (step === 'col2') return send({ card: 'double', col: sel.col, col2: c });
    if (step === 'col') return send({ card: card, col: c, target: card === 'wall' ? sel.wall : sel.t, target2: sel.t2 });
  });
  function bubble(x, em) {
    var el = $('#k4-' + x); if (!el) return;
    var b = document.createElement('div'); b.className = 'fa-pop emoji'; b.textContent = em; b.style.left = '50%'; b.style.top = '30%';
    el.style.position = 'relative'; el.appendChild(b); setTimeout(function () { b.remove(); }, 1300);
  }
  function end() {
    if (endShown) return; endShown = true;
    var w = M.winner, iWon = (w === 1 && side === 'a') || (w === 2 && side === 'b'), bot = M.state.bot;
    hint(iWon ? '🏆 ' + T('Keturi iš eilės! Laimėjai!') : w === 0 ? '🤝 ' + T('Lygiosios!') : '🙇 ' + T('Šįkart laimėjo priešininkas.'));
    $('#k4-end').innerHTML = '<div class="fa-end ' + (iWon ? 'win' : 'lose') + '" style="margin-top:12px"><div class="big">' + (iWon ? '🏆 ' + T('Laimėjai!') : w === 0 ? '🤝 ' + T('Lygiosios!') : '🙇 ' + T('Kitą kartą pavyks!')) + '</div><div class="gm-foot" style="justify-content:center">' +
      (bot ? '<button class="g-btn gold" data-again="' + bot + '">🔁 ' + T('Dar kartą') + '</button>' : '') + '<a class="g-btn" href="' + K.urls.arena + '">⚔️ ' + T('Į areną') + '</a><a class="g-btn" href="' + K.urls.dojo + '">🏯 ' + T('Į dojo') + '</a></div></div>';
  }
  load();
})();
