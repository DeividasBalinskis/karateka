// v2.02: Valdymo meniu tvarkymas (tik administratorius). Šoniniame meniu „✎“ → skyrius galima tempti į kitą vietą ar grupę
// (arba ↑ ↓), pervadinti skyrius ir grupes, kurti ir trinti grupes. „Išsaugoti“ → admin/meniu.php → site_setting admin_menu (visiems).
(function () {
  'use strict';
  var side = document.querySelector('.adm-side[data-menu-edit]');
  var btn = document.getElementById('am-edit');
  if (!side || !btn) return;
  var D;
  try { D = JSON.parse(side.getAttribute('data-menu-edit')); } catch (e) { return; }
  var T = D.t || {}, ed = null, dragEl = null;

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function small(label, title, fn) {
    var b = el('button', 'am-eb', label);
    b.type = 'button'; b.title = title; b.setAttribute('aria-label', title);
    b.addEventListener('click', fn);
    return b;
  }

  function itemRow(it) {
    var r = el('div', 'am-ei');
    r.draggable = true; r.dataset.k = it.k; r.dataset.def = it.def;
    r.appendChild(el('span', 'am-handle', '⠿')).setAttribute('aria-hidden', 'true');
    r.appendChild(el('span', 'am-ic', it.ic)).setAttribute('aria-hidden', 'true');
    var inp = el('input', 'am-in');
    inp.type = 'text'; inp.maxLength = 30; inp.value = it.name || it.def; inp.placeholder = it.def;
    inp.setAttribute('aria-label', T.iname || 'Name');
    // Tempiama už ⠿ ar ikonos (pavadinimo laukelyje - rašoma, žymimas tekstas)
    r.draggable = false;
    Array.prototype.forEach.call(r.querySelectorAll('.am-handle, .am-ic'), function (g) {
      g.addEventListener('mousedown', function () { r.draggable = true; });
    });
    inp.addEventListener('mousedown', function () { r.draggable = false; });
    r.addEventListener('mouseup', function () { if (!dragEl) r.draggable = false; });
    r.appendChild(inp);
    r.appendChild(small('↑', T.up || '↑', function () { move(r, -1); }));
    r.appendChild(small('↓', T.down || '↓', function () { move(r, 1); }));
    r.addEventListener('dragstart', function (e) {
      dragEl = r; r.classList.add('am-drag');
      try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', it.k); } catch (x) {}
    });
    r.addEventListener('dragend', function () { r.classList.remove('am-drag'); r.draggable = false; dragEl = null; marks(); });
    return r;
  }

  function groupBox(g) {
    var box = el('div', 'am-eg');
    box.dataset.id = g.id; box.dataset.def = g.def || '';
    var h = el('div', 'am-egh');
    var inp = el('input', 'am-in am-gin');
    inp.type = 'text'; inp.maxLength = 30; inp.value = g.name || g.def || ''; inp.placeholder = g.def || T.newg || '';
    inp.setAttribute('aria-label', T.gname || 'Group');
    h.appendChild(inp);
    h.appendChild(small('↑', T.up || '↑', function () { var p = box.previousElementSibling; if (p && p.classList.contains('am-eg')) box.parentNode.insertBefore(box, p); }));
    h.appendChild(small('↓', T.down || '↓', function () { var n = box.nextElementSibling; if (n && n.classList.contains('am-eg')) box.parentNode.insertBefore(n, box); }));
    h.appendChild(small('✕', T.del || '✕', function () { delGroup(box); })).classList.add('am-del');
    box.appendChild(h);
    var list = el('div', 'am-el');
    (g.items || []).forEach(function (it) { list.appendChild(itemRow(it)); });
    list.appendChild(el('div', 'am-empty', T.empty || ''));
    list.addEventListener('dragover', function (e) {
      if (!dragEl) return;
      e.preventDefault();
      var after = null, rows = list.querySelectorAll('.am-ei:not(.am-drag)');
      for (var i = 0; i < rows.length; i++) {
        var b = rows[i].getBoundingClientRect();
        if (e.clientY < b.top + b.height / 2) { after = rows[i]; break; }
      }
      list.insertBefore(dragEl, after || list.querySelector('.am-empty'));
    });
    list.addEventListener('drop', function (e) { e.preventDefault(); marks(); });
    box.appendChild(list);
    return box;
  }

  // ↑ ↓: viršuje / apačioje grupės - pereina į gretimą grupę
  function move(r, d) {
    var list = r.parentNode, box = list.parentNode;
    var sib = d < 0 ? r.previousElementSibling : r.nextElementSibling;
    if (sib && sib.classList.contains('am-ei')) {
      list.insertBefore(r, d < 0 ? sib : sib.nextSibling);
    } else {
      var other = d < 0 ? box.previousElementSibling : box.nextElementSibling;
      if (!other || !other.classList.contains('am-eg')) return;
      var ol = other.querySelector('.am-el');
      if (d < 0) ol.insertBefore(r, ol.querySelector('.am-empty'));
      else ol.insertBefore(r, ol.firstChild);
    }
    marks();
    var b = r.querySelectorAll('.am-eb')[d < 0 ? 0 : 1];
    if (b) b.focus();
  }

  function delGroup(box) {
    var all = ed.querySelectorAll('.am-eg');
    if (all.length < 2) return;
    if (!window.confirm(T.delq || '?')) return;
    var first = all[0] === box ? all[1] : all[0];
    var fl = first.querySelector('.am-el');
    Array.prototype.slice.call(box.querySelectorAll('.am-ei')).forEach(function (r) { fl.insertBefore(r, fl.querySelector('.am-empty')); });
    box.parentNode.removeChild(box);
    marks();
  }

  function marks() {
    if (!ed) return;
    var gs = ed.querySelectorAll('.am-eg');
    Array.prototype.forEach.call(gs, function (g) {
      g.classList.toggle('is-empty', !g.querySelector('.am-ei'));
      g.querySelector('.am-del').disabled = gs.length < 2;
    });
  }

  function collect() {
    var out = { groups: [], names: {} };
    Array.prototype.forEach.call(ed.querySelectorAll('.am-eg'), function (g) {
      var nm = g.querySelector('.am-gin').value.trim(), def = g.dataset.def || '';
      var row = { id: g.dataset.id, name: nm === def ? '' : nm, items: [] };
      Array.prototype.forEach.call(g.querySelectorAll('.am-ei'), function (r) {
        row.items.push(r.dataset.k);
        var v = r.querySelector('.am-in').value.trim();
        if (v && v !== r.dataset.def) out.names[r.dataset.k] = v;
      });
      out.groups.push(row);
    });
    return out;
  }

  function send(body, okBtn) {
    if (okBtn) okBtn.disabled = true;
    fetch(side.getAttribute('data-save'), {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': side.getAttribute('data-csrf') },
      body: JSON.stringify(body)
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d || !d.ok) throw new Error((d && d.error) || '');
      location.reload();
    })['catch'](function (e) {
      if (okBtn) okBtn.disabled = false;
      window.alert((e && e.message) || T.err || 'Error');
    });
  }

  function open() {
    if (ed) return;
    side.classList.add('am-editing');
    ed = el('div', 'am-ed');
    ed.appendChild(el('div', 'am-ed-t', T.title || ''));
    ed.appendChild(el('p', 'am-ed-hint', T.hint || ''));
    var wrap = el('div', 'am-egs');
    D.groups.forEach(function (g) { wrap.appendChild(groupBox(g)); });
    ed.appendChild(wrap);
    var add = el('button', 'am-add', T.add || '+');
    add.type = 'button';
    add.addEventListener('click', function () {
      var b = groupBox({ id: 'g' + Math.random().toString(36).slice(2, 8), name: '', def: '', items: [] });
      wrap.appendChild(b); marks();
      var i = b.querySelector('.am-gin'); i.value = T.newg || ''; i.focus(); i.select();
    });
    ed.appendChild(add);
    var foot = el('div', 'am-ed-foot');
    var save = el('button', 'btn btn-primary btn-sm', T.save || 'Save');
    save.type = 'button'; save.id = 'am-save';
    save.addEventListener('click', function () { send(collect(), save); });
    var cancel = el('button', 'btn btn-ghost btn-sm', T.cancel || 'Cancel');
    cancel.type = 'button';
    cancel.addEventListener('click', close);
    foot.appendChild(save); foot.appendChild(cancel);
    if (D.custom) {
      var reset = el('button', 'am-reset', T.reset || 'Reset');
      reset.type = 'button'; reset.id = 'am-reset';
      reset.addEventListener('click', function () { if (window.confirm(T.resetq || '?')) send({ reset: true }, reset); });
      foot.appendChild(reset);
    }
    ed.appendChild(foot);
    side.appendChild(ed);
    marks();
    btn.setAttribute('aria-pressed', 'true');
  }

  function close() {
    if (!ed) return;
    ed.parentNode.removeChild(ed); ed = null;
    side.classList.remove('am-editing');
    btn.setAttribute('aria-pressed', 'false');
  }

  btn.addEventListener('click', function () { if (ed) close(); else open(); });
})();
