// LTKF svetainės redaktorius: bet kurį tekstą puslapyje galima pakeisti tiesiai (spausti ir rašyti), skiltis - paslėpti,
// tarp skilčių - pridėti blokų (tekstas, nuotrauka, galerija, failai, mygtukas, vaizdo įrašas, kortelės).
// Apačioje juosta: Išsaugoti, Atšaukti, Meniu, Išvaizda ir kontaktai, Naujas puslapis, Failų dėžė, Baigti.
(function () {
  'use strict';
  var C = window.LTKF_ED; if (!C) return;
  var main = document.querySelector('main'), foot = document.querySelector('footer.site-footer'), partners = document.querySelector('.partners-band');
  var header = document.querySelector('header.site-header');
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
    '<button type="button" class="ed-b" data-a="versions" title="Visi šio puslapio išsaugojimai - galima atkurti bet kurį">🕘 Versijos</button>' +
    '<span class="ed-sep"></span>' +
    '<button type="button" class="ed-b" data-a="menu">☰ Meniu</button>' +
    '<button type="button" class="ed-b" data-a="look">🎨 Išvaizda ir kontaktai</button>' +
    '<button type="button" class="ed-b" data-a="newpage">📄 Naujas puslapis</button>' +
    '<a class="ed-b ed-inbox" href="failai.php" target="_blank" title="Neradote, kur įdėti? Įmeskite failus ir parašykite komentarą - vėliau sudėsime">📥 Failų dėžė <small>įmesk failus ir komentarą</small></a>' +
    (C.custom ? '<button type="button" class="ed-b ed-danger" data-a="delpage">🗑 Ištrinti puslapį</button>' : '') +
    '<span class="ed-status" id="edStatus">Spauskite bet kurį tekstą (ir meniu) ir rašykite. Prie skilties - 🎨 fonas, 🙈 slėpti, 🗑 ištrinti; tarp skilčių - „+ Pridėti“.</span>' +
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
  prepareTexts(main); prepareTexts(foot); prepareTexts(partners);
  document.addEventListener('input', function (e) { if (e.target.closest && e.target.closest('main, footer, .partners-band, header, .ed-modal')) mark(); });

  // ---------- Antraštė: pavadinimas ir meniu punktai keičiami tiesiai (#82) ----------
  var brandB = header && header.querySelector('.brand-text b'), brandS = header && header.querySelector('.brand-text small');
  [[brandB, 'trumpas'], [brandS, 'pavadinimas']].forEach(function (x) {
    if (!x[0]) return;
    x[0].setAttribute('contenteditable', 'true'); x[0].classList.add('ed-txt-h'); x[0].dataset.nust = x[1]; x[0].dataset.cur = norm(x[0].innerText);
  });
  var navLinks = header ? Array.prototype.filter.call(header.querySelectorAll('.main-nav > a'), function (a) { return !a.closest('.lang-sw'); }) : [];
  navLinks.forEach(function (a, i) {
    a.dataset.mi = i; a.dataset.cur = norm(a.innerText);
    var t = document.createElement('span'); t.className = 'ed-navtools ed-ui';
    t.innerHTML = '<button type="button" data-nav="ren" title="Pervadinti">✎</button><button type="button" data-nav="go" title="Atidaryti puslapį">↗</button><button type="button" data-nav="del" title="Išimti iš meniu">×</button>';
    a.appendChild(t);
  });
  if (header && header.querySelector('.main-nav')) {
    var addNav = document.createElement('button'); addNav.type = 'button'; addNav.className = 'ed-navadd ed-ui'; addNav.textContent = '＋ Punktas'; addNav.title = 'Pridėti meniu punktą';
    var lsw = header.querySelector('.main-nav .lang-sw'); header.querySelector('.main-nav').insertBefore(addNav, lsw || null);
    addNav.addEventListener('click', function () { menuModal(true); });
  }
  var navDel = [];
  function renameNav(a) {
    var span = a.querySelector('.ed-navlab');
    if (!span) { span = document.createElement('span'); span.className = 'ed-navlab'; while (a.firstChild && a.firstChild !== a.querySelector('.ed-navtools')) span.appendChild(a.firstChild); a.insertBefore(span, a.firstChild); }
    if (span.getAttribute('contenteditable') === 'true') return;   // jau rašoma - žymeklis lieka, kur paspausta
    span.setAttribute('contenteditable', 'true'); span.focus();
    var r = document.createRange(); r.selectNodeContents(span); var sl = getSelection(); sl.removeAllRanges(); sl.addRange(r);
  }
  if (header) header.addEventListener('click', function (e) {
    var b = e.target.closest('[data-nav]');
    if (b) {
      e.preventDefault(); e.stopPropagation();
      var a = b.closest('a');
      if (b.dataset.nav === 'del') { if (confirm('Išimti „' + norm(a.dataset.cur) + '“ iš meniu?')) { navDel.push(+a.dataset.mi); a.hidden = true; mark(); } return; }
      if (b.dataset.nav === 'go') {   // #168: puslapis atidaromas tik per ↗
        if (dirty && !confirm('Yra neišsaugotų pakeitimų. Išeiti?')) return;
        dirty = false; location.href = a.getAttribute('href'); return;
      }
      renameNav(a);
      return;
    }
    // #168: redaguojant paspaudus meniu punktą - jo pavadinimas keičiamas, puslapis neatidaromas
    var na = e.target.closest('.main-nav > a');
    if (na && navLinks.indexOf(na) !== -1) { e.preventDefault(); e.stopPropagation(); renameNav(na); return; }
    if (e.target.closest('.ed-navlab[contenteditable], .ed-txt-h')) e.preventDefault();
  }, true);
  if (header) header.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.closest('.ed-navlab, .ed-txt-h')) { e.preventDefault(); e.target.blur(); } });
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
      if (a.closest('header') && (e.target.closest('.ed-navlab[contenteditable], .ed-txt-h, .ed-navtools') || navLinks.indexOf(a) !== -1)) { e.preventDefault(); return; }
      if (a.closest('header') && !a.closest('.lang-sw')) { if (dirty && !confirm('Yra neišsaugotų pakeitimų. Išeiti?')) { e.preventDefault(); return; } dirty = false; return; }
      e.preventDefault();
    }
  }, true);

  // ---------- Skiltys: slėpti / rodyti ----------
  // Kiekvienai skilčiai: 🎨 fonas, 🙈 slėpti / 👁 rodyti, 🗑 ištrinti (ištrinta - suskleista, galima grąžinti) (#83)
  function applyBg(sec) {
    var bg = sec._bg || {};
    sec.style.backgroundColor = bg.c || '';
    sec.style.backgroundImage = bg.img ? 'url(' + bg.img + ')' : '';
    sec.style.backgroundSize = bg.img ? 'cover' : ''; sec.style.backgroundPosition = bg.img ? 'center' : '';
    sec.classList.toggle('lb-bg', !!(bg.c || bg.img)); sec.classList.toggle('lb-light', !!bg.light);
  }
  main.querySelectorAll(':scope > section[data-sec]').forEach(function (sec) {
    try { sec._bg = JSON.parse(sec.getAttribute('data-bg') || 'null') || {}; } catch (x) { sec._bg = {}; }
    var tools = document.createElement('div'); tools.className = 'ed-sec-tools ed-ui';
    tools.innerHTML = '<button type="button" data-st="bg" title="Skilties fonas: spalva ar nuotrauka">🎨 Fonas</button><button type="button" data-st="hide"></button><button type="button" data-st="del"></button>' +
      '<div class="ed-bgpop" hidden><label>Spalva <input type="color" data-bg="c" value="' + esc(sec._bg.c || '#f4f1ea') + '"></label><button type="button" data-st="img">📷 Nuotrauka</button>' +
      '<label class="ed-check"><input type="checkbox" data-bg="light"' + (sec._bg.light ? ' checked' : '') + '> Šviesus tekstas</label><button type="button" data-st="nobg">✕ Be fono</button></div>';
    var upd = function () {
      var h = sec.classList.contains('lb-hidden'), d = sec.classList.contains('lb-deleted');
      var hb = tools.querySelector('[data-st=hide]'), db = tools.querySelector('[data-st=del]');
      hb.textContent = h ? '👁 Rodyti' : '🙈 Slėpti'; hb.title = h ? 'Lankytojai šios skilties nemato' : 'Paslėpti šią skiltį nuo lankytojų'; hb.hidden = d;
      db.textContent = d ? '↺ Grąžinti skiltį' : '🗑 Ištrinti'; db.title = d ? 'Grąžinti ištrintą skiltį' : 'Ištrinti visą skiltį (galima grąžinti)';
    };
    tools.addEventListener('click', function (e) {
      var b = e.target.closest('[data-st]'); if (!b) return;
      var st = b.dataset.st, pop = tools.querySelector('.ed-bgpop');
      if (st === 'bg') { pop.hidden = !pop.hidden; return; }
      if (st === 'hide') { sec.classList.toggle('lb-hidden'); upd(); mark(); }
      if (st === 'del') { if (sec.classList.contains('lb-deleted') || confirm('Ištrinti visą šią skiltį? Lankytojai jos nebematys (galima grąžinti).')) { sec.classList.toggle('lb-deleted'); upd(); mark(); } }
      if (st === 'nobg') { sec._bg = {}; applyBg(sec); mark(); }
      if (st === 'img') picker('image/jpeg,image/png,image/webp', false, function (fs) { if (fs[0]) upload('image', fs[0]).then(function (d) { if (d.ok) { sec._bg.img = d.url; applyBg(sec); mark(); } }); });
    });
    tools.addEventListener('input', function (e) {
      var k = e.target.dataset.bg; if (!k) return;
      if (k === 'c') sec._bg.c = e.target.value;
      if (k === 'light') sec._bg.light = e.target.checked;
      applyBg(sec); mark();
    });
    tools.addEventListener('change', function (e) { if (e.target.dataset.bg === 'light') { sec._bg.light = e.target.checked; applyBg(sec); mark(); } });
    if (getComputedStyle(sec).position === 'static') sec.style.position = 'relative';
    sec.appendChild(tools); upd();
  });

  // ---------- Blokai ----------
  var TYPES = [['h', 'Antraštė', '🔠'], ['text', 'Tekstas', '📝'], ['img', 'Nuotrauka', '🖼️'], ['gallery', 'Galerija', '🖼️🖼️'], ['files', 'Failai (dokumentai)', '📎'],
    ['btn', 'Mygtukas / nuoroda', '🔗'], ['video', 'Vaizdo įrašas (YouTube)', '▶️'], ['cards', 'Kortelės', '🗂️'], ['sec', 'Nauja skiltis su fonu', '🧱']];
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
      case 'sec':
        h = '<section class="lb-sec' + (b.light ? ' lb-light' : '') + '" style="' + (b.c ? 'background-color:' + esc(b.c) + ';' : '') + (b.img ? 'background-image:url(' + esc(b.img) + ');background-size:cover;background-position:center;' : '') + '"><div class="lb-sec-in">' +
          '<h2 class="ed-plain" contenteditable="true" data-f="title">' + esc(b.title || '') + '</h2>' +
          '<div class="ed-rt">' + [['bold', '<b>B</b>'], ['italic', '<i>I</i>'], ['h3', 'H3'], ['p', '¶'], ['ul', '• Sąrašas'], ['link', '🔗 Nuoroda']].map(function (x) { return '<button type="button" data-rt="' + x[0] + '">' + x[1] + '</button>'; }).join('') + '</div>' +
          '<div class="prose ed-rich" contenteditable="true">' + (b.html || '<p>Įrašykite tekstą…</p>') + '</div></div></section>' +
          '<div class="ed-row"><label>Fono spalva <input type="color" data-f="c" value="' + esc(b.c || '#f4f1ea') + '"></label><button type="button" class="ed-b" data-up="secimg">📷 Fono nuotrauka</button>' +
          (b.img ? '<button type="button" class="ed-b" data-rm="secimg">✕ Be nuotraukos</button>' : '') + '<label class="ed-check"><input type="checkbox" data-f="light"' + (b.light ? ' checked' : '') + '> Šviesus tekstas</label></div>';
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
    if (t === 'sec') { b.title = 'Nauja skiltis'; b.html = '<p>Įrašykite tekstą…</p>'; b.c = '#f4f1ea'; }
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
    if (t.dataset.rm === 'secimg') { b.img = ''; renderBody(blk); mark(); return; }
    if (t.dataset.up === 'secimg') return picker('image/jpeg,image/png,image/webp', false, function (fs) { if (fs[0]) upload('image', fs[0]).then(function (d) { if (d.ok) { b.img = d.url; renderBody(blk); mark(); } }); });
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
    if (t.dataset.f && t.type !== 'checkbox') { b[t.dataset.f] = t.isContentEditable ? t.innerText : t.value; if (b.t === 'video' && t.dataset.f === 'url') { clearTimeout(blk._vt); blk._vt = setTimeout(function () { renderBody(blk); }, 700); } }
    if (b.t === 'sec' && t.dataset.f === 'c') { var sp = blk.querySelector('.lb-sec'); if (sp) sp.style.backgroundColor = t.value; }
    if (t.dataset.fi !== undefined) b.items[+t.dataset.fi].name = t.value;
    if (t.dataset.ci !== undefined) b.items[+t.dataset.ci][t.dataset.cf] = t.value;
    if (t.classList.contains('ed-rich')) b.html = t.innerHTML;
  });
  main.addEventListener('change', function (e) {
    var blk = e.target.closest('.ed-block'); if (!blk) return;
    if (e.target.dataset.f) {
      blk._b[e.target.dataset.f] = e.target.type === 'checkbox' ? (e.target.checked ? 1 : 0) : e.target.value;
      if (e.target.dataset.f === 'w' || blk._b.t === 'sec') { if (blk._b.t === 'sec') blk._b.html = blk.querySelector('.ed-rich').innerHTML; renderBody(blk); }
      mark();
    }
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
      (el.closest('footer, .partners-band') ? bendri : pages[C.slug]).texts[el.getAttribute('data-o')] = now;
    });
    main.querySelectorAll(':scope > section[data-sec].lb-hidden').forEach(function (s) { pages[C.slug].hidden.push(s.dataset.sec); });
    pages[C.slug].deleted = []; pages[C.slug].secbg = {};
    main.querySelectorAll(':scope > section[data-sec]').forEach(function (s) {
      if (s.classList.contains('lb-deleted')) pages[C.slug].deleted.push(s.dataset.sec);
      var bg = s._bg || {}; if (bg.c || bg.img) pages[C.slug].secbg[s.dataset.sec] = { c: bg.c || '', img: bg.img || '', light: bg.light ? 1 : 0 };
    });
    zones.forEach(function (z) {
      var list = Array.prototype.map.call(z.querySelectorAll(':scope > .ed-block'), function (el) { var b = el._b; if (b.t === 'text' || b.t === 'sec') b.html = el.querySelector('.ed-rich').innerHTML; if (b.t === 'h') b.text = el.querySelector('[data-f=text]').innerText; if (b.t === 'sec') b.title = el.querySelector('[data-f=title]').innerText; return b; });
      if (list.length) pages[C.slug].blocks[z.dataset.zone] = list;
    });
    if (Object.keys(bendri.texts).length) pages._bendri = bendri;
    var h1 = main.querySelector('h1'); if (C.custom && h1) pages[C.slug].title = norm(h1.innerText);
    return pages;
  }
  // Antraštė: pakeistas pavadinimas -> nustatymai, pakeisti ar išimti meniu punktai -> meniu
  function saveHeader() {
    var jobs = [], nust = {};
    [brandB, brandS].forEach(function (el) { if (el && norm(el.innerText) !== el.dataset.cur) nust[el.dataset.nust] = norm(el.innerText); });
    if (Object.keys(nust).length) jobs.push(post({ action: 'settings', nust: JSON.stringify(nust) }).then(function (d) { if (d.ok) [brandB, brandS].forEach(function (el) { if (el) el.dataset.cur = norm(el.innerText); }); return d; }));
    var changed = navDel.length > 0;
    var items = (C.menu || []).map(function (it, i) {
      var a = navLinks[i]; if (!a) return it;
      var lab = a.querySelector('.ed-navlab'), txt = norm(lab ? lab.innerText : a.dataset.cur);
      if (txt !== a.dataset.cur) changed = true;
      return { l: txt || it.l, h: it.h };
    }).filter(function (it, i) { return navDel.indexOf(i) < 0; });
    if (changed) jobs.push(post({ action: 'menu', menu: JSON.stringify(items) }).then(function (d) {
      if (d.ok) {
        var reload = navDel.length > 0;
        C.menu = items; navDel = [];
        navLinks.forEach(function (a) { var lab = a.querySelector('.ed-navlab'); a.dataset.cur = norm(lab ? lab.innerText : a.dataset.cur); if (lab) lab.removeAttribute('contenteditable'); });
        if (reload) setTimeout(function () { location.reload(); }, 600);   // išimti punktai - meniu iš naujo
      }
      return d;
    }));
    return Promise.all(jobs).then(function (rs) { return rs.filter(function (r) { return !r.ok; })[0] || { ok: true }; });
  }
  function save() {
    say('Saugoma…');
    return Promise.all([post({ action: 'save', pages: JSON.stringify(collect()) }), saveHeader()]).then(function (rs) {
      var d = rs[0].ok ? rs[1] : rs[0];
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
  function menuModal(withNew) {
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
    if (withNew === true) setTimeout(function () { var ab = m.querySelector('[data-mi=add]'); if (ab) ab.click(); }, 0);
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

  // D6 2 dalis: naujienos ir dokumentai kaip įrašai - „+ Nauja“, „✏ Keisti“, „🗑“
  function itemModal(it) {
    if (dirty && !confirm('Yra neišsaugotų teksto pakeitimų - jie bus prarasti. Tęsti?')) return;
    var news = it.kind === 'news', box = document.querySelector('.ed-items[data-kind="' + it.kind + '"]');
    var groups = box && box.dataset.groups ? JSON.parse(box.dataset.groups) : [];
    var html = '<label class="ed-f">Pavadinimas<input type="text" class="it-title" maxlength="200" value="' + esc(it.title || '') + '"></label>' + (news
      ? '<label class="ed-f">Data<input type="date" class="it-date" value="' + esc(it.date || new Date().toISOString().slice(0, 10)) + '"></label>' +
        '<label class="ed-f">Trumpai (sąraše)<input type="text" class="it-sum" maxlength="400" value="' + esc(it.summary || '') + '"></label>' +
        '<label class="ed-f">Tekstas <small>(pastraipos - tuščia eilutė tarp jų)</small><textarea class="it-body" rows="8">' + esc(it.body || '') + '</textarea></label>'
      : '<label class="ed-f">Grupė<input type="text" class="it-grp" list="itGroups" value="' + esc(it.grp || groups[0] || '') + '"></label><datalist id="itGroups">' + groups.map(function (g) { return '<option value="' + esc(g) + '">'; }).join('') + '</datalist>' +
        '<div class="ed-f">Failas: <span class="it-fname">' + (it.file ? esc(it.file.split('/').pop()) : 'neįkeltas') + '</span> <button type="button" class="ed-b it-up">📎 Įkelti failą</button><input type="hidden" class="it-file" value="' + esc(it.file || '') + '"></div>');
    var m = modal(it.id ? (news ? 'Keisti naujieną' : 'Keisti dokumentą') : (news ? 'Nauja naujiena' : 'Naujas dokumentas'), html, function (mm) {
      var q = function (c) { var x = mm.querySelector(c); return x ? x.value : ''; };
      post({ action: 'item_save', kind: it.kind, id: it.id || '', title: q('.it-title'), date: q('.it-date'), summary: q('.it-sum'), body: q('.it-body'), grp: q('.it-grp'), file: q('.it-file') })
        .then(function (d) { if (d.ok) { dirty = false; location.reload(); } else alert(d.error); });
    });
    var up = m.querySelector('.it-up');
    if (up) up.addEventListener('click', function () {
      picker('', false, function (fs) { if (fs[0]) upload('file', fs[0]).then(function (d) { if (d.ok) { m.querySelector('.it-file').value = d.url; m.querySelector('.it-fname').textContent = fs[0].name; if (!m.querySelector('.it-title').value) m.querySelector('.it-title').value = d.name; } }); });
    });
    m.querySelector('.it-title').focus();
  }
  document.querySelectorAll('.ed-items').forEach(function (box) {
    box.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      if (b.dataset.itemNew) return itemModal({ kind: b.dataset.itemNew });
      var li = b.closest('[data-item]'); if (!li) return;
      var it = JSON.parse(li.dataset.item);
      if (b.hasAttribute('data-item-edit')) itemModal(it);
      if (b.hasAttribute('data-item-del') && confirm('Ištrinti „' + it.title + '“?')) post({ action: 'item_delete', id: it.id }).then(function (d) { if (d.ok) location.reload(); else alert(d.error); });
    });
  });

  // D6: puslapio istorija - kiekvienas išsaugojimas; „Atkurti“ (dabartinė irgi lieka istorijoje)
  function versionsModal() {
    post({ action: 'versions', slug: C.slug }).then(function (d) {
      var list = (d && d.list) || [];
      var m = modal('🕘 Puslapio versijos', '<p class="ed-hint">Dabar: ' + esc(d.now || '-') + '</p>' +
        (list.length ? '<ul class="ed-vers">' + list.map(function (v) { return '<li><span>' + esc(v.at) + (v.by ? ' · ' + esc(v.by) : '') + '</span><button type="button" class="ed-b" data-ver="' + v.id + '">Atkurti</button></li>'; }).join('') + '</ul>'
          : '<p>Ankstesnių versijų dar nėra - jos atsiras po kito išsaugojimo.</p>'));
      m.addEventListener('click', function (e) {
        var b = e.target.closest('[data-ver]'); if (!b) return;
        if (dirty && !confirm('Neišsaugoti pakeitimai bus prarasti. Atkurti?')) return;
        post({ action: 'restore', slug: C.slug, id: b.dataset.ver }).then(function (r) { if (r.ok) { dirty = false; location.reload(); } else say(r.error, true); });
      });
    });
  }

  bar.addEventListener('click', function (e) {
    var b = e.target.closest('[data-a]'); if (!b) return;
    var a = b.dataset.a;
    if (a === 'save') save();
    if (a === 'undo') { if (confirm('Grąžinti šį puslapį, koks buvo prieš paskutinį išsaugojimą?')) post({ action: 'undo', slug: C.slug }).then(function (d) { if (d.ok) { dirty = false; location.reload(); } else say(d.error, true); }); }
    if (a === 'versions') versionsModal();
    if (a === 'menu') menuModal();
    if (a === 'look') lookModal();
    if (a === 'newpage') newPageModal();
    if (a === 'delpage') { if (confirm('Ištrinti šį puslapį visam laikui?')) post({ action: 'delete_page', slug: C.slug }).then(function (d) { if (d.ok) { dirty = false; location.href = d.url; } else alert(d.error); }); }
    if (a === 'exit') { if (dirty && !confirm('Yra neišsaugotų pakeitimų. Išeiti be išsaugojimo?')) return; dirty = false; location.href = C.exit || '?'; }
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
