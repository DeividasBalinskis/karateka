// v2.02: Peržiūra test svetainėje (tik administratoriams; app/review.php → review_overlay_html).
// Kiekvienas pakeistas elementas (app/review.json) apvestas auksu. Prie jo: □ → ✓ gerai → ✗ blogai → □ ir 💬 komentaras.
// Apačioje juosta: kiek įvertinta, visų pakeitimų sąrašas (ir kituose puslapiuose), „🎯 Pastaba apie elementą“, „Pateikti“.
(function () {
  'use strict';
  var root = document.getElementById('pzr');
  if (!root) return;
  var C;
  try { C = JSON.parse(root.getAttribute('data-cfg')); } catch (e) { return; }
  var T = C.t || {}, items = C.items || [], marks = {}, here = [], bar, listEl = null, pop = null, picking = false, hoverEl = null;
  var HIDE_KEY = 'pzr-hide-' + C.rev;
  var hidden = false;
  try { hidden = localStorage.getItem(HIDE_KEY) === '1'; } catch (e) {}

  items.forEach(function (it) { marks[it.id] = { mark: it.mark || null, comment: it.comment || '' }; });

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function post(body) {
    body.rev = C.rev;
    return fetch(C.api, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': C.csrf }, body: JSON.stringify(body)
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d || !d.ok) throw new Error((d && d.error) || T.err);
      return d;
    });
  }
  function toast(text, warn) {
    var t = el('div', 'pzr-toast' + (warn ? ' is-warn' : ''), text);
    t.setAttribute('role', 'status');
    document.body.appendChild(t);
    setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 3500);
  }
  function icon(m) { return m === 'good' ? '✓' : (m === 'bad' ? '✗' : '□'); }
  function label(m) { return m === 'good' ? T.good : (m === 'bad' ? T.bad : T.none); }

  // ---------- Auksinės žymos ----------
  function visible(n) {
    if (!n || !n.getClientRects().length) return false;
    var r = n.getBoundingClientRect();
    return r.width > 2 && r.height > 2;
  }
  function target(it) {
    if (!it.sel) return null;
    var list;
    try { list = document.querySelectorAll(it.sel); } catch (e) { return null; }
    for (var i = 0; i < list.length; i++) if (visible(list[i]) && !root.contains(list[i])) return list[i];
    return null;
  }

  function setupHere() {
    items.forEach(function (it) {
      if (!it.here) return;
      var box = el('div', 'pzr-box');
      box.setAttribute('data-pzr', it.id);
      var ctl = el('div', 'pzr-ctl');
      ctl.setAttribute('data-pzr', it.id);
      var mb = el('button', 'pzr-mark');
      mb.type = 'button';
      mb.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); cycle(it); });
      var cb = el('button', 'pzr-cm', '💬');
      cb.type = 'button'; cb.title = T.comment; cb.setAttribute('aria-label', T.comment + ': ' + it.title);
      cb.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); openComment(it, ctl); });
      var lb = el('span', 'pzr-lbl', it.title);
      ctl.appendChild(mb); ctl.appendChild(cb); ctl.appendChild(lb);
      document.body.appendChild(box); document.body.appendChild(ctl);
      here.push({ it: it, box: box, ctl: ctl, mb: mb, cb: cb, node: null });
    });
    paintAll();
  }

  function paint(h) {
    var m = marks[h.it.id];
    h.mb.textContent = icon(m.mark);
    h.mb.className = 'pzr-mark' + (m.mark ? ' is-' + m.mark : '');
    h.mb.title = label(m.mark) + ' · ' + T.cycle;
    h.mb.setAttribute('aria-label', h.it.title + ': ' + label(m.mark));
    h.cb.classList.toggle('has-text', !!m.comment);
    h.box.className = 'pzr-box' + (m.mark ? ' is-' + m.mark : '');
  }
  function paintAll() { here.forEach(paint); place(); renderBar(); }

  function place() {
    var sx = window.pageXOffset, sy = window.pageYOffset, vw = document.documentElement.clientWidth, placed = [];
    here.forEach(function (h) {
      if (!h.node || !h.node.isConnected || !visible(h.node)) h.node = target(h.it);
      if (!h.node || hidden) { h.box.style.display = 'none'; h.ctl.style.display = 'none'; return; }
      var r = h.node.getBoundingClientRect();
      h.box.style.display = ''; h.ctl.style.display = '';
      h.box.style.left = (r.left + sx - 4) + 'px'; h.box.style.top = (r.top + sy - 4) + 'px';
      h.box.style.width = (r.width + 8) + 'px'; h.box.style.height = (r.height + 8) + 'px';
      // Mygtukai - virš auksinio rėmelio dešinėje (neuždengia elemento); jei užliptų ant kito - šalia jo kairėje, jei netelpa - aukščiau
      var w = h.ctl.offsetWidth || 80, ht = h.ctl.offsetHeight || 36;
      var right = Math.min(r.right + sx + 4, sx + vw - 6);
      if (right - w < sx + 6) right = sx + 6 + w;
      var top = r.top + sy - 8 - ht;
      if (top < 4) top = r.top + sy + 4;
      for (var moved = true, guard = 0; moved && guard < 12; guard++) {
        moved = false;
        for (var i = 0; i < placed.length; i++) {
          var p = placed[i];
          if (right - w < p.r && right > p.l && top < p.b && top + ht > p.t) {
            if (p.l - 6 - w >= sx + 6) right = p.l - 6; else top = p.t - ht - 4;
            moved = true;
          }
        }
      }
      placed.push({ l: right - w, r: right, t: top, b: top + ht });
      h.ctl.style.left = right + 'px';
      h.ctl.style.top = top + 'px';
    });
  }
  var raf = 0;
  function schedule() { if (!raf) raf = requestAnimationFrame(function () { raf = 0; place(); }); }

  function cycle(it) {
    var m = marks[it.id], next = m.mark === null ? 'good' : (m.mark === 'good' ? 'bad' : null);
    m.mark = next; C.sent = false;
    paintAll();
    if (next === 'bad' && !m.comment) {
      var h = here.filter(function (x) { return x.it.id === it.id; })[0];
      if (h) openComment(it, h.ctl);
    }
    post({ action: 'mark', id: it.id, mark: next, comment: m.comment })['catch'](function (e) { toast(e.message || T.err, true); });
  }

  function closePop() { if (pop && pop.parentNode) pop.parentNode.removeChild(pop); pop = null; }
  function popAt(anchor, node) {
    closePop();
    pop = node;
    document.body.appendChild(pop);
    var r = anchor.getBoundingClientRect(), sx = window.pageXOffset, sy = window.pageYOffset, vw = document.documentElement.clientWidth;
    var left = Math.max(sx + 12, Math.min(r.right + sx - pop.offsetWidth, sx + vw - pop.offsetWidth - 12));
    pop.style.left = left + 'px';
    pop.style.top = (r.bottom + sy + 8) + 'px';
  }
  function openComment(it, anchor) {
    var m = marks[it.id];
    var p = el('div', 'pzr-pop');
    p.setAttribute('role', 'dialog');
    p.appendChild(el('h4', '', it.title));
    if (it.note) p.appendChild(el('p', '', it.note));
    var ta = el('textarea');
    ta.value = m.comment; ta.placeholder = T.commentPh; ta.maxLength = 2000; ta.setAttribute('aria-label', T.comment);
    p.appendChild(ta);
    var row = el('div', 'pzr-row');
    var ok = el('button', 'pzr-btn pzr-primary', '✓ ' + (T.comment || ''));
    ok.type = 'button';
    var save = function () {
      var v = ta.value.trim();
      if (v === m.comment) { closePop(); return; }
      m.comment = v; C.sent = false; paintAll(); closePop();
      post({ action: 'mark', id: it.id, mark: m.mark, comment: v })['catch'](function (e) { toast(e.message || T.err, true); });
    };
    ok.addEventListener('click', save);
    var no = el('button', 'pzr-btn', T.cancel);
    no.type = 'button';
    no.addEventListener('click', closePop);
    row.appendChild(no); row.appendChild(ok);
    p.appendChild(row);
    ta.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePop(); if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) save(); });
    popAt(anchor, p);
    ta.focus();
  }

  // ---------- Apačios juosta ----------
  function counts() {
    var rated = 0;
    items.forEach(function (it) { if (marks[it.id].mark) rated++; });
    return { rated: rated, all: items.length, here: here.length };
  }
  function renderBar() {
    if (!bar) {
      bar = el('div', 'pzr-bar');
      bar.setAttribute('role', 'region');
      bar.setAttribute('aria-label', T.bar);
      document.body.appendChild(bar);
    }
    var c = counts();
    bar.innerHTML = '';
    bar.classList.toggle('is-mini', hidden || !items.length);
    bar.appendChild(el('span', 'pzr-t', '🔎 ' + T.bar + (C.version ? ' v' + C.version : '')));
    if (items.length) {
      bar.appendChild(el('span', 'pzr-n', (c.here ? c.here + ' ' + T.here + ' · ' : '') + c.rated + '/' + c.all + ' ' + T.rated));
      var lb = el('button', 'pzr-listbtn', '☰ ' + c.rated + '/' + c.all);
      lb.type = 'button'; lb.title = T.list; lb.setAttribute('aria-expanded', listEl ? 'true' : 'false');
      lb.addEventListener('click', toggleList);
      bar.appendChild(lb);
    }
    var pk = el('button', 'pzr-pick', '🎯');
    pk.type = 'button'; pk.title = T.pick + ' - ' + T.pickHint; pk.setAttribute('aria-label', T.pick);
    pk.addEventListener('click', function () { startPick(); });
    bar.appendChild(pk);
    if (items.length) {
      var sb = el('button', 'pzr-submit' + (C.sent ? ' is-sent' : ''), C.sent ? '✓ ' + T.submitted : T.submit);
      sb.type = 'button'; sb.id = 'pzr-submit';
      if (C.sent) sb.title = T.sent + ' ' + T.resend + '?';
      sb.addEventListener('click', submit);
      bar.appendChild(sb);
      var hb = el('button', 'pzr-hide', hidden ? '👁' : '–');
      hb.type = 'button'; hb.title = hidden ? T.show : T.hide; hb.setAttribute('aria-label', hb.title);
      hb.addEventListener('click', function () {
        hidden = !hidden;
        try { localStorage.setItem(HIDE_KEY, hidden ? '1' : '0'); } catch (e) {}
        document.documentElement.classList.toggle('pzr-hidden', hidden);
        renderBar(); place();
      });
      bar.appendChild(hb);
    }
  }

  function toggleList() {
    if (listEl) { listEl.parentNode.removeChild(listEl); listEl = null; renderBar(); return; }
    listEl = el('div', 'pzr-list');
    listEl.setAttribute('role', 'dialog');
    listEl.setAttribute('aria-label', T.list);
    if (!items.length) listEl.appendChild(el('div', 'pzr-empty', T.noItems));
    items.forEach(function (it) {
      var m = marks[it.id];
      var li = el('div', 'pzr-li');
      li.appendChild(el('span', 'pzr-ico' + (m.mark ? ' is-' + m.mark : ''), icon(m.mark)));
      var t = el('div', 'pzr-li-t', it.title);
      var sub = m.comment || it.note;
      var h = here.filter(function (x) { return x.it.id === it.id; })[0];
      if (h && !h.node) sub = '(' + T.missing + ') ' + (sub || '');
      if (sub) t.appendChild(el('small', '', sub));
      li.appendChild(t);
      var a = el('a', '', it.here ? '↓' : T.open + ' →');
      a.href = it.here ? '#pzr-' + it.id : it.href;
      if (it.here) a.addEventListener('click', function (e) { e.preventDefault(); toggleList(); focusItem(it.id); });
      li.appendChild(a);
      listEl.appendChild(li);
    });
    var f = el('div', 'pzr-foot');
    var pa = el('a', '', T.page + ' →');
    pa.href = C.admin;
    f.appendChild(pa);
    listEl.appendChild(f);
    document.body.appendChild(listEl);
    renderBar();
  }

  function focusItem(id) {
    var h = here.filter(function (x) { return x.it.id === id; })[0];
    if (!h) return;
    if (!h.node) h.node = target(h.it);
    if (!h.node) { toast(h.it.title + ': ' + T.missing, true); return; }
    h.node.scrollIntoView({ block: 'center', behavior: 'smooth' });
    h.box.classList.remove('is-flash'); void h.box.offsetWidth; h.box.classList.add('is-flash');
    setTimeout(place, 400);
  }

  function submit() {
    var c = counts();
    var msg = T.askSubmit + (c.rated < c.all ? '\n\n' + T.unrated + ' ' + (c.all - c.rated) : '');
    if (!window.confirm(msg)) return;
    post({ action: 'submit' }).then(function (d) {
      C.sent = true; renderBar(); toast(d.message || T.sent);
    })['catch'](function (e) { toast(e.message || T.err, true); });
  }

  // ---------- 🎯 Pastaba apie bet kurį elementą ----------
  function cssPath(n) {
    if (n.id && /^[A-Za-z][\w-]*$/.test(n.id) && document.querySelectorAll('#' + n.id).length === 1) return '#' + n.id;
    var parts = [];
    while (n && n.nodeType === 1 && n !== document.body && parts.length < 5) {
      var s = n.tagName.toLowerCase();
      if (n.id && /^[A-Za-z][\w-]*$/.test(n.id)) { parts.unshift('#' + n.id); break; }
      var cls = Array.prototype.filter.call(n.classList, function (c) { return /^[A-Za-z][\w-]*$/.test(c) && !/^(is-|active|open|pzr-)/.test(c); }).slice(0, 2);
      if (cls.length) s += '.' + cls.join('.');
      var p = n.parentElement;
      if (p) {
        var same = Array.prototype.filter.call(p.children, function (x) { return x.tagName === n.tagName; });
        if (same.length > 1) s += ':nth-of-type(' + (same.indexOf(n) + 1) + ')';
      }
      parts.unshift(s);
      n = p;
    }
    return parts.join(' > ');
  }
  function ours(n) { return !n || root.contains(n) || (n.closest && n.closest('.pzr-bar, .pzr-list, .pzr-pop, .pzr-ctl, .pzr-toast, .fb')); }
  function startPick() {
    if (picking) { stopPick(); return; }
    closePop();
    if (listEl) toggleList();
    picking = true;
    document.body.classList.add('pzr-picking');
    hoverEl = el('div', 'pzr-hover');
    document.body.appendChild(hoverEl);
    toast(T.pickHint, true);
  }
  function stopPick() {
    picking = false;
    document.body.classList.remove('pzr-picking');
    if (hoverEl && hoverEl.parentNode) hoverEl.parentNode.removeChild(hoverEl);
    hoverEl = null;
  }
  document.addEventListener('mousemove', function (e) {
    if (!picking || !hoverEl) return;
    var n = e.target;
    if (ours(n)) { hoverEl.style.display = 'none'; return; }
    var r = n.getBoundingClientRect();
    hoverEl.style.display = '';
    hoverEl.style.left = (r.left + window.pageXOffset) + 'px'; hoverEl.style.top = (r.top + window.pageYOffset) + 'px';
    hoverEl.style.width = r.width + 'px'; hoverEl.style.height = r.height + 'px';
  }, true);
  document.addEventListener('click', function (e) {
    if (!picking) return;
    var n = e.target;
    if (ours(n)) return;
    e.preventDefault(); e.stopPropagation();
    stopPick();
    var sel = cssPath(n), text = (n.innerText || n.value || n.getAttribute('aria-label') || '').replace(/\s+/g, ' ').trim().slice(0, 160);
    var p = el('div', 'pzr-pop');
    p.setAttribute('role', 'dialog');
    p.appendChild(el('h4', '', T.pick));
    if (text) p.appendChild(el('p', '', '„' + text + '“'));
    var ta = el('textarea');
    ta.placeholder = T.pickPh; ta.maxLength = 2000; ta.setAttribute('aria-label', T.pickPh);
    p.appendChild(ta);
    var row = el('div', 'pzr-row');
    var no = el('button', 'pzr-btn', T.cancel);
    no.type = 'button';
    no.addEventListener('click', closePop);
    var ok = el('button', 'pzr-btn pzr-primary', T.pickSave);
    ok.type = 'button';
    ok.addEventListener('click', function () {
      var v = ta.value.trim();
      if (v.length < 3) { ta.focus(); return; }
      ok.disabled = true;
      post({ action: 'pick', comment: v, selector: sel, text: text, page: C.page, site: C.site, url: location.href, w: window.innerWidth })
        .then(function () { closePop(); toast(T.pickDone); })
        ['catch'](function (er) { ok.disabled = false; toast(er.message || T.err, true); });
    });
    row.appendChild(no); row.appendChild(ok);
    p.appendChild(row);
    ta.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') closePop(); });
    popAt(n, p);
    var box = el('div', 'pzr-hover');
    var r = n.getBoundingClientRect();
    box.style.left = (r.left + window.pageXOffset) + 'px'; box.style.top = (r.top + window.pageYOffset) + 'px';
    box.style.width = r.width + 'px'; box.style.height = r.height + 'px';
    document.body.appendChild(box);
    var obs = setInterval(function () { if (!pop || pop !== p) { clearInterval(obs); if (box.parentNode) box.parentNode.removeChild(box); } }, 300);
    ta.focus();
  }, true);
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (picking) stopPick();
    else if (pop) closePop();
    else if (listEl) toggleList();
  });
  document.addEventListener('mousedown', function (e) {
    if (pop && !pop.contains(e.target) && !(e.target.closest && e.target.closest('.pzr-ctl'))) closePop();
  });

  document.documentElement.classList.toggle('pzr-hidden', hidden);
  setupHere();
  window.addEventListener('scroll', schedule, { passive: true });
  window.addEventListener('resize', schedule);
  setInterval(place, 800);   // turinys keičiasi (skirtukai, išskleidžiami sąrašai) - žymos seka elementus
  var hm = /^#pzr-([a-z0-9-]+)$/.exec(location.hash);
  if (hm) setTimeout(function () { focusItem(hm[1]); }, 300);
})();
