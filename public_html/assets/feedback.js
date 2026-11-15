// Atsiliepimų langas (app/feedback.php) visose svetainėse: karateka.lt, Taika, LTKF.
// Rašai - išsaugoma pati; Ctrl+V - įklijuoja ekrano nuotrauką (galima daug), taip pat - nutempti ar „📎“;
// Enter - išsaugo ir pradeda naują (Shift+Enter - nauja eilutė); apačioje - ankstesni atsiliepimai, paspaudus - taisyti.
(function () {
  function init(box) {
    var cfg = {};
    try { cfg = JSON.parse(box.getAttribute('data-cfg') || '{}'); } catch (e) {}
    var $ = function (id) { return document.getElementById(id); };
    var fab = $('fbFab'), panel = $('fbPanel'), text = $('fbText'), imgs = $('fbImgs'), state = $('fbState');
    var list = $('fbList'), mine = $('fbMine'), editing = $('fbEditing'), fileIn = $('fbFile'), nameIn = $('fbName');
    var cur = { id: 0, images: [] }, items = [], timer = null, chain = Promise.resolve(), loaded = false;

    function setState(t, cls) { state.textContent = t; state.className = 'fb-state ' + (cls || ''); }
    function context() {
      return JSON.stringify({ title: document.title, url: location.pathname + location.search + location.hash, w: window.innerWidth, h: window.innerHeight, ua: navigator.userAgent.slice(0, 160) });
    }
    function form(extra) {
      var f = new FormData();
      f.append('_csrf', cfg.csrf); f.append('page', cfg.page || (location.pathname + location.search)); f.append('site', cfg.site || 'karateka'); f.append('context', context());
      if (nameIn && nameIn.value.trim()) f.append('name', nameIn.value.trim());
      Object.keys(extra).forEach(function (k) { f.append(k, extra[k]); });
      return f;
    }
    function send(extra) {
      return fetch(cfg.api, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': cfg.csrf }, body: form(extra) })
        .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Serverio klaida (' + r.status + ')' }; }); });
    }
    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

    // ---------- Sąrašas ----------
    // Matomi tik 2 naujausi (ir šiuo metu taisomas); kiti - paspaudus „Rodyti visus“
    var showAll = false;
    function renderList() {
      mine.hidden = !items.length;
      var shown = showAll ? items : items.filter(function (it, i) { return i < 2 || it.id === cur.id; });
      list.innerHTML = shown.map(function (it) {
        return '<li class="fb-item' + (it.id === cur.id ? ' on' : '') + '" data-id="' + it.id + '" tabindex="0" role="button" title="Paspauskite - taisyti">' +
          '<div class="fb-item-top"><span class="fb-item-title">' + esc(it.title) + '</span>' + (it.images.length ? '<span class="fb-item-img" title="Nuotraukos">🖼 ' + it.images.length + '</span>' : '') + '</div>' +
          '<div class="fb-item-meta"><span>' + esc(it.date) + '</span><span class="fb-st st-' + esc(it.status) + '">' + esc(it.statusLabel) + '</span></div>' +
          (it.reply ? '<div class="fb-reply"><b>Atsakymas:</b> ' + esc(it.reply) + '</div>' : '') + '</li>';
      }).join('') + (items.length > shown.length ? '<li><button type="button" class="fb-more" data-fb-all>Rodyti visus (' + items.length + ') ↓</button></li>'
        : showAll && items.length > 2 ? '<li><button type="button" class="fb-more" data-fb-all>Rodyti tik naujausius ↑</button></li>' : '');
    }
    list.addEventListener('click', function (e) {
      if (!e.target.closest('[data-fb-all]')) return;
      e.stopPropagation(); showAll = !showAll; renderList();
    }, true);
    function upsert(it) {
      if (!it) return;
      var i = items.findIndex(function (x) { return x.id === it.id; });
      if (i === -1) items.unshift(it); else items[i] = it;
      renderList();
    }
    function load() {
      fetch(cfg.api + (cfg.api.indexOf('?') === -1 ? '?' : '&') + 'list=1', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); }).then(function (d) { items = d.items || []; loaded = true; renderList(); }).catch(function () {});
    }

    // ---------- Rašymas: išsaugoma pati ----------
    function renderImgs() {
      imgs.innerHTML = cur.images.map(function (im) {
        return '<span class="fb-img"' + (im.tmp ? ' data-tmp="1"' : '') + '><a href="' + esc(im.full || im.thumb) + '" target="_blank" rel="noopener"><img src="' + esc(im.thumb) + '" alt=""></a>' +
          (im.tmp ? '<i class="fb-spin" aria-label="Įkeliama"></i>' : '<button type="button" class="fb-img-x" data-del="' + im.id + '" aria-label="Pašalinti nuotrauką">×</button>') + '</span>';
      }).join('');
    }
    function showEditing() {
      editing.hidden = !cur.id;
      if (cur.id) {
        var it = items.find(function (x) { return x.id === cur.id; });
        editing.querySelector('span').textContent = 'Taisomas: ' + (it ? it.title : '#' + cur.id);
      }
      renderList();
    }
    function save() {
      clearTimeout(timer); timer = null;
      var body = text.value;
      if (!cur.id && !body.trim()) return chain;
      chain = chain.then(function () {
        setState('Saugoma…', 'busy');
        return send({ action: 'save', id: cur.id || '', body: body }).then(function (d) {
          if (!d.ok) { setState(d.error || 'Neišsaugota', 'err'); return; }
          if (d.item) { if (!cur.id) cur.id = d.item.id; upsert(d.item); showEditing(); }
          setState('Išsaugota ✓', 'ok');
        }).catch(function () { setState('Nėra ryšio - neišsaugota', 'err'); });
      });
      return chain;
    }
    function startNew() {
      cur = { id: 0, images: [] }; text.value = ''; renderImgs(); showEditing(); text.focus();
    }
    function commit() {
      save().then(function () {
        if (!cur.id) return;
        setState('Išsaugota ✓ - galite rašyti kitą', 'ok');
        startNew();
      });
    }
    text.addEventListener('input', function () { setState('Rašoma…', 'busy'); clearTimeout(timer); timer = setTimeout(save, 700); });
    text.addEventListener('blur', function () { if (timer) save(); });
    text.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); commit(); }
    });
    $('fbSend').addEventListener('click', commit);
    $('fbNew').addEventListener('click', function () { save().then(startNew); });

    // ---------- Nuotraukos: Ctrl+V, nutempti, „📎“ ----------
    function upload(file) {
      if (!cfg.images) { setState('Nuotraukos dar neįjungtos', 'err'); return; }
      if (!/^image\//.test(file.type)) return;
      if (file.size > 15 * 1024 * 1024) { setState('Nuotrauka per didelė (iki 15 MB)', 'err'); return; }
      var tmp = { tmp: true, thumb: URL.createObjectURL(file) };
      cur.images.push(tmp); renderImgs();
      var target = cur;
      chain = chain.then(function () {
        clearTimeout(timer); timer = null;
        setState('Įkeliama nuotrauka…', 'busy');
        var f = file.name ? file : new File([file], 'ekranas.png', { type: file.type });
        return send({ action: 'image', id: target.id || '', body: text.value, file: f }).then(function (d) {
          target.images = target.images.filter(function (x) { return x !== tmp; });
          if (d.item) { if (!target.id) target.id = d.item.id; target.images = d.item.images; upsert(d.item); }
          if (target === cur) { renderImgs(); showEditing(); }
          setState(d.ok ? 'Nuotrauka pridėta ✓' : (d.error || 'Nepavyko įkelti'), d.ok ? 'ok' : 'err');
        }).catch(function () { target.images = target.images.filter(function (x) { return x !== tmp; }); renderImgs(); setState('Nėra ryšio - nuotrauka neįkelta', 'err'); });
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
        cur = { id: it.id, images: it.images.slice() }; text.value = it.body; renderImgs(); showEditing();
        text.focus(); text.setSelectionRange(text.value.length, text.value.length);
        setState('', '');
      });
    }
    list.addEventListener('click', function (e) { var li = e.target.closest('[data-id]'); if (li) edit(+li.getAttribute('data-id')); });
    list.addEventListener('keydown', function (e) { var li = e.target.closest('[data-id]'); if (li && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); edit(+li.getAttribute('data-id')); } });
    editing.addEventListener('click', function (e) {
      if (!e.target.closest('.fb-del') || !cur.id) return;
      if (!confirm('Ištrinti šį atsiliepimą su nuotraukomis?')) return;
      var id = cur.id;
      send({ action: 'delete', id: id }).then(function (d) {
        if (!d.ok) return;
        items = items.filter(function (x) { return x.id !== id; }); startNew(); setState('Ištrinta', 'ok');
      });
    });

    // ---------- Atidaryti / uždaryti ----------
    function toggle(open) {
      panel.hidden = !open;
      box.classList.toggle('open', open);
      fab.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) { if (!loaded) load(); text.focus(); }
      else if (timer) save();
    }
    fab.addEventListener('click', function () { toggle(panel.hidden); });
    panel.querySelector('.fb-close').addEventListener('click', function () { toggle(false); fab.focus(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { toggle(false); fab.focus(); } });
    window.addEventListener('pagehide', function () { if (timer) save(); });
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
