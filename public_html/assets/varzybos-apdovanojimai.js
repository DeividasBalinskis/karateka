(function () {
  'use strict';
  // Apdovanojimų planšetė (varzybos-apdovanojimai.php). Amžiaus grupės: pilka - dar vyksta ar neišsiųsta, žalia - galima apdovanoti,
  // „Užbaigti“ - apdovanota. Atsinaujina kas 4 s; nauja žalia grupė - pyptelėjimas ir paryškinimas.
  var CFG = window.APD || {}, root = document.getElementById('apd');
  var cats = [], AW = {}, failed = 0, seenReady = null, fresh = {}, lastHtml = '', audio = null, busy = false;
  var DISC = { kata: 'Kata', kumite: 'Kumite', fukugo: 'Fukugo', kihon: 'Kihon', enbu: 'Enbu', sumo: 'Sumo' };
  var MEDAL = { 1: '🥇', 2: '🥈', 3: '🥉' };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  function catState(c) {
    var a = AW[c.id] && AW[c.id].st;
    if (a === 'done') return 'awarded';
    if (a === 'sent') return 'sent';
    if (c.finished) return 'fin';
    return c.done > 0 ? 'run' : 'wait';
  }
  var LABEL = { wait: 'Laukiama', run: 'Vyksta', fin: 'Baigta - sekretoriatas dar neišsiuntė', sent: 'Išsiųsta - galima apdovanoti', awarded: 'Apdovanota ✓' };
  function groups() {
    var map = {}, order = [];
    cats.forEach(function (c) {
      if (!map[c.group]) { map[c.group] = { key: c.group, name: c.groupName, cats: [] }; order.push(map[c.group]); }
      map[c.group].cats.push(c);
    });
    order.forEach(function (g) {
      var st = g.cats.map(catState);
      g.awarded = st.every(function (x) { return x === 'awarded'; });
      g.ready = !g.awarded && st.every(function (x) { return x === 'sent' || x === 'awarded'; });
      g.anySent = st.some(function (x) { return x === 'sent'; });
      g.sentAt = g.cats.reduce(function (m, c) { var t = AW[c.id] && AW[c.id].sent; return t && (!m || t > m) ? t : m; }, '');
      g.doneAt = g.cats.reduce(function (m, c) { var t = AW[c.id] && AW[c.id].done; return t && (!m || t > m) ? t : m; }, '');
    });
    return order;
  }
  function placesHtml(c) {
    if (!c.places.length) return '';
    return '<ol class="apd-places">' + c.places.slice().sort(function (a, b) { return a.place - b.place; }).map(function (p) {
      return '<li><span class="apd-medal">' + (MEDAL[p.place] || p.place) + '</span><b>' + esc(p.name) + '</b> <span class="muted">' + esc(p.club) + '</span></li>';
    }).join('') + '</ol>';
  }
  function catHtml(c, showPlaces) {
    var st = catState(c);
    return '<div class="apd-cat st-' + st + '"><div class="apd-cat-top"><span class="disc d-' + esc(c.disc) + '"></span><b>' + esc(c.name) + '</b>' +
      '<span class="apd-st">' + (st === 'run' ? 'Vyksta ' + c.done + '/' + c.total : LABEL[st]) + '</span>' + (c.tatami && (st === 'run' || st === 'wait') ? '<span class="muted small">' + esc(c.tatami) + '</span>' : '') + '</div>' +
      (showPlaces && (st === 'sent' || st === 'awarded' || st === 'fin') ? placesHtml(c) : '') + '</div>';
  }
  function groupHtml(g) {
    var cls = g.awarded ? 'awarded' : g.ready ? 'ready' : 'wait';
    var head = '<div class="apd-g-top"><h2>' + esc(g.name) + '</h2><span class="apd-g-st">' + (g.awarded ? '✓ Apdovanota' : g.ready ? 'Galima apdovanoti' : 'Dar vyksta') + '</span></div>';
    var body = g.cats.map(function (c) { return catHtml(c, !g.awarded); }).join('');
    var btns = '';
    if (g.ready) btns = '<button type="button" class="apd-btn apd-done" data-done="' + esc(g.key) + '">✓ Apdovanota - užbaigti</button>';
    else if (g.awarded) btns = '<button type="button" class="apd-btn apd-undo" data-undo="' + esc(g.key) + '">↺ Grąžinti</button>';
    else if (g.anySent) btns = '<button type="button" class="apd-btn apd-force" data-force="' + esc(g.key) + '" title="Apdovanoti išsiųstas rungtis nelaukiant kitų">Apdovanoti išsiųstas dabar</button>';
    return '<section class="apd-g ' + cls + (fresh[g.key] ? ' fresh' : '') + '">' + head + '<div class="apd-cats">' + body + '</div>' + (btns ? '<div class="apd-btns">' + btns + '</div>' : '') + '</section>';
  }
  function render() {
    var gs = groups(), ready = gs.filter(function (g) { return g.ready; }), wait = gs.filter(function (g) { return !g.ready && !g.awarded; }), done = gs.filter(function (g) { return g.awarded; });
    ready.sort(function (a, b) { return a.sentAt < b.sentAt ? -1 : a.sentAt > b.sentAt ? 1 : 0; });
    done.sort(function (a, b) { return a.doneAt > b.doneAt ? -1 : a.doneAt < b.doneAt ? 1 : 0; });
    // Nauja žalia grupė (atsirado nuo paskutinio karto) - pyptelėjimas ir paryškinimas
    var readyKeys = ready.map(function (g) { return g.key; });
    if (seenReady) readyKeys.forEach(function (k) { if (seenReady.indexOf(k) === -1) { fresh[k] = Date.now(); beep(); } });
    seenReady = readyKeys;
    Object.keys(fresh).forEach(function (k) { if (Date.now() - fresh[k] > 20000) delete fresh[k]; });
    var html = '<div class="apd-sum"><span class="apd-n ready"><b>' + ready.length + '</b> galima apdovanoti</span><span class="apd-n wait"><b>' + wait.length + '</b> dar vyksta</span><span class="apd-n awarded"><b>' + done.length + '</b> apdovanota</span></div>';
    html += ready.length ? '<div class="apd-list">' + ready.map(groupHtml).join('') + '</div>' : '<p class="apd-empty">Kol kas nėra ką apdovanoti. Kai sekretoriatas išsiųs visas amžiaus grupės rungtis, grupė čia užsidegs žaliai.</p>';
    if (wait.length) html += '<h3 class="apd-h">Dar vyksta</h3><div class="apd-list">' + wait.map(groupHtml).join('') + '</div>';
    if (done.length) html += '<details class="apd-donebox"><summary>Apdovanota (' + done.length + ')</summary><div class="apd-list">' + done.map(groupHtml).join('') + '</div></details>';
    html += '<p class="hint">Atsinaujina pats kas kelias sekundes.' + (failed > 2 ? ' <b>Nėra ryšio - bandoma iš naujo…</b>' : '') + ' Pilka grupė tampa žalia, kai baigtos ir išsiųstos visos jos rungtys (kata ir kumite).</p>';
    if (html === lastHtml) return;
    var openDone = root.querySelector('.apd-donebox') && root.querySelector('.apd-donebox').open;
    lastHtml = html; root.innerHTML = html;
    if (openDone) root.querySelector('.apd-donebox').open = true;
  }
  function beep() {
    try { if (navigator.vibrate) navigator.vibrate([120, 80, 120]); } catch (e) {}
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      var o = audio.createOscillator(), g = audio.createGain(); o.frequency.value = 988; o.connect(g); g.connect(audio.destination); g.gain.value = 0.18;
      o.start(); setTimeout(function () { o.frequency.value = 1319; }, 140); setTimeout(function () { o.stop(); }, 320);
    } catch (e) {}
  }
  // Garsas naršyklėje leidžiamas tik po pirmo palietimo
  document.addEventListener('click', function () { try { audio = audio || new (window.AudioContext || window.webkitAudioContext)(); if (audio.resume) audio.resume(); } catch (e) {} }, { once: true });

  function apply(d) { if (d && d.ok) { cats = d.cats || []; AW = d.awards && !Array.isArray(d.awards) ? d.awards : {}; failed = 0; } render(); }
  function load() {
    if (busy) return Promise.resolve();
    return fetch(CFG.api + '&json=1', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(apply).catch(function () { failed++; render(); });
  }
  function post(ids, status) {
    busy = true;
    ids.forEach(function (id) { if (AW[id]) AW[id].st = status; });
    lastHtml = ''; render();
    fetch(CFG.api, { method: 'POST', cache: 'no-store', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ cats: ids, status: status }) })
      .then(function (r) { return r.json(); }).then(function (d) { busy = false; apply(d); })
      .catch(function () { busy = false; failed++; alert('Nepavyko - nėra ryšio. Bandykite dar kartą.'); load(); });
  }
  root.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    var key = b.dataset.done || b.dataset.undo || b.dataset.force; if (!key) return;
    var g = groups().find(function (x) { return x.key === key; }); if (!g) return;
    if (b.dataset.done) post(g.cats.filter(function (c) { return catState(c) === 'sent'; }).map(function (c) { return c.id; }), 'done');
    else if (b.dataset.undo) { if (confirm('Grąžinti „' + g.name + '“ - vėl laukia apdovanojimo?')) post(g.cats.map(function (c) { return c.id; }), 'sent'); }
    else if (confirm('Apdovanoti išsiųstas „' + g.name + '“ rungtis dabar? Kitos šios grupės rungtys liks laukti.')) post(g.cats.filter(function (c) { return catState(c) === 'sent'; }).map(function (c) { return c.id; }), 'done');
  });
  load();
  setInterval(function () { if (!document.hidden) load(); }, 4000);
})();
