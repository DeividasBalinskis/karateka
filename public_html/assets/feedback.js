// Atsiliepimų langas (app/feedback.php) visose svetainėse: karateka.lt, Budo, LTKF.
// Rašai - išsaugoma pati; Ctrl+V - įklijuoja ekrano nuotrauką (galima daug), taip pat - nutempti ar „📎“;
// Enter - išsaugo ir pradeda naują (Shift+Enter - nauja eilutė); apačioje - ankstesni atsiliepimai, paspaudus - taisyti.
// v1.87: sąrašas sugrupuotas pagal vietą (Budo / Karateka / LTKF ir jų skiltys) - pirmiausia ten, kur esate dabar;
// paieška ir būsena; langas, filtrai ir nebaigtas tekstas išlieka pereinant į kitą puslapį (šioje naršyklės kortelėje).
(function () {
  var T = window.tr || function (s, v) { if (v) for (var k in v) s = s.split('{' + k + '}').join(v[k]); return s; };
  var SKEY = 'fb-state-v1', KEEP = 3 * 3600 * 1000;

  function init(box) {
    var cfg = {};
    try { cfg = JSON.parse(box.getAttribute('data-cfg') || '{}'); } catch (e) {}
    var $ = function (id) { return document.getElementById(id); };
    var fab = $('fbFab'), panel = $('fbPanel'), text = $('fbText'), imgs = $('fbImgs'), state = $('fbState');
    var list = $('fbList'), mine = $('fbMine'), editing = $('fbEditing'), fileIn = $('fbFile'), nameIn = $('fbName');
    var qIn = $('fbQ'), stSel = $('fbSt'), gBox = $('fbGroups'), sBox = $('fbSubs'), adminLink = box.querySelector('.fb-admin');
    var fabN = fab.querySelector('.fb-fab-n');
    var cur = { id: 0, images: [] }, items = [], timer = null, chain = Promise.resolve();
    // „Mano“ - tik savo (taisyti), „Visi“ - kitų irgi, bet tik skaityti
    var tab = 'mine', allItems = [], openAll = {}, here = null, groups = {}, shown = 8, restoring = false;
    var flt = { g: '', s: '', st: 'all', stSet: false, q: '' };

    function setState(t, cls) { state.textContent = t; state.className = 'fb-state ' + (cls || ''); }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function fold(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }

    // ---------- Kur esate: atidaryti skirtukai (Budo: Tvarkaraštis / Rezultatai / Administracija, atidaryta kova) ----------
    function tabsNow() {
      if (document.body.classList.contains('bout-open-body')) return { sk: 'kova', sec: 'Kova' };
      var keys = [], names = [];
      document.querySelectorAll('[role="tab"][aria-selected="true"]').forEach(function (t) {
        if (box.contains(t) || !t.offsetParent) return;
        var c = t.cloneNode(true);
        c.querySelectorAll('.tab-n, [aria-hidden="true"]').forEach(function (x) { x.remove(); });
        var n = c.textContent.replace(/\s+/g, ' ').trim();
        if (!n) return;
        keys.push((t.id || '').replace(/^tab-/, '') || n); names.push(n);
      });
      return { sk: keys.join('/'), sec: names.join(' › ') };
    }
    function context() {
      var tn = tabsNow();
      return JSON.stringify({ title: document.title, url: location.pathname + location.search + location.hash, w: window.innerWidth, h: window.innerHeight,
        ua: navigator.userAgent.slice(0, 160), sk: tn.sk, sec: tn.sec });
    }
    function form(extra) {
      var f = new FormData();
      f.append('_csrf', cfg.csrf); f.append('page', cfg.page || (location.pathname + location.search)); f.append('site', cfg.site || 'karateka'); f.append('context', context());
      if (nameIn && nameIn.value.trim()) f.append('name', nameIn.value.trim());
      Object.keys(extra).forEach(function (k) { f.append(k, extra[k]); });
      return f;
    }
    function send(extra, keepalive) {
      return fetch(cfg.api, { method: 'POST', credentials: 'same-origin', keepalive: !!keepalive, headers: { Accept: 'application/json', 'X-CSRF-Token': cfg.csrf }, body: form(extra) })
        .then(function (r) { return r.json().catch(function () { return { ok: false, error: T('Serverio klaida') + ' (' + r.status + ')' }; }); });
    }

    // ---------- Išlieka pereinant į kitą puslapį: atidarytas langas, skirtukas, filtrai, tekstas ----------
    function remember() {
      try { sessionStorage.setItem(SKEY, JSON.stringify({ open: !panel.hidden, tab: tab, flt: flt, id: cur.id, text: text.value, at: Date.now() })); } catch (e) {}
      if (fabN) { var d = panel.hidden && text.value.trim() !== ''; fabN.hidden = !d; fabN.textContent = d ? '✎' : ''; fab.classList.toggle('has-draft', d); }
    }
    function recall() {
      try { var s = JSON.parse(sessionStorage.getItem(SKEY) || 'null'); return s && Date.now() - (s.at || 0) < KEEP ? s : null; } catch (e) { return null; }
    }

    // ---------- Sąrašas: grupės ir skiltys, pirmiausia - kur esate ----------
    function stOk(it) {
      if (flt.st === 'open') return it.status === 'new' || it.status === 'planned';
      if (flt.st === 'done') return it.status === 'done' || it.status === 'declined';
      return true;
    }
    function qOk(it) {
      if (!flt.q.trim()) return true;
      var h = fold([it.title, it.body, it.author, it.reply, it.gl, it.sl, it.sec, '#' + it.id].join(' '));
      return fold(flt.q).split(/\s+/).every(function (w) { return !w || h.indexOf(w) !== -1; });
    }
    function isHere(it) { return !!here && it.g === here.g && it.s === here.s; }
    function chip(attr, val, label, n, on, pin) {
      return '<button type="button" class="fb-chip' + (on ? ' on' : '') + (pin ? ' here' : '') + '" ' + attr + '="' + esc(val) + '" aria-pressed="' + (on ? 'true' : 'false') + '"' +
        (pin ? ' title="' + esc(T('Čia, kur esate dabar')) + '"' : '') + '>' + (pin ? '📍 ' : '') + esc(label) + ' <span class="fb-chip-n">' + n + '</span></button>';
    }
    function renderChips(base) {
      var gc = {}, keys = Object.keys(groups);
      base.forEach(function (it) { gc[it.g] = (gc[it.g] || 0) + 1; });
      if (here) keys.sort(function (a, b) { return (b === here.g) - (a === here.g); });
      keys = keys.filter(function (k) { return gc[k] || (here && k === here.g); });
      gBox.innerHTML = keys.map(function (k) { return chip('data-g', k, groups[k], gc[k] || 0, flt.g === k, !!here && here.g === k); }).join('') +
        chip('data-g', '*', T('Visur'), base.length, flt.g === '*', false);
      gBox.hidden = !base.length && !flt.q;
      var inG = flt.g === '*' ? [] : base.filter(function (it) { return it.g === flt.g; });
      var sc = {}, sl = {}, hereG = !!here && here.g === flt.g;
      inG.forEach(function (it) { sc[it.s] = (sc[it.s] || 0) + 1; sl[it.s] = it.sl; });
      if (hereG && !sc[here.s]) { sc[here.s] = 0; sl[here.s] = here.sl; }
      var sk = Object.keys(sc).sort(function (a, b) {
        return ((hereG && b === here.s) - (hereG && a === here.s)) || (sc[b] - sc[a]) || a.localeCompare(b);
      });
      if (flt.s && !(flt.s in sc)) flt.s = '';
      sBox.hidden = flt.g === '*' || !sk.length;
      sBox.innerHTML = chip('data-s', '', T('Visos skiltys'), inG.length, flt.s === '', false) +
        sk.map(function (k) { return chip('data-s', k, sl[k], sc[k], flt.s === k, hereG && here.s === k); }).join('');
      if (adminLink && cfg.admin) adminLink.href = cfg.admin + (flt.g && flt.g !== '*' ? '?g=' + encodeURIComponent(flt.g) + (flt.s ? '&s=' + encodeURIComponent(flt.s) : '') : '');
    }
    function where(it) {
      var w = (flt.g === '*' ? it.gl + ' › ' : '') + (it.sec && it.sec.indexOf(it.sl) === 0 ? it.sec : it.sl);
      return '<span class="fb-where' + (isHere(it) ? ' here' : '') + '">' + (isHere(it) ? '📍 ' : '') + esc(w) + '</span>';
    }
    function filtered() {
      var base = (tab === 'all' ? allItems : items).filter(function (it) { return stOk(it) && qOk(it); });
      renderChips(base);
      var out = base.filter(function (it) { return flt.g === '*' || (it.g === flt.g && (!flt.s || it.s === flt.s)); });
      out.sort(function (a, b) {
        return ((b.id === cur.id) - (a.id === cur.id)) || (isHere(b) - isHere(a)) || (b.id - a.id);
      });
      return { base: base, out: out };
    }
    function more(total, n) {
      return total > n ? '<li><button type="button" class="fb-more" data-fb-more>' + esc(T('Rodyti dar ({n})', { n: total - n })) + ' ↓</button></li>' : '';
    }
    function empty(base) {
      if (!(tab === 'all' ? allItems : items).length) {
        return '<li class="muted small">' + esc(tab === 'all' ? T('Atsiliepimų dar nėra.') : T('Čia bus tik jūsų atsiliepimai. Kitų - „Visi“.')) + '</li>';
      }
      return '<li class="muted small">' + esc(base.length ? T('Šioje vietoje atsiliepimų nėra.') : T('Nieko nerasta.')) +
        (flt.g !== '*' || flt.q || flt.st !== 'all' ? ' <button type="button" class="fb-link" data-fb-reset>' + esc(T('Rodyti visus')) + ' →</button>' : '') + '</li>';
    }
    function renderList() {
      var r = filtered(), view = r.out.slice(0, shown);
      if (tab === 'mine') mine.hidden = !items.length && !mine.querySelector('.fb-tabs');
      if (!r.out.length) { list.innerHTML = empty(r.base); return; }
      if (tab === 'all') {
        list.innerHTML = view.map(function (it) {
          var o = !!openAll[it.id];
          return '<li class="fb-item fb-ro' + (o ? ' open' : '') + (isHere(it) ? ' is-here' : '') + '" data-all-id="' + it.id + '" tabindex="0" role="button" title="' + esc(T('Paspauskite - visas tekstas')) + '">' +
            '<div class="fb-item-top"><span class="fb-item-title">' + esc(it.title) + '</span>' + (it.images.length ? '<span class="fb-item-img">🖼 ' + it.images.length + '</span>' : '') + '</div>' +
            '<div class="fb-item-meta"><span>' + where(it) + ' · ' + esc(it.author || '') + (it.mine ? ' · ' + esc(T('jūsų')) : '') + ' · ' + esc(it.date) + '</span><span class="fb-st st-' + esc(it.status) + '">' + esc(it.statusLabel) + '</span></div>' +
            (o ? '<div class="fb-ro-body">' + esc(it.body).replace(/\n/g, '<br>') + '</div>' +
              (it.images.length ? '<div class="fb-ro-imgs">' + it.images.map(function (im) { return '<a href="' + esc(im.full) + '" target="_blank" rel="noopener"><img src="' + esc(im.thumb) + '" alt=""></a>'; }).join('') + '</div>' : '') +
              (it.href ? '<a class="fb-go" href="' + esc(it.href) + '">' + esc(T('Atidaryti vietą')) + ' →</a>' : '') : '') +
            (it.reply ? '<div class="fb-reply"><b>' + esc(T('Atsakymas:')) + '</b> ' + esc(it.reply) + '</div>' : '') + '</li>';
        }).join('') + more(r.out.length, view.length);
        return;
      }
      list.innerHTML = view.map(function (it) {
        return '<li class="fb-item' + (it.id === cur.id ? ' on' : '') + (isHere(it) ? ' is-here' : '') + '" data-id="' + it.id + '" tabindex="0" role="button" title="' + esc(T('Paspauskite - taisyti')) + '">' +
          '<div class="fb-item-top"><span class="fb-item-title">' + esc(it.title) + '</span>' + (it.images.length ? '<span class="fb-item-img" title="' + esc(T('Nuotraukos')) + '">🖼 ' + it.images.length + '</span>' : '') + '</div>' +
          '<div class="fb-item-meta"><span>' + where(it) + ' · ' + esc(it.date) + '</span><span class="fb-st st-' + esc(it.status) + '">' + esc(it.statusLabel) + '</span></div>' +
          (it.reply ? '<div class="fb-reply"><b>' + esc(T('Atsakymas:')) + '</b> ' + esc(it.reply) + '</div>' : '') + '</li>';
      }).join('') + more(r.out.length, view.length);
    }
    function syncControls() {
      mine.querySelectorAll('[data-fb-tab]').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-fb-tab') === tab); });
      if (qIn && qIn.value !== flt.q) qIn.value = flt.q;
      if (stSel) stSel.value = flt.st;
    }
    function listUrl(all) {
      var tn = tabsNow();
      return cfg.api + (cfg.api.indexOf('?') === -1 ? '?' : '&') + 'list=1' + (all ? '&all=1' : '') + '&site=' + encodeURIComponent(cfg.site || 'karateka') +
        '&here=' + encodeURIComponent(location.pathname + location.search + location.hash) + '&sk=' + encodeURIComponent(tn.sk) + '&sec=' + encodeURIComponent(tn.sec);
    }
    function fetchList(all) {
      return fetch(listUrl(all), { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
        here = d.here || here; groups = d.groups || groups;
        if (!flt.g) flt.g = here ? here.g : '*';
        return d.items || [];
      });
    }
    function load() {
      return fetchList(false).then(function (d) { items = d; afterRestore(); renderList(); })
        .then(function () { if (tab === 'all') return loadAll(); }).catch(function () {});
    }
    function loadAll() {
      list.innerHTML = '<li class="muted small">' + esc(T('Kraunama…')) + '</li>';
      return fetchList(true).then(function (d) { allItems = d; renderList(); }).catch(function () {});
    }
    mine.addEventListener('click', function (e) {
      var tb = e.target.closest('[data-fb-tab]');
      if (tb) {
        tab = tb.getAttribute('data-fb-tab'); shown = 8;
        if (!flt.stSet) flt.st = tab === 'all' ? 'open' : 'all';
        syncControls(); remember();
        if (tab === 'all') loadAll(); else renderList();
        return;
      }
      var g = e.target.closest('[data-g]');
      if (g) { flt.g = g.getAttribute('data-g'); flt.s = ''; shown = 8; remember(); renderList(); return; }
      var s = e.target.closest('[data-s]');
      if (s) { flt.s = s.getAttribute('data-s'); shown = 8; remember(); renderList(); return; }
      if (e.target.closest('[data-fb-reset]')) { flt.g = '*'; flt.s = ''; flt.q = ''; flt.st = 'all'; flt.stSet = true; shown = 8; syncControls(); remember(); renderList(); }
    });
    if (qIn) qIn.addEventListener('input', function () { flt.q = qIn.value; shown = 8; remember(); renderList(); });
    if (stSel) stSel.addEventListener('change', function () { flt.st = stSel.value; flt.stSet = true; shown = 8; remember(); renderList(); });
    list.addEventListener('click', function (e) {
      if (e.target.closest('[data-fb-more]')) { e.stopPropagation(); shown += 20; renderList(); return; }
      var ro = e.target.closest('[data-all-id]');
      if (ro) { if (!e.target.closest('a')) { var id = +ro.getAttribute('data-all-id'); openAll[id] = !openAll[id]; renderList(); } e.stopPropagation(); }
    }, true);
    function upsert(it) {
      if (!it) return;
      var i = items.findIndex(function (x) { return x.id === it.id; });
      if (i === -1) items.unshift(it); else items[i] = it;
      var j = allItems.findIndex(function (x) { return x.id === it.id; });
      if (j !== -1) allItems[j] = it; else if (allItems.length) allItems.unshift(it);
      renderList();
    }

    // ---------- Rašymas: išsaugoma pati ----------
    function renderImgs() {
      imgs.innerHTML = cur.images.map(function (im) {
        return '<span class="fb-img"' + (im.tmp ? ' data-tmp="1"' : '') + '><a href="' + esc(im.full || im.thumb) + '" target="_blank" rel="noopener"><img src="' + esc(im.thumb) + '" alt=""></a>' +
          (im.tmp ? '<i class="fb-spin" aria-label="' + esc(T('Įkeliama')) + '"></i>' : '<button type="button" class="fb-img-x" data-del="' + im.id + '" aria-label="' + esc(T('Pašalinti nuotrauką')) + '">×</button>') + '</span>';
      }).join('');
    }
    function showEditing() {
      editing.hidden = !cur.id;
      if (cur.id) {
        var it = items.find(function (x) { return x.id === cur.id; });
        editing.querySelector('span').textContent = T('Taisomas:') + ' ' + (it ? it.title + ' · ' + it.gl + ' › ' + it.sl : '#' + cur.id);
      }
      renderList();
    }
    function save(keepalive) {
      clearTimeout(timer); timer = null;
      var body = text.value;
      if (!cur.id && !body.trim()) return chain;
      chain = chain.then(function () {
        setState(T('Saugoma…'), 'busy');
        return send({ action: 'save', id: cur.id || '', body: body }, keepalive).then(function (d) {
          if (!d.ok) { setState(d.error || T('Neišsaugota'), 'err'); return; }
          if (d.item) { if (!cur.id) cur.id = d.item.id; upsert(d.item); showEditing(); remember(); }
          setState(T('Išsaugota ✓'), 'ok');
        }).catch(function () { setState(T('Nėra ryšio - neišsaugota'), 'err'); });
      });
      return chain;
    }
    function startNew() {
      cur = { id: 0, images: [] }; text.value = ''; renderImgs(); showEditing(); remember(); text.focus();
    }
    function commit() {
      save().then(function () {
        if (!cur.id) return;
        setState(T('Išsaugota ✓ - galite rašyti kitą'), 'ok');
        startNew();
      });
    }
    text.addEventListener('input', function () { setState(T('Rašoma…'), 'busy'); remember(); clearTimeout(timer); timer = setTimeout(save, 700); });
    text.addEventListener('blur', function () { if (timer) save(); });
    text.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); commit(); }
    });
    $('fbSend').addEventListener('click', commit);
    $('fbNew').addEventListener('click', function () { save().then(startNew); });

    // Grįžus iš kito puslapio: tęsiamas tas pats atsiliepimas (jei tekstas nespėjo išsisaugoti - išsaugomas dabar, be dublikato)
    function afterRestore() {
      if (!restoring) return;
      restoring = false;
      var it = cur.id ? items.find(function (x) { return x.id === cur.id; }) : null;
      if (cur.id && !it) cur.id = 0;
      if (!cur.id && text.value.trim()) {
        it = items.find(function (x) { return x.body === text.value.trim(); });   // išsaugotas paskutinę akimirką (puslapis jau buvo uždarytas)
        if (it) cur.id = it.id;
      }
      if (it) { cur.images = it.images.slice(); renderImgs(); }
      showEditing();
      if (text.value.trim() && (!it || it.body !== text.value.trim())) save();
    }

    // ---------- Nuotraukos: Ctrl+V, nutempti, „📎“ ----------
    function upload(file) {
      if (!cfg.images) { setState(T('Nuotraukos dar neįjungtos'), 'err'); return; }
      if (!/^image\//.test(file.type)) return;
      if (file.size > 15 * 1024 * 1024) { setState(T('Nuotrauka per didelė (iki 15 MB)'), 'err'); return; }
      var tmp = { tmp: true, thumb: URL.createObjectURL(file) };
      cur.images.push(tmp); renderImgs();
      var target = cur;
      chain = chain.then(function () {
        clearTimeout(timer); timer = null;
        setState(T('Įkeliama nuotrauka…'), 'busy');
        var f = file.name ? file : new File([file], 'ekranas.png', { type: file.type });
        return send({ action: 'image', id: target.id || '', body: text.value, file: f }).then(function (d) {
          target.images = target.images.filter(function (x) { return x !== tmp; });
          if (d.item) { if (!target.id) target.id = d.item.id; target.images = d.item.images; upsert(d.item); }
          if (target === cur) { renderImgs(); showEditing(); remember(); }
          setState(d.ok ? T('Nuotrauka pridėta ✓') : (d.error || T('Nepavyko įkelti')), d.ok ? 'ok' : 'err');
        }).catch(function () { target.images = target.images.filter(function (x) { return x !== tmp; }); renderImgs(); setState(T('Nėra ryšio - nuotrauka neįkelta'), 'err'); });
      });
    }
    panel.addEventListener('paste', function (e) {
      var files = [];
      Array.prototype.forEach.call((e.clipboardData && e.clipboardData.items) || [], function (it) { if (it.kind === 'file' && /^image\//.test(it.type)) files.push(it.getAsFile()); });
      if (files.length) { e.preventDefault(); files.forEach(upload); }
    });
    panel.addEventListener('dragover', function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1) { e.preventDefault(); panel.classList.add('drop'); } });
    panel.addEventListener('dragleave', function (e) { if (!panel.contains(e.relatedTarget)) panel.classList.remove('drop'); });
    panel.addEventListener('drop', function (e) {
      if (!e.dataTransfer || !e.dataTransfer.files.length) return;
      e.preventDefault(); panel.classList.remove('drop');
      Array.prototype.forEach.call(e.dataTransfer.files, upload);
    });
    if (fileIn) fileIn.addEventListener('change', function () { Array.prototype.forEach.call(fileIn.files, upload); fileIn.value = ''; });
    imgs.addEventListener('click', function (e) {
      var b = e.target.closest('[data-del]'); if (!b || !cur.id) return;
      var id = cur.id;
      chain = chain.then(function () {
        return send({ action: 'image_delete', id: id, image: b.getAttribute('data-del') }).then(function (d) {
          if (d.item) { upsert(d.item); if (cur.id === id) { cur.images = d.item.images; renderImgs(); } }
        });
      });
    });

    // ---------- Ankstesni: paspaudus - taisyti ----------
    function edit(id) {
      var it = items.find(function (x) { return x.id === id; }); if (!it) return;
      save().then(function () {
        cur = { id: it.id, images: it.images.slice() }; text.value = it.body; renderImgs(); showEditing(); remember();
        text.focus(); text.setSelectionRange(text.value.length, text.value.length);
        setState('', '');
      });
    }
    list.addEventListener('click', function (e) { var li = e.target.closest('[data-id]'); if (li) edit(+li.getAttribute('data-id')); });
    list.addEventListener('keydown', function (e) {
      var li = e.target.closest('[data-id], [data-all-id]');
      if (!li || (e.key !== 'Enter' && e.key !== ' ')) return;
      e.preventDefault();
      if (li.hasAttribute('data-id')) edit(+li.getAttribute('data-id'));
      else { var id = +li.getAttribute('data-all-id'); openAll[id] = !openAll[id]; renderList(); }
    });
    editing.addEventListener('click', function (e) {
      if (!e.target.closest('.fb-del') || !cur.id) return;
      if (!confirm(T('Ištrinti šį atsiliepimą su nuotraukomis?'))) return;
      var id = cur.id;
      send({ action: 'delete', id: id }).then(function (d) {
        if (!d.ok) return;
        items = items.filter(function (x) { return x.id !== id; }); allItems = allItems.filter(function (x) { return x.id !== id; });
        startNew(); setState(T('Ištrinta'), 'ok');
      });
    });

    // ---------- Atidaryti / uždaryti ----------
    function toggle(open, quiet) {
      panel.hidden = !open;
      box.classList.toggle('open', open);
      fab.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) { load(); if (!quiet) text.focus(); }
      else if (timer) save();
      remember();
    }
    fab.addEventListener('click', function () {
      if (panel.hidden && !restoring) { flt.g = ''; flt.s = ''; flt.q = ''; shown = 8; syncControls(); }   // naujai atidarius - pirmiausia ten, kur esate
      toggle(panel.hidden);
    });
    panel.querySelector('.fb-close').addEventListener('click', function () { toggle(false); fab.focus(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { toggle(false); fab.focus(); } });
    window.addEventListener('pagehide', function () { if (timer) save(true); remember(); });

    // Grįžimas iš kito puslapio: tas pats langas, filtrai ir tekstas (telefone langas neatsidaro pats - užimtų visą ekraną; ✎ ant mygtuko)
    var saved = recall();
    if (saved) {
      tab = saved.tab === 'all' && mine.querySelector('[data-fb-tab="all"]') ? 'all' : 'mine';
      if (saved.flt) flt = { g: saved.flt.g || '', s: saved.flt.s || '', st: saved.flt.st || 'all', stSet: !!saved.flt.stSet, q: saved.flt.q || '' };
      cur.id = saved.id || 0; text.value = saved.text || '';
      restoring = !!(cur.id || text.value.trim());
      syncControls();
      if (saved.open && window.matchMedia('(min-width: 641px)').matches) toggle(true, true);
      else if (restoring) { remember(); load(); }
    }
  }

  function start() {
    var box = document.getElementById('fb');
    if (box) { init(box); return; }
    // Kita svetainė (LTKF): langas paimamas iš karateka.lt (ta pati sesija)
    if (window.FB_REMOTE) {
      fetch(window.FB_REMOTE, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.html) return;
        document.body.insertAdjacentHTML('beforeend', d.html);
        var b = document.getElementById('fb'); if (b) init(b);
      }).catch(function () {});
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
