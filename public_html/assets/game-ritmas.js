// „Kata ritmas“: ritmo žaidimas. Būgnų muzika kuriama naršyklėje (WebAudio), technikos krenta 4 takeliais - spausk laiku.
// Klavišai: D F J K (arba ← ↓ ↑ →), telefone - 4 mygtukai. Laikas skaičiuojamas pagal garso laikrodį, kad būtų tikslu.
(function () {
  var R = window.RITMAS, root = document.getElementById('rt');
  if (!R || !root) return;
  var A = window.GameArt, T = window.tr || function (s) { return s; };
  window.GAME = { catalog: R.catalog };
  var me = R.hero;
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var LANES = [
    { k: 'tsuki', n: 'Tsuki', ic: '👊', c: '#E5484D', keys: ['d', 'arrowleft'] },
    { k: 'uke', n: 'Uke', ic: '🛡', c: '#3B82F6', keys: ['f', 'arrowdown'] },
    { k: 'geri', n: 'Geri', ic: '🦶', c: '#2E8B57', keys: ['j', 'arrowup'] },
    { k: 'kiai', n: 'Kiai!', ic: '💥', c: '#F2A516', keys: ['k', 'arrowright', ' '] }
  ];
  var SONGS = {
    heian: { n: 'Heian Shodan', lvl: '⭐', bpm: 92, bars: 18, dens: 0.42, eighth: 0, dbl: 0, scale: [0, 2, 4, 7, 9], root: 220, seed: 11 },
    bassai: { n: 'Bassai Dai', lvl: '⭐⭐', bpm: 112, bars: 22, dens: 0.55, eighth: 0.18, dbl: 0.05, scale: [0, 3, 5, 7, 10], root: 196, seed: 27 },
    kanku: { n: 'Kanku Dai', lvl: '⭐⭐⭐', bpm: 128, bars: 26, dens: 0.62, eighth: 0.32, dbl: 0.12, scale: [0, 1, 5, 7, 8], root: 233, seed: 53 }
  };
  var WIN = { perfect: 0.06, great: 0.11, good: 0.17 };
  var song = 'heian', ctx = null, G = null, raf = 0;

  function api(data) {
    var post = !!data.action;
    var url = R.api + (post ? '' : ('?' + Object.keys(data).map(function (k) { return k + '=' + encodeURIComponent(data[k]); }).join('&') + '&h=' + me.id));
    if (post) data.h = me.id;
    return fetch(url, { method: post ? 'POST' : 'GET', credentials: 'same-origin', headers: post ? { 'Content-Type': 'application/json', 'X-CSRF-Token': R.csrf } : {}, body: post ? JSON.stringify(data) : undefined })
      .then(function (r) { return r.json(); }).catch(function () { return { ok: false }; });
  }
  function rng(seed) { var s = seed >>> 0; return function () { s = (s * 1664525 + 1013904223) >>> 0; return s / 4294967296; }; }

  // Natų lapas: ketvirtinės (ir aštuntinės sunkesnėse), kartais dvi vienu metu; Kiai - frazės pabaigoje
  function chart(S) {
    var r = rng(S.seed), beat = 60 / S.bpm, notes = [], t0 = beat * 4;   // pirmas taktas - įžanga
    for (var bar = 0; bar < S.bars; bar++) {
      for (var b = 0; b < 4; b++) {
        var t = t0 + (bar * 4 + b) * beat;
        if (b === 3 && bar % 4 === 3) { notes.push({ t: t, l: 3 }); continue; }
        if (r() < S.dens) {
          var l = Math.floor(r() * 3);
          notes.push({ t: t, l: l });
          if (r() < S.dbl) notes.push({ t: t, l: (l + 1 + Math.floor(r() * 2)) % 3 });
        }
        if (r() < S.eighth) notes.push({ t: t + beat / 2, l: Math.floor(r() * 3) });
      }
    }
    notes.forEach(function (n, i) { n.i = i; n.hit = null; });
    return { notes: notes, end: t0 + S.bars * 4 * beat + beat * 2, beat: beat, t0: t0 };
  }

  // ---------- Garsai ----------
  function drum(t, f0, f1, dur, vol) {
    var o = ctx.createOscillator(), g = ctx.createGain();
    o.type = 'sine'; o.frequency.setValueAtTime(f0, t); o.frequency.exponentialRampToValueAtTime(f1, t + dur);
    g.gain.setValueAtTime(vol, t); g.gain.exponentialRampToValueAtTime(0.001, t + dur);
    o.connect(g); g.connect(G.out); o.start(t); o.stop(t + dur + 0.02);
  }
  function noise(t, dur, vol, hp) {
    var len = Math.floor(ctx.sampleRate * dur), buf = ctx.createBuffer(1, len, ctx.sampleRate), d = buf.getChannelData(0);
    for (var i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * (1 - i / len);
    var s = ctx.createBufferSource(), f = ctx.createBiquadFilter(), g = ctx.createGain();
    s.buffer = buf; f.type = 'highpass'; f.frequency.value = hp || 2000; g.gain.value = vol;
    s.connect(f); f.connect(g); g.connect(G.out); s.start(t);
  }
  function pluck(t, freq, vol) {
    var o = ctx.createOscillator(), g = ctx.createGain(), f = ctx.createBiquadFilter();
    o.type = 'triangle'; o.frequency.value = freq; f.type = 'lowpass'; f.frequency.value = 1800;
    g.gain.setValueAtTime(vol, t); g.gain.exponentialRampToValueAtTime(0.001, t + 0.5);
    o.connect(f); f.connect(g); g.connect(G.out); o.start(t); o.stop(t + 0.55);
  }
  function scheduleMusic() {
    var S = SONGS[song], beat = G.ch.beat, ahead = ctx.currentTime + 0.25, r = G.mr;
    while (G.nextBeat < ahead && G.beatN < (S.bars + 1) * 4 + 1) {
      var t = G.nextBeat, b = G.beatN % 4;
      drum(t, b === 0 ? 110 : 80, 40, 0.45, b === 0 ? 0.9 : 0.55);      // didysis taiko - „don“
      noise(t + beat / 2, 0.05, 0.12, 3500);                             // medinis „ka“
      if (G.beatN >= 4) {
        var deg = S.scale[Math.floor(r() * S.scale.length)], oct = r() < 0.3 ? 2 : 1;
        if (r() < 0.75) pluck(t, S.root * oct * Math.pow(2, deg / 12), 0.12);
        if (r() < 0.35) pluck(t + beat / 2, S.root * Math.pow(2, S.scale[Math.floor(r() * S.scale.length)] / 12), 0.08);
      }
      G.nextBeat += beat; G.beatN++;
    }
  }

  // ---------- Ekranai ----------
  function menu() {
    cancelAnimationFrame(raf);
    root.innerHTML = '<div class="fa-top"><a class="g-btn sm" href="' + R.urls.arena + '">← ' + T('Arena') + '</a><span class="fa-mode">🥁 ' + T('Kata ritmas') + '</span></div>' +
      '<div class="g-card rt-intro"><div class="rt-introw"><div class="live rt-me">' + A.hero({ species: me.species, gender: me.gender, look: me.look, equipped: me.equipped, belt: me.belt, name: me.name }) + '</div><div>' +
      '<h2 style="margin:0 0 6px">🥁 ' + T('Kata ritmas') + '</h2><p>' + T('Muša taiko būgnai. Technikos krenta 4 takeliais - paspausk, kai jos pasiekia liniją. Tiksliai laiku - „Puiku!“, serija be klaidų - kombo.') + '</p>' +
      '<div class="rt-keys">' + LANES.map(function (l, i) { return '<span style="--c:' + l.c + '">' + l.ic + ' ' + l.n + ' <kbd>' + ['D', 'F', 'J', 'K'][i] + '</kbd></span>'; }).join('') + '</div>' +
      '<p class="g-empty">' + T('S įvertinimas - dovanų dėžė (vieną kartą kiekvienai katai), A arba S - Taiko būgnas tavo dojo.') + '</p>' +
      '<div class="g-sub">' + T('Kata (daina)') + '</div><div class="ed-seg" style="flex-wrap:wrap">' + Object.keys(SONGS).map(function (k) { return '<button type="button" data-song="' + k + '" class="' + (k === song ? 'sel' : '') + '">' + SONGS[k].n + ' <small>' + SONGS[k].lvl + '</small></button>'; }).join('') + '</div>' +
      '<div class="gm-foot" style="justify-content:flex-start"><button type="button" class="g-btn gold" id="rt-go" style="font-size:1.05rem;padding:10px 22px">▶ ' + T('Pradėti!') + '</button></div></div></div><div id="rt-top"></div></div>';
    root.onclick = function (e) {
      var t = e.target.closest('button'); if (!t) return;
      if (t.dataset.song) { song = t.dataset.song; root.querySelectorAll('[data-song]').forEach(function (b) { b.classList.toggle('sel', b === t); }); top(); }
      if (t.id === 'rt-go') start();
    };
    top();
  }
  function top() {
    api({ top: 1, game: 'ritmas', song: song }).then(function (d) {
      var el = document.getElementById('rt-top'); if (!el || !d.ok) return;
      el.innerHTML = d.top.length ? '<div class="g-sub">🏅 ' + T('Savaitės geriausi') + ' · ' + SONGS[song].n + '</div><ol class="ar-top">' + d.top.map(function (r) { return '<li><span>' + esc(r.name) + '</span><b>' + r.score + '</b></li>'; }).join('') + '</ol>' : '';
    });
  }
  function start() {
    try { ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); ctx.resume(); } catch (e) { ctx = null; }
    if (!ctx) { alert(T('Ši naršyklė nepalaiko garso.')); return; }
    var S = SONGS[song];
    G = { ch: chart(S), score: 0, combo: 0, maxCombo: 0, cnt: { perfect: 0, great: 0, good: 0, miss: 0 }, mr: rng(S.seed * 7), out: ctx.createGain(), pressed: [0, 0, 0, 0], fx: [], over: false };
    G.out.gain.value = 0.8; G.out.connect(ctx.destination);
    G.start = ctx.currentTime + 0.6; G.nextBeat = G.start; G.beatN = 0;
    root.innerHTML = '<div class="fa-top"><button type="button" class="g-btn sm" id="rt-quit">← ' + T('Baigti') + '</button><span class="fa-mode">🥁 ' + esc(S.n) + '</span><span class="sp"></span><span class="fa-timer" id="rt-score">0</span></div>' +
      '<div class="rt-stage"><canvas id="rt-cv"></canvas><div class="rt-hero live" id="rt-hero">' + A.hero({ species: me.species, gender: me.gender, look: me.look, equipped: me.equipped, belt: me.belt, name: me.name }) + '</div><div class="rt-judge" id="rt-judge"></div><div class="rt-combo" id="rt-combo"></div></div>' +
      '<div class="rt-pads">' + LANES.map(function (l, i) { return '<button type="button" class="rt-pad" data-lane="' + i + '" style="--c:' + l.c + '">' + l.ic + '<small>' + l.n + '</small></button>'; }).join('') + '</div>';
    document.getElementById('rt-quit').onclick = function () { stop(); menu(); };
    root.onclick = null;
    root.onpointerdown = function (e) { var b = e.target.closest('[data-lane]'); if (b) { e.preventDefault(); press(+b.dataset.lane); } };
    document.onkeydown = function (e) {
      if (!G || G.over || e.repeat) return;
      var k = e.key.toLowerCase();
      LANES.forEach(function (l, i) { if (l.keys.indexOf(k) >= 0) { e.preventDefault(); press(i); } });
    };
    resize(); window.onresize = resize;
    raf = requestAnimationFrame(frame);
  }
  function stop() { if (G) { G.over = true; try { G.out.disconnect(); } catch (e) {} } cancelAnimationFrame(raf); document.onkeydown = null; root.onpointerdown = null; }
  function resize() {
    var cv = document.getElementById('rt-cv'); if (!cv) return;
    var r = cv.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
    cv.width = Math.round(r.width * dpr); cv.height = Math.round(r.height * dpr); G.dpr = dpr;
  }
  function songTime() { return ctx.currentTime - G.start; }
  function press(lane) {
    var now = songTime(), best = null, bd = 9;
    G.pressed[lane] = performance.now();
    G.ch.notes.forEach(function (n) { if (n.l === lane && !n.hit) { var d = Math.abs(n.t - now); if (d < bd) { bd = d; best = n; } } });
    var pad = root.querySelector('[data-lane="' + lane + '"]'); if (pad) { pad.classList.add('on'); setTimeout(function () { pad.classList.remove('on'); }, 110); }
    if (!best || bd > WIN.good) return;
    var j = bd <= WIN.perfect ? 'perfect' : bd <= WIN.great ? 'great' : 'good';
    best.hit = j; G.cnt[j]++; G.combo++; G.maxCombo = Math.max(G.maxCombo, G.combo);
    G.score += { perfect: 300, great: 200, good: 100 }[j] * (1 + Math.min(G.combo, 50) / 50);
    drum(ctx.currentTime, lane === 3 ? 160 : 220, 70, 0.18, 0.5);
    judge(j); pose(lane);
    G.fx.push({ l: lane, t: performance.now(), j: j });
  }
  function judge(j) {
    var el = document.getElementById('rt-judge');
    el.textContent = { perfect: T('Puiku!'), great: T('Gerai!'), good: T('Neblogai'), miss: T('Praleista') }[j];
    el.className = 'rt-judge show ' + j; void el.offsetWidth; el.className = 'rt-judge show ' + j + ' pop';
    var c = document.getElementById('rt-combo'); c.textContent = G.combo > 2 ? G.combo + ' ' + T('kombo') : '';
    document.getElementById('rt-score').textContent = Math.round(G.score);
  }
  function pose(lane) {
    var h = document.getElementById('rt-hero'), cls = ['p-tsuki', 'p-uke', 'p-geri', 'p-kiai'][lane];
    h.classList.remove('p-tsuki', 'p-uke', 'p-geri', 'p-kiai'); void h.offsetWidth; h.classList.add(cls);
  }
  function frame() {
    if (!G || G.over) return;
    scheduleMusic();
    var now = songTime(), cv = document.getElementById('rt-cv'); if (!cv) return;
    var x = cv.getContext('2d'), W = cv.width, H = cv.height, d = G.dpr || 1;
    var lw = W / 4, hitY = H - 70 * d, speed = H * 0.75;   // px per second
    x.clearRect(0, 0, W, H);
    LANES.forEach(function (l, i) {
      x.fillStyle = i % 2 ? 'rgba(255,255,255,.05)' : 'rgba(255,255,255,.09)'; x.fillRect(i * lw, 0, lw, H);
      var age = performance.now() - G.pressed[i];
      if (age < 160) { x.fillStyle = hexA(l.c, 0.25 * (1 - age / 160)); x.fillRect(i * lw, 0, lw, H); }
    });
    // linija
    x.strokeStyle = 'rgba(255,255,255,.85)'; x.lineWidth = 4 * d; x.beginPath(); x.moveTo(0, hitY); x.lineTo(W, hitY); x.stroke();
    LANES.forEach(function (l, i) { circle(x, i * lw + lw / 2, hitY, 26 * d, 'rgba(0,0,0,.35)', hexA(l.c, 0.9), 3 * d); x.fillStyle = '#fff'; x.font = (22 * d) + 'px sans-serif'; x.textAlign = 'center'; x.textBaseline = 'middle'; x.fillText(l.ic, i * lw + lw / 2, hitY + 1); });
    // natos
    G.ch.notes.forEach(function (n) {
      if (n.hit && n.hit !== 'miss') return;
      var dt = n.t - now;
      if (!n.hit && dt < -WIN.good) { n.hit = 'miss'; G.cnt.miss++; G.combo = 0; judge('miss'); }
      if (n.hit === 'miss' || dt > 2.2) return;
      var y = hitY - dt * speed, cx = n.l * lw + lw / 2, l = LANES[n.l];
      circle(x, cx, y, 24 * d, l.c, '#fff', 3 * d);
      x.fillStyle = '#fff'; x.font = (20 * d) + 'px sans-serif'; x.textAlign = 'center'; x.textBaseline = 'middle'; x.fillText(l.ic, cx, y + 1);
    });
    // pataikymo žiedai
    G.fx = G.fx.filter(function (f) { var a = (performance.now() - f.t) / 300; if (a > 1) return false; circle(x, f.l * lw + lw / 2, hitY, (26 + a * 30) * d, null, hexA(f.j === 'perfect' ? '#FFD75E' : '#FFFFFF', 1 - a), 4 * d); return true; });
    if (now > G.ch.end) return finish();
    raf = requestAnimationFrame(frame);
  }
  function circle(x, cx, cy, r, fill, stroke, lw) { x.beginPath(); x.arc(cx, cy, r, 0, Math.PI * 2); if (fill) { x.fillStyle = fill; x.fill(); } if (stroke) { x.strokeStyle = stroke; x.lineWidth = lw; x.stroke(); } }
  function hexA(h, a) { var n = parseInt(h.slice(1), 16); return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')'; }
  function finish() {
    stop();
    var c = G.cnt, total = c.perfect + c.great + c.good + c.miss, acc = total ? (c.perfect + c.great * 0.7 + c.good * 0.4) / total : 0;
    var grade = acc >= 0.93 && c.miss <= 2 ? 'S' : acc >= 0.85 ? 'A' : acc >= 0.72 ? 'B' : acc >= 0.55 ? 'C' : 'D', score = Math.round(G.score);
    var html = '<div class="fa-end ' + ('SAB'.indexOf(grade) >= 0 ? 'win' : '') + '" style="margin-top:12px"><div class="big">' + { S: '🌟 S', A: '🏆 A', B: '👏 B', C: '🙂 C', D: '💪 D' }[grade] + ' · ' + esc(SONGS[song].n) + '</div>' +
      '<div class="gr-stats"><div><b>' + score + '</b>' + T('Taškai') + '</div><div><b>' + Math.round(acc * 100) + '%</b>' + T('Tikslumas') + '</div><div><b>' + G.maxCombo + '</b>' + T('Didžiausias kombo') + '</div><div><b>' + c.perfect + '</b>' + T('Puiku!') + '</div><div><b>' + c.great + '</b>' + T('Gerai!') + '</div><div><b>' + c.good + '</b>' + T('Neblogai') + '</div><div><b>' + c.miss + '</b>' + T('Praleista') + '</div></div>' +
      '<div id="rt-prize"></div><div class="gm-foot" style="justify-content:center"><button class="g-btn gold" id="rt-again">🔁 ' + T('Dar kartą') + '</button><button class="g-btn" id="rt-menu">🎵 ' + T('Kita kata') + '</button><a class="g-btn" href="' + R.urls.dojo + '">🏯 ' + T('Į dojo') + '</a></div></div>';
    root.querySelector('.rt-pads').outerHTML = html;
    document.getElementById('rt-again').onclick = start;
    document.getElementById('rt-menu').onclick = menu;
    api({ action: 'score', game: 'ritmas', song: song, score: score, grade: grade }).then(function (d) {
      var el = document.getElementById('rt-prize'); if (!el || !d.ok) return;
      if (d.prize === 'box') el.innerHTML = '<p style="font-weight:800">🎁 ' + T('S įvertinimas - dovanų dėžė laukia tavo dojo!') + '</p>';
      else if (d.prize === 'drum') el.innerHTML = '<p style="font-weight:800">🥁 ' + T('Gavai Taiko būgną savo dojo!') + '</p>';
    });
  }
  menu();
})();
