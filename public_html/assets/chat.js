// Pokalbio langelis apačioje dešinėje (app/chat.php, pokalbis.php).
// Narys: pokalbis su Deniu ir anoniminis komentaras. Treneris: kairėje žmonės su paieška, dešinėje pokalbis (kaip Messenger).
// Atidarius - naujos žinutės kas 2,5 s; uždarius - skaičiukas tikrinamas kas 20 s.
(function () {
  'use strict';
  var root = document.getElementById('chat');
  if (!root) return;
  var cfg = JSON.parse(root.getAttribute('data-chat'));
  var fab = document.getElementById('chatFab'), panel = document.getElementById('chatPanel'), hint = document.getElementById('chatHint');
  var thread = panel.querySelector('.chat-thread'), form = panel.querySelector('.chat-form'), badge = fab.querySelector('.count-badge');
  var FAST = 2500, SLOW = 20000, timer = null, lastKey = '';

  function post(url, data) {
    var fd = new FormData();
    fd.append('_csrf', cfg.csrf);
    fd.append('ajax', '1');
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function get(params) {
    return fetch(cfg.api + '?' + new URLSearchParams(params).toString(), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function el(tag, cls, text) {
    var x = document.createElement(tag);
    if (cls) x.className = cls;
    if (text !== undefined && text !== null) x.textContent = text;
    return x;
  }
  function setBadge(n) { badge.textContent = n; badge.hidden = !n; }
  function isOpen() { return !panel.hidden; }
  function atBottom() { return thread.scrollHeight - thread.scrollTop - thread.clientHeight < 60; }

  // ---------- Žinutės ----------
  var replyCtx = 'general';
  function renderThread(list, staffView) {
    var key = list.map(function (m) { return m.id + (m.pub ? 'p' : '') + (m.name ? 'n' : ''); }).join(',');
    if (key === lastKey) return;
    var stick = !lastKey || atBottom();
    lastKey = key;
    thread.innerHTML = '';
    if (!list.length) {
      thread.appendChild(el('p', 'chat-empty', staffView ? 'Žinučių dar nėra - parašykite pirmą.' : 'Parašykite - atsakymas atsiras čia.'));
      return;
    }
    list.forEach(function (m) {
      var mine = staffView ? m.staff : !m.staff;   // savo žinutės - dešinėje
      var box = el('div', 'chat-msg ' + (mine ? 'mine' : 'theirs') + (m.new ? ' is-new' : ''));
      var meta = el('div', 'chat-meta', m.who + ' · ' + m.when);
      if (m.anon) meta.appendChild(el('span', 'chat-tag anon', 'anoniminis'));
      if (m.pub) meta.appendChild(el('span', 'chat-tag pub', 'viešas'));
      box.appendChild(meta);
      if (m.ctx) {
        var c = m.url ? el('a', 'chat-ctx', m.ctx) : el('span', 'chat-ctx', m.ctx);
        if (m.url) c.href = m.url;
        box.appendChild(c);
      }
      box.appendChild(el('div', 'chat-text', m.body));
      if (staffView && !m.staff) {
        var tools = el('div', 'chat-tools');
        if (m.key !== 'general') {
          var r = el('button', 'linklike', '↩ Atsakyti čia');
          r.type = 'button';
          r.addEventListener('click', function () { setReplyCtx(m.key, m.ctx); });
          tools.appendChild(r);
        }
        if (m.key !== 'general' || m.anon) {
          var pub = el('button', 'linklike', m.pub ? '🔒 Slėpti nuo kitų' : '👁 Rodyti visiems');
          pub.type = 'button';
          pub.addEventListener('click', function () { publish(m.id, 'is_public'); });
          tools.appendChild(pub);
          if (m.pub && !m.anon) {
            var nm = el('button', 'linklike', m.name ? 'be vardo' : 'su vardu');
            nm.type = 'button';
            nm.addEventListener('click', function () { publish(m.id, 'show_name'); });
            tools.appendChild(nm);
          }
        }
        if (tools.childNodes.length) box.appendChild(tools);
      }
      thread.appendChild(box);
    });
    if (stick) thread.scrollTop = thread.scrollHeight;
  }

  function sendOnEnter(f) {
    var ta = f.querySelector('textarea');
    ta.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing && window.matchMedia('(hover: hover)').matches) {
        ev.preventDefault();
        f.requestSubmit ? f.requestSubmit() : f.dispatchEvent(new Event('submit', { cancelable: true }));
      }
    });
    ta.addEventListener('input', function () { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 140) + 'px'; });
  }
  sendOnEnter(form);

  function open() {
    panel.hidden = false;
    fab.setAttribute('aria-expanded', 'true');
    root.classList.add('open');
    if (hint) hintSeen();
    refresh();
    clearInterval(timer);
    timer = setInterval(refresh, FAST);
  }
  function close() {
    panel.hidden = true;
    fab.setAttribute('aria-expanded', 'false');
    root.classList.remove('open');
    clearInterval(timer);
    timer = setInterval(checkBadge, SLOW);
  }
  function checkBadge() {
    if (document.hidden) return;
    get({ badge: 1 }).then(function (d) { if (d.ok) setBadge(d.unread); }).catch(function () {});
  }
  fab.addEventListener('click', function () { isOpen() ? close() : open(); });
  panel.querySelectorAll('.chat-close').forEach(function (b) { b.addEventListener('click', close); });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && isOpen() && !profileOpen()) close(); });
  timer = setInterval(checkBadge, SLOW);

  // „Atsakyti“, „Žinutės“ ir pan. nuorodos puslapyje atidaro pokalbį (data-member - su kuriuo nariu)
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-open-chat]');
    if (!b || b.closest('#chat')) return;
    ev.preventDefault();
    var m = b.getAttribute('data-member');
    if (m) selectMember(m);
    open();
  });

  // ---------- Nario profilis (treneriams) ----------
  var modal = null;
  function profileOpen() { return modal && !modal.hidden; }
  function closeProfile() { if (modal) modal.hidden = true; }
  function openProfile(id) {
    if (!cfg.staff) return;
    if (!modal) {
      modal = el('div', 'mp-modal');
      modal.hidden = true;
      modal.innerHTML = '<div class="mp-box" role="dialog" aria-modal="true" aria-labelledby="mpTitle"><button type="button" class="mp-close" aria-label="Uždaryti">×</button><div class="mp-body"></div></div>';
      document.body.appendChild(modal);
      modal.addEventListener('click', function (ev) {
        if (ev.target === modal || ev.target.closest('.mp-close') || ev.target.closest('[data-open-chat]')) closeProfile();
      });
      document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && profileOpen()) closeProfile(); });
    }
    var body = modal.querySelector('.mp-body');
    body.innerHTML = '<p class="chat-empty">Kraunama…</p>';
    modal.hidden = false;
    get({ profile: id }).then(function (d) { body.innerHTML = d.ok ? d.html : '<p class="chat-empty">' + (d.error || 'Nepavyko') + '</p>'; modal.querySelector('.mp-close').focus(); })
      .catch(function () { body.innerHTML = '<p class="chat-empty">Nepavyko įkelti - patikrinkite ryšį.</p>'; });
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-member-profile]');
    if (!b || !cfg.staff) return;
    ev.preventDefault();
    openProfile(b.getAttribute('data-member-profile'));
  });

  // ---------- Narys ----------
  var member = cfg.member, memberSel = panel.querySelector('.chat-member select');
  function memberRefresh() {
    if (document.hidden) return;
    get({ m: member }).then(function (d) { if (d.ok) { renderThread(d.messages, false); setBadge(0); } }).catch(function () {});
  }

  // ---------- Treneris ----------
  var list = panel.querySelector('.chat-list'), search = panel.querySelector('.chat-search'), current = 0, q = '', side = 'chats', listKey = '';
  function initials(name) { return name.split(' ').map(function (w) { return w.charAt(0); }).join('').slice(0, 2); }
  function row(c, sub, isNew) {
    var a = el('button', 'chat-person' + (c.id === current ? ' on' : '') + (c.unread ? ' unread' : ''));
    a.type = 'button';
    a.setAttribute('role', 'listitem');
    var tx = el('span', 'chat-person-text');
    var top = el('span', 'chat-person-top');
    top.appendChild(el('strong', '', c.name));
    if (c.when) top.appendChild(el('span', 'chat-when', c.when));
    tx.appendChild(top);
    tx.appendChild(el('span', 'chat-person-last' + (isNew ? ' is-new' : ''), sub));
    a.appendChild(el('span', 'chat-avatar small', initials(c.name)));
    a.appendChild(tx);
    if (c.unread) a.appendChild(el('span', 'count-badge', c.unread));
    a.addEventListener('click', function () { selectMember(c.id); });
    return a;
  }
  function renderList(d) {
    // Perpiešiama tik pasikeitus (kitaip kas 2,5 s nušoktų slinkimas)
    var key = side + '|' + q + '|' + current + '|' + JSON.stringify(side === 'all' ? d.all : [d.list, d.start]);
    if (key === listKey) return;
    listKey = key;
    var keep = list.scrollTop;
    list.innerHTML = '';
    if (side === 'all') {
      var group = null;
      (d.all || []).forEach(function (c) {
        if (c.group !== group) { group = c.group; list.appendChild(el('div', 'chat-list-label', group)); }
        list.appendChild(row(c, (c.belt ? c.belt + ' · ' : '') + (c.chat ? 'yra pokalbis' : 'pradėti pokalbį'), !c.chat));
      });
      if (!list.childNodes.length) list.appendChild(el('p', 'chat-empty', q ? 'Nieko nerasta.' : 'Aktyvių narių nėra.'));
    } else {
      var total = 0;
      (d.list || []).forEach(function (c) { total += c.unread; list.appendChild(row(c, c.last, false)); });
      if ((d.start || []).length) {
        list.appendChild(el('div', 'chat-list-label', 'Pradėti pokalbį'));
        d.start.forEach(function (c) { list.appendChild(row(c, (c.group ? c.group + ' · ' : '') + 'pradėti pokalbį', true)); });
      }
      if (!list.childNodes.length) list.appendChild(el('p', 'chat-empty', q ? 'Nieko nerasta.' : 'Pokalbių dar nėra. Suraskite narį paieškoje arba skiltyje „Visi nariai“.'));
      if (!q) setBadge(total);
    }
    list.scrollTop = keep;
  }
  function staffRefresh() {
    if (document.hidden) return;
    var params = { q: q };
    params[side === 'all' ? 'all' : 'list'] = 1;
    if (current) params.m = current;
    get(params).then(function (d) {
      if (!d.ok) return;
      renderList(d);
      if (current && d.member) {
        var main = panel.querySelector('.chat-main');
        main.querySelector('.chat-title').textContent = d.member.name;
        main.querySelector('.chat-sub').textContent = d.member.sub;
        main.querySelector('.chat-avatar').textContent = initials(d.member.name);
        main.querySelector('.chat-who').disabled = false;
        main.querySelector('.chat-card').hidden = false;
        renderThread(d.messages, true);
      }
    }).catch(function () {});
  }
  function selectMember(id) {
    id = +id;
    if (cfg.staff) {
      if (id !== current) { lastKey = ''; setReplyCtx('general'); }
      current = id;
      panel.classList.add('has-thread');
      form.querySelector('textarea').disabled = false;
      staffRefresh();
      setTimeout(function () { form.querySelector('textarea').focus(); }, 50);
    } else if (memberSel) {
      memberSel.value = id; member = id; lastKey = ''; memberRefresh();
    }
  }
  var ctxBox = panel.querySelector('.chat-reply-ctx');
  function setReplyCtx(key, label) {
    replyCtx = key || 'general';
    if (!ctxBox) return;
    ctxBox.hidden = replyCtx === 'general';
    ctxBox.querySelector('span').textContent = replyCtx === 'general' ? '' : 'Atsakote: ' + label;
  }
  function publish(id, field) {
    post(cfg.api, { action: 'publish', member_id: current, message_id: id, field: field }).then(function () { lastKey = ''; staffRefresh(); });
  }
  function refresh() { cfg.staff ? staffRefresh() : memberRefresh(); }

  if (cfg.staff) {
    form.querySelector('textarea').disabled = true;
    var t = null;
    search.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { q = search.value.trim(); staffRefresh(); }, 250); });
    // Skirtukai: „Pokalbiai“ (su kuo rašyta) ir „Visi nariai“ (pagal grupes - galima pradėti naują pokalbį)
    panel.querySelectorAll('.chat-side-tabs button').forEach(function (b) {
      b.addEventListener('click', function () {
        side = b.dataset.side;
        panel.querySelectorAll('.chat-side-tabs button').forEach(function (x) { x.classList.toggle('active', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
        list.scrollTop = 0;
        staffRefresh();
      });
    });
    panel.querySelectorAll('.chat-who, .chat-card').forEach(function (b) { b.addEventListener('click', function () { if (current) openProfile(current); }); });
    // Nuoroda iš laiško „Atsakyti“ (admin/?pokalbis=ID) - iš karto atidaro pokalbį su nariu
    var linked = new URLSearchParams(location.search).get('pokalbis');
    if (linked) { selectMember(linked); open(); }
    ctxBox.querySelector('button').addEventListener('click', function () { setReplyCtx('general'); });
    panel.querySelector('.chat-back').addEventListener('click', function () { panel.classList.remove('has-thread'); });
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var ta = form.querySelector('textarea'), text = ta.value.trim();
      if (!text || !current) return;
      ta.disabled = true;
      post(cfg.api, { action: 'reply', member_id: current, body: text, ctx: replyCtx })
        .then(function (d) {
          if (d.ok) { ta.value = ''; ta.style.height = ''; setReplyCtx('general'); lastKey = ''; staffRefresh(); } else { alert(d.error || 'Nepavyko išsiųsti.'); }
        })
        .catch(function () { alert('Nepavyko išsiųsti - patikrinkite ryšį.'); })
        .then(function () { ta.disabled = false; ta.focus(); });
    });
    return;
  }

  // ---------- Narys: siuntimas, anoniminis komentaras, užuomina ----------
  if (memberSel) memberSel.addEventListener('change', function () { member = this.value; lastKey = ''; memberRefresh(); });
  panel.querySelectorAll('.chat-tabs button').forEach(function (b) {
    b.addEventListener('click', function () {
      panel.querySelectorAll('.chat-tabs button').forEach(function (x) {
        x.classList.toggle('active', x === b);
        x.setAttribute('aria-selected', x === b ? 'true' : 'false');
      });
      panel.querySelectorAll('.chat-pane').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== b.getAttribute('data-tab'); });
    });
  });
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var ta = form.querySelector('textarea'), btn = form.querySelector('button'), text = ta.value.trim();
    if (!text) return;
    btn.disabled = true;
    post(cfg.send, { member_id: member, body: text, page: location.pathname + location.search })
      .then(function (d) {
        if (d.ok) { ta.value = ''; ta.style.height = ''; lastKey = ''; memberRefresh(); } else { alert(d.error || 'Nepavyko išsiųsti.'); }
      })
      .catch(function () { alert('Nepavyko išsiųsti - patikrinkite ryšį.'); })
      .then(function () { btn.disabled = false; });
  });
  var anon = panel.querySelector('.chat-anon');
  anon.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var ta = anon.querySelector('textarea'), btn = anon.querySelector('button'), ok = panel.querySelector('.chat-ok');
    if (!ta.value.trim()) return;
    btn.disabled = true;
    post(cfg.api, { action: 'anon', member_id: member, body: ta.value, page: location.pathname + location.search })
      .then(function (d) {
        if (d.ok) { ta.value = ''; ok.hidden = false; lastKey = ''; setTimeout(function () { ok.hidden = true; }, 5000); } else { alert(d.error || 'Nepavyko išsiųsti.'); }
      })
      .catch(function () { alert('Nepavyko išsiųsti - patikrinkite ryšį.'); })
      .then(function () { btn.disabled = false; });
  });

  function hintSeen() {
    if (!cfg.hint) return;
    cfg.hint = false;
    hint.hidden = true;
    post(cfg.api, { action: 'hint' }).catch(function () {});
  }
  // Užuomina po prisijungimo: pirmuose 3 puslapiuose, kol nepaspausta × ar neatidarytas pokalbis
  var views = 0;
  try { views = +(sessionStorage.getItem('chatHint') || 0); sessionStorage.setItem('chatHint', String(views + 1)); } catch (e) {}
  if (cfg.hint && views < 3) {
    setTimeout(function () { if (!isOpen()) hint.hidden = false; }, 1500);
    setTimeout(function () { hint.hidden = true; }, 15000);
  }
  hint.querySelector('.chat-hint-x').addEventListener('click', hintSeen);
})();
