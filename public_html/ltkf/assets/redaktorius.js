// LTKF svetainės redaktorius: bet kurį tekstą puslapyje galima pakeisti tiesiai (spausti ir rašyti), skiltis - paslėpti,
// tarp skilčių - pridėti blokų (tekstas, nuotrauka, galerija, failai, mygtukas, vaizdo įrašas, kortelės).
// Apačioje juosta: Išsaugoti, Atšaukti, Meniu, Išvaizda ir kontaktai, Naujas puslapis, Failų dėžė, Baigti.
(function () {
  'use strict';
  var C = window.LTKF_ED; if (!C) return;
  var main = document.querySelector('main'), foot = document.querySelector('footer.site-footer');
  var dirty = false, hidden = [];
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var norm = function (s) { return String(s || '').replace(/[\s ]+/g, ' ').trim(); };
  var uid = function () { return Math.random().toString(36).slice(2, 10); };

  // ---------- Juosta ----------
  var bar = document.createElement('div');
  bar.className = 'ed-bar ed-ui';
  bar.innerHTML = '<span class="ed-who">✏️ ' + esc(C.who) + '</span>' +
    '<button type="button" class="ed-b ed-save" data-a="save">💾 Išsaugoti</button>' +
    '<button type="button" class="ed-b" data-a="undo" title="Grąžinti, kaip buvo prieš paskutinį išsaugojimą">↶ Atšaukti</button>' +
    '<span class="ed-sep"></span>' +
    '<button type="button" class="ed-b" data-a="menu">☰ Meniu</button>' +
    '<button type="button" class="ed-b" data-a="look">🎨 Išvaizda ir kontaktai</button>' +
    '<button type="button" class="ed-b" data-a="newpage">📄 Naujas puslapis</button>' +
    '<a class="ed-b" href="failai.php" target="_blank">📥 Failų dėžė</a>' +
    (C.custom ? '<button type="button" class="ed-b ed-danger" data-a="delpage">🗑 Ištrinti puslapį</button>' : '') +
    '<span class="ed-status" id="edStatus">Spauskite bet kurį tekstą ir rašykite. Skiltis galima paslėpti, tarp jų - pridėti blokų.</span>' +
    '<button type="button" class="ed-b ed-exit" data-a="exit">✓ Baigti</button>';
  document.body.appendChild(bar);
  var statusEl = bar.querySelector('#edStatus');
  function say(t, bad) { statusEl.textContent = t; statusEl.className = 'ed-status' + (bad ? ' bad' : ''); }
  function mark() { if (!dirty) { dirty = true; say('Yra neišsaugotų pakeitimų - spauskite „Išsaugoti“.'); bar.classList.add('dirty'); } }
  function post(data, files) {
    var fd = new FormData();
    fd.append('_csrf', C.csrf);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    (files || []).forEach(function (f) { fd.append(f[0], f[1]); });
    return fetch(C.api, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); })
      .catch(function () { return { ok: false, error: 'Nepavyko - patikrinkite ryšį.' }; });
  }

  // ---------- Tekstai ----------
  var SKIP = 'script, style, svg, iframe, select, option, input, textarea, button, .lb-zone, .ed-ui, .ed-modal';
  function prepareTexts(root) {
    if (!root) return;
    root.querySelectorAll('*').forEach(function (el) {
      if (el.closest(SKIP)) return;
      var own = Array.prototype.some.call(el.childNodes, function (n) { return n.nodeType === 3 && norm(n.nodeValue) !== ''; });
      if (!own) return;
      el.setAttribute('contenteditable', 'true');
      el.setAttribute('spellcheck', 'true');
      el.classList.add('ed-txt');
      if (!el.hasAttribute('data-o')) el.setAttribute('data-o', norm(el.textContent));
      el.dataset.cur = norm(el.innerText);
    });
  }
  prepareTexts(main); prepareTexts(foot);
  document.addEventListener('input', function (e) { if (e.target.closest && e.target.closest('main, footer, .ed-modal')) mark(); });
  document.addEventListener('paste', function (e) {
    var el = e.target.closest && e.target.closest('.ed-txt, .ed-plain');
    if (!el) return;
    e.preventDefault();
    var t = (e.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, t);
  });
  document.addEventListener('keydown', function (e) {
    var el = e.target.closest && e.target.closest('.ed-txt');
    if (el && e.key === 'Enter') { e.preventDefault(); if (/^H[1-6]$/.test(el.tagName) || el.tagName === 'A') el.blur(); else document.execCommand('insertLineBreak'); }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); }
  });
  // Redaguojant nuorodos neveda kitur (kad paspaudus tekstą neišeitų iš puslapio)
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('main a[href], footer a[href], header a[href]');
    if (a && !e.target.closest('.ed-ui, .ed-modal')) {
      if (a.closest('header') && !a.closest('.lang-sw')) { if (dirty && !confirm('Yra neišsaugotų pakeitimų. Išeiti?')) { e.preventDefault(); return; } dirty = false; return; }
      e.preventDefault();
    }
  }, true);

  // ---------- Skiltys: slėpti / rodyti ----------
  main.querySelectorAll(':scope > section[data-sec]').forEach(function (sec) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'ed-sec-tg ed-ui';
    var upd = function () { var h = sec.classList.contains('lb-hidden'); b.textContent = h ? '👁 Rodyti skiltį' : '🙈 Slėpti skiltį'; b.title = h ? 'Lankytojai šios skilties nemato' : 'Paslėpti šią skiltį nuo lankytojų'; };
    b.addEventListener('click', function () { sec.classList.toggle('lb-hidden'); upd(); mark(); });
    if (getComputedStyle(sec).position === 'static') sec.style.position = 'relative';
    sec.appendChild(b); upd();
  });

  // ---------- Blokai ----------
  var TYPES = [['h', 'Antraštė', '🔠'], ['text', 'Tekstas', '📝'], ['img', 'Nuotrauka', '🖼️'], ['gallery', 'Galerija', '🖼️🖼️'], ['files', 'Failai (dokumentai)', '📎'],
    ['btn', 'Mygtukas / nuoroda', '🔗'], ['video', 'Vaizdo įrašas (YouTube)', '▶️'], ['cards', 'Kortelės', '🗂️']];
  var TNAME = {}; TYPES.forEach(function (t) { TNAME[t[0]] = t[2] + ' ' + t[1]; });
  function picker(accept, multiple, cb) {
    var i = document.createElement('input'); i.type = 'file'; i.accept = accept || ''; i.multiple = !!multiple;
    i.addEventListener('change', function () { cb(Array.prototype.slice.call(i.files)); });
    i.click();
  }
  function upload(kind, file) {
    say('Įkeliama: ' + file.name + '…');
    return post({ action: kind }, [['file', file]]).then(function (d) { if (!d.ok) say(d.error || 'Nepavyko įkelti', true); else say('Įkelta - nepamirškite „Išsaugoti“.'); return d; });
  }
  function ytId(u) { var m = String(u || '').match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/))([A-Za-z0-9_-]{11})/); return m ? m[1] : ''; }

  function blockEl(b) {
    var el = document.createElement('div');
    el.className = 'ed-block ed-ui-keep'; el._b = b;
    el.innerHTML = '<div class="ed-bh"><span>' + esc(TNAME[b.t] || b.t) + '</span><span class="sp"></span><button type="button" data-bk="up" title="Aukštyn">↑</button><button type="button" data-bk="down" title="Žemyn">↓</button><button type="button" data-bk="del" title="Ištrinti">🗑</button></div><div class="ed-bb"></div>';
    renderBody(el);
    return el;
  }
  function renderBody(el) {
    var b = el._b, body = el.querySelector('.ed-bb'), h = '';
    switch (b.t) {
      case 'h': h = '<h2 class="lb-h ed-plain" contenteditable="true" data-f="text">' + esc(b.text || '') + '</h2>'; break;
      case 'text':
        h = '<div class="ed-rt">' + [['bold', '<b>B</b>'], ['italic', '<i>I</i>'], ['h2', 'H2'], ['h3', 'H3'], ['p', '¶'], ['ul', '• Sąrašas'], ['ol', '1. Sąrašas'], ['link', '🔗 Nuoroda'], ['clear', '⨯ Formatas']].map(function (x) { return '<button type="button" data-rt="' + x[0] + '">' + x[1] + '</button>'; }).join('') + '</div>' +
          '<div class="prose lb-text ed-rich" contenteditable="true">' + (b.html || '<p>Įrašykite tekstą…</p>') + '</div>';
        break;
      case 'img':
        h = '<figure class="lb-img w-' + esc(b.w || 'full') + '">' + (b.src ? '<img src="' + esc(b.src) + '" alt="">' : '<div class="ed-ph">Nuotraukos dar nėra</div>') + '</figure>' +
          '<div class="ed-row"><button type="button" class="ed-b" data-up="img">📷 ' + (b.src ? 'Pakeisti' : 'Įkelti') + ' nuotrauką</button>' +
          '<label>Dydis <select data-f="w"><option value="full">Visas plotis</option><option value="half">Pusė</option><option value="small">Maža</option></select></label></div>' +
          '<input type="text" data-f="cap" placeholder="Aprašas po nuotrauka (nebūtina)" value="' + esc(b.cap || '') + '">';
        break;
      case 'gallery':
        h = '<div class="lb-gallery">' + (b.imgs || []).map(function (i, k) { return '<span class="ed-th"><img src="' + esc(i.src) + '" alt=""><button type="button" data-rm="' + k + '" title="Pašalinti">×</button></span>'; }).join('') + '</div>' +
          '<div class="ed-row"><button type="button" class="ed-b" data-up="gallery">📷 Pridėti nuotraukų</button><span class="muted">Galima pasirinkti kelias iš karto.</span></div>';
        break;
      case 'files':
        h = '<input type="text" data-f="title" placeholder="Antraštė (nebūtina), pvz. „Nuostatai“" value="' + esc(b.title || '') + '">' +
          '<ul class="ed-files">' + (b.items || []).map(function (i, k) { return '<li><span class="doc-icon">' + esc((i.url.split('.').pop() || '').toUpperCase().slice(0, 4)) + '</span><input type="text" data-fi="' + k + '" value="' + esc(i.name) + '"><button type="button" data-rm="' + k + '" title="Pašalinti">×</button></li>'; }).join('') + '</ul>' +
          '<div class="ed-row"><button type="button" class="ed-b" data-up="files">📎 Įkelti failų</button><span class="muted">PDF, Word, Excel, nuotraukos… (galima kelis)</span></div>';
        break;
      case 'btn':
        h = '<div class="ed-row"><input type="text" data-f="label" placeholder="Mygtuko tekstas, pvz. „Registracija“" value="' + esc(b.label || '') + '"><input type="text" data-f="href" placeholder="Nuoroda, pvz. https://… arba kalendorius.php" value="' + esc(b.href || '') + '">' +
          '<select data-f="style"><option value="">Ryškus</option><option value="ghost">Kuklus</option></select></div>';
        break;
      case 'video':
        var id = ytId(b.url);
        h = '<input type="text" data-f="url" placeholder="YouTube nuoroda (įrašo ar transliacijos)" value="' + esc(b.url || '') + '">' +
          (id ? '<div class="lb-video"><iframe src="https://www.youtube-nocookie.com/embed/' + id + '" loading="lazy" allowfullscreen></iframe></div>' : '<div class="ed-ph">Įklijuokite YouTube nuorodą</div>');
        break;
      case 'cards':
        h = '<div class="benefits">' + (b.items || []).map(function (i, k) {
          return '<div class="benefit ed-card">' + (i.img ? '<img src="' + esc(i.img) + '" alt="">' : '') + '<input type="text" data-ci="' + k + '" data-cf="title" placeholder="Antraštė" value="' + esc(i.title || '') + '">' +
            '<textarea data-ci="' + k + '" data-cf="text" rows="3" placeholder="Tekstas">' + esc(i.text || '') + '</textarea><div class="ed-row"><button type="button" class="ed-b" data-up="card" data-k="' + k + '">📷</button><button type="button" class="ed-b" data-rm="' + k + '">🗑</button></div></div>';
        }).join('') + '</div><div class="ed-row"><button type="button" class="ed-b" data-add="card">+ Kortelė</button></div>';
        break;
    }
    body.innerHTML = h;
    var sel = body.querySelectorAll('select[data-f]'); sel.forEach(function (s) { s.value = b[s.dataset.f] || s.options[0].value; });
  }
  function zoneUi(zone) {
    var blocks = []; try { blocks = JSON.parse(zone.getAttribute('data-blocks') || '[]'); } catch (e) {}
    zone.innerHTML = '';
    blocks.forEach(function (b) { zone.appendChild(blockEl(b)); });
    var add = document.createElement('div'); add.className = 'ed-add ed-ui';
    add.innerHTML = '<button type="button" class="ed-add-btn">+ Pridėti čia: tekstą, nuotrauką, failus…</button><div class="ed-types" hidden>' + TYPES.map(function (t) { return '<button type="button" data-type="' + t[0] + '">' + t[2] + ' ' + t[1] + '</button>'; }).join('') + '</div>';
    zone.appendChild(add);
  }
  var zones = Array.prototype.slice.call(main.querySelectorAll('.lb-zone[data-zone]'));
  zones.forEach(zoneUi);

  function newBlock(t) {
    var b = { t: t, id: uid() };
    if (t === 'text') b.html = '<p>Įrašykite tekstą…</p>';
    if (t === 'h') b.text = 'Nauja antraštė';
    if (t === 'gallery') b.imgs = [];
    if (t === 'files') b.items = [];
    if (t === 'cards') b.items = [{ title: 'Antraštė', text: 'Tekstas' }];
    return b;
  }
  main.addEventListener('click', function (e) {
    var t = e.target.closest('button'); if (!t) return;
    if (t.classList.contains('ed-add-btn')) { var ty = t.nextElementSibling; ty.hidden = !ty.hidden; return; }
    if (t.dataset.type) {
      var zone = t.closest('.lb-zone'), add = zone.querySelector('.ed-add');
      var el = blockEl(newBlock(t.dataset.type));
      zone.insertBefore(el, add); t.parentNode.hidden = true; mark();
      if (t.dataset.type === 'img') el.querySelector('[data-up]').click();
      var f = el.querySelector('[contenteditable], input'); if (f && t.dataset.type !== 'img') f.focus();
      return;
    }
    var blk = t.closest('.ed-block'); if (!blk) return;
    var b = blk._b;
    if (t.dataset.bk === 'up' && blk.previousElementSibling) { blk.parentNode.insertBefore(blk, blk.previousElementSibling); mark(); return; }
    if (t.dataset.bk === 'down' && blk.nextElementSibling && blk.nextElementSibling.classList.contains('ed-block')) { blk.parentNode.insertBefore(blk.nextElementSibling, blk); mark(); return; }
    if (t.dataset.bk === 'del') { if (confirm('Ištrinti šį bloką?')) { blk.remove(); mark(); } return; }
    if (t.dataset.rt) return richCmd(blk.querySelector('.ed-rich'), t.dataset.rt);
    if (t.dataset.rm !== undefined) {
      var k = +t.dataset.rm;
      if (b.t === 'gallery') b.imgs.splice(k, 1);
      if (b.t === 'files') b.items.splice(k, 1);
      if (b.t === 'cards') b.items.splice(k, 1);
      renderBody(blk); mark(); return;
    }
    if (t.dataset.add === 'card') { b.items.push({ title: '', text: '' }); renderBody(blk); mark(); return; }
    if (t.dataset.up === 'img') return picker('image/jpeg,image/png,image/webp', false, function (fs) { if (fs[0]) upload('image', fs[0]).then(function (d) { if (d.ok) { b.src = d.url; renderBody(blk); mark(); } }); });
    if (t.dataset.up === 'card') return picker('image/jpeg,image/png,image/webp', false, function (fs) { if (fs[0]) upload('image', fs[0]).then(function (d) { if (d.ok) { b.items[+t.dataset.k].img = d.url; renderBody(blk); mark(); } }); });
    if (t.dataset.up === 'gallery') return picker('image/jpeg,image/png,image/webp', true, function (fs) {
      fs.reduce(function (p, f) { return p.then(function () { return upload('image', f).then(function (d) { if (d.ok) { b.imgs.push({ src: d.url }); renderBody(blk); mark(); } }); }); }, Promise.resolve());
    });
    if (t.dataset.up === 'files') return picker('', true, function (fs) {
      fs.reduce(function (p, f) { return p.then(function () { return upload('file', f).then(function (d) { if (d.ok) { b.items.push({ url: d.url, name: d.name, size: d.size }); renderBody(blk); mark(); } }); }); }, Promise.resolve());
    });
  });
  main.addEventListener('input', function (e) {
    var blk = e.target.closest('.ed-block'); if (!blk) return;
    var b = blk._b, t = e.target;
    if (t.dataset.f) { b[t.dataset.f] = t.isContentEditable ? t.innerText : t.value; if (b.t === 'video' && t.dataset.f === 'url') { clearTimeout(blk._vt); blk._vt = setTimeout(function () { renderBody(blk); }, 700); } }
    if (t.dataset.fi !== undefined) b.items[+t.dataset.fi].name = t.value;
    if (t.dataset.ci !== undefined) b.items[+t.dataset.ci][t.dataset.cf] = t.value;
    if (t.classList.contains('ed-rich')) b.html = t.innerHTML;
  });
  main.addEventListener('change', function (e) {
    var blk = e.target.closest('.ed-block'); if (!blk) return;
    if (e.target.dataset.f) { blk._b[e.target.dataset.f] = e.target.value; if (e.target.dataset.f === 'w') renderBody(blk); mark(); }
  });
  function richCmd(box, cmd) {
    box.focus();
    if (cmd === 'bold' || cmd === 'italic') document.execCommand(cmd);
    if (cmd === 'h2' || cmd === 'h3' || cmd === 'p') document.execCommand('formatBlock', false, cmd.toUpperCase());
    if (cmd === 'ul') document.execCommand('insertUnorderedList');
    if (cmd === 'ol') document.execCommand('insertOrderedList');
    if (cmd === 'clear') document.execCommand('removeFormat');
    if (cmd === 'link') { var u = prompt('Nuoroda (https://… arba puslapis, pvz. kalendorius.php):', 'https://'); if (u) document.execCommand('createLink', false, u); }
    box.closest('.ed-block')._b.html = box.innerHTML; mark();
  }

  // ---------- Išsaugoti ----------
  function collect() {
    var pages = {}; pages[C.slug] = { texts: {}, hidden: [], blocks: {} };
    var bendri = { texts: {} };
    document.querySelectorAll('.ed-txt').forEach(function (el) {
      var now = el.innerText.replace(/ /g, ' ').replace(/\n{3,}/g, '\n\n').trim();
      if (norm(now) === el.dataset.cur) return;
      (el.closest('footer') ? bendri : pages[C.slug]).texts[el.getAttribute('data-o')] = now;
    });
    main.querySelectorAll(':scope > section[data-sec].lb-hidden').forEach(function (s) { pages[C.slug].hidden.push(s.dataset.sec); });
    zones.forEach(function (z) {
      var list = Array.prototype.map.call(z.querySelectorAll(':scope > .ed-block'), function (el) { var b = el._b; if (b.t === 'text') b.html = el.querySelector('.ed-rich').innerHTML; if (b.t === 'h') b.text = el.querySelector('[data-f=text]').innerText; return b; });
      if (list.length) pages[C.slug].blocks[z.dataset.zone] = list;
    });
    if (Object.keys(bendri.texts).length) pages._bendri = bendri;
    var h1 = main.querySelector('h1'); if (C.custom && h1) pages[C.slug].title = norm(h1.innerText);
    return pages;
  }
  function save() {
    say('Saugoma…');
    return post({ action: 'save', pages: JSON.stringify(collect()) }).then(function (d) {
      if (!d.ok) { say(d.error || 'Nepavyko išsaugoti', true); return d; }
      dirty = false; bar.classList.remove('dirty');
      document.querySelectorAll('.ed-txt').forEach(function (el) { el.dataset.cur = norm(el.innerText); });
      say('✓ Išsaugota ' + new Date().toLocaleTimeString('lt-LT', { hour: '2-digit', minute: '2-digit' }) + '. Lankytojai jau mato pakeitimus.');
      return d;
    });
  }

  // ---------- Langai ----------
  function modal(title, html, onOk, okLabel) {
    var m = document.createElement('div'); m.className = 'ed-modal';
    m.innerHTML = '<div class="ed-mbox"><h2>' + title + '</h2><div class="ed-mbody">' + html + '</div><div class="ed-mfoot"><button type="button" class="ed-b" data-m="x">Atšaukti</button>' + (onOk ? '<button type="button" class="ed-b ed-save" data-m="ok">' + (okLabel || 'Išsaugoti') + '</button>' : '') + '</div></div>';
    m.addEventListener('click', function (e) { if (e.target === m || (e.target.dataset && e.target.dataset.m === 'x')) m.remove(); if (e.target.dataset && e.target.dataset.m === 'ok') onOk(m); });
    document.body.appendChild(m);
    return m;
  }
  function pageOptions(sel) {
    return C.pages.map(function (p) { return '<option value="' + esc(p.h) + '"' + (p.h === sel ? ' selected' : '') + '>' + esc(p.n) + (p.custom ? ' (naujas)' : '') + '</option>'; }).join('') +
      '<option value="__url"' + (sel && !C.pages.some(function (p) { return p.h === sel; }) ? ' selected' : '') + '>Kita nuoroda…</option>';
  }
  function menuModal() {
    var row = function (i) {
      var known = C.pages.some(function (p) { return p.h === i.h; });
      return '<li class="ed-mi"><span class="ed-drag">⠿</span><input type="text" class="mi-l" value="' + esc(i.l) + '" placeholder="Pavadinimas"><select class="mi-s">' + pageOptions(i.h) + '</select>' +
        '<input type="text" class="mi-u" value="' + esc(known ? '' : i.h) + '" placeholder="https://…"' + (known ? ' hidden' : '') + '><button type="button" data-mi="up">↑</button><button type="button" data-mi="down">↓</button><button type="button" data-mi="del">🗑</button></li>';
    };
    var m = modal('☰ Meniu', '<p class="muted">Pridėkite, ištrinkite, pervadinkite ir rikiuokite punktus. Punktas gali vesti į puslapį arba bet kokią nuorodą.</p><ul class="ed-menu">' + C.menu.map(row).join('') + '</ul><button type="button" class="ed-b" data-mi="add">+ Pridėti punktą</button>', function () {
      var items = Array.prototype.map.call(m.querySelectorAll('.ed-mi'), function (li) { var s = li.querySelector('.mi-s').value; return { l: li.querySelector('.mi-l').value.trim(), h: s === '__url' ? li.querySelector('.mi-u').value.trim() : s }; }).filter(function (i) { return i.l; });
      post({ action: 'menu', menu: JSON.stringify(items) }).then(function (d) { if (d.ok) { C.menu = items; m.remove(); say('✓ Meniu išsaugotas.'); setTimeout(function () { location.reload(); }, 400); } else alert(d.error); });
    });
    var ul = m.querySelector('.ed-menu');
    m.addEventListener('change', function (e) { if (e.target.classList.contains('mi-s')) e.target.parentNode.querySelector('.mi-u').hidden = e.target.value !== '__url'; });
    m.addEventListener('click', function (e) {
      var b = e.target.closest('[data-mi]'); if (!b) return;
      var li = b.closest('li');
      if (b.dataset.mi === 'add') { var w = document.createElement('div'); w.innerHTML = row({ l: '', h: C.pages[0].h }); ul.appendChild(w.firstChild); ul.lastChild.querySelector('input').focus(); }
      if (b.dataset.mi === 'del') li.remove();
      if (b.dataset.mi === 'up' && li.previousElementSibling) ul.insertBefore(li, li.previousElementSibling);
      if (b.dataset.mi === 'down' && li.nextElementSibling) ul.insertBefore(li.nextElementSibling, li);
    });
  }
  function lookModal() {
    var n = C.nust || {};
    var m = modal('🎨 Išvaizda ir kontaktai',
      '<div class="ed-field"><b>Pradžios puslapio fonas</b><div class="ed-hero-prev" style="' + (n.hero_img ? 'background-image:url(' + esc(n.hero_img) + ')' : '') + (n.hero_color ? ';background-color:' + esc(n.hero_color) : '') + '"></div>' +
      '<div class="ed-row"><button type="button" class="ed-b" data-l="img">📷 Įkelti fono nuotrauką</button><button type="button" class="ed-b" data-l="noimg">Be nuotraukos</button>' +
      '<label>Spalva <input type="color" class="l-color" value="' + esc(n.hero_color || '#0f1b33') + '"></label><button type="button" class="ed-b" data-l="nocolor">Numatyta spalva</button></div></div>' +
      '<label class="ed-field">Federacijos pavadinimas <input type="text" class="l-pav" value="' + esc(n.pavadinimas || '') + '"></label>' +
      '<label class="ed-field">El. paštas <input type="text" class="l-mail" value="' + esc(n.el_pastas || '') + '"></label>' +
      '<label class="ed-field">Telefonas <input type="text" class="l-tel" value="' + esc(n.telefonas || '') + '"></label>',
      function () {
        var data = { hero_img: n.hero_img || '', hero_color: m._color === undefined ? (n.hero_color || '') : m._color, pavadinimas: m.querySelector('.l-pav').value, el_pastas: m.querySelector('.l-mail').value, telefonas: m.querySelector('.l-tel').value };
        post({ action: 'settings', nust: JSON.stringify(data) }).then(function (d) { if (d.ok) location.reload(); else alert(d.error); });
      });
    var prev = m.querySelector('.ed-hero-prev');
    m.querySelector('.l-color').addEventListener('input', function () { m._color = this.value; prev.style.backgroundColor = this.value; });
    m.addEventListener('click', function (e) {
      var b = e.target.closest('[data-l]'); if (!b) return;
      if (b.dataset.l === 'img') picker('image/jpeg,image/png,image/webp', false, function (fs) { if (fs[0]) upload('image', fs[0]).then(function (d) { if (d.ok) { n.hero_img = d.url; prev.style.backgroundImage = 'url(' + d.url + ')'; } }); });
      if (b.dataset.l === 'noimg') { n.hero_img = ''; prev.style.backgroundImage = 'none'; }
      if (b.dataset.l === 'nocolor') { m._color = ''; prev.style.backgroundColor = ''; }
    });
  }
  function newPageModal() {
    var m = modal('📄 Naujas puslapis', '<label class="ed-field">Pavadinimas <input type="text" class="np-t" placeholder="pvz. Seminarai, Rinktinė, Galerija"></label><label class="ed-check"><input type="checkbox" class="np-m" checked> Įdėti į meniu</label>', function () {
      var t = m.querySelector('.np-t').value.trim(); if (!t) return;
      var go = function () { post({ action: 'new_page', title: t, to_menu: m.querySelector('.np-m').checked ? 1 : '' }).then(function (d) { if (d.ok) location.href = d.url; else alert(d.error); }); };
      if (dirty) save().then(go); else go();
    }, 'Sukurti');
    m.querySelector('.np-t').focus();
  }

  bar.addEventListener('click', function (e) {
    var b = e.target.closest('[data-a]'); if (!b) return;
    var a = b.dataset.a;
    if (a === 'save') save();
    if (a === 'undo') { if (confirm('Grąžinti šį puslapį, koks buvo prieš paskutinį išsaugojimą?')) post({ action: 'undo', slug: C.slug }).then(function (d) { if (d.ok) { dirty = false; location.reload(); } else say(d.error, true); }); }
    if (a === 'menu') menuModal();
    if (a === 'look') lookModal();
    if (a === 'newpage') newPageModal();
    if (a === 'delpage') { if (confirm('Ištrinti šį puslapį visam laikui?')) post({ action: 'delete_page', slug: C.slug }).then(function (d) { if (d.ok) { dirty = false; location.href = d.url; } else alert(d.error); }); }
    if (a === 'exit') { if (dirty && !confirm('Yra neišsaugotų pakeitimų. Išeiti be išsaugojimo?')) return; dirty = false; location.href = C.exit || '?'; }
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
