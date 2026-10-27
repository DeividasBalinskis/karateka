// Bendras visų puslapių JS: registracijos laikmačiai ir pokalbio su Deniu langelis (apačioje dešinėje)
(function () {
  'use strict';

  // ---------- Registracijos laikmatis: „liko 2 d. 5 val.“ ----------
  function leftText(sec) {
    if (sec <= 0) return 'baigėsi';
    var d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60);
    return 'liko ' + (d ? d + ' d. ' + h + ' val.' : (h ? h + ' val. ' + m + ' min.' : Math.max(1, m) + ' min.'));
  }
  function tickDeadlines() {
    document.querySelectorAll('.deadline[data-deadline]').forEach(function (el) {
      var left = Math.round((new Date(el.getAttribute('data-deadline')).getTime() - Date.now()) / 1000),
          span = el.querySelector('.deadline-left');
      if (span) span.textContent = leftText(left);
      el.classList.toggle('soon', left > 0 && left < 86400);
      if (left <= 0) { el.classList.add('closed'); el.removeAttribute('data-deadline'); }
    });
  }
  tickDeadlines();
  setInterval(tickDeadlines, 30000);

  // ---------- Pokalbis ----------
  var root = document.getElementById('chat');
  if (!root) return;
  var cfg = JSON.parse(root.getAttribute('data-chat'));
  var fab = document.getElementById('chatFab'), panel = document.getElementById('chatPanel'), hint = document.getElementById('chatHint');
  var thread = panel.querySelector('.chat-thread'), form = panel.querySelector('.chat-form'), anon = panel.querySelector('.chat-anon');
  var badge = fab.querySelector('.count-badge'), memberSel = panel.querySelector('.chat-member select');
  var member = cfg.member, timer = null, lastKey = '';

  function post(url, data) {
    var fd = new FormData();
    fd.append('_csrf', cfg.csrf);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function hintSeen() {
    if (!cfg.hint) return;
    cfg.hint = false;
    hint.hidden = true;
    post(cfg.api, { action: 'hint' }).catch(function () {});
  }

  function render(list) {
    var key = list.map(function (m) { return m.id; }).join(',');
    if (key === lastKey) return;
    lastKey = key;
    thread.innerHTML = '';
    if (!list.length) {
      var p = document.createElement('p');
      p.className = 'chat-empty';
      p.textContent = 'Parašykite - atsakymas atsiras čia.';
      thread.appendChild(p);
      return;
    }
    list.forEach(function (m) {
      var el = document.createElement('div'), meta = document.createElement('div'), body = document.createElement('div');
      el.className = 'chat-msg ' + (m.staff ? 'from-staff' : 'from-me');
      meta.className = 'chat-meta';
      meta.textContent = m.who + ' · ' + m.when;
      body.className = 'chat-text';
      body.textContent = m.body;
      el.appendChild(meta);
      el.appendChild(body);
      thread.appendChild(el);
    });
    thread.scrollTop = thread.scrollHeight;
  }
  function load() {
    if (document.hidden) return;
    fetch(cfg.api + '?m=' + member, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) return;
        render(d.messages);
        badge.hidden = true;
      })
      .catch(function () {});
  }

  function open() {
    panel.hidden = false;
    fab.setAttribute('aria-expanded', 'true');
    root.classList.add('open');
    hintSeen();
    load();
    clearInterval(timer);
    timer = setInterval(load, 10000);   // naujos Denio žinutės - kas 10 s, kol langelis atidarytas
    setTimeout(function () { (form.querySelector('textarea')).focus(); }, 50);
  }
  function close() {
    panel.hidden = true;
    fab.setAttribute('aria-expanded', 'false');
    root.classList.remove('open');
    clearInterval(timer);
  }
  fab.addEventListener('click', function () { panel.hidden ? open() : close(); });
  panel.querySelector('.chat-close').addEventListener('click', close);
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !panel.hidden) close(); });
  if (memberSel) memberSel.addEventListener('change', function () { member = this.value; lastKey = ''; load(); });

  // Skirtukai: žinutė / anoniminis komentaras
  panel.querySelectorAll('.chat-tabs button').forEach(function (b) {
    b.addEventListener('click', function () {
      panel.querySelectorAll('.chat-tabs button').forEach(function (x) {
        x.classList.toggle('active', x === b);
        x.setAttribute('aria-selected', x === b ? 'true' : 'false');
      });
      panel.querySelectorAll('.chat-pane').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== b.getAttribute('data-tab'); });
    });
  });

  function sendOnEnter(f) {
    f.querySelector('textarea').addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing && window.matchMedia('(hover: hover)').matches) {
        ev.preventDefault();
        f.requestSubmit ? f.requestSubmit() : f.dispatchEvent(new Event('submit', { cancelable: true }));
      }
    });
  }
  sendOnEnter(form);

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var ta = form.querySelector('textarea'), btn = form.querySelector('button'), text = ta.value.trim();
    if (!text) return;
    btn.disabled = true;
    post(cfg.send, { ajax: '1', member_id: member, body: text })
      .then(function (d) {
        if (d.ok) { ta.value = ''; lastKey = ''; load(); } else { alert(d.error || 'Nepavyko išsiųsti.'); }
      })
      .catch(function () { alert('Nepavyko išsiųsti - patikrinkite ryšį.'); })
      .then(function () { btn.disabled = false; });
  });

  anon.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var ta = anon.querySelector('textarea'), btn = anon.querySelector('button'), ok = panel.querySelector('.chat-ok');
    if (!ta.value.trim()) return;
    btn.disabled = true;
    post(cfg.api, { action: 'anon', body: ta.value, page: location.pathname + location.search })
      .then(function (d) {
        if (d.ok) { ta.value = ''; ok.hidden = false; setTimeout(function () { ok.hidden = true; }, 5000); } else { alert(d.error || 'Nepavyko išsiųsti.'); }
      })
      .catch(function () { alert('Nepavyko išsiųsti - patikrinkite ryšį.'); })
      .then(function () { btn.disabled = false; });
  });

  // Užuomina po prisijungimo (vieną kartą): po 1,5 s, užveriama paspaudus × arba atidarius pokalbį
  // (pirmuose 3 puslapiuose po prisijungimo, kol nepaspausta × ar neatidarytas pokalbis)
  var views = 0;
  try { views = +(sessionStorage.getItem('chatHint') || 0); sessionStorage.setItem('chatHint', String(views + 1)); } catch (e) {}
  if (cfg.hint && views < 3) {
    setTimeout(function () { if (panel.hidden) hint.hidden = false; }, 1500);
    setTimeout(function () { hint.hidden = true; }, 15000);
  }
  hint.querySelector('.chat-hint-x').addEventListener('click', hintSeen);
})();
