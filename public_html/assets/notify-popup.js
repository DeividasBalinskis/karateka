// Pranešimai treneriui (app/notifications.php notify_popup_html): kas užsiregistravo į egzaminą, pavėlavo,
// kitų klubų registracija į varžybas. v1.94 (#176): nebe kortelės iš šono - tik mažas 🔔 su skaičiumi apačioje dešinėje
// (virš pokalbio mygtuko); paspaudus išsiskleidžia sąrašas. Atsiradus naujam - varpelis švelniai sujuda. Tikrinama per pulsą.
(function () {
  var box = document.getElementById('ntfPop'); if (!box) return;
  var cfg = {}; try { cfg = JSON.parse(box.getAttribute('data-cfg') || '{}'); } catch (e) {}
  var L = cfg.l || {}, items = {}, order = [], gone = {};
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function markRead(id) {
    var f = new FormData(); f.append('_csrf', cfg.csrf); f.append('ajax', '1'); f.append('action', 'read'); f.append('id', id);
    return fetch(cfg.api, { method: 'POST', body: f, credentials: 'same-origin', headers: { 'X-CSRF-Token': cfg.csrf } }).catch(function () {});
  }
  box.innerHTML = '<button type="button" class="ntf-bell" aria-expanded="false" aria-controls="ntfMenu" title="' + esc(L.show || 'Pranešimai') + '">'
    + '<span aria-hidden="true">🔔</span><span class="ntf-n"></span></button>'
    + '<div class="ntf-menu" id="ntfMenu" role="dialog" aria-label="' + esc(L.title || 'Pranešimai') + '" hidden>'
    + '<div class="ntf-head"><b>' + esc(L.title || 'Pranešimai') + '</b><button type="button" class="ntf-all">' + esc(L.all || 'Supratau visus') + '</button></div>'
    + '<ul class="ntf-list"></ul></div>';
  var bell = box.querySelector('.ntf-bell'), menu = box.querySelector('.ntf-menu'), list = box.querySelector('.ntf-list'), num = box.querySelector('.ntf-n');
  // Jau matyti šioje naršyklės sesijoje - naujas tik tas, kurio dar nematė (tada varpelis sujuda)
  var seen = {}; try { seen = JSON.parse(sessionStorage.getItem('ntf-seen') || '{}'); } catch (e) {}
  function remember() { order.forEach(function (id) { seen[id] = 1; }); try { sessionStorage.setItem('ntf-seen', JSON.stringify(seen)); } catch (e) {} }
  function draw() {
    var ids = order.filter(function (id) { return !gone[id]; });
    box.hidden = !ids.length;
    num.textContent = ids.length > 99 ? '99+' : ids.length;
    if (!ids.length) { open(false); return; }
    list.innerHTML = ids.map(function (id) {
      var n = items[id];
      return '<li class="ntf-row" data-id="' + id + '"><span class="ntf-icon" aria-hidden="true">' + esc(n.icon) + '</span>'
        + '<div class="ntf-txt"><b>' + esc(n.title) + '</b>' + (n.body ? '<span>' + esc(n.body) + '</span>' : '') + '<small>' + esc(n.when) + '</small></div>'
        + '<div class="ntf-btns">' + (n.link ? '<a class="ntf-open" href="' + esc(n.link) + '">' + esc(L.open || 'Atidaryti') + '</a>' : '')
        + '<button type="button" class="ntf-ok" title="' + esc(L.ok || 'Supratau') + '">✓</button></div></li>';
    }).join('');
  }
  function add(arr) {
    var fresh = false;
    (arr || []).forEach(function (n) {
      if (gone[n.id]) return;
      if (!items[n.id]) { order.unshift(n.id); if (!seen[n.id]) fresh = true; }
      items[n.id] = n;
    });
    order.sort(function (a, b) { return b - a; });   // naujausi viršuje
    draw(); remember();
    if (fresh) { bell.classList.remove('ring'); void bell.offsetWidth; bell.classList.add('ring'); }
  }
  function open(on) {
    menu.hidden = !on; bell.setAttribute('aria-expanded', on ? 'true' : 'false'); box.classList.toggle('open', on);
  }
  function done(id) { gone[id] = true; markRead(id); draw(); }
  bell.addEventListener('click', function () { open(menu.hidden); });
  box.addEventListener('click', function (e) {
    var row = e.target.closest('.ntf-row');
    if (e.target.closest('.ntf-all')) { order.forEach(function (id) { if (!gone[id]) done(id); }); return; }
    if (!row) return;
    var id = +row.dataset.id;
    if (e.target.closest('.ntf-ok')) done(id);
    else if (e.target.closest('.ntf-open')) { e.preventDefault(); var href = e.target.closest('.ntf-open').href; gone[id] = true; markRead(id).then(function () { location.href = href; }); }
  });
  document.addEventListener('click', function (e) { if (!menu.hidden && !box.contains(e.target)) open(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { open(false); bell.focus(); } });
  add(cfg.items || []);
  // C7: nauji pranešimai - iš bendro pulso (pulsas.php kas 30 s), ne atskira užklausa
  document.addEventListener('karateka:pulse', function (e) { var d = e.detail; if (d.ntf) add(d.ntf); });
})();
