// Pagrindinio puslapio redagavimas (tik adminui): tekstai rašomi tiesiai puslapyje, nuotraukos keičiamos 📷,
// blokai ir sąrašų elementai tempiami ⠿. „Išsaugoti“ siunčia visą puslapį į admin/puslapis.php.
(function () {
  var api = window.HOME_API, root = document.getElementById('homeBlocks'), status = document.getElementById('hbStatus');
  var dirty = false, insertAfter = null;

  function say(text, bad) { status.textContent = text; status.className = 'hb-status' + (bad ? ' bad' : ''); }
  function mark() { if (!dirty) { dirty = true; say('Yra neišsaugotų pakeitimų.'); } }
  function post(data, file) {
    var fd = new FormData();
    fd.append('_csrf', api.csrf);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    if (file) fd.append('image', file);
    return fetch(api.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  // ---------- Duomenų surinkimas iš puslapio ----------
  function owner(el) { return el.parentElement.closest('[data-item],[data-block],[data-obj]'); }
  function readEl(el) {
    if (el.matches('input[type=checkbox]')) return el.checked;
    if (el.matches('input, select, textarea')) return el.value;
    if (el.hasAttribute('data-img')) return el.dataset.value || '';
    return el.innerText.replace(/ /g, ' ').replace(/\n{3,}/g, '\n\n').trim();
  }
  function readObj(scope) {
    var o = {};
    scope.querySelectorAll('[data-f]').forEach(function (el) { if (owner(el) === scope) o[el.dataset.f] = readEl(el); });
    scope.querySelectorAll('[data-obj]').forEach(function (el) { if (owner(el) === scope) o[el.dataset.obj] = readObj(el); });
    scope.querySelectorAll('[data-list]').forEach(function (l) {
      if (owner(l) !== scope) return;
      var items = Array.prototype.filter.call(l.children, function (c) { return c.hasAttribute('data-item'); }).map(readObj);
      o[l.dataset.list] = (o[l.dataset.list] || []).concat(items);   // vertybės - du stulpeliai, vienas sąrašas
    });
    return o;
  }
  function collect() {
    return Array.prototype.map.call(root.querySelectorAll(':scope > [data-block]'), function (b) {
      return { id: b.dataset.id, type: b.dataset.type, hidden: b.classList.contains('is-hidden'), menu: b.querySelector('.hb-menu-input').value.trim(), d: readObj(b) };
    });
  }
  function save() {
    say('Saugoma…');
    return post({ action: 'save', page: JSON.stringify(collect()) }).then(function (d) {
      if (!d.ok) throw new Error(d.error || 'Nepavyko išsaugoti');
      dirty = false; say('✓ Išsaugota ' + new Date().toLocaleTimeString('lt-LT', { hour: '2-digit', minute: '2-digit' }) + '. Lankytojai jau mato pakeitimus.');
    }).catch(function (e) { say(e.message || 'Nepavyko išsaugoti - patikrinkite ryšį', true); throw e; });
  }

  // ---------- Tekstai ----------
  root.addEventListener('paste', function (ev) {
    var el = ev.target.closest('[contenteditable]'); if (!el) return;
    ev.preventDefault();
    var text = (ev.clipboardData || window.clipboardData).getData('text/plain');
    if (!el.classList.contains('hb-ml')) text = text.replace(/\s*\n\s*/g, ' ');
    document.execCommand('insertText', false, text);
  });
  root.addEventListener('keydown', function (ev) {
    var el = ev.target.closest('[contenteditable]');
    if (el && ev.key === 'Enter' && !el.classList.contains('hb-ml')) { ev.preventDefault(); el.blur(); }
    if (el && ev.key === 'Enter' && el.classList.contains('hb-ml')) { ev.preventDefault(); document.execCommand('insertText', false, '\n'); }
  });
  root.addEventListener('input', mark);
  root.addEventListener('change', function (ev) {
    mark();
    var t = ev.target;
    if (t.matches('[data-f="color"]')) { var ic = t.closest('.value-item').querySelector('.value-icon'); ic.classList.remove('rose', 'indigo', 'green'); ic.classList.add(t.value); }
    if (t.matches('[data-f="lead"]')) t.closest('.instructor-card').classList.toggle('lead', t.checked);
    if (t.matches('[data-f="side"]')) t.closest('.hb-text-grid').classList.toggle('img-left', t.value === 'left');
  });
  // Nuorodos ir mygtukai redaguojant neveikia (kad paspaudus tekstą neišeitų iš puslapio)
  root.addEventListener('click', function (ev) {
    var a = ev.target.closest('a[href]');
    if (a && !ev.target.closest('.hb-bar, .hb-sched')) ev.preventDefault();
  });
  root.addEventListener('submit', function (ev) { ev.preventDefault(); });

  // ---------- Nuotraukos ----------
  var picker = document.createElement('input');
  picker.type = 'file'; picker.accept = 'image/jpeg,image/png,image/webp'; picker.hidden = true;
  document.body.appendChild(picker);
  var target = null;
  function setImage(box, value, src) {
    var img = box.querySelector('img');
    box.dataset.value = value;
    if (img) { if (src) { img.src = src; img.hidden = false; } else { img.removeAttribute('src'); img.hidden = true; } }
    box.querySelectorAll('.hb-initials, .hb-img-empty').forEach(function (x) { x.hidden = !!src; });
    mark();
  }
  picker.addEventListener('change', function () {
    var f = picker.files[0], box = target; picker.value = '';
    if (!f || !box) return;
    box.classList.add('hb-uploading'); say('Įkeliama nuotrauka…');
    post({ action: 'upload' }, f).then(function (d) {
      box.classList.remove('hb-uploading');
      if (!d.ok) { say(d.error || 'Nepavyko įkelti', true); return; }
      setImage(box, d.path, d.url); say('Nuotrauka įkelta - nepamirškite „Išsaugoti“.');
    }).catch(function () { box.classList.remove('hb-uploading'); say('Nepavyko įkelti nuotraukos', true); });
  });

  // ---------- Blokai ----------
  function wireLists(scope) {
    if (!window.Sortable) return;
    scope.querySelectorAll('[data-list]').forEach(function (l) {
      Sortable.create(l, { group: 'list-' + (l.closest('[data-block]').dataset.id || Math.random()), handle: '.hb-drag', draggable: '[data-item]', animation: 150, onEnd: mark });
    });
  }
  function newId(type) { return type + '-' + Math.random().toString(36).slice(2, 8); }
  var addMenu = document.getElementById('hbAddMenu');

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('button'); if (!b) return;
    var block = b.closest('[data-block]');
    if (b.classList.contains('hb-img-btn')) { target = b.closest('[data-img]'); picker.click(); return; }
    if (b.classList.contains('hb-img-clear')) { setImage(b.closest('[data-img]'), '', ''); return; }
    if (b.classList.contains('hb-item-del')) { var it = b.closest('[data-item]'); if (confirm('Pašalinti?')) { it.remove(); mark(); } return; }
    if (b.classList.contains('hb-add-item')) {
      var tpl = block.querySelector('template.hb-item-tpl'), lists = block.querySelectorAll('[data-list]');
      var wrap = document.createElement('div'); wrap.innerHTML = tpl.innerHTML;
      var item = wrap.firstElementChild; lists[lists.length - 1].appendChild(item); mark();
      var f = item.querySelector('[contenteditable]'); if (f) f.focus();
      return;
    }
    if (b.classList.contains('hb-up') && block.previousElementSibling) { root.insertBefore(block, block.previousElementSibling); mark(); return; }
    if (b.classList.contains('hb-down') && block.nextElementSibling) { root.insertBefore(block.nextElementSibling, block); mark(); return; }
    if (b.classList.contains('hb-hide')) { var h = block.classList.toggle('is-hidden'); b.textContent = h ? '👁 Rodyti' : '🙈 Slėpti'; mark(); return; }
    if (b.classList.contains('hb-del')) { if (confirm('Ištrinti visą bloką? (Galima vietoj to paslėpti.)')) { block.remove(); mark(); } return; }
    if (b.classList.contains('hb-reset')) {
      if (!confirm('Grąžinti pradinį šio bloko turinį? Kiti pakeitimai bus išsaugoti.')) return;
      save().then(function () { return post({ action: 'reset_block', id: block.dataset.id }); }).then(function (d) { if (d.ok) location.reload(); else say(d.error, true); });
      return;
    }
    if (b.classList.contains('hb-insert')) { insertAfter = block; addMenu.hidden = false; return; }
    if (b.dataset.addBlock) {
      var t = addMenu.querySelector('template[data-block-tpl="' + b.dataset.addBlock + '"]');
      var w = document.createElement('div'); w.innerHTML = t.innerHTML;
      var nb = w.firstElementChild; nb.dataset.id = newId(b.dataset.addBlock);
      if (insertAfter) insertAfter.after(nb); else root.appendChild(nb);
      wireLists(nb); addMenu.hidden = true; mark();
      if (b.dataset.single) { t.remove(); b.remove(); }   // vieno egzemplioriaus bloką galima pridėti tik kartą
      nb.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    if (b.classList.contains('hb-add-cancel')) { addMenu.hidden = true; return; }
    if (b.classList.contains('hb-sched-btn')) { document.getElementById('sched-' + b.dataset.cat).showModal(); return; }
    if (b.hasAttribute('data-close-dialog')) { b.closest('dialog').close(); return; }
  });

  // Kaina ir tvarkaraštis išsaugomi iš karto (schedule-editor.js) - atnaujiname, ką rodo puslapis
  document.addEventListener('schedule-changed', function () {
    var dlg = document.querySelector('dialog.hb-sched[open]'); if (!dlg) return;
    var cat = dlg.id.replace('sched-', '');
    fetch(api.fragment + cat, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok) return;
      var set = function (key, html) { var el = document.querySelector('[data-live="' + key + '-' + cat + '"]'); if (el) el.innerHTML = html; };
      set('price', d.price); set('schedule', d.schedule); set('locations', d.locations);
    });
  });

  if (window.Sortable) Sortable.create(root, { handle: '.hb-block-drag', draggable: '[data-block]', animation: 150, onEnd: mark });
  wireLists(document);

  document.getElementById('hbSave').addEventListener('click', function () { save().catch(function () {}); });
  document.getElementById('hbUndo').addEventListener('click', function () {
    if (!confirm('Grąžinti puslapį, koks buvo prieš paskutinį išsaugojimą?')) return;
    post({ action: 'undo' }).then(function (d) { if (d.ok) { dirty = false; location.reload(); } else say(d.error, true); });
  });
  window.addEventListener('beforeunload', function (ev) { if (dirty) { ev.preventDefault(); ev.returnValue = ''; } });
})();
