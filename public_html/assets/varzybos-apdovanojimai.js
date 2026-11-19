(function () {
  'use strict';
  // Apdovanojimų planšetė (varzybos-apdovanojimai.php): viršuje - baigtos ir dar neapdovanotos rungtys (anksčiausiai baigtos pirmos),
  // „✓ Apdovanota“ - nukeliama žemyn. Atsinaujina kas 4 s; nauja baigta rungtis - pyptelėjimas ir paryškinimas.
  var CFG = window.APD || {}, root = document.getElementById('apd');
  var cats = [], AW = {}, failed = 0, seenReady = null, fresh = {}, lastHtml = '', audio = null, busy = false;
  var DISC = { kata: 'Kata', kumite: 'Kumite', fukugo: 'Fukugo', kihon: 'Kihon', enbu: 'Enbu', sumo: 'Sumo' };
  var MEDAL = { 1: '🥇', 2: '🥈', 3: '🥉' };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  // #122: kiekviena baigta rungtis - iš karto čia (rezultatai jau suskaičiuoti), pirmiausia anksčiausiai baigtos.
  // „✓ Apdovanota“ - vienu paspaudimu nukeliama į „Jau apdovanota“ (matosi, kad būtų galima atsekti klaidas, „↺ Grąžinti“).
  function catState(c) {
    var a = AW[c.id] && AW[c.id].st;
    if (a === 'done') return 'awarded';
    if (c.finished) return 'ready';
    return c.done > 0 ? 'run' : 'wait';
  }
  function at(c, k) { return (AW[c.id] && AW[c.id][k]) || ''; }
  function hhmm(ts) { var m = String(ts || '').match(/(\d{2}):(\d{2})/); return m ? m[1] + ':' + m[2] : ''; }
  function placesHtml(c) {
    if (!c.places.length) return '<p class="muted small">Prizininkų nėra</p>';
    return '<ol class="apd-places">' + c.places.slice().sort(function (a, b) { return a.place - b.place; }).map(function (p) {
      return '<li><span class="apd-medal">' + (MEDAL[p.place] || p.place) + '</span><b>' + esc(p.name) + '</b> <span class="muted">' + esc(p.club) + '</span></li>';
    }).join('') + '</ol>';
  }
  function readyCard(c) {
    var t = hhmm(at(c, 'sent'));
    return '<section class="apd-c ready' + (fresh[c.id] ? ' fresh' : '') + '"><div class="apd-c-top"><span class="disc d-' + esc(c.disc) + '"></span><div class="apd-c-name"><h2>' + esc(c.name) + '</h2>' +
      '<span class="muted small">' + (c.groupName && c.groupName !== c.name ? esc(c.groupName) + ' · ' : '') + (c.tatami ? esc(c.tatami) + ' · ' : '') + (t ? 'baigta ' + t : 'ką tik baigta') + '</span></div></div>' +
      placesHtml(c) + '<div class="apd-btns"><button type="button" class="apd-btn apd-done" data-done="' + esc(c.id) + '">✓ Apdovanota</button></div></section>';
  }
  var doneOpen = false, runOpen = false;
  function render() {
    var ready = cats.filter(function (c) { return catState(c) === 'ready'; }), run = cats.filter(function (c) { var s = catState(c); return s === 'run' || s === 'wait'; }),
      done = cats.filter(function (c) { return catState(c) === 'awarded'; });
    ready.sort(function (a, b) { var x = at(a, 'sent') || '9', y = at(b, 'sent') || '9'; return x < y ? -1 : x > y ? 1 : 0; });
    done.sort(function (a, b) { var x = at(a, 'done'), y = at(b, 'done'); return x > y ? -1 : x < y ? 1 : 0; });
    // Nauja baigta rungtis (atsirado nuo paskutinio karto) - pyptelėjimas ir paryškinimas
    var readyKeys = ready.map(function (c) { return c.id; });
    if (seenReady) readyKeys.forEach(function (k) { if (seenReady.indexOf(k) === -1) { fresh[k] = Date.now(); beep(); } });
    seenReady = readyKeys;
    Object.keys(fresh).forEach(function (k) { if (Date.now() - fresh[k] > 20000) delete fresh[k]; });
    var html = '<div class="apd-sum"><span class="apd-n ready"><b>' + ready.length + '</b> apdovanoti dabar</span><span class="apd-n wait"><b>' + run.length + '</b> dar vyksta</span><span class="apd-n awarded"><b>' + done.length + '</b> apdovanota</span></div>';
    html += ready.length ? '<div class="apd-list">' + ready.map(readyCard).join('') + '</div>'
      : '<p class="apd-empty">Kol kas nėra ką apdovanoti. Kai rungtis baigsis, ji čia atsiras pati (su rezultatais).</p>';
    if (done.length) html += '<details class="apd-donebox"' + (doneOpen ? ' open' : '') + '><summary>✓ Jau apdovanota (' + done.length + ')</summary><ul class="apd-donelist">' + done.map(function (c) {
      return '<li><div><b>' + esc(c.name) + '</b> <span class="muted small">' + hhmm(at(c, 'done')) + '</span>' + placesHtml(c) + '</div><button type="button" class="apd-btn apd-undo" data-undo="' + esc(c.id) + '">↺ Grąžinti</button></li>';
    }).join('') + '</ul></details>';
    if (run.length) html += '<details class="apd-runbox"' + (runOpen ? ' open' : '') + '><summary>Dar vyksta (' + run.length + ')</summary><ul class="apd-runlist">' + run.map(function (c) {
      return '<li><span class="disc d-' + esc(c.disc) + '"></span><b>' + esc(c.name) + '</b><span class="muted small">' + (c.done > 0 ? 'Vyksta ' + c.done + '/' + c.total : 'Laukiama') + (c.tatami ? ' · ' + esc(c.tatami) : '') + '</span></li>';
    }).join('') + '</ul></details>';
    html += '<p class="hint">Atsinaujina pats kas kelias sekundes.' + (failed > 2 ? ' <b>Nėra ryšio - bandoma iš naujo…</b>' : '') + '</p>';
    if (html === lastHtml) return;
    lastHtml = html; root.innerHTML = html;
    var db = root.querySelector('.apd-donebox'), rb = root.querySelector('.apd-runbox');
    if (db) db.addEventListener('toggle', function () { doneOpen = db.open; });
    if (rb) rb.addEventListener('toggle', function () { runOpen = rb.open; });
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
    ids.forEach(function (id) { AW[id] = AW[id] || {}; AW[id].st = status; });
    lastHtml = ''; render();
    fetch(CFG.api, { method: 'POST', cache: 'no-store', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ cats: ids, status: status }) })
      .then(function (r) { return r.json(); }).then(function (d) { busy = false; apply(d); })
      .catch(function () { busy = false; failed++; alert('Nepavyko - nėra ryšio. Bandykite dar kartą.'); load(); });
  }
  root.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    if (b.dataset.done) post([b.dataset.done], 'done');
    else if (b.dataset.undo) { var c = cats.find(function (x) { return x.id === b.dataset.undo; }); if (confirm('Grąžinti „' + (c ? c.name : '') + '“ - vėl laukia apdovanojimo?')) post([b.dataset.undo], 'sent'); }
  });
  load();
  setInterval(function () { if (!document.hidden) load(); }, 4000);
})();
