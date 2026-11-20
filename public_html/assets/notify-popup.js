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
  // Po 12 s (jei pelė ne ant jų) kortelės susitraukia į mažą „🔔 N“ - neuždengia lentelių; paspaudus vėl išsiskleidžia
  var timer = null, folded = false;
  function fold(on) {
    folded = on;
    var cards = box.querySelectorAll('.ntf-card'), pill = box.querySelector('.ntf-pill');
    if (on && cards.length) {
      Array.prototype.forEach.call(cards, function (c) { c.hidden = true; });
      var more = box.querySelector('.ntf-more'); if (more) more.remove();
      if (!pill) { pill = document.createElement('button'); pill.type = 'button'; pill.className = 'ntf-pill'; box.appendChild(pill); }
      pill.textContent = '🔔 ' + cards.length; pill.title = L.show || 'Rodyti pranešimus';
    } else if (pill) pill.remove();
  }
  function arm() { clearTimeout(timer); timer = setTimeout(function () { if (!box.contains(document.activeElement)) fold(true); else arm(); }, 12000); }
  // Jau matyti šioje naršyklės sesijoje - kitame puslapyje iškart tik „🔔 N“
  var seen = {}; try { seen = JSON.parse(sessionStorage.getItem('ntf-seen') || '{}'); } catch (e) {}
  function remember(items) { items.forEach(function (n) { seen[n.id] = 1; }); try { sessionStorage.setItem('ntf-seen', JSON.stringify(seen)); } catch (e) {} }
  function render(items) {
    if (folded && !items.some(function (n) { return !shown[n.id] && !gone[n.id]; })) { fold(true); return; }
    if (folded) fold(false);
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
    if (cards.length) arm();
  }
  function close(card, read) {
    var id = +card.dataset.id; gone[id] = true;
    card.classList.remove('in'); setTimeout(function () { card.remove(); render([]); }, 200);
    if (read) markRead(id);
  }
  box.addEventListener('click', function (e) {
    var card = e.target.closest('.ntf-card');
    if (e.target.closest('.ntf-pill')) { fold(false); render([]); return; }
    if (e.target.closest('.ntf-more')) { Array.prototype.forEach.call(box.querySelectorAll('.ntf-card'), function (c) { c.hidden = false; }); e.target.remove(); return; }
    if (!card) return;
    if (e.target.closest('.ntf-ok')) close(card, true);
    else if (e.target.closest('.ntf-x')) close(card, false);   // tik paslėpti šiame puslapyje
    else if (e.target.closest('.ntf-open')) { e.preventDefault(); var href = e.target.closest('.ntf-open').href; markRead(+card.dataset.id).then(function () { location.href = href; }); }
  });
  var first = cfg.items || [], fresh = first.some(function (n) { return !seen[n.id]; });
  render(first); remember(first);
  if (first.length && !fresh) fold(true);
  setInterval(function () {
    if (document.hidden) return;
    fetch(cfg.api + '?naujausi=1', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); })
      .then(function (d) { if (d.items) { render(d.items); remember(d.items); } }).catch(function () {});
  }, 60000);
})();
