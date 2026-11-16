// Kvietimai žaisti (visuose puslapiuose): kai kas nors pakviečia - mažas langelis su „Priimti“ / „Atmesti“.
(function () {
  var s = document.currentScript, api = s && s.dataset.api, csrf = s && s.dataset.csrf, arena = s && s.dataset.arena, keturi = s && s.dataset.keturi;
  if (!api || /\/(arena|keturi|ritmas)\.php/.test(location.pathname)) return;
  var T = window.tr || function (x) { return x; }, shown = {}, stop = false;
  function esc(x) { return String(x).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function poll() {
    if (stop || document.hidden) return setTimeout(poll, 15000);
    fetch(api + '?invites=1', { credentials: 'same-origin' }).then(function (r) { if (r.status === 403) { stop = true; return null; } return r.json(); }).then(function (d) {
      if (!d || !d.ok) return;
      d.items.filter(function (i) { return i.incoming && !shown[i.id]; }).slice(0, 1).forEach(show);
    }).catch(function () {}).then(function () { if (!stop) setTimeout(poll, 15000); });
  }
  function show(i) {
    shown[i.id] = 1;
    var g = i.game === 'keturi' ? '🔴 ' + T('Keturi iš eilės') : (i.mode === 'fair' ? '🎯 ' + T('Gudrumo kova') : '🏆 ' + T('Jėgos kova'));
    var el = document.createElement('div'); el.className = 'g-toast g-invite';
    el.innerHTML = '<span>📨 <b>' + esc(i.from) + '</b> ' + T('kviečia:') + ' ' + g + '</span><button class="g-btn sm gold" data-a="accept">✓ ' + T('Priimti') + '</button><button class="g-btn sm" data-a="decline">' + T('Atmesti') + '</button>';
    document.body.appendChild(el);
    var timer = setTimeout(function () { el.remove(); }, 170000);
    el.onclick = function (e) {
      var b = e.target.closest('[data-a]'); if (!b) return;
      clearTimeout(timer); el.remove();
      fetch(api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ action: b.dataset.a, id: i.id, h: i.toHero }) })
        .then(function (r) { return r.json(); }).then(function (d) { if (d.ok && b.dataset.a === 'accept') location.href = (i.game === 'keturi' ? keturi : arena) + '?m=' + d.id; });
    };
  }
  setTimeout(poll, 3000);
})();
