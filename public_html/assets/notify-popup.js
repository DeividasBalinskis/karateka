// Iššokantys pranešimai treneriui (app/notifications.php notify_popup_html): kas užsiregistravo į egzaminą, pavėlavo,
// kitų klubų registracija į varžybas. Rodoma apačioje dešinėje (virš pokalbio mygtuko), kol nepažymėta „Supratau“ ar neatidaryta; tikrinama kas minutę.
(function () {
  var box = document.getElementById('ntfPop'); if (!box) return;
  var cfg = {}; try { cfg = JSON.parse(box.getAttribute('data-cfg') || '{}'); } catch (e) {}
  var shown = {}, gone = {}, L = cfg.l || { open: 'Atidaryti', ok: 'Supratau', close: 'Uždaryti' };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function markRead(id) {
    var f = new FormData(); f.append('_csrf', cfg.csrf); f.append('ajax', '1'); f.append('action', 'read'); f.append('id', id);
    return fetch(cfg.api, { method: 'POST', body: f, credentials: 'same-origin', headers: { 'X-CSRF-Token': cfg.csrf } }).catch(function () {});
  }
  function render(items) {
    items.forEach(function (n) {
      if (shown[n.id] || gone[n.id]) return;
      shown[n.id] = true;
      var el = document.createElement('div');
      el.className = 'ntf-card'; el.setAttribute('role', 'status'); el.dataset.id = n.id;
      el.innerHTML = '<span class="ntf-icon" aria-hidden="true">' + esc(n.icon) + '</span><div class="ntf-txt"><b>' + esc(n.title) + '</b>' + (n.body ? '<span>' + esc(n.body) + '</span>' : '') +
        '<small>' + esc(n.when) + '</small><div class="ntf-btns">' + (n.link ? '<a class="ntf-open" href="' + esc(n.link) + '">' + esc(L.open) + '</a>' : '') +
        '<button type="button" class="ntf-ok">' + esc(L.ok) + '</button></div></div><button type="button" class="ntf-x" aria-label="' + esc(L.close) + '">×</button>';
      box.appendChild(el);
      requestAnimationFrame(function () { el.classList.add('in'); });
    });
    // Daugiausia 3 matomi; kiti - „dar N“
    var cards = box.querySelectorAll('.ntf-card');
    Array.prototype.forEach.call(cards, function (c, i) { c.hidden = i >= 3; });
    var more = box.querySelector('.ntf-more');
    if (cards.length > 3) {
      if (!more) { more = document.createElement('button'); more.type = 'button'; more.className = 'ntf-more'; box.appendChild(more); }
      more.textContent = '+ dar ' + (cards.length - 3);
    } else if (more) more.remove();
  }
  function close(card, read) {
    var id = +card.dataset.id; gone[id] = true;
    card.classList.remove('in'); setTimeout(function () { card.remove(); render([]); }, 200);
    if (read) markRead(id);
  }
  box.addEventListener('click', function (e) {
    var card = e.target.closest('.ntf-card');
    if (e.target.closest('.ntf-more')) { Array.prototype.forEach.call(box.querySelectorAll('.ntf-card'), function (c) { c.hidden = false; }); e.target.remove(); return; }
    if (!card) return;
    if (e.target.closest('.ntf-ok')) close(card, true);
    else if (e.target.closest('.ntf-x')) close(card, false);   // tik paslėpti šiame puslapyje
    else if (e.target.closest('.ntf-open')) { e.preventDefault(); var href = e.target.closest('.ntf-open').href; markRead(+card.dataset.id).then(function () { location.href = href; }); }
  });
  render(cfg.items || []);
  setInterval(function () {
    if (document.hidden) return;
    fetch(cfg.api + '?naujausi=1', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); })
      .then(function (d) { if (d.items) render(d.items); }).catch(function () {});
  }, 60000);
})();
