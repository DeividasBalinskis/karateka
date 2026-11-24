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
    var qIn = $('fbQ'), stSel = $('fbSt'), placeSel = $('fbPlace'), allList = $('fbAllList'), allBtn = $('fbAllBtn'), allN = $('fbAllN');
    var write = $('fbWrite'), allView = $('fbAll'), title = $('fbTitle'), titleWrite = title.textContent;
    var fabN = fab.querySelector('.fb-fab-n');
    var cur = { id: 0, images: [] }, items = [], timer = null, chain = Promise.resolve();
    // Rašymo vaizdas: tekstas + „Jūsų atsiliepimai“ (paspaudus - taisyti). Personalui - vaizdas „Visi atsiliepimai“ (tik skaityti) su filtrais.
    var view = 'write', allLoaded = false, allItems = [], openAll = {}, here = null, groups = {}, shown = 20, mineAll = false, restoring = false;
    var flt = { p: '', st: 'open', q: '' };   // p: „*“ - visur, „budo|“ - visa grupė, „budo|Tvarkaraštis“ - skiltis

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

    // ---------- Išlieka pereinant į kitą puslapį: atidarytas langas, vaizdas, filtrai, tekstas ----------
    function remember() {
      try { sessionStorage.setItem(SKEY, JSON.stringify({ open: !panel.hidden, view: view, flt: flt, id: cur.id, text: text.value, at: Date.now() })); } catch (e) {}
      badge();
    }
    // Ant 💡: ❓N - klausimai, laukiantys jūsų atsakymo (v1.90); ✎ - nebaigtas tekstas
    var qCount = +cfg.questions || 0;
    function badge() {
      if (!fabN) return;
      var d = panel.hidden && text.value.trim() !== '', q = qCount > 0;
      fabN.hidden = !q && !d; fabN.textContent = q ? '❓' + qCount : (d ? '✎' : '');
      fabN.classList.toggle('q', q); fab.classList.toggle('has-draft', d); fab.classList.toggle('has-q', q);
      if (q) fab.title = T('Laukia jūsų atsakymo: {n}', { n: qCount });
    }
    function recall() {
      try { var s = JSON.parse(sessionStorage.getItem(SKEY) || 'null'); return s && Date.now() - (s.at || 0) < KEEP ? s : null; } catch (e) { return null; }
    }

    // ---------- Bendra: vieta, eilutė ----------
    function isHere(it) { return !!here && it.g === here.g && it.s === here.s; }
    function byHere(a, b) { return ((b.id === cur.id) - (a.id === cur.id)) || (isHere(b) - isHere(a)) || ((!!here && b.g === here.g) - (!!here && a.g === here.g)) || (b.id - a.id); }
    function where(it, full) {
      var w = (full ? it.gl + ' › ' : '') + (it.sec && it.sec.indexOf(it.sl) === 0 ? it.sec : it.sl);
      return '<span class="fb-where' + (isHere(it) ? ' here' : '') + '">' + (isHere(it) ? '📍 ' : '') + esc(w) + '</span>';
    }
    function more(total, n, attr, label) {
      return total > n ? '<li><button type="button" class="fb-more" ' + attr + '>' + esc(label) + ' ↓</button></li>' : '';
    }

    // ---------- v1.89: pataisyta - autorius įvertina („Patinka“ - paliekama, „Grąžinti kaip buvo“ - pakeitimas atšaukiamas ir vėl įkeliama) ----------
    function verdictHtml(it) {
      if (it.canVerdict) {
        return '<div class="fb-verdict"><span>' + esc(T('Ar patinka pakeitimas?')) + '</span>' +
          '<button type="button" class="fb-vbtn ok" data-verdict="ok" data-vid="' + it.id + '">👍 ' + esc(T('Patinka')) + '</button>' +
          '<button type="button" class="fb-vbtn back" data-verdict="revert" data-vid="' + it.id + '">↩ ' + esc(T('Grąžinti kaip buvo')) + '</button></div>';
      }
      var txt = { ok: '👍 ' + T('Patiko - paliekama'), revert: '↩ ' + T('Bus grąžinta kaip buvo'), reverted: '↩ ' + T('Grąžinta kaip buvo') }[it.verdict];
      return txt ? '<div class="fb-verdict done">' + esc(txt) + '</div>' : '';
    }
    list.addEventListener('click', function (e) {
      var b = e.target.closest('[data-verdict]'); if (!b) return;
      e.stopPropagation(); b.disabled = true;
      send({ action: 'verdict', id: b.getAttribute('data-vid'), verdict: b.getAttribute('data-verdict') }).then(function (d) {
        if (d.item) { upsert(d.item); setState(d.item.verdict === 'ok' ? T('Ačiū! Paliekame.') : T('Gerai - grąžinsime kaip buvo.'), 'ok'); }
        else { b.disabled = false; setState(d.error || T('Neišsaugota'), 'err'); }
      }).catch(function () { b.disabled = false; setState(T('Nėra ryšio - neišsaugota'), 'err'); });
    }, true);

    // ---------- v1.90: klausimas jums - paryškinta, atsakoma čia pat ----------
    function questionHtml(it) {
      return '<li class="fb-item fb-qitem" data-qid="' + it.id + '"><div class="fb-qhead">❓ ' + esc(T('Reikia jūsų atsakymo')) + '</div>' +
        '<div class="fb-item-title">' + esc(it.title) + '</div>' +
        '<div class="fb-qtext"><b>' + esc(T('Klausimas:')) + '</b> ' + esc(it.reply || '').replace(/\n/g, '<br>') + '</div>' +
        '<textarea class="fb-qans" rows="3" maxlength="2000" placeholder="' + esc(T('Jūsų atsakymas…')) + '" aria-label="' + esc(T('Jūsų atsakymas')) + '"></textarea>' +
        '<button type="button" class="btn btn-primary btn-sm fb-qsend" data-answer="' + it.id + '">' + esc(T('Atsakyti')) + '</button></li>';
    }
    list.addEventListener('click', function (e) {
      if (e.target.closest('.fb-qitem')) e.stopPropagation();   // klausimo kortelė - ne taisymui
      var b = e.target.closest('[data-answer]'); if (!b) return;
      var li = b.closest('.fb-qitem'), ta = li.querySelector('.fb-qans'), ans = ta.value.trim();
      if (!ans) { ta.focus(); return; }
      b.disabled = true;
      send({ action: 'answer', id: b.getAttribute('data-answer'), answer: ans }).then(function (d) {
        if (d.item) { upsert(d.item); setState(T('Ačiū! Atsakymas išsiųstas.'), 'ok'); }
        else { b.disabled = false; setState(d.error || T('Neišsaugota'), 'err'); }
      }).catch(function () { b.disabled = false; setState(T('Nėra ryšio - neišsaugota'), 'err'); });
    }, true);
    list.addEventListener('keydown', function (e) { if (e.target.classList && e.target.classList.contains('fb-qans')) e.stopPropagation(); }, true);

    // ---------- Jūsų atsiliepimai (rašymo vaizde): 3 naujausi, pirmiausia - šios vietos ----------
    function renderList() {
      mine.hidden = !items.length;
      // v1.90: klausimai jums - visada pirmi ir visi matomi; toliau - laukiantys įvertinimo; #164: kitų - tik paskutinis
      // + laukiantys įvertinimo („Patinka“ / „Grąžinti“) - irgi visi; iš kitų - tik paskutinis parašytas
      var qs = items.filter(function (it) { return it.canAnswer; });
      var vs = items.filter(function (it) { return !it.canAnswer && it.canVerdict; }).sort(byHere);
      var rest = items.filter(function (it) { return !it.canAnswer && !it.canVerdict; }).sort(function (a, b) { return ((b.id === cur.id) - (a.id === cur.id)) || (b.id - a.id); });
      var list0 = qs.concat(vs, rest), view0 = mineAll ? list0 : qs.concat(vs, rest.slice(0, 1));
      qCount = qs.length; badge();
      list.innerHTML = view0.map(function (it) {
        if (it.canAnswer) return questionHtml(it);
        return '<li class="fb-item' + (it.id === cur.id ? ' on' : '') + '" data-id="' + it.id + '" tabindex="0" role="button" title="' + esc(T('Paspauskite - taisyti')) + '">' +
          '<div class="fb-item-top"><span class="fb-item-title">' + esc(it.title) + '</span>' + (it.images.length ? '<span class="fb-item-img" title="' + esc(T('Nuotraukos')) + '">🖼 ' + it.images.length + '</span>' : '') + '</div>' +
          '<div class="fb-item-meta"><span>' + where(it, here && it.g !== here.g) + ' · ' + esc(it.date) + '</span><span class="fb-st st-' + esc(it.status) + '">' + esc(it.statusLabel) + '</span></div>' +
          (it.reply ? '<div class="fb-reply"><b>' + esc(T('Atsakymas:')) + '</b> ' + esc(it.reply) + '</div>' : '') +
          (it.answer ? '<div class="fb-myanswer"><b>' + esc(T('Jūsų atsakymas:')) + '</b> ' + esc(it.answer) + '</div>' : '') + verdictHtml(it) + '</li>';
      }).join('') + more(list0.length, view0.length, 'data-mine-all', T('Rodyti visus ({n})', { n: list0.length }));
      fit();
    }

    // ---------- Visi atsiliepimai (personalui): vieta, būsena, paieška - išskleidžiami sąrašai ----------
    function stOk(it) {
      if (flt.st === 'open') return it.status === 'new' || it.status === 'planned' || it.status === 'question';
      if (flt.st === 'done') return it.status === 'done' || it.status === 'declined';
      return true;
    }
    function qOk(it) {
      if (!flt.q.trim()) return true;
      var h = fold([it.title, it.body, it.author, it.reply, it.gl, it.sl, it.sec, '#' + it.id].join(' '));
      return fold(flt.q).split(/\s+/).every(function (w) { return !w || h.indexOf(w) !== -1; });
    }
    function inPlace(it) {
      if (flt.p === '*' || !flt.p) return true;
      var i = flt.p.indexOf('|'), g = flt.p.slice(0, i), s = flt.p.slice(i + 1);
      return it.g === g && (!s || it.s === s);
    }
    function renderPlaces(base) {
      if (!placeSel) return;
      var gc = {}, sc = {}, sl = {}, keys = Object.keys(groups);
      base.forEach(function (it) { gc[it.g] = (gc[it.g] || 0) + 1; sc[it.g] = sc[it.g] || {}; sc[it.g][it.s] = (sc[it.g][it.s] || 0) + 1; sl[it.g + '|' + it.s] = it.sl; });
      if (here) { sc[here.g] = sc[here.g] || {}; if (!sc[here.g][here.s]) sc[here.g][here.s] = 0; sl[here.g + '|' + here.s] = here.sl; }
      if (here) keys.sort(function (a, b) { return (b === here.g) - (a === here.g); });
      var opt = function (v, label, n, pin) { return '<option value="' + esc(v) + '"' + (flt.p === v ? ' selected' : '') + '>' + (pin ? '📍 ' : '') + esc(label) + ' (' + n + ')</option>'; };
      var html = opt('*', T('Visur'), base.length, false);
      keys.forEach(function (g) {
        if (!gc[g] && !(here && here.g === g)) return;
        var subs = Object.keys(sc[g] || {}).sort(function (a, b) {
          var ha = here && here.g === g && here.s === a, hb = here && here.g === g && here.s === b;
          return (hb - ha) || (sc[g][b] - sc[g][a]) || a.localeCompare(b);
        });
        html += '<optgroup label="' + esc(groups[g]) + '">' + opt(g + '|', T('Visa {g}', { g: groups[g] }), gc[g] || 0, !!here && here.g === g) +
          subs.map(function (s) { return opt(g + '|' + s, '   ' + sl[g + '|' + s], sc[g][s], !!here && here.g === g && here.s === s); }).join('') + '</optgroup>';
      });
      placeSel.innerHTML = html;
      if (placeSel.value !== flt.p) placeSel.value = flt.p;
    }
    function renderAll() {
      if (!allList) return;
      if (!allLoaded) { allList.innerHTML = '<li class="muted small">' + esc(T('Kraunama…')) + '</li>'; return; }
      var base = allItems.filter(function (it) { return stOk(it) && qOk(it); });
      renderPlaces(base);
      var out = base.filter(inPlace).sort(byHere), v = out.slice(0, shown);
      if (allN) { var n = allItems.filter(function (it) { return it.status === 'new' || it.status === 'planned'; }).length; allN.hidden = !n; allN.textContent = n; }
      if (!out.length) {
        allList.innerHTML = '<li class="muted small">' + esc(allItems.length ? T('Nieko nerasta.') : T('Atsiliepimų dar nėra.')) +
          (allItems.length && (flt.p !== '*' || flt.q || flt.st !== 'all') ? ' <button type="button" class="fb-link" data-fb-reset>' + esc(T('Rodyti visus')) + ' →</button>' : '') + '</li>';
        return;
      }
      allList.innerHTML = v.map(function (it) {
        var o = !!openAll[it.id];
        return '<li class="fb-item fb-ro' + (o ? ' open' : '') + '" data-all-id="' + it.id + '" tabindex="0" role="button" title="' + esc(T('Paspauskite - visas tekstas')) + '">' +
          '<div class="fb-item-top"><span class="fb-item-title">' + esc(it.title) + '</span>' + (it.images.length ? '<span class="fb-item-img">🖼 ' + it.images.length + '</span>' : '') + '</div>' +
          '<div class="fb-item-meta"><span>' + where(it, flt.p === '*' || !flt.p) + ' · ' + esc(it.author || '') + (it.mine ? ' · ' + esc(T('jūsų')) : '') + ' · ' + esc(it.date) + '</span><span class="fb-st st-' + esc(it.status) + '">' + esc(it.statusLabel) + '</span></div>' +
          (o ? '<div class="fb-ro-body">' + esc(it.body).replace(/\n/g, '<br>') + '</div>' +
            (it.images.length ? '<div class="fb-ro-imgs">' + it.images.map(function (im) { return '<a href="' + esc(im.full) + '" target="_blank" rel="noopener"><img src="' + esc(im.thumb) + '" alt=""></a>'; }).join('') + '</div>' : '') +
            (it.href ? '<a class="fb-go" href="' + esc(it.href) + '">' + esc(T('Atidaryti vietą')) + ' →</a>' : '') : '') +
          (it.reply ? '<div class="fb-reply"><b>' + esc(T('Atsakymas:')) + '</b> ' + esc(it.reply) + '</div>' : '') + '</li>';
      }).join('') + more(out.length, v.length, 'data-fb-more', T('Rodyti dar ({n})', { n: out.length - v.length }));
    }
    function setView(v, quiet) {
      if (!allView) v = 'write';
      view = v;
      write.hidden = v === 'all'; allView && (allView.hidden = v !== 'all');
      title.textContent = v === 'all' ? title.getAttribute('data-all') : titleWrite;
      if (v === 'all') { if (!flt.p && here) flt.p = here.g + '|'; if (stSel) stSel.value = flt.st; if (qIn) qIn.value = flt.q; renderAll(); }
      remember();
    }
    function listUrl(all) {
      var tn = tabsNow();
      return cfg.api + (cfg.api.indexOf('?') === -1 ? '?' : '&') + 'list=1' + (all ? '&all=1' : '') + '&site=' + encodeURIComponent(cfg.site || 'karateka') +
        '&here=' + encodeURIComponent(location.pathname + location.search + location.hash) + '&sk=' + encodeURIComponent(tn.sk) + '&sec=' + encodeURIComponent(tn.sec);
    }
    function fetchList(all) {
      return fetch(listUrl(all), { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
        here = d.here || here; groups = d.groups || groups;
        return d.items || [];
      });
    }
    function load() {
      var p = fetchList(false).then(function (d) { items = d; afterRestore(); renderList(); }).catch(function () {});
      if (allView) {
        fetchList(true).then(function (d) { allItems = d; allLoaded = true; if (!flt.p && here) flt.p = here.g + '|'; renderAll(); }).catch(function () {});
      }
      return p;
    }
    if (allBtn) allBtn.addEventListener('click', function () { flt.p = here ? here.g + '|' : ''; shown = 20; setView('all'); });
    if ($('fbBack')) $('fbBack').addEventListener('click', function () { setView('write'); text.focus(); });
    if (placeSel) placeSel.addEventListener('change', function () { flt.p = placeSel.value; shown = 20; remember(); renderAll(); });
    if (stSel) stSel.addEventListener('change', function () { flt.st = stSel.value; shown = 20; remember(); renderAll(); });
    if (qIn) qIn.addEventListener('input', function () { flt.q = qIn.value; shown = 20; remember(); renderAll(); });
    if (allList) {
      allList.addEventListener('click', function (e) {
        if (e.target.closest('[data-fb-more]')) { shown += 20; renderAll(); return; }
        if (e.target.closest('[data-fb-reset]')) { flt.p = '*'; flt.q = ''; flt.st = 'all'; if (qIn) qIn.value = ''; if (stSel) stSel.value = 'all'; remember(); renderAll(); return; }
        var ro = e.target.closest('[data-all-id]');
        if (ro && !e.target.closest('a')) { var id = +ro.getAttribute('data-all-id'); openAll[id] = !openAll[id]; renderAll(); }
      });
      allList.addEventListener('keydown', function (e) {
        var ro = e.target.closest('[data-all-id]');
        if (!ro || (e.key !== 'Enter' && e.key !== ' ')) return;
        e.preventDefault(); var id = +ro.getAttribute('data-all-id'); openAll[id] = !openAll[id]; renderAll();
      });
    }
    list.addEventListener('click', function (e) {
      if (e.target.closest('[data-mine-all]')) { e.stopPropagation(); mineAll = true; renderList(); }
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
      var body = text.value, target = cur;   // kuriam atsiliepimui (jei tuo metu pradedamas naujas - senasis vis tiek išsisaugo)
      if (!target.id && !body.trim()) return chain;
      chain = chain.then(function () {
        setState(T('Saugoma…'), 'busy');
        return send({ action: 'save', id: target.id || '', body: body }, keepalive).then(function (d) {
          if (!d.ok) { setState(d.error || T('Neišsaugota'), 'err'); return; }
          if (d.item) { if (!target.id) target.id = d.item.id; upsert(d.item); if (target === cur) { showEditing(); remember(); } }
          setState(T('Išsaugota ✓'), 'ok');
        }).catch(function () { setState(T('Nėra ryšio - neišsaugota'), 'err'); });
      });
      return chain;
    }
    function startNew() {
      restoring = false;   // žmogus jau pats pradėjo naują - atkūrimas nebereikalingas
      cur = { id: 0, images: [] }; text.value = ''; renderImgs(); showEditing(); remember(); text.focus();
    }
    function commit() {
      var t = cur, body = text.value;
      save().then(function () {
        if (!t.id || cur !== t || text.value !== body) return;   // neišsaugota arba jau rašoma toliau - tekstas lieka
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
    // „+ Naujas“: taisomas (jau išsaugotas) atsiliepimas išsisaugo fone, naujas pradedamas iš karto - spėtas rašyti tekstas nedingsta
    $('fbNew').addEventListener('click', function () { if (cur.id) { save(); startNew(); } else save().then(startNew); });

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
      var it = items.find(function (x) { return x.id === id; }); if (!it || !it.mine) return;   // iš kito įrenginio - tik įvertinti
      restoring = false;
      var open = function () {
        cur = { id: it.id, images: it.images.slice() }; text.value = it.body; renderImgs(); showEditing(); remember();
        text.focus(); text.setSelectionRange(text.value.length, text.value.length);
        setState('', '');
      };
      if (cur.id || !text.value.trim()) { save(); open(); } else save().then(open);
    }
    list.addEventListener('click', function (e) { var li = e.target.closest('[data-id]'); if (li) edit(+li.getAttribute('data-id')); });
    list.addEventListener('keydown', function (e) { var li = e.target.closest('[data-id]'); if (li && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); edit(+li.getAttribute('data-id')); } });
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
    // #167: langas visada telpa į matomą ekrano dalį (planšetėje su klaviatūra irgi) - viršus su „Atsiliepimas“ ir × lieka matomas
    function fit() {
      if (panel.hidden) return;
      panel.style.maxHeight = '';
      var vv = window.visualViewport, top = vv ? vv.offsetTop : 0, bottom = vv ? vv.offsetTop + vv.height : window.innerHeight;
      var r = panel.getBoundingClientRect(), avail = Math.floor(Math.min(r.bottom, bottom) - top - 8);
      if (avail > 120 && r.height > avail) panel.style.maxHeight = avail + 'px';
    }
    window.addEventListener('resize', fit);
    if (window.visualViewport) { window.visualViewport.addEventListener('resize', fit); window.visualViewport.addEventListener('scroll', fit); }
    text.addEventListener('focus', function () { setTimeout(fit, 350); });   // klaviatūra atsidaro šiek tiek vėliau
    function toggle(open, quiet) {
      panel.hidden = !open;
      box.classList.toggle('open', open);
      fab.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) { load(); fit(); if (!quiet && view === 'write') text.focus(); }
      else if (timer) save();
      remember();
    }
    fab.addEventListener('click', function () {
      if (panel.hidden && !restoring) { mineAll = false; setView('write', true); }   // naujai atidarius - rašymas; „Visi“ - vėl nuo šios vietos
      toggle(panel.hidden);
    });
    panel.querySelector('.fb-close').addEventListener('click', function () { toggle(false); fab.focus(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { toggle(false); fab.focus(); } });
    window.addEventListener('pagehide', function () { if (timer) save(true); remember(); });

    // Grįžimas iš kito puslapio: tas pats langas, filtrai ir tekstas (telefone langas neatsidaro pats - užimtų visą ekraną; ✎ ant mygtuko)
    badge();
    // Nuoroda su #atsiliepimas (pranešimas „Reikia atsakymo“, „Reikia padaryti“) - langas atsidaro iš karto
    function openFromHash() { if (location.hash === '#atsiliepimas' && panel.hidden) { mineAll = false; setView('write', true); toggle(true, true); } }
    openFromHash();
    window.addEventListener('hashchange', openFromHash);   // ta pati puslapio nuoroda („Reikia padaryti“ → #atsiliepimas) puslapio neperkrauna
    var saved = recall();
    if (saved && location.hash !== '#atsiliepimas') {
      if (saved.flt) flt = { p: String(saved.flt.p || ''), st: saved.flt.st || 'open', q: String(saved.flt.q || '') };
      cur.id = saved.id || 0; text.value = saved.text || '';
      restoring = !!(cur.id || text.value.trim());
      setView(saved.view === 'all' ? 'all' : 'write', true);
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
