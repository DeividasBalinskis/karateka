(function () {
  'use strict';
  // Varžybos gyvai (varzybos-gyvai.php): žiūrovų puslapis ir tatamio ekranas. Duomenys - iš serverio kas 1-3 s,
  // o tame pačiame kompiuteryje atidarytas ekranas gauna kovos skydelio pakeitimus iškart (BroadcastChannel).
  var CFG = window.GYVAI || {}, root = document.getElementById('gyvai');
  var data = null, offset = 0, local = {}, failed = 0;
  var MODE = { kumite: 'Kumite', team: 'Komandinis kumite', sumo: 'Sumo', flags: 'Vėliavėlės', score2: 'Kata + Pritaikymas' };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function fmt(ms) { var s = Math.ceil(Math.max(0, ms) / 1000), m = Math.floor(s / 60); s %= 60; return m + ':' + (s < 10 ? '0' : '') + s; }

  try {
    var bc = new BroadcastChannel('taika-' + CFG.id);
    bc.onmessage = function (e) { var d = e.data; if (d && d.area !== undefined) { local[d.area] = { data: d, got: Date.now() }; render(); } };
  } catch (e) {}

  // Kas vyksta ant tatamio: naujesnis iš dviejų šaltinių (serveris arba tas pats kompiuteris)
  function liveFor(area) {
    var srv = data && data.live && data.live[area], loc = local[area], pick = null;
    if (srv) pick = { d: srv.data, src: 'srv', ms: srv.ms };
    if (loc && (!pick || (loc.data.at || 0) >= (pick.d.at || 0))) pick = { d: loc.data, src: 'loc', got: loc.got };
    if (!pick || pick.d.idle) return null;
    var age = pick.src === 'srv' ? (Date.now() + offset) - pick.ms : Date.now() - pick.got;
    if (age > 30 * 60000) return null;   // senesnė nei 30 min. - nebeaktuali
    return pick;
  }
  function timeLeft(pick) {
    var t = pick.d.timer; if (!t) return null;
    if (!t.running) return t.remaining;
    var elapsed = pick.src === 'srv' ? (Date.now() + offset) - pick.ms : Date.now() - pick.d.at;
    return Math.max(0, t.remaining - elapsed);
  }
  function area(id) { return ((data && data.areas) || []).find(function (a) { return +a.id === +id; }) || null; }

  // Kas eina toliau - be tos kovos, kuri vyksta dabar
  function upNext(a) {
    var pick = a && liveFor(a.id), d = pick && pick.d;
    return (a && a.next || []).filter(function (m) { return !d || d.winner || !(m.cat === d.cat && m.a === d.aka.name && m.b === d.shiro.name); });
  }
  function nextList(a, n, cls) {
    var list = upNext(a).slice(0, n);
    if (!list.length) return '<p class="muted">Kovų daugiau nėra.</p>';
    return '<ol class="' + cls + '">' + list.map(function (m) {
      return '<li><span class="nx-cat"><span class="disc d-' + esc(m.disc) + '"></span>' + esc(m.cat) + ' · ' + (m.no ? m.no + ' · ' : '') + esc(m.title) + '</span>' +
        '<span class="nx-who"><b class="aka-t">' + esc(m.a) + '</b>' + (m.b ? ' <span class="muted">prieš</span> <b>' + esc(m.b) + '</b>' : '') + '</span></li>';
    }).join('') + '</ol>';
  }
  function partsHtml(p) {
    var row = function (vals, drop, label, sum) {
      return '<div class="sp-part"><span>' + label + '</span><span class="sp-vals">' + (vals || []).map(function (v, i) { return '<i class="' + (drop.indexOf(i) >= 0 ? 'dropped' : '') + '">' + (v == null ? '–' : esc(v)) + '</i>'; }).join('') + '</span><b>' + (sum == null ? '–' : sum.toFixed(1)) + '</b></div>';
    };
    return row(p.k, p.dk || [], 'Kata', p.tk) + row(p.p, p.dp || [], 'Pritaikymas', p.tp) +
      '<div class="sp-total">' + (p.tk == null || p.tp == null ? '–' : (p.tk + p.tp).toFixed(1)) + '</div>';
  }

  // ---------- Tatamio ekranas (televizorius) ----------
  function renderScreen() {
    var a = area(CFG.area), pick = liveFor(CFG.area), name = a ? a.name : (pick ? pick.d.areaName : 'TATAMIS');
    if (!pick) {
      root.innerHTML = '<div class="scr-idle"><div class="scr-area">' + esc(name) + '</div><div class="scr-title">' + esc(CFG.title) + '</div>' +
        '<h2>Toliau</h2>' + nextList(a, 5, 'scr-next') + (a && a.end ? '<p class="scr-end">Pabaiga apie ' + esc(a.end) + '</p>' : '') + '</div>';
      return;
    }
    var d = pick.d, left = timeLeft(pick), win = d.winner;
    var side = function (s) {
      var who = s === 'a' ? d.aka : d.shiro, sc = s === 'a' ? d.sa : d.sb, now = s === 'a' ? d.akaNow : d.shiroNow;
      return '<div class="scr-side ' + (s === 'a' ? 'aka' : 'shiro') + (win === s ? ' won' : win ? ' lost' : '') + '"><div class="scr-tag">' + (s === 'a' ? 'AKA' : 'SHIRO') + (win === s ? ' · LAIMĖJO' : '') + '</div>' +
        '<div class="scr-name">' + esc(who.name) + '</div><div class="scr-club">' + esc(who.club) + '</div>' + (now ? '<div class="scr-now">Kovoja: <b>' + esc(now) + '</b></div>' : '') +
        (d.mode === 'score2' ? '<div class="scr-parts">' + partsHtml(d.parts[s]) + '</div>' : '<div class="scr-score">' + sc + '</div>') + '</div>';
    };
    root.innerHTML = '<div class="scr-bout"><div class="scr-top"><span class="scr-area">' + esc(name) + '</span><span><span class="disc d-' + esc(d.disc) + '"></span>' + esc(d.cat) + '</span><span>' + esc(d.round) + '</span>' +
      (d.bout ? '<span class="scr-pill">Kova ' + esc(d.bout) + '</span>' : '') + (d.to ? '<span class="scr-pill">iki ' + d.to + '</span>' : '') + '</div>' +
      '<div class="scr-main">' + side('a') + (left !== null && d.mode !== 'score2' ? '<div class="scr-timer' + (left <= 0 ? ' over' : left <= 15000 ? ' last' : '') + '" id="scr-timer">' + fmt(left) + '</div>' : '') + side('b') + '</div>' +
      '<div class="scr-foot"><b>Toliau:</b> ' + (upNext(a).slice(0, 2).map(function (m) { return esc(m.a) + (m.b ? ' – ' + esc(m.b) : '') + ' <span class="muted">(' + esc(m.cat) + ')</span>'; }).join(' · ') || '—') + '</div></div>';
  }

  // ---------- Žiūrovams (telefone) ----------
  function renderViewer() {
    var areas = (data && data.areas) || [];
    var html = '<div class="gv-areas">' + areas.map(function (a) {
      var pick = liveFor(a.id), now = '';
      if (pick) {
        var d = pick.d, left = timeLeft(pick);
        now = '<div class="gv-now"><div class="gv-now-top"><span class="live-dot"></span> Dabar · ' + esc(d.cat) + ' · ' + esc(d.round) + (d.bout ? ' · kova ' + esc(d.bout) : '') + '</div>' +
          '<div class="gv-board"><span class="gv-n aka-t">' + esc(d.aka.name) + '</span><b class="gv-s">' + (d.mode === 'score2' ? (d.parts.a.tk != null && d.parts.a.tp != null ? (d.parts.a.tk + d.parts.a.tp).toFixed(1) : '–') : d.sa) + '</b>' +
          (left !== null && d.mode !== 'score2' ? '<span class="gv-t" data-timer="' + a.id + '">' + fmt(left) + '</span>' : '<span class="gv-t">:</span>') +
          '<b class="gv-s">' + (d.mode === 'score2' ? (d.parts.b.tk != null && d.parts.b.tp != null ? (d.parts.b.tk + d.parts.b.tp).toFixed(1) : '–') : d.sb) + '</b><span class="gv-n">' + esc(d.shiro.name) + '</span></div>' +
          (d.winner ? '<div class="gv-win">Laimėjo ' + esc(d.winner === 'a' ? d.aka.name : d.shiro.name) + '</div>' : '') + '</div>';
      }
      return '<section class="panel gv-area"><h2>' + esc(a.name) + '<span class="spacer"></span><span class="muted small">pabaiga apie ' + esc(a.end) + '</span></h2>' + now +
        '<div class="gv-sub">Toliau</div>' + nextList(a, 4, 'gv-next') +
        '<details class="gv-cats"><summary>Kategorijos (' + a.cats.length + ')</summary><ul>' + a.cats.map(function (c) {
          var pct = c.total ? Math.round(c.done / c.total * 100) : 0;
          return '<li><span class="gv-time">' + esc(c.start) + '</span><span><span class="disc d-' + esc(c.disc) + '"></span>' + esc(c.name) + '</span><span class="gv-prog' + (pct === 100 ? ' done' : '') + '"><i style="width:' + pct + '%"></i></span></li>';
        }).join('') + '</ul></details></section>';
    }).join('') + '</div>';
    var medals = (data && data.medals) || [], fin = ((data && data.cats) || []).filter(function (c) { return c.places.length; });
    html += '<section class="panel gv-res"><h2>Klubų medaliai</h2>' + (medals.length ? '<table><thead><tr><th class="num">#</th><th>Klubas</th><th class="num">🥇</th><th class="num">🥈</th><th class="num">🥉</th></tr></thead><tbody>' +
      medals.slice(0, 15).map(function (m, i) { return '<tr><td class="num">' + (i + 1) + '</td><td>' + esc(m.club) + '</td><td class="num">' + m.g + '</td><td class="num">' + m.s + '</td><td class="num">' + m.b + '</td></tr>'; }).join('') + '</tbody></table>'
      : '<p class="muted" style="padding:10px 14px;">Medalių dar nėra.</p>') + '</section>';
    if (fin.length) {
      html += '<section class="panel gv-res"><h2>Prizininkai</h2><div class="res-cats">' + fin.map(function (c) {
        return '<div class="res-cat"><div class="res-cat-top"><strong><span class="disc d-' + esc(c.disc) + '"></span>' + esc(c.name) + '</strong>' + (c.finished ? '<span class="pill ok">Baigta</span>' : '') + '</div>' +
          c.places.map(function (p) { return '<div class="rp"><span class="medal ' + ['', 'g', 's', 'b'][p.place] + '">' + p.place + '</span><span>' + esc(p.name) + ' <span class="club">' + esc(p.club) + '</span></span></div>'; }).join('') + '</div>';
      }).join('') + '</div></section>';
    }
    html += '<p class="hint">Atsinaujina pats. Vaikų ir jaunių iki 18 m. rodomas vardas ir pavardės pirmoji raidė.' + (failed > 2 ? ' <b>Nėra ryšio - bandoma iš naujo…</b>' : '') + '</p>';
    // Neperpiešiama, kol žmogus skaito išskleistą kategorijų sąrašą
    var open = [].slice.call(root.querySelectorAll('details.gv-cats')).map(function (x) { return x.open; });
    root.innerHTML = html;
    root.querySelectorAll('details.gv-cats').forEach(function (x, i) { if (open[i]) x.open = true; });
  }

  function render() { if (!data && !Object.keys(local).length) return; if (CFG.screen) renderScreen(); else renderViewer(); }
  // Laikrodis tiksi tarp atnaujinimų
  setInterval(function () {
    if (CFG.screen) { var el = document.getElementById('scr-timer'), p = liveFor(CFG.area); if (el && p) { var l = timeLeft(p); el.textContent = fmt(l); el.className = 'scr-timer' + (l <= 0 ? ' over' : l <= 15000 ? ' last' : ''); } return; }
    root.querySelectorAll('[data-timer]').forEach(function (el) { var p = liveFor(el.dataset.timer); if (p) el.textContent = fmt(timeLeft(p)); });
  }, 250);
  function poll() {
    var sent = Date.now();
    fetch(CFG.json, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
      failed = 0;
      offset = d.now - Math.round((sent + Date.now()) / 2);
      data = d; render();
    }).catch(function () { failed++; render(); })
      .then(function () { setTimeout(poll, CFG.screen ? 1000 : 3000); });
  }
  poll();
})();
